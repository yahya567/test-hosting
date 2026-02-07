<?php
echo "<h2>SQL Server Connection Test</h2>";

// Test database credentials
// $server = "host.docker.internal"; // For Windows/Mac Docker
// $server = "172.17.0.1"; // For Linux Docker (docker0 bridge)
$server = "196.188.169.49"; // Your test server from appsettings.json
$database = "E-CRMIS-2023-PRO-API";
$username = "sa";
$password = "p@55w0rd";

// Test different connection methods
echo "<h3>Testing Connection Methods:</h3>";

// Method 1: sqlsrv
if (extension_loaded('sqlsrv')) {
    echo "1. Testing sqlsrv extension...<br>";
    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true
    );
    
    $conn = sqlsrv_connect($server, $connectionInfo);
    if ($conn) {
        echo "<span style='color: green;'>✓ sqlsrv connection successful!</span><br>";
        
        $sql = "SELECT @@VERSION as version";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            echo "SQL Server Version: " . substr($row['version'], 0, 100) . "...<br>";
        }
        sqlsrv_close($conn);
    } else {
        echo "<span style='color: red;'>✗ sqlsrv failed: ";
        print_r(sqlsrv_errors());
        echo "</span><br>";
    }
} else {
    echo "✗ sqlsrv extension not loaded<br>";
}

// Method 2: PDO SQLSRV
if (extension_loaded('pdo_sqlsrv')) {
    echo "<br>2. Testing PDO SQLSRV...<br>";
    try {
        $dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1";
        $conn = new PDO($dsn, $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span style='color: green;'>✓ PDO SQLSRV connection successful!</span><br>";
        
        $stmt = $conn->query("SELECT DB_NAME() as db, CURRENT_USER as user");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Database: " . $row['db'] . "<br>";
        echo "User: " . $row['user'] . "<br>";
    } catch (PDOException $e) {
        echo "<span style='color: red;'>✗ PDO SQLSRV failed: " . $e->getMessage() . "</span><br>";
    }
}

// Method 3: PDO ODBC
if (extension_loaded('pdo_odbc')) {
    echo "<br>3. Testing PDO ODBC...<br>";
    try {
        $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$server;Database=$database;TrustServerCertificate=1";
        $conn = new PDO($dsn, $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span style='color: green;'>✓ PDO ODBC connection successful!</span><br>";
    } catch (PDOException $e) {
        echo "<span style='color: red;'>✗ PDO ODBC failed: " . $e->getMessage() . "</span><br>";
    }
}

// Show loaded extensions
echo "<h3>Loaded Extensions:</h3>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<pre>" . implode("\n", $extensions) . "</pre>";

// Show PDO drivers
echo "<h3>PDO Drivers:</h3>";
if (extension_loaded('pdo')) {
    echo "<pre>" . print_r(PDO::getAvailableDrivers(), true) . "</pre>";
}
?>