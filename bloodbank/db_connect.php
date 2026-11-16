<?php
/**
 * Database Connection File
 * Upgraded for Aiven Cloud MySQL (SSL requirement & 5-digit ports) and PHP 8.x
 */

// Disable MySQLi exception throwing to prevent fatal Uncaught mysqli_sql_exception in PHP 8.1+
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Enable error logging while keeping display safe
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Read Environment Variables with sensible defaults
$db_server   = getenv('DB_SERVER') ?: getenv('DB_HOST') ?: 'localhost';
$db_port     = (int)(getenv('DB_PORT') ?: 3306);
$db_user     = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root';
$db_pass     = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$db_name     = getenv('DB_NAME') ?: 'bloodbank_db';
$db_ssl_env  = getenv('DB_SSL');

// Determine SSL Requirement:
// Aiven MySQL enforces SSL (require_secure_transport=ON).
// Auto-detect SSL if connecting to an Aiven hostname, remote custom port, or DB_SSL flag is set.
$use_ssl = false;
if ($db_ssl_env !== false && $db_ssl_env !== '') {
    $use_ssl = in_array(strtolower((string)$db_ssl_env), ['1', 'true', 'yes', 'on', 'require'], true);
} else {
    if (strpos($db_server, 'aivencloud.com') !== false || ($db_port !== 3306 && $db_server !== 'localhost' && $db_server !== '127.0.0.1')) {
        $use_ssl = true;
    }
}

// Initialize mysqli with mysqli_init()
$conn = mysqli_init();

if (!$conn) {
    die("Database Connection Error: mysqli_init failed.");
}

// Connection timeout (10 seconds)
if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
}

// Configure SSL if required
$client_flags = 0;
if ($use_ssl) {
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        // Enforce encrypted transport while allowing connection without mandatory local CA file
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }
    if (function_exists('mysqli_ssl_set')) {
        @mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    }
    if (defined('MYSQLI_CLIENT_SSL')) {
        $client_flags |= MYSQLI_CLIENT_SSL;
    }
}

// Establish database connection
$connected = @$conn->real_connect($db_server, $db_user, $db_pass, $db_name, $db_port, null, $client_flags);

// Fallback 1: If SSL failed and SSL wasn't explicitly forced via DB_SSL env, try standard connection
if (!$connected && $use_ssl && $db_ssl_env === false) {
    $conn = mysqli_init();
    if ($conn) {
        $connected = @$conn->real_connect($db_server, $db_user, $db_pass, $db_name, $db_port);
    }
}

// Fallback 2: If database does not exist yet (error 1049), connect without db name so installer can run
if (!$connected && $conn->connect_errno == 1049) {
    $conn = mysqli_init();
    if ($use_ssl && defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }
    @$conn->real_connect($db_server, $db_user, $db_pass, null, $db_port, null, $client_flags);
}

// Connection validation
if ($conn->connect_error) {
    $db_connection_error = $conn->connect_error;
    // Don't kill execution if current page is db_setup.php
    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($current_script !== 'db_setup.php') {
        error_log("Database connection failed: " . $conn->connect_error);
    }
} else {
    $conn->set_charset("utf8mb4");
}
