<?php
/**
 * TrackED Database Diagnostic Utility
 * File: api/db_test.php
 *
 * Diagnoses reachability, TCP port 3306 sockets, credentials, and SSL settings
 * for both Local Orange Pi MariaDB and Admin Server MySQL DB.
 */

date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_OFF);

$is_cli = (php_sapi_name() === 'cli');
define('SCANNER_KEY', 'tracked_secret_key');

// Guard against unauthenticated external requests when accessed via browser
if (!$is_cli) {
    $provided_key = $_GET['key'] ?? '';
    if ($provided_key !== SCANNER_KEY) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Unauthorized. Access key required (?key=...).\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "====================================================\n";
echo " TrackED Database Connectivity & Diagnostic Tool\n";
echo "====================================================\n";
echo "Run Time: " . date('Y-m-d H:i:s') . "\n\n";

$targets = [
    'Local Orange Pi Database' => [
        'host' => getenv('LOCAL_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('LOCAL_DB_PORT') ?: 3306),
        'user' => getenv('LOCAL_DB_USER') ?: 'root',
        'pass' => getenv('LOCAL_DB_PASS') ?: 'MyNewPassword123!',
        'db'   => getenv('LOCAL_DB_NAME') ?: 'tracked_local',
        'desc' => 'Local MariaDB service on Orange Pi board'
    ],
    'Admin Server Database' => [
        'host' => getenv('SERVER_DB_HOST') ?: '192.168.2.100',
        'port' => (int)(getenv('SERVER_DB_PORT') ?: 3306),
        'user' => getenv('SERVER_DB_USER') ?: 'tracked_user',
        'pass' => getenv('SERVER_DB_PASS') ?: 'MyNewPassword123!',
        'db'   => getenv('SERVER_DB_NAME') ?: 'tracked_db',
        'desc' => 'Central Admin Server DB over LAN'
    ]
];

foreach ($targets as $label => $cfg) {
    $host = $cfg['host'];
    $port = $cfg['port'];
    $user = $cfg['user'];
    $pass = $cfg['pass'];
    $db   = $cfg['db'];

    echo "----------------------------------------------------\n";
    echo "Testing: {$label}\n";
    echo "Target:  {$user}@{$host}:{$port}/{$db}\n";
    echo "Details: {$cfg['desc']}\n";
    echo "----------------------------------------------------\n";

    // Step 1: Network socket test (TCP reachability)
    echo "1. Checking TCP reachability to port {$port}...\n";
    $errno  = 0;
    $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($sock) {
        echo "   -> SUCCESS: Port {$port} is open and reachable.\n";
        fclose($sock);
    } else {
        echo "   -> FAILED: Cannot reach {$host}:{$port}. (Error {$errno}: {$errstr})\n";
        echo "      Troubleshooting tips:\n";
        if ($host === '127.0.0.1' || $host === 'localhost') {
            echo "       - Is MariaDB/MySQL running locally? Check: sudo systemctl status mariadb\n";
            echo "       - Start MariaDB: sudo systemctl start mariadb\n";
        } else {
            echo "       - Verify LAN connectivity: ping -c 3 {$host}\n";
            echo "       - Ensure MySQL on {$host} is listening on 0.0.0.0 or LAN IP (not just 127.0.0.1)\n";
            echo "       - Check firewall on Server: allow incoming TCP port {$port}\n";
        }
    }

    // Step 2: Standard PHP mysqli connection
    echo "2. Testing PHP mysqli connection (Standard)...\n";
    $conn1 = mysqli_init();
    @$conn1->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$conn1->real_connect($host, $user, $pass, $db, $port);
    if ($conn1->connect_error) {
        echo "   -> FAILED: " . $conn1->connect_error . " (Error code: " . $conn1->connect_errno . ")\n";
        if ($conn1->connect_errno === 1045) {
            echo "      Tip: Access denied - verify username/password or GRANT permissions for '{$user}'@'%'.\n";
        } elseif ($conn1->connect_errno === 1049) {
            echo "      Tip: Unknown database '{$db}' - verify database has been created and migrated.\n";
        }
    } else {
        echo "   -> SUCCESS: Connected to {$db} successfully.\n";
        $res = @$conn1->query("SELECT DATABASE() AS cur_db, VERSION() AS ver");
        if ($res && ($row = $res->fetch_assoc())) {
            echo "      Current DB: " . $row['cur_db'] . " | MySQL Version: " . $row['ver'] . "\n";
        }
        $conn1->close();
    }

    // Step 3: MySQL connection with SSL bypass
    echo "3. Testing PHP mysqli connection (SSL verification disabled)...\n";
    $conn2 = mysqli_init();
    @$conn2->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        @$conn2->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }
    $flags = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY') ? MYSQLI_CLIENT_SSL_DONT_VERIFY : 0;
    @$conn2->real_connect($host, $user, $pass, $db, $port, null, $flags);
    if ($conn2->connect_error) {
        echo "   -> FAILED: " . $conn2->connect_error . "\n";
    } else {
        echo "   -> SUCCESS: Connected using SSL bypass flags.\n";
        $conn2->close();
    }

    echo "\n";
}

echo "====================================================\n";
echo " Diagnostic Summary Complete\n";
echo "====================================================\n";
