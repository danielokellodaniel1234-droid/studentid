<?php

function studentQrMultiply(int $left, int $right): int
{
    $result = 0;
    while ($right > 0) {
        if ($right & 1) {
            $result ^= $left;
        }
        $left <<= 1;
        if ($left & 0x100) {
            $left ^= 0x11D;
        }
        $right >>= 1;
    }
    return $result;
}

function studentQrFormatBits(int $data): int
{
    $remainder = $data << 10;
    for ($bit = 14; $bit >= 10; $bit--) {
        if (($remainder >> $bit) & 1) {
            $remainder ^= 0x537 << ($bit - 10);
        }
    }
    return (($data << 10) | $remainder) ^ 0x5412;
}

function studentQrSvg(string $payload, string $foreground = '#000000', string $background = '#ffffff'): string
{
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $foreground) !== 1 || preg_match('/^#[0-9a-fA-F]{6}$/', $background) !== 1) {
        throw new InvalidArgumentException('QR foreground and background colors must be six-digit hex colors.');
    }
    $payloadBytes = array_values(unpack('C*', $payload) ?: []);
    if (!$payloadBytes || count($payloadBytes) > 53) {
        throw new RuntimeException('This card number cannot be encoded by the local QR generator.');
    }

    $dataCapacity = count($payloadBytes) <= 32 ? 34 : 55;
    $errorCorrectionCapacity = $dataCapacity === 34 ? 10 : 15;
    $size = $dataCapacity === 34 ? 25 : 29;
    $alignmentCenter = $dataCapacity === 34 ? 18 : 22;
    $bitCapacity = $dataCapacity * 8;
    $bits = '0100' . str_pad(decbin(count($payloadBytes)), 8, '0', STR_PAD_LEFT);
    foreach ($payloadBytes as $byte) {
        $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }
    $bits .= str_repeat('0', min(4, $bitCapacity - strlen($bits)));
    while (strlen($bits) % 8 !== 0) {
        $bits .= '0';
    }
    $data = [];
    foreach (str_split($bits, 8) as $byteBits) {
        $data[] = bindec($byteBits);
    }
    for ($pad = 0; count($data) < $dataCapacity; $pad++) {
        $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
    }

    $generator = [1];
    $root = 1;
    for ($rootIndex = 0; $rootIndex < $errorCorrectionCapacity; $rootIndex++) {
        $next = array_fill(0, count($generator) + 1, 0);
        foreach ($generator as $index => $coefficient) {
            $next[$index] ^= $coefficient;
            $next[$index + 1] ^= studentQrMultiply($coefficient, $root);
        }
        $generator = $next;
        $root = studentQrMultiply($root, 2);
    }
    $work = array_merge($data, array_fill(0, $errorCorrectionCapacity, 0));
    for ($index = 0; $index < $dataCapacity; $index++) {
        $factor = $work[$index];
        if ($factor !== 0) {
            for ($offset = 0; $offset < count($generator); $offset++) {
                $work[$index + $offset] ^= studentQrMultiply($generator[$offset], $factor);
            }
        }
    }
    $codewords = array_merge($data, array_slice($work, $dataCapacity));
    $encodedBits = '';
    foreach ($codewords as $byte) {
        $encodedBits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }

    $matrix = array_fill(0, $size, array_fill(0, $size, null));
    $function = array_fill(0, $size, array_fill(0, $size, false));
    $setFunction = static function (int $x, int $y, bool $value) use (&$matrix, &$function, $size): void {
        if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
            $matrix[$y][$x] = $value;
            $function[$y][$x] = true;
        }
    };

    $drawFinder = static function (int $centerX, int $centerY) use ($setFunction): void {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $x = $centerX + $dx;
                $y = $centerY + $dy;
                $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                $black = $inside && (
                    $dx === 0 || $dx === 6 || $dy === 0 || $dy === 6
                    || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4)
                );
                $setFunction($x, $y, $black);
            }
        }
    };
    $drawFinder(0, 0);
    $drawFinder($size - 7, 0);
    $drawFinder(0, $size - 7);

    for ($position = 8; $position < $size - 8; $position++) {
        $timingDark = $position % 2 === 0;
        $setFunction(6, $position, $timingDark);
        $setFunction($position, 6, $timingDark);
    }
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $distance = max(abs($dx), abs($dy));
            $setFunction($alignmentCenter + $dx, $alignmentCenter + $dy, $distance !== 1);
        }
    }

    $format = studentQrFormatBits(8);
    for ($index = 0; $index <= 5; $index++) {
        $setFunction(8, $index, (($format >> $index) & 1) !== 0);
    }
    $setFunction(8, 7, (($format >> 6) & 1) !== 0);
    $setFunction(8, 8, (($format >> 7) & 1) !== 0);
    $setFunction(7, 8, (($format >> 8) & 1) !== 0);
    for ($index = 9; $index < 15; $index++) {
        $setFunction(14 - $index, 8, (($format >> $index) & 1) !== 0);
    }
    for ($index = 0; $index < 8; $index++) {
        $setFunction($size - 1 - $index, 8, (($format >> $index) & 1) !== 0);
    }
    for ($index = 8; $index < 15; $index++) {
        $setFunction(8, $size - 15 + $index, (($format >> $index) & 1) !== 0);
    }
    $setFunction(8, $size - 8, true);

    $bitIndex = 0;
    $upward = true;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vertical = 0; $vertical < $size; $vertical++) {
            $y = $upward ? $size - 1 - $vertical : $vertical;
            for ($offset = 0; $offset < 2; $offset++) {
                $x = $right - $offset;
                if ($function[$y][$x]) {
                    continue;
                }
                $bit = $bitIndex < strlen($encodedBits) && $encodedBits[$bitIndex] === '1';
                $matrix[$y][$x] = $bit !== ((($x + $y) % 2) === 0);
                $bitIndex++;
            }
        }
        $upward = !$upward;
    }

    $quiet = 4;
    $viewSize = $size + 2 * $quiet;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $viewSize . ' ' . $viewSize
        . '" role="img" aria-label="QR code for student card" shape-rendering="crispEdges">';
    $svg .= '<rect width="100%" height="100%" fill="' . strtolower($background) . '"/><path fill="' . strtolower($foreground) . '" d="';
    foreach ($matrix as $y => $row) {
        foreach ($row as $x => $dark) {
            if ($dark === true) {
                $svg .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
            }
        }
    }
    return $svg . '"/></svg>';
}
