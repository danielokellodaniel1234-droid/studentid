<?php
$studentMenuItems = [
    ['overview', 'Overview', 'fa-house', 'student_dashboard.php'],
    ['notifications', 'Notifications', 'fa-bell', 'student_portal.php?view=notifications'],
    ['card', 'My ID Card', 'fa-id-card', 'student_portal.php?view=card'],
    ['apply', 'Apply for ID', 'fa-circle-plus', 'student_portal.php?view=apply&type=new'],
    ['track', 'Track Application', 'fa-list-check', 'student_portal.php?view=track'],
    ['replacement', 'Replace lost ID', 'fa-credit-card', 'student_portal.php?view=replacement'],
    ['correction', 'Correct ID', 'fa-pen-to-square', 'student_portal.php?view=correction'],
    ['profile', 'Edit profile', 'fa-user-pen', 'student_portal.php?view=profile'],
    ['settings', 'Settings', 'fa-gear', 'student_portal.php?view=settings'],
    ['help', 'Help & Support', 'fa-circle-question', 'student_portal.php?view=help'],
    ['course_registration', 'Course Registration', 'fa-book', 'student_portal.php?view=course_registration'],
    ['fee_payment', 'Fee Payment', 'fa-file-invoice-dollar', 'student_portal.php?view=fee_payment'],
    ['library', 'Library Access', 'fa-book-open-reader', 'student_portal.php?view=library'],
    ['hostel', 'Hostel Booking', 'fa-bed', 'student_portal.php?view=hostel'],
    ['exam_results', 'Exam Results', 'fa-chart-line', 'student_portal.php?view=exam_results'],
    ['calendar', 'Academic Calendar', 'fa-calendar-days', 'student_portal.php?view=calendar'],
];
$studentNotificationStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM application_events e
     INNER JOIN id_applications a ON a.id = e.application_id
     WHERE a.student_id = ? AND e.action = 'rejected'"
);
$studentNotificationStmt->bind_param('i', $userId);
$studentNotificationStmt->execute();
$studentNotificationCount = (int)$studentNotificationStmt->get_result()->fetch_assoc()['total'];
$studentNotificationStmt->close();
$activeAnnouncements = isset($conn)
    ? $conn->query("SELECT title, message, created_at FROM admin_announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC)
    : [];
?>
<header class="student-topbar">
    <div class="student-topbar-inner">
        <button type="button" class="student-menu-toggle" id="student-menu-toggle"
            aria-label="Open student menu" aria-controls="student-menu" aria-expanded="false">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <a class="student-brand" href="student_dashboard.php">
            <img src="KU Logo.png" alt="">
            <span><strong>Kumi University</strong><small>Student ID Management System</small></span>
        </a>
        <div class="student-topbar-user">
            <span><?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></span>
            <a class="student-notification-link" href="student_portal.php?view=notifications" aria-label="Notifications<?php echo $studentNotificationCount ? ' (' . $studentNotificationCount . ')' : ''; ?>">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <?php if ($studentNotificationCount > 0): ?><span class="student-notification-count"><?php echo $studentNotificationCount > 99 ? '99+' : $studentNotificationCount; ?></span><?php endif; ?>
            </a>
            <a href="logout.php" class="student-signout"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out</a>
        </div>
    </div>
</header>
<?php if ($activeAnnouncements): ?>
    <section class="student-announcements" aria-label="University announcements">
        <?php foreach ($activeAnnouncements as $announcement): ?>
            <article class="student-announcement">
                <strong><i class="fas fa-bullhorn" aria-hidden="true"></i> <?php echo htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?php echo nl2br(htmlspecialchars($announcement['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                <small><?php echo htmlspecialchars(date('M j, Y', strtotime($announcement['created_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<div class="student-menu-backdrop" id="student-menu-backdrop" hidden></div>
<aside class="student-menu" id="student-menu" aria-label="Student navigation" aria-hidden="true">
    <div class="student-menu-heading">
        <strong><i class="fas fa-compass" aria-hidden="true"></i> Dashboard Menu</strong>
        <button type="button" id="student-menu-close" aria-label="Close student menu">&times;</button>
    </div>
    <nav class="student-menu-links">
        <?php foreach ($studentMenuItems as [$menuView, $label, $icon, $href]): ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $activeView === $menuView ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($menuView === 'notifications' && $studentNotificationCount > 0): ?>
                    <span class="student-menu-notification-count"><?php echo $studentNotificationCount > 99 ? '99+' : $studentNotificationCount; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="student-menu-footer">
        <span class="student-menu-status-icon"><i class="fas fa-id-card" aria-hidden="true"></i></span>
        <span><small>Signed in as</small><strong><?php echo htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8'); ?></strong></span>
    </div>
</aside>
<script src="assets/student-panel.js" defer></script>
