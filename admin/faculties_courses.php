<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/layout.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if (($_SESSION['logged_in'] ?? false) !== true || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(403);
    exit('Administrator account could not be verified.');
}

if (($_GET['document'] ?? '') === 'registrar-signature') {
    $signatureRow = $conn->query(
        "SELECT setting_value FROM system_settings
         WHERE setting_key = 'academic_registrar_signature_path' LIMIT 1"
    )->fetch_assoc();
    $storageRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private');
    $storedPath = (string)($signatureRow['setting_value'] ?? '');
    $resolvedPath = $storageRoot && $storedPath !== ''
        ? realpath($storageRoot . DIRECTORY_SEPARATOR . $storedPath)
        : false;
    if (!$resolvedPath || !$storageRoot || strpos($resolvedPath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($resolvedPath)) {
        http_response_code(404);
        exit('Signature image not found.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($resolvedPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(415);
        exit('Signature image type is not supported.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($resolvedPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="registrar-signature"');
    readfile($resolvedPath);
    exit;
}

if (!isset($_SESSION['catalog_csrf']) || !is_string($_SESSION['catalog_csrf']) || strlen($_SESSION['catalog_csrf']) < 32) {
    $_SESSION['catalog_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['catalog_csrf'];
$flash = $_SESSION['catalog_flash'] ?? null;
unset($_SESSION['catalog_flash']);
$errors = [];

function catalogRedirect(): never
{
    header('Location: console.php?view=catalog');
    exit;
}

function catalogSaveImageUpload(int $adminId): string
{
    $file = $_FILES['registrar_signature'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload a registrar signature image.');
    }
    if ((int)$file['size'] < 1 || (int)$file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('The signature image must be no larger than 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Use a valid PNG, JPEG or WebP signature image.');
    }
    $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private'
        . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'signatures';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private signature storage is unavailable. Contact the server administrator.');
    }
    $filename = 'registrar-' . bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The signature image could not be saved.');
    }
    return 'system' . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'The page session expired. Reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_faculty') {
                $name = trim((string)($_POST['faculty_name'] ?? ''));
                if ($name === '' || strlen($name) > 150) {
                    throw new RuntimeException('Enter a faculty name of up to 150 characters.');
                }
                $stmt = $conn->prepare('INSERT INTO student_faculties (name, created_by) VALUES (?, ?)');
                $stmt->bind_param('si', $name, $adminId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['catalog_flash'] = 'Faculty added.';
                catalogRedirect();
            }

            if ($action === 'add_course') {
                $facultyId = filter_var($_POST['faculty_id'] ?? null, FILTER_VALIDATE_INT);
                $name = trim((string)($_POST['course_name'] ?? ''));
                if (!$facultyId || $name === '' || strlen($name) > 150) {
                    throw new RuntimeException('Choose a faculty and enter a course name of up to 150 characters.');
                }
                $facultyStmt = $conn->prepare("SELECT id FROM student_faculties WHERE id = ? AND status = 'active'");
                $facultyStmt->bind_param('i', $facultyId);
                $facultyStmt->execute();
                $facultyFound = $facultyStmt->get_result()->fetch_assoc();
                $facultyStmt->close();
                if (!$facultyFound) {
                    throw new RuntimeException('The selected faculty is not active.');
                }
                $stmt = $conn->prepare('INSERT INTO student_courses (faculty_id, name, created_by) VALUES (?, ?, ?)');
                $stmt->bind_param('isi', $facultyId, $name, $adminId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['catalog_flash'] = 'Course added.';
                catalogRedirect();
            }

            if ($action === 'toggle_catalog_status') {
                $kind = (string)($_POST['catalog_kind'] ?? '');
                $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
                $newStatus = (string)($_POST['new_status'] ?? '');
                if (!$recordId || !in_array($newStatus, ['active', 'inactive'], true)) {
                    throw new RuntimeException('Invalid catalog status change.');
                }
                if ($kind === 'faculty') {
                    $stmt = $conn->prepare('UPDATE student_faculties SET status = ? WHERE id = ?');
                } elseif ($kind === 'course') {
                    $stmt = $conn->prepare('UPDATE student_courses SET status = ? WHERE id = ?');
                } else {
                    throw new RuntimeException('Invalid catalog item.');
                }
                $stmt->bind_param('si', $newStatus, $recordId);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new RuntimeException('The catalog item was not changed. Reload and check its current status.');
                }
                $stmt->close();
                $_SESSION['catalog_flash'] = ucfirst($kind) . ' status updated.';
                catalogRedirect();
            }

            if ($action === 'upload_registrar_signature') {
                $newPath = catalogSaveImageUpload($adminId);
                $settingKey = 'academic_registrar_signature_path';
                $stmt = $conn->prepare(
                    'INSERT INTO system_settings (setting_key, setting_value, updated_by)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
                );
                $stmt->bind_param('ssi', $settingKey, $newPath, $adminId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['catalog_flash'] = 'Academic Registrar signature updated.';
                catalogRedirect();
            }

            throw new RuntimeException('That action is not available.');
        } catch (mysqli_sql_exception $exception) {
            error_log('Student catalog update failed: ' . $exception->getMessage());
            if ((int)$exception->getCode() === 1062) {
                $errors[] = 'That faculty or course already exists.';
            } else {
                $errors[] = 'The change could not be saved. Check the database and try again.';
            }
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$facultyResult = $conn->query('SELECT id, name, status FROM student_faculties ORDER BY name');
$faculties = $facultyResult->fetch_all(MYSQLI_ASSOC);
$courseResult = $conn->query(
    'SELECT c.id, c.name, c.status, f.name AS faculty_name
     FROM student_courses c INNER JOIN student_faculties f ON f.id = c.faculty_id
     ORDER BY f.name, c.name'
);
$courses = $courseResult->fetch_all(MYSQLI_ASSOC);
$signatureResult = $conn->query(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'academic_registrar_signature_path' LIMIT 1"
)->fetch_assoc();
$signaturePath = (string)($signatureResult['setting_value'] ?? '');
?>
<?php adminShellStart($conn, 'Card Catalog & Signature', 'catalog'); ?>
    <div class="catalog-layout">
    <section class="student-panel-card">
        <h1>Student ID card catalog</h1>
        <p>Active faculties and courses appear in student application dropdowns. Items are deactivated, not deleted, so historic card records remain intact.</p>
    </section>
    <?php if ($flash): ?><div class="student-notice success"><?php echo htmlspecialchars((string)$flash, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="student-notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?>

        <section class="student-panel-card">
            <h2>Faculties</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add_faculty">
                <div class="student-field"><label for="faculty_name">New faculty name</label><input id="faculty_name" name="faculty_name" maxlength="150" required></div>
                <button class="student-button" type="submit">Add faculty</button>
            </form>
            <?php foreach ($faculties as $faculty): ?>
                <div class="catalog-item">
                    <span><?php echo htmlspecialchars($faculty['name'], ENT_QUOTES, 'UTF-8'); ?> <span class="student-status"><?php echo htmlspecialchars($faculty['status'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="toggle_catalog_status">
                        <input type="hidden" name="catalog_kind" value="faculty">
                        <input type="hidden" name="record_id" value="<?php echo (int)$faculty['id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $faculty['status'] === 'active' ? 'inactive' : 'active'; ?>">
                        <button class="student-button-secondary" type="submit"><?php echo $faculty['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="student-panel-card">
            <h2>Courses</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add_course">
                <div class="student-field"><label for="course_faculty">Faculty</label>
                    <select id="course_faculty" name="faculty_id" required>
                        <option value="">Select faculty</option>
                        <?php foreach ($faculties as $faculty): ?>
                            <?php if ($faculty['status'] === 'active'): ?>
                                <option value="<?php echo (int)$faculty['id']; ?>"><?php echo htmlspecialchars($faculty['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="student-field"><label for="course_name">New course / programme</label><input id="course_name" name="course_name" maxlength="150" required></div>
                <button class="student-button" type="submit">Add course</button>
            </form>
            <?php foreach ($courses as $course): ?>
                <div class="catalog-item">
                    <span><?php echo htmlspecialchars($course['name'] . ' · ' . $course['faculty_name'], ENT_QUOTES, 'UTF-8'); ?> <span class="student-status"><?php echo htmlspecialchars($course['status'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="toggle_catalog_status">
                        <input type="hidden" name="catalog_kind" value="course">
                        <input type="hidden" name="record_id" value="<?php echo (int)$course['id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $course['status'] === 'active' ? 'inactive' : 'active'; ?>">
                        <button class="student-button-secondary" type="submit"><?php echo $course['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <section class="student-panel-card">
        <h2>Academic Registrar signature on ID cards</h2>
        <p>Upload an authorized transparent PNG signature when possible. This image is stored privately and shown only on student ID cards.</p>
        <?php if ($signaturePath !== ''): ?>
            <img class="catalog-signature-preview" src="../admin_faculties_courses.php?document=registrar-signature" alt="Current Academic Registrar signature">
        <?php else: ?>
            <p>No registrar signature has been uploaded yet.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="upload_registrar_signature">
            <div class="student-field"><label for="registrar_signature">Signature image (PNG/JPEG/WebP, max 2 MB)</label><input id="registrar_signature" type="file" name="registrar_signature" accept="image/png,image/jpeg,image/webp" required></div>
            <button class="student-button" type="submit">Upload signature</button>
        </form>
    </section>
<?php adminShellEnd(); ?>
