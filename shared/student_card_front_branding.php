<?php

function studentCardFrontBrandingFonts(): array
{
    return [
        'Arial' => 'Arial, sans-serif',
        'Georgia' => 'Georgia, serif',
        'Times New Roman' => '"Times New Roman", serif',
        'Verdana' => 'Verdana, sans-serif',
        'Tahoma' => 'Tahoma, sans-serif',
    ];
}

function studentCardFrontBrandingDefaults(): array
{
    return [
        'university_name' => 'KUMI UNIVERSITY',
        'card_title' => "STUDENT'S IDENTITY CARD",
        'university_color' => '#1a5f9e',
        'university_font_size_px' => '20',
        'university_font_family' => 'Times New Roman',
        'card_title_color' => '#ff0000',
        'card_title_font_size_px' => '12',
        'card_title_font_family' => 'Times New Roman',
        'logo_file' => '',
        'logo_height_px' => '40',
        'watermark_enabled' => '1',
        'watermark_width_mm' => '55',
        'watermark_height_mm' => '35',
        'watermark_opacity' => '0.12',
        'watermark_horizontal_position' => 'center',
        'watermark_vertical_position' => 'center',
        'watermark_x_mm' => '0',
        'watermark_y_mm' => '0',
        'holder_signature_x_mm' => '0',
        'holder_signature_width_mm' => '25',
        'registrar_signature_x_mm' => '0',
        'registrar_signature_width_mm' => '28',
        'signature_y_mm' => '0',
        'right_pattern_width_pct' => '25',
        'right_pattern_height_pct' => '16.66',
        'right_pattern_scale_pct' => '100',
        'bar_color' => '#1a5f9e',
        'bar_thickness_pct' => '100',
        'bar_top_visible' => '1',
        'bar_middle_visible' => '1',
        'bar_bottom_visible' => '1',
        'bar_top_x_mm' => '0',
        'bar_top_y_mm' => '0',
        'bar_top_rotation_deg' => '0',
        'bar_bottom_x_mm' => '0',
        'bar_bottom_y_mm' => '0',
        'bar_bottom_rotation_deg' => '0',
    ];
}

function studentCardFrontBrandingSettings(mysqli $conn): array
{
    static $settingsByConnection = [];
    $connectionKey = spl_object_id($conn);
    if (isset($settingsByConnection[$connectionKey])) {
        return $settingsByConnection[$connectionKey];
    }

    $settings = studentCardFrontBrandingDefaults();
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'card_front_branding_%'");
    while ($row = $result->fetch_assoc()) {
        $key = substr((string)$row['setting_key'], strlen('card_front_branding_'));
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string)$row['setting_value'];
        }
    }

    foreach (['university_color', 'card_title_color', 'bar_color'] as $key) {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $settings[$key]) !== 1) {
            $settings[$key] = studentCardFrontBrandingDefaults()[$key];
        } else {
            $settings[$key] = strtolower($settings[$key]);
        }
    }
    $fonts = studentCardFrontBrandingFonts();
    foreach (['university_font_family', 'card_title_font_family'] as $key) {
        if (!array_key_exists($settings[$key], $fonts)) {
            $settings[$key] = 'Times New Roman';
        }
    }
    foreach (['university_name' => 80, 'card_title' => 80] as $key => $maximum) {
        $settings[$key] = trim(substr($settings[$key], 0, $maximum));
    }
    foreach ([
        'university_font_size_px' => [12, 24],
        'card_title_font_size_px' => [8, 16],
        'logo_height_px' => [24, 48],
        'watermark_width_mm' => [10, 80],
        'watermark_height_mm' => [10, 50],
        'watermark_opacity' => [0.05, 0.5],
        'watermark_x_mm' => [-42.5, 42.5],
        'watermark_y_mm' => [-27, 27],
        'holder_signature_x_mm' => [-30, 30],
        'holder_signature_width_mm' => [5, 40],
        'registrar_signature_x_mm' => [-30, 30],
        'registrar_signature_width_mm' => [5, 40],
        'signature_y_mm' => [-25, 25],
        'right_pattern_width_pct' => [1, 100],
        'right_pattern_height_pct' => [1, 100],
        'right_pattern_scale_pct' => [25, 300],
        'bar_thickness_pct' => [50, 200],
        'bar_top_x_mm' => [-10, 10],
        'bar_top_y_mm' => [-10, 10],
        'bar_top_rotation_deg' => [-15, 15],
        'bar_bottom_x_mm' => [-10, 10],
        'bar_bottom_y_mm' => [-10, 10],
        'bar_bottom_rotation_deg' => [-15, 15],
    ] as $key => [$minimum, $maximum]) {
        $size = is_numeric($settings[$key]) ? (float)$settings[$key] : (float)studentCardFrontBrandingDefaults()[$key];
        $precision = $key === 'watermark_opacity' || $key === 'right_pattern_height_pct' ? 2 : 1;
        $settings[$key] = number_format(max($minimum, min($maximum, $size)), $precision, '.', '');
    }
    $settings['watermark_enabled'] = $settings['watermark_enabled'] === '1' ? '1' : '0';
    if (!in_array($settings['watermark_horizontal_position'], ['left', 'center', 'right'], true)) {
        $settings['watermark_horizontal_position'] = 'center';
    }
    if (!in_array($settings['watermark_vertical_position'], ['top', 'center', 'bottom'], true)) {
        $settings['watermark_vertical_position'] = 'center';
    }
    foreach (['bar_top_visible', 'bar_middle_visible', 'bar_bottom_visible'] as $key) {
        $settings[$key] = $settings[$key] === '1' ? '1' : '0';
    }
    if (preg_match('/\A[a-f0-9]{32}\.(?:png|jpg|webp)\z/', $settings['logo_file']) !== 1) {
        $settings['logo_file'] = '';
    }

    $settingsByConnection[$connectionKey] = $settings;
    return $settings;
}

function studentCardFrontBrandingBarsStyle(array $settings): string
{
    $scale = number_format((float)$settings['bar_thickness_pct'] / 100, 2, '.', '');
    $style = [
        '--ku-id-bars-color:' . $settings['bar_color'],
        '--ku-id-bar-thickness-scale:' . $scale,
        '--ku-id-top-bar-display:' . ($settings['bar_top_visible'] === '1' ? 'block' : 'none'),
        '--ku-id-middle-bar-display:' . ($settings['bar_middle_visible'] === '1' ? 'block' : 'none'),
        '--ku-id-footer-bar-display:' . ($settings['bar_bottom_visible'] === '1' ? 'block' : 'none'),
    ];
    foreach (['top' => 'top', 'bottom' => 'bottom'] as $position => $key) {
        $style[] = '--ku-id-' . $position . '-bar-x:' . $settings['bar_' . $key . '_x_mm'] . 'mm';
        $style[] = '--ku-id-' . $position . '-bar-y:' . $settings['bar_' . $key . '_y_mm'] . 'mm';
        $style[] = '--ku-id-' . $position . '-bar-rotation:' . $settings['bar_' . $key . '_rotation_deg'] . 'deg';
    }
    return implode(';', $style);
}

function studentCardFrontBrandingWatermarkStyle(array $settings): string
{
    $horizontal = [
        'left' => ['0%', '0%'],
        'center' => ['50%', '-50%'],
        'right' => ['100%', '-100%'],
    ][$settings['watermark_horizontal_position']];
    $vertical = [
        'top' => ['0%', '0%'],
        'center' => ['50%', '-50%'],
        'bottom' => ['100%', '-100%'],
    ][$settings['watermark_vertical_position']];
    return implode(';', [
        '--ku-id-watermark-left:' . $horizontal[0],
        '--ku-id-watermark-shift-x:' . $horizontal[1],
        '--ku-id-watermark-x:' . $settings['watermark_x_mm'] . 'mm',
        '--ku-id-watermark-top:' . $vertical[0],
        '--ku-id-watermark-shift-y:' . $vertical[1],
        '--ku-id-watermark-y:' . $settings['watermark_y_mm'] . 'mm',
    ]);
}

function studentCardFrontBrandingSignatureStyle(array $settings, string $signature): string
{
    if (!in_array($signature, ['holder', 'registrar'], true)) {
        throw new InvalidArgumentException('Unsupported student card signature style.');
    }
    return implode(';', [
        '--ku-id-' . $signature . '-signature-x:' . $settings[$signature . '_signature_x_mm'] . 'mm',
        '--ku-id-' . $signature . '-signature-width:' . $settings[$signature . '_signature_width_mm'] . 'mm',
        '--ku-id-signature-y:' . $settings['signature_y_mm'] . 'mm',
    ]);
}

function studentCardFrontBrandingRightPatternStyle(array $settings): string
{
    $scale = (float)$settings['right_pattern_scale_pct'] / 100;
    $width = number_format((float)$settings['right_pattern_width_pct'] * $scale, 2, '.', '');
    $height = number_format((float)$settings['right_pattern_height_pct'] * $scale, 2, '.', '');
    return '--ku-id-right-pattern-width:' . $width . '%;--ku-id-right-pattern-height:' . $height . '%';
}

function studentCardFrontBrandingTextStyle(array $settings, string $prefix): string
{
    $colorKey = $prefix === 'university' ? 'university_color' : 'card_title_color';
    $sizeKey = $prefix . '_font_size_px';
    $fontKey = $prefix . '_font_family';
    if (!isset($settings[$colorKey], $settings[$sizeKey], $settings[$fontKey])) {
        throw new InvalidArgumentException('Unsupported student card header text style.');
    }
    $font = studentCardFrontBrandingFonts()[$settings[$fontKey]];
    return 'color:' . $settings[$colorKey] . ';font-size:' . $settings[$sizeKey] . 'px;font-family:' . $font;
}

function studentCardFrontLogoPath(array $settings): string
{
    return $settings['logo_file'] === ''
        ? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'KU Logo.png'
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'card-branding' . DIRECTORY_SEPARATOR . $settings['logo_file'];
}

function studentCardFrontLogoUrl(array $settings, bool $admin = false): string
{
    return $admin
        ? ($settings['logo_file'] === '' ? '../KU Logo.png' : '../assets/card-branding/' . rawurlencode($settings['logo_file']))
        : ($settings['logo_file'] === '' ? 'KU Logo.png' : '../assets/card-branding/' . rawurlencode($settings['logo_file']));
}
