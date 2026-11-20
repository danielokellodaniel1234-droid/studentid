<?php

function studentRegistryNormalizeName(string $name): string
{
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function studentRegistryVerify(mysqli $conn, string $registrationNumber, string $fullName): ?array
{
    $stmt = $conn->prepare(
        "SELECT registration_number, full_name, course, programme
         FROM student_registry
         WHERE registration_number = ? AND status = 'active'
         LIMIT 1"
    );
    $stmt->bind_param('s', $registrationNumber);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student || studentRegistryNormalizeName((string)$student['full_name']) !== studentRegistryNormalizeName($fullName)) {
        return null;
    }

    return $student;
}

function studentRegistrySpreadsheetValue(SimpleXMLElement $cell, array $sharedStrings): string
{
    $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $type = (string)$cell['t'];
    if ($type === 'inlineStr') {
        $parts = $cell->xpath('.//m:is//m:t');
        return is_array($parts) ? implode('', array_map('strval', $parts)) : '';
    }

    $value = isset($cell->v) ? (string)$cell->v : '';
    if ($type === 's') {
        $index = filter_var($value, FILTER_VALIDATE_INT);
        return $index !== false && isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
    }

    return $value;
}

function studentRegistryReadZipXml(string $path, array $wantedEntries): array
{
    $contents = file_get_contents($path);
    if ($contents === false || strlen($contents) < 22) {
        throw new RuntimeException('The Excel workbook is not a valid ZIP file.');
    }

    $endOffset = strrpos($contents, "PK\x05\x06");
    if ($endOffset === false || $endOffset + 22 > strlen($contents)) {
        throw new RuntimeException('The Excel workbook is not a valid ZIP file.');
    }
    $end = unpack('vdisk/vstart/ventries_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length', substr($contents, $endOffset + 4, 18));
    if (!$end || $end['disk'] !== 0 || $end['start'] !== 0 || $end['entries_disk'] !== $end['entries']
        || $end['entries'] === 0 || $end['entries'] > 10000 || $end['entries'] === 0xFFFF
        || $end['central_offset'] === 0xFFFFFFFF || $end['central_size'] === 0xFFFFFFFF
        || $endOffset + 22 + $end['comment_length'] > strlen($contents)
        || $end['central_offset'] + $end['central_size'] > $endOffset) {
        throw new RuntimeException('The Excel workbook uses an unsupported or invalid ZIP layout.');
    }

    $wanted = [];
    foreach ($wantedEntries as $entryName) {
        $wanted[strtolower(str_replace('\\', '/', $entryName))] = true;
    }
    $found = [];
    $offset = $end['central_offset'];
    for ($entryNumber = 0; $entryNumber < $end['entries']; $entryNumber++) {
        if (substr($contents, $offset, 4) !== "PK\x01\x02" || $offset + 46 > strlen($contents)) {
            throw new RuntimeException('The Excel workbook has a damaged ZIP directory.');
        }
        $entry = unpack('vflags/vmethod/x8/Vcompressed/Vuncompressed/vname_length/vextra_length/vcomment_length/x8/Vlocal_offset', substr($contents, $offset + 8, 38));
        $recordLength = 46 + $entry['name_length'] + $entry['extra_length'] + $entry['comment_length'];
        if ($offset + $recordLength > strlen($contents)) {
            throw new RuntimeException('The Excel workbook has a damaged ZIP entry.');
        }
        $name = str_replace('\\', '/', substr($contents, $offset + 46, $entry['name_length']));
        $nameKey = strtolower($name);
        $wantedName = isset($wanted[$nameKey]);
        if (!$wantedName) {
            foreach ($wanted as $candidate => $unused) {
                if (str_ends_with($candidate, '*') && str_starts_with($nameKey, substr($candidate, 0, -1))) {
                    $wantedName = true;
                    break;
                }
            }
        }
        if ($wantedName) {
            if (($entry['flags'] & 1) !== 0 || !in_array($entry['method'], [0, 8], true)
                || $entry['uncompressed'] > 25 * 1024 * 1024
                || $entry['local_offset'] + 30 > strlen($contents)
                || substr($contents, $entry['local_offset'], 4) !== "PK\x03\x04") {
                throw new RuntimeException('The Excel workbook contains an unsupported or invalid XML entry.');
            }
            $local = unpack('x26/vname_length/vextra_length', substr($contents, $entry['local_offset'], 30));
            $dataOffset = $entry['local_offset'] + 30 + $local['name_length'] + $local['extra_length'];
            if ($dataOffset + $entry['compressed'] > $end['central_offset']) {
                throw new RuntimeException('The Excel workbook contains a truncated XML entry.');
            }
            $compressed = substr($contents, $dataOffset, $entry['compressed']);
            $xml = $entry['method'] === 0 ? $compressed : gzinflate($compressed, 25 * 1024 * 1024);
            if ($xml === false || strlen($xml) !== $entry['uncompressed']) {
                throw new RuntimeException('The Excel workbook contains invalid compressed XML.');
            }
            $found[$nameKey] = $xml;
        }
        $offset += $recordLength;
    }
    return $found;
}

function studentRegistrySpreadsheetKind(string $path, string $extension): string
{
    $head = file_get_contents($path, false, null, 0, 8);
    if ($head === false) {
        throw new RuntimeException('The spreadsheet could not be opened.');
    }
    if (str_starts_with($head, "PK\x03\x04")) {
        return 'ooxml';
    }
    if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
        return 'xls';
    }
    if (str_starts_with($head, '%PDF') || str_starts_with($head, 'MZ') || str_starts_with($head, "\x7FELF")) {
        throw new RuntimeException('Choose an Excel workbook or a CSV file exported from Excel.');
    }
    if (in_array($extension, ['xlsb'], true)) {
        throw new RuntimeException('This file is an Excel binary workbook (.xlsb). Save it as .xlsx or .xls and upload it again.');
    }
    return 'csv';
}

function studentRegistryCsvRows(string $path): array
{
    $sample = file_get_contents($path, false, null, 0, 8192);
    if ($sample === false) {
        throw new RuntimeException('The spreadsheet could not be opened.');
    }
    $sample = preg_replace('/^\xEF\xBB\xBF/', '', $sample) ?? $sample;
    $firstLine = strtok($sample, "\r\n") ?: '';
    $delimiter = ',';
    $best = -1;
    foreach ([',', ';', "\t", '|'] as $candidate) {
        $count = substr_count($firstLine, $candidate);
        if ($count > $best) {
            $best = $count;
            $delimiter = $candidate;
        }
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The spreadsheet could not be opened.');
    }
    try {
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]) ?? (string)$row[0];
            }
            $rows[] = array_map(static fn($value): string => trim((string)$value), $row);
        }
        return $rows;
    } finally {
        fclose($handle);
    }
}

function studentRegistrySheetRowsFromXml(string $sheetXml, array $sharedStrings): array
{
    $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);
    if ($sheet === false) {
        return [];
    }
    $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $xmlRow) {
        $xmlRow->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row = [];
        foreach ($xmlRow->xpath('./m:c') ?: [] as $cell) {
            $reference = (string)$cell['r'];
            if (!preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
                continue;
            }
            $column = 0;
            foreach (str_split(strtoupper($matches[1])) as $letter) {
                $column = $column * 26 + ord($letter) - 64;
            }
            if ($column < 1 || $column > 64) {
                continue;
            }
            $row[$column - 1] = trim(studentRegistrySpreadsheetValue($cell, $sharedStrings));
        }
        if ($row) {
            ksort($row);
            $rows[] = array_values(array_replace(array_fill(0, max(array_keys($row)) + 1, ''), $row));
        }
    }
    return $rows;
}

function studentRegistryOoxmlRows(string $path): array
{
    if (!function_exists('gzinflate')) {
        throw new RuntimeException('Excel .xlsx files cannot be read because PHP zlib is unavailable.');
    }
    $xmlEntries = studentRegistryReadZipXml($path, [
        'xl/workbook.xml',
        'xl/_rels/workbook.xml.rels',
        'xl/sharedStrings.xml',
        'xl/worksheets/*',
    ]);

    $sharedStrings = [];
    if (isset($xmlEntries['xl/sharedstrings.xml'])) {
        $sharedXml = simplexml_load_string($xmlEntries['xl/sharedstrings.xml'], SimpleXMLElement::class, LIBXML_NONET);
        if ($sharedXml !== false) {
            $sharedXml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sharedXml->xpath('//m:si') ?: [] as $item) {
                $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $parts = $item->xpath('.//m:t');
                $sharedStrings[] = is_array($parts) ? implode('', array_map('strval', $parts)) : '';
            }
        }
    }

    $sheetPaths = [];
    if (isset($xmlEntries['xl/workbook.xml'], $xmlEntries['xl/_rels/workbook.xml.rels'])) {
        $workbook = simplexml_load_string($xmlEntries['xl/workbook.xml'], SimpleXMLElement::class, LIBXML_NONET);
        $rels = simplexml_load_string($xmlEntries['xl/_rels/workbook.xml.rels'], SimpleXMLElement::class, LIBXML_NONET);
        if ($workbook !== false && $rels !== false) {
            $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
            $targets = [];
            foreach ($rels->xpath('//r:Relationship') ?: [] as $relationship) {
                $targets[(string)$relationship['Id']] = (string)$relationship['Target'];
            }
            foreach ($workbook->xpath('//m:sheets/m:sheet') ?: [] as $sheet) {
                $relationId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = str_replace('\\', '/', $targets[$relationId] ?? '');
                $target = ltrim($target, '/');
                if (str_starts_with(strtolower($target), 'xl/')) {
                    $sheetPaths[] = strtolower($target);
                } elseif ($target !== '') {
                    $sheetPaths[] = 'xl/' . strtolower($target);
                }
            }
        }
    }
    if (!$sheetPaths) {
        foreach (array_keys($xmlEntries) as $name) {
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $sheetPaths[] = $name;
            }
        }
    }

    foreach ($sheetPaths as $sheetPath) {
        if (!isset($xmlEntries[$sheetPath])) {
            continue;
        }
        $rows = studentRegistrySheetRowsFromXml($xmlEntries[$sheetPath], $sharedStrings);
        if ($rows) {
            return $rows;
        }
    }
    throw new RuntimeException('The Excel workbook has no readable worksheet.');
}

function studentRegistryDecodeRk(int $rk): string
{
    $divide = ($rk & 1) === 1;
    if (($rk & 2) === 2) {
        $value = $rk >> 2;
        if ($value & 0x20000000) {
            $value -= 0x40000000;
        }
        $number = (float)$value;
    } else {
        $unpacked = unpack('d', pack('V', 0) . pack('V', $rk & 0xFFFFFFFC));
        $number = (float)($unpacked[1] ?? 0);
    }
    if ($divide) {
        $number /= 100;
    }
    if (is_finite($number) && abs($number - round($number)) < 0.0000001 && abs($number) < 1e15) {
        return sprintf('%.0f', $number);
    }
    $text = rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    return $text === '' ? '0' : $text;
}

function studentRegistryFormatExcelNumber(float $number): string
{
    if (!is_finite($number)) {
        return '';
    }
    if (abs($number - round($number)) < 0.0000001 && abs($number) < 1e15) {
        return sprintf('%.0f', $number);
    }
    $text = rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    return $text === '' ? '0' : $text;
}

function studentRegistryOleChain(string $contents, int $start, array $fat, int $sectorSize, int $maxBytes): string
{
    $data = '';
    $sector = $start;
    $guard = 0;
    $end = 0xFFFFFFFE;
    while ($sector !== $end && $sector !== 0xFFFFFFFF && $sector < 0xFFFFFFF0 && $guard < 100000 && strlen($data) < $maxBytes) {
        $offset = ($sector + 1) * $sectorSize;
        if ($offset < 0 || $offset >= strlen($contents)) {
            break;
        }
        $data .= substr($contents, $offset, $sectorSize);
        $sector = $fat[$sector] ?? $end;
        $guard++;
    }
    return $data;
}

function studentRegistryXlsWorkbook(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false || strlen($contents) < 512 || !str_starts_with($contents, "\xD0\xCF\x11\xE0")) {
        throw new RuntimeException('The Excel .xls workbook could not be read.');
    }
    $header = unpack('vsector_shift/vmini_shift', substr($contents, 0x1E, 4));
    $counts = unpack('Vfat_count/Vdir_start', substr($contents, 0x2C, 8));
    $mini = unpack('Vmini_cutoff/Vmini_fat_start/Vmini_fat_count/Vdifat_start/Vdifat_count', substr($contents, 0x38, 20));
    if (!$header || !$counts || !$mini) {
        throw new RuntimeException('The Excel .xls workbook could not be read.');
    }
    $sectorSize = 1 << $header['sector_shift'];
    $miniSize = 1 << $header['mini_shift'];
    if ($sectorSize < 512 || $sectorSize > 4096 || $miniSize < 64) {
        throw new RuntimeException('The Excel .xls workbook uses an unsupported layout.');
    }

    $fatSectors = [];
    $difatBytes = substr($contents, 0x4C, 109 * 4);
    $difat = $difatBytes === '' ? [] : (array)unpack('V*', $difatBytes);
    foreach ($difat as $sectorId) {
        if ($sectorId < 0xFFFFFFF0) {
            $fatSectors[] = $sectorId;
        }
    }
    $difatSector = $mini['difat_start'];
    $difatGuard = 0;
    while ($difatSector < 0xFFFFFFF0 && $difatGuard < 1000 && count($fatSectors) < $counts['fat_count'] + 1000) {
        $offset = ($difatSector + 1) * $sectorSize;
        $block = substr($contents, $offset, $sectorSize);
        $entries = $block === '' ? [] : (array)unpack('V*', $block);
        $last = array_pop($entries);
        foreach ($entries as $sectorId) {
            if ($sectorId < 0xFFFFFFF0) {
                $fatSectors[] = $sectorId;
            }
        }
        $difatSector = (int)$last;
        $difatGuard++;
    }

    $fat = [];
    foreach ($fatSectors as $sectorId) {
        $block = substr($contents, ($sectorId + 1) * $sectorSize, $sectorSize);
        $entries = $block === '' ? [] : (array)unpack('V*', $block);
        foreach ($entries as $value) {
            $fat[] = $value;
        }
    }

    $directory = studentRegistryOleChain($contents, $counts['dir_start'], $fat, $sectorSize, 2 * 1024 * 1024);
    $entries = [];
    for ($offset = 0; $offset + 128 <= strlen($directory); $offset += 128) {
        $nameLength = unpack('v', substr($directory, $offset + 64, 2))[1] ?? 0;
        $type = ord($directory[$offset + 66]);
        $start = unpack('V', substr($directory, $offset + 116, 4))[1] ?? 0;
        $size = unpack('V', substr($directory, $offset + 120, 4))[1] ?? 0;
        $name = $nameLength > 2 ? substr($directory, $offset, $nameLength - 2) : '';
        $name = iconv('UTF-16LE', 'UTF-8//IGNORE', $name) ?: '';
        $entries[] = ['name' => $name, 'type' => $type, 'start' => $start, 'size' => $size];
    }

    $workbook = null;
    foreach ($entries as $entry) {
        if ($entry['type'] === 2 && in_array(strtolower($entry['name']), ['workbook', 'book'], true)) {
            $workbook = $entry;
            break;
        }
    }
    if (!$workbook) {
        throw new RuntimeException('The Excel .xls workbook has no readable worksheet.');
    }

    if ($workbook['size'] < $mini['mini_cutoff'] && isset($entries[0]) && $entries[0]['type'] === 5) {
        $miniFat = [];
        $miniSector = $mini['mini_fat_start'];
        $miniGuard = 0;
        while ($miniSector < 0xFFFFFFF0 && $miniGuard < 10000) {
            $block = substr($contents, ($miniSector + 1) * $sectorSize, $sectorSize);
            $values = $block === '' ? [] : (array)unpack('V*', $block);
            foreach ($values as $value) {
                $miniFat[] = $value;
            }
            $miniSector = $fat[$miniSector] ?? 0xFFFFFFFE;
            $miniGuard++;
        }
        $miniStream = studentRegistryOleChain($contents, $entries[0]['start'], $fat, $sectorSize, max($entries[0]['size'], $miniSize));
        $data = '';
        $sector = $workbook['start'];
        $guard = 0;
        while ($sector < 0xFFFFFFF0 && $guard < 100000 && strlen($data) < $workbook['size']) {
            $data .= substr($miniStream, $sector * $miniSize, $miniSize);
            $sector = $miniFat[$sector] ?? 0xFFFFFFFE;
            $guard++;
        }
        return substr($data, 0, $workbook['size']);
    }

    return substr(studentRegistryOleChain($contents, $workbook['start'], $fat, $sectorSize, $workbook['size']), 0, $workbook['size']);
}

function studentRegistryXlsRows(string $path): array
{
    $workbook = studentRegistryXlsWorkbook($path);
    $position = 0;
    $length = strlen($workbook);
    $sharedStrings = [];
    $rows = [];
    $codepage = 'Windows-1252';
    $inSheet = false;
    $sheetDone = false;
    $expectFormulaString = false;
    $formulaRow = 0;
    $formulaColumn = 0;

    $readRecord = static function () use (&$workbook, &$position, $length): ?array {
        if ($position + 4 > $length) {
            return null;
        }
        $header = unpack('vcode/vsize', substr($workbook, $position, 4));
        $size = (int)$header['size'];
        if ($position + 4 + $size > $length) {
            return null;
        }
        $data = substr($workbook, $position + 4, $size);
        $position += 4 + $size;
        return ['code' => (int)$header['code'], 'data' => $data];
    };

    $decodeText = static function (string $bytes, bool $utf16) use (&$codepage): string {
        if ($utf16) {
            $text = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);
            return $text === false ? '' : $text;
        }
        $text = iconv($codepage, 'UTF-8//IGNORE', $bytes);
        return $text === false ? $bytes : $text;
    };

    while (($record = $readRecord()) !== null) {
        $code = $record['code'];
        $data = $record['data'];
        if ($code === 0x003C && $expectFormulaString === false) {
            continue;
        }
        if ($code === 0x0042 && strlen($data) >= 2) {
            $page = unpack('v', substr($data, 0, 2))[1];
            $codepage = $page === 1200 ? 'UTF-16LE' : 'CP' . $page;
            continue;
        }
        if ($code === 0x00FC) {
            $chunks = [$data];
            $saved = $position;
            while ($position + 4 <= $length) {
                $next = unpack('vcode/vsize', substr($workbook, $position, 4));
                if ((int)$next['code'] !== 0x003C) {
                    break;
                }
                $nextSize = (int)$next['size'];
                $chunks[] = substr($workbook, $position + 4, $nextSize);
                $position += 4 + $nextSize;
            }
            if (count($chunks) === 1) {
                $position = $saved;
            }
            $sharedStrings = studentRegistryXlsSharedStrings($chunks, $decodeText);
            continue;
        }
        if ($code === 0x0809 && strlen($data) >= 4) {
            $type = unpack('v', substr($data, 2, 2))[1];
            if ($type === 0x0010) {
                if ($inSheet) {
                    $sheetDone = true;
                }
                $inSheet = true;
            }
            continue;
        }
        if ($sheetDone || !$inSheet) {
            $expectFormulaString = false;
            continue;
        }
        if ($expectFormulaString && $code === 0x0207 && strlen($data) >= 3) {
            $chars = unpack('v', substr($data, 0, 2))[1];
            $flags = ord($data[2]);
            $utf16 = ($flags & 1) === 1;
            $bytes = substr($data, 3, $utf16 ? $chars * 2 : $chars);
            $rows[$formulaRow][$formulaColumn] = trim($decodeText($bytes, $utf16));
            $expectFormulaString = false;
            continue;
        }
        $expectFormulaString = false;
        if (strlen($data) < 6) {
            continue;
        }
        $rowNumber = unpack('v', substr($data, 0, 2))[1];
        $column = unpack('v', substr($data, 2, 2))[1];
        if ($column > 63) {
            continue;
        }
        $value = null;
        if ($code === 0x00FD && strlen($data) >= 10) {
            $index = unpack('V', substr($data, 6, 4))[1];
            $value = $sharedStrings[$index] ?? '';
        } elseif ($code === 0x0204 && strlen($data) >= 9) {
            $chars = unpack('v', substr($data, 6, 2))[1];
            $flags = ord($data[8]);
            $utf16 = ($flags & 1) === 1;
            $value = $decodeText(substr($data, 9, $utf16 ? $chars * 2 : $chars), $utf16);
        } elseif ($code === 0x0203 && strlen($data) >= 14) {
            $number = unpack('d', substr($data, 6, 8));
            $value = studentRegistryFormatExcelNumber((float)($number[1] ?? 0));
        } elseif ($code === 0x027E && strlen($data) >= 10) {
            $rk = unpack('V', substr($data, 6, 4))[1];
            $value = studentRegistryDecodeRk($rk);
        } elseif ($code === 0x00BD && strlen($data) >= 8) {
            $firstColumn = $column;
            $offset = 4;
            while ($offset + 6 <= strlen($data) - 2) {
                $rk = unpack('V', substr($data, $offset + 2, 4))[1];
                $rows[$rowNumber][$firstColumn] = studentRegistryDecodeRk($rk);
                $firstColumn++;
                $offset += 6;
            }
            continue;
        } elseif ($code === 0x0006 && strlen($data) >= 14) {
            if (ord($data[12]) === 0xFF) {
                $formulaRow = $rowNumber;
                $formulaColumn = $column;
                $expectFormulaString = true;
                continue;
            }
            $number = unpack('d', substr($data, 6, 8));
            $value = studentRegistryFormatExcelNumber((float)($number[1] ?? 0));
        }
        if ($value !== null) {
            $rows[$rowNumber][$column] = trim($value);
        }
    }

    if (!$rows) {
        throw new RuntimeException('The Excel workbook has no readable worksheet.');
    }
    ksort($rows);
    $table = [];
    foreach ($rows as $row) {
        ksort($row);
        $table[] = array_values(array_replace(array_fill(0, max(array_keys($row)) + 1, ''), $row));
    }
    return $table;
}

function studentRegistryXlsSharedStrings(array $chunks, callable $decodeText): array
{
    $strings = [];
    $chunk = 0;
    $data = $chunks[0] ?? '';
    $position = 8;
    if (strlen($data) < 8) {
        return [];
    }
    $unique = unpack('V', substr($data, 4, 4))[1] ?? 0;
    $nextChunk = static function () use (&$chunks, &$chunk, &$data, &$position): bool {
        $chunk++;
        if (!isset($chunks[$chunk]) || $chunks[$chunk] === '') {
            return false;
        }
        $data = $chunks[$chunk];
        $position = 0;
        return true;
    };
    $pullBytes = static function (int $size, bool $plain) use (&$data, &$position, $nextChunk): string {
        $bytes = '';
        while ($size > 0) {
            if ($position >= strlen($data) && !$nextChunk()) {
                break;
            }
            $take = min($size, strlen($data) - $position);
            $bytes .= substr($data, $position, $take);
            $position += $take;
            $size -= $take;
            unset($plain);
        }
        return $bytes;
    };

    for ($index = 0; $index < $unique; $index++) {
        if ($position + 3 > strlen($data)) {
            $pending = substr($data, $position);
            if (!$nextChunk()) {
                break;
            }
            $data = $pending . $data;
            $position = 0;
        }
        $charCount = unpack('v', substr($data, $position, 2))[1];
        $position += 2;
        $flags = ord($data[$position]);
        $position++;
        $utf16 = ($flags & 0x01) === 1;
        $richRuns = 0;
        $extSize = 0;
        if (($flags & 0x08) !== 0) {
            $richBytes = $pullBytes(2, true);
            $richRuns = strlen($richBytes) === 2 ? unpack('v', $richBytes)[1] : 0;
        }
        if (($flags & 0x04) !== 0) {
            $extBytes = $pullBytes(4, true);
            $extSize = strlen($extBytes) === 4 ? unpack('V', $extBytes)[1] : 0;
        }

        $text = '';
        $charsLeft = $charCount;
        while ($charsLeft > 0) {
            if ($position >= strlen($data)) {
                if (!$nextChunk()) {
                    break;
                }
                if ($text !== '') {
                    $utf16 = (ord($data[0]) & 0x01) === 1;
                    $position = 1;
                }
            }
            $available = strlen($data) - $position;
            $need = $utf16 ? $charsLeft * 2 : $charsLeft;
            $take = min($need, $available);
            if ($utf16 && ($take % 2) === 1) {
                $take--;
            }
            if ($take <= 0) {
                break;
            }
            $text .= $decodeText(substr($data, $position, $take), $utf16);
            $position += $take;
            $charsLeft -= $utf16 ? intdiv($take, 2) : $take;
        }
        $pullBytes($richRuns * 4 + $extSize, true);
        $strings[] = $text;
    }
    return $strings;
}

function studentRegistrySpreadsheetRows(string $path, string $extension): array
{
    $kind = studentRegistrySpreadsheetKind($path, strtolower($extension));
    try {
        if ($kind === 'csv') {
            return studentRegistryCsvRows($path);
        }
        if ($kind === 'xls') {
            return studentRegistryXlsRows($path);
        }
        return studentRegistryOoxmlRows($path);
    } catch (RuntimeException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        error_log('Student registry spreadsheet read failed: ' . $exception->getMessage());
        throw new RuntimeException('The spreadsheet could not be read. Save it from Excel as .xlsx, .xls, or CSV and try again.');
    }
}

function studentRegistryClip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? trim($value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function studentRegistryNormalizeHeader(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s\-\.]+/', '_', $header) ?? $header;
    return preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;
}

function studentRegistryHeaderField(string $header): ?string
{
    $exact = [
        'registration_number' => 'registration_number',
        'registration_no' => 'registration_number',
        'registration' => 'registration_number',
        'reg_no' => 'registration_number',
        'reg_number' => 'registration_number',
        'regno' => 'registration_number',
        'regnumber' => 'registration_number',
        'student_id' => 'registration_number',
        'student_no' => 'registration_number',
        'student_number' => 'registration_number',
        'index_no' => 'registration_number',
        'index_number' => 'registration_number',
        'admission_no' => 'registration_number',
        'admission_number' => 'registration_number',
        'roll_no' => 'registration_number',
        'roll_number' => 'registration_number',
        'card_no' => 'card_number',
        'card_number' => 'card_number',
        'cardnumber' => 'card_number',
        'card_num' => 'card_number',
        'cardno' => 'card_number',
        'id_card_no' => 'card_number',
        'id_card_number' => 'card_number',
        'full_name' => 'full_name',
        'fullname' => 'full_name',
        'student_name' => 'full_name',
        'name' => 'full_name',
        'names' => 'full_name',
        'course' => 'course',
        'course_name' => 'course',
        'course_code' => 'course',
        'programme' => 'programme',
        'program' => 'programme',
        'programme_name' => 'programme',
        'program_name' => 'programme',
    ];
    if (isset($exact[$header])) {
        return $exact[$header];
    }
    if (str_contains($header, 'course')) {
        return 'course';
    }
    if (str_contains($header, 'program')) {
        return 'programme';
    }
    if (str_contains($header, 'regist') || str_contains($header, 'reg_no') || str_contains($header, 'regno') || $header === 'reg') {
        return 'registration_number';
    }
    if ($header === 'name' || str_ends_with($header, '_name') || str_contains($header, 'student_name')) {
        return 'full_name';
    }
    return null;
}

function studentRegistryMapSpreadsheetRows(array $rows): array
{
    if (!$rows) {
        throw new RuntimeException('The spreadsheet is empty.');
    }

    $normalized = array_map(
        static fn($header): string => studentRegistryNormalizeHeader((string)$header),
        $rows[0]
    );
    $indices = [];
    foreach ($normalized as $index => $header) {
        $field = studentRegistryHeaderField($header);
        if ($field !== null && !isset($indices[$field])) {
            $indices[$field] = $index;
        }
    }

    $dataRows = $rows;
    $rowNumber = 0;
    if (isset($indices['registration_number'])) {
        array_shift($dataRows);
        $rowNumber = 1;
    } else {
        $indices = [
            'registration_number' => 0,
            'full_name' => 1,
            'course' => 2,
            'programme' => 3,
            'card_number' => 4,
        ];
    }

    $records = [];
    $seenRegistrationNumbers = [];
    $seenCardNumbers = [];
    $skippedDuplicates = 0;
    $duplicateExamples = [];
    foreach ($dataRows as $row) {
        $rowNumber++;
        if (count(array_filter($row, static fn($value): bool => trim((string)$value) !== '')) === 0) {
            continue;
        }
        $record = [
            'registration_number' => studentRegistryClip((string)($row[$indices['registration_number']] ?? ''), 80),
            'full_name' => studentRegistryClip((string)($row[$indices['full_name']] ?? ''), 150),
            'course' => studentRegistryClip((string)(isset($indices['course']) ? ($row[$indices['course']] ?? '') : ''), 150),
            'programme' => studentRegistryClip((string)(isset($indices['programme']) ? ($row[$indices['programme']] ?? '') : ''), 150),
            'card_number' => studentRegistryClip((string)(isset($indices['card_number']) ? ($row[$indices['card_number']] ?? '') : ''), 50),
            'row' => $rowNumber,
        ];
        if ($record['card_number'] !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/D', $record['card_number']) !== 1) {
            throw new RuntimeException('Spreadsheet row ' . $rowNumber . ' has an invalid card number. Use up to 50 letters, numbers, dots, underscores or hyphens.');
        }
        if ($record['registration_number'] === '') {
            continue;
        }
        if ($record['full_name'] === '') {
            $record['full_name'] = $record['registration_number'];
        }
        $registrationKey = strtolower(preg_replace('/\s+/', '', $record['registration_number']) ?? $record['registration_number']);
        if (isset($seenRegistrationNumbers[$registrationKey])) {
            $skippedDuplicates++;
            if (count($duplicateExamples) < 5) {
                $duplicateExamples[] = 'row ' . $rowNumber . ' (' . $record['registration_number'] . ')';
            }
            continue;
        }
        $seenRegistrationNumbers[$registrationKey] = true;
        if ($record['card_number'] !== '') {
            $cardKey = strtolower($record['card_number']);
            if (isset($seenCardNumbers[$cardKey])) {
                throw new RuntimeException('Spreadsheet row ' . $rowNumber . ' repeats card number "' . $record['card_number'] . '". Each student must have a different card number.');
            }
            $seenCardNumbers[$cardKey] = true;
        }
        $records[] = $record;
    }

    if (!$records) {
        throw new RuntimeException('The spreadsheet has no registration numbers to add.');
    }
    return [
        'records' => $records,
        'skipped_duplicates' => $skippedDuplicates,
        'duplicate_examples' => $duplicateExamples,
    ];
}
