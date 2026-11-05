<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_OFF);

define('SERVER_HOST', getenv('SERVER_DB_HOST') ?: '192.168.2.100');
define('SERVER_PORT', (int)(getenv('SERVER_DB_PORT') ?: 3306));
define('SERVER_DB',   getenv('SERVER_DB_NAME') ?: 'tracked_db');
define('SERVER_USER', getenv('SERVER_DB_USER') ?: 'tracked_user');
define('SERVER_PASS', getenv('SERVER_DB_PASS') ?: 'MyNewPassword123!');

define('LOCAL_HOST',  getenv('LOCAL_DB_HOST') ?: 'localhost');
define('LOCAL_DB',    getenv('LOCAL_DB_NAME') ?: 'tracked_local');
define('LOCAL_USER',  getenv('LOCAL_DB_USER') ?: 'root');
define('LOCAL_PASS',  getenv('LOCAL_DB_PASS') ?: 'MyNewPassword123!');

$use_local = isset($_GET['local']) && (string)$_GET['local'] === '1';

function db_connect_config($host, $user, $pass, $db, $port = 3306, $timeout = 2) {
    $c = mysqli_init();
    if (!$c) return null;
    @$c->options(MYSQLI_OPT_CONNECT_TIMEOUT, max(1, (int)$timeout));
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        @$c->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    }
    $flags = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY') ? MYSQLI_CLIENT_SSL_DONT_VERIFY : 0;
    if (!@$c->real_connect($host, $user, $pass, $db, (int)$port, null, $flags)) {
        return null;
    }
    $c->set_charset('utf8mb4');
    return $c;
}

$conn = null;
if ($use_local) {
    $conn = db_connect_config(LOCAL_HOST, LOCAL_USER, LOCAL_PASS, LOCAL_DB, 3306, 1);
    if (!$conn) {
        $conn = db_connect_config(SERVER_HOST, SERVER_USER, SERVER_PASS, SERVER_DB, SERVER_PORT, 2);
    }
} else {
    $conn = db_connect_config(SERVER_HOST, SERVER_USER, SERVER_PASS, SERVER_DB, SERVER_PORT, 2);
    if (!$conn) {
        $conn = db_connect_config(LOCAL_HOST, LOCAL_USER, LOCAL_PASS, LOCAL_DB, 3306, 1);
    }
}

if (!$conn || $conn->connect_error) {
    echo json_encode([
        'offline'     => true,
        'school_name' => 'TrackED Attendance System'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = [
    'offline'     => false,
    'school_name' => 'TrackED Attendance System'
];

$res = @$conn->query("SELECT config_key, config_value FROM system_config");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $config[$r['config_key']] = $r['config_value'];
    }
}

$conn->close();

echo json_encode($config, JSON_UNESCAPED_UNICODE);
