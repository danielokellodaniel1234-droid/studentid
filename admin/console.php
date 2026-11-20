<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/layout.php';
require_once dirname(__DIR__) . '/shared/student_card_barcode.php';
require_once dirname(__DIR__) . '/shared/student_card_code_settings.php';
require_once dirname(__DIR__) . '/shared/student_card_front_row_settings.php';
require_once dirname(__DIR__) . '/shared/student_card_front_branding.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$view = (string)($_GET['view'] ?? 'dashboard');
$validViews = [
    'dashboard', 'analytics', 'reports', 'students', 'registry', 'student_view', 'student_edit', 'student_add',
    'student_export', 'registry_export', 'report_export', 'import', 'staff', 'applications', 'service_requests', 'print_queue', 'print_batch',
    'card', 'templates', 'catalog', 'settings', 'announcements', 'notifications', 'private_document',
    'monitor', 'audit', 'backup', 'backup_download', 'backup_restore',
];
if (!in_array($view, $validViews, true)) {
    $view = 'dashboard';
}
$adminOnlyViews = ['registry', 'registry_export', 'import', 'staff', 'student_add', 'student_edit', 'student_export', 'settings', 'announcements', 'backup', 'backup_download', 'backup_restore'];
adminRequireAccess(in_array($view, $adminOnlyViews, true));

if ($view === 'catalog') {
    adminRequireAccess(true);
    require __DIR__ . '/faculties_courses.php';
    exit;
}

function adminGo(string $view, array $query = []): never
{
    $query = ['view' => $view] + $query;
    header('Location: console.php?' . http_build_query($query));
    exit;
}

function adminSetSetting(mysqli $conn, string $key, string $value, int $userId): void
{
    $stmt = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );
    $stmt->bind_param('ssi', $key, $value, $userId);
    $stmt->execute();
    $stmt->close();
}

function adminGetSetting(mysqli $conn, string $key, string $default = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['setting_value'] : $default;
}

function adminCardBackSettings(mysqli $conn): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'header_title' => 'This Card is a property of Kumi University',
        'motto' => '“Endliness & Excellence for servanthood”',
        'address' => 'P.O. Box 178, Kumi (U)',
        'telephone' => 'Tel: +256 392 001 391',
        'email' => 'Email: ar@kumiuniversity.ac.ug',
        'website' => 'Website: www.kumiuniversity.ac.ug',
        'return_text' => 'If found, please return to the above address',
        'accent_color' => '#1a5f9e',
        'header_text_color' => '#ffffff',
        'body_text_color' => '#242424',
    ];

    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'card_back_%'");
    while ($row = $result->fetch_assoc()) {
        $key = substr((string)$row['setting_key'], strlen('card_back_'));
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string)$row['setting_value'];
        }
    }

    foreach (['accent_color', 'header_text_color', 'body_text_color'] as $colorKey) {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $settings[$colorKey]) !== 1) {
            $settings[$colorKey] = [
                'accent_color' => '#1a5f9e',
                'header_text_color' => '#ffffff',
                'body_text_color' => '#242424',
            ][$colorKey];
        }
    }

    return $settings;
}

function adminBadge(string $status): string
{
    $tone = in_array($status, ['issued', 'printed', 'active', 'approved', 'verified', 'completed'], true)
        ? 'success'
        : (in_array($status, ['rejected', 'inactive', 'disabled'], true) ? 'danger' : 'warning');
    return '<span class="admin-badge ' . $tone . '">' . adminEscape(str_replace('_', ' ', $status)) . '</span>';
}

function adminPrivateRoot(): string
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private';
}

function adminStoredFile(string $relativePath): string|false
{
    $root = realpath(adminPrivateRoot());
    if (!$root || $relativePath === '') {
        return false;
    }
    $resolved = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
    return $resolved && strpos($resolved, $root . DIRECTORY_SEPARATOR) === 0 && is_file($resolved)
        ? $resolved
        : false;
}

function adminRedirectWithFlash(string $view, string $message, string $type = 'success', array $query = []): never
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
    adminGo($view, $query);
}

function adminRecordApplicationEvent(
    mysqli $conn,
    int $applicationId,
    string $action,
    string $details
): void {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $actorRole = (string)($_SESSION['user_type'] ?? 'staff');
    $stmt = $conn->prepare(
        'INSERT INTO application_events (application_id, actor_id, actor_role, action, details)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iisss', $applicationId, $actorId, $actorRole, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function adminSaveStudent(mysqli $conn, int $adminId, bool $isNew): void
{
    $studentId = trim((string)($_POST['student_id'] ?? ''));
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $program = trim((string)($_POST['program'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'active'));
    $password = (string)($_POST['password'] ?? '');
    $id = filter_var($_POST['student_db_id'] ?? null, FILTER_VALIDATE_INT);
    if ($studentId === '' || strlen($studentId) > 80 || $fullName === '' || strlen($fullName) > 150
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
        || strlen($phone) > 50 || strlen($program) > 150
        || !in_array($gender, ['', 'Female', 'Male', 'Other'], true)
        || !in_array($status, ['active', 'inactive'], true)
        || ($isNew && (strlen($password) < 8 || strlen($password) > 200))
        || (!$isNew && (!$id || $id < 1))) {
        throw new RuntimeException('Check the student ID, name, email, status and other field lengths. New accounts need a password of at least 8 characters.');
    }
    if ($isNew) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO students (student_id, full_name, email, phone, program, gender, password, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssss', $studentId, $fullName, $email, $phone, $program, $gender, $hash, $status);
        $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();
        adminAudit($conn, 'student_created', 'Created student record #' . $id . ' (' . $studentId . ').');
    } else {
        $stmt = $conn->prepare(
            'UPDATE students SET student_id = ?, full_name = ?, email = ?, phone = ?, program = ?, gender = ?, status = ?
             WHERE id = ?'
        );
        $stmt->bind_param('sssssssi', $studentId, $fullName, $email, $phone, $program, $gender, $status, $id);
        $stmt->execute();
        $stmt->close();
        adminAudit($conn, 'student_updated', 'Updated student record #' . $id . ' (' . $studentId . ').');
    }
}

function adminImportStudents(mysqli $conn): int
{
    $file = $_FILES['student_csv'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Choose a CSV file to import.');
    }
    if ((int)$file['size'] < 1 || (int)$file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('The CSV file must be no larger than 5 MB.');
    }
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        throw new RuntimeException('The CSV file could not be opened.');
    }
    try {
        $header = fgetcsv($handle);
        if (!is_array($header)) {
            throw new RuntimeException('The CSV file is empty.');
        }
        $header = array_map(static fn($field): string => strtolower(trim((string)$field)), $header);
        $required = ['student_id', 'full_name', 'email', 'password'];
        if (array_diff($required, $header)) {
            throw new RuntimeException('CSV headers must include student_id, full_name, email, password. Optional: phone, program, gender.');
        }
        $indices = array_flip($header);
        $conn->begin_transaction();
        $inserted = 0;
        $stmt = $conn->prepare(
            'INSERT INTO students (student_id, full_name, email, phone, program, gender, password)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            if (++$inserted > 1000) {
                throw new RuntimeException('Import is limited to 1,000 student rows per file.');
            }
            $value = static fn(string $key): string => trim((string)($row[$indices[$key]] ?? ''));
            $studentId = $value('student_id');
            $fullName = $value('full_name');
            $email = $value('email');
            $password = $value('password');
            $phone = isset($indices['phone']) ? $value('phone') : '';
            $program = isset($indices['program']) ? $value('program') : '';
            $gender = isset($indices['gender']) ? $value('gender') : '';
            if ($studentId === '' || strlen($studentId) > 80 || $fullName === '' || strlen($fullName) > 150
                || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
                || strlen($password) < 8 || strlen($password) > 200
                || strlen($phone) > 50 || strlen($program) > 150
                || !in_array($gender, ['', 'Female', 'Male', 'Other'], true)) {
                throw new RuntimeException('CSV row ' . ($inserted + 1) . ' has invalid data. No rows were imported.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('sssssss', $studentId, $fullName, $email, $phone, $program, $gender, $hash);
            $stmt->execute();
        }
        $stmt->close();
        if ($inserted === 0) {
            throw new RuntimeException('The CSV contains no student data rows.');
        }
        $conn->commit();
        return $inserted;
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    } finally {
        fclose($handle);
    }
}

function adminSaveRegistryStudent(mysqli $conn, int $adminId): int
{
    $rawId = (string)($_POST['registry_id'] ?? '');
    $id = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT);
    $registrationNumber = trim((string)($_POST['registration_number'] ?? ''));
    $cardNumber = trim((string)($_POST['card_number'] ?? ''));
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $course = trim((string)($_POST['course'] ?? ''));
    $programme = trim((string)($_POST['programme'] ?? ''));
    $status = (string)($_POST['status'] ?? 'active');
    if ($registrationNumber === '' || strlen($registrationNumber) > 80
        || ($cardNumber !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/D', $cardNumber) !== 1)
        || $fullName === '' || strlen($fullName) > 150
        || strlen($course) > 150 || strlen($programme) > 150
        || !in_array($status, ['active', 'inactive'], true)
        || ($rawId !== '' && (!$id || $id < 1))) {
        throw new RuntimeException('Enter a registration number and full name. Card numbers must be unique and use up to 50 letters, numbers, dots, underscores or hyphens; course and programme must be 150 characters or fewer.');
    }
    if ($cardNumber !== '') {
        $duplicateStmt = $id
            ? $conn->prepare('SELECT id FROM student_registry WHERE card_number = ? AND id <> ? LIMIT 1')
            : $conn->prepare('SELECT id FROM student_registry WHERE card_number = ? LIMIT 1');
        if ($id) {
            $duplicateStmt->bind_param('si', $cardNumber, $id);
        } else {
            $duplicateStmt->bind_param('s', $cardNumber);
        }
        $duplicateStmt->execute();
        $duplicateCardNumber = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();
        if ($duplicateCardNumber) {
            throw new RuntimeException('That card number is already assigned to another student.');
        }
    }

    if ($id) {
        $stmt = $conn->prepare(
            'UPDATE student_registry
             SET registration_number = ?, card_number = NULLIF(?, \'\'), full_name = ?, course = ?, programme = ?, status = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ssssssi', $registrationNumber, $cardNumber, $fullName, $course, $programme, $status, $id);
        $stmt->execute();
        $stmt->close();
        adminAudit($conn, 'registry_student_updated', 'Updated registry record #' . $id . ' (' . $registrationNumber . ').');
        return $id;
    }

    $stmt = $conn->prepare(
        'INSERT INTO student_registry (registration_number, card_number, full_name, course, programme, created_by)
         VALUES (?, NULLIF(?, \'\'), ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssi', $registrationNumber, $cardNumber, $fullName, $course, $programme, $adminId);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    adminAudit($conn, 'registry_student_added', 'Added registry record #' . $id . ' (' . $registrationNumber . ').');
    return $id;
}

function adminImportRegistry(mysqli $conn): string
{
    require_once dirname(__DIR__) . '/shared/student_registry.php';
    $file = $_FILES['student_file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Choose an Excel or CSV roster file to import.');
    }
    if ((int)$file['size'] < 1 || (int)$file['size'] > 25 * 1024 * 1024) {
        throw new RuntimeException('The roster file must be no larger than 25 MB.');
    }
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mapped = studentRegistryMapSpreadsheetRows(
        studentRegistrySpreadsheetRows((string)$file['tmp_name'], $extension)
    );
    $records = $mapped['records'];

    $existing = [];
    foreach (array_chunk($records, 400) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $lookup = $conn->prepare('SELECT registration_number FROM student_registry WHERE registration_number IN (' . $placeholders . ')');
        $numbers = array_column($chunk, 'registration_number');
        $lookup->bind_param(str_repeat('s', count($numbers)), ...$numbers);
        $lookup->execute();
        $found = $lookup->get_result();
        while ($row = $found->fetch_assoc()) {
            $key = strtolower(preg_replace('/\s+/', '', (string)$row['registration_number']) ?? (string)$row['registration_number']);
            $existing[$key] = true;
        }
        $lookup->close();
    }

    $cardNumbers = array_values(array_filter(array_column($records, 'card_number'), static fn(string $value): bool => $value !== ''));
    $existingCardNumbers = [];
    foreach (array_chunk($cardNumbers, 400) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $lookup = $conn->prepare('SELECT card_number FROM student_registry WHERE card_number IN (' . $placeholders . ')');
        $lookup->bind_param(str_repeat('s', count($chunk)), ...$chunk);
        $lookup->execute();
        $found = $lookup->get_result();
        while ($row = $found->fetch_assoc()) {
            $existingCardNumbers[strtolower((string)$row['card_number'])] = true;
        }
        $lookup->close();
    }
    foreach ($records as $record) {
        $registrationKey = strtolower(preg_replace('/\s+/', '', $record['registration_number']) ?? $record['registration_number']);
        $cardNumber = $record['card_number'];
        if ($cardNumber !== '' && !isset($existing[$registrationKey]) && isset($existingCardNumbers[strtolower($cardNumber)])) {
            throw new RuntimeException('Card number "' . $cardNumber . '" is already assigned to a student in the registry. No rows were imported.');
        }
    }

    $conn->begin_transaction();
    $imported = 0;
    $alreadyThere = 0;
    try {
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare(
            'INSERT INTO student_registry (registration_number, card_number, full_name, course, programme, created_by)
             VALUES (?, NULLIF(?, \'\'), ?, ?, ?, ?)'
        );
        foreach ($records as $record) {
            $key = strtolower(preg_replace('/\s+/', '', $record['registration_number']) ?? $record['registration_number']);
            if (isset($existing[$key])) {
                $alreadyThere++;
                continue;
            }
            $registrationNumber = $record['registration_number'];
            $cardNumber = $record['card_number'];
            $fullName = $record['full_name'];
            $course = $record['course'];
            $programme = $record['programme'];
            try {
                $stmt->bind_param('sssssi', $registrationNumber, $cardNumber, $fullName, $course, $programme, $adminId);
                $stmt->execute();
                $imported++;
                $existing[$key] = true;
            } catch (mysqli_sql_exception $exception) {
                if ((int)$exception->getCode() !== 1062) {
                    throw $exception;
                }
                if (isset($existing[$key])) {
                    $alreadyThere++;
                } else {
                    throw new RuntimeException('A registration or card number conflicts with a student already in the registry. No rows were imported.');
                }
            }
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    $message = $imported . ' student' . ($imported === 1 ? '' : 's') . ' added to the registry.';
    $skippedDuplicates = (int)$mapped['skipped_duplicates'];
    if ($skippedDuplicates > 0) {
        $message .= ' ' . $skippedDuplicates . ' repeated registration number' . ($skippedDuplicates === 1 ? '' : 's') . ' in the file ' . ($skippedDuplicates === 1 ? 'was' : 'were') . ' skipped';
        if ($mapped['duplicate_examples']) {
            $message .= ' (' . implode(', ', $mapped['duplicate_examples']) . ')';
        }
        $message .= '. The first row for each number was kept.';
    }
    if ($alreadyThere > 0) {
        $message .= ' ' . $alreadyThere . ' registration number' . ($alreadyThere === 1 ? '' : 's') . ' already in the registry ' . ($alreadyThere === 1 ? 'was' : 'were') . ' left unchanged.';
    }
    return $message;
}

function adminCreateCard(mysqli $conn, int $applicationId): void
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT a.id, a.student_id, a.application_status, a.application_type, a.graduation_year,
                    p.payment_status, r.card_number AS registry_card_number
             FROM id_applications a
             LEFT JOIN payments p ON p.application_id = a.id
             INNER JOIN students s ON s.id = a.student_id
             LEFT JOIN student_registry r ON r.registration_number = s.student_id
             WHERE a.id = ? FOR UPDATE'
        );
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$application || $application['application_status'] !== 'approved') {
            throw new RuntimeException('Only approved applications can be added to the print queue.');
        }
        if ($application['application_type'] !== 'new' && $application['payment_status'] !== 'verified') {
            throw new RuntimeException('Verify the renewal/replacement payment before adding this card to the print queue.');
        }
        $cardNumber = trim((string)($application['registry_card_number'] ?? ''));
        if ($cardNumber === '') {
            $cardNumber = null;
        } else {
            $existingCardStmt = $conn->prepare('SELECT id FROM id_cards WHERE card_number = ? LIMIT 1');
            $existingCardStmt->bind_param('s', $cardNumber);
            $existingCardStmt->execute();
            $cardNumberInUse = $existingCardStmt->get_result()->fetch_assoc();
            $existingCardStmt->close();
            if ($cardNumberInUse) {
                throw new RuntimeException('The registry card number is already assigned. Update the registry number before adding this card to the print queue.');
            }
        }
        $expiresAt = $application['graduation_year']
            ? sprintf('%04d-12-31', (int)$application['graduation_year'])
            : null;
        $cardStmt = $conn->prepare(
            "INSERT INTO id_cards (application_id, student_id, card_number, card_status, expires_at, verification_token)
             VALUES (?, ?, ?, 'ready_to_print', ?, ?)"
        );
        $verificationToken = bin2hex(random_bytes(24));
        $cardStmt->bind_param('iisss', $applicationId, $application['student_id'], $cardNumber, $expiresAt, $verificationToken);
        $cardStmt->execute();
        $cardStmt->close();
        $conn->query("UPDATE id_applications SET application_status = 'ready_to_print' WHERE id = " . (int)$applicationId);
        adminRecordApplicationEvent($conn, $applicationId, 'added_to_print_queue', 'Card generated and added to the database-backed print queue.');
        adminAudit($conn, 'card_added_to_queue', 'Application #' . $applicationId . ' added to card print queue.');
        adminNotify($conn, 'Card ready to print', 'An approved ID application has been added to the print queue.', 'card', 'application', $applicationId);
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function adminCardQuery(mysqli $conn, int $applicationId): ?array
{
    $stmt = $conn->prepare(
        'SELECT c.id AS card_id, c.card_number, c.card_status, c.expires_at, c.issued_at, c.printed_at,
                c.verification_token,
                a.id AS application_id, NULLIF(TRIM(a.surname), \'\') AS surname,
                NULLIF(TRIM(a.given_name), \'\') AS given_name,
                NULLIF(TRIM(a.nationality), \'\') AS nationality,
                NULLIF(TRIM(a.sex), \'\') AS sex,
                NULLIF(TRIM(a.card_reg_no), \'\') AS card_reg_no,
                a.date_admitted, a.graduation_year, a.photo_path, a.signature_path, s.student_id, s.full_name,
                NULLIF(TRIM(f.name), \'\') AS faculty_name,
                NULLIF(TRIM(co.name), \'\') AS course_name
         FROM id_cards c
         INNER JOIN id_applications a ON a.id = c.application_id
         INNER JOIN students s ON s.id = c.student_id
         LEFT JOIN student_faculties f ON f.id = a.faculty_id
         LEFT JOIN student_courses co ON co.id = a.course_id
         WHERE a.id = ? AND c.student_id = a.student_id LIMIT 1'
    );
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function adminRenderCard(array $card, string $face, string $alignment): void
{
    require_once dirname(__DIR__) . '/shared/qr_code.php';
    $expiry = $card['graduation_year']
        ? sprintf('%04d-12-31', (int)$card['graduation_year'])
        : (string)($card['expires_at'] ?? '');
    global $conn;
    $backSettings = adminCardBackSettings($conn);
    $codeSettings = studentCardCodeSettings($conn);
    $frontRowSettings = studentCardFrontRowSettings($conn);
    $frontBranding = studentCardFrontBrandingSettings($conn);
    $verificationUrl = strpos($codeSettings['barcode_output_template'], '{verification_url}') !== false
        ? studentCardVerificationUrl((string)$card['verification_token'])
        : '';
    $codeValues = [
        'card_number' => (string)$card['card_number'],
        'student_id' => (string)$card['student_id'],
        'student_name' => (string)$card['full_name'],
        'verification_url' => $verificationUrl,
    ];
    $qrPayload = studentCardCodePayload($codeSettings['qr_output_template'], $codeValues, 'qr');
    $barcodePayload = studentCardCodePayload($codeSettings['barcode_output_template'], $codeValues, 'barcode');
    $registrarSignature = adminGetSetting($conn, 'academic_registrar_signature_path');
    $hasRegistrarSignature = adminStoredFile($registrarSignature) !== false;
    $facultyName = trim((string)($card['faculty_name'] ?? ''));
    $isPostgraduateFaculty = preg_match('/^school\s+of\s+post\s*graduate\b/i', $facultyName) === 1;
    $missingFields = [];
    foreach ([
        'surname' => 'surname',
        'given_name' => 'given name',
        'nationality' => 'nationality',
        'sex' => 'sex',
        'card_reg_no' => 'registration number',
        'date_admitted' => 'date of issue',
        'faculty_name' => 'faculty',
        'course_name' => 'course',
        'graduation_year' => 'graduation year',
    ] as $field => $label) {
        if (trim((string)($card[$field] ?? '')) === '') {
            $missingFields[] = $label;
        }
    }
    ?>
    <?php if ($missingFields): ?>
        <div class="admin-alert error no-print" role="status">
            This application is missing saved ID details: <?php echo adminEscape(implode(', ', $missingFields)); ?>.
            The card uses this application's own database record; missing values cannot be recovered from another application.
        </div>
    <?php endif; ?>
    <div class="ku-id-card-pair<?php echo $alignment === 'tray' ? ' print-tray-alignment' : ''; ?>">
    <?php if ($face === 'front' || $face === 'both'): ?>
    <article class="ku-id-card admin-card-print" aria-label="Front of student ID card for <?php echo adminEscape($card['full_name']); ?>" style="--ku-id-qr-size:<?php echo adminEscape($codeSettings['qr_size_mm']); ?>mm;<?php echo adminEscape(studentCardFrontBrandingBarsStyle($frontBranding) . ';' . studentCardFrontBrandingRightPatternStyle($frontBranding)); ?>">
        <?php if ($frontBranding['watermark_enabled'] === '1'): ?><img class="ku-id-custom-watermark" src="<?php echo adminEscape(studentCardFrontLogoUrl($frontBranding, true)); ?>" alt="" aria-hidden="true" style="--ku-id-watermark-width:<?php echo adminEscape($frontBranding['watermark_width_mm']); ?>mm;--ku-id-watermark-height:<?php echo adminEscape($frontBranding['watermark_height_mm']); ?>mm;--ku-id-watermark-opacity:<?php echo adminEscape($frontBranding['watermark_opacity']); ?>;<?php echo adminEscape(studentCardFrontBrandingWatermarkStyle($frontBranding)); ?>"><?php endif; ?>
        <div class="ku-id-top-bar"></div>
        <div class="ku-id-header">
            <div class="ku-id-logo" style="--ku-id-logo-height:<?php echo adminEscape($frontBranding['logo_height_px']); ?>px"><img src="<?php echo adminEscape(studentCardFrontLogoUrl($frontBranding, true)); ?>" alt="<?php echo adminEscape($frontBranding['university_name']); ?>"></div>
            <div class="ku-id-university-header"><div class="ku-id-university-name" style="<?php echo adminEscape(studentCardFrontBrandingTextStyle($frontBranding, 'university')); ?>"><?php echo adminEscape($frontBranding['university_name']); ?></div><div class="ku-id-card-title" style="<?php echo adminEscape(studentCardFrontBrandingTextStyle($frontBranding, 'card_title')); ?>"><?php echo adminEscape($frontBranding['card_title']); ?></div></div>
        </div>
        <div class="ku-id-middle-bar"></div>
        <div class="ku-id-content">
            <div class="ku-id-photo-section"><div class="ku-id-photo-stack">
                <div class="ku-id-photo-container">
                    <?php if (adminStoredFile((string)$card['photo_path'])): ?>
                            <img class="student-photo" style="<?php echo adminEscape('--admin-photo-x:' . number_format((float)($card['photo_large_x_mm'] ?? 0), 1, '.', '') . 'mm;--admin-photo-y:' . number_format((float)($card['photo_large_y_mm'] ?? 0), 1, '.', '') . 'mm;--admin-photo-scale:' . number_format((float)($card['photo_large_scale_pct'] ?? 100) / 100, 2, '.', '')); ?>" src="console.php?view=private_document&amp;application_id=<?php echo (int)$card['application_id']; ?>&amp;kind=photo" alt="Student photograph">
                    <?php else: ?><span class="ku-id-photo-missing">Photo unavailable</span><?php endif; ?>
                </div>
                <div class="ku-id-card-qr"><?php echo studentQrSvg($qrPayload, $codeSettings['qr_foreground'], $codeSettings['qr_background']); ?></div>
            </div></div>
            <div class="ku-id-details">
                <div class="ku-id-column ku-id-left-column">
                    <?php foreach (['Surname' => $card['surname'], 'Given Name' => $card['given_name']] as $label => $value): ?>
                        <?php $rowName = $label === 'Surname' ? 'surname' : 'given_name'; ?>
                        <div class="ku-id-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, $rowName)); ?>"><span class="ku-id-label"><?php echo adminEscape($label); ?>:</span><span class="ku-id-value"><?php echo adminEscape((string)($value ?: 'Not recorded')); ?></span></div>
                    <?php endforeach; ?>
                    <div class="ku-id-row ku-id-demographic-row">
                        <div class="ku-id-demographic-labels">
                            <span class="ku-id-label ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'nationality')); ?>">Nationality:</span>
                            <span class="ku-id-label ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'sex')); ?>">Sex:</span>
                        </div>
                        <div class="ku-id-demographic-values">
                            <span class="ku-id-value ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'nationality')); ?>"><?php echo adminEscape((string)($card['nationality'] ?: 'Not recorded')); ?></span>
                            <span class="ku-id-value ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'sex')); ?>"><?php echo adminEscape(strtoupper(substr(trim((string)$card['sex']), 0, 1)) ?: 'Not recorded'); ?></span>
                        </div>
                    </div>
                    <div class="ku-id-row ku-id-issue-date ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'issue_date')); ?>"><span class="ku-id-label">Date of Issue:</span><span class="ku-id-value"><?php echo !empty($card['date_admitted']) ? adminEscape(date('d.m.Y', strtotime((string)$card['date_admitted']))) : 'Not recorded'; ?></span></div>
                    <div class="ku-id-holder-signature"><span>HOLDERS SIGNATURE:</span><img style="<?php echo adminEscape(studentCardFrontBrandingSignatureStyle($frontBranding, 'holder')); ?>" src="console.php?view=private_document&amp;application_id=<?php echo (int)$card['application_id']; ?>&amp;kind=signature" alt="Student signature"></div>
                </div>
                <div class="ku-id-column ku-id-right-column">
                    <div class="ku-id-right-fields">
                        <div class="ku-id-row ku-id-horizontal ku-id-card-number-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'card_number')); ?>"><span class="ku-id-label">Card No.:</span><span class="ku-id-card-number"><?php echo adminEscape($card['card_number']); ?></span></div>
                        <div class="ku-id-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'registration_number')); ?>"><span class="ku-id-label">Reg No.:</span><span class="ku-id-reg-no"><?php echo adminEscape(str_replace('/', '.', (string)($card['card_reg_no'] ?: $card['student_id']))); ?></span></div>
                        <?php if ($isPostgraduateFaculty): ?>
                            <div class="ku-id-row ku-id-postgraduate-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'faculty')); ?>"><span class="ku-id-postgraduate-value"><?php echo adminEscape($facultyName); ?></span></div>
                        <?php else: ?>
                            <div class="ku-id-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'faculty')); ?>"><span class="ku-id-label">Faculty:</span><span class="ku-id-value"><?php echo adminEscape($facultyName ?: 'Not recorded'); ?></span></div>
                        <?php endif; ?>
                        <div class="ku-id-row ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'course')); ?>"><span class="ku-id-label">Course:</span><span class="ku-id-value"><?php echo adminEscape((string)($card['course_name'] ?? 'Not recorded')); ?></span></div>
                        <div class="ku-id-row ku-id-horizontal ku-id-custom-front-row" style="<?php echo adminEscape(studentCardFrontRowStyle($frontRowSettings, 'expiry_date')); ?>"><span class="ku-id-label">Expiry Date:</span><span class="ku-id-value"><?php echo $expiry ? adminEscape(date('d.m.Y', strtotime($expiry))) : 'Not recorded'; ?></span></div>
                    </div>
                    <?php if (adminStoredFile((string)$card['photo_path'])): ?>
                        <img class="ku-id-small-photo" style="<?php echo adminEscape('--admin-photo-x:' . number_format((float)($card['photo_small_x_mm'] ?? 0), 1, '.', '') . 'mm;--admin-photo-y:' . number_format((float)($card['photo_small_y_mm'] ?? 0), 1, '.', '') . 'mm;--admin-photo-scale:' . number_format((float)($card['photo_small_scale_pct'] ?? 100) / 100, 2, '.', '')); ?>" src="console.php?view=private_document&amp;application_id=<?php echo (int)$card['application_id']; ?>&amp;kind=photo" alt="Small student photograph">
                    <?php endif; ?>
                    <div class="ku-id-registrar-signature"><span>ACADEMIC REGISTRAR'S SIGNATURE:</span><?php if ($hasRegistrarSignature): ?><img style="<?php echo adminEscape(studentCardFrontBrandingSignatureStyle($frontBranding, 'registrar')); ?>" src="../admin_faculties_courses.php?document=registrar-signature" alt="Academic registrar signature"><?php endif; ?></div>
                </div>
            </div>
        </div>
        <div class="ku-id-footer-bar"></div>
    </article>
    <?php endif; ?>
    <?php if ($face === 'back' || $face === 'both'): ?>
    <article class="ku-id-card ku-id-card-back admin-card-print" aria-label="Back of student ID card for <?php echo adminEscape($card['full_name']); ?>" style="--ku-id-back-accent:<?php echo adminEscape($backSettings['accent_color']); ?>;--ku-id-back-header-text:<?php echo adminEscape($backSettings['header_text_color']); ?>;--ku-id-back-body-text:<?php echo adminEscape($backSettings['body_text_color']); ?>">
        <div class="ku-id-back-header">
            <strong><?php echo adminEscape($backSettings['header_title']); ?></strong>
            <span><?php echo adminEscape($backSettings['motto']); ?></span>
        </div>
        <div class="ku-id-back-contact">
            <?php foreach (['address', 'telephone', 'email', 'website'] as $contactKey): ?>
                <?php if ($backSettings[$contactKey] !== ''): ?><strong><?php echo adminEscape($backSettings[$contactKey]); ?></strong><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="ku-id-back-divider"></div>
        <div class="ku-id-back-barcode" style="--ku-id-barcode-width:<?php echo adminEscape($codeSettings['barcode_width_percent']); ?>%;--ku-id-barcode-height:<?php echo adminEscape($codeSettings['barcode_height_mm']); ?>mm"><?php echo studentCode128Svg($barcodePayload, $codeSettings['barcode_foreground'], $codeSettings['barcode_background']); ?></div>
        <?php if ($backSettings['return_text'] !== ''): ?><div class="ku-id-back-return"><?php echo adminEscape($backSettings['return_text']); ?></div><?php endif; ?>
    </article>
    <?php endif; ?>
    </div>
    <?php
}

if ($view === 'private_document') {
    $applicationId = filter_var($_GET['application_id'] ?? null, FILTER_VALIDATE_INT);
    $kind = (string)($_GET['kind'] ?? '');
    $columns = ['photo' => 'photo_path', 'signature' => 'signature_path', 'source_photo' => 'source_photo_path'];
    if (!$applicationId || ($kind !== 'receipt' && !isset($columns[$kind]))) {
        http_response_code(404);
        exit('Document not found.');
    }
    $stmt = $kind === 'receipt'
        ? $conn->prepare('SELECT p.receipt_path AS file_path FROM payments p INNER JOIN id_applications a ON a.id = p.application_id WHERE a.id = ? LIMIT 1')
        : $conn->prepare('SELECT `' . $columns[$kind] . '` AS file_path FROM id_applications WHERE id = ?');
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $fileRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $resolvedFile = adminStoredFile((string)($fileRow['file_path'] ?? ''));
    if (!$resolvedFile) {
        http_response_code(404);
        exit('Document not found.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($resolvedFile);
    $allowedMimes = $kind === 'receipt'
        ? ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
        : ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        http_response_code(415);
        exit('Unsupported document type.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($resolvedFile));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="' . ($kind === 'receipt' ? 'payment-receipt' : 'student-image') . '"');
    readfile($resolvedFile);
    exit;
}

if ($view === 'student_export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student-records-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['student_id', 'full_name', 'email', 'phone', 'program', 'gender', 'status', 'registration_date']);
    $result = $conn->query('SELECT student_id, full_name, email, phone, program, gender, status, registration_date FROM students ORDER BY id');
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}

if ($view === 'registry_export') {
    adminRequireAccess(true);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student-registry-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['registration_number', 'card_no', 'full_name', 'course', 'programme', 'status']);
    $result = $conn->query('SELECT registration_number, card_number AS card_no, full_name, course, programme, status FROM student_registry ORDER BY id');
    while ($row = $result->fetch_assoc()) {
        $values = array_values($row);
        foreach ($values as &$value) {
            if (is_string($value) && preg_match('/^[\s]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    exit;
}

if ($view === 'report_export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="id-card-report-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['application_id', 'student_id', 'full_name', 'application_type', 'application_status', 'card_number', 'card_status', 'faculty', 'course', 'created_at', 'printed_at', 'issued_at']);
    $result = $conn->query(
        'SELECT a.id AS application_id, s.student_id, s.full_name, a.application_type, a.application_status,
                c.card_number, c.card_status, f.name AS faculty, co.name AS course, a.created_at, c.printed_at, c.issued_at
         FROM id_applications a INNER JOIN students s ON s.id = a.student_id
         LEFT JOIN id_cards c ON c.application_id = a.id
         LEFT JOIN student_faculties f ON f.id = a.faculty_id LEFT JOIN student_courses co ON co.id = a.course_id
         ORDER BY a.created_at DESC'
    );
    while ($row = $result->fetch_assoc()) { fputcsv($output, array_values($row)); }
    fclose($output);
    exit;
}

if ($view === 'backup_download') {
    adminRequireAccess(true);
    $backupTables = ['students', 'student_registry', 'staff_accounts', 'student_faculties', 'student_courses', 'system_settings',
        'admin_announcements', 'id_applications', 'payments', 'id_cards', 'application_events',
        'admin_notifications', 'system_audit_log', 'activity_logs', 'student_service_requests'];
    $snapshot = ['format' => 'studentid-admin-backup-v1', 'created_at' => date(DATE_ATOM), 'tables' => []];
    foreach ($backupTables as $table) {
        $result = $conn->query('SELECT * FROM `' . $table . '`');
        $snapshot['tables'][$table] = $result->fetch_all(MYSQLI_ASSOC);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="studentid-backup-' . date('Y-m-d-His') . '.json"');
    header('Cache-Control: private, no-store');
    echo json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit;
}

$csrfToken = adminCsrfToken();
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$errors = [];
$formValues = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $adminOnlyActions = [
            'save_student', 'toggle_student', 'import_students', 'save_registry_student', 'import_registry', 'add_staff', 'toggle_staff',
            'application_settings', 'card_back_settings', 'card_code_settings', 'card_front_row_settings', 'card_front_branding', 'create_announcement', 'toggle_announcement', 'restore_backup',
            'update_card_number',
        ];
        if (in_array($action, $adminOnlyActions, true) && ($_SESSION['user_type'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Administrator access required.');
        }
        try {
            if ($action === 'save_student') {
                $isNew = (($_POST['student_db_id'] ?? '') === '');
                adminSaveStudent($conn, (int)$_SESSION['user_id'], $isNew);
                adminRedirectWithFlash('students', $isNew ? 'Student account added.' : 'Student record updated.');
            } elseif ($action === 'save_registry_student') {
                $isNew = (($_POST['registry_id'] ?? '') === '');
                adminSaveRegistryStudent($conn, (int)$_SESSION['user_id']);
                adminRedirectWithFlash('registry', $isNew ? 'Student added to the registry.' : 'Registry record updated.');
            } elseif ($action === 'import_registry') {
                $summary = adminImportRegistry($conn);
                adminAudit($conn, 'student_registry_imported', $summary);
                adminRedirectWithFlash('registry', $summary);
            } elseif ($action === 'update_card_number') {
                $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
                $cardNumber = trim((string)($_POST['card_number'] ?? ''));
                if (!$applicationId || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/D', $cardNumber)) {
                    throw new RuntimeException('Enter a card number of up to 50 letters, numbers, dots, underscores or hyphens.');
                }
                $conn->begin_transaction();
                $cardStmt = $conn->prepare('SELECT id, card_number FROM id_cards WHERE application_id = ? FOR UPDATE');
                $cardStmt->bind_param('i', $applicationId);
                $cardStmt->execute();
                $currentCard = $cardStmt->get_result()->fetch_assoc();
                $cardStmt->close();
                if (!$currentCard) {
                    throw new RuntimeException('The card could not be found.');
                }
                $cardId = (int)$currentCard['id'];
                $duplicateStmt = $conn->prepare('SELECT id FROM id_cards WHERE card_number = ? AND id <> ? LIMIT 1');
                $duplicateStmt->bind_param('si', $cardNumber, $cardId);
                $duplicateStmt->execute();
                $duplicate = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();
                if ($duplicate) {
                    throw new RuntimeException('That card number is already assigned to another card.');
                }
                if ((string)$currentCard['card_number'] !== $cardNumber) {
                    $updateStmt = $conn->prepare('UPDATE id_cards SET card_number = ? WHERE id = ?');
                    $updateStmt->bind_param('si', $cardNumber, $cardId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    adminAudit($conn, 'card_number_updated', 'Updated the card number for application #' . $applicationId . '.');
                }
                $conn->commit();
                adminRedirectWithFlash('card', 'Card number saved.', 'success', ['application_id' => $applicationId]);
            } elseif ($action === 'toggle_student') {
                $id = filter_var($_POST['student_db_id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string)($_POST['new_status'] ?? '');
                if (!$id || !in_array($status, ['active', 'inactive'], true)) {
                    throw new RuntimeException('Invalid student account status update.');
                }
                $stmt = $conn->prepare('UPDATE students SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('Student status was not changed. Reload and verify the current status.');
                }
                $stmt->close();
                adminAudit($conn, 'student_status_updated', 'Student #' . $id . ' status set to ' . $status . '.');
                adminRedirectWithFlash('students', 'Student account status updated.');
            } elseif ($action === 'import_students') {
                $count = adminImportStudents($conn);
                adminAudit($conn, 'students_imported', 'Imported ' . $count . ' student records from CSV.');
                adminRedirectWithFlash('students', $count . ' student records imported.');
            } elseif ($action === 'add_staff') {
                $username = trim((string)($_POST['username'] ?? ''));
                $name = trim((string)($_POST['full_name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $role = (string)($_POST['role'] ?? 'staff');
                if ($username === '' || strlen($username) > 80 || $name === '' || strlen($name) > 150
                    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
                    || strlen($password) < 8 || !in_array($role, ['admin', 'staff'], true)) {
                    throw new RuntimeException('Enter a username, full name, valid email, role, and password of at least 8 characters.');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO staff_accounts (username, full_name, email, password_hash, role, status)
                     VALUES (?, ?, ?, ?, ?, 'active')"
                );
                $stmt->bind_param('sssss', $username, $name, $email, $hash, $role);
                $stmt->execute();
                $newStaffId = (int)$conn->insert_id;
                $stmt->close();
                adminAudit($conn, 'staff_created', 'Created ' . $role . ' account #' . $newStaffId . '.');
                adminRedirectWithFlash('staff', 'Staff account created.');
            } elseif ($action === 'toggle_staff') {
                $id = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string)($_POST['new_status'] ?? '');
                if (!$id || $id === (int)$_SESSION['user_id'] || !in_array($status, ['active', 'disabled'], true)) {
                    throw new RuntimeException('Invalid account status update. You cannot disable your own account.');
                }
                $stmt = $conn->prepare('UPDATE staff_accounts SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('Staff account status was not changed.');
                }
                $stmt->close();
                adminAudit($conn, 'staff_status_updated', 'Staff account #' . $id . ' set to ' . $status . '.');
                adminRedirectWithFlash('staff', 'Staff account status updated.');
            } elseif ($action === 'application_review') {
                $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
                $decision = (string)($_POST['decision'] ?? '');
                if (!$id || !in_array($decision, ['start_review', 'approve', 'reject', 'make_print_ready'], true)) {
                    throw new RuntimeException('Invalid application action.');
                }
                $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
                $studentAdvice = trim((string)($_POST['student_advice'] ?? ''));
                if ($decision === 'reject' && (
                    $rejectionReason === '' || $studentAdvice === ''
                    || strlen($rejectionReason) > 450 || strlen($studentAdvice) > 450
                )) {
                    throw new RuntimeException('Enter a rejection reason and student advice, each no longer than 450 characters.');
                }
                if ($decision === 'make_print_ready') {
                    adminCreateCard($conn, $id);
                    adminRedirectWithFlash('applications', 'Card generated and added to the print queue.');
                }
                $conn->begin_transaction();
                $stmt = $conn->prepare('SELECT application_status FROM id_applications WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $transitions = [
                    'start_review' => ['submitted', 'under_review', 'Under review'],
                    'approve' => ['under_review', 'approved', 'Approved'],
                    'reject' => [['submitted', 'under_review'], 'rejected', 'Rejected'],
                ];
                [$expected, $newStatus, $label] = $transitions[$decision];
                $expectedStatuses = is_array($expected) ? $expected : [$expected];
                if (!$current || !in_array($current['application_status'], $expectedStatuses, true)) {
                    throw new RuntimeException('This application has changed or is not in the stage required for that action.');
                }
                $update = $conn->prepare('UPDATE id_applications SET application_status = ? WHERE id = ?');
                $update->bind_param('si', $newStatus, $id);
                $update->execute();
                $update->close();
                $eventDetails = $decision === 'reject'
                    ? "Reason: {$rejectionReason}\nAdvice: {$studentAdvice}"
                    : $label . ' by ' . ($_SESSION['full_name'] ?? 'administrator') . '.';
                adminRecordApplicationEvent(
                    $conn,
                    $id,
                    strtolower(str_replace(' ', '_', $label)),
                    $eventDetails
                );
                adminAudit($conn, 'application_' . strtolower(str_replace(' ', '_', $label)), 'Application #' . $id . ' ' . strtolower($label) . '.');
                adminNotify($conn, 'Application ' . strtolower($label), 'Application #' . $id . ' was ' . strtolower($label) . '.', 'application', 'application', $id);
                $conn->commit();
                adminRedirectWithFlash('applications', 'Application ' . strtolower($label) . '.');
            } elseif ($action === 'payment_review') {
                $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
                $decision = (string)($_POST['decision'] ?? '');
                if (!$applicationId || !in_array($decision, ['verified', 'rejected'], true)) {
                    throw new RuntimeException('Invalid payment review action.');
                }
                $stmt = $conn->prepare(
                    "UPDATE payments SET payment_status = ?, verified_by = ?, verified_at = NOW()
                     WHERE application_id = ? AND payment_status = 'pending'"
                );
                $adminId = (int)$_SESSION['user_id'];
                $stmt->bind_param('sii', $decision, $adminId, $applicationId);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('Payment evidence is not pending or has already been reviewed.');
                }
                $stmt->close();
                adminAudit($conn, 'payment_' . $decision, 'Payment evidence for application #' . $applicationId . ' ' . $decision . '.');
                adminNotify($conn, 'Payment ' . $decision, 'Payment evidence for application #' . $applicationId . ' was ' . $decision . '.', 'payment', 'application', $applicationId);
                adminRedirectWithFlash('applications', 'Payment evidence ' . $decision . '.');
            } elseif ($action === 'review_service_request') {
                $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
                $requestStatus = (string)($_POST['request_status'] ?? '');
                $response = trim((string)($_POST['staff_response'] ?? ''));
                if (!$requestId || !in_array($requestStatus, ['in_review', 'completed', 'rejected'], true)
                    || strlen($response) > 5000) {
                    throw new RuntimeException('Choose a valid request status and keep the response under 5,000 characters.');
                }
                $conn->begin_transaction();
                $requestStmt = $conn->prepare(
                    'SELECT request_status FROM student_service_requests WHERE id = ? FOR UPDATE'
                );
                $requestStmt->bind_param('i', $requestId);
                $requestStmt->execute();
                $request = $requestStmt->get_result()->fetch_assoc();
                $requestStmt->close();
                if (!$request || !in_array($request['request_status'], ['submitted', 'in_review'], true)) {
                    throw new RuntimeException('This request is not open for review.');
                }
                $updateStmt = $conn->prepare(
                    'UPDATE student_service_requests SET request_status = ?, staff_response = ? WHERE id = ?'
                );
                $updateStmt->bind_param('ssi', $requestStatus, $response, $requestId);
                $updateStmt->execute();
                $updateStmt->close();
                adminAudit($conn, 'service_request_' . $requestStatus, 'Student service request #' . $requestId . ' updated.');
                $conn->commit();
                adminRedirectWithFlash('service_requests', 'Student service request updated.');
            } elseif ($action === 'batch_print') {
                $ids = array_values(array_unique(array_filter(array_map(
                    static fn($value): int => (int)$value,
                    is_array($_POST['application_ids'] ?? null) ? $_POST['application_ids'] : []
                ), static fn(int $id): bool => $id > 0)));
                if (!$ids || count($ids) > 2) {
                    throw new RuntimeException('Select one or two cards per print batch.');
                }
                $conn->begin_transaction();
                $printedCount = 0;
                foreach ($ids as $id) {
                    $stmt = $conn->prepare(
                        "UPDATE id_cards SET card_status = 'printed', printed_at = NOW()
                         WHERE application_id = ? AND card_status = 'ready_to_print'
                           AND card_number IS NOT NULL AND TRIM(card_number) <> ''"
                    );
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $changed = $stmt->affected_rows;
                    $stmt->close();
                    if ($changed !== 1) {
                        throw new RuntimeException('One selected card is no longer waiting to print or has no card number. Add its card number in the card preview first. No cards in this batch were changed.');
                    }
                    $stmt = $conn->prepare("UPDATE id_applications SET application_status = 'printed' WHERE id = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    adminRecordApplicationEvent($conn, $id, 'printed', 'Card marked printed in admin batch printing.');
                    $printedCount++;
                }
                adminAudit($conn, 'cards_printed', 'Marked ' . $printedCount . ' ID card(s) as printed.');
                $conn->commit();
                adminRedirectWithFlash('print_queue', $printedCount . ' card(s) marked printed.');
            } elseif ($action === 'issue_card') {
                $cardId = filter_var($_POST['card_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$cardId) {
                    throw new RuntimeException('Select a card to issue.');
                }
                $conn->begin_transaction();
                $stmt = $conn->prepare('SELECT application_id, card_status, card_number FROM id_cards WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $cardId);
                $stmt->execute();
                $cardToIssue = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$cardToIssue || $cardToIssue['card_status'] !== 'printed') {
                    throw new RuntimeException('Only a card marked printed can be issued.');
                }
                if (trim((string)($cardToIssue['card_number'] ?? '')) === '') {
                    throw new RuntimeException('A card without a card number cannot be issued. Add the number in the card preview first.');
                }
                $applicationId = (int)$cardToIssue['application_id'];
                $stmt = $conn->prepare("UPDATE id_cards SET card_status = 'issued', issued_at = NOW() WHERE id = ? AND card_status = 'printed' AND card_number IS NOT NULL AND TRIM(card_number) <> ''");
                $stmt->bind_param('i', $cardId);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('The printed card changed before it could be issued. Refresh the queue and try again.');
                }
                $stmt->close();
                $stmt = $conn->prepare("UPDATE id_applications SET application_status = 'issued' WHERE id = ?");
                $stmt->bind_param('i', $applicationId);
                $stmt->execute();
                $stmt->close();
                adminRecordApplicationEvent($conn, $applicationId, 'issued', 'Printed student ID card issued.');
                adminAudit($conn, 'card_issued', 'Issued card for application #' . $applicationId . '.');
                adminNotify($conn, 'Student ID issued', 'The card for application #' . $applicationId . ' was issued.', 'card', 'application', $applicationId);
                $conn->commit();
                adminRedirectWithFlash('print_queue', 'Card marked as issued.', 'success', ['status' => 'issued']);
            } elseif ($action === 'save_print_alignment') {
                $alignmentSettings = [
                    'offset_mm' => [-30.0, 30.0],
                    'offset_y_mm' => [-10.0, 10.0],
                    'card1_y_mm' => [-10.0, 10.0],
                    'card2_y_mm' => [-10.0, 10.0],
                    'card1_x_mm' => [-10.0, 10.0],
                    'card2_x_mm' => [-10.0, 10.0],
                    'photo_large_x_mm' => [-10.0, 10.0],
                    'photo_large_y_mm' => [-10.0, 10.0],
                    'photo_large_scale_pct' => [50.0, 200.0],
                    'photo_small_x_mm' => [-10.0, 10.0],
                    'photo_small_y_mm' => [-10.0, 10.0],
                    'photo_small_scale_pct' => [50.0, 200.0],
                ];
                $savedValues = [];
                foreach ($alignmentSettings as $key => [$minimum, $maximum]) {
                    $value = $_POST[$key] ?? null;
                    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < $minimum || (float)$value > $maximum) {
                        http_response_code(422);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'message' => 'Print alignment settings are invalid.']);
                        exit;
                    }
                    $precision = in_array($key, ['offset_mm', 'photo_large_scale_pct', 'photo_small_scale_pct'], true) ? 0 : 1;
                    $savedValues[$key] = number_format(round((float)$value, $precision), $precision, '.', '');
                }
                $conn->begin_transaction();
                try {
                    foreach ($savedValues as $key => $value) {
                        if (str_starts_with($key, 'photo_')) {
                            $settingKey = 'card_print_' . $key;
                        } else {
                            $settingKey = 'card_print_alignment_' . $key;
                        }
                        adminSetSetting($conn, $settingKey, $value, (int)$_SESSION['user_id']);
                    }
                    adminAudit($conn, 'card_print_layout_saved', 'Saved card alignment and photo layout settings for future previews and print jobs.');
                    $conn->commit();
                } catch (Throwable $error) {
                    $conn->rollback();
                    throw $error;
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Card layout settings saved for future print jobs.']);
                exit;
            } elseif ($action === 'application_settings') {
                $open = (string)($_POST['student_applications_open'] ?? '0') === '1' ? '1' : '0';
                adminSetSetting($conn, 'student_applications_open', $open, (int)$_SESSION['user_id']);
                adminAudit($conn, 'application_settings_updated', 'Student ID applications ' . ($open === '1' ? 'opened' : 'paused') . '.');
                adminRedirectWithFlash('settings', 'Application settings updated.');
            } elseif ($action === 'card_back_settings') {
                $fields = [
                    'header_title' => ['Header title', 100],
                    'motto' => ['Motto', 120],
                    'address' => ['Address', 120],
                    'telephone' => ['Telephone', 80],
                    'email' => ['Email', 120],
                    'website' => ['Website', 120],
                    'return_text' => ['Return message', 140],
                ];
                $values = [];
                foreach ($fields as $key => [$label, $maximumLength]) {
                    $value = trim((string)($_POST[$key] ?? ''));
                    if (strlen($value) > $maximumLength) {
                        throw new RuntimeException($label . ' must be ' . $maximumLength . ' characters or fewer.');
                    }
                    $values[$key] = $value;
                }
                foreach (['accent_color', 'header_text_color', 'body_text_color'] as $colorKey) {
                    $color = (string)($_POST[$colorKey] ?? '');
                    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                        throw new RuntimeException('Choose valid six-digit hex colors for the card back.');
                    }
                    $values[$colorKey] = strtolower($color);
                }
                $conn->begin_transaction();
                try {
                    foreach ($values as $key => $value) {
                        adminSetSetting($conn, 'card_back_' . $key, $value, (int)$_SESSION['user_id']);
                    }
                    $conn->commit();
                } catch (Throwable $error) {
                    $conn->rollback();
                    throw $error;
                }
                adminAudit($conn, 'card_back_design_updated', 'Updated the printed student ID card back design.');
                adminRedirectWithFlash('settings', 'Card back design saved. New previews and prints will use these settings.');
            } elseif ($action === 'card_code_settings') {
                $templates = [
                    'qr_output_template' => (string)($_POST['qr_output_template'] ?? ''),
                    'barcode_output_template' => (string)($_POST['barcode_output_template'] ?? ''),
                ];
                studentCardCodeValidateTemplate($templates['qr_output_template'], 'qr');
                studentCardCodeValidateTemplate($templates['barcode_output_template'], 'barcode');
                $colors = [];
                foreach (['qr_foreground', 'qr_background', 'barcode_foreground', 'barcode_background'] as $key) {
                    $color = (string)($_POST[$key] ?? '');
                    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                        throw new RuntimeException('Choose valid six-digit hex colors for both codes.');
                    }
                    $colors[$key] = strtolower($color);
                }
                if (studentCardColorContrast($colors['qr_foreground'], $colors['qr_background']) < 7
                    || studentCardColorContrast($colors['barcode_foreground'], $colors['barcode_background']) < 7) {
                    throw new RuntimeException('Choose code colors with at least 7:1 contrast so scanners can distinguish the marks reliably.');
                }
                $sizeFields = [
                    'qr_size_mm' => ['QR code size', 8.0, 14.0],
                    'barcode_width_percent' => ['Barcode width', 50.0, 60.0],
                    'barcode_height_mm' => ['Barcode height', 8.0, 12.0],
                ];
                $values = $colors + $templates;
                foreach ($sizeFields as $key => [$label, $minimum, $maximum]) {
                    $rawValue = $_POST[$key] ?? null;
                    if (!is_numeric($rawValue) || (float)$rawValue < $minimum || (float)$rawValue > $maximum) {
                        throw new RuntimeException($label . ' must be between ' . $minimum . ' and ' . $maximum . '.');
                    }
                    $values[$key] = number_format(round((float)$rawValue, 1), 1, '.', '');
                }
                $conn->begin_transaction();
                try {
                    foreach ($values as $key => $value) {
                        adminSetSetting($conn, 'card_code_' . $key, $value, (int)$_SESSION['user_id']);
                    }
                    $conn->commit();
                } catch (Throwable $error) {
                    $conn->rollback();
                    throw $error;
                }
                adminAudit($conn, 'card_codes_customized', 'Updated QR and barcode appearance and scan output templates.');
                adminRedirectWithFlash('settings', 'QR and barcode settings saved. Updated appearance and scan outputs apply to student and admin card previews.');
            } elseif ($action === 'card_front_row_settings') {
                $rowDefaults = studentCardFrontRowDefaults();
                $fontFamilies = studentCardFrontRowFontFamilies();
                $submittedRows = $_POST['rows'] ?? null;
                if (!is_array($submittedRows)) {
                    throw new RuntimeException('Submit the text style settings for each card row.');
                }
                $values = [];
                foreach ($rowDefaults as $rowName => $defaults) {
                    $submitted = $submittedRows[$rowName] ?? null;
                    if (!is_array($submitted)) {
                        throw new RuntimeException('Missing text style settings for ' . str_replace('_', ' ', $rowName) . '.');
                    }
                    foreach (['label_color', 'value_color'] as $colorKey) {
                        $color = (string)($submitted[$colorKey] ?? '');
                        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                            throw new RuntimeException('Choose valid colors for every card text row.');
                        }
                        $values[$rowName . '_' . $colorKey] = strtolower($color);
                    }
                    $fontSize = $submitted['font_size_px'] ?? null;
                    if (!is_numeric($fontSize) || (float)$fontSize < 6 || (float)$fontSize > 12) {
                        throw new RuntimeException('Card text sizes must be between 6 and 12 pixels.');
                    }
                    $values[$rowName . '_font_size_px'] = number_format(round((float)$fontSize, 1), 1, '.', '');
                    $fontFamily = (string)($submitted['font_family'] ?? '');
                    if (!array_key_exists($fontFamily, $fontFamilies)) {
                        throw new RuntimeException('Choose a supported font for every card text row.');
                    }
                    $values[$rowName . '_font_family'] = $fontFamily;
                }
                $conn->begin_transaction();
                try {
                    foreach ($values as $key => $value) {
                        adminSetSetting($conn, 'card_front_row_' . $key, $value, (int)$_SESSION['user_id']);
                    }
                    $conn->commit();
                } catch (Throwable $error) {
                    $conn->rollback();
                    throw $error;
                }
                adminAudit($conn, 'card_front_rows_styled', 'Updated colors and typography for individual student card detail rows.');
                adminRedirectWithFlash('settings', 'Card detail row styles saved and applied to admin and student card previews.');
            } elseif ($action === 'card_front_branding') {
                $fields = [
                    'university_name' => ['University name', 80],
                    'card_title' => ['Card title', 80],
                ];
                $values = [];
                foreach ($fields as $key => [$label, $maximumLength]) {
                    $value = trim((string)($_POST[$key] ?? ''));
                    if ($value === '' || strlen($value) > $maximumLength) {
                        throw new RuntimeException($label . ' must contain 1 to ' . $maximumLength . ' characters.');
                    }
                    $values[$key] = $value;
                }
                foreach (['university_color', 'card_title_color'] as $colorKey) {
                    $color = (string)($_POST[$colorKey] ?? '');
                    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                        throw new RuntimeException('Choose valid six-digit colors for the card header text.');
                    }
                    $values[$colorKey] = strtolower($color);
                }
                $barColor = (string)($_POST['bar_color'] ?? '');
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $barColor) !== 1) {
                    throw new RuntimeException('Choose a valid six-digit color for the front-card bars.');
                }
                $values['bar_color'] = strtolower($barColor);
                foreach (['university_font_size_px' => [12.0, 24.0], 'card_title_font_size_px' => [8.0, 16.0], 'logo_height_px' => [24.0, 48.0], 'watermark_width_mm' => [10.0, 80.0], 'watermark_height_mm' => [10.0, 50.0], 'watermark_opacity' => [0.05, 0.5], 'watermark_x_mm' => [-42.5, 42.5], 'watermark_y_mm' => [-27.0, 27.0], 'holder_signature_x_mm' => [-30.0, 30.0], 'holder_signature_width_mm' => [5.0, 40.0], 'registrar_signature_x_mm' => [-30.0, 30.0], 'registrar_signature_width_mm' => [5.0, 40.0], 'signature_y_mm' => [-25.0, 25.0], 'right_pattern_width_pct' => [1.0, 100.0], 'right_pattern_height_pct' => [1.0, 100.0], 'right_pattern_scale_pct' => [25.0, 300.0], 'bar_thickness_pct' => [50.0, 200.0], 'bar_top_x_mm' => [-10.0, 10.0], 'bar_top_y_mm' => [-10.0, 10.0], 'bar_top_rotation_deg' => [-15.0, 15.0], 'bar_bottom_x_mm' => [-10.0, 10.0], 'bar_bottom_y_mm' => [-10.0, 10.0], 'bar_bottom_rotation_deg' => [-15.0, 15.0]] as $key => [$minimum, $maximum]) {
                    $value = $_POST[$key] ?? null;
                    if (!is_numeric($value) || (float)$value < $minimum || (float)$value > $maximum) {
                        throw new RuntimeException(str_replace('_', ' ', ucfirst($key)) . ' must be between ' . $minimum . ' and ' . $maximum . '.');
                    }
                    $precision = $key === 'watermark_opacity' || $key === 'right_pattern_height_pct' ? 2 : (str_starts_with($key, 'bar_top_') || str_starts_with($key, 'bar_bottom_') || str_starts_with($key, 'right_pattern_') || str_starts_with($key, 'watermark_') ? 1 : 0);
                    $values[$key] = number_format(round((float)$value, $precision), $precision, '.', '');
                }
                foreach (['watermark_horizontal_position' => ['left', 'center', 'right'], 'watermark_vertical_position' => ['top', 'center', 'bottom']] as $key => $allowedPositions) {
                    $position = (string)($_POST[$key] ?? '');
                    if (!in_array($position, $allowedPositions, true)) {
                        throw new RuntimeException('Choose a valid position for the centered card watermark.');
                    }
                    $values[$key] = $position;
                }
                $values['watermark_enabled'] = isset($_POST['watermark_enabled']) ? '1' : '0';
                foreach (['bar_top_visible', 'bar_middle_visible', 'bar_bottom_visible'] as $key) {
                    $values[$key] = isset($_POST[$key]) ? '1' : '0';
                }
                $fontFamilies = studentCardFrontBrandingFonts();
                foreach (['university_font_family', 'card_title_font_family'] as $key) {
                    $font = (string)($_POST[$key] ?? '');
                    if (!array_key_exists($font, $fontFamilies)) {
                        throw new RuntimeException('Choose a supported font for the card header.');
                    }
                    $values[$key] = $font;
                }
                $upload = $_FILES['card_logo'] ?? null;
                if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                        || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))
                        || (int)($upload['size'] ?? 0) < 1
                        || (int)$upload['size'] > 2 * 1024 * 1024) {
                        throw new RuntimeException('Upload a valid logo image no larger than 2 MB.');
                    }
                    $imageInfo = getimagesize((string)$upload['tmp_name']);
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
                    $imageTypes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                    if (!is_array($imageInfo) || !isset($imageTypes[$mime])
                        || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
                        || $imageInfo[0] > 2000 || $imageInfo[1] > 2000) {
                        throw new RuntimeException('Logo must be a PNG, JPEG, or WebP image no larger than 2000 × 2000 pixels.');
                    }
                    $logoDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'card-branding';
                    if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0755, true) && !is_dir($logoDirectory)) {
                        throw new RuntimeException('The card logo upload directory could not be created.');
                    }
                    $logoFile = bin2hex(random_bytes(16)) . '.' . $imageTypes[$mime];
                    if (!move_uploaded_file((string)$upload['tmp_name'], $logoDirectory . DIRECTORY_SEPARATOR . $logoFile)) {
                        throw new RuntimeException('The card logo could not be saved.');
                    }
                    $values['logo_file'] = $logoFile;
                }
                $conn->begin_transaction();
                try {
                    foreach ($values as $key => $value) {
                        adminSetSetting($conn, 'card_front_branding_' . $key, $value, (int)$_SESSION['user_id']);
                    }
                    $conn->commit();
                } catch (Throwable $error) {
                    $conn->rollback();
                    throw $error;
                }
                adminAudit($conn, 'card_front_branding_updated', 'Updated front card heading, logo, watermark, or blue bar settings.');
                adminRedirectWithFlash('settings', 'Card front branding, watermark, and blue bar settings saved for admin and student previews.');
            } elseif ($action === 'create_announcement') {
                $title = trim((string)($_POST['title'] ?? ''));
                $message = trim((string)($_POST['message'] ?? ''));
                if ($title === '' || strlen($title) > 180 || $message === '' || strlen($message) > 10000) {
                    throw new RuntimeException('Enter a title and announcement message within the stated limits.');
                }
                $adminId = (int)$_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO admin_announcements (title, message, created_by) VALUES (?, ?, ?)");
                $stmt->bind_param('ssi', $title, $message, $adminId);
                $stmt->execute();
                $id = (int)$conn->insert_id;
                $stmt->close();
                adminAudit($conn, 'announcement_created', 'Created announcement #' . $id . ': ' . $title);
                adminNotify($conn, 'Announcement published', $title, 'announcement', 'announcement', $id);
                adminRedirectWithFlash('announcements', 'Announcement published.');
            } elseif ($action === 'toggle_announcement') {
                $id = filter_var($_POST['announcement_id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string)($_POST['new_status'] ?? '');
                if (!$id || !in_array($status, ['active', 'inactive'], true)) {
                    throw new RuntimeException('Invalid announcement update.');
                }
                $stmt = $conn->prepare('UPDATE admin_announcements SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                $stmt->close();
                adminAudit($conn, 'announcement_status_updated', 'Announcement #' . $id . ' set to ' . $status . '.');
                adminRedirectWithFlash('announcements', 'Announcement status updated.');
            } elseif ($action === 'mark_notification_read') {
                $id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
                $role = (string)$_SESSION['user_type'];
                if (!$id) {
                    throw new RuntimeException('Select a notification.');
                }
                $stmt = $conn->prepare('UPDATE admin_notifications SET read_at = NOW() WHERE id = ? AND recipient_role = ? AND read_at IS NULL');
                $stmt->bind_param('is', $id, $role);
                $stmt->execute();
                $stmt->close();
                adminGo('notifications');
            } elseif ($action === 'restore_backup') {
                adminRequireAccess(true);
                $file = $_FILES['backup_file'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                    || !is_uploaded_file((string)($file['tmp_name'] ?? '')) || (int)$file['size'] > 20 * 1024 * 1024) {
                    throw new RuntimeException('Choose a valid backup file no larger than 20 MB.');
                }
                $backup = json_decode((string)file_get_contents($file['tmp_name']), true, 512, JSON_THROW_ON_ERROR);
                if (($backup['format'] ?? '') !== 'studentid-admin-backup-v1' || !is_array($backup['tables'] ?? null)) {
                    throw new RuntimeException('The selected file is not a supported Student ID backup.');
                }
                $allowedTables = ['students', 'student_registry', 'staff_accounts', 'student_faculties', 'student_courses', 'system_settings',
                    'admin_announcements', 'id_applications', 'payments', 'id_cards', 'application_events',
                    'admin_notifications', 'system_audit_log', 'activity_logs', 'student_service_requests'];
                $conn->begin_transaction();
                $restored = 0;
                foreach ($backup['tables'] as $table => $records) {
                    if (!in_array($table, $allowedTables, true) || !is_array($records)) {
                        throw new RuntimeException('Backup contains an unsupported table or data format.');
                    }
                    $columnResult = $conn->query('SHOW COLUMNS FROM `' . $table . '`');
                    $validColumns = [];
                    while ($column = $columnResult->fetch_assoc()) {
                        $validColumns[$column['Field']] = true;
                    }
                    foreach ($records as $record) {
                        if (!is_array($record) || !$record) {
                            throw new RuntimeException('Backup contains an invalid row.');
                        }
                        $columns = array_keys($record);
                        foreach ($columns as $column) {
                            if (!isset($validColumns[$column])) {
                                throw new RuntimeException('Backup contains an unsupported column in ' . $table . '.');
                            }
                        }
                        $quoted = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
                        $marks = implode(',', array_fill(0, count($columns), '?'));
                        $insert = $conn->prepare('INSERT INTO `' . $table . '` (' . implode(',', $quoted) . ') VALUES (' . $marks . ')');
                        $values = array_values($record);
                        $types = str_repeat('s', count($values));
                        $bindParameters = [$types];
                        foreach ($values as $index => &$value) {
                            $bindParameters[] = &$value;
                        }
                        unset($value);
                        call_user_func_array([$insert, 'bind_param'], $bindParameters);
                        $insert->execute();
                        $insert->close();
                        $restored++;
                    }
                }
                adminAudit($conn, 'backup_restored', 'Restored ' . $restored . ' row(s) using merge-only backup import; existing rows were not overwritten.');
                $conn->commit();
                adminRedirectWithFlash('backup', $restored . ' backup row(s) restored. Existing rows were preserved; conflicting rows roll back the restore.');
            } else {
                throw new RuntimeException('That admin action is not available.');
            }
        } catch (mysqli_sql_exception $exception) {
            if ($conn->errno || $conn->thread_id) {
                try { $conn->rollback(); } catch (Throwable) {}
            }
            error_log('Admin console update failed: ' . $exception->getMessage());
            if (($action ?? '') === 'save_print_alignment') {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'The alignment settings could not be saved to the database.']);
                exit;
            }
            $errors[] = (int)$exception->getCode() === 1062
                ? 'A registration number, student ID, username or email already exists.'
                : 'The change could not be saved. Check the fields and database, then try again.';
            $formValues = $_POST;
        } catch (JsonException|RuntimeException $exception) {
            try { $conn->rollback(); } catch (Throwable) {}
            if (($action ?? '') === 'save_print_alignment') {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                exit;
            }
            $errors[] = $exception->getMessage();
            $formValues = $_POST;
        } catch (Throwable $exception) {
            try { $conn->rollback(); } catch (Throwable) {}
            error_log('Admin console action failed: ' . $exception->getMessage());
            if (($action ?? '') === 'save_print_alignment') {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'The alignment settings could not be saved. Please try again.']);
                exit;
            }
            $errors[] = 'The action could not be completed. Please reload and try again.';
            $formValues = $_POST;
        }
    }
}

$pageTitles = [
    'dashboard' => 'Admin Dashboard', 'analytics' => 'Analytics', 'reports' => 'Reports',
    'students' => 'Student Management', 'registry' => 'Student Registry', 'student_view' => 'Student Details', 'student_edit' => 'Edit Student', 'student_add' => 'Add Student',
    'import' => 'Bulk Import Student Accounts', 'staff' => 'Staff Management',
    'applications' => 'Process Applications', 'print_queue' => 'ID Card Print Queue',
    'service_requests' => 'Student Service Requests',
    'print_batch' => 'Print Selected Cards', 'card' => 'Student ID Card Preview',
    'templates' => 'Card Design Preview', 'settings' => 'Application Settings',
    'announcements' => 'Announcements', 'notifications' => 'Notifications',
    'monitor' => 'System Monitor', 'audit' => 'Audit Logs', 'backup' => 'Backup & Restore',
];
$title = $pageTitles[$view] ?? 'Admin Dashboard';
adminShellStart($conn, $title, in_array($view, ['student_add', 'student_edit', 'student_view', 'student_export'], true) ? 'students' : $view);
?>
<?php if ($flash): ?><div class="admin-alert <?php echo adminEscape((string)($flash['type'] ?? 'success')); ?>"><?php echo adminEscape((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="admin-alert error"><?php echo adminEscape($error); ?></div><?php endforeach; ?>

<?php if ($view === 'dashboard'): ?>
    <?php
    $totalStudents = (int)$conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'];
    $pendingApplications = (int)$conn->query(
        "SELECT COUNT(*) AS total FROM id_applications WHERE application_status IN ('submitted','under_review')"
    )->fetch_assoc()['total'];
    $printedCards = (int)$conn->query("SELECT COUNT(*) AS total FROM id_cards WHERE card_status IN ('printed','issued')")->fetch_assoc()['total'];
    $activeCards = (int)$conn->query("SELECT COUNT(*) AS total FROM id_cards WHERE card_status = 'issued'")->fetch_assoc()['total'];
    $cardsToPrint = (int)$conn->query("SELECT COUNT(*) AS total FROM id_cards WHERE card_status = 'ready_to_print'")->fetch_assoc()['total'];
    $rejectedApplications = (int)$conn->query("SELECT COUNT(*) AS total FROM id_applications WHERE application_status = 'rejected'")->fetch_assoc()['total'];
    $replacementCards = (int)$conn->query(
        "SELECT COUNT(*) AS total
         FROM id_cards c
         INNER JOIN id_applications a ON a.id = c.application_id
         WHERE a.application_type IN ('lost','damaged')"
    )->fetch_assoc()['total'];
    $recentEvents = $conn->query(
        'SELECT e.action, e.details, e.created_at, e.actor_role, a.id AS application_id, a.application_type,
                s.full_name, s.student_id
         FROM application_events e
         LEFT JOIN id_applications a ON a.id = e.application_id
         LEFT JOIN students s ON s.id = a.student_id
         ORDER BY e.created_at DESC LIMIT 6'
    )->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="admin-page-heading"><div><h1>Welcome, <?php echo adminEscape(explode(' ', (string)($_SESSION['full_name'] ?? 'Administrator'))[0]); ?></h1><p>Live student, application and ID-card activity from the system database.</p></div><span class="admin-badge"><?php echo date('l, F j, Y'); ?></span></div>
    <section class="admin-stats">
        <a class="admin-stat" href="console.php?view=students"><span class="admin-stat-icon"><i class="fas fa-users"></i></span><span><strong><?php echo number_format($totalStudents); ?></strong><small>Total Students</small></span></a>
        <a class="admin-stat" href="console.php?view=applications"><span class="admin-stat-icon orange"><i class="fas fa-clipboard-list"></i></span><span><strong><?php echo number_format($pendingApplications); ?></strong><small>Applications in review</small></span></a>
        <a class="admin-stat" href="console.php?view=print_queue"><span class="admin-stat-icon purple"><i class="fas fa-id-card"></i></span><span><strong><?php echo number_format($printedCards); ?></strong><small>Cards printed / issued</small></span></a>
        <a class="admin-stat" href="console.php?view=print_queue&amp;status=issued"><span class="admin-stat-icon green"><i class="fas fa-user-check"></i></span><span><strong><?php echo number_format($activeCards); ?></strong><small>Issued ID Cards</small></span></a>
        <a class="admin-stat" href="console.php?view=print_queue&amp;status=ready_to_print"><span class="admin-stat-icon orange"><i class="fas fa-print"></i></span><span><strong><?php echo number_format($cardsToPrint); ?></strong><small>Cards waiting to print</small></span></a>
        <a class="admin-stat" href="console.php?view=applications&amp;status=rejected"><span class="admin-stat-icon red"><i class="fas fa-circle-xmark"></i></span><span><strong><?php echo number_format($rejectedApplications); ?></strong><small>Rejected applications</small></span></a>
        <a class="admin-stat" href="console.php?view=print_queue&amp;status=all&amp;type=replacement"><span class="admin-stat-icon teal"><i class="fas fa-arrow-rotate-left"></i></span><span><strong><?php echo number_format($replacementCards); ?></strong><small>Lost / damaged replacement cards</small></span></a>
    </section>
    <section class="admin-modules">
        <article class="admin-module"><h2><i class="fas fa-users"></i> User Management</h2><ul><li><i class="fas fa-circle-check"></i> View and update student accounts</li><li><i class="fas fa-circle-check"></i> Maintain the authoritative enrollment registry</li><li><i class="fas fa-circle-check"></i> Manage staff roles and access</li></ul><div class="admin-module-actions"><a class="admin-button" href="console.php?view=students">Manage Accounts</a><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><a class="admin-button-secondary" href="console.php?view=registry">Student Registry</a><a class="admin-button-secondary" href="console.php?view=student_add">Add Account</a><?php endif; ?></div></article>
        <article class="admin-module"><h2><i class="fas fa-id-card"></i> ID Card Management</h2><ul><li><i class="fas fa-circle-check"></i> Preview the selected database card design</li><li><i class="fas fa-circle-check"></i> Print one or two queued cards</li><li><i class="fas fa-circle-check"></i> Track printing and issuance statuses</li></ul><div class="admin-module-actions"><a class="admin-button" href="console.php?view=templates">Design Cards</a><a class="admin-button-secondary" href="console.php?view=print_queue">View Queue</a><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><a class="admin-button-secondary" href="console.php?view=catalog">Card Catalog &amp; Signature</a><?php endif; ?></div></article>
        <article class="admin-module"><h2><i class="fas fa-clipboard-check"></i> Application Management</h2><ul><li><i class="fas fa-circle-check"></i> Review submitted applications</li><li><i class="fas fa-circle-check"></i> Verify replacement payment evidence</li><li><i class="fas fa-circle-check"></i> Generate cards after approval</li></ul><div class="admin-module-actions"><a class="admin-button" href="console.php?view=applications">Process Applications</a><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><a class="admin-button-secondary" href="console.php?view=settings">Settings</a><?php endif; ?></div></article>
        <article class="admin-module"><h2><i class="fas fa-chart-column"></i> Reporting &amp; Analytics</h2><ul><li><i class="fas fa-circle-check"></i> Enrollment and application totals</li><li><i class="fas fa-circle-check"></i> Card print and issuance counts</li><li><i class="fas fa-circle-check"></i> Export report data as CSV</li></ul><div class="admin-module-actions"><a class="admin-button" href="console.php?view=reports">View Reports</a><a class="admin-button-secondary" href="console.php?view=analytics">Analytics</a></div></article>
    </section>
    <section class="admin-panel">
        <div class="admin-page-heading"><div><h2>Recent application activity</h2><p>Activity below comes from saved application events.</p></div><a class="admin-button-secondary" href="console.php?view=audit">View All Logs</a></div>
        <?php if (!$recentEvents): ?><div class="admin-empty">No application events have been recorded yet.</div>
        <?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Application</th><th>Action</th><th>Details</th><th>Actor</th><th>Time</th></tr></thead><tbody>
            <?php foreach ($recentEvents as $event): ?><tr><td><?php echo $event['application_id'] ? '#' . (int)$event['application_id'] . ' · ' . adminEscape((string)($event['full_name'] ?? '')) . ' (' . adminEscape((string)($event['student_id'] ?? '')) . ')' : 'Application unavailable'; ?></td><td><?php echo adminBadge((string)$event['action']); ?></td><td><?php echo adminEscape((string)($event['details'] ?? '')); ?></td><td><?php echo adminEscape((string)$event['actor_role']); ?></td><td><?php echo adminEscape(date('M j, Y H:i', strtotime((string)$event['created_at']))); ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

<?php elseif ($view === 'registry'): ?>
    <?php
    $registryEditId = filter_var($_GET['edit_id'] ?? null, FILTER_VALIDATE_INT);
    $registryForm = [
        'id' => '',
        'registration_number' => '',
        'card_number' => '',
        'full_name' => '',
        'course' => '',
        'programme' => '',
        'status' => 'active',
    ];
    if ($registryEditId) {
        $editStmt = $conn->prepare(
            'SELECT id, registration_number, card_number, full_name, course, programme, status
             FROM student_registry WHERE id = ?'
        );
        $editStmt->bind_param('i', $registryEditId);
        $editStmt->execute();
        $registryForm = $editStmt->get_result()->fetch_assoc() ?: [];
        $editStmt->close();
        if (!$registryForm) {
            http_response_code(404);
            $errors[] = 'Registry record not found.';
            $registryForm = [
                'id' => '',
                'registration_number' => '',
                'card_number' => '',
                'full_name' => '',
                'course' => '',
                'programme' => '',
                'status' => 'active',
            ];
        }
    }
    if ($formValues && ($formValues['action'] ?? '') === 'save_registry_student') {
        $registryForm = [
            'id' => (string)($formValues['registry_id'] ?? ''),
            'registration_number' => (string)($formValues['registration_number'] ?? ''),
            'card_number' => (string)($formValues['card_number'] ?? ''),
            'full_name' => (string)($formValues['full_name'] ?? ''),
            'course' => (string)($formValues['course'] ?? ''),
            'programme' => (string)($formValues['programme'] ?? ''),
            'status' => (string)($formValues['status'] ?? 'active'),
        ];
    }
    $registrySearch = trim((string)($_GET['q'] ?? ''));
    $registrySearchField = (string)($_GET['field'] ?? 'all');
    $registryColumns = [
        'registration_number' => 'registration_number',
        'card_number' => 'card_number',
        'name' => 'full_name',
        'course' => 'course',
        'programme' => 'programme',
    ];
    $registryWhere = '';
    $registryLike = '%' . $registrySearch . '%';
    if ($registrySearch !== '') {
        if (isset($registryColumns[$registrySearchField])) {
            $registryWhere = ' WHERE `' . $registryColumns[$registrySearchField] . '` LIKE ?';
        } else {
            $registrySearchField = 'all';
            $registryWhere = ' WHERE registration_number LIKE ? OR card_number LIKE ? OR full_name LIKE ? OR course LIKE ? OR programme LIKE ?';
        }
    } else {
        $registrySearchField = isset($registryColumns[$registrySearchField]) ? $registrySearchField : 'all';
    }
    if ($registrySearch === '') {
        $totalRegistryRows = (int)$conn->query('SELECT COUNT(*) AS total FROM student_registry')->fetch_assoc()['total'];
    } elseif (isset($registryColumns[$registrySearchField])) {
        $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM student_registry' . $registryWhere);
        $countStmt->bind_param('s', $registryLike);
        $countStmt->execute();
        $totalRegistryRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM student_registry' . $registryWhere);
        $countStmt->bind_param('sssss', $registryLike, $registryLike, $registryLike, $registryLike, $registryLike);
        $countStmt->execute();
        $totalRegistryRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
    }
    $registryPageSize = 50;
    $registryPageCount = max(1, (int)ceil($totalRegistryRows / $registryPageSize));
    $registryPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $registryPage = max(1, min($registryPageCount, $registryPage ?: 1));
    $registryOffset = ($registryPage - 1) * $registryPageSize;
    $registrySql = 'SELECT id, registration_number, card_number, full_name, course, programme, status, created_at
                    FROM student_registry' . $registryWhere . ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $registryStmt = $conn->prepare($registrySql);
    if ($registrySearch === '') {
        $registryStmt->bind_param('ii', $registryPageSize, $registryOffset);
    } elseif (isset($registryColumns[$registrySearchField])) {
        $registryStmt->bind_param('sii', $registryLike, $registryPageSize, $registryOffset);
    } else {
        $registryStmt->bind_param('sssssii', $registryLike, $registryLike, $registryLike, $registryLike, $registryLike, $registryPageSize, $registryOffset);
    }
    $registryStmt->execute();
    $registryStudents = $registryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $registryStmt->close();
    ?>
    <div class="admin-page-heading">
        <div><h1>Authoritative Student Registry</h1><p><?php echo number_format($totalRegistryRows); ?> matching record(s). Only active registry records can create student accounts.</p></div>
        <div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=registry_export">Export Registry CSV</a><a class="admin-button-secondary" href="console.php?view=students">Student Accounts</a></div>
    </div>
    <section class="admin-grid">
        <div class="admin-panel admin-span-4">
            <h2><?php echo $registryForm['id'] !== '' ? 'Edit Registry Student' : 'Add Student to Registry'; ?></h2>
            <form method="post" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                <input type="hidden" name="action" value="save_registry_student">
                <input type="hidden" name="registry_id" value="<?php echo adminEscape((string)$registryForm['id']); ?>">
                <div class="admin-field full"><label for="registry_registration_number">Registration number</label><input id="registry_registration_number" name="registration_number" maxlength="80" required value="<?php echo adminEscape((string)$registryForm['registration_number']); ?>"></div>
                <div class="admin-field full"><label for="registry_card_number">Card No. (optional)</label><input id="registry_card_number" name="card_number" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,49}" value="<?php echo adminEscape((string)($registryForm['card_number'] ?? '')); ?>"><small>Must be unique. This is suggested on the student ID card and can still be edited on its preview.</small></div>
                <div class="admin-field full"><label for="registry_full_name">Full name</label><input id="registry_full_name" name="full_name" maxlength="150" required value="<?php echo adminEscape((string)$registryForm['full_name']); ?>"></div>
                <div class="admin-field"><label for="registry_course">Course</label><input id="registry_course" name="course" maxlength="150" value="<?php echo adminEscape((string)($registryForm['course'] ?? '')); ?>"></div>
                <div class="admin-field"><label for="registry_programme">Programme</label><input id="registry_programme" name="programme" maxlength="150" value="<?php echo adminEscape((string)($registryForm['programme'] ?? '')); ?>"></div>
                <?php if ($registryForm['id'] !== ''): ?><div class="admin-field"><label for="registry_status">Status</label><select id="registry_status" name="status"><option value="active" <?php echo $registryForm['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $registryForm['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div><?php endif; ?>
                <div class="admin-field full"><button class="admin-button" type="submit"><?php echo $registryForm['id'] !== '' ? 'Save Registry Record' : 'Add to Registry'; ?></button><?php if ($registryForm['id'] !== ''): ?> <a class="admin-button-secondary" href="console.php?view=registry">Cancel edit</a><?php endif; ?></div>
            </form>
        </div>
        <div class="admin-panel admin-span-8">
            <h2>Import Enrollment Spreadsheet</h2>
            <p>Upload an Excel workbook (<code>.xls</code>, <code>.xlsx</code>, or <code>.xlsm</code>) or a CSV file. Column headings can include registration number, optional card no, full name, course, and programme. If the file has no headings, the first two columns are the registration number and name, followed optionally by course, programme, and card no. Card numbers must be unique. A repeated registration number keeps the first row and skips the copy, and numbers already in the registry are left unchanged.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                <input type="hidden" name="action" value="import_registry">
                <div class="admin-field"><label for="student_file">Excel or CSV file</label><input id="student_file" type="file" name="student_file" required></div>
                <button class="admin-button" type="submit" style="margin-top:12px">Import Students</button>
            </form>
        </div>
    </section>
    <section class="admin-panel">
        <form class="admin-search" method="get">
            <input type="hidden" name="view" value="registry">
            <select name="field" aria-label="Registry search field">
                <option value="all" <?php echo $registrySearchField === 'all' ? 'selected' : ''; ?>>All fields</option>
                <option value="registration_number" <?php echo $registrySearchField === 'registration_number' ? 'selected' : ''; ?>>Registration number</option>
                <option value="card_number" <?php echo $registrySearchField === 'card_number' ? 'selected' : ''; ?>>Card No.</option>
                <option value="name" <?php echo $registrySearchField === 'name' ? 'selected' : ''; ?>>Student name</option>
                <option value="course" <?php echo $registrySearchField === 'course' ? 'selected' : ''; ?>>Course</option>
                <option value="programme" <?php echo $registrySearchField === 'programme' ? 'selected' : ''; ?>>Programme</option>
            </select>
            <input name="q" value="<?php echo adminEscape($registrySearch); ?>" placeholder="Search the registry">
            <button class="admin-button" type="submit">Search</button>
        </form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Registration number</th><th>Card No.</th><th>Full name</th><th>Course</th><th>Programme</th><th>Status</th><th>Added</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($registryStudents as $registryStudent): ?><tr>
                <td><?php echo adminEscape($registryStudent['registration_number']); ?></td>
                <td><?php echo adminEscape((string)($registryStudent['card_number'] ?? '')); ?></td>
                <td><?php echo adminEscape($registryStudent['full_name']); ?></td>
                <td><?php echo adminEscape((string)$registryStudent['course']); ?></td>
                <td><?php echo adminEscape((string)$registryStudent['programme']); ?></td>
                <td><?php echo adminBadge((string)$registryStudent['status']); ?></td>
                <td><?php echo adminEscape((string)$registryStudent['created_at']); ?></td>
                <td><a class="admin-button-secondary" href="console.php?view=registry&amp;edit_id=<?php echo (int)$registryStudent['id']; ?>">Edit</a></td>
            </tr><?php endforeach; ?>
            <?php if (!$registryStudents): ?><tr><td colspan="8" class="admin-empty">No matching registry records.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($registryPageCount > 1): ?><div class="admin-actions" style="justify-content:space-between;margin-top:16px">
            <span>Page <?php echo $registryPage; ?> of <?php echo $registryPageCount; ?></span>
            <div class="admin-actions">
                <?php if ($registryPage > 1): ?><a class="admin-button-secondary" href="console.php?<?php echo adminEscape(http_build_query(['view' => 'registry', 'field' => $registrySearchField, 'q' => $registrySearch, 'page' => $registryPage - 1])); ?>">Previous</a><?php endif; ?>
                <?php if ($registryPage < $registryPageCount): ?><a class="admin-button-secondary" href="console.php?<?php echo adminEscape(http_build_query(['view' => 'registry', 'field' => $registrySearchField, 'q' => $registrySearch, 'page' => $registryPage + 1])); ?>">Next</a><?php endif; ?>
            </div>
        </div><?php endif; ?>
    </section>

<?php elseif ($view === 'students'): ?>
    <?php
    $search = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        'SELECT id, student_id, full_name, email, phone, program, status, registration_date
         FROM students WHERE (? = "" OR student_id LIKE ? OR full_name LIKE ? OR email LIKE ?)
         ORDER BY id DESC LIMIT 500'
    );
    $stmt->bind_param('ssss', $search, $like, $like, $like);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>
    <div class="admin-page-heading"><div><h1>Student Management</h1><p>Search, review, add and update student accounts. Account changes do not delete records.</p></div><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><div class="admin-actions"><a class="admin-button" href="console.php?view=student_add"><i class="fas fa-user-plus"></i> Add Student</a><a class="admin-button-secondary" href="console.php?view=import">Bulk Import</a><a class="admin-button-secondary" href="console.php?view=student_export">Export CSV</a></div><?php endif; ?></div>
    <section class="admin-panel">
        <form class="admin-search" method="get"><input type="hidden" name="view" value="students"><input name="q" value="<?php echo adminEscape($search); ?>" placeholder="Search ID, name or email"><button class="admin-button" type="submit">Search</button></form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Program</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($students as $student): ?><tr><td><?php echo adminEscape($student['student_id']); ?></td><td><?php echo adminEscape($student['full_name']); ?></td><td><?php echo adminEscape($student['email']); ?></td><td><?php echo adminEscape((string)$student['program']); ?></td><td><?php echo adminBadge((string)$student['status']); ?></td><td><?php echo adminEscape((string)$student['registration_date']); ?></td><td><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=student_view&amp;id=<?php echo (int)$student['id']; ?>">Details</a><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><a class="admin-button-secondary" href="console.php?view=student_edit&amp;id=<?php echo (int)$student['id']; ?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="toggle_student"><input type="hidden" name="student_db_id" value="<?php echo (int)$student['id']; ?>"><input type="hidden" name="new_status" value="<?php echo $student['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="<?php echo $student['status'] === 'active' ? 'admin-button-danger' : 'admin-button-secondary'; ?>" type="submit"><?php echo $student['status'] === 'active' ? 'Disable' : 'Activate'; ?></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
            <?php if (!$students): ?><tr><td colspan="7" class="admin-empty">No matching student records.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

<?php elseif ($view === 'student_view'): ?>
    <?php
    $studentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $studentStmt = $conn->prepare(
        'SELECT id, student_id, full_name, email, phone, program, gender, status, registration_date, last_login
         FROM students WHERE id = ? LIMIT 1'
    );
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();
    $studentDetails = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();
    $studentApplications = [];
    $studentRequests = [];
    if ($studentDetails) {
        $applicationStmt = $conn->prepare(
            'SELECT a.id, a.application_type, a.application_status, a.reason, a.created_at, a.updated_at,
                    a.surname, a.given_name, a.nationality, a.sex, a.card_reg_no, a.date_admitted, a.graduation_year,
                    f.name AS faculty_name, c.name AS course_name, p.payment_status, p.receipt_path,
                    idc.card_number, idc.card_status, idc.printed_at, idc.issued_at
             FROM id_applications a
             LEFT JOIN student_faculties f ON f.id = a.faculty_id
             LEFT JOIN student_courses c ON c.id = a.course_id
             LEFT JOIN payments p ON p.application_id = a.id
             LEFT JOIN id_cards idc ON idc.application_id = a.id
             WHERE a.student_id = ? ORDER BY a.created_at DESC, a.id DESC'
        );
        $studentStmtId = (int)$studentDetails['id'];
        $applicationStmt->bind_param('i', $studentStmtId);
        $applicationStmt->execute();
        $studentApplications = $applicationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $applicationStmt->close();
        $requestStmt = $conn->prepare(
            'SELECT id, service_key, subject, request_status, staff_response, created_at, updated_at
             FROM student_service_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $requestStmt->bind_param('i', $studentStmtId);
        $requestStmt->execute();
        $studentRequests = $requestStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $requestStmt->close();
    }
    ?>
    <?php if (!$studentDetails): ?>
        <div class="admin-alert error">Student record not found.</div>
    <?php else: ?>
        <div class="admin-page-heading admin-page-heading-context"><div><h1><?php echo adminEscape($studentDetails['full_name']); ?></h1><p>Student account and full application history.</p></div><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=students">Back to students</a><?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?><a class="admin-button" href="console.php?view=student_edit&amp;id=<?php echo (int)$studentDetails['id']; ?>">Edit student</a><?php endif; ?></div></div>
        <section class="admin-stats">
            <div class="admin-stat"><span class="admin-stat-icon"><i class="fas fa-id-card"></i></span><span><strong><?php echo adminEscape($studentDetails['student_id']); ?></strong><small>Student ID</small></span></div>
            <div class="admin-stat"><span class="admin-stat-icon green"><i class="fas fa-user-check"></i></span><span><strong><?php echo adminBadge((string)$studentDetails['status']); ?></strong><small>Account status</small></span></div>
            <div class="admin-stat"><span class="admin-stat-icon purple"><i class="fas fa-clipboard-list"></i></span><span><strong><?php echo count($studentApplications); ?></strong><small>ID applications</small></span></div>
            <div class="admin-stat"><span class="admin-stat-icon orange"><i class="fas fa-inbox"></i></span><span><strong><?php echo count($studentRequests); ?></strong><small>Service requests</small></span></div>
        </section>
        <section class="admin-grid">
            <article class="admin-panel admin-span-6"><h2>Student profile</h2><dl class="admin-record-details">
                <dt>Email</dt><dd><?php echo adminEscape((string)$studentDetails['email']); ?></dd>
                <dt>Phone</dt><dd><?php echo adminEscape((string)($studentDetails['phone'] ?: 'Not recorded')); ?></dd>
                <dt>Program</dt><dd><?php echo adminEscape((string)($studentDetails['program'] ?: 'Not recorded')); ?></dd>
                <dt>Gender</dt><dd><?php echo adminEscape((string)($studentDetails['gender'] ?: 'Not recorded')); ?></dd>
                <dt>Registered</dt><dd><?php echo adminEscape((string)$studentDetails['registration_date']); ?></dd>
                <dt>Last login</dt><dd><?php echo adminEscape((string)($studentDetails['last_login'] ?: 'Not recorded')); ?></dd>
            </dl></article>
            <article class="admin-panel admin-span-6"><h2>Student service requests</h2>
                <?php if (!$studentRequests): ?><div class="admin-empty">No service requests.</div>
                <?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Request</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
                    <?php foreach ($studentRequests as $request): ?><tr><td><?php echo adminEscape($request['subject']); ?><br><small><?php echo adminEscape(ucwords(str_replace('_', ' ', (string)$request['service_key']))); ?></small><?php if ($request['staff_response']): ?><br><strong>Response:</strong> <?php echo nl2br(adminEscape((string)$request['staff_response'])); ?><?php endif; ?></td><td><?php echo adminBadge((string)$request['request_status']); ?></td><td><?php echo adminEscape((string)$request['created_at']); ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </article>
            <article class="admin-panel admin-span-12"><h2>ID application history</h2>
                <?php if (!$studentApplications): ?><div class="admin-empty">No ID applications.</div>
                <?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Application</th><th>Card identity</th><th>Study details</th><th>Payment</th><th>Card</th><th>Dates / actions</th></tr></thead><tbody>
                    <?php foreach ($studentApplications as $application): ?><tr>
                        <td>#<?php echo (int)$application['id']; ?> · <?php echo adminEscape(ucfirst((string)$application['application_type'])); ?><br><?php echo adminBadge((string)$application['application_status']); ?><br><small><?php echo adminEscape((string)$application['created_at']); ?></small></td>
                        <td><?php echo adminEscape(trim((string)$application['surname'] . ' ' . (string)$application['given_name'])); ?><br><?php echo adminEscape((string)$application['nationality']); ?> · <?php echo adminEscape((string)$application['sex']); ?><br><?php echo adminEscape((string)$application['card_reg_no']); ?></td>
                        <td><?php echo adminEscape((string)($application['faculty_name'] ?? '')); ?><br><?php echo adminEscape((string)($application['course_name'] ?? '')); ?><br>Date of Issue: <?php echo adminEscape((string)($application['date_admitted'] ?? 'Not recorded')); ?><br>Graduation: <?php echo adminEscape((string)($application['graduation_year'] ?? 'Not recorded')); ?></td>
                        <td><?php echo $application['application_type'] === 'new' ? 'Not required' : adminBadge((string)($application['payment_status'] ?? 'missing')); ?><?php if (!empty($application['receipt_path']) && adminStoredFile((string)$application['receipt_path'])): ?><br><a href="console.php?view=private_document&amp;application_id=<?php echo (int)$application['id']; ?>&amp;kind=receipt" target="_blank" rel="noopener">View receipt</a><?php endif; ?></td>
                        <td><?php echo $application['card_number'] ? adminEscape((string)$application['card_number']) . '<br>' . adminBadge((string)$application['card_status']) : 'Not generated'; ?></td>
                        <td><div class="admin-actions"><?php if ($application['card_number']): ?><a class="admin-button-secondary" href="console.php?view=card&amp;application_id=<?php echo (int)$application['id']; ?>">Preview</a><?php endif; ?><a class="admin-button-secondary" href="console.php?view=applications">Review</a></div><?php if ($application['issued_at']): ?><small>Issued <?php echo adminEscape((string)$application['issued_at']); ?></small><?php endif; ?></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </article>
        </section>
    <?php endif; ?>

<?php elseif ($view === 'student_add' || $view === 'student_edit'): ?>
    <?php
    $student = ['id' => '', 'student_id' => '', 'full_name' => '', 'email' => '', 'phone' => '', 'program' => '', 'gender' => '', 'status' => 'active'];
    if ($view === 'student_edit') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = $conn->prepare('SELECT id, student_id, full_name, email, phone, program, gender, status FROM students WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        if (!$student) { http_response_code(404); echo '<div class="admin-alert error">Student record not found.</div>'; }
    }
    ?>
    <?php if ($student): ?>
    <div class="admin-page-heading"><div><h1><?php echo $view === 'student_add' ? 'Add Student' : 'Edit Student'; ?></h1><p>Set the record values used by student sign-in and ID card applications.</p></div><a class="admin-button-secondary" href="console.php?view=students">Back to students</a></div>
    <section class="admin-panel">
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="save_student"><input type="hidden" name="student_db_id" value="<?php echo adminEscape((string)$student['id']); ?>">
            <div class="admin-field"><label for="student_id">Student ID / registration number</label><input id="student_id" name="student_id" maxlength="80" required value="<?php echo adminEscape($formValues['student_id'] ?? (string)$student['student_id']); ?>"></div>
            <div class="admin-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="150" required value="<?php echo adminEscape($formValues['full_name'] ?? (string)$student['full_name']); ?>"></div>
            <div class="admin-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" required value="<?php echo adminEscape($formValues['email'] ?? (string)$student['email']); ?>"></div>
            <div class="admin-field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="50" value="<?php echo adminEscape($formValues['phone'] ?? (string)($student['phone'] ?? '')); ?>"></div>
            <div class="admin-field"><label for="program">Program</label><input id="program" name="program" maxlength="150" value="<?php echo adminEscape($formValues['program'] ?? (string)($student['program'] ?? '')); ?>"></div>
            <div class="admin-field"><label for="gender">Gender</label><select id="gender" name="gender"><?php foreach (['', 'Female', 'Male', 'Other'] as $gender): ?><option value="<?php echo adminEscape($gender); ?>" <?php echo ($formValues['gender'] ?? (string)($student['gender'] ?? '')) === $gender ? 'selected' : ''; ?>><?php echo $gender === '' ? 'Not specified' : adminEscape($gender); ?></option><?php endforeach; ?></select></div>
            <div class="admin-field"><label for="status">Account status</label><select id="status" name="status"><?php foreach (['active', 'inactive'] as $status): ?><option value="<?php echo $status; ?>" <?php echo ($formValues['status'] ?? (string)$student['status']) === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select></div>
            <?php if ($view === 'student_add'): ?><div class="admin-field"><label for="password">Initial password (at least 8 characters)</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div><?php endif; ?>
            <div class="admin-field full"><button class="admin-button" type="submit">Save Student Record</button></div>
        </form>
    </section>
    <?php endif; ?>

<?php elseif ($view === 'import'): ?>
    <div class="admin-page-heading"><div><h1>Bulk Import Student Accounts</h1><p>This legacy CSV importer creates sign-in accounts. Use Student Registry to load the authoritative enrollment list used to verify new signups.</p></div><a class="admin-button-secondary" href="console.php?view=student_export">Download current accounts</a></div>
    <section class="admin-panel"><h2>CSV format</h2><p>Required header columns: <code>student_id,full_name,email,password</code>. Optional columns: <code>phone,program,gender</code>. Passwords are hashed before storage. Maximum 1,000 records and 5 MB per file.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="import_students"><div class="admin-field"><label for="student_csv">CSV file</label><input id="student_csv" type="file" name="student_csv" accept=".csv,text/csv" required></div><button class="admin-button" type="submit" style="margin-top:12px">Validate and Import</button></form></section>

<?php elseif ($view === 'staff'): ?>
    <?php $staffRows = $conn->query('SELECT id, username, full_name, email, role, status, created_at FROM staff_accounts ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC); ?>
    <div class="admin-page-heading"><div><h1>Staff Management</h1><p>Create staff/admin accounts and disable access without deleting account records.</p></div></div>
    <section class="admin-grid">
        <div class="admin-panel admin-span-4"><h2>Add Staff Account</h2><form method="post" class="admin-form-grid"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="add_staff"><div class="admin-field"><label>Username</label><input name="username" maxlength="80" required></div><div class="admin-field"><label>Full name</label><input name="full_name" maxlength="150" required></div><div class="admin-field full"><label>Email</label><input type="email" name="email" maxlength="190" required></div><div class="admin-field"><label>Role</label><select name="role"><option value="staff">Staff</option><option value="admin">Administrator</option></select></div><div class="admin-field"><label>Initial password</label><input type="password" name="password" minlength="8" required></div><div class="admin-field full"><button class="admin-button" type="submit">Create Account</button></div></form></div>
        <div class="admin-panel admin-span-8"><h2>Staff Accounts</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($staffRows as $staff): ?><tr><td><?php echo adminEscape($staff['full_name']); ?></td><td><?php echo adminEscape($staff['username']); ?></td><td><?php echo adminEscape($staff['email']); ?></td><td><?php echo adminEscape($staff['role']); ?></td><td><?php echo adminBadge((string)$staff['status']); ?></td><td><?php if ((int)$staff['id'] !== (int)$_SESSION['user_id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="toggle_staff"><input type="hidden" name="staff_id" value="<?php echo (int)$staff['id']; ?>"><input type="hidden" name="new_status" value="<?php echo $staff['status'] === 'active' ? 'disabled' : 'active'; ?>"><button class="<?php echo $staff['status'] === 'active' ? 'admin-button-danger' : 'admin-button-secondary'; ?>" type="submit"><?php echo $staff['status'] === 'active' ? 'Disable' : 'Enable'; ?></button></form><?php else: ?><span>Current account</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    </section>

<?php elseif ($view === 'applications'): ?>
    <?php
    $applicationStatusFilter = (string)($_GET['status'] ?? 'all');
    if (!in_array($applicationStatusFilter, ['all', 'rejected'], true)) {
        $applicationStatusFilter = 'all';
    }
    $applicationSql = 'SELECT a.id, a.student_id, a.application_type, a.reason, a.application_status, a.created_at,
                a.surname, a.given_name, a.nationality, a.sex, a.card_reg_no, a.date_admitted, a.graduation_year,
                a.photo_path, a.signature_path, s.full_name, s.email, s.student_id AS registration_number,
                f.name AS faculty_name, co.name AS course_name, p.payment_status, p.reference, p.amount, p.receipt_path,
                c.card_number, c.card_status
         FROM id_applications a INNER JOIN students s ON s.id = a.student_id
         LEFT JOIN student_faculties f ON f.id = a.faculty_id LEFT JOIN student_courses co ON co.id = a.course_id
         LEFT JOIN payments p ON p.application_id = a.id LEFT JOIN id_cards c ON c.application_id = a.id';
    if ($applicationStatusFilter === 'rejected') {
        $applicationSql .= " WHERE a.application_status = 'rejected'";
    }
    $applicationSql .= ' ORDER BY FIELD(a.application_status, "submitted", "under_review", "approved", "ready_to_print", "printed", "issued", "rejected"), a.created_at';
    $applicationRows = $conn->query($applicationSql)->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="admin-page-heading"><div><h1>Process Applications</h1><p>Review student records, advance applications through approval, and verify any renewal/replacement payment evidence.</p></div><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=applications">All applications</a><a class="admin-button-secondary" href="console.php?view=applications&amp;status=rejected">Rejected applications</a><a class="admin-button-secondary" href="console.php?view=print_queue">Open Card Print Queue</a></div></div>
    <section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Student / Application</th><th>Card details</th><th>Payment</th><th>Application status</th><th>Photo &amp; signature</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($applicationRows as $app): ?><tr><td><strong><?php echo adminEscape($app['full_name']); ?></strong><br><?php echo adminEscape($app['registration_number']); ?><br>#<?php echo (int)$app['id']; ?> · <?php echo adminEscape($app['application_type']); ?><br><small><?php echo adminEscape((string)$app['created_at']); ?></small></td>
            <td><?php echo adminEscape(trim((string)$app['surname'] . ', ' . (string)$app['given_name'], ', ')); ?><br><?php echo adminEscape((string)$app['card_reg_no']); ?><br><?php echo adminEscape((string)$app['faculty_name'] . ' · ' . (string)$app['course_name']); ?><br>Date of Issue: <?php echo adminEscape((string)($app['date_admitted'] ?? 'Not recorded')); ?><br><?php echo !empty($app['graduation_year']) ? 'Graduates ' . (int)$app['graduation_year'] : ''; ?><details class="admin-record-details"><summary>View application details</summary><dl><dt>Nationality</dt><dd><?php echo adminEscape((string)($app['nationality'] ?: 'Not recorded')); ?></dd><dt>Sex</dt><dd><?php echo adminEscape((string)($app['sex'] ?: 'Not recorded')); ?></dd><dt>Date of Issue</dt><dd><?php echo adminEscape((string)($app['date_admitted'] ?: 'Not recorded')); ?></dd><dt>Application reason</dt><dd><?php echo nl2br(adminEscape((string)($app['reason'] ?: 'Not provided'))); ?></dd><dt>Account email</dt><dd><?php echo adminEscape((string)$app['email']); ?></dd></dl></details></td>
            <td><?php if ($app['application_type'] === 'new'): ?>Not required<?php else: ?><?php echo adminBadge((string)($app['payment_status'] ?? 'missing')); ?><?php if ($app['payment_status'] === 'pending' || $app['payment_status'] === 'rejected'): ?><br><small><?php echo adminEscape((string)$app['reference']); ?> · <?php echo adminEscape((string)$app['amount']); ?></small><?php endif; ?><?php if ($app['receipt_path']): ?><br><a href="console.php?view=private_document&amp;application_id=<?php echo (int)$app['id']; ?>&amp;kind=receipt" target="_blank" rel="noopener">View payment receipt</a><?php endif; ?><?php endif; ?></td>
            <td><?php echo adminBadge((string)$app['application_status']); ?><?php if ($app['card_number']): ?><br><small><?php echo adminEscape($app['card_number']); ?></small><?php endif; ?></td>
            <td><a class="admin-button-secondary" href="console.php?view=student_view&amp;id=<?php echo (int)$app['student_id']; ?>">Student details</a><br><a href="console.php?view=private_document&amp;application_id=<?php echo (int)$app['id']; ?>&amp;kind=photo" target="_blank" rel="noopener">View photo</a><br><a href="console.php?view=private_document&amp;application_id=<?php echo (int)$app['id']; ?>&amp;kind=signature" target="_blank" rel="noopener">Signature</a></td>
            <td><div class="admin-actions">
                <?php if ($app['application_status'] === 'submitted'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="application_review"><input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>"><input type="hidden" name="decision" value="start_review"><button class="admin-button" type="submit">Start Review</button></form><?php endif; ?>
                <?php if ($app['application_status'] === 'under_review'): ?>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="application_review"><input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>"><input type="hidden" name="decision" value="approve"><button class="admin-button" type="submit">Approve</button></form>
                    <button class="admin-button-danger" type="button" data-open-rejection="reject-application-<?php echo (int)$app['id']; ?>">Reject</button>
                    <dialog class="admin-rejection-dialog" id="reject-application-<?php echo (int)$app['id']; ?>" aria-labelledby="reject-title-<?php echo (int)$app['id']; ?>">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                            <input type="hidden" name="action" value="application_review">
                            <input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>">
                            <input type="hidden" name="decision" value="reject">
                            <h2 id="reject-title-<?php echo (int)$app['id']; ?>">Reject application #<?php echo (int)$app['id']; ?></h2>
                            <p>Provide a clear reason and tell the student what to do next.</p>
                            <div class="admin-field">
                                <label for="rejection-reason-<?php echo (int)$app['id']; ?>">Reason for rejection</label>
                                <textarea id="rejection-reason-<?php echo (int)$app['id']; ?>" name="rejection_reason" maxlength="450" required></textarea>
                            </div>
                            <div class="admin-field">
                                <label for="student-advice-<?php echo (int)$app['id']; ?>">Advice for the student</label>
                                <textarea id="student-advice-<?php echo (int)$app['id']; ?>" name="student_advice" maxlength="450" required></textarea>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button-secondary" type="button" data-close-rejection>Cancel</button>
                                <button class="admin-button-danger" type="submit">Confirm rejection</button>
                            </div>
                        </form>
                    </dialog>
                    <?php
                    if (!isset($rejectionDialogScriptAdded)) {
                        $rejectionDialogScriptAdded = true;
                        ?>
                        <script>
                            document.addEventListener('click', function (event) {
                                var openButton = event.target.closest('[data-open-rejection]');
                                if (openButton) {
                                    var dialog = document.getElementById(openButton.getAttribute('data-open-rejection'));
                                    if (dialog && typeof dialog.showModal === 'function') {
                                        dialog.showModal();
                                    }
                                    return;
                                }
                                if (event.target.matches('[data-close-rejection]')) {
                                    event.target.closest('dialog').close();
                                }
                            });
                        </script>
                        <?php
                    }
                    ?>
                <?php endif; ?>
                <?php if ($app['application_status'] === 'approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="application_review"><input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>"><input type="hidden" name="decision" value="make_print_ready"><button class="admin-button" type="submit">Add to Print Queue</button></form><?php endif; ?>
                <?php if ($app['payment_status'] === 'pending' && $app['application_type'] !== 'new'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="payment_review"><input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>"><input type="hidden" name="decision" value="verified"><button class="admin-button-secondary" type="submit">Verify Payment</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="payment_review"><input type="hidden" name="application_id" value="<?php echo (int)$app['id']; ?>"><input type="hidden" name="decision" value="rejected"><button class="admin-button-danger" type="submit">Reject Payment</button></form><?php endif; ?>
                <?php if ($app['card_number']): ?><a class="admin-button-secondary" href="console.php?view=card&amp;application_id=<?php echo (int)$app['id']; ?>">Preview Card</a><?php endif; ?>
            </div></td></tr><?php endforeach; ?>
        <?php if (!$applicationRows): ?><tr><td colspan="6" class="admin-empty">No applications have been submitted.</td></tr><?php endif; ?>
    </tbody></table></div></section>

<?php elseif ($view === 'print_queue'): ?>
    <?php
    $cardStatusFilter = (string)($_GET['status'] ?? 'ready_to_print');
    $allowedCardStatuses = ['ready_to_print', 'printed', 'issued', 'not_issued', 'all'];
    if (!in_array($cardStatusFilter, $allowedCardStatuses, true)) { $cardStatusFilter = 'ready_to_print'; }
    $cardTypeFilter = (string)($_GET['type'] ?? 'all');
    if (!in_array($cardTypeFilter, ['all', 'replacement'], true)) { $cardTypeFilter = 'all'; }
    $searchName = trim((string)($_GET['search_name'] ?? ''));
    $searchCard = trim((string)($_GET['search_card'] ?? ''));
    $searchFaculty = trim((string)($_GET['search_faculty'] ?? ''));
    $searchCourse = trim((string)($_GET['search_course'] ?? ''));
    $queueFaculties = $conn->query('SELECT name FROM student_faculties ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $queueCourses = $conn->query('SELECT name FROM student_courses ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $queueSql = 'SELECT c.id AS card_id, c.card_number, c.card_status, c.printed_at, c.issued_at, a.id AS application_id,
                        a.application_type, a.surname, a.given_name, a.card_reg_no, s.student_id, s.full_name,
                        f.name AS faculty_name, co.name AS course_name
                 FROM id_cards c INNER JOIN id_applications a ON a.id = c.application_id
                 INNER JOIN students s ON s.id = c.student_id
                 LEFT JOIN student_faculties f ON f.id = a.faculty_id LEFT JOIN student_courses co ON co.id = a.course_id';
    $queueConditions = [];
    if ($cardStatusFilter === 'ready_to_print') {
        $queueConditions[] = "c.card_status = 'ready_to_print'";
    } elseif ($cardStatusFilter === 'printed') {
        $queueConditions[] = "c.card_status IN ('printed','issued')";
    } elseif ($cardStatusFilter === 'issued') {
        $queueConditions[] = "c.card_status = 'issued'";
    } elseif ($cardStatusFilter === 'not_issued') {
        $queueConditions[] = "c.card_status IN ('ready_to_print','printed')";
    }
    if ($cardTypeFilter === 'replacement') {
        $queueConditions[] = "a.application_type IN ('lost','damaged')";
    }
    $queueParameters = [];
    foreach ([
        [$searchName, '(s.full_name LIKE ? OR a.surname LIKE ? OR a.given_name LIKE ? OR s.student_id LIKE ?)', 4],
        [$searchCard, '(c.card_number LIKE ? OR a.card_reg_no LIKE ?)', 2],
        [$searchFaculty, 'f.name LIKE ?', 1],
        [$searchCourse, 'co.name LIKE ?', 1],
    ] as [$term, $condition, $parameterCount]) {
        if ($term !== '') {
            $queueConditions[] = $condition;
            for ($index = 0; $index < $parameterCount; $index++) {
                $queueParameters[] = '%' . $term . '%';
            }
        }
    }
    if ($queueConditions) {
        $queueSql .= ' WHERE ' . implode(' AND ', $queueConditions);
    }
    $queueSql .= ' ORDER BY c.generated_at DESC';
    if ($queueParameters) {
        $queueStmt = $conn->prepare($queueSql);
        $queueStmt->bind_param(str_repeat('s', count($queueParameters)), ...$queueParameters);
        $queueStmt->execute();
        $queueRows = $queueStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $queueStmt->close();
    } else {
        $queueRows = $conn->query($queueSql)->fetch_all(MYSQLI_ASSOC);
    }
    ?>
    <div class="print-queue-toolbar">
        <?php $replacementTypeQuery = $cardTypeFilter === 'replacement' ? '&amp;type=replacement' : ''; ?>
        <div class="print-queue-toolbar-links">
            <div class="admin-actions">
                <a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=ready_to_print<?php echo $replacementTypeQuery; ?>">Waiting to print</a><a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=printed<?php echo $replacementTypeQuery; ?>">Printed (including issued)</a><a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=issued<?php echo $replacementTypeQuery; ?>">Issued</a><a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=not_issued<?php echo $replacementTypeQuery; ?>">Not issued</a><a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=all">All cards</a><a class="admin-button-secondary" href="console.php?view=print_queue&amp;status=all&amp;type=replacement">Lost / damaged replacement cards</a>
            </div>
            <a class="admin-button-secondary" href="console.php?view=applications">Review Applications</a>
        </div>
        <form method="get" action="console.php" id="print-queue-search" class="admin-form-grid print-queue-search-fields" style="margin-bottom:16px">
            <input type="hidden" name="view" value="print_queue">
            <div class="admin-field"><label for="queue-search-name">Student name</label><input id="queue-search-name" name="search_name" value="<?php echo adminEscape($searchName); ?>" placeholder="Search student name" autocomplete="off"></div>
            <div class="admin-field"><label for="queue-search-card">Card number / registration</label><input id="queue-search-card" name="search_card" value="<?php echo adminEscape($searchCard); ?>" placeholder="Search card number" autocomplete="off"></div>
            <div class="admin-field"><label for="queue-search-faculty">Faculty (type or choose)</label><input id="queue-search-faculty" name="search_faculty" list="queue-faculty-options" value="<?php echo adminEscape($searchFaculty); ?>" placeholder="Type or choose a faculty" autocomplete="off"><datalist id="queue-faculty-options"><?php foreach ($queueFaculties as $facultyOption): ?><option value="<?php echo adminEscape((string)$facultyOption['name']); ?>"><?php endforeach; ?></datalist></div>
            <div class="admin-field"><label for="queue-search-course">Course (type or choose)</label><input id="queue-search-course" name="search_course" list="queue-course-options" value="<?php echo adminEscape($searchCourse); ?>" placeholder="Type or choose a course" autocomplete="off"><datalist id="queue-course-options"><?php foreach ($queueCourses as $courseOption): ?><option value="<?php echo adminEscape((string)$courseOption['name']); ?>"><?php endforeach; ?></datalist></div>
            <div class="admin-field"><label for="queue-search-status">Card status</label><select id="queue-search-status" name="status"><?php foreach (['ready_to_print' => 'Waiting to print', 'printed' => 'Printed (including issued)', 'issued' => 'Issued', 'not_issued' => 'Not issued', 'all' => 'All cards'] as $statusValue => $statusLabel): ?><option value="<?php echo adminEscape($statusValue); ?>"<?php echo $cardStatusFilter === $statusValue ? ' selected' : ''; ?>><?php echo adminEscape($statusLabel); ?></option><?php endforeach; ?></select></div>
            <?php if ($cardTypeFilter === 'replacement'): ?><input type="hidden" name="type" value="replacement"><?php endif; ?>
        </form>
    </div>
    <section class="admin-panel print-queue-results">
        <form method="get" action="console.php" id="print-queue-form"><input type="hidden" name="view" value="print_batch"></form>
        <div class="admin-table-wrap"><table class="admin-table" id="print-queue-table"><thead><tr><th>Select</th><th>Student</th><th>Card</th><th>Faculty / course</th><th>Status</th><th>Preview / issue</th></tr></thead><tbody>
            <?php foreach ($queueRows as $cardRow): ?><?php $hasCardNumber = trim((string)($cardRow['card_number'] ?? '')) !== ''; ?><tr><td><?php if ($cardStatusFilter === 'ready_to_print' && $cardRow['card_status'] === 'ready_to_print'): ?><?php if ($hasCardNumber): ?><input type="checkbox" form="print-queue-form" name="ids[]" value="<?php echo (int)$cardRow['application_id']; ?>"><?php else: ?><span class="admin-muted" title="Add a card number in the preview before printing">Number required</span><?php endif; ?><?php endif; ?></td><td><?php echo adminEscape($cardRow['full_name']); ?><br><small><?php echo adminEscape($cardRow['student_id']); ?></small></td><td><?php echo adminEscape((string)($cardRow['card_number'] ?? '')); ?><?php if (!$hasCardNumber): ?><br><small class="admin-text-danger">Card number required</small><?php endif; ?><br><?php echo adminEscape(trim((string)$cardRow['surname'] . ' ' . (string)$cardRow['given_name'])); ?></td><td><?php echo adminEscape((string)$cardRow['faculty_name']); ?><br><?php echo adminEscape((string)$cardRow['course_name']); ?></td><td><?php echo adminBadge((string)$cardRow['card_status']); ?><?php if ($cardRow['issued_at']): ?><br><small>Issued <?php echo adminEscape((string)$cardRow['issued_at']); ?></small><?php elseif ($cardRow['printed_at']): ?><br><small>Printed <?php echo adminEscape((string)$cardRow['printed_at']); ?></small><?php endif; ?></td><td><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=card&amp;application_id=<?php echo (int)$cardRow['application_id']; ?>"><?php echo $hasCardNumber ? 'Preview' : 'Add card number'; ?></a><?php if ($cardRow['card_status'] === 'printed' && $hasCardNumber): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="issue_card"><input type="hidden" name="card_id" value="<?php echo (int)$cardRow['card_id']; ?>"><button class="admin-button" type="submit">Issue Card</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
            <?php if (!$queueRows): ?><tr><td colspan="6" class="admin-empty">No cards match this queue filter.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($cardStatusFilter === 'ready_to_print'): ?><p id="print-selection-status" role="status" aria-live="polite">Select one or two cards for printing.</p><button class="admin-button" id="print-selection-submit" type="submit" form="print-queue-form" style="margin-top:12px">Preview selected cards (1–2)</button><?php endif; ?>
    </section>
    <script>
        (function () {
            var searchForm = document.getElementById('print-queue-search');
            var batchForm = document.getElementById('print-queue-form');
            var table = document.getElementById('print-queue-table');
            var submit = document.getElementById('print-selection-submit');
            var selectionStatus = document.getElementById('print-selection-status');
            if (searchForm) {
                var searchTimer;
                searchForm.querySelectorAll('input').forEach(function (input) {
                    input.addEventListener('input', function () {
                        window.clearTimeout(searchTimer);
                        searchTimer = window.setTimeout(function () { searchForm.requestSubmit(); }, 250);
                    });
                });
                searchForm.querySelector('select[name="status"]').addEventListener('change', function () {
                    searchForm.requestSubmit();
                });
            }
            if (!batchForm || !table || !submit || !selectionStatus) { return; }
            var selectedCards = function () {
                return document.querySelectorAll('#print-queue-table input[name="ids[]"]:checked');
            };
            submit.disabled = selectedCards().length === 0;
            table.addEventListener('change', function (event) {
                if (!event.target.matches('input[name="ids[]"]')) { return; }
                var selected = selectedCards();
                if (selected.length > 2) {
                    event.target.checked = false;
                    selected = selectedCards();
                    selectionStatus.textContent = 'Only two cards can be selected in one print batch.';
                    return;
                }
                submit.disabled = selected.length === 0;
                selectionStatus.textContent = selected.length === 0
                    ? 'Select one or two cards for printing.'
                    : selected.length + ' card' + (selected.length === 1 ? '' : 's') + ' selected.';
            });
        })();
    </script>

<?php elseif ($view === 'service_requests'): ?>
    <?php
    $requestFilter = (string)($_GET['status'] ?? 'open');
    $validRequestFilters = ['open', 'completed', 'rejected', 'all'];
    if (!in_array($requestFilter, $validRequestFilters, true)) { $requestFilter = 'open'; }
    $requestSql = 'SELECT r.id, r.service_key, r.subject, r.details, r.request_status, r.staff_response,
                          r.created_at, r.updated_at, s.full_name, s.student_id, s.email
                   FROM student_service_requests r
                   INNER JOIN students s ON s.id = r.student_id';
    if ($requestFilter === 'open') {
        $requestRows = $conn->query($requestSql . " WHERE r.request_status IN ('submitted','in_review') ORDER BY r.created_at ASC")->fetch_all(MYSQLI_ASSOC);
    } elseif ($requestFilter === 'all') {
        $requestRows = $conn->query($requestSql . ' ORDER BY r.created_at DESC LIMIT 300')->fetch_all(MYSQLI_ASSOC);
    } else {
        $requestStmt = $conn->prepare($requestSql . ' WHERE r.request_status = ? ORDER BY r.updated_at DESC LIMIT 300');
        $requestStmt->bind_param('s', $requestFilter);
        $requestStmt->execute();
        $requestRows = $requestStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $requestStmt->close();
    }
    ?>
    <div class="admin-page-heading"><div><h1>Student Service Requests</h1><p>Review and respond to support requests submitted through the student portal. Completing a request does not complete the underlying university service.</p></div><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=service_requests&amp;status=open">Open</a><a class="admin-button-secondary" href="console.php?view=service_requests&amp;status=completed">Completed</a><a class="admin-button-secondary" href="console.php?view=service_requests&amp;status=rejected">Rejected</a><a class="admin-button-secondary" href="console.php?view=service_requests&amp;status=all">All requests</a></div></div>
    <section class="admin-grid">
        <?php if (!$requestRows): ?><div class="admin-panel admin-span-12 admin-empty">No student service requests match this filter.</div>
        <?php else: foreach ($requestRows as $request): ?>
            <article class="admin-panel admin-span-6">
                <div class="admin-page-heading"><div><h2><?php echo adminEscape($request['subject']); ?></h2><p><?php echo adminEscape(ucwords(str_replace('_', ' ', (string)$request['service_key']))); ?> · <?php echo adminEscape($request['full_name'] . ' (' . $request['student_id'] . ')'); ?></p></div><?php echo adminBadge((string)$request['request_status']); ?></div>
                <p><?php echo nl2br(adminEscape($request['details'])); ?></p>
                <p><small><?php echo adminEscape((string)$request['email']); ?> · Submitted <?php echo adminEscape((string)$request['created_at']); ?></small></p>
                <?php if ($request['request_status'] === 'submitted' || $request['request_status'] === 'in_review'): ?>
                    <form method="post" class="admin-form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                        <input type="hidden" name="action" value="review_service_request">
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                        <div class="admin-field"><label for="request-status-<?php echo (int)$request['id']; ?>">Status</label><select id="request-status-<?php echo (int)$request['id']; ?>" name="request_status"><option value="in_review">In review</option><option value="completed">Completed</option><option value="rejected">Rejected</option></select></div>
                        <div class="admin-field full"><label for="request-response-<?php echo (int)$request['id']; ?>">Response to student</label><textarea id="request-response-<?php echo (int)$request['id']; ?>" name="staff_response" maxlength="5000"><?php echo adminEscape((string)$request['staff_response']); ?></textarea></div>
                        <div class="admin-field full"><button class="admin-button" type="submit">Save response</button></div>
                    </form>
                <?php elseif ($request['staff_response'] !== null && $request['staff_response'] !== ''): ?>
                    <p><strong>Staff response:</strong><br><?php echo nl2br(adminEscape((string)$request['staff_response'])); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; endif; ?>
    </section>

<?php elseif ($view === 'print_batch' || $view === 'card' || $view === 'templates'): ?>
    <?php
    $face = (string)($_GET['face'] ?? '');
    $validFaces = ['front', 'back', 'both'];
    $faceSelected = in_array($face, $validFaces, true);
    $alignment = (string)($_GET['alignment'] ?? 'tray');
    if (!in_array($alignment, ['tray', 'center'], true)) {
        $alignment = 'tray';
    }
    $printOffset = (int)round((float)adminGetSetting($conn, 'card_print_alignment_offset_mm', '0'));
    $printOffset = max(-30, min(30, $printOffset));
    $verticalOffset = max(-10, min(10, (float)adminGetSetting($conn, 'card_print_alignment_offset_y_mm', '0')));
    $firstCardOffset = max(-10, min(10, (float)adminGetSetting($conn, 'card_print_alignment_card1_y_mm', '0')));
    $secondCardOffset = max(-10, min(10, (float)adminGetSetting($conn, 'card_print_alignment_card2_y_mm', '5')));
    $firstCardHorizontalOffset = max(-10, min(10, (float)adminGetSetting($conn, 'card_print_alignment_card1_x_mm', '0')));
    $secondCardHorizontalOffset = max(-10, min(10, (float)adminGetSetting($conn, 'card_print_alignment_card2_x_mm', '0')));
    $frontBranding = studentCardFrontBrandingSettings($conn);
    $photoSettings = [];
    foreach ([
        'photo_large_x_mm' => [-10, 10, '0', 1],
        'photo_large_y_mm' => [-10, 10, '0', 1],
        'photo_large_scale_pct' => [50, 200, '100', 0],
        'photo_small_x_mm' => [-10, 10, '0', 1],
        'photo_small_y_mm' => [-10, 10, '0', 1],
        'photo_small_scale_pct' => [50, 200, '100', 0],
    ] as $key => [$minimum, $maximum, $default, $precision]) {
        $settingValue = (float)adminGetSetting($conn, 'card_print_' . $key, $default);
        $photoSettings[$key] = round(max($minimum, min($maximum, $settingValue)), $precision);
    }
    $ids = $view === 'templates' ? [] : array_values(array_unique(array_filter(array_map('intval', is_array($_GET['ids'] ?? null) ? $_GET['ids'] : [(int)($_GET['application_id'] ?? 0)]))));
    if ($view === 'templates') {
        $template = $conn->query("SELECT id FROM id_cards WHERE card_status IN ('ready_to_print','printed','issued') ORDER BY generated_at DESC LIMIT 1")->fetch_assoc();
        $ids = $template ? [(int)$template['id']] : [];
        if ($template) {
            $stmt = $conn->prepare('SELECT application_id FROM id_cards WHERE id = ?');
            $stmt->bind_param('i', $ids[0]);
            $stmt->execute();
            $ids = [(int)$stmt->get_result()->fetch_assoc()['application_id']];
            $stmt->close();
        }
    }
    if ($view === 'print_batch' && (!$ids || count($ids) > 2)) {
        echo '<div class="admin-alert error">Select one or two cards from the print queue.</div><a class="admin-button" href="console.php?view=print_queue">Return to print queue</a>';
    } elseif (!$ids) {
        echo '<div class="admin-alert">There are no generated cards in the database to preview. Process and approve a student application first.</div>';
    } else {
        $cards = [];
        foreach ($ids as $id) {
            $card = adminCardQuery($conn, (int)$id);
            if ($card && ($view !== 'print_batch' || $card['card_status'] === 'ready_to_print')) {
                $cards[] = array_merge($card, $photoSettings);
            }
        }
        $cardsMissingNumbers = array_values(array_filter(
            $cards,
            static fn(array $card): bool => trim((string)($card['card_number'] ?? '')) === ''
        ));
        if (!$cards) { echo '<div class="admin-alert error">Card not found.</div>'; }
        else {
            $faceQuery = ['view' => $view];
            if ($view === 'card' && isset($_GET['application_id'])) {
                $faceQuery['application_id'] = (int)$_GET['application_id'];
            } elseif ($view === 'print_batch') {
                $faceQuery['ids'] = $ids;
            }
            $faceQuery['alignment'] = $alignment;
            ?>
            <div class="admin-page-heading no-print"><div><h1><?php echo $view === 'templates' ? 'Database Card Design Preview' : (count($cards) > 1 ? 'Batch Card Preview' : 'Student ID Card Preview'); ?></h1><p><?php echo $faceSelected ? 'Each face prints at 85 × 54 mm. Choose portrait or landscape, paper size, and tray in your printer settings; both sides print on separate pages.' : 'Choose which card side you want to preview and print.'; ?></p></div><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=print_queue">Back to queue</a></div></div>
            <?php if (!$faceSelected): ?>
                <section class="admin-panel card-face-selector no-print">
                    <h2>Select card side</h2>
                    <p>Select the face to display before printing.</p>
                    <div class="admin-actions">
                        <?php foreach (['front' => 'Front', 'back' => 'Back', 'both' => 'Both sides'] as $faceChoice => $faceLabel): ?>
                            <a class="admin-button" href="console.php?<?php echo adminEscape(http_build_query($faceQuery + ['face' => $faceChoice])); ?>"><?php echo adminEscape($faceLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php else: ?>
                <div class="admin-actions no-print card-face-print-action"><button class="admin-button" type="button" onclick="window.print()"<?php echo $cardsMissingNumbers ? ' disabled aria-describedby="card-number-print-warning"' : ''; ?>>Print <?php echo adminEscape(ucfirst($face)); ?></button>
                    <a class="admin-button-secondary" data-print-offset-link href="console.php?<?php echo adminEscape(http_build_query($faceQuery)); ?>">Change side</a>
                </div>
            <?php endif; ?>
            <?php if ($faceSelected && $cardsMissingNumbers): ?>
                <div id="card-number-print-warning" class="admin-alert error no-print" role="status">
                    Printing is disabled because <?php echo count($cardsMissingNumbers) === 1 ? 'this card has' : 'these cards have'; ?> no card number. Add the number using the admin card-number field before printing.
                    <?php foreach ($cardsMissingNumbers as $missingCard): ?>
                        <a href="console.php?view=card&amp;application_id=<?php echo (int)$missingCard['application_id']; ?>">Add number for <?php echo adminEscape($missingCard['full_name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($faceSelected): ?>
            <?php if (in_array($view, ['print_batch', 'card', 'templates'], true)): ?>
                <div class="admin-print-alignment">
                <section class="admin-print-ruler admin-print-horizontal no-print" data-card-tool-category="horizontal" hidden aria-label="Horizontal print alignment">
                    <datalist id="admin-print-vertical-ticks"><?php for ($tick = -100; $tick <= 100; $tick++): ?><option value="<?php echo number_format($tick / 10, 1, '.', ''); ?>"></option><?php endfor; ?></datalist>
                    <label for="admin-print-offset">Horizontal alignment <output id="admin-print-offset-value" for="admin-print-offset"><?php echo $printOffset > 0 ? '+' : ''; ?><?php echo $printOffset; ?> mm</output></label>
                    <div class="admin-print-ruler-scale" aria-hidden="true"><span>-30</span><span>-20</span><span>-10</span><span>0</span><span>+10</span><span>+20</span><span>+30 mm</span></div>
                    <input id="admin-print-offset" type="range" min="-30" max="30" step="1" value="<?php echo $printOffset; ?>" aria-label="Move cards left or right in millimeters">
                    <div class="admin-print-ruler-help"><span>Move left</span><button class="admin-button-secondary" id="admin-print-offset-reset" type="button">Center</button><span>Move right</span></div>
                </section>
            <?php endif; ?>
                <div class="admin-print-calibration-row">
                    <?php if (in_array($view, ['print_batch', 'card', 'templates'], true)): ?>
                        <aside class="admin-print-vertical-panel admin-print-vertical-left no-print" data-card-tool-category="vertical" hidden>
                            <div class="admin-print-vertical-heading"><label for="admin-print-offset-y">Both cards</label><span>Vertical alignment</span><output id="admin-print-offset-y-value" for="admin-print-offset-y"><?php echo $verticalOffset > 0 ? '+' : ''; ?><?php echo number_format($verticalOffset, 1); ?> mm</output></div>
                            <div class="admin-print-vertical-control"><div class="admin-print-vertical-scale" aria-hidden="true"><span>-10 · up</span><span>-5</span><span>0</span><span>+5</span><span>+10 · down</span></div>
                                <input id="admin-print-offset-y" class="admin-print-vertical-slider" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($verticalOffset, 1, '.', ''); ?>" aria-label="Move all cards up or down in millimeters">
                            </div>
                            <div class="admin-print-vertical-help"><span>Up</span><button class="admin-button-secondary" id="admin-print-offset-y-reset" type="button">Center</button><span>Down</span></div>
                        </aside>
                    <?php endif; ?>
                    <div class="admin-print-preview">
            <?php if ($view === 'print_batch'): ?>
                <?php $batchFaces = $face === 'both' ? ['front', 'back'] : [$face]; ?>
                <?php foreach ($batchFaces as $batchFaceIndex => $batchFace): ?>
                    <div class="admin-batch-print-sheet<?php echo $batchFaceIndex > 0 ? ' admin-batch-print-sheet-next' : ''; ?>" style="--batch-print-offset: <?php echo $printOffset; ?>mm; --batch-print-offset-y: <?php echo $verticalOffset; ?>mm">
                        <?php foreach ($cards as $cardIndex => $card): ?>
                            <?php $cardVerticalOffset = $cardIndex === 0 ? $firstCardOffset : $secondCardOffset; ?>
                            <?php $cardHorizontalOffset = $cardIndex === 0 ? $firstCardHorizontalOffset : $secondCardHorizontalOffset; ?>
                            <?php if (trim((string)($card['card_number'] ?? '')) !== ''): ?>
                                <div class="admin-print-card-slot" data-card-index="<?php echo $cardIndex + 1; ?>" style="--card-vertical-offset: <?php echo $cardVerticalOffset; ?>mm; --card-horizontal-offset: <?php echo number_format($cardHorizontalOffset, 1, '.', ''); ?>mm">
                                    <?php adminRenderCard($card, $batchFace, 'center'); ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($cards as $card): ?>
                    <?php if ($view === 'card' && ($_SESSION['user_type'] ?? '') === 'admin'): ?>
                        <form class="admin-panel no-print card-number-editor" method="post">
                            <h2>Admin card number</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                            <input type="hidden" name="action" value="update_card_number">
                            <input type="hidden" name="application_id" value="<?php echo (int)$card['application_id']; ?>">
                            <label for="card-number-<?php echo (int)$card['application_id']; ?>">Card number</label>
                            <input id="card-number-<?php echo (int)$card['application_id']; ?>" name="card_number" value="<?php echo adminEscape((string)$card['card_number']); ?>" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{0,49}" required>
                            <button class="admin-button" type="submit">Save card number</button>
                        </form>
                    <?php endif; ?>
                    <?php if (trim((string)($card['card_number'] ?? '')) !== ''): ?>
                        <div class="admin-print-offset-sheet" style="--batch-print-offset: <?php echo $printOffset; ?>mm; --batch-print-offset-y: <?php echo $verticalOffset; ?>mm">
                            <div class="admin-print-card-slot" data-card-index="1" style="--card-vertical-offset: <?php echo $firstCardOffset; ?>mm; --card-horizontal-offset: <?php echo number_format($firstCardHorizontalOffset, 1, '.', ''); ?>mm">
                                <?php adminRenderCard($card, $face, 'center'); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="admin-alert error no-print">This card has no card number and cannot be printed. Enter one above, then reload the preview.</div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
                    </div>
                    <?php if (in_array($view, ['print_batch', 'card', 'templates'], true)): ?>
                        <aside class="admin-print-vertical-panel admin-print-vertical-right no-print" data-card-tool-category="vertical" hidden>
                            <?php if ($view === 'print_batch' && count($cards) > 1): ?>
                                <div class="admin-print-vertical-group">
                                    <div class="admin-print-vertical-heading"><label for="admin-print-card1-y">First (upper) card</label><span>Move this card</span><output id="admin-print-card1-y-value" for="admin-print-card1-y"><?php echo $firstCardOffset > 0 ? '+' : ''; ?><?php echo number_format($firstCardOffset, 1); ?> mm</output></div>
                                    <div class="admin-print-vertical-control"><div class="admin-print-vertical-scale" aria-hidden="true"><span>-10 · up</span><span>-5</span><span>0</span><span>+5</span><span>+10 · down</span></div>
                                        <input id="admin-print-card1-y" class="admin-print-vertical-slider" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($firstCardOffset, 1, '.', ''); ?>" aria-label="Move the first card up or down in millimeters">
                                    </div>
                                    <div class="admin-print-vertical-help"><span>Up</span><button class="admin-button-secondary" id="admin-print-card1-y-reset" type="button">Reset</button><span>Down</span></div>
                                </div>
                                <div class="admin-print-vertical-group">
                                    <div class="admin-print-vertical-heading"><label for="admin-print-card2-y">Second (lower) card</label><span>Move this card</span><output id="admin-print-card2-y-value" for="admin-print-card2-y"><?php echo $secondCardOffset > 0 ? '+' : ''; ?><?php echo number_format($secondCardOffset, 1); ?> mm</output></div>
                                    <div class="admin-print-vertical-control"><div class="admin-print-vertical-scale" aria-hidden="true"><span>-10 · up</span><span>-5</span><span>0</span><span>+5</span><span>+10 · down</span></div>
                                        <input id="admin-print-card2-y" class="admin-print-vertical-slider" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($secondCardOffset, 1, '.', ''); ?>" aria-label="Move the second card up or down in millimeters">
                                    </div>
                                    <div class="admin-print-vertical-help"><span>Up</span><button class="admin-button-secondary" id="admin-print-card2-y-reset" type="button">Reset to +5 mm</button><span>Down</span></div>
                                </div>
                            <?php else: ?>
                                <div class="admin-print-vertical-group">
                                    <div class="admin-print-vertical-heading"><label for="admin-print-card1-y">This card</label><span>Vertical alignment</span><output id="admin-print-card1-y-value" for="admin-print-card1-y"><?php echo $firstCardOffset > 0 ? '+' : ''; ?><?php echo number_format($firstCardOffset, 1); ?> mm</output></div>
                                    <div class="admin-print-vertical-control"><div class="admin-print-vertical-scale" aria-hidden="true"><span>-10 · up</span><span>-5</span><span>0</span><span>+5</span><span>+10 · down</span></div>
                                        <input id="admin-print-card1-y" class="admin-print-vertical-slider" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($firstCardOffset, 1, '.', ''); ?>" aria-label="Move this card up or down in millimeters">
                                    </div>
                                    <div class="admin-print-vertical-help"><span>Up</span><button class="admin-button-secondary" id="admin-print-card1-y-reset" type="button">Center</button><span>Down</span></div>
                                    <input id="admin-print-card2-y" type="hidden" value="<?php echo $secondCardOffset; ?>">
                                </div>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>
                </div>
                </div>
            <?php if (in_array($view, ['print_batch', 'card', 'templates'], true)): ?>
                <section class="admin-print-horizontal-card-controls no-print" data-card-tool-category="horizontal" hidden aria-label="Individual card horizontal positions">
                    <div class="admin-print-horizontal-card-control">
                        <label for="admin-print-card1-x-control">Upper card horizontal position <output id="admin-print-card1-x-value" for="admin-print-card1-x-control"><?php echo $firstCardHorizontalOffset > 0 ? '+' : ''; ?><?php echo number_format($firstCardHorizontalOffset, 1); ?> mm</output></label>
                        <div class="admin-print-horizontal-scale" aria-hidden="true"><span>-10 mm · left</span><span>-5</span><span>0</span><span>+5</span><span>+10 mm · right</span></div>
                        <input id="admin-print-card1-x-control" data-print-setting="card1_x_mm" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($firstCardHorizontalOffset, 1, '.', ''); ?>" aria-label="Move the upper card left or right in millimeters">
                        <button class="admin-button-secondary admin-print-horizontal-reset" id="admin-print-card1-x-reset" type="button">Reset to center</button>
                    </div>
                    <div class="admin-print-horizontal-card-control">
                        <label for="admin-print-card2-x-control">Lower card horizontal position <output id="admin-print-card2-x-value" for="admin-print-card2-x-control"><?php echo $secondCardHorizontalOffset > 0 ? '+' : ''; ?><?php echo number_format($secondCardHorizontalOffset, 1); ?> mm</output></label>
                        <div class="admin-print-horizontal-scale" aria-hidden="true"><span>-10 mm · left</span><span>-5</span><span>0</span><span>+5</span><span>+10 mm · right</span></div>
                        <input id="admin-print-card2-x-control" data-print-setting="card2_x_mm" type="range" min="-10" max="10" step="0.1" list="admin-print-vertical-ticks" value="<?php echo number_format($secondCardHorizontalOffset, 1, '.', ''); ?>" aria-label="Move the lower card left or right in millimeters">
                        <button class="admin-button-secondary admin-print-horizontal-reset" id="admin-print-card2-x-reset" type="button">Reset to center</button>
                    </div>
                </section>
            <?php endif; ?>
            <?php if (in_array($view, ['print_batch', 'card', 'templates'], true)): ?>
                <section class="admin-panel admin-photo-layout-controls no-print" data-card-tool-category="photo" hidden aria-label="Student photo positioning and size">
                    <h2>Student photo positioning and size</h2>
                    <p>Adjust the large and small photo on the card. The frame stays in place; moving or resizing the image changes its crop.</p>
                    <div class="admin-photo-layout-grid">
                        <?php foreach ([
                            'large' => ['Large photo', 'photo_large'],
                            'small' => ['Small photo', 'photo_small'],
                        ] as $photoKind => [$photoLabel, $photoKey]): ?>
                            <fieldset class="admin-photo-layout-group">
                                <legend><?php echo adminEscape($photoLabel); ?></legend>
                                <label for="admin-photo-<?php echo $photoKind; ?>-x">Horizontal position <output id="admin-photo-<?php echo $photoKind; ?>-x-value"><?php echo $photoSettings[$photoKey . '_x_mm'] > 0 ? '+' : ''; ?><?php echo number_format($photoSettings[$photoKey . '_x_mm'], 1); ?> mm</output></label>
                                <div class="admin-print-horizontal-scale" aria-hidden="true"><span>-10 mm · left</span><span>0</span><span>+10 mm · right</span></div>
                                <input id="admin-photo-<?php echo $photoKind; ?>-x" type="range" min="-10" max="10" step="0.1" value="<?php echo number_format($photoSettings[$photoKey . '_x_mm'], 1, '.', ''); ?>" aria-label="<?php echo adminEscape($photoLabel); ?> horizontal position">
                                <label for="admin-photo-<?php echo $photoKind; ?>-y">Vertical position <output id="admin-photo-<?php echo $photoKind; ?>-y-value"><?php echo $photoSettings[$photoKey . '_y_mm'] > 0 ? '+' : ''; ?><?php echo number_format($photoSettings[$photoKey . '_y_mm'], 1); ?> mm</output></label>
                                <div class="admin-print-horizontal-scale" aria-hidden="true"><span>-10 mm · up</span><span>0</span><span>+10 mm · down</span></div>
                                <input id="admin-photo-<?php echo $photoKind; ?>-y" type="range" min="-10" max="10" step="0.1" value="<?php echo number_format($photoSettings[$photoKey . '_y_mm'], 1, '.', ''); ?>" aria-label="<?php echo adminEscape($photoLabel); ?> vertical position">
                                <label for="admin-photo-<?php echo $photoKind; ?>-scale">Photo size <output id="admin-photo-<?php echo $photoKind; ?>-scale-value"><?php echo (int)$photoSettings[$photoKey . '_scale_pct']; ?>%</output></label>
                                <div class="admin-print-horizontal-scale" aria-hidden="true"><span>50% · smaller</span><span>100%</span><span>200% · larger</span></div>
                                <input id="admin-photo-<?php echo $photoKind; ?>-scale" type="range" min="50" max="200" step="1" value="<?php echo (int)$photoSettings[$photoKey . '_scale_pct']; ?>" aria-label="<?php echo adminEscape($photoLabel); ?> size">
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($view === 'print_batch' && !$cardsMissingNumbers): ?><form class="admin-panel no-print admin-print-confirmation" method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="batch_print"><?php foreach ($ids as $id): ?><input type="hidden" name="application_ids[]" value="<?php echo (int)$id; ?>"><?php endforeach; ?>
                <p>After sending the cards to your printer, confirm the queue update below.</p><button class="admin-button" type="submit">Confirm printed (update database)</button></form><?php endif; ?>
            <?php endif; ?>
            <?php
        }
    }
    ?>

<?php elseif ($view === 'analytics'): ?>
            <?php
            $applicationStats = $conn->query('SELECT application_status, COUNT(*) AS total FROM id_applications GROUP BY application_status')->fetch_all(MYSQLI_ASSOC);
            $cardStats = $conn->query('SELECT card_status, COUNT(*) AS total FROM id_cards GROUP BY card_status')->fetch_all(MYSQLI_ASSOC);
            $monthlyStats = $conn->query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total
                 FROM id_applications WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY period DESC"
            )->fetch_all(MYSQLI_ASSOC);
            $serviceStats = $conn->query('SELECT service_key, request_status, COUNT(*) AS total FROM student_service_requests GROUP BY service_key, request_status ORDER BY service_key')->fetch_all(MYSQLI_ASSOC);
            ?>
            <div class="admin-page-heading"><div><h1>Analytics</h1><p>Counts and trends calculated from saved students, applications, cards and service requests.</p></div><a class="admin-button-secondary" href="console.php?view=reports">Open Reports</a></div>
            <section class="admin-grid">
                <div class="admin-panel admin-span-6"><h2>Application status</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Status</th><th>Records</th></tr></thead><tbody><?php foreach ($applicationStats as $row): ?><tr><td><?php echo adminBadge((string)$row['application_status']); ?></td><td><?php echo number_format((int)$row['total']); ?></td></tr><?php endforeach; ?><?php if (!$applicationStats): ?><tr><td colspan="2" class="admin-empty">No application records.</td></tr><?php endif; ?></tbody></table></div></div>
                <div class="admin-panel admin-span-6"><h2>ID card status</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Status</th><th>Cards</th></tr></thead><tbody><?php foreach ($cardStats as $row): ?><tr><td><?php echo adminBadge((string)$row['card_status']); ?></td><td><?php echo number_format((int)$row['total']); ?></td></tr><?php endforeach; ?><?php if (!$cardStats): ?><tr><td colspan="2" class="admin-empty">No cards generated yet.</td></tr><?php endif; ?></tbody></table></div></div>
                <div class="admin-panel admin-span-6"><h2>Applications submitted in the last 12 months</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Month</th><th>Applications</th></tr></thead><tbody><?php foreach ($monthlyStats as $row): ?><tr><td><?php echo adminEscape((string)$row['period']); ?></td><td><?php echo number_format((int)$row['total']); ?></td></tr><?php endforeach; ?><?php if (!$monthlyStats): ?><tr><td colspan="2" class="admin-empty">No recent applications.</td></tr><?php endif; ?></tbody></table></div></div>
                <div class="admin-panel admin-span-6"><h2>Student service requests</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Service</th><th>Status</th><th>Requests</th></tr></thead><tbody><?php foreach ($serviceStats as $row): ?><tr><td><?php echo adminEscape((string)$row['service_key']); ?></td><td><?php echo adminBadge((string)$row['request_status']); ?></td><td><?php echo (int)$row['total']; ?></td></tr><?php endforeach; ?><?php if (!$serviceStats): ?><tr><td colspan="3" class="admin-empty">No student service requests.</td></tr><?php endif; ?></tbody></table></div></div>
            </section>

<?php elseif ($view === 'reports'): ?>
            <?php
            $reportCounts = [
                'Students' => (int)$conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'],
                'Applications' => (int)$conn->query('SELECT COUNT(*) AS total FROM id_applications')->fetch_assoc()['total'],
                'Cards ready to print' => (int)$conn->query("SELECT COUNT(*) AS total FROM id_cards WHERE card_status = 'ready_to_print'")->fetch_assoc()['total'],
                'Cards issued' => (int)$conn->query("SELECT COUNT(*) AS total FROM id_cards WHERE card_status = 'issued'")->fetch_assoc()['total'],
                'Pending payment evidence' => (int)$conn->query("SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'pending'")->fetch_assoc()['total'],
            ];
            ?>
            <div class="admin-reports-overview">
                <div class="admin-page-heading"><div><h1>Reports</h1><p>Current totals and downloadable student or card records from the database.</p></div><a class="admin-button" href="console.php?view=report_export">Export card report (CSV)</a></div>
                <section class="admin-stats"><?php foreach ($reportCounts as $label => $count): ?><div class="admin-stat"><span class="admin-stat-icon"><i class="fas fa-chart-simple"></i></span><span><strong><?php echo number_format($count); ?></strong><small><?php echo adminEscape($label); ?></small></span></div><?php endforeach; ?></section>
            </div>
            <section class="admin-panel"><h2>Report exports</h2><div class="admin-actions"><a class="admin-button" href="console.php?view=student_export">Export student directory</a><a class="admin-button-secondary" href="console.php?view=report_export">Export ID card/application report</a><a class="admin-button-secondary" href="console.php?view=analytics">View status analytics</a></div></section>

<?php elseif ($view === 'settings'): ?>
            <?php $applicationsOpen = adminGetSetting($conn, 'student_applications_open', '1') === '1'; ?>
            <?php $backSettings = adminCardBackSettings($conn); ?>
            <?php $codeSettings = studentCardCodeSettings($conn); ?>
            <?php $frontRowSettings = studentCardFrontRowSettings($conn); ?>
            <?php $frontBranding = studentCardFrontBrandingSettings($conn); ?>
            <div class="admin-page-heading"><div><h1>Application Settings</h1><p>Control whether student accounts can submit new or replacement ID applications.</p></div></div>
            <section class="admin-panel"><h2>Student ID application intake</h2><p>Current state: <?php echo $applicationsOpen ? '<span class="admin-badge success">Open</span>' : '<span class="admin-badge danger">Paused</span>'; ?></p><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="application_settings"><input type="hidden" name="student_applications_open" value="<?php echo $applicationsOpen ? '0' : '1'; ?>"><button class="<?php echo $applicationsOpen ? 'admin-button-danger' : 'admin-button'; ?>" type="submit"><?php echo $applicationsOpen ? 'Pause student applications' : 'Open student applications'; ?></button></form><p style="margin-top:12px;color:var(--admin-muted)">This setting is enforced when students submit applications. Existing submissions are not changed.</p></section>
            <section class="admin-panel admin-card-back-settings">
                <h2>Student ID card back design</h2>
                <p>Update the text and colors printed on the back of every card. The verification barcode remains automatically generated for each card.</p>
                <form method="post" class="admin-form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                    <input type="hidden" name="action" value="card_back_settings">
                    <div class="admin-field full"><label for="card-back-header-title">Header title</label><input id="card-back-header-title" name="header_title" maxlength="100" value="<?php echo adminEscape($backSettings['header_title']); ?>"></div>
                    <div class="admin-field full"><label for="card-back-motto">Motto / subtitle</label><input id="card-back-motto" name="motto" maxlength="120" value="<?php echo adminEscape($backSettings['motto']); ?>"></div>
                    <div class="admin-field"><label for="card-back-address">Address</label><input id="card-back-address" name="address" maxlength="120" value="<?php echo adminEscape($backSettings['address']); ?>"></div>
                    <div class="admin-field"><label for="card-back-telephone">Telephone</label><input id="card-back-telephone" name="telephone" maxlength="80" value="<?php echo adminEscape($backSettings['telephone']); ?>"></div>
                    <div class="admin-field"><label for="card-back-email">Email</label><input id="card-back-email" name="email" maxlength="120" value="<?php echo adminEscape($backSettings['email']); ?>"></div>
                    <div class="admin-field"><label for="card-back-website">Website</label><input id="card-back-website" name="website" maxlength="120" value="<?php echo adminEscape($backSettings['website']); ?>"></div>
                    <div class="admin-field full"><label for="card-back-return-text">“If found” return message</label><input id="card-back-return-text" name="return_text" maxlength="140" value="<?php echo adminEscape($backSettings['return_text']); ?>"></div>
                    <div class="admin-field"><label for="card-back-accent-color">Header and divider color</label><input id="card-back-accent-color" name="accent_color" type="color" value="<?php echo adminEscape($backSettings['accent_color']); ?>"></div>
                    <div class="admin-field"><label for="card-back-header-text-color">Header text color</label><input id="card-back-header-text-color" name="header_text_color" type="color" value="<?php echo adminEscape($backSettings['header_text_color']); ?>"></div>
                    <div class="admin-field"><label for="card-back-body-text-color">Contact and return text color</label><input id="card-back-body-text-color" name="body_text_color" type="color" value="<?php echo adminEscape($backSettings['body_text_color']); ?>"></div>
                    <div class="admin-field full"><button class="admin-button" type="submit">Save card back design</button></div>
                </form>
            </section>
            <section class="admin-panel admin-card-code-settings">
                <h2>Student ID front logo and heading</h2>
                <p>Customize the logo and both heading lines shown on the front of student ID cards.</p>
                <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                    <input type="hidden" name="action" value="card_front_branding">
                    <div class="admin-field full"><label for="card-front-university-name">University name</label><input id="card-front-university-name" name="university_name" maxlength="80" value="<?php echo adminEscape($frontBranding['university_name']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-university-color">University name color</label><input id="card-front-university-color" name="university_color" type="color" value="<?php echo adminEscape($frontBranding['university_color']); ?>"></div>
                    <div class="admin-field"><label for="card-front-university-size">University name size (12–24 px)</label><input id="card-front-university-size" name="university_font_size_px" type="number" min="12" max="24" step="0.5" value="<?php echo adminEscape($frontBranding['university_font_size_px']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-university-font">University name font</label><select id="card-front-university-font" name="university_font_family" required><?php foreach (studentCardFrontBrandingFonts() as $fontLabel => $fontCss): ?><option value="<?php echo adminEscape($fontLabel); ?>"<?php echo $frontBranding['university_font_family'] === $fontLabel ? ' selected' : ''; ?>><?php echo adminEscape($fontLabel); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-field full"><label for="card-front-title">Card title</label><input id="card-front-title" name="card_title" maxlength="80" value="<?php echo adminEscape($frontBranding['card_title']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-title-color">Card title color</label><input id="card-front-title-color" name="card_title_color" type="color" value="<?php echo adminEscape($frontBranding['card_title_color']); ?>"></div>
                    <div class="admin-field"><label for="card-front-title-size">Card title size (8–16 px)</label><input id="card-front-title-size" name="card_title_font_size_px" type="number" min="8" max="16" step="0.5" value="<?php echo adminEscape($frontBranding['card_title_font_size_px']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-title-font">Card title font</label><select id="card-front-title-font" name="card_title_font_family" required><?php foreach (studentCardFrontBrandingFonts() as $fontLabel => $fontCss): ?><option value="<?php echo adminEscape($fontLabel); ?>"<?php echo $frontBranding['card_title_font_family'] === $fontLabel ? ' selected' : ''; ?>><?php echo adminEscape($fontLabel); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-field"><label for="card-front-logo">Upload logo (PNG, JPEG, or WebP; up to 2 MB)</label><input id="card-front-logo" name="card_logo" type="file" accept="image/png,image/jpeg,image/webp"></div>
                    <div class="admin-field"><label for="card-front-logo-height">Logo height (24–48 px)</label><input id="card-front-logo-height" name="logo_height_px" type="number" min="24" max="48" step="1" value="<?php echo adminEscape($frontBranding['logo_height_px']); ?>" required></div>
                    <div class="admin-field full"><?php if (is_file(studentCardFrontLogoPath($frontBranding))): ?><img src="<?php echo adminEscape(studentCardFrontLogoUrl($frontBranding, true)); ?>" alt="Current card logo" style="max-width:160px;max-height:80px;object-fit:contain"><?php endif; ?></div>
                    <div class="admin-field full"><strong>Centered front-card watermark</strong><span>Uses the current university header logo. Changing the header logo also changes this watermark.</span></div>
                    <div class="admin-field full"><label><input name="watermark_enabled" type="checkbox" value="1"<?php echo $frontBranding['watermark_enabled'] === '1' ? ' checked' : ''; ?>> Show the watermark (uncheck to remove it; check again to restore it)</label></div>
                    <div class="admin-field"><label for="card-front-watermark-width">Watermark width (10–80 mm)</label><input id="card-front-watermark-width" name="watermark_width_mm" type="number" min="10" max="80" step="1" value="<?php echo adminEscape($frontBranding['watermark_width_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-watermark-height">Watermark height (10–50 mm)</label><input id="card-front-watermark-height" name="watermark_height_mm" type="number" min="10" max="50" step="1" value="<?php echo adminEscape($frontBranding['watermark_height_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-watermark-opacity">Watermark opacity (0.05–0.50)</label><input id="card-front-watermark-opacity" name="watermark_opacity" type="number" min="0.05" max="0.50" step="0.01" value="<?php echo adminEscape($frontBranding['watermark_opacity']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-watermark-horizontal">Watermark left/center/right position</label><select id="card-front-watermark-horizontal" name="watermark_horizontal_position"><option value="left"<?php echo $frontBranding['watermark_horizontal_position'] === 'left' ? ' selected' : ''; ?>>Left</option><option value="center"<?php echo $frontBranding['watermark_horizontal_position'] === 'center' ? ' selected' : ''; ?>>Center</option><option value="right"<?php echo $frontBranding['watermark_horizontal_position'] === 'right' ? ' selected' : ''; ?>>Right</option></select></div>
                    <div class="admin-field"><label for="card-front-watermark-vertical">Watermark top/center/bottom position</label><select id="card-front-watermark-vertical" name="watermark_vertical_position"><option value="top"<?php echo $frontBranding['watermark_vertical_position'] === 'top' ? ' selected' : ''; ?>>Top</option><option value="center"<?php echo $frontBranding['watermark_vertical_position'] === 'center' ? ' selected' : ''; ?>>Center</option><option value="bottom"<?php echo $frontBranding['watermark_vertical_position'] === 'bottom' ? ' selected' : ''; ?>>Bottom</option></select></div>
                    <div class="admin-field"><label for="card-front-watermark-x">Horizontal ruler (−42.5 to 42.5 mm; − left, + right)</label><input id="card-front-watermark-x" name="watermark_x_mm" type="number" min="-42.5" max="42.5" step="0.1" value="<?php echo adminEscape($frontBranding['watermark_x_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-watermark-y">Vertical ruler (−27 to 27 mm; − up, + down)</label><input id="card-front-watermark-y" name="watermark_y_mm" type="number" min="-27" max="27" step="0.1" value="<?php echo adminEscape($frontBranding['watermark_y_mm']); ?>" required></div>
                    <div class="admin-field full"><strong>Signature image placement and size</strong><span>Move each signature image horizontally in millimetres and adjust its width; the original aspect ratio is preserved.</span></div>
                    <div class="admin-field"><label for="card-front-holder-signature-x">Holder signature horizontal movement (−30 to 30 mm)</label><input id="card-front-holder-signature-x" name="holder_signature_x_mm" type="number" min="-30" max="30" step="0.1" value="<?php echo adminEscape($frontBranding['holder_signature_x_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-holder-signature-width">Holder signature width (5–40 mm)</label><input id="card-front-holder-signature-width" name="holder_signature_width_mm" type="number" min="5" max="40" step="0.5" value="<?php echo adminEscape($frontBranding['holder_signature_width_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-registrar-signature-x">Registrar signature horizontal movement (−30 to 30 mm)</label><input id="card-front-registrar-signature-x" name="registrar_signature_x_mm" type="number" min="-30" max="30" step="0.1" value="<?php echo adminEscape($frontBranding['registrar_signature_x_mm']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-registrar-signature-width">Registrar signature width (5–40 mm)</label><input id="card-front-registrar-signature-width" name="registrar_signature_width_mm" type="number" min="5" max="40" step="0.5" value="<?php echo adminEscape($frontBranding['registrar_signature_width_mm']); ?>" required></div>
                    <div class="admin-field full"><label for="card-front-signature-y">Move both signature blocks vertically (−25 mm up, +25 mm down)</label><input id="card-front-signature-y" name="signature_y_mm" type="number" min="-25" max="25" step="0.1" value="<?php echo adminEscape($frontBranding['signature_y_mm']); ?>" required></div>
                    <div class="admin-field full"><strong>Background logos behind right-side text</strong><span>Adjust each repeated logo horizontally, vertically, or enlarge both directions together.</span></div>
                    <div class="admin-field"><label for="card-front-right-pattern-width">Horizontal logo size (left/right %, 1–100)</label><input id="card-front-right-pattern-width" name="right_pattern_width_pct" type="number" min="1" max="100" step="1" value="<?php echo adminEscape($frontBranding['right_pattern_width_pct']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-right-pattern-height">Vertical logo size (up/down %, 1–100)</label><input id="card-front-right-pattern-height" name="right_pattern_height_pct" type="number" min="1" max="100" step="0.01" value="<?php echo adminEscape($frontBranding['right_pattern_height_pct']); ?>" required></div>
                    <div class="admin-field"><label for="card-front-right-pattern-scale">Scale both directions together (25–300%)</label><input id="card-front-right-pattern-scale" name="right_pattern_scale_pct" type="number" min="25" max="300" step="5" value="<?php echo adminEscape($frontBranding['right_pattern_scale_pct']); ?>" required></div>
                    <section class="admin-card-bar-controls admin-field full" aria-label="Front-card blue bar customization">
                        <h3>Front-card blue bars</h3>
                        <p>These controls apply the color and thickness to all bars. Move and rotate the top and bottom bars; visibility can be toggled for each bar.</p>
                        <div class="admin-card-bar-global">
                            <label for="card-front-bar-color">Bar color</label>
                            <input id="card-front-bar-color" name="bar_color" type="color" value="<?php echo adminEscape($frontBranding['bar_color']); ?>" required>
                            <label for="card-front-bar-thickness">Thickness (50–200%)</label>
                            <input id="card-front-bar-thickness" name="bar_thickness_pct" type="number" min="50" max="200" step="5" value="<?php echo adminEscape($frontBranding['bar_thickness_pct']); ?>" required>
                        </div>
                        <div class="admin-card-bar-grid">
                            <?php foreach ([
                                'top' => ['Top bar', 'top'],
                                'middle' => ['Header divider', null],
                                'bottom' => ['Bottom bar', 'bottom'],
                            ] as $bar => [$barLabel, $position]): ?>
                                <fieldset class="admin-card-bar-group">
                                    <legend><?php echo adminEscape($barLabel); ?></legend>
                                    <label><input name="bar_<?php echo $bar; ?>_visible" type="checkbox" value="1"<?php echo $frontBranding['bar_' . $bar . '_visible'] === '1' ? ' checked' : ''; ?>> Show this bar</label>
                                    <?php if ($position !== null): ?>
                                        <?php foreach (['x_mm' => 'Horizontal movement (mm)', 'y_mm' => 'Vertical movement (mm)', 'rotation_deg' => 'Rotation (degrees)'] as $axis => $axisLabel): ?>
                                            <?php $settingKey = 'bar_' . $position . '_' . $axis; ?>
                                            <?php $inputId = 'card-front-bar-' . $position . '-' . ($axis === 'rotation_deg' ? 'rotation' : substr($axis, 0, 1)); ?>
                                            <label for="<?php echo $inputId; ?>"><?php echo adminEscape($axisLabel); ?></label>
                                            <input id="<?php echo $inputId; ?>" name="<?php echo adminEscape($settingKey); ?>" type="number" min="<?php echo $axis === 'rotation_deg' ? '-15' : '-10'; ?>" max="<?php echo $axis === 'rotation_deg' ? '15' : '10'; ?>" step="0.1" value="<?php echo adminEscape($frontBranding[$settingKey]); ?>" required>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </fieldset>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <div class="admin-field full"><button class="admin-button" type="submit">Save card front branding and bars</button></div>
                </form>
            </section>
            <section class="admin-panel admin-card-code-settings">
                <h2>Student ID detail row styles</h2>
                <p>Customize the label and value colors, text size, and font for each row in the circled card details. Changes apply to both admin and student card previews and prints.</p>
                <form method="post" class="admin-form-grid admin-card-row-style-form">
                    <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                    <input type="hidden" name="action" value="card_front_row_settings">
                    <div class="admin-field full"><strong>Left-side card text</strong></div>
                    <?php foreach ([
                        'surname' => 'Surname',
                        'given_name' => 'Given name',
                        'nationality' => 'Nationality',
                        'sex' => 'Sex',
                        'issue_date' => 'Date of issue',
                    ] as $rowName => $rowLabel): ?>
                        <?php $rowSettings = $frontRowSettings[$rowName]; ?>
                        <div class="admin-field full admin-card-row-style-heading"><strong><?php echo adminEscape($rowLabel); ?></strong></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-label-color">Label color</label><input id="card-row-<?php echo adminEscape($rowName); ?>-label-color" name="rows[<?php echo adminEscape($rowName); ?>][label_color]" type="color" value="<?php echo adminEscape($rowSettings['label_color']); ?>"></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-value-color">Value color</label><input id="card-row-<?php echo adminEscape($rowName); ?>-value-color" name="rows[<?php echo adminEscape($rowName); ?>][value_color]" type="color" value="<?php echo adminEscape($rowSettings['value_color']); ?>"></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-font-size">Text size (6–12 px)</label><input id="card-row-<?php echo adminEscape($rowName); ?>-font-size" name="rows[<?php echo adminEscape($rowName); ?>][font_size_px]" type="number" min="6" max="12" step="0.5" value="<?php echo adminEscape($rowSettings['font_size_px']); ?>" required></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-font-family">Font type</label><select id="card-row-<?php echo adminEscape($rowName); ?>-font-family" name="rows[<?php echo adminEscape($rowName); ?>][font_family]" required><?php foreach (studentCardFrontRowFontFamilies() as $fontLabel => $fontCss): ?><option value="<?php echo adminEscape($fontLabel); ?>"<?php echo $rowSettings['font_family'] === $fontLabel ? ' selected' : ''; ?>><?php echo adminEscape($fontLabel); ?></option><?php endforeach; ?></select></div>
                    <?php endforeach; ?>
                    <div class="admin-field full"><strong>Right-side card text</strong></div>
                    <?php foreach ([
                        'card_number' => 'Card number',
                        'registration_number' => 'Registration number',
                        'faculty' => 'Faculty',
                        'course' => 'Course',
                        'expiry_date' => 'Expiry date',
                    ] as $rowName => $rowLabel): ?>
                        <?php $rowSettings = $frontRowSettings[$rowName]; ?>
                        <div class="admin-field full admin-card-row-style-heading"><strong><?php echo adminEscape($rowLabel); ?></strong></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-label-color">Label color</label><input id="card-row-<?php echo adminEscape($rowName); ?>-label-color" name="rows[<?php echo adminEscape($rowName); ?>][label_color]" type="color" value="<?php echo adminEscape($rowSettings['label_color']); ?>"></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-value-color">Value color</label><input id="card-row-<?php echo adminEscape($rowName); ?>-value-color" name="rows[<?php echo adminEscape($rowName); ?>][value_color]" type="color" value="<?php echo adminEscape($rowSettings['value_color']); ?>"></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-font-size">Text size (6–12 px)</label><input id="card-row-<?php echo adminEscape($rowName); ?>-font-size" name="rows[<?php echo adminEscape($rowName); ?>][font_size_px]" type="number" min="6" max="12" step="0.5" value="<?php echo adminEscape($rowSettings['font_size_px']); ?>" required></div>
                        <div class="admin-field"><label for="card-row-<?php echo adminEscape($rowName); ?>-font-family">Font type</label><select id="card-row-<?php echo adminEscape($rowName); ?>-font-family" name="rows[<?php echo adminEscape($rowName); ?>][font_family]" required><?php foreach (studentCardFrontRowFontFamilies() as $fontLabel => $fontCss): ?><option value="<?php echo adminEscape($fontLabel); ?>"<?php echo $rowSettings['font_family'] === $fontLabel ? ' selected' : ''; ?>><?php echo adminEscape($fontLabel); ?></option><?php endforeach; ?></select></div>
                    <?php endforeach; ?>
                    <div class="admin-field full"><button class="admin-button" type="submit">Save card detail row styles</button></div>
                </form>
            </section>
            <section class="admin-panel admin-card-code-settings">
                <h2>Student ID QR code and barcode</h2>
                <p>Adjust code colors, print sizes, and the text encoded when each code is scanned. QR output is limited to 53 bytes; barcode output supports up to 250 printable ASCII characters.</p>
                <form method="post" class="admin-form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                    <input type="hidden" name="action" value="card_code_settings">
                    <div class="admin-field"><label for="card-code-qr-foreground">QR code marks</label><input id="card-code-qr-foreground" name="qr_foreground" type="color" value="<?php echo adminEscape($codeSettings['qr_foreground']); ?>"></div>
                    <div class="admin-field"><label for="card-code-qr-background">QR background</label><input id="card-code-qr-background" name="qr_background" type="color" value="<?php echo adminEscape($codeSettings['qr_background']); ?>"></div>
                    <div class="admin-field"><label for="card-code-qr-size">QR print size (8–14 mm)</label><input id="card-code-qr-size" name="qr_size_mm" type="number" min="8" max="14" step="0.5" value="<?php echo adminEscape($codeSettings['qr_size_mm']); ?>" required></div>
                    <div class="admin-field full"><label for="card-code-qr-output">QR scan output</label><input id="card-code-qr-output" name="qr_output_template" maxlength="250" value="<?php echo adminEscape($codeSettings['qr_output_template']); ?>" required><small>Use <code>{card_number}</code> to insert the card number. QR output must fit 53 bytes.</small></div>
                    <div class="admin-field"><label for="card-code-barcode-foreground">Barcode marks</label><input id="card-code-barcode-foreground" name="barcode_foreground" type="color" value="<?php echo adminEscape($codeSettings['barcode_foreground']); ?>"></div>
                    <div class="admin-field"><label for="card-code-barcode-background">Barcode background</label><input id="card-code-barcode-background" name="barcode_background" type="color" value="<?php echo adminEscape($codeSettings['barcode_background']); ?>"></div>
                    <div class="admin-field"><label for="card-code-barcode-width">Barcode width (50–60%)</label><input id="card-code-barcode-width" name="barcode_width_percent" type="number" min="50" max="60" step="1" value="<?php echo adminEscape($codeSettings['barcode_width_percent']); ?>" required></div>
                    <div class="admin-field"><label for="card-code-barcode-height">Barcode height (8–12 mm)</label><input id="card-code-barcode-height" name="barcode_height_mm" type="number" min="8" max="12" step="0.5" value="<?php echo adminEscape($codeSettings['barcode_height_mm']); ?>" required></div>
                    <div class="admin-field full"><label for="card-code-barcode-output">Barcode scan output</label><input id="card-code-barcode-output" name="barcode_output_template" maxlength="250" value="<?php echo adminEscape($codeSettings['barcode_output_template']); ?>" required><small>Available placeholders: <code>{verification_url}</code>, <code>{card_number}</code>, <code>{student_id}</code>, <code>{student_name}</code>. Names are transliterated to printable ASCII for Code 128.</small></div>
                    <div class="admin-field full"><button class="admin-button" type="submit">Save QR and barcode settings</button></div>
                </form>
            </section>

<?php elseif ($view === 'announcements'): ?>
            <?php $announcements = $conn->query('SELECT id, title, message, status, created_at FROM admin_announcements ORDER BY created_at DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC); ?>
            <div class="admin-page-heading"><div><h1>Announcements</h1><p>Create and manage saved system announcements. Active notices are stored in the database.</p></div></div>
            <section class="admin-grid">
                <div class="admin-panel admin-span-4"><h2>Publish Announcement</h2><form method="post" class="admin-form-grid"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="create_announcement"><div class="admin-field full"><label for="announcement-title">Title</label><input id="announcement-title" name="title" maxlength="180" required></div><div class="admin-field full"><label for="announcement-message">Message</label><textarea id="announcement-message" name="message" maxlength="10000" required></textarea></div><div class="admin-field full"><button class="admin-button" type="submit">Publish</button></div></form></div>
                <div class="admin-panel admin-span-8"><h2>Published Announcements</h2><?php if (!$announcements): ?><div class="admin-empty">No announcements have been saved.</div><?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title / Message</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody><?php foreach ($announcements as $announcement): ?><tr><td><strong><?php echo adminEscape($announcement['title']); ?></strong><br><?php echo nl2br(adminEscape($announcement['message'])); ?></td><td><?php echo adminBadge((string)$announcement['status']); ?></td><td><?php echo adminEscape((string)$announcement['created_at']); ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="toggle_announcement"><input type="hidden" name="announcement_id" value="<?php echo (int)$announcement['id']; ?>"><input type="hidden" name="new_status" value="<?php echo $announcement['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="admin-button-secondary" type="submit"><?php echo $announcement['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
            </section>

<?php elseif ($view === 'notifications'): ?>
            <?php
            $role = (string)$_SESSION['user_type'];
            $stmt = $conn->prepare('SELECT id, title, message, notification_type, reference_type, reference_id, read_at, created_at FROM admin_notifications WHERE recipient_role = ? ORDER BY read_at IS NULL DESC, created_at DESC LIMIT 150');
            $stmt->bind_param('s', $role);
            $stmt->execute();
            $notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $pendingRequestCount = (int)$conn->query("SELECT COUNT(*) AS total FROM student_service_requests WHERE request_status IN ('submitted','in_review')")->fetch_assoc()['total'];
            ?>
            <div class="admin-page-heading"><div><h1>Notifications</h1><p>Workflow notices created from admin actions. There are also <?php echo $pendingRequestCount; ?> student service requests awaiting staff review.</p></div><div class="admin-actions"><a class="admin-button-secondary" href="console.php?view=applications">Application queue</a><a class="admin-button-secondary" href="console.php?view=service_requests">Review service requests</a></div></div>
            <section class="admin-panel"><?php if (!$notices): ?><div class="admin-empty">No saved notifications yet. Application and card actions will add notifications here.</div><?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Notice</th><th>Type</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($notices as $notice): ?><tr><td><strong><?php echo adminEscape($notice['title']); ?></strong><br><?php echo adminEscape($notice['message']); ?><?php if ($notice['reference_type'] === 'application'): ?><br><a href="console.php?view=applications">Open applications</a><?php elseif ($notice['reference_type'] === 'service_request'): ?><br><a href="console.php?view=service_requests">Open service requests</a><?php endif; ?></td><td><?php echo adminBadge((string)$notice['notification_type']); ?></td><td><?php echo adminEscape((string)$notice['created_at']); ?></td><td><?php echo $notice['read_at'] ? 'Read' : '<span class="admin-badge warning">Unread</span>'; ?></td><td><?php if (!$notice['read_at']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?php echo (int)$notice['id']; ?>"><button class="admin-button-secondary" type="submit">Mark read</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<?php elseif ($view === 'audit'): ?>
            <?php $auditRows = $conn->query('SELECT id, user_id, user_role, action, details, ip_address, created_at FROM system_audit_log ORDER BY created_at DESC, id DESC LIMIT 250')->fetch_all(MYSQLI_ASSOC); ?>
            <div class="admin-page-heading"><div><h1>Audit Logs</h1><p>Recent changes made through protected system workflows.</p></div></div>
            <section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead><tbody><?php foreach ($auditRows as $row): ?><tr><td><?php echo adminEscape((string)$row['created_at']); ?></td><td><?php echo adminEscape($row['user_role'] . ' #' . $row['user_id']); ?></td><td><?php echo adminBadge((string)$row['action']); ?></td><td><?php echo adminEscape((string)$row['details']); ?></td><td><?php echo adminEscape((string)$row['ip_address']); ?></td></tr><?php endforeach; ?><?php if (!$auditRows): ?><tr><td colspan="5" class="admin-empty">No audit entries have been recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>

<?php elseif ($view === 'monitor'): ?>
            <?php
            $dbStatus = $conn->ping() ? 'Connected' : 'Unavailable';
            $tableCount = (int)$conn->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetch_assoc()['total'];
            $dbName = (string)$conn->query('SELECT DATABASE() AS db_name')->fetch_assoc()['db_name'];
            $dbVersion = (string)$conn->query('SELECT VERSION() AS version')->fetch_assoc()['version'];
            $diskFree = @disk_free_space(dirname(__DIR__));
            ?>
            <div class="admin-page-heading"><div><h1>System Monitor</h1><p>Live runtime and database health values from this XAMPP instance.</p></div></div>
            <section class="admin-stats"><div class="admin-stat"><span class="admin-stat-icon green"><i class="fas fa-database"></i></span><span><strong><?php echo adminEscape($dbStatus); ?></strong><small>MySQL connection</small></span></div><div class="admin-stat"><span class="admin-stat-icon"><i class="fas fa-table"></i></span><span><strong><?php echo $tableCount; ?></strong><small>Database tables</small></span></div><div class="admin-stat"><span class="admin-stat-icon purple"><i class="fas fa-code"></i></span><span><strong><?php echo adminEscape(PHP_VERSION); ?></strong><small>PHP runtime</small></span></div><div class="admin-stat"><span class="admin-stat-icon orange"><i class="fas fa-hard-drive"></i></span><span><strong><?php echo $diskFree === false ? 'Unavailable' : number_format($diskFree / 1024 / 1024 / 1024, 1) . ' GB'; ?></strong><small>Free disk space</small></span></div></section>
            <section class="admin-panel"><h2>Environment</h2><div class="admin-table-wrap"><table class="admin-table"><tbody><tr><th>Database</th><td><?php echo adminEscape($dbName); ?></td></tr><tr><th>MySQL version</th><td><?php echo adminEscape($dbVersion); ?></td></tr><tr><th>Server</th><td><?php echo adminEscape((string)($_SERVER['SERVER_SOFTWARE'] ?? 'Local web server')); ?></td></tr><tr><th>Application</th><td>Student ID Management System</td></tr><tr><th>System time</th><td><?php echo date(DATE_ATOM); ?></td></tr></tbody></table></div></section>

<?php elseif ($view === 'backup'): ?>
            <?php $backupTables = ['students', 'student_registry', 'staff_accounts', 'student_faculties', 'student_courses', 'system_settings', 'admin_announcements', 'id_applications', 'payments', 'id_cards', 'application_events', 'admin_notifications', 'system_audit_log', 'activity_logs', 'student_service_requests']; ?>
            <div class="admin-page-heading"><div><h1>Backup &amp; Restore</h1><p>Download an application-data snapshot or merge a compatible snapshot into an empty/new installation.</p></div></div>
            <section class="admin-grid">
                <div class="admin-panel admin-span-6"><h2>Create a Backup</h2><p>Downloads the supported application tables as JSON. The file contains private student records and password hashes; keep it securely.</p><a class="admin-button" href="console.php?view=backup_download">Download Database Backup</a></div>
                <div class="admin-panel admin-span-6"><h2>Restore / Merge Backup</h2><p>Restore is insert-only: existing records are never replaced or deleted. If any primary or unique key conflicts, the entire restore rolls back. Restore into an empty, schema-compatible database for a full recovery.</p><form method="post" enctype="multipart/form-data" onsubmit="return confirm('Import this backup as new rows only? Existing records will not be overwritten.');"><input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>"><input type="hidden" name="action" value="restore_backup"><div class="admin-field"><label for="backup_file">Backup JSON (max 20 MB)</label><input id="backup_file" type="file" name="backup_file" accept=".json,application/json" required></div><button class="admin-button-secondary" type="submit" style="margin-top:10px">Restore without overwriting</button></form></div>
                <div class="admin-panel admin-span-12"><h2>Included tables</h2><p><?php echo adminEscape(implode(', ', $backupTables)); ?>. Restore requires compatible tables and does not change application files or uploaded documents.</p></div>
            </section>

<?php endif; ?>
<?php adminShellEnd(); ?>
