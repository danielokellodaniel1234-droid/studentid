<?php
require_once dirname(__DIR__) . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

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
    'SELECT id, student_id, full_name, email, phone, program, gender, registration_date
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
$activeView = 'overview';

$applicationStmt = $conn->prepare(
    'SELECT a.id, a.application_type, a.reason, a.application_status, a.created_at,
            c.card_number, c.card_status, c.expires_at
     FROM id_applications a
     LEFT JOIN id_cards c ON c.application_id = a.id AND c.student_id = a.student_id
     WHERE a.student_id = ?
     ORDER BY a.created_at DESC, a.id DESC LIMIT 1'
);
$applicationStmt->bind_param('i', $userId);
$applicationStmt->execute();
$latestApplication = $applicationStmt->get_result()->fetch_assoc();
$applicationStmt->close();

$issuedStmt = $conn->prepare(
    "SELECT c.id, c.card_number, c.expires_at, a.photo_path
     FROM id_cards c
     INNER JOIN id_applications a ON a.id = c.application_id
     WHERE c.student_id = ? AND c.card_status = 'issued'
     ORDER BY c.issued_at DESC, c.id DESC LIMIT 1"
);
$issuedStmt->bind_param('i', $userId);
$issuedStmt->execute();
$issuedCard = $issuedStmt->get_result()->fetch_assoc();
$issuedStmt->close();

$activityStmt = $conn->prepare(
    'SELECT a.id, a.application_type, a.application_status, a.created_at
     FROM id_applications a WHERE a.student_id = ?
     ORDER BY a.created_at DESC, a.id DESC LIMIT 5'
);
$activityStmt->bind_param('i', $userId);
$activityStmt->execute();
$applications = $activityStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activityStmt->close();
$intakeStmt = $conn->prepare(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'student_applications_open' LIMIT 1"
);
$intakeStmt->execute();
$intakeSetting = $intakeStmt->get_result()->fetch_assoc();
$intakeStmt->close();
$applicationsOpen = !isset($intakeSetting['setting_value']) || $intakeSetting['setting_value'] === '1';

$full_name = $student['full_name'];
$phone = $student['phone'] ?? '';
$program = $student['program'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard - Kumi University</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/student-panel.css?v=20261120-signature-label-stability">
</head>
<body>
<?php require __DIR__ . '/student_header.php'; ?>
<main class="student-page">
    <?php if (!$applicationsOpen): ?><div class="student-notice error">Student ID applications are temporarily paused. You can still track existing applications and contact support.</div><?php endif; ?>
    <section class="student-panel-card student-welcome-card">
        <div class="student-welcome-layout">
            <div class="student-welcome-copy">
                <p class="student-welcome-tagline">Manage your student ID, applications and requests from one place.</p>
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0], ENT_QUOTES, 'UTF-8'); ?>!</h1>
            </div>
            <div class="student-summary-grid" aria-label="Student account details">
                <div><strong>Student ID</strong><span><?php echo htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div><strong>Program</strong><span><?php echo htmlspecialchars($program ?: 'Not specified', ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div><strong>Member since</strong><span><?php echo $student['registration_date'] ? htmlspecialchars(date('M Y', strtotime($student['registration_date'])), ENT_QUOTES, 'UTF-8') : 'Not available'; ?></span></div>
            </div>
        </div>
    </section>

    <?php if ($issuedCard): ?>
        <section class="student-panel-card">
            <h2><i class="fas fa-id-card" aria-hidden="true"></i> Your issued ID card</h2>
            <p>Your card is marked as issued in the student records. Expiry:
                <strong><?php echo $issuedCard['expires_at'] ? htmlspecialchars(date('F j, Y', strtotime($issuedCard['expires_at'])), ENT_QUOTES, 'UTF-8') : 'Not recorded'; ?></strong>.
            </p>
            <a class="student-button" href="student_portal.php?view=card"><i class="fas fa-eye" aria-hidden="true"></i> View / print card</a>
        </section>
    <?php elseif ($latestApplication): ?>
        <section class="student-panel-card">
            <?php if ($latestApplication['application_status'] === 'rejected'): ?>
                <h2><i class="fas fa-circle-xmark" aria-hidden="true"></i> Your latest application was rejected</h2>
                <p>Open your <a href="student_portal.php?view=notifications">notifications</a> to review the reason and advice from the administrator.</p>
            <?php else: ?>
                <h2><i class="fas fa-hourglass-half" aria-hidden="true"></i> ID application in progress</h2>
                <p>Your latest <?php echo htmlspecialchars($latestApplication['application_type'], ENT_QUOTES, 'UTF-8'); ?> application is
                    <span class="student-status"><?php echo htmlspecialchars(str_replace('_', ' ', $latestApplication['application_status']), ENT_QUOTES, 'UTF-8'); ?></span>.
                    It is not printable until it has been approved and issued by an administrator.
                </p>
            <?php endif; ?>
            <a class="student-button-secondary" href="student_portal.php?view=track">Track application</a>
        </section>
    <?php else: ?>
        <section class="student-panel-card">
            <h2><i class="fas fa-id-card" aria-hidden="true"></i> No ID card on file</h2>
            <p>Submit your first ID application to get started.</p>
            <?php if ($applicationsOpen): ?><a class="student-button" href="student_portal.php?view=apply&amp;type=new">Apply for an ID card</a><?php else: ?><span class="student-button-disabled" aria-disabled="true">Applications are paused</span><?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="student-grid">
        <section class="student-panel-card">
            <h2><i class="fas fa-bolt" aria-hidden="true"></i> ID card actions</h2>
            <div class="student-id-actions">
                <a class="student-button-secondary" href="student_portal.php?view=card">My ID card</a>
                <a class="student-button-secondary" href="student_portal.php?view=card&amp;print=1">Download / print card (Save as PDF)</a>
                <a class="student-button-secondary" href="student_portal.php?view=qr">Card QR code</a>
                <?php if ($applicationsOpen): ?>
                    <a class="student-button-secondary" href="student_portal.php?view=apply&amp;type=new">Apply for a new ID</a>
                    <a class="student-button-secondary" href="student_portal.php?view=apply&amp;type=renewal">Renew ID card</a>
                    <a class="student-button-secondary" href="student_portal.php?view=replacement">Report loss / replace card</a>
                <?php else: ?>
                    <span class="student-button-disabled" aria-disabled="true">New applications paused</span>
                    <span class="student-button-disabled" aria-disabled="true">Renewals paused</span>
                    <span class="student-button-disabled" aria-disabled="true">Replacements paused</span>
                <?php endif; ?>
                <a class="student-button-secondary" href="student_portal.php?view=correction">Request an ID correction</a>
            </div>
        </section>
        <section class="student-panel-card">
            <h2><i class="fas fa-history" aria-hidden="true"></i> Recent ID applications</h2>
            <?php if (!$applications): ?>
                <p>No ID applications have been submitted yet.</p>
            <?php else: ?>
                <div class="student-table-wrap">
                    <table class="student-table">
                        <thead><tr><th>Application</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>#<?php echo (int)$application['id']; ?> · <?php echo htmlspecialchars(ucfirst($application['application_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="student-status"><?php echo htmlspecialchars(str_replace('_', ' ', $application['application_status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($application['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:14px"><a class="student-button-secondary" href="student_portal.php?view=track">View all applications</a></p>
            <?php endif; ?>
        </section>
    </div>

    <section class="student-panel-card">
        <h2><i class="fas fa-university" aria-hidden="true"></i> University service links</h2>
        <p>These pages let you submit requests to the university. They do not connect to external academic, payment or booking systems.</p>
        <div class="student-service-links">
            <a class="student-button-secondary" href="student_portal.php?view=course_registration">Course Registration</a>
            <a class="student-button-secondary" href="student_portal.php?view=fee_payment">Fee Payment</a>
            <a class="student-button-secondary" href="student_portal.php?view=library">Library Access</a>
            <a class="student-button-secondary" href="student_portal.php?view=hostel">Hostel Booking</a>
            <a class="student-button-secondary" href="student_portal.php?view=exam_results">Exam Results</a>
            <a class="student-button-secondary" href="student_portal.php?view=calendar">Academic Calendar</a>
        </div>
    </section>
</main>
</body>
</html>
