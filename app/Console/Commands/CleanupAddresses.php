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

        // Always clear tickets.apartment = '0' (old private-house marker)
        if ($apply) {
            $cleared = DB::table('tickets')->where('apartment', '0')->update(['apartment' => null]);
            $this->info("  Cleared {$cleared} tickets with apartment='0'");
        } else {
            $cnt = DB::table('tickets')->where('apartment', '0')->count();
            if ($cnt > 0) $this->line("  [dry-run] Would clear $cnt tickets with apartment='0'");
        }

        $this->newLine();

        $this->newLine();
        $this->cleanupStreetVariants($apply);
        $this->newLine();
        $this->mergeDuplicateStreetSpellings($apply);
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

    /**
     * Phase 3: причесать известные варианты написания одного и того же типа
     * улицы ("ул," опечатка вместо "ул.", "блв." vs "бул.", "кв-л" vs "квартал").
     * Компания слита из ~10 провайдеров с разными учётными системами — но, в
     * отличие от общего разнобоя (например, Макеевка вообще без префикса —
     * это осознанный формат целого города, его НЕ трогаем), эти три пары —
     * штучные опечатки/дубли одной и той же улицы под одним и тем же типом,
     * подтверждено вручную (см. память проекта). Меняется только ведущий
     * токен, остаток названия улицы не трогается.
     */
    private function cleanupStreetVariants(bool $apply): void
    {
        $this->info('Phase 3: known street-prefix spelling variants (ул,→ул. / блв.→бул. / кв-л→квартал)');

        $variantMap = ['ул,' => 'ул.', 'блв.' => 'бул.', 'кв-л' => 'квартал'];

        $updates = [];
        foreach ($variantMap as $from => $to) {
            $rows = DB::table('addresses')->where('street', 'like', $from . '%')->get(['id', 'street']);
            foreach ($rows as $r) {
                $rest = trim(mb_substr($r->street, mb_strlen($from)));
                $updates[] = ['id' => $r->id, 'old' => $r->street, 'new' => trim($to . ' ' . $rest)];
            }
        }

        if (empty($updates)) {
            $this->line('  Nothing to fix.');
            return;
        }

        $this->table(['id', 'было', 'станет'],
            collect($updates)->take(10)->map(fn($u) => [$u['id'], $u['old'], $u['new']])->toArray());
        $this->info('  ' . count($updates) . ' address records to update.');

        if (!$apply) {
            $this->comment('  [dry-run] Run with --apply to execute.');
            return;
        }

        if (!$this->option('force') && !$this->confirm('Apply street-prefix fixes to ' . count($updates) . ' records?')) {
            $this->info('Phase 3 skipped.');
            return;
        }

        $backupP3 = 'addresses_bak_p3_' . date('YmdHis');
        DB::statement("CREATE TABLE `{$backupP3}` SELECT * FROM `addresses`");
        $this->info("  Backup: {$backupP3}");

        foreach ($updates as $u) {
            DB::table('addresses')->where('id', $u['id'])->update(['street' => $u['new']]);
        }
        $this->info('  Updated ' . count($updates) . ' address records.');
    }

    /**
     * Phase 4: в пределах одного города одна и та же улица иногда встречается
     * под двумя написаниями (с префиксом "ул./пер./бул." и без) — как правило
     * потому, что массовый импорт когда-то создал её без префикса, а позже
     * кто-то вручную добавил несколько адресов через форму (там префикс по
     * умолчанию "ул."). Сливаем такие пары к более частому написанию —
     * НО только когда:
     *  - ровно 2 варианта написания (3+ варианта пропускаем: почти всегда это
     *    разные типы — квартал/посёлок/улица/бульвар с одним именем это разные
     *    физические объекты, не опечатка — см. пример "Ленина"/"пос. Ленина");
     *  - соотношение большинства к меньшинству не меньше 2:1 (иначе слишком
     *    похоже на две реально разные маленькие улицы с совпавшим именем —
     *    см. пример "Артема"/"ул. Артема", 6 против 5, не трогаем).
     * Название типа "Шахтерский" (квартал) и "Шахтерская" (улица) — это не
     * вариант написания одного и того же, а разные слова (род/окончание),
     * normalizeStreet() их и не группирует вместе.
     */
    private function mergeDuplicateStreetSpellings(bool $apply): void
    {
        $this->info('Phase 4: same street under 2 spellings within one city (merge to majority)');

        $cities = DB::table('addresses')->select('city')->distinct()->pluck('city');
        $merges = [];
        $skipped = [];

        foreach ($cities as $city) {
            $streets = DB::table('addresses')->where('city', $city)->select('street')->distinct()->pluck('street');
            $groups = [];
            foreach ($streets as $s) {
                $groups[\App\Models\Address::normalizeStreet($s)][] = $s;
            }
            foreach ($groups as $norm => $variants) {
                $variants = array_values(array_unique($variants));
                if (count($variants) < 2) continue;

                $counts = [];
                foreach ($variants as $v) {
                    $counts[$v] = DB::table('addresses')->where('city', $city)->where('street', $v)->count();
                }
                arsort($counts);

                if (count($counts) > 2) {
                    $skipped[] = [$city, $norm, $counts, '3+ вариантов — вероятно разные типы'];
                    continue;
                }

                $keys = array_keys($counts);
                $vals = array_values($counts);
                $ratio = $vals[1] > 0 ? $vals[0] / $vals[1] : INF;
                if ($ratio < 2) {
                    $skipped[] = [$city, $norm, $counts, 'неоднозначно (соотношение ' . round($ratio, 2) . ')'];
                    continue;
                }

                $merges[] = ['city' => $city, 'from' => $keys[1], 'to' => $keys[0], 'count' => $vals[1]];
            }
        }

        if (!empty($skipped)) {
            $this->line('  Пропущено (нужна ручная проверка, не тронуто):');
            foreach ($skipped as [$city, $norm, $counts, $reason]) {
                $this->line("    [{$city}] {$norm}: " . json_encode($counts, JSON_UNESCAPED_UNICODE) . " — {$reason}");
            }
        }

        if (empty($merges)) {
            $this->line('  Nothing to merge.');
            return;
        }

        $this->table(['Город', 'Из', 'В', 'Записей'],
            collect($merges)->map(fn($m) => [$m['city'], $m['from'], $m['to'], $m['count']])->toArray());
        $totalRows = array_sum(array_column($merges, 'count'));
        $this->info("  {$totalRows} address records to update across " . count($merges) . ' groups.');

        if (!$apply) {
            $this->comment('  [dry-run] Run with --apply to execute.');
            return;
        }

        if (!$this->option('force') && !$this->confirm("Merge {$totalRows} records into majority spelling?")) {
            $this->info('Phase 4 skipped.');
            return;
        }

        $backupP4 = 'addresses_bak_p4_' . date('YmdHis');
        DB::statement("CREATE TABLE `{$backupP4}` SELECT * FROM `addresses`");
        $this->info("  Backup: {$backupP4}");

        foreach ($merges as $m) {
            DB::table('addresses')->where('city', $m['city'])->where('street', $m['from'])
                ->update(['street' => $m['to']]);
        }
        $this->info("  Merged {$totalRows} records.");
    }
}