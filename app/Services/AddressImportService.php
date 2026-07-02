<?php

namespace App\Services;

use App\Models\{Address, Territory};
use Illuminate\Http\UploadedFile;

class AddressImportService
{
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'csv'         => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->parseXlsx($file->getRealPath()),
            default       => throw new \InvalidArgumentException("Неподдерживаемый формат: {$extension}"),
        };

        // Кэш территорий по имени
        $territories = Territory::pluck('id', 'name')->mapWithKeys(
            fn($id, $name) => [mb_strtolower($name) => $id]
        )->toArray();

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $index => $row) {
            try {
                $data = $this->mapRow($row);

                if (empty($data['street']) || empty($data['building'])) {
                    $skipped++;
                    continue;
                }

                // Определяем территорию по имени
                if (!empty($data['territory_name'])) {
                    $tName = mb_strtolower(trim($data['territory_name']));
                    $data['territory_id'] = $territories[$tName] ?? null;
                }
                unset($data['territory_name']);

                $existing = Address::where('street', $data['street'])
                    ->where('building', $data['building'])
                    ->where('apartment', $data['apartment'] ?? null)
                    ->first();

                if ($existing) {
                    $skipped++;
                } else {
                    Address::create($data);
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = "Строка " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Не удалось открыть файл");
        }

        // BOM removal
        $bom = fread($handle, 3);
        if ($bom !== "\xef\xbb\xbf") rewind($handle);

        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom !== "\xef\xbb\xbf") $firstLine = fread($handle, strlen($firstLine));
        rewind($handle);
        if ($bom !== "\xef\xbb\xbf") {}

        // Определяем разделитель
        $handle2 = fopen($path, 'r');
        $sample  = fgets($handle2);
        fclose($handle2);
        $delimiter = substr_count($sample, ';') >= substr_count($sample, ',') ? ';' : ',';

        $handle = fopen($path, 'r');
        // Пропускаем BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xef\xbb\xbf") rewind($handle);

        $lineNum = 0;
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line = array_map(fn($v) => trim($v), $line);
            if ($lineNum === 0) {
                $headers = array_map(fn($h) => mb_strtolower(trim($h)), $line);
            } else {
                if (count($line) >= count($headers) && !empty(array_filter($line))) {
                    $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
                }
            }
            $lineNum++;
        }
        fclose($handle);
        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        // Проверяем доступность ZipArchive
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(
                "Расширение ZipArchive не установлено. " .
                "Установите: apt install php8.2-zip && systemctl restart php8.2-fpm\n" .
                "Или используйте CSV формат для импорта."
            );
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Не удалось открыть XLSX файл");
        }

        // Shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $r) {
                        if (isset($r->t)) $parts[] = (string)$r->t;
                    }
                }
                $sharedStrings[] = implode('', $parts);
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            throw new \RuntimeException("Лист не найден в XLSX");
        }

        $sheet   = simplexml_load_string($sheetXml);
        $rows    = [];
        $headers = [];

        foreach ($sheet->sheetData->row as $rowIndex => $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $type  = (string)$cell['t'];
                $value = isset($cell->v) ? (string)$cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }
                $rowData[] = trim($value);
            }

            if ($rowIndex === 0) {
                $headers = array_map(fn($h) => mb_strtolower($h), $rowData);
            } elseif (!empty(array_filter($rowData))) {
                $padded  = array_pad($rowData, count($headers), '');
                $rows[]  = array_combine($headers, array_slice($padded, 0, count($headers)));
            }
        }

        return $rows;
    }

    private function mapRow(array $row): array
    {
        $map = [
            'city'            => ['city', 'город'],
            'street'          => ['street', 'улица', 'адрес'],
            'building'        => ['building', 'дом', 'house'],
            'apartment'       => ['apartment', 'квартира', 'flat', 'кв'],
            'entrance'        => ['entrance', 'подъезд'],
            'floor'           => ['floor', 'этаж'],
            'subscriber_name' => ['subscriber_name', 'абонент', 'фио', 'name', 'имя'],
            'phone'           => ['phone', 'телефон', 'тел'],
            'contract_no'     => ['contract_no', 'договор', 'contract', 'лицевой'],
            'territory_name'  => ['territory', 'территория'],  // ← новое поле
        ];

        $result   = [];
        $lowerRow = array_change_key_case($row, CASE_LOWER);

        foreach ($map as $field => $aliases) {
            foreach ($aliases as $alias) {
                $alias = mb_strtolower($alias);
                if (isset($lowerRow[$alias]) && $lowerRow[$alias] !== '') {
                    $result[$field] = trim((string)$lowerRow[$alias]);
                    break;
                }
            }
        }

        return $result;
    }
}
