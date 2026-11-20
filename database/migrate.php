<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once dirname(__DIR__) . '/config.php';
$conn->set_charset('utf8mb4');

function migrationHasColumn($connection, $table, $column) {
    $stmt = $connection->prepare(
        'SELECT COUNT(*) AS found FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $found = (int)$stmt->get_result()->fetch_assoc()['found'] > 0;
    $stmt->close();
    return $found;
}

function migrationEnsureColumn($connection, $table, $column, $definition) {
    if (!migrationHasColumn($connection, $table, $column)) {
        $connection->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added $table.$column\n";
    }
}

function migrationEnsureNullableCardNumber($connection) {
    $result = $connection->query(
        "SELECT IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_cards' AND COLUMN_NAME = 'card_number'"
    );
    $column = $result->fetch_assoc();
    if (!$column) {
        throw new RuntimeException('The id_cards.card_number column is missing.');
    }
    if ($column['IS_NULLABLE'] !== 'YES') {
        $connection->query('ALTER TABLE `id_cards` MODIFY COLUMN `card_number` VARCHAR(50) NULL');
        echo "Allowed id_cards.card_number to remain blank until assigned\n";
    }
}

function migrationEnsureUniqueIndex($connection, $table, $column, $indexName) {
    $result = $connection->query("SHOW INDEX FROM `$table`");
    $indexes = [];
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['Non_unique'] === 0) {
            $indexes[$row['Key_name']][(int)$row['Seq_in_index']] = $row['Column_name'];
        }
    }
    foreach ($indexes as $columns) {
        ksort($columns);
        if (count($columns) === 1 && reset($columns) === $column) {
            return;
        }
    }
    $duplicate = $connection->query(
        "SELECT `$column` FROM `$table` WHERE `$column` IS NOT NULL
         GROUP BY `$column` HAVING COUNT(*) > 1 LIMIT 1"
    )->fetch_row();
    if ($duplicate) {
        throw new RuntimeException(
            "Cannot add the required unique index on `$table`.`$column`: duplicate value " .
            $duplicate[0] . '. Resolve duplicates before rerunning.'
        );
    }
    $connection->query("ALTER TABLE `$table` ADD UNIQUE KEY `$indexName` (`$column`)");
    echo "Added unique index $indexName\n";
}

$tables = [
    'students' => "CREATE TABLE IF NOT EXISTS students (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(80) NOT NULL,
        username VARCHAR(80) NULL,
        full_name VARCHAR(150) NOT NULL,
        gender VARCHAR(20) NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(50) NULL,
        program VARCHAR(150) NULL,
        password VARCHAR(255) NOT NULL,
        security_question VARCHAR(20) NULL,
        security_answer VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME NULL,
        UNIQUE KEY uq_students_student_id (student_id),
        UNIQUE KEY uq_students_username (username),
        UNIQUE KEY uq_students_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'student_registry' => "CREATE TABLE IF NOT EXISTS student_registry (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        registration_number VARCHAR(80) NOT NULL,
        card_number VARCHAR(50) NULL,
        full_name VARCHAR(150) NOT NULL,
        course VARCHAR(150) NULL,
        programme VARCHAR(150) NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_registry_registration (registration_number),
        UNIQUE KEY uq_student_registry_card_number (card_number),
        INDEX idx_student_registry_name (full_name),
        INDEX idx_student_registry_course (course),
        INDEX idx_student_registry_programme (programme)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'staff_accounts' => "CREATE TABLE IF NOT EXISTS staff_accounts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'id_applications' => "CREATE TABLE IF NOT EXISTS id_applications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        application_type ENUM('new','renewal','lost','damaged') NOT NULL,
        reason VARCHAR(1000) NULL,
        surname VARCHAR(100) NULL,
        given_name VARCHAR(150) NULL,
        nationality VARCHAR(80) NULL,
        sex VARCHAR(20) NULL,
        card_reg_no VARCHAR(80) NULL,
        date_admitted DATE NULL,
        faculty_id INT UNSIGNED NULL,
        course_id INT UNSIGNED NULL,
        graduation_year SMALLINT UNSIGNED NULL,
        source_photo_path VARCHAR(255) NULL,
        photo_path VARCHAR(255) NOT NULL,
        signature_path VARCHAR(255) NOT NULL,
        application_status ENUM('submitted','under_review','approved','rejected','ready_to_print','printed','issued') NOT NULL DEFAULT 'submitted',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_applications_student (student_id, created_at),
        INDEX idx_applications_status (application_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'student_faculties' => "CREATE TABLE IF NOT EXISTS student_faculties (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_faculties_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'student_courses' => "CREATE TABLE IF NOT EXISTS student_courses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_courses_faculty_name (faculty_id, name),
        INDEX idx_student_courses_status (faculty_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'system_settings' => "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_by INT UNSIGNED NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'admin_announcements' => "CREATE TABLE IF NOT EXISTS admin_announcements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin_announcements_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'admin_notifications' => "CREATE TABLE IF NOT EXISTS admin_notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        recipient_role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
        title VARCHAR(180) NOT NULL,
        message VARCHAR(1000) NOT NULL,
        notification_type VARCHAR(40) NOT NULL DEFAULT 'system',
        reference_type VARCHAR(40) NULL,
        reference_id BIGINT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        read_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_notifications_inbox (recipient_role, read_at, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'payments' => "CREATE TABLE IF NOT EXISTS payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        application_id BIGINT UNSIGNED NOT NULL UNIQUE,
        reference VARCHAR(120) NULL,
        amount DECIMAL(12,2) NULL,
        receipt_path VARCHAR(255) NULL,
        payment_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
        verified_by INT UNSIGNED NULL,
        verified_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'id_cards' => "CREATE TABLE IF NOT EXISTS id_cards (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        application_id BIGINT UNSIGNED NOT NULL UNIQUE,
        student_id INT UNSIGNED NOT NULL,
        card_number VARCHAR(50) NULL UNIQUE,
        card_status ENUM('ready_to_print','printed','issued') NOT NULL DEFAULT 'ready_to_print',
        verification_token CHAR(48) NULL UNIQUE,
        generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        printed_at DATETIME NULL,
        issued_at DATETIME NULL,
        expires_at DATE NULL,
        INDEX idx_cards_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'application_events' => "CREATE TABLE IF NOT EXISTS application_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        application_id BIGINT UNSIGNED NOT NULL,
        actor_id INT UNSIGNED NOT NULL,
        actor_role VARCHAR(20) NOT NULL,
        action VARCHAR(80) NOT NULL,
        details VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_events_application (application_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'student_service_requests' => "CREATE TABLE IF NOT EXISTS student_service_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        service_key VARCHAR(40) NOT NULL,
        subject VARCHAR(180) NOT NULL,
        details TEXT NOT NULL,
        request_status ENUM('submitted','in_review','completed','rejected') NOT NULL DEFAULT 'submitted',
        staff_response TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_service_requests_student (student_id, created_at),
        INDEX idx_service_requests_status (request_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'system_audit_log' => "CREATE TABLE IF NOT EXISTS system_audit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        user_role VARCHAR(20) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details VARCHAR(1000) NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_system_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'activity_logs' => "CREATE TABLE IF NOT EXISTS activity_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        activity VARCHAR(100) NOT NULL,
        details VARCHAR(1000) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_logs_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'login_logs' => "CREATE TABLE IF NOT EXISTS login_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(80) NOT NULL,
        login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_login_logs_student (student_id, login_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'remember_tokens' => "CREATE TABLE IF NOT EXISTS remember_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_remember_tokens_user (user_id),
        UNIQUE KEY uq_remember_tokens_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

try {
    foreach ($tables as $table => $createSql) {
        $conn->query($createSql);
        echo "Checked table $table\n";
    }

    migrationEnsureColumn($conn, 'students', 'username', 'VARCHAR(80) NULL');
    migrationEnsureColumn($conn, 'students', 'gender', 'VARCHAR(20) NULL');
    migrationEnsureColumn($conn, 'students', 'phone', 'VARCHAR(50) NULL');
    migrationEnsureColumn($conn, 'students', 'program', 'VARCHAR(150) NULL');
    migrationEnsureColumn($conn, 'students', 'security_question', 'VARCHAR(20) NULL');
    migrationEnsureColumn($conn, 'students', 'security_answer', 'VARCHAR(255) NULL');
    migrationEnsureColumn($conn, 'students', 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
    migrationEnsureColumn($conn, 'students', 'registration_date', 'DATETIME NULL');
    migrationEnsureColumn($conn, 'students', 'last_login', 'DATETIME NULL');
    migrationEnsureColumn($conn, 'student_registry', 'card_number', 'VARCHAR(50) NULL');
    migrationEnsureUniqueIndex($conn, 'student_registry', 'card_number', 'uq_student_registry_card_number');
    migrationEnsureColumn($conn, 'payments', 'receipt_path', 'VARCHAR(255) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'surname', 'VARCHAR(100) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'given_name', 'VARCHAR(150) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'nationality', 'VARCHAR(80) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'sex', 'VARCHAR(20) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'card_reg_no', 'VARCHAR(80) NULL');
    migrationEnsureColumn($conn, 'id_applications', 'date_admitted', 'DATE NULL');
    migrationEnsureColumn($conn, 'id_applications', 'faculty_id', 'INT UNSIGNED NULL');
    migrationEnsureColumn($conn, 'id_applications', 'course_id', 'INT UNSIGNED NULL');
    migrationEnsureColumn($conn, 'id_applications', 'graduation_year', 'SMALLINT UNSIGNED NULL');
    migrationEnsureColumn($conn, 'id_applications', 'source_photo_path', 'VARCHAR(255) NULL');
    migrationEnsureColumn($conn, 'id_cards', 'verification_token', 'CHAR(48) NULL UNIQUE');
    migrationEnsureNullableCardNumber($conn);
    $missingCardTokens = $conn->query('SELECT id FROM id_cards WHERE verification_token IS NULL');
    $tokenUpdate = $conn->prepare('UPDATE id_cards SET verification_token = ? WHERE id = ? AND verification_token IS NULL');
    while ($missingCard = $missingCardTokens->fetch_assoc()) {
        $verificationToken = bin2hex(random_bytes(24));
        $missingCardId = (int)$missingCard['id'];
        $tokenUpdate->bind_param('si', $verificationToken, $missingCardId);
        $tokenUpdate->execute();
    }
    $tokenUpdate->close();

    migrationEnsureUniqueIndex($conn, 'students', 'student_id', 'uq_students_student_id');
    migrationEnsureUniqueIndex($conn, 'students', 'username', 'uq_students_username');
    migrationEnsureUniqueIndex($conn, 'students', 'email', 'uq_students_email');
    migrationEnsureUniqueIndex($conn, 'remember_tokens', 'user_id', 'uq_remember_tokens_user');

    echo "Database migration completed. Existing records were preserved.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Database migration stopped: " . $exception->getMessage() . "\n");
    exit(1);
}
