<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupAddresses extends Command
{
    protected $signature = 'helpdesk:cleanup-addresses
                            {--apply : Apply changes (default is dry-run)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Phase 1: migrate apartment="0" tickets to building level. Phase 2: remove fake apartment records for private houses.';

    public function handle(): int
    {
        $apply = $this->option('apply');

        if (!$apply) {
            $this->warn('[DRY RUN] No changes will be made. Use --apply to execute.');
            $this->newLine();
        }

        // ── Phase 1: Fix apartment="0" records ─────────────────────────────
        $this->info('Phase 1: apartment="0" records (private house markers in old system)');

        $aptZeroBuildings = DB::table('addresses')
            ->where('apartment', '0')
            ->selectRaw('city, street, building, COUNT(*) as cnt')
            ->groupBy('city', 'street', 'building')
            ->orderBy('city')->orderBy('street')
            ->get();

        if ($aptZeroBuildings->isEmpty()) {
            $this->line('  Nothing to fix.');
        } else {
            $ticketsTotal = 0;
            $addrTotal    = 0;
            foreach ($aptZeroBuildings as $b) {
                $aptZeroIds = DB::table('addresses')
                    ->where('city', $b->city)->where('street', $b->street)
                    ->where('building', $b->building)->where('apartment', '0')
                    ->pluck('id');

                $ticketsTotal += DB::table('tickets')
                    ->whereIn('address_id', $aptZeroIds)
                    ->whereNull('deleted_at')
                    ->count();
                $addrTotal += $b->cnt;
            }

            $this->table(
                ['City', 'Street', 'Building', 'Apt=0 records'],
                $aptZeroBuildings->map(fn($b) => [$b->city, $b->street, $b->building, $b->cnt])->toArray()
            );
            $this->info("  {$aptZeroBuildings->count()} buildings · {$addrTotal} address records · ~{$ticketsTotal} tickets to migrate");

            if ($apply) {
                if (!$this->option('force') && !$this->confirm("Migrate and remove apartment=\"0\" records?")) {
                    $this->info('Phase 1 skipped.');
                } else {
                    $backupP1 = 'addresses_bak_p1_' . date('YmdHis');
                    DB::statement("CREATE TABLE `{$backupP1}` SELECT * FROM `addresses`");
                    $this->info("  Backup: {$backupP1}");

                    $migratedTickets = 0;
                    $deletedAddrs    = 0;
                    foreach ($aptZeroBuildings as $b) {
                        $aptZeroIds = DB::table('addresses')
                            ->where('city', $b->city)->where('street', $b->street)
                            ->where('building', $b->building)->where('apartment', '0')
                            ->pluck('id');

                        // Ensure building-level record exists
                        $baseAddr = DB::table('addresses')
                            ->where('city', $b->city)->where('street', $b->street)->where('building', $b->building)
                            ->where(fn($q) => $q->whereNull('apartment')->orWhere('apartment', ''))
                            ->first();

                        if (!$baseAddr) {
                            $sample = DB::table($backupP1)
                                ->where('city', $b->city)->where('street', $b->street)->where('building', $b->building)
                                ->first();
                            $newId = DB::table('addresses')->insertGetId([
                                'city'         => $b->city,
                                'street'       => $b->street,
                                'building'     => $b->building,
                                'apartment'    => null,
                                'territory_id' => $sample->territory_id ?? null,
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ]);
                            $baseAddrId = $newId;
                        } else {
                            $baseAddrId = $baseAddr->id;
                        }

                        // Move tickets to building-level address
                        $migratedTickets += DB::table('tickets')
                            ->whereIn('address_id', $aptZeroIds)
                            ->update(['address_id' => $baseAddrId, 'apartment' => null]);

                        // Delete apt=0 address records
                        $deletedAddrs += DB::table('addresses')
                            ->whereIn('id', $aptZeroIds)
                            ->delete();
                    }

                    // Also clear tickets.apartment = '0' on any ticket
                    $cleared = DB::table('tickets')->where('apartment', '0')->update(['apartment' => null]);

                    $this->info("  Migrated {$migratedTickets} tickets, deleted {$deletedAddrs} address records, cleared {$cleared} ticket.apartment='0'.");
                }
            }
        }

        $this->newLine();

        // ── Phase 2: Remove fake apartment records (no ticket ever used them) ──
        $this->info('Phase 2: Buildings with fake apartment records (no real tickets)');

        $buildings = DB::table('addresses')
            ->whereNotNull('apartment')
            ->where('apartment', '!=', '')
            ->where('apartment', '!=', '0')
            ->selectRaw('city, street, building, COUNT(*) as apt_count')
            ->groupBy('city', 'street', 'building')
            ->orderBy('city')->orderBy('street')
            ->get();

        $this->info("Buildings with apartment records: {$buildings->count()}");

        $private  = [];
        $mkdCount = 0;

        foreach ($buildings as $b) {
            $hasRealApt = DB::table('tickets')
                ->join('addresses', 'tickets.address_id', '=', 'addresses.id')
                ->where('addresses.city', $b->city)
                ->where('addresses.street', $b->street)
                ->where('addresses.building', $b->building)
                ->whereNull('tickets.deleted_at')
                ->whereRaw("COALESCE(NULLIF(NULLIF(tickets.apartment,''),'0'), NULLIF(NULLIF(addresses.apartment,''),'0')) IS NOT NULL")
                ->exists();

            if ($hasRealApt) {
                $mkdCount++;
            } else {
                $private[] = $b;
            }
        }

        $this->info("MKD (keep): {$mkdCount}");
        $this->info("Private houses (clean up): " . count($private));
        $this->newLine();

        if (empty($private)) {
            $this->info('Nothing to clean up in Phase 2.');
            return 0;
        }

        $totalDel = array_sum(array_column((array) $private, 'apt_count'));
        $this->table(
            ['City', 'Street', 'Building', 'Apt records'],
            array_map(fn($b) => [$b->city, $b->street, $b->building, $b->apt_count], $private)
        );
        $this->newLine();
        $this->info("Total apartment records to delete: {$totalDel}");

        if (!$apply) {
            $this->comment('Run with --apply to execute these changes.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("Delete {$totalDel} apartment records from private houses?")) {
            $this->info('Aborted.');
            return 0;
        }

        $backupP2 = 'addresses_bak_p2_' . date('YmdHis');
        $this->info("Creating backup table: {$backupP2}...");
        DB::statement("CREATE TABLE `{$backupP2}` SELECT * FROM `addresses`");
        $this->info("Backup created.");

        $deleted = 0;
        foreach ($private as $b) {
            $count = DB::table('addresses')
                ->where('city', $b->city)
                ->where('street', $b->street)
                ->where('building', $b->building)
                ->whereNotNull('apartment')
                ->where('apartment', '!=', '')
                ->whereNotExists(function ($q) {
                    $q->from('tickets')->whereColumn('tickets.address_id', 'addresses.id');
                })
                ->delete();
            $deleted += $count;

            $hasBase = DB::table('addresses')
                ->where('city', $b->city)->where('street', $b->street)->where('building', $b->building)
                ->where(fn($q) => $q->whereNull('apartment')->orWhere('apartment', ''))
                ->exists();

            if (!$hasBase) {
                $sample = DB::table($backupP2)
                    ->where('city', $b->city)->where('street', $b->street)->where('building', $b->building)
                    ->first();
                if ($sample) {
                    DB::table('addresses')->insert([
                        'city'         => $b->city,
                        'street'       => $b->street,
                        'building'     => $b->building,
                        'apartment'    => null,
                        'territory_id' => $sample->territory_id ?? null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }

        $this->info("Deleted {$deleted} apartment records.");
        $this->info("Done! Backup table: {$backupP2}");
        return 0;
    }
}