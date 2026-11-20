<?php

function studentCardFrontRowFontFamilies(): array
{
    return [
        'Arial' => 'Arial, sans-serif',
        'Georgia' => 'Georgia, serif',
        'Times New Roman' => '"Times New Roman", serif',
        'Verdana' => 'Verdana, sans-serif',
        'Tahoma' => 'Tahoma, sans-serif',
    ];
}

function studentCardFrontRowDefaults(): array
{
    return [
        'surname' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'given_name' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'nationality' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'sex' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'issue_date' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'card_number' => ['label_color' => '#333333', 'value_color' => '#ff0000', 'font_size_px' => '7', 'font_family' => 'Arial'],
        'registration_number' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'faculty' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'course' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
        'expiry_date' => ['label_color' => '#333333', 'value_color' => '#1a5f9e', 'font_size_px' => '9', 'font_family' => 'Arial'],
    ];
}

function studentCardFrontRowSettings(mysqli $conn): array
{
    static $settingsByConnection = [];
    $connectionKey = spl_object_id($conn);
    if (isset($settingsByConnection[$connectionKey])) {
        return $settingsByConnection[$connectionKey];
    }

    $settings = studentCardFrontRowDefaults();
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'card_front_row_%'");
    while ($row = $result->fetch_assoc()) {
        $key = substr((string)$row['setting_key'], strlen('card_front_row_'));
        foreach (array_keys($settings) as $rowName) {
            foreach (array_keys($settings[$rowName]) as $property) {
                if ($key === $rowName . '_' . $property) {
                    $settings[$rowName][$property] = (string)$row['setting_value'];
                }
            }
        }
    }

    $fontFamilies = studentCardFrontRowFontFamilies();
    foreach ($settings as &$rowSettings) {
        foreach (['label_color', 'value_color'] as $colorKey) {
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $rowSettings[$colorKey]) !== 1) {
                $rowSettings[$colorKey] = '#333333';
            } else {
                $rowSettings[$colorKey] = strtolower($rowSettings[$colorKey]);
            }
        }
        $size = is_numeric($rowSettings['font_size_px']) ? (float)$rowSettings['font_size_px'] : 9.0;
        $rowSettings['font_size_px'] = number_format(max(6, min(12, $size)), 1, '.', '');
        if (!array_key_exists($rowSettings['font_family'], $fontFamilies)) {
            $rowSettings['font_family'] = 'Arial';
        }
    }
    unset($rowSettings);

    $settingsByConnection[$connectionKey] = $settings;
    return $settings;
}

function studentCardFrontRowStyle(array $settings, string $rowName): string
{
    if (!isset($settings[$rowName])) {
        throw new InvalidArgumentException('Unsupported student card text row.');
    }
    $row = $settings[$rowName];
    $fontFamily = studentCardFrontRowFontFamilies()[$row['font_family']];
    return '--ku-id-row-label-color:' . $row['label_color']
        . ';--ku-id-row-value-color:' . $row['value_color']
        . ';--ku-id-row-font-size:' . $row['font_size_px'] . 'px'
        . ';--ku-id-row-font-family:' . $fontFamily;
}
