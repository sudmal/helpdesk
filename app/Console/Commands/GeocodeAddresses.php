<?php

namespace App\Console\Commands;

use App\Models\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeAddresses extends Command
{
    protected $signature = 'addresses:geocode
        {--limit=300    : Максимум адресов для геокодирования}
        {--radius=100   : Максимальный радиус от центра в км (защита от мусора)}
        {--center=48.0835,37.9742 : Центр сети lat,lng}
        {--cleanup      : Обнулить адреса за пределами радиуса}';

    protected $description = 'Геокодирование адресов через Nominatim (OSM)';

    public function handle(): int
    {
        [$centerLat, $centerLng] = array_map('floatval', explode(',', $this->option('center')));
        $radius = (float) $this->option('radius');

        if ($this->option('cleanup')) {
            return $this->cleanup($centerLat, $centerLng, $radius);
        }

        $limit = (int) $this->option('limit');

        $addresses = Address::whereNull('lat')
            ->whereHas('tickets')
            ->withCount('tickets')
            ->orderByDesc('tickets_count')
            ->limit($limit)
            ->get();

        if ($addresses->isEmpty()) {
            $this->info('Нет адресов для геокодирования.');
            return 0;
        }

        $this->info("Геокодируем {$addresses->count()} адресов (Nominatim, ~1 сек/адрес)...");
        $bar = $this->output->createProgressBar($addresses->count());
        $bar->start();

        $ok = $fail = $skip = 0;
        foreach ($addresses as $addr) {
            $result = static::geocodeOne($addr, $centerLat, $centerLng, $radius);
            match ($result) {
                'ok'      => $ok++,
                'skip'    => $skip++,
                default   => $fail++,
            };
            $bar->advance();
            sleep(1);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Готово: {$ok} найдено, {$skip} вне радиуса, {$fail} не найдено.");
        return 0;
    }

    private function cleanup(float $centerLat, float $centerLng, float $radius): int
    {
        $bad = Address::whereNotNull('lat')->get()->filter(
            fn($a) => !static::withinRadius((float)$a->lat, (float)$a->lng, $centerLat, $centerLng, $radius)
        );

        if ($bad->isEmpty()) {
            $this->info('Мусорных координат не найдено.');
            return 0;
        }

        $this->info("Найдено {$bad->count()} адресов за пределами {$radius} км — обнуляем координаты...");
        foreach ($bad as $addr) {
            $addr->updateQuietly(['lat' => null, 'lng' => null]);
        }
        $this->info("Готово.");
        return 0;
    }

    public static function geocodeOne(
        Address $addr,
        float $centerLat = 48.0835,
        float $centerLng = 37.9742,
        float $maxRadius = 100
    ): string {
        $q = implode(', ', array_filter([$addr->city, $addr->street, $addr->building]));
        if (!$q) return 'fail';

        try {
            $res = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'HelpDesk-Geocoder/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q'            => $q,
                    'format'       => 'json',
                    'limit'        => 1,
                    'countrycodes' => 'ua,ru',
                    'viewbox'      => '36.5,49.0,39.5,47.0',
                    'bounded'      => 1,
                ]);

            $data = $res->json();
            if (empty($data[0]['lat'])) return 'fail';

            $lat = round((float) $data[0]['lat'], 6);
            $lng = round((float) $data[0]['lon'], 6);

            if (!static::withinRadius($lat, $lng, $centerLat, $centerLng, $maxRadius)) {
                return 'skip';
            }

            $addr->updateQuietly(['lat' => $lat, 'lng' => $lng]);
            return 'ok';
        } catch (\Throwable) {}

        return 'fail';
    }

    private static function withinRadius(
        float $lat1, float $lng1,
        float $lat2, float $lng2,
        float $maxKm
    ): bool {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $dist = 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $dist <= $maxKm;
    }
}
