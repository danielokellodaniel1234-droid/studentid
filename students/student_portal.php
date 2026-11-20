<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/qr_code.php';
require_once dirname(__DIR__) . '/shared/student_card_barcode.php';
require_once dirname(__DIR__) . '/shared/student_card_code_settings.php';
require_once dirname(__DIR__) . '/shared/student_card_front_row_settings.php';
require_once dirname(__DIR__) . '/shared/student_card_front_branding.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$cardCodeSettings = studentCardCodeSettings($conn);
$frontRowSettings = studentCardFrontRowSettings($conn);
$frontBranding = studentCardFrontBrandingSettings($conn);

if (($_SESSION['logged_in'] ?? false) !== true) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user_type'] ?? '') !== 'student') {
    http_response_code(403);
    exit('Student access required.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(403);
    exit('Student account could not be verified.');
}

$studentStmt = $conn->prepare(
    'SELECT id, student_id, full_name, gender, email, phone, program, password, registration_date
     FROM students WHERE id = ? AND status = ? LIMIT 1'
);
$activeStatus = 'active';
$studentStmt->bind_param('is', $userId, $activeStatus);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();
if (!$student) {
    http_response_code(403);
    exit('Student account could not be verified.');
}

$full_name = $student['full_name'];
$student_id = $student['student_id'];
$activeView = (string)($_GET['view'] ?? 'track');
$servicePages = [
    'course_registration' => ['Course Registration', 'Send the academic office a request about registering or changing your courses. This portal does not register courses in the university academic system.'],
    'fee_payment' => ['Fee Payment', 'This page does not collect money or confirm a payment. Contact the finance office for official payment instructions, then submit an inquiry here if you need help.'],
    'library' => ['Library Access', 'Request help with your student library access. This does not create a library account or issue books.'],
    'hostel' => ['Hostel Booking', 'Send a request to the relevant university office. This portal does not make or confirm a hostel reservation.'],
    'exam_results' => ['Exam Results', 'Request guidance from the academic office. Results are not available in this portal unless the university connects an official results service.'],
    'calendar' => ['Academic Calendar', 'No official calendar feed is connected to this portal. Submit a request for the current approved academic calendar.'],
    'help' => ['Help & Support', 'Describe your question or problem and the student-support team can follow up.'],
    'correction' => ['Request an ID correction', 'Describe the information that needs correction. Staff will review the request; this form does not change an issued card automatically.'],
];
$flash = $_SESSION['student_portal_flash'] ?? null;
unset($_SESSION['student_portal_flash']);
$errors = [];
$applicationIntakeStmt = $conn->prepare(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'student_applications_open' LIMIT 1"
);
$applicationIntakeStmt->execute();
$applicationIntakeSetting = $applicationIntakeStmt->get_result()->fetch_assoc();
$applicationIntakeStmt->close();
$applicationsOpen = !isset($applicationIntakeSetting['setting_value']) || $applicationIntakeSetting['setting_value'] === '1';
$csrfToken = $_SESSION['student_portal_csrf'] ?? '';
if (!is_string($csrfToken) || strlen($csrfToken) < 32) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['student_portal_csrf'] = $csrfToken;
}

function studentPortalRedirect(string $view): never
{
    header('Location: student_portal.php?view=' . rawurlencode($view));
    exit;
}

function studentPortalStoreUpload(
    string $field,
    int $userId,
    bool $imageOnly = true,
    bool $studentPhoto = false,
    bool $pngOnly = false
): string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        throw new RuntimeException('Please select the required file.');
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The file upload did not complete. Please select it again.');
    }
    $maxBytes = $studentPhoto ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
    if ((int)$file['size'] < 1 || (int)$file['size'] > $maxBytes) {
        throw new RuntimeException($studentPhoto
            ? 'The student photo must be no larger than 20 MB.'
            : 'Each file must be no larger than 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $pngOnly = $studentPhoto || $pngOnly;
    $allowed = $pngOnly
        ? ['image/png' => 'png']
        : ($imageOnly
            ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
            : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf']);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException($pngOnly
            ? 'Use a PNG image for this upload. JPEG and other image formats are not accepted.'
            : ($imageOnly
            ? 'Use a JPEG, PNG or WebP image file.'
            : 'Use a JPEG, PNG, WebP or PDF receipt.'));
    }
    $imageInfo = $imageOnly ? @getimagesize($file['tmp_name']) : false;
    if ($imageOnly && $imageInfo === false) {
        throw new RuntimeException('The selected file is not a valid image.');
    }
    if ($pngOnly) {
        if (!is_array($imageInfo) || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG) {
            throw new RuntimeException('This upload must be a valid PNG image.');
        }
        if ($studentPhoto && ($imageInfo[0] < 600 || $imageInfo[1] < 750)) {
            throw new RuntimeException('The student photo must be at least 600×750 pixels.');
        }

        $pngBytes = file_get_contents($file['tmp_name']);
        if (!is_string($pngBytes) || strlen($pngBytes) < 33 || substr($pngBytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException('The selected PNG could not be read.');
        }
        $colorType = ord($pngBytes[25]);
        $declaresTransparency = in_array($colorType, [4, 6], true);
        $chunkOffset = 8;
        $pngLength = strlen($pngBytes);
        while ($chunkOffset + 12 <= $pngLength) {
            $chunkLength = unpack('Nlength', substr($pngBytes, $chunkOffset, 4))['length'];
            $chunkType = substr($pngBytes, $chunkOffset + 4, 4);
            if ($chunkType === 'tRNS') {
                $declaresTransparency = true;
            }
            $chunkOffset += 12 + $chunkLength;
            if ($chunkType === 'IEND' || $chunkOffset > $pngLength) {
                break;
            }
        }
        if (!$declaresTransparency) {
            throw new RuntimeException('The PNG must include transparency data (an alpha channel or transparency table).');
        }
        unset($pngBytes);
    }
    $relativeDirectory = 'students' . DIRECTORY_SEPARATOR . $userId;
    $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private' . DIRECTORY_SEPARATOR . $relativeDirectory;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private upload storage is unavailable. Contact the administrator.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The uploaded file could not be saved.');
    }
    return $relativeDirectory . DIRECTORY_SEPARATOR . $filename;
}

function studentPortalStoreServiceRequest(mysqli $conn, int $userId, string $service, string $subject, string $details): void
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO student_service_requests (student_id, service_key, subject, details)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isss', $userId, $service, $subject, $details);
        $stmt->execute();
        $requestId = (int)$conn->insert_id;
        $stmt->close();

        $notificationTitle = 'New student service request';
        $notificationMessage = 'A student submitted: ' . $subject;
        $notificationType = 'service_request';
        $referenceType = 'service_request';
        $notificationStmt = $conn->prepare(
            'INSERT INTO admin_notifications
                (recipient_role, title, message, notification_type, reference_type, reference_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (['admin', 'staff'] as $recipientRole) {
            $notificationStmt->bind_param(
                'sssssi',
                $recipientRole,
                $notificationTitle,
                $notificationMessage,
                $notificationType,
                $referenceType,
                $requestId
            );
            $notificationStmt->execute();
        }
        $notificationStmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'submit_application') {
                if (!$applicationsOpen) {
                    throw new RuntimeException('Student ID applications are temporarily paused. Please check again later.');
                }
                $applicationType = (string)($_POST['application_type'] ?? '');
                $allowedTypes = ['new', 'renewal', 'lost', 'damaged'];
                if (!in_array($applicationType, $allowedTypes, true)) {
                    throw new RuntimeException('Choose a valid application type.');
                }
                $surname = trim((string)($_POST['surname'] ?? ''));
                $givenName = trim((string)($_POST['given_name'] ?? ''));
                $nationality = strtoupper(trim((string)($_POST['nationality'] ?? '')));
                $sex = (string)($_POST['sex'] ?? '');
                $cardRegNo = trim((string)($_POST['card_reg_no'] ?? ''));
                $dateAdmitted = trim((string)($_POST['date_admitted'] ?? ''));
                $facultyId = filter_var($_POST['faculty_id'] ?? null, FILTER_VALIDATE_INT);
                $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
                $graduationYear = filter_var($_POST['graduation_year'] ?? null, FILTER_VALIDATE_INT);
                $currentYear = (int)date('Y');
                $admissionDateParts = explode('-', $dateAdmitted);
                $validAdmissionDate = count($admissionDateParts) === 3
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAdmitted)
                    && checkdate((int)$admissionDateParts[1], (int)$admissionDateParts[2], (int)$admissionDateParts[0]);
                if ($surname === '' || strlen($surname) > 100 || $givenName === '' || strlen($givenName) > 150
                    || $nationality === '' || strlen($nationality) > 80
                    || !in_array($sex, ['Male', 'Female'], true)
                    || $cardRegNo === '' || strlen($cardRegNo) > 80
                    || !$validAdmissionDate
                    || !$facultyId || !$courseId
                    || !$graduationYear || $graduationYear < $currentYear || $graduationYear > $currentYear + 12) {
                    throw new RuntimeException('Enter valid identity and study details, including the date you were admitted and a registration number of up to 80 characters.');
                }
                $catalogStmt = $conn->prepare(
                    "SELECT c.id FROM student_courses c
                     INNER JOIN student_faculties f ON f.id = c.faculty_id
                     WHERE c.id = ? AND c.faculty_id = ? AND c.status = 'active' AND f.status = 'active'"
                );
                $catalogStmt->bind_param('ii', $courseId, $facultyId);
                $catalogStmt->execute();
                $validCourse = $catalogStmt->get_result()->fetch_assoc();
                $catalogStmt->close();
                if (!$validCourse) {
                    throw new RuntimeException('Choose an active course that belongs to the selected faculty.');
                }
                $reason = trim((string)($_POST['reason'] ?? ''));
                if ($applicationType !== 'new' && strlen($reason) < 8) {
                    throw new RuntimeException('Please provide a brief reason for this request.');
                }
                $openStmt = $conn->prepare(
                    "SELECT id FROM id_applications
                     WHERE student_id = ? AND application_status IN ('submitted','under_review','approved','ready_to_print','printed')
                     LIMIT 1"
                );
                $openStmt->bind_param('i', $userId);
                $openStmt->execute();
                $hasOpen = $openStmt->get_result()->fetch_assoc() !== null;
                $openStmt->close();
                if ($hasOpen) {
                    throw new RuntimeException('You already have an application being processed. Track it before submitting another.');
                }

                $issuedStmt = $conn->prepare(
                    "SELECT id FROM id_cards WHERE student_id = ? AND card_status = 'issued' LIMIT 1"
                );
                $issuedStmt->bind_param('i', $userId);
                $issuedStmt->execute();
                $hasIssuedCard = $issuedStmt->get_result()->fetch_assoc() !== null;
                $issuedStmt->close();
                if ($applicationType === 'new' && $hasIssuedCard) {
                    throw new RuntimeException('You already have an issued ID card. Choose renewal, lost or damaged instead.');
                }
                if ($applicationType !== 'new' && !$hasIssuedCard) {
                    throw new RuntimeException('A renewal or replacement requires an issued student ID card.');
                }

                $sourcePhotoPath = studentPortalStoreUpload('camera_photo', $userId, true, true);
                $photoPath = $sourcePhotoPath;
                try {
                    $signaturePath = studentPortalStoreUpload('signature', $userId, true, false, true);
                } catch (Throwable $exception) {
                    $storageRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private' . DIRECTORY_SEPARATOR;
                    @unlink($storageRoot . $sourcePhotoPath);
                    throw $exception;
                }
                try {
                    $conn->begin_transaction();
                    $applicationStmt = $conn->prepare(
                        'INSERT INTO id_applications (
                            student_id, application_type, reason, surname, given_name, nationality, sex,
                            card_reg_no, faculty_id, course_id, graduation_year, source_photo_path, photo_path, signature_path,
                            date_admitted
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $applicationStmt->bind_param(
                        'isssssssiiissss',
                        $userId,
                        $applicationType,
                        $reason,
                        $surname,
                        $givenName,
                        $nationality,
                        $sex,
                        $cardRegNo,
                        $facultyId,
                        $courseId,
                        $graduationYear,
                        $sourcePhotoPath,
                        $photoPath,
                        $signaturePath,
                        $dateAdmitted
                    );
                    $applicationStmt->execute();
                    $applicationId = (int)$conn->insert_id;
                    $applicationStmt->close();
                    if ($applicationType !== 'new') {
                        $paymentStmt = $conn->prepare('INSERT INTO payments (application_id) VALUES (?)');
                        $paymentStmt->bind_param('i', $applicationId);
                        $paymentStmt->execute();
                        $paymentStmt->close();
                    }
                    $conn->commit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $storageRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private' . DIRECTORY_SEPARATOR;
                    @unlink($storageRoot . $sourcePhotoPath);
                    @unlink($storageRoot . $signaturePath);
                    throw $exception;
                }
                $_SESSION['student_portal_flash'] = ['type' => 'success', 'message' => 'Your application was submitted. You can follow its progress on the tracking page.'];
                studentPortalRedirect('track');
            }

            if ($action === 'submit_payment') {
                $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
                $reference = trim((string)($_POST['reference'] ?? ''));
                $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                if (!$applicationId || $reference === '' || strlen($reference) > 120 || !$amount || $amount <= 0) {
                    throw new RuntimeException('Enter a valid payment reference and positive amount.');
                }
                $applicationStmt = $conn->prepare(
                    "SELECT a.id, p.payment_status FROM id_applications a
                     LEFT JOIN payments p ON p.application_id = a.id
                     WHERE a.id = ? AND a.student_id = ? AND a.application_type IN ('renewal','lost','damaged')"
                );
                $applicationStmt->bind_param('ii', $applicationId, $userId);
                $applicationStmt->execute();
                $ownedApplication = $applicationStmt->get_result()->fetch_assoc();
                $applicationStmt->close();
                if (!$ownedApplication) {
                    throw new RuntimeException('That application is not available for payment evidence.');
                }
                if ($ownedApplication['payment_status'] === 'verified') {
                    throw new RuntimeException('Payment for this application has already been verified.');
                }
                $receiptPath = studentPortalStoreUpload('receipt', $userId, false);
                try {
                    if ($ownedApplication['payment_status'] === null) {
                        $paymentStmt = $conn->prepare(
                            "INSERT INTO payments (application_id, reference, amount, receipt_path, payment_status)
                             VALUES (?, ?, ?, ?, 'pending')"
                        );
                        $paymentStmt->bind_param('isds', $applicationId, $reference, $amount, $receiptPath);
                        $paymentStmt->execute();
                        $paymentStmt->close();
                    } else {
                        $paymentStmt = $conn->prepare(
                            "UPDATE payments SET reference = ?, amount = ?, receipt_path = ?, payment_status = 'pending',
                                verified_by = NULL, verified_at = NULL
                             WHERE application_id = ? AND payment_status <> 'verified'"
                        );
                        $paymentStmt->bind_param('sdsi', $reference, $amount, $receiptPath, $applicationId);
                        $paymentStmt->execute();
                        if ($paymentStmt->affected_rows !== 1) {
                            $paymentStmt->close();
                            throw new RuntimeException('Payment has already been verified or changed. Reload the tracking page.');
                        }
                        $paymentStmt->close();
                    }
                } catch (Throwable $exception) {
                    @unlink(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private' . DIRECTORY_SEPARATOR . $receiptPath);
                    throw $exception;
                }
                $_SESSION['student_portal_flash'] = ['type' => 'success', 'message' => 'Your payment evidence was submitted for administrator verification. This is not a payment confirmation.'];
                studentPortalRedirect('track');
            }

            if ($action === 'update_profile') {
                $name = trim((string)($_POST['full_name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $program = trim((string)($_POST['program'] ?? ''));
                $gender = trim((string)($_POST['gender'] ?? ''));
                if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                    || strlen($email) > 190 || strlen($phone) > 50 || strlen($program) > 150
                    || !in_array($gender, ['', 'Female', 'Male', 'Other'], true)) {
                    throw new RuntimeException('Check your name, email and other profile information.');
                }
                $updateStmt = $conn->prepare(
                    'UPDATE students SET full_name = ?, email = ?, phone = ?, program = ?, gender = ? WHERE id = ?'
                );
                $updateStmt->bind_param('sssssi', $name, $email, $phone, $program, $gender, $userId);
                $updateStmt->execute();
                $updateStmt->close();
                $_SESSION['full_name'] = $name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                $_SESSION['program'] = $program;
                $_SESSION['student_portal_flash'] = ['type' => 'success', 'message' => 'Your profile was updated.'];
                studentPortalRedirect('profile');
            }

            if ($action === 'change_password') {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');
                if (!password_verify($currentPassword, $student['password'])) {
                    throw new RuntimeException('The current password is incorrect.');
                }
                if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
                    throw new RuntimeException('The new password must be at least 8 characters and both entries must match.');
                }
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordStmt = $conn->prepare('UPDATE students SET password = ? WHERE id = ?');
                $passwordStmt->bind_param('si', $passwordHash, $userId);
                $passwordStmt->execute();
                $passwordStmt->close();
                $_SESSION['student_portal_flash'] = ['type' => 'success', 'message' => 'Your password was changed.'];
                studentPortalRedirect('settings');
            }

            if ($action === 'submit_service_request') {
                $serviceKey = (string)($_POST['service_key'] ?? '');
                if (!isset($servicePages[$serviceKey])) {
                    throw new RuntimeException('Choose a valid service.');
                }
                $subject = trim((string)($_POST['subject'] ?? ''));
                $details = trim((string)($_POST['details'] ?? ''));
                if ($subject === '' || strlen($subject) > 180 || strlen($details) < 10 || strlen($details) > 5000) {
                    throw new RuntimeException('Enter a subject and at least 10 characters of detail (up to 5,000 characters).');
                }
                studentPortalStoreServiceRequest($conn, $userId, $serviceKey, $subject, $details);
                $_SESSION['student_portal_flash'] = ['type' => 'success', 'message' => 'Your request was sent to the student-services queue. This does not complete the underlying university service.'];
                studentPortalRedirect($serviceKey);
            }

            throw new RuntimeException('That action is not available.');
        } catch (Throwable $exception) {
            if ($exception instanceof mysqli_sql_exception || !($exception instanceof RuntimeException)) {
                error_log('Student portal action failed: ' . $exception->getMessage());
                $errors[] = 'The request could not be saved. Please try again or contact the administrator.';
            } else {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$view = $activeView;
$allowedViews = [
    'track', 'notifications', 'apply', 'replacement', 'card', 'qr', 'profile', 'settings',
    'correction', 'help', 'course_registration', 'fee_payment', 'library',
    'hostel', 'exam_results', 'calendar', 'document',
];
if (!in_array($view, $allowedViews, true)) {
    $view = 'track';
    $activeView = $view;
}

if ($view === 'notifications') {
    $rejectionNoticeStmt = $conn->prepare(
        "SELECT e.application_id, e.details, e.created_at
         FROM application_events e
         INNER JOIN id_applications a ON a.id = e.application_id
         WHERE a.student_id = ? AND e.action = 'rejected'
         ORDER BY e.created_at DESC, e.id DESC LIMIT 100"
    );
    $rejectionNoticeStmt->bind_param('i', $userId);
    $rejectionNoticeStmt->execute();
    $rejectionNotices = $rejectionNoticeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rejectionNoticeStmt->close();
} else {
    $rejectionNotices = [];
}

if ($view === 'document') {
    $applicationId = filter_var($_GET['application_id'] ?? null, FILTER_VALIDATE_INT);
    $kind = (string)($_GET['kind'] ?? '');
    if ($kind === 'registrar') {
        $issuedStmt = $conn->prepare(
            "SELECT id FROM id_cards WHERE student_id = ? AND card_status = 'issued' LIMIT 1"
        );
        $issuedStmt->bind_param('i', $userId);
        $issuedStmt->execute();
        $mayViewSignature = $issuedStmt->get_result()->fetch_assoc() !== null;
        $issuedStmt->close();
        if (!$mayViewSignature) {
            http_response_code(404);
            exit('Document not found.');
        }
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
            exit('Document not found.');
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
    $column = ['photo' => 'photo_path', 'signature' => 'signature_path', 'receipt' => 'receipt_path'][$kind] ?? null;
    if (!$applicationId || !$column) {
        http_response_code(404);
        exit('Document not found.');
    }
    $documentSql = $kind === 'receipt'
        ? 'SELECT p.receipt_path AS stored_path FROM payments p
           INNER JOIN id_applications a ON a.id = p.application_id
           WHERE a.id = ? AND a.student_id = ?'
        : "SELECT a.`$column` AS stored_path FROM id_applications a WHERE a.id = ? AND a.student_id = ?";
    $documentStmt = $conn->prepare($documentSql);
    $documentStmt->bind_param('ii', $applicationId, $userId);
    $documentStmt->execute();
    $document = $documentStmt->get_result()->fetch_assoc();
    $documentStmt->close();
    $storageRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private');
    $storedPath = (string)($document['stored_path'] ?? '');
    $resolvedPath = $storageRoot && $storedPath !== ''
        ? realpath($storageRoot . DIRECTORY_SEPARATOR . $storedPath)
        : false;
    if (!$resolvedPath || !$storageRoot || strpos($resolvedPath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($resolvedPath)) {
        http_response_code(404);
        exit('Document not found.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($resolvedPath);
    $permittedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array($mime, $permittedMimes, true)) {
        http_response_code(415);
        exit('Document type is not supported.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($resolvedPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="student-document.' . ($mime === 'application/pdf' ? 'pdf' : 'image') . '"');
    readfile($resolvedPath);
    exit;
}

$applicationsStmt = $conn->prepare(
    "SELECT a.id, a.application_type, a.reason, a.application_status, a.created_at, a.updated_at,
            a.surname, a.given_name, a.nationality, a.sex, a.card_reg_no, a.date_admitted, a.graduation_year,
            a.source_photo_path, a.photo_path, a.signature_path, f.name AS faculty_name, co.name AS course_name,
            p.reference, p.amount, p.receipt_path, p.payment_status,
            c.card_number, c.card_status, c.expires_at, c.issued_at, c.generated_at, c.verification_token,
            s.student_id AS student_code, s.full_name AS student_name,
            (SELECT e.details FROM application_events e
             WHERE e.application_id = a.id AND e.action = 'rejected'
             ORDER BY e.created_at DESC, e.id DESC LIMIT 1) AS rejection_details
     FROM id_applications a
     INNER JOIN students s ON s.id = a.student_id
     LEFT JOIN payments p ON p.application_id = a.id
     LEFT JOIN id_cards c ON c.application_id = a.id AND c.student_id = a.student_id
     LEFT JOIN student_faculties f ON f.id = a.faculty_id
     LEFT JOIN student_courses co ON co.id = a.course_id
     WHERE a.student_id = ? ORDER BY a.created_at DESC, a.id DESC"
);
$applicationsStmt->bind_param('i', $userId);
$applicationsStmt->execute();
$applications = $applicationsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$applicationsStmt->close();

$issuedCard = null;
foreach ($applications as $application) {
    if ($application['card_status'] === 'issued') {
        $issuedCard = $application;
        break;
    }
}
$codeValues = $issuedCard ? [
    'card_number' => (string)$issuedCard['card_number'],
    'student_id' => (string)$issuedCard['student_code'],
    'student_name' => (string)$issuedCard['student_name'],
    'verification_url' => strpos($cardCodeSettings['barcode_output_template'], '{verification_url}') !== false
        ? studentCardVerificationUrl((string)$issuedCard['verification_token'])
        : '',
] : [];
$qrPayload = $issuedCard ? studentCardCodePayload($cardCodeSettings['qr_output_template'], $codeValues, 'qr') : '';
$barcodePayload = $issuedCard ? studentCardCodePayload($cardCodeSettings['barcode_output_template'], $codeValues, 'barcode') : '';

$serviceRequestsStmt = $conn->prepare(
    'SELECT id, service_key, subject, request_status, staff_response, created_at
     FROM student_service_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 20'
);
$serviceRequestsStmt->bind_param('i', $userId);
$serviceRequestsStmt->execute();
$serviceRequests = $serviceRequestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$serviceRequestsStmt->close();
$registrarSignatureRow = $conn->query(
    "SELECT setting_value FROM system_settings
     WHERE setting_key = 'academic_registrar_signature_path' LIMIT 1"
)->fetch_assoc();
$registrarSignaturePath = (string)($registrarSignatureRow['setting_value'] ?? '');

$facultyOptions = $conn->query(
    "SELECT id, name FROM student_faculties WHERE status = 'active' ORDER BY name"
)->fetch_all(MYSQLI_ASSOC);
$courseOptions = $conn->query(
    "SELECT c.id, c.faculty_id, c.name FROM student_courses c
     INNER JOIN student_faculties f ON f.id = c.faculty_id
     WHERE c.status = 'active' AND f.status = 'active' ORDER BY c.name"
)->fetch_all(MYSQLI_ASSOC);
$cardFace = (string)($_GET['face'] ?? '');
$cardFaceSelected = in_array($cardFace, ['front', 'back', 'both'], true);
$cardAlignment = (string)($_GET['alignment'] ?? 'tray');
if (!in_array($cardAlignment, ['tray', 'center'], true)) {
    $cardAlignment = 'tray';
}
$studentNameParts = preg_split('/\s+/', trim((string)$student['full_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$defaultGivenName = count($studentNameParts) > 1 ? (string)array_pop($studentNameParts) : (string)$student['full_name'];
$defaultSurname = implode(' ', $studentNameParts);
$cardFormValues = [
    'surname' => $defaultSurname,
    'given_name' => $defaultGivenName,
    'nationality' => 'UGA',
    'sex' => '',
    'card_reg_no' => (string)$student['student_id'],
    'date_admitted' => '',
];
$postedCardValues = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_application';
if ($postedCardValues) {
    foreach (['surname', 'given_name', 'nationality', 'sex', 'card_reg_no', 'date_admitted'] as $field) {
        $cardFormValues[$field] = trim((string)($_POST[$field] ?? ''));
    }
}
$defaultSex = ucfirst(strtolower((string)($student['gender'] ?? '')));
$validSex = in_array($defaultSex, ['Male', 'Female'], true) ? $defaultSex : '';
if (!$postedCardValues && $validSex !== '') {
    $cardFormValues['sex'] = $validSex;
}

function studentPortalStatus(string $value): string
{
    return htmlspecialchars(ucfirst(str_replace('_', ' ', $value)), ENT_QUOTES, 'UTF-8');
}

function studentPortalApplicationStage(string $status): int
{
    return match ($status) {
        'approved', 'rejected' => 1,
        'ready_to_print' => 2,
        'printed' => 3,
        'issued' => 4,
        default => 0,
    };
}

function studentPortalDocumentExists(?string $relativePath): bool
{
    if (!$relativePath) {
        return false;
    }
    $root = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'STUDENTID-private');
    $path = $root ? realpath($root . DIRECTORY_SEPARATOR . $relativePath) : false;
    return $root !== false && $path !== false && strpos($path, $root . DIRECTORY_SEPARATOR) === 0 && is_file($path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Portal - Kumi University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/student-panel.css?v=20261120-signature-label-stability">
</head>
<body class="<?php echo $view === 'card' && $issuedCard && $cardFaceSelected ? 'print-id-card' : ''; ?>">
<?php require __DIR__ . '/student_header.php'; ?>
<main class="student-page">
    <?php if ($flash): ?>
        <div class="student-notice <?php echo htmlspecialchars($flash['type'] ?? 'success', ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="student-notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <?php if ($view === 'notifications'): ?>
        <section class="student-panel-card student-notifications" aria-labelledby="student-notifications-title">
            <h1 id="student-notifications-title"><i class="fas fa-bell" aria-hidden="true"></i> Notifications</h1>
            <p>Updates about your student ID applications appear here.</p>
            <?php if (!$rejectionNotices): ?>
                <div class="student-notice">You have no application notifications.</div>
            <?php else: ?>
                <?php foreach ($rejectionNotices as $notice): ?>
                    <?php
                    $reason = '';
                    $advice = '';
                    foreach (preg_split('/\R/', (string)$notice['details']) ?: [] as $detailLine) {
                        if (str_starts_with($detailLine, 'Reason: ')) {
                            $reason = substr($detailLine, strlen('Reason: '));
                        } elseif (str_starts_with($detailLine, 'Advice: ')) {
                            $advice = substr($detailLine, strlen('Advice: '));
                        }
                    }
                    ?>
                    <article class="student-notice error">
                        <strong>Application #<?php echo (int)$notice['application_id']; ?> was rejected</strong>
                        <?php if ($reason !== '' || $advice !== ''): ?>
                            <?php if ($reason !== ''): ?><p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')); ?></p><?php endif; ?>
                            <?php if ($advice !== ''): ?><p><strong>Advice:</strong> <?php echo nl2br(htmlspecialchars($advice, ENT_QUOTES, 'UTF-8')); ?></p><?php endif; ?>
                        <?php else: ?>
                            <p><?php echo nl2br(htmlspecialchars((string)$notice['details'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <small><?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($notice['created_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <a class="student-button-secondary" href="student_portal.php?view=track">Track applications</a>
        </section>

    <?php elseif ($view === 'track'): ?>
        <section class="student-panel-card">
            <h1>Track your ID applications</h1>
            <p>Application and payment statuses below come from the student ID records.</p>
            <?php if (!$applications): ?>
                <div class="student-notice">There are no applications yet. <a href="student_portal.php?view=apply&amp;type=new">Apply for an ID card</a>.</div>
            <?php else: ?>
                <div class="student-table-wrap">
                    <table class="student-table">
                        <thead><tr><th>Application</th><th>Status</th><th>Payment</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>#<?php echo (int)$application['id']; ?> · <?php echo htmlspecialchars(ucfirst($application['application_type']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($application['reason']): ?><br><small><?php echo htmlspecialchars($application['reason'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                                </td>
                                <td><span class="student-status"><?php echo studentPortalStatus($application['application_status']); ?></span>
                                    <?php if ($application['card_status'] === 'issued'): ?><br><a href="student_portal.php?view=card">View issued card</a><?php endif; ?>
                                    <?php if ($application['application_status'] === 'rejected' && $application['rejection_details']): ?>
                                        <div class="student-notice error application-rejection-details">
                                            <strong>Reason and advice</strong>
                                            <p><?php echo nl2br(htmlspecialchars((string)$application['rejection_details'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($application['application_type'] === 'new'): ?>
                                        Not required for first applications
                                    <?php else: ?>
                                        <span class="student-status"><?php echo studentPortalStatus($application['payment_status'] ?? 'pending'); ?></span>
                                        <?php if ($application['payment_status'] !== 'verified'): ?>
                                            <details style="margin-top:7px">
                                                <summary>Submit / update receipt</summary>
                                                <form method="post" enctype="multipart/form-data" style="min-width:220px;margin-top:8px">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="submit_payment">
                                                    <input type="hidden" name="application_id" value="<?php echo (int)$application['id']; ?>">
                                                    <div class="student-field"><label>Payment reference</label><input name="reference" maxlength="120" required></div>
                                                    <div class="student-field"><label>Amount reported</label><input type="number" name="amount" min="0.01" step="0.01" required></div>
                                                    <div class="student-field"><label>Receipt (image/PDF, max 5 MB)</label><input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                                                    <button class="student-button" type="submit">Send evidence for verification</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($application['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php
                            $applicationStage = studentPortalApplicationStage((string)$application['application_status']);
                            $applicationRejected = $application['application_status'] === 'rejected';
                            $applicationStages = ['Under review', 'Approved / Rejected', 'Ready for printing', 'Printed', 'Issued'];
                            ?>
                            <tr class="application-progress-row">
                                <td colspan="4">
                                    <ol class="application-progress" aria-label="Application progress">
                                        <?php foreach ($applicationStages as $stageIndex => $stageLabel): ?>
                                            <?php
                                            $stageClass = $stageIndex < $applicationStage
                                                ? 'is-complete'
                                                : ($stageIndex === $applicationStage ? 'is-current' : 'is-pending');
                                            if ($applicationRejected && $stageIndex === $applicationStage) {
                                                $stageClass .= ' is-rejected';
                                            }
                                            ?>
                                            <li class="<?php echo $stageClass; ?>" <?php echo $stageIndex === $applicationStage ? 'aria-current="step"' : ''; ?>>
                                                <span class="application-progress-dot" aria-hidden="true"></span>
                                                <span class="application-progress-label"><?php echo htmlspecialchars($stageLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if ($stageIndex === 1 && in_array($application['application_status'], ['approved', 'rejected'], true)): ?>
                                                    <span class="application-progress-result"><?php echo htmlspecialchars(ucfirst((string)$application['application_status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php if ($serviceRequests): ?>
            <section class="student-panel-card">
                <h2>Other requests</h2>
                <div class="student-table-wrap">
                    <table class="student-table">
                        <thead><tr><th>Request</th><th>Service</th><th>Status</th><th>Staff response</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($serviceRequests as $request): ?>
                            <tr>
                                <td>#<?php echo (int)$request['id']; ?> · <?php echo htmlspecialchars($request['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($servicePages[$request['service_key']][0] ?? $request['service_key'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="student-status"><?php echo studentPortalStatus($request['request_status']); ?></span></td>
                                <td><?php echo htmlspecialchars($request['staff_response'] ?: 'Awaiting review', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($view === 'apply' || $view === 'replacement'): ?>
        <?php
        $applicationType = $view === 'replacement'
            ? (string)($_GET['type'] ?? 'lost')
            : (string)($_GET['type'] ?? 'new');
        if (!in_array($applicationType, ['new', 'renewal', 'lost', 'damaged'], true)) {
            $applicationType = $view === 'replacement' ? 'lost' : 'new';
        }
        $activeView = $view === 'replacement' ? 'replacement' : 'apply';
        ?>
        <section class="student-panel-card">
            <h1><?php echo $view === 'replacement' ? 'Replace a lost or damaged ID' : 'Apply for a student ID'; ?></h1>
            <p>Complete or correct the card identity and study details. These identity fields, including the registration number, are saved with your application and printed on the card. Your expiry date is set to 31 December of your graduation year.</p>
            <?php if (!$applicationsOpen): ?><div class="student-notice error">Student ID applications are temporarily paused by the administrator.</div><?php endif; ?>
            <div class="student-notice">Upload a transparent PNG photo (at least 600 × 750 pixels, max 20 MB) and a transparent PNG signature (max 5 MB). JPEGs are not accepted.</div>
            <?php if ($view === 'replacement'): ?>
                <p><a class="student-button-secondary" href="student_portal.php?view=replacement&amp;type=lost">Lost card</a>
                    <a class="student-button-secondary" href="student_portal.php?view=replacement&amp;type=damaged">Damaged card</a></p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="submit_application">
                <input type="hidden" name="application_type" value="<?php echo htmlspecialchars($applicationType, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="student-field">
                    <label>Application type</label>
                    <select disabled><option><?php echo htmlspecialchars(ucfirst($applicationType), ENT_QUOTES, 'UTF-8'); ?></option></select>
                </div>
                <div class="student-fields">
                    <div class="student-field"><label for="surname">Surname</label><input id="surname" name="surname" maxlength="100" value="<?php echo htmlspecialchars($cardFormValues['surname'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="given_name">Given name(s)</label><input id="given_name" name="given_name" maxlength="150" value="<?php echo htmlspecialchars($cardFormValues['given_name'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="nationality">Nationality (country code/name)</label><input id="nationality" name="nationality" maxlength="80" value="<?php echo htmlspecialchars($cardFormValues['nationality'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="sex">Sex</label>
                        <select id="sex" name="sex" required>
                            <option value="">Select</option>
                            <?php foreach (['Male', 'Female'] as $sexOption): ?>
                                <option value="<?php echo $sexOption; ?>" <?php echo $cardFormValues['sex'] === $sexOption ? 'selected' : ''; ?>><?php echo $sexOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="student-field"><label for="card_reg_no">Registration number</label><input id="card_reg_no" name="card_reg_no" maxlength="80" value="<?php echo htmlspecialchars($cardFormValues['card_reg_no'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="date_admitted">Date of Issue</label><input id="date_admitted" type="date" name="date_admitted" value="<?php echo htmlspecialchars($cardFormValues['date_admitted'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="faculty_id">Faculty</label>
                        <select id="faculty_id" name="faculty_id" required>
                            <option value="">Select faculty</option>
                            <?php foreach ($facultyOptions as $faculty): ?>
                                <option value="<?php echo (int)$faculty['id']; ?>"><?php echo htmlspecialchars($faculty['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="student-field"><label for="course_id">Course / programme</label>
                        <select id="course_id" name="course_id" required disabled>
                            <option value="">Select a faculty first</option>
                            <?php foreach ($courseOptions as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" data-faculty="<?php echo (int)$course['faculty_id']; ?>"><?php echo htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="student-field"><label for="graduation_year">Expected graduation year</label>
                        <select id="graduation_year" name="graduation_year" required>
                            <option value="">Select year</option>
                            <?php for ($year = (int)date('Y'); $year <= (int)date('Y') + 12; $year++): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="student-field"><label for="expiry_preview">Card expiry date</label><input id="expiry_preview" value="31 December of graduation year" readonly></div>
                </div>
                <?php if ($applicationType !== 'new'): ?>
                    <div class="student-field"><label for="reason">Reason</label><textarea id="reason" name="reason" maxlength="1000" required minlength="8"></textarea></div>
                <?php else: ?>
                    <input type="hidden" name="reason" value="">
                <?php endif; ?>
                <div class="student-fields">
                    <div class="student-field"><label for="camera_photo">Student photo (transparent PNG; minimum 600 × 750 px, max 20 MB)</label><input id="camera_photo" type="file" name="camera_photo" accept="image/png,.png" required></div>
                    <div class="student-field"><label for="signature">Student signature (transparent PNG; max 5 MB)</label><input id="signature" type="file" name="signature" accept="image/png,.png" required></div>
                </div>
                <button class="student-button" type="submit" <?php echo !$applicationsOpen ? 'disabled' : ''; ?>>Submit application</button>
            </form>
            <script>
                (function () {
                    var faculty = document.getElementById('faculty_id');
                    var course = document.getElementById('course_id');
                    var graduationYear = document.getElementById('graduation_year');
                    var expiryPreview = document.getElementById('expiry_preview');
                    function filterCourses() {
                        var selectedFaculty = faculty.value;
                        var available = false;
                        Array.prototype.forEach.call(course.options, function (option, index) {
                            if (index === 0) {
                                option.hidden = false;
                                option.disabled = false;
                                return;
                            }
                            var matches = option.getAttribute('data-faculty') === selectedFaculty;
                            option.hidden = !matches;
                            option.disabled = !matches;
                            available = available || matches;
                        });
                        course.value = '';
                        course.disabled = !selectedFaculty || !available;
                        course.options[0].textContent = available ? 'Select course / programme' : 'No active courses for this faculty';
                    }
                    faculty.addEventListener('change', filterCourses);
                    graduationYear.addEventListener('change', function () {
                        expiryPreview.value = graduationYear.value
                            ? '31 December ' + graduationYear.value
                            : '31 December of graduation year';
                    });
                    filterCourses();
                })();
            </script>
        </section>

    <?php elseif ($view === 'card'): ?>
        <section class="student-panel-card card-preview-section">
            <h1>My ID Card</h1>
            <?php if (!$issuedCard): ?>
                <div class="student-notice">No issued card is available yet. A card marked “ready to print” is still awaiting staff processing.
                    <a href="student_portal.php?view=track">Track applications</a>.
                </div>
            <?php else: ?>
                <?php
                $cardExpiry = $issuedCard['graduation_year']
                    ? $issuedCard['graduation_year'] . '-12-31'
                    : ($issuedCard['expires_at'] ?? '');
                $cardIssueDate = $issuedCard['date_admitted'] ?? '';
                $facultyName = $issuedCard['faculty_name'] ?? '';
                $courseName = $issuedCard['course_name'] ?? '';
                $isPostgraduateFaculty = preg_match('/^school\s+of\s+post\s*graduate\b/i', trim($facultyName)) === 1;
                ?>
                <?php if (!$cardFaceSelected): ?>
                    <div class="student-notice no-print">Choose which card side you want to display and print.</div>
                    <div class="card-face-selector no-print">
                        <a class="student-button" href="student_portal.php?view=card&amp;face=front&amp;alignment=<?php echo rawurlencode($cardAlignment); ?>">Front</a>
                        <a class="student-button" href="student_portal.php?view=card&amp;face=back&amp;alignment=<?php echo rawurlencode($cardAlignment); ?>">Back</a>
                        <a class="student-button" href="student_portal.php?view=card&amp;face=both&amp;alignment=<?php echo rawurlencode($cardAlignment); ?>">Both sides</a>
                    </div>
                <?php else: ?>
                <p class="no-print">Selected side: <?php echo htmlspecialchars(ucfirst($cardFace), ENT_QUOTES, 'UTF-8'); ?>. Each face prints at 85 × 54 mm. Choose portrait or landscape, paper size, and tray in your printer settings; both sides print on separate pages. Alignment: <?php echo $cardAlignment === 'tray' ? '4.5 cm left of center' : 'centered'; ?>.</p>
                <p class="no-print card-face-print-action"><button class="student-button" type="button" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> Print <?php echo htmlspecialchars(ucfirst($cardFace), ENT_QUOTES, 'UTF-8'); ?></button> <a class="student-button-secondary" href="student_portal.php?view=card&amp;alignment=<?php echo rawurlencode($cardAlignment); ?>">Change side</a></p>
                <div class="no-print card-face-print-action">
                    <a class="student-button-secondary" href="student_portal.php?view=card&amp;face=<?php echo rawurlencode($cardFace); ?>&amp;alignment=<?php echo $cardAlignment === 'tray' ? 'center' : 'tray'; ?>"><?php echo $cardAlignment === 'tray' ? 'Center on selected stock' : 'Shift left 4.5 cm'; ?></a>
                </div>
                <div class="ku-id-card-pair<?php echo $cardAlignment === 'tray' ? ' print-tray-alignment' : ''; ?>">
                <?php if ($cardFace === 'front' || $cardFace === 'both'): ?>
                <div class="ku-id-card" aria-label="Front of Kumi University student identity card" style="<?php echo htmlspecialchars(studentCardFrontBrandingBarsStyle($frontBranding) . ';' . studentCardFrontBrandingRightPatternStyle($frontBranding), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($frontBranding['watermark_enabled'] === '1'): ?><img class="ku-id-custom-watermark" src="<?php echo htmlspecialchars(studentCardFrontLogoUrl($frontBranding), ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" style="--ku-id-watermark-width:<?php echo htmlspecialchars($frontBranding['watermark_width_mm'], ENT_QUOTES, 'UTF-8'); ?>mm;--ku-id-watermark-height:<?php echo htmlspecialchars($frontBranding['watermark_height_mm'], ENT_QUOTES, 'UTF-8'); ?>mm;--ku-id-watermark-opacity:<?php echo htmlspecialchars($frontBranding['watermark_opacity'], ENT_QUOTES, 'UTF-8'); ?>;<?php echo htmlspecialchars(studentCardFrontBrandingWatermarkStyle($frontBranding), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                    <div class="ku-id-top-bar"></div>
                    <div class="ku-id-header">
                        <div class="ku-id-logo" style="--ku-id-logo-height:<?php echo htmlspecialchars($frontBranding['logo_height_px'], ENT_QUOTES, 'UTF-8'); ?>px"><img src="<?php echo htmlspecialchars(studentCardFrontLogoUrl($frontBranding), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($frontBranding['university_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="ku-id-university-header">
                            <div class="ku-id-university-name" style="<?php echo htmlspecialchars(studentCardFrontBrandingTextStyle($frontBranding, 'university'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($frontBranding['university_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="ku-id-card-title" style="<?php echo htmlspecialchars(studentCardFrontBrandingTextStyle($frontBranding, 'card_title'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($frontBranding['card_title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                    <div class="ku-id-middle-bar"></div>
                    <div class="ku-id-content">
                        <div class="ku-id-photo-section">
                            <div class="ku-id-photo-stack">
                            <div class="ku-id-photo-container">
                    <?php if ($issuedCard['photo_path'] && studentPortalDocumentExists($issuedCard['photo_path'])): ?>
                        <img class="student-photo" src="student_portal.php?view=document&amp;application_id=<?php echo (int)$issuedCard['id']; ?>&amp;kind=photo" alt="Student photograph">
                    <?php else: ?>
                                <span class="ku-id-photo-missing">Photo not available</span>
                    <?php endif; ?>
                            </div>
                                <div class="ku-id-card-qr" style="--ku-id-qr-size:<?php echo htmlspecialchars($cardCodeSettings['qr_size_mm'], ENT_QUOTES, 'UTF-8'); ?>mm"><?php echo studentQrSvg($qrPayload, $cardCodeSettings['qr_foreground'], $cardCodeSettings['qr_background']); ?></div>
                            </div>
                        </div>
                        <div class="ku-id-details">
                            <div class="ku-id-column ku-id-left-column">
                                <div class="ku-id-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'surname'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Surname:</span><span class="ku-id-value"><?php echo htmlspecialchars($issuedCard['surname'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <div class="ku-id-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'given_name'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Given Name:</span><span class="ku-id-value"><?php echo htmlspecialchars($issuedCard['given_name'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <div class="ku-id-row ku-id-demographic-row">
                                    <div class="ku-id-demographic-labels">
                                        <span class="ku-id-label ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'nationality'), ENT_QUOTES, 'UTF-8'); ?>">Nationality:</span>
                                        <span class="ku-id-label ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'sex'), ENT_QUOTES, 'UTF-8'); ?>">Sex:</span>
                                    </div>
                                    <div class="ku-id-demographic-values">
                                        <span class="ku-id-value ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'nationality'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($issuedCard['nationality'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="ku-id-value ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'sex'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper(substr(trim((string)($issuedCard['sex'] ?? '')), 0, 1)) ?: 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="ku-id-row ku-id-issue-date ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'issue_date'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Date of Issue:</span><span class="ku-id-value"><?php echo $cardIssueDate ? htmlspecialchars(date('d.m.Y', strtotime($cardIssueDate)), ENT_QUOTES, 'UTF-8') : 'Not recorded'; ?></span></div>
                                <div class="ku-id-holder-signature">
                                    <span>HOLDERS SIGNATURE:</span>
                                    <?php if ($issuedCard['signature_path'] && studentPortalDocumentExists($issuedCard['signature_path'])): ?>
                                        <img style="<?php echo htmlspecialchars(studentCardFrontBrandingSignatureStyle($frontBranding, 'holder'), ENT_QUOTES, 'UTF-8'); ?>" src="student_portal.php?view=document&amp;application_id=<?php echo (int)$issuedCard['id']; ?>&amp;kind=signature" alt="">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ku-id-column ku-id-right-column">
                                <div class="ku-id-right-fields">
                                    <div class="ku-id-row ku-id-horizontal ku-id-card-number-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'card_number'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Card No.:</span><span class="ku-id-card-number"><?php echo htmlspecialchars($issuedCard['card_number'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <div class="ku-id-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'registration_number'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Reg No.:</span><span class="ku-id-reg-no"><?php echo htmlspecialchars(str_replace('/', '.', (string)($issuedCard['card_reg_no'] ?: $student_id)), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <?php if ($isPostgraduateFaculty): ?>
                                        <div class="ku-id-row ku-id-postgraduate-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'faculty'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-postgraduate-value"><?php echo htmlspecialchars($facultyName, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <?php else: ?>
                                        <div class="ku-id-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'faculty'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Faculty:</span><span class="ku-id-value"><?php echo htmlspecialchars($facultyName ?: 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <?php endif; ?>
                                    <div class="ku-id-row ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'course'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Course:</span><span class="ku-id-value"><?php echo htmlspecialchars($courseName ?: 'Not recorded', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <div class="ku-id-row ku-id-horizontal ku-id-custom-front-row" style="<?php echo htmlspecialchars(studentCardFrontRowStyle($frontRowSettings, 'expiry_date'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ku-id-label">Expiry Date:</span><span class="ku-id-value"><?php echo $cardExpiry ? htmlspecialchars(date('d.m.Y', strtotime($cardExpiry)), ENT_QUOTES, 'UTF-8') : 'Not recorded'; ?></span></div>
                                </div>
                                <?php if ($issuedCard['photo_path'] && studentPortalDocumentExists($issuedCard['photo_path'])): ?>
                                    <img class="ku-id-small-photo" src="student_portal.php?view=document&amp;application_id=<?php echo (int)$issuedCard['id']; ?>&amp;kind=photo" alt="Small student photograph">
                                <?php endif; ?>
                                <div class="ku-id-registrar-signature">
                                    <span>ACADEMIC REGISTRAR'S SIGNATURE:</span>
                                    <?php if ($registrarSignaturePath !== '' && studentPortalDocumentExists($registrarSignaturePath)): ?>
                                        <img style="<?php echo htmlspecialchars(studentCardFrontBrandingSignatureStyle($frontBranding, 'registrar'), ENT_QUOTES, 'UTF-8'); ?>" src="student_portal.php?view=document&amp;kind=registrar" alt="Academic Registrar signature">
                                    <?php else: ?>
                                        <span>Signature pending admin upload</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ku-id-footer-bar"></div>
                </div>
                <?php endif; ?>
                <?php if ($cardFace === 'back' || $cardFace === 'both'): ?>
                <div class="ku-id-card ku-id-card-back" aria-label="Back of Kumi University student identity card">
                    <div class="ku-id-back-header">
                        <strong>This Card is a property of Kumi University</strong>
                        <span>“Endliness &amp; Excellence for servanthood”</span>
                    </div>
                    <div class="ku-id-back-contact">
                        <strong>P.O. Box 178, Kumi (U)</strong>
                        <strong>Tel: +256 392 001 391</strong>
                        <strong>Email: ar@kumiuniversity.ac.ug</strong>
                        <strong>Website: www.kumiuniversity.ac.ug</strong>
                    </div>
                    <div class="ku-id-back-divider"></div>
                    <div class="ku-id-back-barcode" style="--ku-id-barcode-width:<?php echo htmlspecialchars($cardCodeSettings['barcode_width_percent'], ENT_QUOTES, 'UTF-8'); ?>%;--ku-id-barcode-height:<?php echo htmlspecialchars($cardCodeSettings['barcode_height_mm'], ENT_QUOTES, 'UTF-8'); ?>mm"><?php echo studentCode128Svg($barcodePayload, $cardCodeSettings['barcode_foreground'], $cardCodeSettings['barcode_background']); ?></div>
                    <div class="ku-id-back-return">If found, please return to the above address</div>
                </div>
                <?php endif; ?>
                </div>
                <p class="no-print" style="margin-top:15px"><a class="student-button-secondary" href="student_portal.php?view=qr">View card QR data</a></p>
                <?php if (($_GET['print'] ?? '') === '1' && $cardFaceSelected): ?>
                    <script>window.addEventListener('load', function () { window.print(); });</script>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    <?php elseif ($view === 'qr'): ?>
        <section class="student-panel-card">
            <h1>Student card QR information</h1>
            <?php if (!$issuedCard): ?>
                <p>An issued card is required before card verification information is available.</p>
            <?php else: ?>
                <p>These are the values encoded in your current ID card. The QR output is customized by the university; the barcode output is the configured scan template.</p>
                <div class="student-id-card">
                    <?php echo studentQrSvg($qrPayload, $cardCodeSettings['qr_foreground'], $cardCodeSettings['qr_background']); ?>
                    <p><strong>QR scan output:</strong><br><?php echo htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Barcode scan output:</strong><br><?php echo htmlspecialchars($barcodePayload, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <p class="no-print" style="margin-top:15px"><button class="student-button" type="button" onclick="window.print()">Print QR code</button></p>
            <?php endif; ?>
        </section>

    <?php elseif ($view === 'profile'): ?>
        <section class="student-panel-card">
            <h1>Edit profile</h1>
            <p>These details are used in your student account. ID-card corrections are reviewed separately.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="student-fields">
                    <div class="student-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="150" value="<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="email">Email</label><input id="email" type="email" name="email" maxlength="190" value="<?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="student-field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="50" value="<?php echo htmlspecialchars($student['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="student-field"><label for="program">Program</label><input id="program" name="program" maxlength="150" value="<?php echo htmlspecialchars($student['program'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="student-field"><label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Not specified</option>
                            <?php foreach (['Female', 'Male', 'Other'] as $genderOption): ?>
                                <option value="<?php echo $genderOption; ?>" <?php echo ($student['gender'] ?? '') === $genderOption ? 'selected' : ''; ?>><?php echo $genderOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="student-button" type="submit">Save profile</button>
            </form>
        </section>

    <?php elseif ($view === 'settings'): ?>
        <section class="student-panel-card">
            <h1>Account settings</h1>
            <h2>Change password</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="student-fields">
                    <div class="student-field"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
                    <div class="student-field"><label for="new_password">New password (at least 8 characters)</label><input id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password" required></div>
                    <div class="student-field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
                </div>
                <button class="student-button" type="submit">Change password</button>
            </form>
        </section>

    <?php elseif (isset($servicePages[$view])): ?>
        <?php $activeView = $view; ?>
        <section class="student-panel-card">
            <h1><?php echo htmlspecialchars($servicePages[$view][0], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="student-notice"><?php echo htmlspecialchars($servicePages[$view][1], ENT_QUOTES, 'UTF-8'); ?></div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="submit_service_request">
                <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="student-field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="180" required></div>
                <div class="student-field"><label for="details">Details</label><textarea id="details" name="details" maxlength="5000" minlength="10" required></textarea></div>
                <button class="student-button" type="submit">Send request for review</button>
            </form>
            <p>Requests are visible under <a href="student_portal.php?view=track">Track Application</a>. A submitted request is not an approval, booking, registration or payment.</p>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
