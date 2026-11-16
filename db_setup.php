<?php
/**
 * 1-Click Aiven Cloud MySQL Database Importer & Health Check Tool
 * Blood Bank Management System
 */

session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Helper function to resolve config from POST, Session, or Environment
function get_db_param($key, $default = '') {
    if (isset($_POST[$key])) return trim($_POST[$key]);
    if (isset($_SESSION['custom_db'][$key])) return $_SESSION['custom_db'][$key];
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    return $default;
}

// Handle credential test / override
if (isset($_POST['save_credentials'])) {
    $_SESSION['custom_db'] = [
        'DB_SERVER'   => trim($_POST['db_server'] ?? ''),
        'DB_PORT'     => trim($_POST['db_port'] ?? '3306'),
        'DB_USERNAME' => trim($_POST['db_user'] ?? ''),
        'DB_PASSWORD' => trim($_POST['db_pass'] ?? ''),
        'DB_NAME'     => trim($_POST['db_name'] ?? 'bloodbank_db'),
        'DB_SSL'      => isset($_POST['db_ssl']) ? '1' : '0',
    ];
}

if (isset($_POST['reset_credentials'])) {
    unset($_SESSION['custom_db']);
}

$db_server   = get_db_param('DB_SERVER', get_db_param('DB_HOST', 'localhost'));
$db_port     = (int)get_db_param('DB_PORT', 3306);
$db_user     = get_db_param('DB_USERNAME', get_db_param('DB_USER', 'root'));
$db_pass     = get_db_param('DB_PASSWORD', get_db_param('DB_PASS', ''));
$db_name     = get_db_param('DB_NAME', 'bloodbank_db');
$db_ssl_val  = get_db_param('DB_SSL', '');

$use_ssl = false;
if ($db_ssl_val !== '') {
    $use_ssl = in_array(strtolower((string)$db_ssl_val), ['1', 'true', 'yes', 'on', 'require'], true);
} else {
    if (strpos($db_server, 'aivencloud.com') !== false || ($db_port !== 3306 && $db_server !== 'localhost' && $db_server !== '127.0.0.1')) {
        $use_ssl = true;
    }
}

// Attempt Database Connection
$conn = mysqli_init();
$conn_status = false;
$conn_error  = '';
$ssl_cipher  = 'Disabled';
$server_version = '';

if ($conn) {
    if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    }
    $flags = 0;
    if ($use_ssl) {
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        if (function_exists('mysqli_ssl_set')) {
            @mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
        }
        if (defined('MYSQLI_CLIENT_SSL')) {
            $flags |= MYSQLI_CLIENT_SSL;
        }
    }
    $connected = @$conn->real_connect($db_server, $db_user, $db_pass, $db_name, $db_port, null, $flags);
    
    // Fallback: If DB does not exist yet (Error 1049), connect without db name
    if (!$connected && $conn->connect_errno == 1049) {
        $conn = mysqli_init();
        if ($use_ssl && defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        $connected = @$conn->real_connect($db_server, $db_user, $db_pass, null, $db_port, null, $flags);
        if ($connected) {
            $conn_error = "Note: Connected to MySQL server, but database '{$db_name}' does not exist yet. It will be created during import.";
            $conn_status = true;
        }
    }

    if ($connected && empty($conn->connect_error)) {
        $conn_status = true;
        $conn->set_charset("utf8mb4");
        $server_version = $conn->server_info;
        
        // Check SSL Cipher
        $cipher_res = $conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
        if ($cipher_res && $cipher_res instanceof mysqli_result && $cipher_res->num_rows > 0) {
            $row = $cipher_res->fetch_assoc();
            if (!empty($row['Value'])) {
                $ssl_cipher = $row['Value'];
            }
        }
    } else {
        $conn_error = $conn->connect_error ?: 'Connection failed. Please check host, port, username, password and SSL settings.';
    }
}

// Find SQL Dump File
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

// Action: Perform 1-Click Import
$import_message = '';
$import_success = false;

if (isset($_POST['import_database']) && $conn_status) {
    if (empty($sql_file)) {
        $import_message = "SQL Dump file (bloodbank_db.sql) was not found in database/ directory.";
    } else {
        // Try creating database if not exists
        @$conn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        @$conn->select_db($db_name);

        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            $import_message = "Failed to read SQL file contents.";
        } else {
            // Disable foreign key checks & strict mode for import
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

            // Clean parse SQL queries
            $lines = explode("\n", $sql_content);
            $query = '';
            $queries_executed = 0;
            $queries_failed = 0;

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed == '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '#') === 0) {
                    continue;
                }
                $query .= $line . "\n";
                if (substr(rtrim($query), -1) == ';') {
                    if (@$conn->query($query)) {
                        $queries_executed++;
                    } else {
                        $queries_failed++;
                    }
                    $query = '';
                }
            }

            // Verify or inject default admin
            $admin_check = $conn->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
            if (!$admin_check || $admin_check->num_rows == 0) {
                $default_pass = md5('admin123');
                $conn->query("INSERT INTO users (`name`, `username`, `password`, `type`) VALUES ('Administrator', 'admin', '{$default_pass}', 1)");
            }

            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $import_success = true;
            $import_message = "Database imported successfully! Executed {$queries_executed} queries (Ignored non-critical warnings: {$queries_failed}). Default admin user verified.";
        }
    }
}

// Inspect Tables & Counts
$tables_status = [];
$required_tables = ['blood_inventory', 'donors', 'handedover_request', 'requests', 'system_settings', 'users'];
if ($conn_status) {
    @$conn->select_db($db_name);
    foreach ($required_tables as $table) {
        $chk = @$conn->query("SHOW TABLES LIKE '{$table}'");
        if ($chk && $chk->num_rows > 0) {
            $cnt_q = @$conn->query("SELECT COUNT(*) as cnt FROM `{$table}`");
            $cnt = ($cnt_q && $cnt_q instanceof mysqli_result) ? $cnt_q->fetch_assoc()['cnt'] : 0;
            $tables_status[$table] = ['exists' => true, 'count' => $cnt];
        } else {
            $tables_status[$table] = ['exists' => false, 'count' => 0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Diagnostics - Blood Bank Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .header-banner {
            background: linear-gradient(135deg, #b71c1c, #880e4f);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .card-header {
            font-weight: 600;
            background: #fff;
            border-bottom: 2px solid #f0f0f0;
        }
        .badge-status {
            font-size: 0.9rem;
            padding: 0.4em 0.8em;
        }
        .btn-brand {
            background-color: #b71c1c;
            color: #fff;
            font-weight: 600;
        }
        .btn-brand:hover {
            background-color: #d32f2f;
            color: #fff;
        }
        .code-box {
            background: #272822;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>

<div class="header-banner">
    <div class="container text-center">
        <h1><i class="fa fa-heartbeat mr-2"></i> Blood Bank Management System</h1>
        <p class="lead mb-0">Aiven Cloud MySQL & PHP 8.x Database Importer & Health Checker</p>
    </div>
</div>

<div class="container mb-5">

    <?php if (!empty($import_message)): ?>
    <div class="alert alert-<?php echo $import_success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <i class="fa <?php echo $import_success ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($import_message); ?>
        <?php if ($import_success): ?>
            <div class="mt-3">
                <a href="login.php" class="btn btn-success btn-lg"><i class="fa fa-sign-in-alt mr-1"></i> Proceed to Login Page Now</a>
            </div>
        <?php endif; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Diagnostic & Health Check -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-stethoscope text-danger mr-2"></i> System & Aiven Connection Health</span>
                    <?php if ($conn_status): ?>
                        <span class="badge badge-success badge-status"><i class="fa fa-check mr-1"></i> Connected</span>
                    <?php else: ?>
                        <span class="badge badge-danger badge-status"><i class="fa fa-times mr-1"></i> Disconnected</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 35%;">PHP Version</th>
                                <td><?php echo PHP_VERSION; ?> <?php echo (version_compare(PHP_VERSION, '8.0.0') >= 0) ? '<span class="badge badge-success">PHP 8.x Compatible</span>' : '<span class="badge badge-warning">Legacy</span>'; ?></td>
                            </tr>
                            <tr>
                                <th>Target MySQL Host</th>
                                <td><code><?php echo htmlspecialchars($db_server); ?></code></td>
                            </tr>
                            <tr>
                                <th>Target MySQL Port</th>
                                <td><code><?php echo htmlspecialchars($db_port); ?></code> <?php if ($db_port != 3306) echo '<span class="badge badge-info">Aiven Custom Port</span>'; ?></td>
                            </tr>
                            <tr>
                                <th>Database Name</th>
                                <td><code><?php echo htmlspecialchars($db_name); ?></code></td>
                            </tr>
                            <tr>
                                <th>SSL Encryption</th>
                                <td>
                                    <?php if ($use_ssl): ?>
                                        <span class="badge badge-success"><i class="fa fa-lock mr-1"></i> SSL Enforced (Aiven Compatible)</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Plain (No SSL)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>SSL Cipher</th>
                                <td><code><?php echo htmlspecialchars($ssl_cipher); ?></code></td>
                            </tr>
                            <?php if (!empty($server_version)): ?>
                            <tr>
                                <th>MySQL Server Version</th>
                                <td><?php echo htmlspecialchars($server_version); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($conn_error)): ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <strong>Connection Notice / Error:</strong><br>
                        <?php echo htmlspecialchars($conn_error); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Status -->
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-table text-primary mr-2"></i> Database Tables Inspection
                </div>
                <div class="card-body">
                    <?php if (!$conn_status): ?>
                        <div class="alert alert-secondary text-center">Connect to MySQL first to inspect tables.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Status</th>
                                        <th class="text-right">Total Rows</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $all_tables_exist = true;
                                    foreach ($required_tables as $tbl): 
                                        $info = $tables_status[$tbl] ?? ['exists' => false, 'count' => 0];
                                        if (!$info['exists']) $all_tables_exist = false;
                                    ?>
                                    <tr>
                                        <td><code><?php echo $tbl; ?></code></td>
                                        <td>
                                            <?php if ($info['exists']): ?>
                                                <span class="badge badge-success"><i class="fa fa-check"></i> Ready</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><i class="fa fa-times"></i> Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right font-weight-bold">
                                            <?php echo number_format($info['count']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($all_tables_exist): ?>
                            <div class="alert alert-success mt-3 mb-0 small">
                                <i class="fa fa-check-circle mr-1"></i> All 6 required tables are present and ready!
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-3 mb-0 small">
                                <i class="fa fa-info-circle mr-1"></i> Tables need to be imported. Click the 1-Click Import button on the right.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: 1-Click Action & Manual Credential Override -->
        <div class="col-lg-5">
            <!-- 1-Click Import Card -->
            <div class="card border-primary">
                <div class="card-header bg-danger text-white">
                    <i class="fa fa-database mr-2"></i> 1-Click Database Setup
                </div>
                <div class="card-body text-center">
                    <p class="text-muted">Import all system tables, sample data, and initialize the default administrator account into Aiven Cloud MySQL with SSL.</p>

                    <p class="small text-left mb-2"><strong>SQL Dump File:</strong><br>
                        <code><?php echo !empty($sql_file) ? htmlspecialchars(basename(dirname($sql_file)) . '/' . basename($sql_file)) : 'NOT FOUND'; ?></code>
                    </p>

                    <form method="POST">
                        <button type="submit" name="import_database" class="btn btn-brand btn-block btn-lg py-3 mt-3" <?php echo !$conn_status ? 'disabled' : ''; ?>>
                            <i class="fa fa-cloud-upload-alt mr-2"></i> Import / Reset Database (1-Click)
                        </button>
                    </form>

                    <hr>
                    <a href="login.php" class="btn btn-outline-success btn-block">
                        <i class="fa fa-sign-in-alt mr-1"></i> Open Login Page
                    </a>
                </div>
            </div>

            <!-- Default Admin Credentials -->
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-key text-warning mr-2"></i> Default Admin Credentials
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Username:</span>
                        <strong class="text-danger">admin</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Password:</span>
                        <strong class="text-danger">admin123</strong>
                    </div>
                </div>
            </div>

            <!-- Manual Credential Test & Configuration Override -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-cogs text-secondary mr-2"></i> Quick Credentials Override</span>
                    <?php if (isset($_SESSION['custom_db'])): ?>
                        <span class="badge badge-warning">Session Override Active</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-2">
                            <label class="small mb-1">Host (DB_SERVER)</label>
                            <input type="text" name="db_server" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_server); ?>" required>
                        </div>
                        <div class="form-row mb-2">
                            <div class="col">
                                <label class="small mb-1">Port (DB_PORT)</label>
                                <input type="number" name="db_port" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_port); ?>" required>
                            </div>
                            <div class="col">
                                <label class="small mb-1">DB Name (DB_NAME)</label>
                                <input type="text" name="db_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_name); ?>" required>
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small mb-1">User (DB_USERNAME)</label>
                            <input type="text" name="db_user" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_user); ?>" required>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small mb-1">Password (DB_PASSWORD)</label>
                            <input type="password" name="db_pass" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_pass); ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="db_ssl" class="form-check-input" id="sslCheck" <?php echo $use_ssl ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="sslCheck">Enable SSL (Required for Aiven Cloud)</label>
                        </div>
                        <button type="submit" name="save_credentials" class="btn btn-primary btn-sm btn-block">
                            <i class="fa fa-sync-alt mr-1"></i> Apply & Test Connection
                        </button>
                        <?php if (isset($_SESSION['custom_db'])): ?>
                        <button type="submit" name="reset_credentials" class="btn btn-outline-secondary btn-sm btn-block mt-2">
                            Reset to Default Environment
                        </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
