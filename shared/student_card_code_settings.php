<?php

function studentCardColorContrast(string $foreground, string $background): float
{
    $luminance = static function (string $color): float {
        $channels = sscanf($color, '#%02x%02x%02x');
        if (!is_array($channels) || count($channels) !== 3) {
            return 0.0;
        }
        $linear = array_map(static function (int $channel): float {
            $value = $channel / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    };

    $first = $luminance($foreground);
    $second = $luminance($background);
    return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
}

function studentCardCodeValidateTemplate(string $template, string $type): void
{
    $placeholderLimits = $type === 'qr'
        ? ['card_number' => 50]
        : ($type === 'barcode'
            ? ['card_number' => 50, 'student_id' => 80, 'student_name' => 150, 'verification_url' => 160]
            : null);
    if ($placeholderLimits === null) {
        throw new InvalidArgumentException('Unsupported student card code type.');
    }

    $expanded = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $match) use ($placeholderLimits): string {
        if (!array_key_exists($match[1], $placeholderLimits)) {
            throw new InvalidArgumentException('The code template contains an unsupported placeholder: {' . $match[1] . '}.');
        }
        return str_repeat('X', $placeholderLimits[$match[1]]);
    }, $template);
    if (!is_string($expanded) || $template === '' || strlen($template) > 250 || strpbrk($expanded, '{}') !== false) {
        throw new InvalidArgumentException('Code templates must be non-empty and contain only supported placeholders.');
    }

    if ($type === 'qr' && strlen($expanded) > 53) {
        throw new InvalidArgumentException('QR template output must fit the 53-byte QR limit. Use only a card number with at most three extra characters.');
    }
    if ($type === 'barcode' && (strlen($expanded) > 250 || preg_match('/[^\x20-\x7E]/', $expanded) === 1)) {
        throw new InvalidArgumentException('Barcode templates must produce at most 250 printable ASCII characters.');
    }
}

function studentCardCodePayload(string $template, array $values, string $type): string
{
    studentCardCodeValidateTemplate($template, $type);
    $payload = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $match) use ($values): string {
        if (!array_key_exists($match[1], $values)) {
            throw new RuntimeException('A required value for the student card code template is unavailable.');
        }
        $value = (string)$values[$match[1]];
        if ($match[1] === 'student_name' && preg_match('/[^\x20-\x7E]/', $value) === 1) {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($value === false) {
                throw new RuntimeException('The student name could not be converted to printable barcode text.');
            }
        }
        return $value;
    }, $template);
    if (!is_string($payload) || $payload === '') {
        throw new RuntimeException('Student card code output cannot be empty.');
    }
    if ($type === 'qr' && strlen($payload) > 53) {
        throw new RuntimeException('This QR template output exceeds the 53-byte capacity. Shorten the card number or template.');
    }
    if ($type === 'barcode' && (strlen($payload) > 250 || preg_match('/[^\x20-\x7E]/', $payload) === 1)) {
        throw new RuntimeException('This barcode template output must be at most 250 printable ASCII characters.');
    }
    return $payload;
}

function studentCardCodeSettings(mysqli $conn): array
{
    static $settingsByConnection = [];
    $connectionKey = spl_object_id($conn);
    if (isset($settingsByConnection[$connectionKey])) {
        return $settingsByConnection[$connectionKey];
    }

    $settings = [
        'qr_foreground' => '#000000',
        'qr_background' => '#ffffff',
        'barcode_foreground' => '#000000',
        'barcode_background' => '#ffffff',
        'qr_output_template' => '{card_number}',
        'barcode_output_template' => '{verification_url}',
        'qr_size_mm' => '11',
        'barcode_width_percent' => '56',
        'barcode_height_mm' => '10',
    ];

    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'card_code_%'");
    while ($row = $result->fetch_assoc()) {
        $key = substr((string)$row['setting_key'], strlen('card_code_'));
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string)$row['setting_value'];
        }
    }

    foreach (['qr_foreground', 'qr_background', 'barcode_foreground', 'barcode_background'] as $key) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $settings[$key]) !== 1) {
            $settings[$key] = str_ends_with($key, 'background') ? '#ffffff' : '#000000';
        } else {
            $settings[$key] = strtolower($settings[$key]);
        }
    }
    foreach (['qr_output_template' => 'qr', 'barcode_output_template' => 'barcode'] as $key => $type) {
        try {
            studentCardCodeValidateTemplate($settings[$key], $type);
        } catch (InvalidArgumentException $exception) {
            $settings[$key] = $type === 'qr' ? '{card_number}' : '{verification_url}';
        }
    }
    if (studentCardColorContrast($settings['qr_foreground'], $settings['qr_background']) < 7) {
        $settings['qr_foreground'] = '#000000';
        $settings['qr_background'] = '#ffffff';
    }
    if (studentCardColorContrast($settings['barcode_foreground'], $settings['barcode_background']) < 7) {
        $settings['barcode_foreground'] = '#000000';
        $settings['barcode_background'] = '#ffffff';
    }

    $qrSize = is_numeric($settings['qr_size_mm']) ? (float)$settings['qr_size_mm'] : 11.0;
    $barcodeWidth = is_numeric($settings['barcode_width_percent']) ? (float)$settings['barcode_width_percent'] : 56.0;
    $barcodeHeight = is_numeric($settings['barcode_height_mm']) ? (float)$settings['barcode_height_mm'] : 10.0;
    $settings['qr_size_mm'] = number_format(max(8, min(14, $qrSize)), 1, '.', '');
    $settings['barcode_width_percent'] = number_format(max(50, min(60, $barcodeWidth)), 1, '.', '');
    $settings['barcode_height_mm'] = number_format(max(8, min(12, $barcodeHeight)), 1, '.', '');

    $settingsByConnection[$connectionKey] = $settings;
    return $settings;
}
