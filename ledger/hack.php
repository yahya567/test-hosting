<?php
// Local DB admin (configurable hard-coded credentials and optional write actions)
// WARNING: Storing credentials in files is insecure for production. This file is intended for local development only.

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

// -- Configuration (edit here if you prefer hard-coded values) -----------------
$CONFIG = [
    // Set to true to allow actions that modify server state (backup/export files).
    'ALLOW_WRITE' => true,

    // Database connection (hard-coded as requested)
    'DB_HOST' => '196.188.169.49',
    'DB_PORT' => '1433',
    'DB_NAME' => 'E-CRMIS-2023-PRO-API',
    'DB_USER' => 'sa',
    'DB_PASS' => 'p@55w0rd',

    // Admin credentials (hard-coded). Consider changing before using on any network-accessible host.
    'ADMIN_USER' => 'admin',
    'ADMIN_PASS' => 'changeme',

    // Backup directory (must be writable by SQL Server service if using BACKUP DATABASE)
    'BACKUP_DIR' => __DIR__ . '/backups',
];
// ------------------------------------------------------------------------------

// Only allow requests from localhost (development-only guard)
// $remote = $_SERVER['REMOTE_ADDR'] ?? '';
// if (!in_array($remote, ['127.0.0.1', '::1'])) {
//     http_response_code(403);
//     echo "Access denied. This admin interface is restricted to localhost.";
//     exit;
// }

// Basic auth using the hard-coded admin credentials
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Local DB Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}
if (!hash_equals($CONFIG['ADMIN_USER'], $_SERVER['PHP_AUTH_USER']) || !hash_equals($CONFIG['ADMIN_PASS'], $_SERVER['PHP_AUTH_PW'])) {
    http_response_code(403);
    echo 'Invalid credentials.';
    exit;
}

// Helper for safe output
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Build DSN and connect via PDO
$host = $CONFIG['DB_HOST'];
$port = $CONFIG['DB_PORT'];
$database = $CONFIG['DB_NAME'];
$dbUser = $CONFIG['DB_USER'];
$dbPass = $CONFIG['DB_PASS'];

$dsn = "sqlsrv:Server={$host},{$port};Database={$database};TrustServerCertificate=1";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Failed to connect to database: " . h($e->getMessage());
    exit;
}

// Ensure backup dir exists when needed
if ($CONFIG['ALLOW_WRITE']) {
    if (!is_dir($CONFIG['BACKUP_DIR'])) {
        @mkdir($CONFIG['BACKUP_DIR'], 0755, true);
    }
}

// CSRF token for state-changing actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Fetch list of tables
try {
    $stmt = $pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE','VIEW') ORDER BY TABLE_SCHEMA, TABLE_NAME");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error fetching tables: " . h($e->getMessage());
    exit;
}

// Build whitelist map for validation
$tableWhitelist = [];
foreach ($tables as $t) {
    $full = $t['TABLE_SCHEMA'] . '.' . $t['TABLE_NAME'];
    $tableWhitelist[$full] = $t;
    // short name only if unique
    if (!isset($tableWhitelist[$t['TABLE_NAME']])) {
        $tableWhitelist[$t['TABLE_NAME']] = $t;
    }
}

// Handle POST actions (backup/export) with CSRF and ALLOW_WRITE guard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        echo "<p style='color:red;'>Invalid CSRF token.</p>";
    } else {
        if ($action === 'backup_db') {
            if (!$CONFIG['ALLOW_WRITE']) {
                echo "<p>Write actions are disabled by configuration.</p>";
            } else {
                // Perform backup (server must have permissions)
                $timestamp = date('Ymd_His');
                $safeDb = preg_replace('/[^A-Za-z0-9_\-]/', '_', $database);
                $backupFile = $CONFIG['BACKUP_DIR'] . DIRECTORY_SEPARATOR . $safeDb . "_backup_{$timestamp}.bak";
                // SQL Server expects Windows-style paths when running on Windows; leave to user to set proper BACKUP_DIR
                $sql = "BACKUP DATABASE [" . str_replace(']', ']]', $database) . "] TO DISK = N'" . addslashes($backupFile) . "' WITH FORMAT, INIT, NAME = 'Full Backup of " . addslashes($database) . "'";
                try {
                    $pdo->exec($sql);
                    echo "<p style='color:green;'>Backup command issued. File: " . h($backupFile) . "</p>";
                } catch (PDOException $e) {
                    echo "<p style='color:red;'>Backup failed: " . h($e->getMessage()) . "</p>";
                }
            }
        } elseif ($action === 'export_table') {
            $table = $_POST['table'] ?? '';
            if (!isset($tableWhitelist[$table])) {
                echo "<p style='color:red;'>Invalid table.</p>";
            } else {
                $meta = $tableWhitelist[$table];
                $schema = $meta['TABLE_SCHEMA'];
                $name = $meta['TABLE_NAME'];
                $identifier = '[' . str_replace(']', ']]', $schema) . '].[' . str_replace(']', ']]', $name) . ']';
                $limit = min(10000, max(1, intval($_POST['limit'] ?? 1000)));
                try {
                    $sql = "SELECT TOP {$limit} * FROM {$identifier}";
                    $stmt = $pdo->query($sql);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    // Output CSV for download
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $name . '.csv"');
                    $out = fopen('php://output', 'w');
                    if (!empty($rows)) {
                        fputcsv($out, array_keys($rows[0]));
                        foreach ($rows as $r) {
                            // convert objects to strings
                            $line = array_map(function($v){ return is_object($v) ? (string)$v : $v; }, $r);
                            fputcsv($out, $line);
                        }
                    }
                    fclose($out);
                    exit;
                } catch (PDOException $e) {
                    echo "<p style='color:red;'>Export failed: " . h($e->getMessage()) . "</p>";
                }
            }
        }
    }
}

// Simple HTML interface
echo "<h2>Local DB Admin</h2>";
echo "<p>Connected to database: <strong>" . h($database) . "</strong></p>";
echo "<p>Write actions: " . ($CONFIG['ALLOW_WRITE'] ? '<strong style="color:green">ENABLED</strong>' : '<strong style="color:orange">DISABLED</strong>') . "</p>";

// Table list with view/export options
echo "<h3>Tables</h3>\n";
echo "<table border=1 cellpadding=6>\n";
echo "<tr><th>Schema</th><th>Name</th><th>Type</th><th>Actions</th></tr>\n";
foreach ($tables as $t) {
    $schema = $t['TABLE_SCHEMA'];
    $name = $t['TABLE_NAME'];
    $type = $t['TABLE_TYPE'];
    $full = $schema . '.' . $name;
    echo "<tr>";
    echo "<td>" . h($schema) . "</td>";
    echo "<td>" . h($name) . "</td>";
    echo "<td>" . h($type) . "</td>";
    echo "<td>";
    echo "<a href='?action=view_table&table=" . rawurlencode($full) . "&limit=10'>View (10)</a> ";
    if ($CONFIG['ALLOW_WRITE']) {
        echo "| <form style='display:inline' method='post'>";
        echo "<input type='hidden' name='csrf' value='" . h($csrf) . "'>";
        echo "<input type='hidden' name='action' value='export_table'>";
        echo "<input type='hidden' name='table' value='" . h($full) . "'>";
        echo "<button type='submit'>Export CSV</button>";
        echo "</form>";
    }
    echo "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// View table data (read-only query for display)
if (isset($_GET['action']) && $_GET['action'] === 'view_table' && isset($_GET['table'])) {
    $requested = $_GET['table'];
    $limit = min(1000, max(1, intval($_GET['limit'] ?? 10)));
    if (!isset($tableWhitelist[$requested])) {
        echo "<p style='color: red;'>Invalid or unauthorized table requested.</p>";
    } else {
        $meta = $tableWhitelist[$requested];
        $schema = $meta['TABLE_SCHEMA'];
        $name = $meta['TABLE_NAME'];
        $identifier = '[' . str_replace(']', ']]', $schema) . '].[' . str_replace(']', ']]', $name) . ']';
        try {
            $sql = "SELECT TOP {$limit} * FROM {$identifier}";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>Data: " . h($schema) . "." . h($name) . " (first " . h($limit) . ")</h3>";
            if (empty($rows)) {
                echo "<p>No rows returned.</p>";
            } else {
                echo "<table border=1 cellpadding=6>\n<tr>";
                foreach (array_keys($rows[0]) as $col) { echo "<th>" . h($col) . "</th>"; }
                echo "</tr>\n";
                foreach ($rows as $r) {
                    echo "<tr>";
                    foreach ($r as $v) { echo "<td>" . ($v === null ? '<i>NULL</i>' : h($v)) . "</td>"; }
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Query failed: " . h($e->getMessage()) . "</p>";
        }
    }
}

// Backup form (if writes allowed)
if ($CONFIG['ALLOW_WRITE']) {
    echo "<h3>Database Actions</h3>";
    echo "<form method='post' onsubmit='return confirm(" . json_encode("Issue backup? This runs BACKUP DATABASE on the server and requires permissions.") . ")'>";
    echo "<input type='hidden' name='csrf' value='" . h($csrf) . "'>";
    echo "<input type='hidden' name='action' value='backup_db'>";
    echo "<button type='submit'>Create Backup</button>";
    echo "</form>";
}

// Close connection
$pdo = null;
?>