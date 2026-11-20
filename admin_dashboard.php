<?php
$projectPath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin_dashboard.php'))), '/');
header('Location: ' . $projectPath . '/admin/console.php?view=dashboard');
exit;
