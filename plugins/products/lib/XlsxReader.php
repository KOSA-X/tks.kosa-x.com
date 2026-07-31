<?php
/**
 * XlsxReader — minimalny czytnik plików .xlsx (bez zależności zewnętrznych).
 *
 * Czyta pierwszy arkusz skoroszytu do tablicy wierszy (indeksowanej od 0).
 * Obsługuje: sharedStrings, inlineStr, wartości liczbowe/tekstowe/logiczne,
 * formuły (zwraca zbuforowaną wartość <v>, nie liczy formuł).
 *
 * Użycie:
 *   $rows = XlsxReader::read('/sciezka/plik.xlsx');
 */
class XlsxReader
{
    /**
     * @return array<int, array<int, string>> wiersze → komórki (indeks kolumny od 0)
     */
    public static function read(string $path, int $sheetIndex = 0): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Brak rozszerzenia PHP zip (ZipArchive) — wymagane do odczytu .xlsx');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Nie można otworzyć pliku XLSX: '.$path);
        }

        // 1) Wspólne łańcuchy tekstowe
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $sx = simplexml_load_string($ssXml);
            if ($sx !== false) {
                foreach ($sx->si as $si) {
                    // <si><t>...</t></si> lub rich text <si><r><t>..</t></r>...</si>
                    if (isset($si->t)) {
                        $shared[] = (string)$si->t;
                    } else {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string)$r->t;
                        }
                        $shared[] = $txt;
                    }
                }
            }
        }

        // 2) Ścieżka arkusza o podanym indeksie (wg kolejności w workbook.xml)
        $sheetPath = self::resolveSheetPath($zip, $sheetIndex);

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Brak arkusza w pliku XLSX: '.$sheetPath);
        }

        // 3) Parsowanie komórek (XMLReader — niska pamięć przy dużych arkuszach)
        $rows = [];
        $reader = new XMLReader();
        $reader->XML($sheetXml);

        $rowIdx = -1;
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->name === 'row') {
                $rowIdx = (int)$reader->getAttribute('r') - 1;
                if ($rowIdx < 0) {
                    $rowIdx = count($rows);
                }
                $rows[$rowIdx] = [];
                continue;
            }
            if ($reader->name === 'c' && $rowIdx >= 0) {
                $ref  = (string)$reader->getAttribute('r'); // np. "B4"
                $type = (string)$reader->getAttribute('t'); // s|str|inlineStr|b|e|n(brak)
                $col  = self::colIndex($ref);

                $cellXml = $reader->readOuterXML();
                $value   = self::cellValue($cellXml, $type, $shared);

                $rows[$rowIdx][$col] = $value;
            }
        }
        $reader->close();

        // 4) Wyrównaj: gęste tablice z ciągłymi indeksami kolumn
        $out = [];
        $maxRow = $rows ? max(array_keys($rows)) : -1;
        for ($r = 0; $r <= $maxRow; $r++) {
            $row = $rows[$r] ?? [];
            $maxCol = $row ? max(array_keys($row)) : -1;
            $dense = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $dense[$c] = $row[$c] ?? '';
            }
            $out[$r] = $dense;
        }

        return $out;
    }

    private static function resolveSheetPath(ZipArchive $zip, int $sheetIndex): string
    {
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wbXml === false || $relXml === false) {
            return 'xl/worksheets/sheet1.xml'; // rozsądny fallback
        }

        $wb = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relXml);
        if ($wb === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        // mapa rId → target
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = (string)$rel['Target'];
        }

        $i = 0;
        foreach ($wb->sheets->sheet as $sheet) {
            if ($i === $sheetIndex) {
                $rid = null;
                foreach ($sheet->attributes('r', true) as $k => $v) {
                    if ($k === 'id') {
                        $rid = (string)$v;
                    }
                }
                if ($rid && isset($relMap[$rid])) {
                    $target = $relMap[$rid];
                    if (strpos($target, '/') === 0) {
                        return ltrim($target, '/');
                    }
                    return 'xl/'.$target;
                }
            }
            $i++;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function cellValue(string $cellXml, string $type, array $shared): string
    {
        $c = simplexml_load_string($cellXml);
        if ($c === false) {
            return '';
        }

        if ($type === 'inlineStr') {
            if (isset($c->is->t)) {
                return (string)$c->is->t;
            }
            $txt = '';
            if (isset($c->is->r)) {
                foreach ($c->is->r as $r) {
                    $txt .= (string)$r->t;
                }
            }
            return $txt;
        }

        $v = isset($c->v) ? (string)$c->v : '';

        if ($type === 's') {
            return $shared[(int)$v] ?? '';
        }
        if ($type === 'b') {
            return $v === '1' ? '1' : '0';
        }

        // n / str / e / brak typu — zwracamy zbuforowaną wartość
        return $v;
    }

    /** "BC12" → 54 (indeks kolumny od 0) */
    private static function colIndex(string $ref): int
    {
        $col = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ref[$i];
            if ($ch < 'A' || $ch > 'Z') {
                break;
            }
            $col = $col * 26 + (ord($ch) - 64);
        }
        return max(0, $col - 1);
    }
}
