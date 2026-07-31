<?php
/**
 * XlsReader — minimalny czytnik starych binarnych plików .xls (BIFF8 / Excel 97-2003).
 *
 * Bez zależności zewnętrznych. Czyta pierwszy arkusz do tablicy wierszy.
 * Obsługiwane rekordy: SST (+CONTINUE), LABELSST, LABEL, NUMBER, RK, MULRK,
 * FORMULA (+STRING dla wyniku tekstowego), BOOLERR. Wystarczające dla
 * eksportów cenników (motec.xls). Zweryfikowany 1:1 względem xlrd (Python).
 *
 * Użycie:
 *   $rows = XlsReader::read('/sciezka/plik.xls');
 */
class XlsReader
{
    /**
     * @return array<int, array<int, string>> wiersze → komórki (indeksy od 0)
     */
    public static function read(string $path): array
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Nie można odczytać pliku XLS: '.$path);
        }

        $workbook = self::extractWorkbookStream($data);
        return self::parseWorkbook($workbook);
    }

    // ============================================================
    // OLE2 / Compound File — wyciągnięcie strumienia "Workbook"
    // ============================================================
    private static function extractWorkbookStream(string $d): string
    {
        if (substr($d, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw new RuntimeException('To nie jest plik OLE2 (.xls)');
        }

        $u16 = fn(int $o) => unpack('v', substr($d, $o, 2))[1];
        $u32 = fn(int $o) => unpack('V', substr($d, $o, 4))[1];

        $sectorSize     = 1 << $u16(0x1E); // zwykle 512
        $miniSectorSize = 1 << $u16(0x20); // zwykle 64
        $dirStart       = $u32(0x30);
        $miniCutoff     = $u32(0x38);
        $miniFatStart   = $u32(0x3C);
        $difStart       = $u32(0x44);
        $numDifSectors  = $u32(0x48);

        // ---- FAT (tablica alokacji sektorów) ----
        $fatSectors = [];
        for ($i = 0; $i < 109; $i++) {
            $s = $u32(0x4C + $i * 4);
            if ($s !== 0xFFFFFFFE && $s !== 0xFFFFFFFF) {
                $fatSectors[] = $s;
            }
        }
        // DIFAT — dodatkowe sektory FAT (duże pliki)
        $dif = $difStart;
        for ($n = 0; $n < $numDifSectors && $dif !== 0xFFFFFFFE && $dif !== 0xFFFFFFFF; $n++) {
            $off = 512 + $dif * $sectorSize;
            $cnt = intdiv($sectorSize, 4) - 1;
            for ($i = 0; $i < $cnt; $i++) {
                $s = $u32($off + $i * 4);
                if ($s !== 0xFFFFFFFE && $s !== 0xFFFFFFFF) {
                    $fatSectors[] = $s;
                }
            }
            $dif = $u32($off + $cnt * 4);
        }

        $fat = [];
        foreach ($fatSectors as $s) {
            $off = 512 + $s * $sectorSize;
            for ($i = 0; $i < intdiv($sectorSize, 4); $i++) {
                $fat[] = $u32($off + $i * 4);
            }
        }

        $readChain = function (int $start) use ($d, $fat, $sectorSize): string {
            $out = '';
            $s = $start;
            $guard = 0;
            while ($s !== 0xFFFFFFFE && $s !== 0xFFFFFFFF && $guard++ < 200000) {
                $out .= substr($d, 512 + $s * $sectorSize, $sectorSize);
                $s = $fat[$s] ?? 0xFFFFFFFE;
            }
            return $out;
        };

        // ---- Katalog wpisów ----
        $dirData = $readChain($dirStart);
        $entries = [];
        for ($o = 0; $o + 128 <= strlen($dirData); $o += 128) {
            $nameLen = unpack('v', substr($dirData, $o + 64, 2))[1];
            if ($nameLen < 2) {
                continue;
            }
            $name = @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($dirData, $o, $nameLen - 2));
            $entries[] = [
                'name'  => $name,
                'type'  => ord($dirData[$o + 66]),
                'start' => unpack('V', substr($dirData, $o + 116, 4))[1],
                'size'  => unpack('V', substr($dirData, $o + 120, 4))[1],
            ];
        }

        $root = null;
        $wb = null;
        foreach ($entries as $e) {
            if ($e['type'] === 5) {
                $root = $e; // Root Entry
            }
            if ($e['type'] === 2 && in_array($e['name'], ['Workbook', 'Book'], true)) {
                $wb = $e;
            }
        }
        if (!$wb) {
            throw new RuntimeException('Brak strumienia Workbook w pliku XLS');
        }

        if ($wb['size'] >= $miniCutoff) {
            return substr($readChain($wb['start']), 0, $wb['size']);
        }

        // ---- Mini-strumień (małe pliki) ----
        if (!$root) {
            throw new RuntimeException('Brak Root Entry w pliku XLS');
        }
        $miniStream = $readChain($root['start']);

        $miniFat = [];
        $mf = $miniFatStart;
        $guard = 0;
        while ($mf !== 0xFFFFFFFE && $mf !== 0xFFFFFFFF && $guard++ < 100000) {
            $off = 512 + $mf * $sectorSize;
            for ($i = 0; $i < intdiv($sectorSize, 4); $i++) {
                $miniFat[] = $u32($off + $i * 4);
            }
            $mf = $fat[$mf] ?? 0xFFFFFFFE;
        }

        $out = '';
        $s = $wb['start'];
        $guard = 0;
        while ($s !== 0xFFFFFFFE && $s !== 0xFFFFFFFF && $guard++ < 200000) {
            $out .= substr($miniStream, $s * $miniSectorSize, $miniSectorSize);
            $s = $miniFat[$s] ?? 0xFFFFFFFE;
        }
        return substr($out, 0, $wb['size']);
    }

    // ============================================================
    // BIFF8 — parsowanie rekordów skoroszytu
    // ============================================================
    private static function parseWorkbook(string $wb): array
    {
        $len = strlen($wb);

        // ---- Przebieg 1 (globals): SST + offset pierwszego arkusza ----
        $sst = [];
        $firstSheetOffset = null;

        $pos = 0;
        while ($pos + 4 <= $len) {
            $op = unpack('v', substr($wb, $pos, 2))[1];
            $sz = unpack('v', substr($wb, $pos + 2, 2))[1];
            $body = substr($wb, $pos + 4, $sz);

            if ($op === 0x0085) { // BOUNDSHEET
                $offset = unpack('V', substr($body, 0, 4))[1];
                if ($firstSheetOffset === null) {
                    $firstSheetOffset = $offset;
                }
            } elseif ($op === 0x00FC) { // SST (+ ewentualne CONTINUE)
                $chunks = [$body];
                $p2 = $pos + 4 + $sz;
                while ($p2 + 4 <= $len) {
                    $op2 = unpack('v', substr($wb, $p2, 2))[1];
                    $sz2 = unpack('v', substr($wb, $p2 + 2, 2))[1];
                    if ($op2 !== 0x003C) { // CONTINUE
                        break;
                    }
                    $chunks[] = substr($wb, $p2 + 4, $sz2);
                    $p2 += 4 + $sz2;
                }
                $sst = self::parseSST($chunks);
            } elseif ($op === 0x000A && $firstSheetOffset !== null) { // EOF globals
                break;
            }

            $pos += 4 + $sz;
        }

        if ($firstSheetOffset === null) {
            throw new RuntimeException('Brak arkuszy w pliku XLS');
        }

        // ---- Przebieg 2: komórki pierwszego arkusza ----
        $rows = [];
        $pos = $firstSheetOffset;
        $lastFormulaCell = null;

        while ($pos + 4 <= $len) {
            $op = unpack('v', substr($wb, $pos, 2))[1];
            $sz = unpack('v', substr($wb, $pos + 2, 2))[1];
            $body = substr($wb, $pos + 4, $sz);
            $pos += 4 + $sz;

            if ($op === 0x000A) { // EOF — koniec arkusza
                break;
            }

            switch ($op) {
                case 0x00FD: { // LABELSST
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $i = unpack('V', substr($body, 6, 4))[1];
                    $rows[$r][$c] = $sst[$i] ?? '';
                    break;
                }
                case 0x0203: { // NUMBER (double)
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $v = unpack('e', substr($body, 6, 8))[1];
                    $rows[$r][$c] = self::numToStr($v);
                    break;
                }
                case 0x027E: { // RK
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $rows[$r][$c] = self::numToStr(self::decodeRK(substr($body, 6, 4)));
                    break;
                }
                case 0x00BD: { // MULRK — seria RK w jednym wierszu
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $cFirst = unpack('v', substr($body, 2, 2))[1];
                    $count = intdiv(strlen($body) - 6, 6);
                    for ($i = 0; $i < $count; $i++) {
                        $rk = substr($body, 4 + $i * 6 + 2, 4);
                        $rows[$r][$cFirst + $i] = self::numToStr(self::decodeRK($rk));
                    }
                    break;
                }
                case 0x0006: { // FORMULA — wynik liczbowy lub flaga "string follows"
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $res = substr($body, 6, 8);
                    if (substr($res, 6, 2) === "\xFF\xFF") {
                        $kind = ord($res[0]);
                        if ($kind === 0) {
                            $lastFormulaCell = [$r, $c]; // STRING record za chwilę
                        } elseif ($kind === 1) {
                            $rows[$r][$c] = ord($res[2]) ? '1' : '0';
                        } else {
                            $rows[$r][$c] = '';
                        }
                    } else {
                        $rows[$r][$c] = self::numToStr(unpack('e', $res)[1]);
                    }
                    break;
                }
                case 0x0207: { // STRING — tekstowy wynik formuły
                    if ($lastFormulaCell !== null) {
                        [$r, $c] = $lastFormulaCell;
                        $rows[$r][$c] = self::readUnicodeString($body, 0)[0];
                        $lastFormulaCell = null;
                    }
                    break;
                }
                case 0x0204: { // LABEL (BIFF8, bez SST — rzadkie)
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $rows[$r][$c] = self::readUnicodeString($body, 6)[0];
                    break;
                }
                case 0x0205: { // BOOLERR
                    $r = unpack('v', substr($body, 0, 2))[1];
                    $c = unpack('v', substr($body, 2, 2))[1];
                    $isErr = ord($body[7]);
                    $rows[$r][$c] = $isErr ? '' : (ord($body[6]) ? '1' : '0');
                    break;
                }
            }
        }

        // ---- Gęste tablice ----
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

    /**
     * SST — wspólna tablica łańcuchów, może być podzielona rekordami CONTINUE.
     * Każdy CONTINUE zaczyna się nowym bajtem flag dla kontynuowanego tekstu.
     */
    private static function parseSST(array $chunks): array
    {
        $strings = [];
        $chunkIdx = 0;
        $chunk = $chunks[0];
        $pos = 8; // pomijamy cstTotal + cstUnique
        $total = unpack('V', substr($chunk, 4, 4))[1];

        $nextChunk = function () use (&$chunkIdx, &$chunk, &$pos, $chunks): bool {
            $chunkIdx++;
            if (!isset($chunks[$chunkIdx])) {
                return false;
            }
            $chunk = $chunks[$chunkIdx];
            $pos = 0;
            return true;
        };

        for ($n = 0; $n < $total; $n++) {
            // nagłówek łańcucha może wypaść na granicy — w praktyce eksportów
            // nagłówek jest zawsze w całości w jednym chunku
            if ($pos + 3 > strlen($chunk)) {
                if (!$nextChunk()) {
                    break;
                }
            }

            $cch = unpack('v', substr($chunk, $pos, 2))[1];
            $flags = ord($chunk[$pos + 2]);
            $pos += 3;

            $fHigh = $flags & 0x01;
            $fExt  = $flags & 0x04;
            $fRich = $flags & 0x08;

            $cRun = 0;
            $cbExt = 0;
            if ($fRich) {
                $cRun = unpack('v', substr($chunk, $pos, 2))[1];
                $pos += 2;
            }
            if ($fExt) {
                $cbExt = unpack('V', substr($chunk, $pos, 4))[1];
                $pos += 4;
            }

            // ---- treść znaków (może przechodzić przez CONTINUE) ----
            $out = '';
            $remaining = $cch;
            while ($remaining > 0) {
                $avail = strlen($chunk) - $pos;
                if ($avail <= 0) {
                    if (!$nextChunk()) {
                        break;
                    }
                    // nowy bajt flag na początku kontynuacji tekstu
                    $fHigh = ord($chunk[$pos]) & 0x01;
                    $pos += 1;
                    continue;
                }
                $charSize = $fHigh ? 2 : 1;
                $take = min($remaining, intdiv($avail, $charSize));
                if ($take === 0) {
                    if (!$nextChunk()) {
                        break;
                    }
                    $fHigh = ord($chunk[$pos]) & 0x01;
                    $pos += 1;
                    continue;
                }
                $raw = substr($chunk, $pos, $take * $charSize);
                $pos += $take * $charSize;
                $remaining -= $take;

                if ($fHigh) {
                    $out .= @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw);
                } else {
                    // "compressed" = Latin-1
                    $out .= mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
                }
            }

            // ---- pomiń formatowanie rich/ext (też może przejść przez CONTINUE) ----
            $skip = $cRun * 4 + $cbExt;
            while ($skip > 0) {
                $avail = strlen($chunk) - $pos;
                if ($avail >= $skip) {
                    $pos += $skip;
                    $skip = 0;
                } else {
                    $skip -= $avail;
                    if (!$nextChunk()) {
                        break;
                    }
                }
            }

            $strings[] = $out;
        }

        return $strings;
    }

    /** Unicode string poza SST (STRING/LABEL): [cch u16][flags u8][chars] */
    private static function readUnicodeString(string $data, int $offset): array
    {
        $cch = unpack('v', substr($data, $offset, 2))[1];
        $flags = ord($data[$offset + 2]);
        $pos = $offset + 3;
        if ($flags & 0x01) {
            $raw = substr($data, $pos, $cch * 2);
            return [@iconv('UTF-16LE', 'UTF-8//IGNORE', $raw), $pos + $cch * 2];
        }
        $raw = substr($data, $pos, $cch);
        return [mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1'), $pos + $cch];
    }

    /** Dekodowanie liczby w formacie RK */
    private static function decodeRK(string $rk4): float
    {
        $v = unpack('V', $rk4)[1];
        $div100 = $v & 0x01;
        $isInt  = $v & 0x02;

        if ($isInt) {
            $num = $v >> 2;
            // znak (30-bitowa liczba ze znakiem)
            if ($num & 0x20000000) {
                $num -= 0x40000000;
            }
            $num = (float)$num;
        } else {
            // górne 30 bitów mantysy double, dolne 34 bity = 0
            $bits = ($v & 0xFFFFFFFC);
            $packed = pack('V', 0).pack('V', $bits);
            $num = unpack('e', $packed)[1];
        }

        return $div100 ? $num / 100.0 : $num;
    }

    /** Liczby bez ogona ".0" — spójnie z eksportem CSV */
    private static function numToStr(float $v): string
    {
        if (abs($v - round($v)) < 1e-9 && abs($v) < 1e15) {
            return (string)(int)round($v);
        }
        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }
}
