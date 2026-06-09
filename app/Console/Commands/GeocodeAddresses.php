<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeAddresses extends Command
{
    protected $signature   = 'geocode:addresses {--force : Re-geocode even if coords already set} {--limit=0 : Max addresses to process (0 = all)}';
    protected $description = 'Geocode addresses without coordinates using Nominatim (OpenStreetMap)';

    public function handle(): int
    {
        $query = Address::query();

        if (!$this->option('force')) {
            $query->whereNull('lat')->orWhereNull('lng');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total     = $query->count();
        $processed = 0;
        $found     = 0;

        $this->info("Geocoding {$total} addresses...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->each(function (Address $address) use (&$processed, &$found, $bar) {
            $q = implode(', ', array_filter([
                $address->city,
                $address->street,
                $address->building,
            ]));

            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'SP-Helpdesk/1.0 (geocoder)'])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q'            => $q,
                        'format'       => 'json',
                        'limit'        => 1,
                        'countrycodes' => 'ru',
                        'addressdetails' => 0,
                    ]);

                $data = $response->json();
                if (!empty($data[0]['lat'])) {
                    $address->update([
                        'lat' => round((float) $data[0]['lat'], 6),
                        'lng' => round((float) $data[0]['lon'], 6),
                    ]);
                    $found++;
                }
            } catch (\Throwable) {
                // skip on timeout or network error
            }

            $processed++;
            $bar->advance();

            // Nominatim: max 1 request per second
            if ($processed < 999) usleep(1_100_000);
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Found coords for {$found} of {$total} addresses.");

        return self::SUCCESS;
    }
}