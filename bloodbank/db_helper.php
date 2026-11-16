<?php
/**
 * Database Auto-Setup Helper
 * Automatically seeds Aiven Cloud MySQL database with required tables and default admin account.
 * Fully compatible with Aiven's strict sql_require_primary_key=ON policy.
 */

function auto_import_db($conn, $db_name = '') {
    if (!$conn || !($conn instanceof mysqli) || !empty($conn->connect_error)) {
        return false;
    }

    if (empty($db_name)) {
        $db_name = getenv('DB_NAME') ?: 'defaultdb';
    }

    @$conn->select_db($db_name);
    @$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    @$conn->query("SET SESSION sql_require_primary_key = 0;");
    @$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

    // Table 1: blood_inventory (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `blood_inventory` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `blood_group` varchar(10) NOT NULL,
      `volume` float NOT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = in -stock,2=out',
      `donor_id` int(30) NOT NULL,
      `request_id` int(30) NOT NULL,
      `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table 2: donors (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `donors` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `blood_group` varchar(10) NOT NULL,
      `name` text NOT NULL,
      `address` text NOT NULL,
      `contact` varchar(20) NOT NULL,
      `email` varchar(50) NOT NULL,
      `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table 3: handedover_request (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `handedover_request` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `request_id` int(30) NOT NULL,
      `picked_up_by` text NOT NULL,
      `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table 4: requests (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `requests` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `ref_code` varchar(20) NOT NULL,
      `patient` text NOT NULL,
      `blood_group` varchar(10) NOT NULL,
      `volume` float NOT NULL,
      `physician_name` text NOT NULL,
      `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0= pending,1= approved',
      `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table 5: system_settings (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `name` text NOT NULL,
      `email` varchar(200) NOT NULL,
      `contact` varchar(20) NOT NULL,
      `cover_img` text NOT NULL,
      `about_content` text NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Table 6: users (Explicit PRIMARY KEY for Aiven MySQL)
    @$conn->query("CREATE TABLE IF NOT EXISTS `users` (
      `id` int(30) NOT NULL AUTO_INCREMENT,
      `name` text NOT NULL,
      `username` varchar(200) NOT NULL,
      `password` text NOT NULL,
      `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Admin,2=Staff, 3= subscriber',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insert Default System Settings if empty
    $sys_check = @$conn->query("SELECT * FROM system_settings LIMIT 1");
    if (!$sys_check || $sys_check->num_rows == 0) {
        @$conn->query("INSERT INTO `system_settings` (`id`, `name`, `email`, `contact`, `cover_img`, `about_content`) VALUES
        (1, 'Blood Bank Management System', 'info@sample.comm', '+6948 8542 623', 'blood-cells.jpg', 'Blood Bank Management System');");
    }

    // Insert or Reset Default Admin User (admin / admin123)
    $default_pass = md5('admin123'); // 0192023a7bbd73250516f069df18b500
    $admin_check = @$conn->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
    if (!$admin_check || $admin_check->num_rows == 0) {
        @$conn->query("INSERT INTO `users` (`name`, `username`, `password`, `type`) VALUES ('Administrator', 'admin', '{$default_pass}', 1);");
    } else {
        @$conn->query("UPDATE `users` SET `password` = '{$default_pass}', `type` = 1 WHERE `username` = 'admin';");
    }

    // Seed Sample Donors and Blood Inventory if empty
    $inv_check = @$conn->query("SELECT * FROM blood_inventory LIMIT 1");
    if (!$inv_check || $inv_check->num_rows == 0) {
        @$conn->query("INSERT IGNORE INTO `donors` (`id`, `blood_group`, `name`, `address`, `contact`, `email`, `date_created`) VALUES
        (1, 'AB+', 'John Smith', 'Sample Address', '+18456-5455-55', 'jsmith@sample.com', '2020-10-23 09:25:57'),
        (2, 'B-', 'George Wilson', 'Sample address', '8747808787', 'gwilson@sample.com', '2020-10-23 09:27:54'),
        (3, 'O+', 'Claire Blake', 'Sample Address', '+6948 8542 623', 'cblake@sample.com', '2020-10-23 09:28:14');");

        @$conn->query("INSERT IGNORE INTO `blood_inventory` (`id`, `blood_group`, `volume`, `status`, `donor_id`, `request_id`, `date_created`) VALUES
        (1, 'O+', 450, 1, 3, 0, '2020-10-23 00:00:00'),
        (2, 'AB+', 450, 1, 1, 0, '2020-08-05 00:00:00'),
        (3, 'B-', 450, 1, 2, 0, '2020-10-01 00:00:00');");
    }

    @$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    return true;
}
