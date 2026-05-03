<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AddressImportService
{
    /**
     * Импорт адресов из CSV или XLS/XLSX файла.
     * Поддерживаемые заголовки (рус/англ):
     *   street/улица, building/дом, apartment/квартира, city/город,
     *   subscriber_name/абонент/фио, phone/телефон, contract_no/договор
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'csv'         => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->parseXlsx($file->getRealPath()),
            default       => throw new \InvalidArgumentException("Неподдерживаемый формат: {$extension}"),
        };

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

    /** Парсинг CSV через встроенный PHP */
    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException("Не удалось открыть файл");
        }

        // Определяем разделитель
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $lineNum = 0;
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($lineNum === 0) {
                $headers = array_map(fn($h) => mb_strtolower(trim($h)), $line);
            } else {
                if (count($line) >= count($headers)) {
                    $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
                }
            }
            $lineNum++;
        }

        fclose($handle);
        return $rows;
    }

    /** Парсинг XLSX через ZipArchive + XML (без сторонних библиотек) */
    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Не удалось открыть XLSX файл");
        }

        // Читаем shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $sharedStrings[] = (string) ($si->t ?? implode('', array_map('strval', $si->r->t ?? [])));
            }
        }

        // Читаем первый лист
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            throw new \RuntimeException("Лист не найден в XLSX файле");
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows  = [];
        $headers = [];

        foreach ($sheet->sheetData->row as $rowIndex => $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $type  = (string) $cell['t'];
                $value = (string) $cell->v;

                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }
                $rowData[] = $value;
            }

            if ($rowIndex === 0) {
                $headers = array_map(fn($h) => mb_strtolower(trim($h)), $rowData);
            } else {
                if (!empty(array_filter($rowData))) {
                    $padded = array_pad($rowData, count($headers), '');
                    $rows[] = array_combine($headers, array_slice($padded, 0, count($headers)));
                }
            }
        }

        return $rows;
    }

    /** Маппинг заголовков (рус/англ) */
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
            'contract_no'     => ['contract_no', 'договор', 'contract', 'лицевой', '№договора'],
        ];

        $result   = [];
        $lowerRow = array_change_key_case($row, CASE_LOWER);

        foreach ($map as $field => $aliases) {
            foreach ($aliases as $alias) {
                $alias = mb_strtolower($alias);
                if (isset($lowerRow[$alias]) && $lowerRow[$alias] !== '') {
                    $result[$field] = trim((string) $lowerRow[$alias]);
                    break;
                }
            }
        }

        return $result;
    }
}
