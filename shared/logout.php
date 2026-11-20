<?php
session_start();

$_SESSION = [];
$sessionCookie = session_get_cookie_params();
session_destroy();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $sessionCookie['path'],
    'domain' => $sessionCookie['domain'],
    'secure' => $sessionCookie['secure'],
    'httponly' => $sessionCookie['httponly'],
    'samesite' => $sessionCookie['samesite'] ?? 'Lax',
]);

setcookie('remember_user', '', time() - 3600, '/');
setcookie('remember_token', '', time() - 3600, '/');

$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/logout.php')));
if (in_array(basename($scriptDirectory), ['admin', 'shared', 'students'], true)) {
    $scriptDirectory = dirname($scriptDirectory);
}
$loginPath = rtrim($scriptDirectory, '/') . '/login.php?success=logout';
header('Location: ' . $loginPath);
exit;