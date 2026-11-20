<?php

function adminRequireAccess(bool $adminOnly = false): void
{
    if (($_SESSION['logged_in'] ?? false) !== true) {
        $scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        if (basename($scriptDirectory) === 'admin') {
            $scriptDirectory = dirname($scriptDirectory);
        }
        header('Location: ' . rtrim($scriptDirectory, '/') . '/login.php');
        exit;
    }
    $role = (string)($_SESSION['user_type'] ?? '');
    if (!in_array($role, ['admin', 'staff'], true)
        || ($adminOnly && $role !== 'admin')) {
        http_response_code(403);
        exit($adminOnly ? 'Administrator access required.' : 'Staff access required.');
    }
}

function adminEscape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function adminAudit(mysqli $conn, string $action, string $details = ''): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_type'] ?? 'staff');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $stmt = $conn->prepare(
        'INSERT INTO system_audit_log (user_id, user_role, action, details, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $role, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

function adminNotify(mysqli $conn, string $title, string $message, string $type, string $referenceType = '', int $referenceId = 0): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_type'] ?? 'admin');
    $referenceTypeOrNull = $referenceType !== '' ? $referenceType : null;
    $referenceIdOrNull = $referenceId > 0 ? $referenceId : null;
    $stmt = $conn->prepare(
        'INSERT INTO admin_notifications
            (recipient_role, title, message, notification_type, reference_type, reference_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssii', $role, $title, $message, $type, $referenceTypeOrNull, $referenceIdOrNull, $userId);
    $stmt->execute();
    $stmt->close();
}

function adminCsrfToken(): string
{
    if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf']) || strlen($_SESSION['admin_csrf']) < 32) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function adminShellStart(mysqli $conn, string $title, string $activeView): void
{
    $fullName = (string)($_SESSION['full_name'] ?? 'Administrator');
    $role = (string)($_SESSION['user_type'] ?? 'admin');
    $unread = 0;
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM admin_notifications WHERE recipient_role = ? AND read_at IS NULL'
    );
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $unread = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $groups = [
        'Dashboard' => [
            ['dashboard', 'Overview', 'fa-gauge-high'],
            ['analytics', 'Analytics', 'fa-chart-line'],
            ['reports', 'Reports', 'fa-file-lines'],
        ],
        'User management' => [
            ['students', 'Student Management', 'fa-users'],
            ['registry', 'Student Registry', 'fa-address-book'],
            ['student_add', 'Add Student', 'fa-user-plus'],
            ['import', 'Import Accounts (CSV)', 'fa-file-import'],
            ['student_export', 'Export Data', 'fa-file-export'],
            ['staff', 'Staff Management', 'fa-user-tie'],
        ],
        'Application management' => [
            ['applications', 'Process Applications', 'fa-clipboard-check'],
            ['service_requests', 'Student Service Requests', 'fa-inbox'],
            ['settings', 'Application Settings', 'fa-gears'],
            ['catalog', 'Card Faculties, Courses & Signature', 'fa-graduation-cap'],
            ['print_queue', 'Card Printing Queue', 'fa-id-card'],
        ],
        'System configuration' => [
            ['backup', 'Backup & Restore', 'fa-database'],
        ],
        'Communication' => [
            ['notifications', 'Notifications', 'fa-bell'],
            ['announcements', 'Announcements', 'fa-bullhorn'],
        ],
        'Maintenance' => [
            ['monitor', 'System Monitor', 'fa-desktop'],
            ['audit', 'Audit Logs', 'fa-clock-rotate-left'],
        ],
    ];
    $adminOnlyViews = [
        'registry', 'registry_export', 'import', 'staff', 'student_add', 'student_edit', 'student_export',
        'settings', 'catalog', 'announcements', 'backup', 'backup_download', 'backup_restore',
    ];
    $url = static fn(string $view): string => 'console.php?view=' . rawurlencode($view);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo adminEscape($title); ?> - Kumi University ID Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/student-panel.css?v=20261120-signature-label-stability">
    <link rel="stylesheet" href="../assets/admin-panel.css?v=20261120-signature-label-stability">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <button class="admin-menu-toggle" type="button" id="admin-menu-toggle" aria-label="Open admin menu" aria-controls="admin-drawer" aria-expanded="false">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <a class="admin-brand" href="<?php echo adminEscape($url('dashboard')); ?>">
        <img src="../KU Logo.png" alt="">
        <span><strong>Kumi University</strong><small>Student ID Administration</small></span>
    </a>
    <div class="admin-current-page-title"><?php echo adminEscape($title); ?></div>
    <div class="admin-topbar-spacer"></div>
    <form class="admin-search" action="console.php" method="get" style="margin:0;max-width:320px">
        <input type="hidden" name="view" value="students">
        <input name="q" aria-label="Search students" placeholder="Search students">
    </form>
    <div class="admin-topbar-user">
        <a href="<?php echo adminEscape($url('notifications')); ?>" aria-label="Notifications">
            <i class="fas fa-bell" aria-hidden="true"></i><?php if ($unread): ?> <span class="admin-badge warning"><?php echo $unread; ?></span><?php endif; ?>
        </a>
        <span><?php echo adminEscape($fullName); ?> · <?php echo adminEscape(ucfirst($role)); ?></span>
        <a class="admin-signout" href="../logout.php"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out</a>
    </div>
</header>
<?php if (in_array($activeView, ['card', 'print_batch', 'templates'], true)
    && in_array((string)($_GET['face'] ?? ''), ['front', 'back', 'both'], true)): ?>
<nav class="admin-card-tools no-print" aria-label="Card layout tools">
    <?php if (in_array((string)($_GET['face'] ?? ''), ['front', 'both'], true)): ?>
        <div class="admin-card-tools-picker">
            <label for="admin-card-tool-category">Card adjustment</label>
            <select id="admin-card-tool-category" aria-label="Choose card adjustment controls">
                <option value="">Select controls to display</option>
                <option value="horizontal">Horizontal rulers</option>
                <option value="vertical">Vertical rulers</option>
                <option value="photo">Photo position &amp; size</option>
            </select>
        </div>
    <?php endif; ?>
    <div class="admin-card-tools-save">
        <input type="hidden" id="admin-print-settings-csrf" value="<?php echo adminEscape(adminCsrfToken()); ?>">
        <?php if (in_array((string)($_GET['face'] ?? ''), ['front', 'both'], true)): ?>
            <button class="admin-button" id="admin-print-settings-save" type="button">Save card layout</button>
            <output id="admin-print-settings-status" role="status" aria-live="polite">Adjustments can be saved for future previews and prints.</output>
        <?php endif; ?>
        <button class="admin-button" id="admin-card-png-toggle" type="button" aria-expanded="false" aria-controls="admin-card-png-export-panel">Epson L8100 PNG</button>
    </div>
    <section class="admin-panel admin-card-png-export-panel" id="admin-card-png-export-panel" aria-label="Epson L8100 PVC card PNG export" hidden>
        <h2>Epson L8100 PVC card PNG export</h2>
        <p>Download the front and back as separate PNGs. Each card is rotated 90° clockwise to match the supplied portrait alignment. When two cards are selected, they are arranged left to right in the selected order. Physical card size defaults to 85 × 54 mm (portrait PNG: 54 × 85 mm per card).</p>
        <div class="admin-card-png-settings">
            <div class="admin-field"><label for="card-png-width">Card width (mm)</label><input id="card-png-width" type="number" min="40" max="120" step="0.1" value="85"></div>
            <div class="admin-field"><label for="card-png-height">Card height (mm)</label><input id="card-png-height" type="number" min="25" max="90" step="0.1" value="54"></div>
            <div class="admin-field"><label for="card-png-gap">Gap between cards (mm)</label><input id="card-png-gap" type="number" min="0" max="20" step="0.1" value="2"></div>
            <div class="admin-field"><label for="card-png-dpi">PNG resolution</label><select id="card-png-dpi"><option value="300" selected>300 DPI</option><option value="600">600 DPI</option></select></div>
        </div>
        <div class="admin-actions admin-card-png-actions">
            <?php foreach (((string)($_GET['face'] ?? '') === 'both' ? ['front', 'back'] : [(string)($_GET['face'] ?? '')]) as $exportFace): ?>
                <button class="admin-button" type="button" data-card-png-face="<?php echo adminEscape($exportFace); ?>">Download <?php echo adminEscape(ucfirst($exportFace)); ?> PNG</button>
            <?php endforeach; ?>
            <span id="card-png-status" role="status" aria-live="polite"></span>
        </div>
        <small>Print the downloaded PNG at 100% / actual size in the Epson print dialog. Card proportions are preserved when you change the dimensions.</small>
    </section>
</nav>
<script src="../assets/admin-card-png-export.js?v=20261120-watermark-mm"></script>
<?php endif; ?>
<div class="admin-overlay" id="admin-overlay" hidden></div>
<aside class="admin-drawer" id="admin-drawer" aria-label="Admin navigation" aria-hidden="true" inert>
    <div class="admin-drawer-head">
        <strong><i class="fas fa-shield-halved" aria-hidden="true"></i> Admin Dashboard</strong>
        <button class="admin-drawer-close" id="admin-drawer-close" type="button" aria-label="Close admin menu">&times;</button>
    </div>
    <nav class="admin-drawer-nav">
        <?php foreach ($groups as $groupName => $items): ?>
            <div class="admin-nav-group">
                <div class="admin-nav-heading"><?php echo adminEscape($groupName); ?></div>
                <?php foreach ($items as [$view, $label, $icon]): ?>
                    <?php if ($role !== 'admin' && in_array($view, $adminOnlyViews, true)) { continue; } ?>
                    <a class="admin-nav-link <?php echo $activeView === $view ? 'active' : ''; ?>"
                       href="<?php echo adminEscape($url($view)); ?>"
                       <?php echo $activeView === $view ? 'aria-current="page"' : ''; ?>>
                        <i class="fas <?php echo adminEscape($icon); ?>" aria-hidden="true"></i><span><?php echo adminEscape($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>
<main class="admin-main">
    <?php
}

function adminShellEnd(): void
{
    ?>
</main>
<footer class="admin-footer">
    <div class="admin-footer-row">
        <span>&copy; <?php echo date('Y'); ?> Kumi University · Student ID Management System</span>
        <span>PHP <?php echo adminEscape(PHP_VERSION); ?> · <?php echo adminEscape((string)($_SERVER['SERVER_NAME'] ?? 'localhost')); ?></span>
    </div>
</footer>
<script>
(function () {
    var drawer = document.getElementById('admin-drawer');
    var overlay = document.getElementById('admin-overlay');
    var toggle = document.getElementById('admin-menu-toggle');
    var close = document.getElementById('admin-drawer-close');
    function setOpen(open) {
        if (!open && !drawer.classList.contains('open')) { return; }
        drawer.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        drawer.inert = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close admin menu' : 'Open admin menu');
        overlay.hidden = !open;
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            var firstLink = drawer.querySelector('.admin-nav-link');
            if (firstLink) { firstLink.focus(); }
        } else {
            toggle.focus();
        }
    }
    toggle.addEventListener('click', function () { setOpen(!drawer.classList.contains('open')); });
    close.addEventListener('click', function () { setOpen(false); });
    overlay.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { setOpen(false); }
    });
    var printInputIds = {
        offset_mm: 'admin-print-offset',
        offset_y_mm: 'admin-print-offset-y',
        card1_y_mm: 'admin-print-card1-y',
        card2_y_mm: 'admin-print-card2-y',
        card1_x_mm: 'admin-print-card1-x-control',
        card2_x_mm: 'admin-print-card2-x-control',
        photo_large_x_mm: 'admin-photo-large-x',
        photo_large_y_mm: 'admin-photo-large-y',
        photo_large_scale_pct: 'admin-photo-large-scale',
        photo_small_x_mm: 'admin-photo-small-x',
        photo_small_y_mm: 'admin-photo-small-y',
        photo_small_scale_pct: 'admin-photo-small-scale'
    };
    var printOutputIds = {
        offset_mm: 'admin-print-offset-value',
        offset_y_mm: 'admin-print-offset-y-value',
        card1_y_mm: 'admin-print-card1-y-value',
        card2_y_mm: 'admin-print-card2-y-value',
        card1_x_mm: 'admin-print-card1-x-value',
        card2_x_mm: 'admin-print-card2-x-value',
        photo_large_x_mm: 'admin-photo-large-x-value',
        photo_large_y_mm: 'admin-photo-large-y-value',
        photo_large_scale_pct: 'admin-photo-large-scale-value',
        photo_small_x_mm: 'admin-photo-small-x-value',
        photo_small_y_mm: 'admin-photo-small-y-value',
        photo_small_scale_pct: 'admin-photo-small-scale-value'
    };
    var printSettingInputs = {};
    Object.keys(printInputIds).forEach(function (key) {
        var input = document.getElementById(printInputIds[key]);
        if (input) { printSettingInputs[key] = input; }
    });
    var printSettingsStatus = document.getElementById('admin-print-settings-status');
    if (Object.keys(printSettingInputs).length) {
        function settingValue(key) {
            var input = printSettingInputs[key];
            var value = input ? parseFloat(input.value) : 0;
            if (!Number.isFinite(value)) {
                value = key === 'card2_y_mm' ? 5
                    : (key === 'photo_large_scale_pct' || key === 'photo_small_scale_pct' ? 100 : 0);
            }
            if (key === 'offset_mm') {
                return Math.round(Math.max(-30, Math.min(30, value)));
            }
            if (key === 'photo_large_scale_pct' || key === 'photo_small_scale_pct') {
                return Math.round(Math.max(50, Math.min(200, value)));
            }
            return Math.round(Math.max(-10, Math.min(10, value)) * 10) / 10;
        }
        function formatSetting(key, value) {
            if (key.indexOf('scale_pct') !== -1) { return value + '%'; }
            var formatted = key === 'offset_mm' ? String(value) : value.toFixed(1);
            return (value > 0 ? '+' : '') + formatted + ' mm';
        }
        function currentPrintSettings() {
            var settings = {
                offset_mm: settingValue('offset_mm'),
                offset_y_mm: settingValue('offset_y_mm'),
                card1_y_mm: settingValue('card1_y_mm'),
                card2_y_mm: settingValue('card2_y_mm'),
                card1_x_mm: settingValue('card1_x_mm'),
                card2_x_mm: settingValue('card2_x_mm'),
                photo_large_x_mm: settingValue('photo_large_x_mm'),
                photo_large_y_mm: settingValue('photo_large_y_mm'),
                photo_large_scale_pct: settingValue('photo_large_scale_pct'),
                photo_small_x_mm: settingValue('photo_small_x_mm'),
                photo_small_y_mm: settingValue('photo_small_y_mm'),
                photo_small_scale_pct: settingValue('photo_small_scale_pct')
            };
            return settings;
        }
        function updatePrintSettings() {
            var settings = currentPrintSettings();
            Object.keys(printSettingInputs).forEach(function (key) {
                printSettingInputs[key].value = settings[key];
                var output = document.getElementById(printOutputIds[key]);
                if (output) { output.textContent = formatSetting(key, settings[key]); }
            });
            document.querySelectorAll('.admin-batch-print-sheet, .admin-print-offset-sheet').forEach(function (sheet) {
                sheet.style.setProperty('--batch-print-offset', settings.offset_mm + 'mm');
                sheet.style.setProperty('--batch-print-offset-y', settings.offset_y_mm + 'mm');
            });
            document.querySelectorAll('.admin-print-card-slot').forEach(function (slot) {
                var key = slot.getAttribute('data-card-index') === '2' ? 'card2_y_mm' : 'card1_y_mm';
                slot.style.setProperty('--card-vertical-offset', settings[key] + 'mm');
                var horizontalKey = slot.getAttribute('data-card-index') === '2' ? 'card2_x_mm' : 'card1_x_mm';
                slot.style.setProperty('--card-horizontal-offset', settings[horizontalKey] + 'mm');
            });
            document.querySelectorAll('.ku-id-photo-container img.student-photo').forEach(function (photo) {
                photo.style.setProperty('--admin-photo-x', settings.photo_large_x_mm + 'mm');
                photo.style.setProperty('--admin-photo-y', settings.photo_large_y_mm + 'mm');
                photo.style.setProperty('--admin-photo-scale', settings.photo_large_scale_pct / 100);
            });
            document.querySelectorAll('.ku-id-small-photo').forEach(function (photo) {
                photo.style.setProperty('--admin-photo-x', settings.photo_small_x_mm + 'mm');
                photo.style.setProperty('--admin-photo-y', settings.photo_small_y_mm + 'mm');
                photo.style.setProperty('--admin-photo-scale', settings.photo_small_scale_pct / 100);
            });
            return settings;
        }
        Object.keys(printSettingInputs).forEach(function (key) {
            printSettingInputs[key].addEventListener('input', function () {
                updatePrintSettings();
                if (printSettingsStatus) { printSettingsStatus.textContent = 'Unsaved card layout changes. Save to use them on future previews and prints.'; }
            });
        });
        updatePrintSettings();
        var cardToolCategory = document.getElementById('admin-card-tool-category');
        if (cardToolCategory) {
            cardToolCategory.addEventListener('change', function () {
                document.querySelectorAll('[data-card-tool-category]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-card-tool-category') !== cardToolCategory.value;
                });
            });
        }
        [
            ['admin-print-offset-reset', { offset_mm: 0 }],
            ['admin-print-offset-y-reset', { offset_y_mm: 0 }],
            ['admin-print-card1-y-reset', { card1_y_mm: 0 }],
            ['admin-print-card2-y-reset', { card2_y_mm: 5 }],
            ['admin-print-card1-x-reset', { card1_x_mm: 0 }],
            ['admin-print-card2-x-reset', { card2_x_mm: 0 }]
        ].forEach(function (entry) {
            var button = document.getElementById(entry[0]);
            if (!button) { return; }
            button.addEventListener('click', function () {
                Object.keys(entry[1]).forEach(function (key) {
                    if (printSettingInputs[key]) { printSettingInputs[key].value = entry[1][key]; }
                });
                updatePrintSettings();
                if (printSettingsStatus) { printSettingsStatus.textContent = 'Unsaved card layout changes. Save to use them on future previews and prints.'; }
            });
        });
        var savePrintSettings = document.getElementById('admin-print-settings-save');
        if (savePrintSettings) {
            savePrintSettings.addEventListener('click', function () {
                var settings = updatePrintSettings();
                var body = new URLSearchParams();
                body.set('csrf_token', document.getElementById('admin-print-settings-csrf').value);
                body.set('action', 'save_print_alignment');
                Object.keys(settings).forEach(function (key) { body.set(key, String(settings[key])); });
                savePrintSettings.disabled = true;
                if (printSettingsStatus) { printSettingsStatus.textContent = 'Saving card layout settings to the server…'; }
                fetch('console.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    body: body
                }).then(function (response) {
                    return response.json().then(function (result) {
                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'The alignment settings could not be saved.');
                        }
                        if (printSettingsStatus) { printSettingsStatus.textContent = 'Card layout saved on the server for future previews and prints.'; }
                    });
                }).catch(function (error) {
                    if (printSettingsStatus) { printSettingsStatus.textContent = error.message || 'Could not save alignment settings. Check the connection and try again.'; }
                }).finally(function () {
                    savePrintSettings.disabled = false;
                });
            });
        }
    }
    var pngToggle = document.getElementById('admin-card-png-toggle');
    var pngPanel = document.getElementById('admin-card-png-export-panel');
    if (pngToggle && pngPanel) {
        pngToggle.addEventListener('click', function () {
            var open = pngPanel.hidden;
            pngPanel.hidden = !open;
            pngToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !pngPanel.hidden) {
                pngPanel.hidden = true;
                pngToggle.setAttribute('aria-expanded', 'false');
                pngToggle.focus();
            }
        });
    }
})();
</script>
</body>
</html>
    <?php
}
