<?php
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$encodedToken = (string)($_GET['t'] ?? $_GET['token'] ?? '');
$token = '';
$card = null;
if (preg_match('/\A[a-f0-9]{48}\z/D', $encodedToken)) {
    $token = $encodedToken;
} elseif (preg_match('/\A[A-Za-z0-9_-]{32}\z/D', $encodedToken)) {
    $decodedToken = base64_decode(strtr($encodedToken, '-_', '+/'), true);
    if ($decodedToken !== false && strlen($decodedToken) === 24) {
        $token = bin2hex($decodedToken);
    }
}
if ($token !== '') {
    $stmt = $conn->prepare(
        'SELECT c.card_number, c.card_status, c.expires_at,
                a.surname, a.given_name, f.name AS faculty_name, co.name AS course_name
         FROM id_cards c
         INNER JOIN id_applications a ON a.id = c.application_id AND a.student_id = c.student_id
         LEFT JOIN student_faculties f ON f.id = a.faculty_id
         LEFT JOIN student_courses co ON co.id = a.course_id
         WHERE c.verification_token = ? LIMIT 1'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$valid = $card !== null
    && $card['card_status'] === 'issued'
    && (empty($card['expires_at']) || $card['expires_at'] >= date('Y-m-d'));
if (!$card) {
    http_response_code(404);
}
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$fullName = $card ? trim((string)$card['given_name'] . ' ' . (string)$card['surname']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student ID Verification - Kumi University</title>
    <style>
        body{margin:0;padding:24px;background:#f3f6fa;color:#26384a;font:16px/1.5 Arial,sans-serif;text-align:left}
        main{max-width:480px;margin:8vh auto;padding:20px;border:1px solid #dce5ee;border-radius:12px;background:#fff;box-shadow:0 4px 18px #18314b18}
        h1{margin-top:0;color:#1a5f9e;font-size:23px}
        .status{padding:10px 12px;border-radius:6px;background:#fde7e5;color:#9c2d26;font-weight:700}
        .status.valid{background:#e1f4e8;color:#226b3d}
        dl{display:grid;grid-template-columns:max-content 1fr;gap:7px 14px;text-align:left}
        dt{font-weight:700}dd{margin:0;overflow-wrap:anywhere}
    </style>
</head>
<body>
<main>
    <h1>Kumi University Student ID Verification</h1>
    <?php if (!$card): ?>
        <p class="status">This verification link is not recognized. Contact Kumi University for assistance.</p>
    <?php else: ?>
        <p class="status<?php echo $valid ? ' valid' : ''; ?>"><?php echo $valid ? 'Valid issued student ID' : 'This card is not currently valid'; ?></p>
        <dl>
            <dt>Cardholder</dt><dd><?php echo $escape($fullName ?: 'Not recorded'); ?></dd>
            <dt>Card number</dt><dd><?php echo $escape($card['card_number']); ?></dd>
            <dt>Faculty</dt><dd><?php echo $escape($card['faculty_name'] ?: 'Not recorded'); ?></dd>
            <dt>Programme</dt><dd><?php echo $escape($card['course_name'] ?: 'Not recorded'); ?></dd>
            <dt>Expiry date</dt><dd><?php echo $escape($card['expires_at'] ?: 'Not recorded'); ?></dd>
        </dl>
    <?php endif; ?>
</main>
</body>
</html>
