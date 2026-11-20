<?php

function studentCardVerificationUrl(string $token): string
{
    if (!preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
        throw new InvalidArgumentException('A valid student card verification token is required.');
    }

    $configuredBase = trim((string)(defined('STUDENT_ID_PUBLIC_URL')
        ? STUDENT_ID_PUBLIC_URL
        : (getenv('STUDENT_ID_PUBLIC_URL') ?: '')));
    if ($configuredBase !== '') {
        $parts = parse_url($configuredBase);
        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('STUDENT_ID_PUBLIC_URL must be an absolute HTTP(S) application URL without credentials or query parameters.');
        }
        $baseUrl = rtrim($configuredBase, '/');
    } else {
        $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
        $isIpv6 = filter_var($serverName, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        if (!$isIpv6 && filter_var($serverName, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            && filter_var($serverName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new RuntimeException('The server name is not configured for card verification links.');
        }
        $scheme = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $host = $isIpv6 ? '[' . $serverName . ']' : $serverName;
        $port = (int)($_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port > 0 && $port !== ($scheme === 'https' ? 443 : 80)) {
            $host .= ':' . $port;
        }
        $scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        if (in_array(basename($scriptDirectory), ['admin', 'students'], true)) {
            $scriptDirectory = dirname($scriptDirectory);
        }
        $applicationPath = '/' . trim($scriptDirectory, '/');
        $baseUrl = $scheme . '://' . $host . ($applicationPath === '/' ? '' : $applicationPath);
    }

    $shortToken = rtrim(strtr(base64_encode(hex2bin($token)), '+/', '-_'), '=');

    return $baseUrl . '/v.php?t=' . rawurlencode($shortToken);
}

function studentCode128Svg(string $payload, string $foreground = '#111111', string $background = '#ffffff'): string
{
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $foreground) !== 1 || preg_match('/^#[0-9a-fA-F]{6}$/', $background) !== 1) {
        throw new InvalidArgumentException('Barcode foreground and background colors must be six-digit hex colors.');
    }
    static $patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    $codewords = [104];
    foreach (unpack('C*', $payload) ?: [] as $byte) {
        if ($byte < 32 || $byte > 127) {
            throw new InvalidArgumentException('Code 128 verification links must contain printable ASCII characters.');
        }
        $codewords[] = $byte - 32;
    }
    if (count($codewords) < 2) {
        throw new InvalidArgumentException('A non-empty verification link is required for the barcode.');
    }

    $checksum = 104;
    for ($index = 1; $index < count($codewords); $index++) {
        $checksum += $codewords[$index] * $index;
    }
    $codewords[] = $checksum % 103;
    $codewords[] = 106;

    $quiet = 12;
    $modules = $quiet * 2;
    foreach ($codewords as $codeword) {
        $modules += array_sum(array_map('intval', str_split($patterns[$codeword])));
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $modules
        . ' 100" preserveAspectRatio="none" role="img" aria-label="Barcode for Kumi University card verification" shape-rendering="crispEdges">';
    $svg .= '<rect width="100%" height="100%" fill="' . strtolower($background) . '"/><path fill="' . strtolower($foreground) . '" d="';
    $x = $quiet;
    foreach ($codewords as $codeword) {
        $pattern = str_split($patterns[$codeword]);
        $isBar = true;
        foreach ($pattern as $width) {
            $barWidth = (int)$width;
            if ($isBar) {
                $svg .= 'M' . $x . ' 0h' . $barWidth . 'v82h-' . $barWidth . 'z';
            }
            $x += $barWidth;
            $isBar = !$isBar;
        }
    }

    return $svg . '"/></svg>';
}
