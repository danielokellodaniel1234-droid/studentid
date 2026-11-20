<?php
if (($_GET['document'] ?? '') === 'registrar-signature') {
    require __DIR__ . '/admin/faculties_courses.php';
    exit;
}
header('Location: admin/console.php?view=catalog');
exit;
