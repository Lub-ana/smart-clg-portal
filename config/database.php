<?php
/**
 * Smart College Management Portal
 * Database Configuration & PDO Connection
 *
 * Designed for standard XAMPP environment (Apache + MySQL/MariaDB)
 */

// Database credentials
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'smart_clg_portal');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Application metadata
if (!defined('APP_NAME')) define('APP_NAME', 'Smart College Management Portal');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Global PDO holder and connection error holder
$pdo = null;
$db_connection_error = null;

/**
 * Returns a shared PDO database connection instance.
 *
 * @return PDO|null Returns PDO on success, or null on failure.
 */
function getDBConnection(): ?PDO {
    global $pdo, $db_connection_error;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $db_connection_error = null;
        return $pdo;
    } catch (PDOException $e) {
        $db_connection_error = $e->getMessage();
        // Log error in production or debug environments
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Helper to check connection status and test server connectivity.
 *
 * @return array Array containing status (bool), message (string), and details (array)
 */
function testDatabaseConnection(): array {
    global $db_connection_error;

    $conn = getDBConnection();
    if ($conn instanceof PDO) {
        try {
            $stmt = $conn->query("SELECT VERSION() AS version, DATABASE() AS db_name");
            $info = $stmt->fetch();
            return [
                'status'  => true,
                'message' => 'Successfully connected to database "' . htmlspecialchars(DB_NAME) . '".',
                'details' => [
                    'database' => $info['db_name'] ?? DB_NAME,
                    'version'  => $info['version'] ?? 'Unknown',
                    'host'     => DB_HOST . ':' . DB_PORT,
                    'user'     => DB_USER,
                ]
            ];
        } catch (PDOException $e) {
            return [
                'status'  => false,
                'message' => 'Connected to MySQL server, but querying failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }

    return [
        'status'  => false,
        'message' => 'Unable to connect to database "' . DB_NAME . '". Error: ' . ($db_connection_error ?: 'Unknown error'),
        'details' => []
    ];
}

// Initialize connection for scripts that include this config file
$pdo = getDBConnection();
