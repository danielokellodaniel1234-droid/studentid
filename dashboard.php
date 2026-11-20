<?php
$projectPath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/dashboard.php'))), '/');
header('Location: ' . $projectPath . '/admin/console.php?view=dashboard');
exit;
