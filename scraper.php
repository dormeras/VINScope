<?php
declare(strict_types=1);

function normalizeVin(string $vin): string {
    return strtoupper(trim($vin));
}

function scrapeDemoSources(string $vin): array {
    $vin = normalizeVin($vin);
    $dir = __DIR__ . '/sources';
    $files = glob($dir . '/*.html') ?: [];
    $sources = [];
    $signals = [];

    foreach ($files as $file) {
        // Достаем имя источника из названия файла (например, "taxi_registry")
        $sourceName = pathinfo($file, PATHINFO_FILENAME);
        $html = file_get_contents($file) ?: '';
        $rowsData = [];

        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            $rows = $xpath->query('//table[@data-source]/tbody/tr');
            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                if ($cells->length >= 3) {
                    $rowsData[] = [
                        $cells->item(0)->textContent, 
                        $cells->item(1)->textContent, 
                        $cells->item(2)->textContent
                    ];
                }
            }
        } else {
            preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $rowsData[] = [$m[1], $m[2], $m[3]];
            }
        }

        $found = false;

        foreach ($rowsData as $cells) {
            $rowVin = normalizeVin(strip_tags(html_entity_decode($cells[0])));
            if ($rowVin !== $vin) continue;

            $found = true;
            $record = [
                'vin' => $rowVin,
                'source' => trim(strip_tags(html_entity_decode($cells[1]))),
                'value' => trim(strip_tags(html_entity_decode($cells[2]))),
            ];
            $sources[] = $record;

            // Приводим текст к нижнему регистру для регистронезависимого поиска
            $lowerText = mb_strtolower($record['value'], 'UTF-8');

            if (str_contains($lowerText, 'такси')) {
                $signals[] = 'Источник сообщает о работе в такси';
            }
            if (str_contains($lowerText, 'коммерчес')) {
                $signals[] = 'Источник указывает на коммерческую эксплуатацию';
            }
            if (str_contains($lowerText, 'аренда')) {
                $signals[] = 'Источник содержит признак аренды';
            }
        }

        if (!$found) {
            $sources[] = [
                'vin' => $vin,
                'source' => $sourceName, // Теперь переменная объявлена и хранит название файла
                'value' => 'Совпадение не найдено',
            ];
        }
    }

    $taxi = count($signals) > 0;
    return [
        'vin' => $vin,
        'sourcesChecked' => count($files),
        'recordsFound' => count(array_filter($sources, fn($x) => $x['value'] !== 'Совпадение не найдено')),
        'taxiDetected' => $taxi,
        'taxiLabel' => $taxi ? 'Есть признаки коммерческого использования' : 'Прямых признаков такси в источниках нет',
        'signals' => array_values(array_unique($signals)),
        'sources' => $sources,
        'scrapedAt' => date('c'),
    ];
}