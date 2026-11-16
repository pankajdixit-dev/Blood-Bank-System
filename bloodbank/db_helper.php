<?php
/**
 * Database Auto-Setup Helper
 * Automatically seeds Aiven Cloud MySQL database with required tables and default admin account
 */

function auto_import_db($conn, $db_name = '') {
    if (!$conn || !($conn instanceof mysqli) || !empty($conn->connect_error)) {
        return false;
    }

    if (empty($db_name)) {
        $db_name = getenv('DB_NAME') ?: 'defaultdb';
    }

    // Locate SQL Dump File
    $sql_paths = [
        __DIR__ . '/database/bloodbank_db.sql',
        __DIR__ . '/bloodbank/database/bloodbank_db.sql',
        __DIR__ . '/bloodbank_db.sql'
    ];
    $sql_file = '';
    foreach ($sql_paths as $p) {
        if (file_exists($p)) {
            $sql_file = $p;
            break;
        }
    }
    if (empty($sql_file)) {
        return false;
    }

    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        return false;
    }

    // Select database
    @$conn->select_db($db_name);
    @$conn->query("SET FOREIGN_KEY_CHECKS = 0");
    @$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    // Clean parse and execute statements
    $lines = explode("\n", $sql_content);
    $query = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed == '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        $query .= $line . "\n";
        if (substr(rtrim($query), -1) == ';') {
            @$conn->query($query);
            $query = '';
        }
    }

    // Ensure default admin exists
    $admin_check = @$conn->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
    if (!$admin_check || $admin_check->num_rows == 0) {
        $default_pass = md5('admin123');
        @$conn->query("INSERT INTO users (`name`, `username`, `password`, `type`) VALUES ('Administrator', 'admin', '{$default_pass}', 1)");
    }

    @$conn->query("SET FOREIGN_KEY_CHECKS = 1");
    return true;
}
