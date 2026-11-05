<?php
// ── SINGLE-ACCOUNT ADMIN AUTHENTICATION ──────────────────
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
]);
session_start();

define('ADMIN_USERNAME', 'sibtech@spmo');
define('ADMIN_PASSWORD_HASH', '$2y$12$awkmk3NAmPJEegziLgeMceJCY/hKhTm45OzoZMYlRZAoLIsWIqLhG');
define('SESSION_MAX_IDLE', 28800); // 8 hours

if (isset($_GET['logout'])) {
    // Best-effort sign-out audit. Never block logout if the database or migration is unavailable.
    try {
        $auditConn = @new mysqli('localhost','root','','tracked_db');
        if ($auditConn && !$auditConn->connect_error) {
            $tbl=@$auditConn->query("SHOW TABLES LIKE 'admin_audit_log'");
            if($tbl&&$tbl->num_rows){$who=$auditConn->real_escape_string((string)($_SESSION['admin_username']??'SPMO Admin'));$ip=$auditConn->real_escape_string((string)($_SERVER['REMOTE_ADDR']??''));@$auditConn->query("INSERT INTO admin_audit_log(category,action_type,entity_type,entity_id,description,performed_by,ip_address) VALUES('SECURITY','ADMIN_LOGOUT','session','','Administrator signed out of TrackED.','$who','$ip')");}
            $auditConn->close();
        }
    } catch (Throwable $e) {}
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = ADMIN_USERNAME;
        $_SESSION['last_activity'] = time();
        $_SESSION['audit_login_pending'] = true;
        header('Location: index.php');
        exit;
    }
    $login_error = 'Invalid username or password.';
}

if (!empty($_SESSION['admin_authenticated'])) {
    $last = (int)($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > SESSION_MAX_IDLE) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $login_error = 'Your session expired. Please sign in again.';
    } else {
        $_SESSION['last_activity'] = time();
    }
}

if (empty($_SESSION['admin_authenticated'])) {
    
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrackED — Secure Access</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20260907-fresh-final">
    <link rel="icon" type="image/png" href="logo.png">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
    <script>
        try { document.documentElement.setAttribute('data-theme', localStorage.getItem('tracked_theme') || 'light'); } catch(e) {}
    </script>
</head>
<body class="auth-page">
    <div class="auth-atmosphere" aria-hidden="true">
        <div class="auth-orb auth-orb-one"></div>
        <div class="auth-orb auth-orb-two"></div>
        <div class="auth-grid"></div>
    </div>
    <main class="auth-shell">
        <section class="auth-visual-panel">
            <div class="auth-brand-lockup">
                <div class="auth-logo-mark"><i class="ph-fill ph-fingerprint"></i></div>
                <div><span class="auth-brand-name">TrackED</span><span class="auth-brand-sub">SIBTECH Student Monitoring</span></div>
            </div>
            <div class="auth-hero-copy">
                <span class="auth-kicker"><i class="ph-fill ph-shield-check"></i> SECURE ADMIN CONSOLE</span>
                <h1>RFID-Based Student<br><span>Monitoring System</span></h1>
            </div>
            <div class="auth-signal-row">
                <div class="auth-signal-card"><i class="ph-fill ph-radio"></i><span><strong>RFID</strong><small>Live verification</small></span></div>
                <div class="auth-signal-card"><i class="ph-fill ph-database"></i><span><strong>SYNC</strong><small>Local + server</small></span></div>
                <div class="auth-signal-card"><i class="ph-fill ph-lock-key"></i><span><strong>SECURE</strong><small>Private console</small></span></div>
            </div>
        </section>
        <section class="auth-card-wrap">
            <div class="auth-card">
                <div class="auth-card-head">
                    <div class="auth-mini-icon"><i class="ph-fill ph-lock-key-open"></i></div>
                    <div><p class="auth-eyebrow">AUTHORIZED PERSONNEL ONLY</p><h2>Welcome back</h2><p>Sign in to continue to the TrackED administrator console.</p></div>
                </div>
                <?php if ($login_error): ?>
                    <div class="auth-error"><i class="ph-fill ph-warning-circle"></i><span><?= htmlspecialchars($login_error) ?></span></div>
                <?php endif; ?>
                <form method="post" class="auth-form" autocomplete="off">
                    <input type="hidden" name="action" value="login">
                    <label class="auth-field">
                        <span>Username</span>
                        <div class="auth-input-shell"><i class="ph ph-user"></i><input type="text" name="username" autocomplete="username" required autofocus placeholder="Enter administrator username"></div>
                    </label>
                    <label class="auth-field">
                        <span>Password</span>
                        <div class="auth-input-shell"><i class="ph ph-key"></i><input type="password" name="password" id="login-password" autocomplete="current-password" required placeholder="Enter password"><button type="button" class="auth-password-toggle" id="auth-password-toggle" aria-label="Show password"><i class="ph ph-eye"></i></button></div>
                    </label>
                    <button type="submit" class="auth-submit"><span>Enter Secure Console</span><i class="ph ph-arrow-right"></i></button>
                </form>
                <div class="auth-footnote"><i class="ph-fill ph-shield-check"></i><span>Single-account access • Session protected • No public registration</span></div>
            </div>
        </section>
    </main>
    <script>
        document.getElementById('auth-password-toggle')?.addEventListener('click', function(){
            const input = document.getElementById('login-password');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.innerHTML = show ? '<i class="ph ph-eye-slash"></i>' : '<i class="ph ph-eye"></i>';
        });
    </script>
</body>
</html>
    <?php
    exit;
}

define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','tracked_db');

date_default_timezone_set('Asia/Manila');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) $db_error = "Database not connected. Running in demo mode.";

// ── MASTER DATA ─────────────────────────────────────────
$COURSES     = ['BSIT', 'BSCUSTOM', 'BSCRIM', 'BSHM'];
$YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$GRADE_LEVELS = ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];
$SHS_TRACKS   = ['Academic Track','TVL Track','Sports Track','Arts and Design Track'];
$SHS_STRANDS  = ['STEM','ABM','HUMSS','GAS','ICT','HE','Industrial Arts'];
$DAYS        = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

// ── STUDENT MONITORING HELPERS / SUMMARY ──────────────────
$today_date = date('Y-m-d');
$today_day  = date('l');
$total_students = $inside_now = $entries_today = $exits_today = $early_exits_today = $rejected_today = 0;
$movement_ready = false;

function tracked_table_exists($conn, $table) {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}

function tracked_column_exists($conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

function tracked_ensure_daily_exception_schema($conn) {
    if (!$conn || $conn->connect_error) return false;
    $sql = "CREATE TABLE IF NOT EXISTS daily_monitoring_exceptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id INT NOT NULL,
        exception_date DATE NOT NULL,
        exception_type VARCHAR(40) NOT NULL DEFAULT 'NO_OUT',
        last_movement_id BIGINT UNSIGNED NULL,
        last_movement_at DATETIME NULL,
        detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_resolved TINYINT(1) NOT NULL DEFAULT 0,
        resolved_at DATETIME NULL,
        resolution_note VARCHAR(255) NOT NULL DEFAULT '',
        source VARCHAR(30) NOT NULL DEFAULT 'AUTO_EOD',
        PRIMARY KEY(id),
        UNIQUE KEY uq_daily_exception (student_id,exception_date,exception_type),
        KEY idx_daily_exception_date (exception_date,exception_type,is_resolved),
        KEY idx_daily_exception_student (student_id,exception_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    return (bool)$conn->query($sql);
}

function tracked_backfill_no_out_range($conn, $dateFrom, $dateTo) {
    if (!tracked_ensure_daily_exception_schema($conn) || !tracked_table_exists($conn,'student_movement_logs')) return 0;
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $today = date('Y-m-d');
    if ($dt >= $today) $dt = date('Y-m-d', strtotime('-1 day'));
    if ($df > $dt) return 0;
    $sql = "INSERT IGNORE INTO daily_monitoring_exceptions
        (student_id,exception_date,exception_type,last_movement_id,last_movement_at,detected_at,is_resolved,resolution_note,source)
        SELECT m.student_id,DATE(m.verified_at),'NO_OUT',m.id,m.verified_at,NOW(),0,'','AUTO_REPORT_BACKFILL'
        FROM student_movement_logs m
        WHERE DATE(m.verified_at) BETWEEN '$df' AND '$dt'
          AND m.movement_type='IN'
          AND NOT EXISTS (
              SELECT 1 FROM student_movement_logs m2
              WHERE m2.student_id=m.student_id
                AND DATE(m2.verified_at)=DATE(m.verified_at)
                AND (m2.verified_at>m.verified_at OR (m2.verified_at=m.verified_at AND m2.id>m.id))
          )";
    $conn->query($sql);
    $inserted = max(0,(int)$conn->affected_rows);
    // If a same-day OUT was backfilled later, keep the exception as audit history but mark it resolved.
    $conn->query("UPDATE daily_monitoring_exceptions e
        JOIN student_movement_logs m ON m.id=(
            SELECT m3.id FROM student_movement_logs m3
            WHERE m3.student_id=e.student_id AND DATE(m3.verified_at)=e.exception_date
            ORDER BY m3.verified_at DESC,m3.id DESC LIMIT 1
        )
        SET e.is_resolved=1,e.resolved_at=COALESCE(e.resolved_at,NOW()),
            e.resolution_note=IF(e.resolution_note='', 'A later same-day OUT record was added after the exception was detected.', e.resolution_note)
        WHERE e.exception_type='NO_OUT' AND e.exception_date BETWEEN '$df' AND '$dt'
          AND e.is_resolved=0 AND m.movement_type='OUT'");
    return $inserted;
}

if (!isset($db_error)) tracked_ensure_daily_exception_schema($conn);

require_once __DIR__ . '/maintenance/log_retention_lib.php';

// ── OPERATIONS / SECURITY AUDIT HELPERS ───────────────────
function tracked_audit($conn, $category, $action, $entityType, $entityId, $description, $metadata = []) {
    if (!$conn || !tracked_table_exists($conn,'admin_audit_log')) return;
    $category = substr(trim((string)$category),0,50);
    $action = substr(trim((string)$action),0,80);
    $entityType = substr(trim((string)$entityType),0,60);
    $entityId = substr(trim((string)$entityId),0,80);
    $description = substr(trim((string)$description),0,255);
    $performedBy = substr((string)($_SESSION['admin_username'] ?? 'SPMO Admin'),0,120);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64);
    $meta = json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare("INSERT INTO admin_audit_log(category,action_type,entity_type,entity_id,description,metadata_json,performed_by,ip_address) VALUES(?,?,?,?,?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param('ssssssss',$category,$action,$entityType,$entityId,$description,$meta,$performedBy,$ip);
        @$stmt->execute();
        $stmt->close();
    }
}

if (!isset($db_error) && !empty($_SESSION['audit_login_pending'])) {
    tracked_audit($conn,'SECURITY','ADMIN_LOGIN','session','', 'Administrator signed in to TrackED.');
    unset($_SESSION['audit_login_pending']);
}

// ── AJAX: SYSTEM HEALTH DASHBOARD ─────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_system_health') {
    header('Content-Type: application/json');
    $out = [
        'generated_at'=>date('Y-m-d H:i:s'),
        'operations_ready'=>false,
        'database'=>['status'=>isset($db_error)?'OFFLINE':'ONLINE','latency_ms'=>null,'message'=>$db_error??'Connected'],
        'orangepi'=>['status'=>'UNKNOWN','age_seconds'=>null,'last_seen'=>null,'last_sync_success'=>null,'last_sync_error'=>'','server_latency_ms'=>null,'unsynced_movements'=>0,'unsynced_rejections'=>0,'pending_event_queue'=>0,'version'=>''],
        'sms'=>['pending'=>0,'failed'=>0,'sent'=>0,'latest_sent'=>null],
        'backup'=>['status'=>'NOT CONFIGURED','completed_at'=>null,'message'=>''],
        'rfid_activity'=>['status'=>'IDLE','last_activity'=>null,'kind'=>''],
        'storage'=>['free_bytes'=>0,'total_bytes'=>0],
    ];
    if (!isset($db_error)) {
        $t0=microtime(true); @$conn->query('SELECT 1'); $out['database']['latency_ms']=round((microtime(true)-$t0)*1000,1);
        $out['operations_ready']=tracked_table_exists($conn,'system_heartbeats') && tracked_table_exists($conn,'admin_audit_log') && tracked_table_exists($conn,'emergency_muster_sessions');
        if (tracked_table_exists($conn,'system_heartbeats')) {
            $r=$conn->query("SELECT *,TIMESTAMPDIFF(SECOND,last_seen,NOW()) age_seconds FROM system_heartbeats WHERE source_key='orangepi-sync' LIMIT 1");
            if($r&&$r->num_rows){$h=$r->fetch_assoc();$age=$h['age_seconds']===null?null:(int)$h['age_seconds'];$out['orangepi']=[
                'status'=>($age!==null&&$age<=90)?'ONLINE':'OFFLINE','age_seconds'=>$age,'last_seen'=>$h['last_seen'],'last_sync_success'=>$h['last_sync_success'],'last_sync_error'=>$h['last_sync_error']??'','server_latency_ms'=>$h['server_latency_ms']===null?null:(float)$h['server_latency_ms'],
                'unsynced_movements'=>(int)$h['unsynced_movements'],'unsynced_rejections'=>(int)$h['unsynced_rejections'],'pending_event_queue'=>(int)$h['pending_event_queue'],'version'=>$h['version']??''
            ];}
        }
        if (tracked_table_exists($conn,'sms_queue')) {
            $r=$conn->query("SELECT status,COUNT(*) c FROM sms_queue GROUP BY status"); if($r)while($x=$r->fetch_assoc())$out['sms'][$x['status']]=(int)$x['c'];
            $r=$conn->query("SELECT sent_at,created_at FROM sms_queue WHERE status='sent' ORDER BY COALESCE(sent_at,created_at) DESC LIMIT 1"); if($r&&$r->num_rows){$x=$r->fetch_assoc();$out['sms']['latest_sent']=$x['sent_at']?:$x['created_at'];}
        }
        if (tracked_table_exists($conn,'log_backup_runs')) {
            $r=$conn->query("SELECT status,completed_at,started_at,message FROM log_backup_runs ORDER BY id DESC LIMIT 1");
            if($r&&$r->num_rows){$b=$r->fetch_assoc();$out['backup']=['status'=>$b['status'],'completed_at'=>$b['completed_at']?:$b['started_at'],'message'=>$b['message']??''];}
        }
        $lastMove=null;$lastReject=null;
        if (tracked_table_exists($conn,'student_movement_logs')) {$r=$conn->query("SELECT MAX(verified_at) t FROM student_movement_logs");if($r)$lastMove=$r->fetch_assoc()['t'];}
        if (tracked_table_exists($conn,'rejected_scans')) {$r=$conn->query("SELECT MAX(rejected_at) t FROM rejected_scans");if($r)$lastReject=$r->fetch_assoc()['t'];}
        if($lastMove||$lastReject){if(!$lastReject||($lastMove&&strtotime($lastMove)>=strtotime($lastReject))){$out['rfid_activity']=['status'=>'ACTIVE','last_activity'=>$lastMove,'kind'=>'Verified movement'];}else{$out['rfid_activity']=['status'=>'ACTIVE','last_activity'=>$lastReject,'kind'=>'Denied verification'];}}
    }
    $free=@disk_free_space(__DIR__);$total=@disk_total_space(__DIR__);$out['storage']['free_bytes']=$free===false?0:(int)$free;$out['storage']['total_bytes']=$total===false?0:(int)$total;
    echo json_encode($out); exit;
}

// ── AJAX: ADMIN / SECURITY AUDIT TRAIL ───────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_audit_log') {
    header('Content-Type: application/json');
    $out=[];$category=trim((string)($_GET['category']??''));$search=trim((string)($_GET['s']??''));$from=trim((string)($_GET['date_from']??''));$to=trim((string)($_GET['date_to']??''));$limit=max(25,min(500,(int)($_GET['limit']??200)));
    if(!isset($db_error) && tracked_table_exists($conn,'admin_audit_log')){
        $w=['1=1'];
        if($category!==''){$c=$conn->real_escape_string($category);$w[]="category='$c'";}
        if($search!==''){$q=$conn->real_escape_string($search);$w[]="(description LIKE '%$q%' OR action_type LIKE '%$q%' OR entity_id LIKE '%$q%' OR performed_by LIKE '%$q%')";}
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$f=$conn->real_escape_string($from);$w[]="DATE(created_at)>='$f'";}
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$t=$conn->real_escape_string($to);$w[]="DATE(created_at)<='$t'";}
        $ws=implode(' AND ',$w);$r=$conn->query("SELECT id,category,action_type,entity_type,entity_id,description,metadata_json,performed_by,ip_address,created_at,'ADMIN' source FROM admin_audit_log WHERE $ws ORDER BY created_at DESC,id DESC LIMIT $limit");if($r)while($x=$r->fetch_assoc())$out[]=$x;
        if(($category===''||$category==='SECURITY') && tracked_table_exists($conn,'student_scan_access_audit')){
            $wa=["a.action='LOCKED'"];if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$f=$conn->real_escape_string($from);$wa[]="DATE(a.created_at)>='$f'";}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$t=$conn->real_escape_string($to);$wa[]="DATE(a.created_at)<='$t'";}if($search!==''){$q=$conn->real_escape_string($search);$wa[]="(s.full_name LIKE '%$q%' OR s.student_id_no LIKE '%$q%' OR a.reason LIKE '%$q%' OR a.action LIKE '%$q%')";}
            $wsa=implode(' AND ',$wa);$r=$conn->query("SELECT a.id,'SECURITY' category,CONCAT('SCAN_',a.action) action_type,'student' entity_type,CAST(a.student_id AS CHAR) entity_id,CONCAT(COALESCE(s.full_name,'Student'),' — ',a.action,' scan access') description,CONCAT('{\"attempt_count\":',a.attempt_count,',\"reason\":',JSON_QUOTE(COALESCE(a.reason,'')),'}') metadata_json,a.performed_by,'' ip_address,a.created_at,'SCAN_ACCESS' source FROM student_scan_access_audit a LEFT JOIN students s ON s.id=a.student_id WHERE $wsa ORDER BY a.created_at DESC LIMIT $limit");if($r)while($x=$r->fetch_assoc())$out[]=$x;
            usort($out,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));$out=array_slice($out,0,$limit);
        }
    }
    echo json_encode(['status'=>'success','logs'=>$out]);exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_audit_csv') {
    if(isset($db_error)||!tracked_table_exists($conn,'admin_audit_log')){http_response_code(503);exit('Audit log unavailable');}
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="tracked_audit_'.date('Ymd_His').'.csv"');echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');fputcsv($fp,['Date/Time','Category','Action','Entity','Entity ID','Description','Performed By','Source / IP']);
    $rows=[];$r=$conn->query("SELECT created_at,category,action_type,entity_type,entity_id,description,performed_by,ip_address,'ADMIN' source FROM admin_audit_log ORDER BY created_at DESC,id DESC LIMIT 10000");if($r)while($x=$r->fetch_assoc())$rows[]=$x;
    if(tracked_table_exists($conn,'student_scan_access_audit')){$r=$conn->query("SELECT a.created_at,'SECURITY' category,CONCAT('SCAN_',a.action) action_type,'student' entity_type,CAST(a.student_id AS CHAR) entity_id,CONCAT(COALESCE(s.full_name,'Student'),' — ',a.action,' scan access. Reason: ',COALESCE(a.reason,'')) description,a.performed_by,'' ip_address,'SCAN_ACCESS' source FROM student_scan_access_audit a LEFT JOIN students s ON s.id=a.student_id WHERE a.action='LOCKED' ORDER BY a.created_at DESC LIMIT 10000");if($r)while($x=$r->fetch_assoc())$rows[]=$x;}
    usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));foreach(array_slice($rows,0,15000) as $x)fputcsv($fp,[$x['created_at'],$x['category'],$x['action_type'],$x['entity_type'],$x['entity_id'],$x['description'],$x['performed_by'],trim(($x['source']??'').(($x['ip_address']??'')!==''?' / '.$x['ip_address']:''))]);fclose($fp);exit;
}

// ── AJAX: EMERGENCY MUSTER MODE ───────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_muster') {
    header('Content-Type: application/json');$sid=(int)($_GET['session_id']??0);$session=null;$members=[];$recent=[];
    if(!isset($db_error)&&tracked_table_exists($conn,'emergency_muster_sessions')){
        $r=$conn->query("SELECT * FROM emergency_muster_sessions ORDER BY started_at DESC,id DESC LIMIT 10");if($r)while($x=$r->fetch_assoc())$recent[]=$x;
        if($sid>0)$r=$conn->query("SELECT * FROM emergency_muster_sessions WHERE id=$sid LIMIT 1");else{$r=$conn->query("SELECT * FROM emergency_muster_sessions WHERE status='ACTIVE' ORDER BY started_at DESC,id DESC LIMIT 1");if(!$r||!$r->num_rows)$r=$conn->query("SELECT * FROM emergency_muster_sessions ORDER BY started_at DESC,id DESC LIMIT 1");}
        if($r&&$r->num_rows){$session=$r->fetch_assoc();$id=(int)$session['id'];$mr=$conn->query("SELECT * FROM emergency_muster_members WHERE session_id=$id ORDER BY FIELD(education_level_snapshot,'College','Senior High School','Junior High School'),academic_group_snapshot,year_level_snapshot,section_snapshot,full_name_snapshot");if($mr)while($x=$mr->fetch_assoc())$members[]=$x;}
    }
    echo json_encode(['status'=>'success','session'=>$session,'members'=>$members,'recent_sessions'=>$recent]);exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'start_muster') {
    header('Content-Type: application/json');
    if(isset($db_error)||!tracked_table_exists($conn,'emergency_muster_sessions')||!tracked_table_exists($conn,'student_movement_logs')){echo json_encode(['status'=>'error','message'=>'Run sql_operations_upgrade.sql first.']);exit;}
    $r=$conn->query("SELECT id FROM emergency_muster_sessions WHERE status='ACTIVE' LIMIT 1");if($r&&$r->num_rows){echo json_encode(['status'=>'error','message'=>'An emergency muster session is already active.']);exit;}
    $title=trim((string)($_POST['title']??''));if($title==='')$title='Emergency Muster '.date('M j, Y h:i A');$notes=trim((string)($_POST['notes']??''));$ts=$conn->real_escape_string(substr($title,0,160));$ns=$conn->real_escape_string(substr($notes,0,255));$by=$conn->real_escape_string((string)($_SESSION['admin_username']??'SPMO Admin'));
    $today=date('Y-m-d');$conn->begin_transaction();try{
        $conn->query("INSERT INTO emergency_muster_sessions(title,status,started_by,notes) VALUES('$ts','ACTIVE','$by','$ns')");$sessionId=(int)$conn->insert_id;
        $q="SELECT s.id,s.student_id_no,s.full_name,COALESCE(s.education_level,'College') education_level,s.course,s.track,s.strand,s.year_level,s.section FROM students s JOIN student_movement_logs m ON m.id=(SELECT m2.id FROM student_movement_logs m2 WHERE m2.student_id=s.id AND DATE(m2.verified_at)='$today' ORDER BY m2.verified_at DESC,m2.id DESC LIMIT 1) WHERE s.is_active=1 AND m.movement_type='IN' ORDER BY s.full_name";
        $sr=$conn->query($q);$count=0;if($sr)while($st=$sr->fetch_assoc()){$level=$st['education_level']?:'College';$group=$level==='College'?($st['course']??''):($level==='Senior High School'?(($st['strand']??'')?:($st['track']??'')):'');$idno=$conn->real_escape_string($st['student_id_no']??'');$name=$conn->real_escape_string($st['full_name']??'');$lev=$conn->real_escape_string($level);$grp=$conn->real_escape_string($group);$yr=$conn->real_escape_string($st['year_level']??'');$sec=$conn->real_escape_string($st['section']??'');$studentId=(int)$st['id'];$conn->query("INSERT INTO emergency_muster_members(session_id,student_id,student_id_no_snapshot,full_name_snapshot,education_level_snapshot,academic_group_snapshot,year_level_snapshot,section_snapshot) VALUES($sessionId,$studentId,'$idno','$name','$lev','$grp','$yr','$sec')");$count++;}
        $conn->query("UPDATE emergency_muster_sessions SET total_snapshot=$count WHERE id=$sessionId");$conn->commit();tracked_audit($conn,'EMERGENCY','MUSTER_STARTED','muster',(string)$sessionId,"Emergency muster started with $count students inside campus.",['snapshot_count'=>$count]);echo json_encode(['status'=>'success','message'=>"Emergency muster started. $count students were captured in the inside-campus snapshot.",'session_id'=>$sessionId]);
    }catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>'error','message'=>'Could not start muster: '.$e->getMessage()]);}exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update_muster_member') {
    header('Content-Type: application/json');$memberId=(int)($_POST['member_id']??0);$status=strtoupper(trim((string)($_POST['status']??'')));$note=trim((string)($_POST['note']??''));if(!in_array($status,['UNCONFIRMED','SAFE','NOT_FOUND','EXCUSED'],true)){echo json_encode(['status'=>'error','message'=>'Invalid muster status.']);exit;}
    if(isset($db_error)||!tracked_table_exists($conn,'emergency_muster_members')){echo json_encode(['status'=>'error','message'=>'Muster database unavailable.']);exit;}$r=$conn->query("SELECT m.*,s.status session_status FROM emergency_muster_members m JOIN emergency_muster_sessions s ON s.id=m.session_id WHERE m.id=$memberId LIMIT 1");if(!$r||!$r->num_rows){echo json_encode(['status'=>'error','message'=>'Muster member not found.']);exit;}$m=$r->fetch_assoc();if($m['session_status']!=='ACTIVE'){echo json_encode(['status'=>'error','message'=>'This muster session is already closed.']);exit;}$st=$conn->real_escape_string($status);$nt=$conn->real_escape_string(substr($note,0,255));$by=$conn->real_escape_string((string)($_SESSION['admin_username']??'SPMO Admin'));$accounted=$status==='UNCONFIRMED'?'NULL':'NOW()';$bySql=$status==='UNCONFIRMED'?'NULL':"'$by'";$ok=$conn->query("UPDATE emergency_muster_members SET accounted_status='$st',accounted_at=$accounted,accounted_by=$bySql,note='$nt' WHERE id=$memberId");if($ok)tracked_audit($conn,'EMERGENCY','MUSTER_MEMBER_STATUS','student',(string)$m['student_id'],($m['full_name_snapshot']??'Student')." marked $status in emergency muster.",['session_id'=>(int)$m['session_id'],'note'=>$note]);echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?'Muster status updated.':$conn->error]);exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'close_muster') {
    header('Content-Type: application/json');$sid=(int)($_POST['session_id']??0);if(isset($db_error)||$sid<=0){echo json_encode(['status'=>'error','message'=>'Invalid muster session.']);exit;}$by=$conn->real_escape_string((string)($_SESSION['admin_username']??'SPMO Admin'));$ok=$conn->query("UPDATE emergency_muster_sessions SET status='CLOSED',closed_at=NOW(),closed_by='$by' WHERE id=$sid AND status='ACTIVE'");if($ok&&$conn->affected_rows>0){tracked_audit($conn,'EMERGENCY','MUSTER_CLOSED','muster',(string)$sid,'Emergency muster session closed.');echo json_encode(['status'=>'success','message'=>'Emergency muster closed and preserved for review.']);}else echo json_encode(['status'=>'error','message'=>'Muster is already closed or unavailable.']);exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_muster_csv') {
    $sid=(int)($_GET['session_id']??0);if(isset($db_error)||$sid<=0||!tracked_table_exists($conn,'emergency_muster_members')){http_response_code(404);exit('Muster unavailable');}header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="tracked_muster_'.$sid.'_'.date('Ymd_His').'.csv"');echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');fputcsv($fp,['Student ID','Student','Education Level','Academic Group','Year/Grade','Section','Muster Status','Accounted At','Accounted By','Note']);$r=$conn->query("SELECT * FROM emergency_muster_members WHERE session_id=$sid ORDER BY education_level_snapshot,academic_group_snapshot,year_level_snapshot,section_snapshot,full_name_snapshot");if($r)while($x=$r->fetch_assoc())fputcsv($fp,[$x['student_id_no_snapshot'],$x['full_name_snapshot'],$x['education_level_snapshot'],$x['academic_group_snapshot'],$x['year_level_snapshot'],$x['section_snapshot'],$x['accounted_status'],$x['accounted_at'],$x['accounted_by'],$x['note']]);fclose($fp);exit;
}

// ── AJAX: CAMPUS MOVEMENT POLICY (authenticated) ─────────
if (isset($_GET['action']) && $_GET['action'] === 'get_movement_policy') {
    header('Content-Type: application/json');
    $out = [
        'hs_controlled_exit_enabled'=>1,
        'hs_dismissal_start'=>'15:30:00',
        'hs_early_exit_requires_reason'=>1,
        'hs_allow_reentry'=>1,
        'sms_every_movement'=>1,
        'sms_early_exit'=>1,
        'same_card_cooldown_seconds'=>4
    ];
    if (!isset($db_error) && tracked_table_exists($conn,'movement_policy')) {
        $r = $conn->query("SELECT * FROM movement_policy WHERE id=1 LIMIT 1");
        if ($r && $r->num_rows) $out = array_merge($out, $r->fetch_assoc());
    }
    echo json_encode($out); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_movement_policy') {
    header('Content-Type: application/json');
    if (isset($db_error) || !tracked_table_exists($conn,'movement_policy')) {
        echo json_encode(['status'=>'error','message'=>'Run sql_student_monitoring_final_compat.sql first.']); exit;
    }
    $controlled = !empty($_POST['hs_controlled_exit_enabled']) ? 1 : 0;
    $dismissal = trim((string)($_POST['hs_dismissal_start'] ?? '15:30:00'));
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $dismissal)) $dismissal='15:30:00';
    if (strlen($dismissal)===5) $dismissal .= ':00';
    $dismissal = $conn->real_escape_string($dismissal);
    $reason = !empty($_POST['hs_early_exit_requires_reason']) ? 1 : 0;
    $reentry = !empty($_POST['hs_allow_reentry']) ? 1 : 0;
    $sms = !empty($_POST['sms_every_movement']) ? 1 : 0;
    $smsEarly = !empty($_POST['sms_early_exit']) ? 1 : 0;
    $cool = max(2,min(15,(int)($_POST['same_card_cooldown_seconds'] ?? 4)));
    $ok = $conn->query("INSERT INTO movement_policy(id,hs_controlled_exit_enabled,hs_dismissal_start,hs_early_exit_requires_reason,hs_allow_reentry,sms_every_movement,sms_early_exit,same_card_cooldown_seconds) VALUES(1,$controlled,'$dismissal',$reason,$reentry,$sms,$smsEarly,$cool) ON DUPLICATE KEY UPDATE hs_controlled_exit_enabled=VALUES(hs_controlled_exit_enabled),hs_dismissal_start=VALUES(hs_dismissal_start),hs_early_exit_requires_reason=VALUES(hs_early_exit_requires_reason),hs_allow_reentry=VALUES(hs_allow_reentry),sms_every_movement=VALUES(sms_every_movement),sms_early_exit=VALUES(sms_early_exit),same_card_cooldown_seconds=VALUES(same_card_cooldown_seconds)");
    if($ok) tracked_audit($conn,'POLICY','CAMPUS_POLICY_UPDATED','movement_policy','1','Campus movement policy updated.',['controlled_exit'=>$controlled,'dismissal'=>$dismissal,'reason_required'=>$reason,'reentry'=>$reentry,'sms_movement'=>$sms,'sms_early'=>$smsEarly,'cooldown'=>$cool]);
    echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?'Campus monitoring policy saved.':$conn->error]); exit;
}

if (!isset($db_error)) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM students WHERE is_active=1");
    if ($r) $total_students = (int)$r->fetch_assoc()['c'];

    $movement_ready = tracked_table_exists($conn, 'student_movement_logs');
    if ($movement_ready) {
        // State resets to OUTSIDE at the beginning of a new day. Latest movement today decides presence.
        $r = $conn->query("SELECT COUNT(*) AS c FROM students s
            JOIN student_movement_logs m ON m.id=(SELECT m2.id FROM student_movement_logs m2 WHERE m2.student_id=s.id AND DATE(m2.verified_at)='$today_date' ORDER BY m2.verified_at DESC,m2.id DESC LIMIT 1)
            WHERE s.is_active=1 AND m.movement_type='IN'");
        if ($r) $inside_now = (int)$r->fetch_assoc()['c'];

        $r = $conn->query("SELECT COUNT(*) AS c FROM student_movement_logs WHERE DATE(verified_at)='$today_date' AND movement_type='IN'");
        if ($r) $entries_today = (int)$r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) AS c FROM student_movement_logs WHERE DATE(verified_at)='$today_date' AND movement_type='OUT'");
        if ($r) $exits_today = (int)$r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) AS c FROM student_movement_logs WHERE DATE(verified_at)='$today_date' AND movement_context='EARLY_EXIT'");
        if ($r) $early_exits_today = (int)$r->fetch_assoc()['c'];
    }

    if (tracked_table_exists($conn, 'rejected_scans')) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM rejected_scans WHERE DATE(rejected_at)='$today_date'");
        if ($r) $rejected_today = (int)$r->fetch_assoc()['c'];
    }
}

// ── AJAX: GET STUDENTS ────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_students') {
    header('Content-Type: application/json');
    $s     = isset($_GET['s'])     ? $conn->real_escape_string(trim($_GET['s']))     : '';
    $level = isset($_GET['level']) ? $conn->real_escape_string(trim($_GET['level'])) : '';
    $group = isset($_GET['group']) ? $conn->real_escape_string(trim($_GET['group'])) : '';
    $fy    = isset($_GET['year'])  ? $conn->real_escape_string(trim($_GET['year']))  : '';
    $fi    = isset($_GET['irreg']) ? (int)$_GET['irreg'] : -1;

    $w = ["s.is_active=1"];
    if ($s) $w[] = "(s.full_name LIKE '%$s%' OR s.rfid_uid LIKE '%$s%' OR s.student_id_no LIKE '%$s%')";
    if ($level !== '') $w[] = "COALESCE(s.education_level,'College')='$level'";
    if ($group !== '') {
        if ($level === 'Senior High School') $w[] = "s.strand='$group'";
        elseif ($level === 'College') $w[] = "s.course='$group'";
    }
    if ($fy !== '') $w[] = "s.year_level='$fy'";
    if ($fi >= 0) $w[] = "s.is_irregular=$fi";
    $ws = implode(' AND ', $w);
    $out = [];

    if (!isset($db_error)) {
        $hasMovements = tracked_table_exists($conn, 'student_movement_logs');
        $movementFields = $hasMovements ? "
            (SELECT movement_type FROM student_movement_logs m WHERE m.student_id=s.id AND DATE(m.verified_at)='$today_date' ORDER BY m.verified_at DESC,m.id DESC LIMIT 1) AS today_last_movement,
            (SELECT movement_type FROM student_movement_logs m WHERE m.student_id=s.id ORDER BY m.verified_at DESC,m.id DESC LIMIT 1) AS last_movement,
            (SELECT movement_context FROM student_movement_logs m WHERE m.student_id=s.id ORDER BY m.verified_at DESC,m.id DESC LIMIT 1) AS last_movement_context,
            (SELECT authorization_reason FROM student_movement_logs m WHERE m.student_id=s.id ORDER BY m.verified_at DESC,m.id DESC LIMIT 1) AS last_authorization_reason,
            (SELECT verified_at FROM student_movement_logs m WHERE m.student_id=s.id ORDER BY m.verified_at DESC,m.id DESC LIMIT 1) AS last_movement_at,
            (SELECT COUNT(*) FROM student_movement_logs m WHERE m.student_id=s.id AND DATE(m.verified_at)='$today_date') AS movements_today
        " : "NULL AS today_last_movement,NULL AS last_movement,NULL AS last_movement_context,NULL AS last_authorization_reason,NULL AS last_movement_at,0 AS movements_today";
        $res = $conn->query("SELECT s.*, $movementFields FROM students s WHERE $ws ORDER BY s.full_name");
        // Compatibility fallback: student records must remain available even if a
        // newly-added monitoring table is incomplete while a migration is being applied.
        if (!$res) {
            $res = $conn->query("SELECT s.* FROM students s WHERE $ws ORDER BY s.full_name");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $r['today_last_movement'] = null;
                    $r['last_movement'] = null;
                    $r['last_movement_context'] = null;
                    $r['last_authorization_reason'] = null;
                    $r['last_movement_at'] = null;
                    $r['movements_today'] = 0;
                    $r['scan_enabled'] = isset($r['scan_enabled']) ? (int)$r['scan_enabled'] : 1;
                    $r['denied_attempt_count'] = isset($r['denied_attempt_count']) ? (int)$r['denied_attempt_count'] : 0;
                    $r['irreg_days'] = json_decode($r['irregular_days_json'] ?? '[]', true) ?: [];
                    $r['campus_state'] = 'OUTSIDE';
                    $out[] = $r;
                }
            }
        } else {
            while ($r = $res->fetch_assoc()) {
                $r['scan_enabled'] = isset($r['scan_enabled']) ? (int)$r['scan_enabled'] : 1;
                $r['denied_attempt_count'] = isset($r['denied_attempt_count']) ? (int)$r['denied_attempt_count'] : 0;
                $r['irreg_days'] = json_decode($r['irregular_days_json'] ?? '[]', true) ?: [];
                $r['campus_state'] = (($r['today_last_movement'] ?? '') === 'IN') ? 'INSIDE' : 'OUTSIDE';
                $out[] = $r;
            }
        }
    }
    echo json_encode($out); exit;
}

// ── AJAX: GET SCHEDULES ───────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_schedules') {
    header('Content-Type: application/json');
    $out = [];
    if (!isset($db_error)) {
        $res = $conn->query("SELECT * FROM schedules ORDER BY education_level, course, track, strand, year_level, section_scope");
        while ($r = $res->fetch_assoc()) {
            $r['days'] = json_decode($r['days_json'] ?? '[]', true);
            $out[] = $r;
        }
    } else {
        $out = [
            ['course'=>'BSIT',    'year_level'=>'1st Year','days'=>['Monday','Tuesday'],                      'ontime_cutoff'=>'07:30:00','late_cutoff'=>'08:30:00'],
            ['course'=>'BSIT',    'year_level'=>'2nd Year','days'=>['Wednesday','Thursday'],                   'ontime_cutoff'=>'07:30:00','late_cutoff'=>'08:30:00'],
            ['course'=>'BSIT',    'year_level'=>'3rd Year','days'=>['Monday','Wednesday','Friday'],            'ontime_cutoff'=>'07:00:00','late_cutoff'=>'08:00:00'],
            ['course'=>'BSIT',    'year_level'=>'4th Year','days'=>['Tuesday','Thursday'],                     'ontime_cutoff'=>'07:00:00','late_cutoff'=>'08:00:00'],
            ['course'=>'BSHM',    'year_level'=>'1st Year','days'=>['Monday','Tuesday','Wednesday'],           'ontime_cutoff'=>'07:30:00','late_cutoff'=>'08:30:00'],
            ['course'=>'BSHM',    'year_level'=>'2nd Year','days'=>['Thursday','Friday'],                      'ontime_cutoff'=>'07:30:00','late_cutoff'=>'08:30:00'],
            ['course'=>'BSCRIM',  'year_level'=>'1st Year','days'=>['Monday','Tuesday','Wednesday','Thursday'],'ontime_cutoff'=>'06:00:00','late_cutoff'=>'07:00:00'],
            ['course'=>'BSCUSTOM','year_level'=>'1st Year','days'=>['Monday','Wednesday'],                     'ontime_cutoff'=>'07:30:00','late_cutoff'=>'08:30:00'],
        ];
    }
    echo json_encode($out); exit;
}

// ── AJAX: GET SUBJECTS (per schedule) ─────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_subjects') {
    header('Content-Type: application/json');
    $sid = (int)($_GET['schedule_id'] ?? 0);
    $out = [];
    if (!isset($db_error) && $sid) {
        $res = $conn->query("SELECT * FROM subjects WHERE schedule_id=$sid ORDER BY day, time_start, id");
        while ($r = $res->fetch_assoc()) $out[] = $r;
    }
    echo json_encode($out); exit;
}

// ── AJAX: SAVE SUBJECT (add or edit) ──────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_subject') {
    header('Content-Type: application/json');
    $sid   = (int)($_POST['schedule_id'] ?? 0);
    $id    = (int)($_POST['id'] ?? 0);
    $name  = $conn->real_escape_string(trim($_POST['subject_name'] ?? ''));
    $code  = $conn->real_escape_string(trim($_POST['subject_code'] ?? ''));
    $instr = $conn->real_escape_string(trim($_POST['instructor']   ?? ''));
    $room  = $conn->real_escape_string(trim($_POST['room']         ?? ''));
    $day   = $conn->real_escape_string(trim($_POST['day']          ?? ''));
    $ts    = $conn->real_escape_string(trim($_POST['time_start']   ?? ''));
    $te    = $conn->real_escape_string(trim($_POST['time_end']     ?? ''));

    if (!$sid || $name === '') {
        echo json_encode(['status'=>'error','message'=>'Subject name is required.']); exit;
    }

    if (!isset($db_error)) {
        $ts_sql = $ts !== '' ? "'$ts'" : 'NULL';
        $te_sql = $te !== '' ? "'$te'" : 'NULL';
        if ($id > 0) {
            $ok = $conn->query("UPDATE subjects SET subject_name='$name', subject_code='$code',
                instructor='$instr', room='$room', day='$day', time_start=$ts_sql, time_end=$te_sql
                WHERE id=$id AND schedule_id=$sid");
            $msg = "$name updated.";
        } else {
            $ok = $conn->query("INSERT INTO subjects (schedule_id,subject_name,subject_code,instructor,room,day,time_start,time_end)
                VALUES ($sid,'$name','$code','$instr','$room','$day',$ts_sql,$te_sql)");
            $msg = "$name added.";
        }
        if ($ok) { $auditId=$id>0?$id:(int)$conn->insert_id; tracked_audit($conn,'POLICY',$id>0?'SUBJECT_UPDATED':'SUBJECT_ADDED','subject',(string)$auditId,$msg,['schedule_id'=>$sid]); echo json_encode(['status'=>'success','message'=>$msg]); }
        else     echo json_encode(['status'=>'error','message'=>$conn->error]);
    } else {
        echo json_encode(['status'=>'info','message'=>"Demo mode — $name simulated."]);
    }
    exit;
}

// ── AJAX: DELETE SUBJECT ───────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!isset($db_error) && $id) {
        $ok = $conn->query("DELETE FROM subjects WHERE id=$id");
        if($ok)tracked_audit($conn,'POLICY','SUBJECT_DELETED','subject',(string)$id,'Schedule subject deleted.');
        echo json_encode(['status'=> $ok ? 'success' : 'error', 'message'=> $ok ? 'Subject deleted.' : $conn->error]);
    } else {
        echo json_encode(['status'=>'info','message'=>'Demo mode — delete simulated.']);
    }
    exit;
}

// ── AJAX: SAVE SCHEDULE ───────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_schedule') {
    header('Content-Type: application/json');
    $level  = $conn->real_escape_string(trim($_POST['education_level'] ?? 'College'));
    $c      = $conn->real_escape_string(trim($_POST['course'] ?? ''));
    $track  = $conn->real_escape_string(trim($_POST['track'] ?? ''));
    $strand = $conn->real_escape_string(trim($_POST['strand'] ?? ''));
    $y      = $conn->real_escape_string(trim($_POST['year_level'] ?? ''));
    $scope  = $conn->real_escape_string(trim($_POST['section_scope'] ?? ''));
    $days = json_encode($_POST['days'] ?? []);
    $dj   = $conn->real_escape_string($days);

    $tin  = $conn->real_escape_string($_POST['time_in_start']  ?? '06:00:00');
    $ot   = $conn->real_escape_string($_POST['ontime_cutoff']  ?? '07:30:00');
    $lt   = $conn->real_escape_string($_POST['late_cutoff']    ?? '08:30:00');

    $lunch_enabled = isset($_POST['lunch_enabled']) && $_POST['lunch_enabled'] === '1' ? 1 : 0;
    $los  = $conn->real_escape_string($_POST['lunch_out_start'] ?? '11:30:00');
    $loe  = $conn->real_escape_string($_POST['lunch_out_end']   ?? '12:15:00');
    $lis  = $conn->real_escape_string($_POST['lunch_in_start']  ?? '12:15:00');
    $lie  = $conn->real_escape_string($_POST['lunch_in_end']    ?? '13:15:00');

    $tos  = $conn->real_escape_string($_POST['time_out_start'] ?? '15:00:00');
    $toe  = $conn->real_escape_string($_POST['time_out_end']   ?? '18:00:00');

    if ($y === '' || ($level === 'College' && $c === '') || ($level === 'Senior High School' && ($track === '' || $strand === ''))) {
        echo json_encode(['status'=>'error','message'=>'Complete the academic classification first.']); exit;
    }

    if (!isset($db_error)) {
        $exists = $conn->query("SELECT id FROM schedules WHERE education_level='$level' AND course='$c' AND track='$track' AND strand='$strand' AND year_level='$y' AND section_scope='$scope' LIMIT 1");
        if ($exists && $exists->num_rows > 0) {
            $row = $exists->fetch_assoc();
            $id  = (int)$row['id'];
            $ok  = $conn->query("UPDATE schedules SET
                education_level='$level', course='$c', track='$track', strand='$strand', year_level='$y', section_scope='$scope',
                days_json='$dj', time_in_start='$tin', ontime_cutoff='$ot', late_cutoff='$lt',
                lunch_enabled=$lunch_enabled, lunch_out_start='$los', lunch_out_end='$loe',
                lunch_in_start='$lis', lunch_in_end='$lie', time_out_start='$tos', time_out_end='$toe',
                timeout_cutoff='$tos'
                WHERE id=$id");
        } else {
            $ok = $conn->query("INSERT INTO schedules
                (education_level,course,track,strand,year_level,section_scope,days_json,time_in_start,ontime_cutoff,late_cutoff,lunch_enabled,
                 lunch_out_start,lunch_out_end,lunch_in_start,lunch_in_end,time_out_start,time_out_end,timeout_cutoff)
                VALUES
                ('$level','$c','$track','$strand','$y','$scope','$dj','$tin','$ot','$lt',$lunch_enabled,
                 '$los','$loe','$lis','$lie','$tos','$toe','$tos')");
        }
        if ($ok) { if(empty($id))$id=(int)$conn->insert_id; tracked_audit($conn,'POLICY','LEGACY_SCHEDULE_SAVED','schedule',(string)$id,'Legacy/event-compatible schedule saved.',['education_level'=>$level,'year_level'=>$y,'section_scope'=>$scope]); echo json_encode(['status'=>'success','message'=>'Schedule saved successfully.']); }
        else     echo json_encode(['status'=>'error','message'=>$conn->error]);
    } else {
        echo json_encode(['status'=>'info','message'=>"Demo mode — $c $y schedule simulated."]);
    }
    exit;
}

// ── AJAX: REGISTER STUDENT ────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    header('Content-Type: application/json');
    $rfid    = $conn->real_escape_string(trim($_POST['rfid_uid']       ?? ''));
    $idno    = $conn->real_escape_string(trim($_POST['student_id_no']  ?? ''));
    $name    = $conn->real_escape_string(trim($_POST['full_name']      ?? ''));
    $level   = $conn->real_escape_string(trim($_POST['education_level'] ?? 'College'));
    $course  = $conn->real_escape_string(trim($_POST['course'] ?? ''));
    $track   = $conn->real_escape_string(trim($_POST['track'] ?? ''));
    $strand  = $conn->real_escape_string(trim($_POST['strand'] ?? ''));
    $year    = $conn->real_escape_string(trim($_POST['year_level'] ?? ''));
    $sec     = $conn->real_escape_string(trim($_POST['section'] ?? ''));
    $mob     = $conn->real_escape_string(trim($_POST['parent_mobile']  ?? ''));
    $gname   = $conn->real_escape_string(trim($_POST['guardian_name'] ?? ''));
    $gaddr   = $conn->real_escape_string(trim($_POST['guardian_address'] ?? ''));
    $irreg   = isset($_POST['is_irregular']) ? 1 : 0;
    $idays   = '[]'; // retained only for backward-compatible schema; irregular status no longer defines a gate schedule
    $photo   = '';
    $signature = '';

    if (!isset($db_error)) {
        if (!empty($_FILES['student_photo']['name'])) {
            $dir = 'uploads/students/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $fn = uniqid('s_') . ".$ext";
                if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $dir.$fn))
                    $photo = $dir.$fn;
            }
        }
        if (!empty($_FILES['student_signature']['name'])) {
            $dir = 'uploads/signatures/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['student_signature']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $fn = uniqid('sig_') . ".$ext";
                if (move_uploaded_file($_FILES['student_signature']['tmp_name'], $dir.$fn))
                    $signature = $dir.$fn;
            }
        }
        $chk = $conn->query("SELECT id FROM students WHERE rfid_uid='$rfid'");
        if ($chk && $chk->num_rows > 0) {
            echo json_encode(['status'=>'error','message'=>'RFID already registered.']); exit;
        }
        if ($conn->query("INSERT INTO students (rfid_uid,student_id_no,full_name,education_level,course,track,strand,year_level,section,parent_mobile,guardian_name,guardian_address,photo_path,signature_path,is_irregular,irregular_days_json,is_active)
                VALUES ('$rfid','$idno','$name','$level','$course','$track','$strand','$year','$sec','$mob','$gname','$gaddr','$photo','$signature',$irreg,'$idays',1)")) {
            $newStudentId=(int)$conn->insert_id; tracked_audit($conn,'STUDENT','STUDENT_REGISTERED','student',(string)$newStudentId,"$name registered.",['student_id_no'=>$idno,'education_level'=>$level]);
            echo json_encode(['status'=>'success','message'=>"$name registered successfully!"]);
        } else
            echo json_encode(['status'=>'error','message'=>$conn->error]);
    } else {
        echo json_encode(['status'=>'info','message'=>"Demo: $name would be registered."]);
    }
    exit;
}

// ── AJAX: UPDATE STUDENT ──────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_student') {
    header('Content-Type: application/json');
    $id      = intval($_POST['student_id']     ?? 0);
    $idno    = $conn->real_escape_string(trim($_POST['student_id_no']  ?? ''));
    $name    = $conn->real_escape_string(trim($_POST['full_name']      ?? ''));
    $level   = $conn->real_escape_string(trim($_POST['education_level'] ?? 'College'));
    $course  = $conn->real_escape_string(trim($_POST['course'] ?? ''));
    $track   = $conn->real_escape_string(trim($_POST['track'] ?? ''));
    $strand  = $conn->real_escape_string(trim($_POST['strand'] ?? ''));
    $year    = $conn->real_escape_string(trim($_POST['year_level'] ?? ''));
    $sec     = $conn->real_escape_string(trim($_POST['section'] ?? ''));
    $mob     = $conn->real_escape_string(trim($_POST['parent_mobile']  ?? ''));
    $gname   = $conn->real_escape_string(trim($_POST['guardian_name'] ?? ''));
    $gaddr   = $conn->real_escape_string(trim($_POST['guardian_address'] ?? ''));
    $irreg   = isset($_POST['is_irregular']) && $_POST['is_irregular'] === '1' ? 1 : 0;
    $idays   = '[]'; // retained only for backward-compatible schema; irregular status no longer defines a gate schedule

    if (!isset($db_error) && $id) {
        $signature_sql = '';
        if (!empty($_FILES['student_signature']['name'])) {
            $dir = 'uploads/signatures/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['student_signature']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $fn = uniqid('sig_') . ".$ext";
                if (move_uploaded_file($_FILES['student_signature']['tmp_name'], $dir.$fn)) {
                    $sig_path = $conn->real_escape_string($dir.$fn);
                    $signature_sql = ", signature_path='$sig_path'";
                }
            }
        }
        $ok = $conn->query("UPDATE students SET
            student_id_no='$idno', full_name='$name', education_level='$level', course='$course', track='$track', strand='$strand',
            year_level='$year', section='$sec', parent_mobile='$mob', guardian_name='$gname', guardian_address='$gaddr' $signature_sql,
            is_irregular=$irreg, irregular_days_json='$idays'
            WHERE id=$id");
        if ($ok) { tracked_audit($conn,'STUDENT','STUDENT_UPDATED','student',(string)$id,"$name student record updated.",['student_id_no'=>$idno,'education_level'=>$level]); echo json_encode(['status'=>'success','message'=>"$name updated successfully."]); }
        else     echo json_encode(['status'=>'error','message'=>$conn->error]);
    } else {
        echo json_encode(['status'=>'info','message'=>"Demo mode — changes simulated for record $id."]);
    }
    exit;
}


// ── AJAX: SPMO RE-ENABLE STUDENT RFID IN/OUT ───────────
if (isset($_POST['action']) && $_POST['action'] === 'reenable_student_scanning') {
    header('Content-Type: application/json');
    $id = intval($_POST['student_id'] ?? 0);
    if (isset($db_error) || !$id || !tracked_column_exists($conn,'students','scan_enabled')) {
        echo json_encode(['status'=>'error','message'=>'Run sql_spmo_denied_lock_upgrade.sql first.']); exit;
    }
    $r=$conn->query("SELECT full_name,scan_enabled,denied_attempt_count FROM students WHERE id=$id AND is_active=1 LIMIT 1");
    if(!$r || !$r->num_rows){echo json_encode(['status'=>'error','message'=>'Student record not found.']);exit;}
    $st=$r->fetch_assoc();
    $ok=$conn->query("UPDATE students SET scan_enabled=1,denied_attempt_count=0,scan_locked_at=NULL,scan_lock_reason=NULL,scan_unlocked_at=NOW(),scan_access_updated_at=NOW() WHERE id=$id");
    if($ok && tracked_table_exists($conn,'student_scan_access_audit')){
        $reason=$conn->real_escape_string('SPMO manually re-enabled campus IN/OUT scanning');
        @$conn->query("INSERT INTO student_scan_access_audit(student_id,action,attempt_count,reason,performed_by) VALUES($id,'UNLOCKED',0,'$reason','SPMO Admin')");
    }
    if($ok) tracked_audit($conn,'SECURITY','SCAN_ACCESS_REENABLED','student',(string)$id,$st['full_name'].' scan access manually re-enabled by SPMO.');
    echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?($st['full_name'].' can scan IN/OUT again. The denied counter has been reset to 0/3.'):$conn->error]); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['student_id'] ?? 0);
    if (!isset($db_error) && $id) {
        $nm='Student #'.$id;$rr=$conn->query("SELECT full_name FROM students WHERE id=$id LIMIT 1");if($rr&&$rr->num_rows)$nm=$rr->fetch_assoc()['full_name'];
        $ok=$conn->query("UPDATE students SET is_active=0 WHERE id=$id");if($ok)tracked_audit($conn,'STUDENT','STUDENT_DEACTIVATED','student',(string)$id,$nm.' student record deactivated.');
    }
    echo json_encode(['status'=>'success']); exit;
}

// ── AJAX: REJECTED / DENIED SCANS ───────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_rejected_scans') {
    header('Content-Type: application/json');
    $out = [];

    if (!isset($db_error)) {
        $table = $conn->query("SHOW TABLES LIKE 'rejected_scans'");
        if ($table && $table->num_rows > 0) {
            $res = $conn->query("
                SELECT
                    r.id,
                    r.student_id,
                    r.rfid_uid,
                    r.scan_type,
                    " . (tracked_column_exists($conn,'rejected_scans','movement_context') ? "r.movement_context" : "'NORMAL' AS movement_context") . ",
                    r.rejection_reason,
                    r.rejected_at,
                    s.full_name,
                    s.student_id_no,
                    s.course,
                    s.year_level,
                    s.section,
                    s.photo_path,
                    " . (tracked_column_exists($conn,'students','scan_enabled') ? "s.scan_enabled,s.denied_attempt_count,s.scan_locked_at," : "1 AS scan_enabled,0 AS denied_attempt_count,NULL AS scan_locked_at,") . "
                    (
                        SELECT COUNT(*)
                        FROM rejected_scans r2
                        WHERE r2.student_id = r.student_id
                          AND DATE(r2.rejected_at) = CURDATE()
                    ) AS attempts_today
                FROM rejected_scans r
                LEFT JOIN students s ON s.id = r.student_id
                ORDER BY r.rejected_at DESC
                LIMIT 20
            ");

            if ($res) {
                while ($row = $res->fetch_assoc()) $out[] = $row;
            }
        }
    }

    echo json_encode($out);
    exit;
}

// ── AJAX: LIVE CAMPUS MOVEMENTS ──────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_live_scans') {
    header('Content-Type: application/json');
    $out = [];
    $level = isset($_GET['level']) ? $conn->real_escape_string(trim($_GET['level'])) : '';
    if (!isset($db_error) && tracked_table_exists($conn, 'student_movement_logs')) {
        $where = "1=1";
        if ($level !== '') $where .= " AND COALESCE(s.education_level,'College')='$level'";
        $res = $conn->query("SELECT m.id,m.rfid_uid,m.movement_type,m.movement_context,m.authorization_reason,m.verified_at,m.verified_by,m.event_id,
                s.student_id_no,s.full_name,COALESCE(s.education_level,'College') AS education_level,
                s.course,s.track,s.strand,s.year_level,s.section,s.photo_path,s.is_irregular
            FROM student_movement_logs m JOIN students s ON m.student_id=s.id
            WHERE $where ORDER BY m.verified_at DESC,m.id DESC LIMIT 40");
        if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    }
    echo json_encode($out); exit;
}

// ── AJAX: MOVEMENT DASHBOARD STATS ───────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_movement_dashboard') {
    header('Content-Type: application/json');
    $level = isset($_GET['level']) ? $conn->real_escape_string(trim($_GET['level'])) : '';
    $today = date('Y-m-d');
    $result = ['inside'=>0,'entries'=>0,'exits'=>0,'early_exits'=>0,'rejected'=>0,'total'=>0];
    if (!isset($db_error) && tracked_table_exists($conn, 'student_movement_logs')) {
        $studentFilter = $level !== '' ? " AND COALESCE(s.education_level,'College')='$level'" : '';
        $movementFilter = $level !== '' ? " AND COALESCE(s.education_level,'College')='$level'" : '';
        if ($r=$conn->query("SELECT COUNT(*) c FROM students s WHERE s.is_active=1 $studentFilter")) $result['total']=(int)$r->fetch_assoc()['c'];
        if ($r=$conn->query("SELECT COUNT(*) c FROM students s JOIN student_movement_logs m ON m.id=(SELECT m2.id FROM student_movement_logs m2 WHERE m2.student_id=s.id AND DATE(m2.verified_at)='$today' ORDER BY m2.verified_at DESC,m2.id DESC LIMIT 1) WHERE s.is_active=1 AND m.movement_type='IN' $studentFilter")) $result['inside']=(int)$r->fetch_assoc()['c'];
        if ($r=$conn->query("SELECT COUNT(*) c FROM student_movement_logs m JOIN students s ON s.id=m.student_id WHERE DATE(m.verified_at)='$today' AND m.movement_type='IN' $movementFilter")) $result['entries']=(int)$r->fetch_assoc()['c'];
        if ($r=$conn->query("SELECT COUNT(*) c FROM student_movement_logs m JOIN students s ON s.id=m.student_id WHERE DATE(m.verified_at)='$today' AND m.movement_type='OUT' $movementFilter")) $result['exits']=(int)$r->fetch_assoc()['c'];
        if ($r=$conn->query("SELECT COUNT(*) c FROM student_movement_logs m JOIN students s ON s.id=m.student_id WHERE DATE(m.verified_at)='$today' AND m.movement_context='EARLY_EXIT' $movementFilter")) $result['early_exits']=(int)$r->fetch_assoc()['c'];
        if (tracked_table_exists($conn,'rejected_scans')) {
            $rejJoin = $level !== '' ? " JOIN students s ON s.id=r.student_id" : '';
            $rejFilter = $level !== '' ? " AND COALESCE(s.education_level,'College')='$level'" : '';
            if ($r=$conn->query("SELECT COUNT(*) c FROM rejected_scans r $rejJoin WHERE DATE(r.rejected_at)='$today' $rejFilter")) $result['rejected']=(int)$r->fetch_assoc()['c'];
        }
    }
    echo json_encode($result); exit;
}

// ── AJAX: STUDENT MOVEMENT HISTORY ───────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_student_movements') {
    header('Content-Type: application/json');
    $studentId=(int)($_GET['student_id']??0);
    $limit=max(10,min(500,(int)($_GET['limit']??150)));
    $out=[];
    if (!isset($db_error) && $studentId>0 && tracked_table_exists($conn,'student_movement_logs')) {
        // Ensure completed-day NO OUT anomalies are visible in the student's history too.
        tracked_backfill_no_out_range($conn,date('Y-m-d',strtotime('-31 days')),date('Y-m-d',strtotime('-1 day')));
        $r=$conn->query("SELECT id,'MOVEMENT' record_kind,movement_type,movement_context,authorization_reason,verified_at,verified_by,event_id,NULL exception_date,0 is_resolved,'' resolution_note FROM student_movement_logs WHERE student_id=$studentId ORDER BY verified_at DESC,id DESC LIMIT $limit");
        if($r) while($row=$r->fetch_assoc()) $out[]=$row;
        if(tracked_table_exists($conn,'daily_monitoring_exceptions')) {
            $er=$conn->query("SELECT id,'EXCEPTION' record_kind,NULL movement_type,'NO_OUT' movement_context,'' authorization_reason,last_movement_at verified_at,'System End-of-Day Check' verified_by,NULL event_id,exception_date,is_resolved,resolution_note FROM daily_monitoring_exceptions WHERE student_id=$studentId ORDER BY exception_date DESC,id DESC LIMIT $limit");
            if($er) while($row=$er->fetch_assoc()) $out[]=$row;
        }
        usort($out,function($a,$b){$ta=strtotime((string)($a['verified_at']??$a['exception_date']??''));$tb=strtotime((string)($b['verified_at']??$b['exception_date']??''));return $tb<=>$ta;});
        if(count($out)>$limit)$out=array_slice($out,0,$limit);
    }
    echo json_encode($out); exit;
}

// ── AJAX: CAMPUS MOVEMENT REPORT ─────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_report') {
    header('Content-Type: application/json');
    $df=$conn->real_escape_string($_GET['date_from']??date('Y-m-d',strtotime('-7 days')));
    $dt=$conn->real_escape_string($_GET['date_to']??date('Y-m-d'));
    $level=$conn->real_escape_string(trim($_GET['level']??''));
    $group=$conn->real_escape_string(trim($_GET['group']??''));
    $fy=$conn->real_escape_string(trim($_GET['year']??''));
    $section=$conn->real_escape_string(trim($_GET['section']??''));
    $search=$conn->real_escape_string(trim($_GET['search']??''));
    $movement=$conn->real_escape_string(trim($_GET['movement']??''));
    $context=$conn->real_escape_string(trim($_GET['context']??''));
    $cluster_event=(int)($_GET['cluster_event']??0);
    $cluster_id=(int)($_GET['cluster_id']??0);
    $out=[];
    if(!isset($db_error) && tracked_table_exists($conn,'student_movement_logs')) {
        $w=["DATE(m.verified_at) BETWEEN '$df' AND '$dt'"];
        if($level!=='') $w[]="COALESCE(s.education_level,'College')='$level'";
        if($group!=='') {
            if($level==='College') $w[]="s.course='$group'";
            elseif($level==='Senior High School') $w[]="s.strand='$group'";
        }
        if($fy!=='') $w[]="s.year_level='$fy'";
        if($section!=='') $w[]="s.section='$section'";
        if($search!=='') $w[]="(s.full_name LIKE '%$search%' OR s.student_id_no LIKE '%$search%' OR m.rfid_uid LIKE '%$search%')";
        if(in_array($movement,['IN','OUT'],true)) $w[]="m.movement_type='$movement'";
        if($context!=='') $w[]="m.movement_context='$context'";
        if($cluster_id>0) $w[]="EXISTS (SELECT 1 FROM intramural_cluster_members icm JOIN intramural_clusters ic ON ic.id=icm.cluster_id WHERE icm.student_id=s.id AND ic.id=$cluster_id".($cluster_event>0?" AND ic.event_id=$cluster_event":"").")";
        $ws=implode(' AND ',$w);
        $r=$conn->query("SELECT m.id,m.rfid_uid,m.movement_type,m.movement_context,m.authorization_reason,m.verified_at,m.verified_by,m.event_id,
            s.student_id_no,s.full_name,COALESCE(s.education_level,'College') education_level,s.course,s.track,s.strand,s.year_level,s.section,s.is_irregular
            FROM student_movement_logs m JOIN students s ON s.id=m.student_id WHERE $ws ORDER BY m.verified_at DESC,m.id DESC LIMIT 3000");
        if($r) while($row=$r->fetch_assoc()) $out[]=$row;
    }
    echo json_encode($out); exit;
}

// ── AJAX: DAILY MONITORING EXCEPTIONS ───────────────────
// NO_OUT is an exception/anomaly record. It never fabricates an OUT movement.
if (isset($_GET['action']) && $_GET['action'] === 'get_exception_report') {
    header('Content-Type: application/json');
    $df=$conn->real_escape_string($_GET['date_from']??date('Y-m-d',strtotime('-7 days')));
    $dt=$conn->real_escape_string($_GET['date_to']??date('Y-m-d'));
    $level=$conn->real_escape_string(trim($_GET['level']??''));
    $group=$conn->real_escape_string(trim($_GET['group']??''));
    $fy=$conn->real_escape_string(trim($_GET['year']??''));
    $section=$conn->real_escape_string(trim($_GET['section']??''));
    $search=$conn->real_escape_string(trim($_GET['search']??''));
    $exceptionStatus=$conn->real_escape_string(trim($_GET['exception_status']??''));
    $out=[];
    if(!isset($db_error) && tracked_table_exists($conn,'student_movement_logs')) {
        tracked_backfill_no_out_range($conn,$df,$dt);
        if(tracked_table_exists($conn,'daily_monitoring_exceptions')) {
            $w=["e.exception_date BETWEEN '$df' AND '$dt'"];
            if($level!=='') $w[]="COALESCE(s.education_level,'College')='$level'";
            if($group!=='') {
                if($level==='College') $w[]="s.course='$group'";
                elseif($level==='Senior High School') $w[]="s.strand='$group'";
            }
            if($fy!=='') $w[]="s.year_level='$fy'";
            if($section!=='') $w[]="s.section='$section'";
            if($search!=='') $w[]="(s.full_name LIKE '%$search%' OR s.student_id_no LIKE '%$search%' OR s.rfid_uid LIKE '%$search%')";
            if($exceptionStatus==='open') $w[]="e.is_resolved=0";
            elseif($exceptionStatus==='resolved') $w[]="e.is_resolved=1";
            $ws=implode(' AND ',$w);
            $r=$conn->query("SELECT e.id,e.exception_date,e.exception_type,e.last_movement_id,e.last_movement_at,e.detected_at,e.is_resolved,e.resolved_at,e.resolution_note,e.source,
                s.rfid_uid,s.student_id_no,s.full_name,COALESCE(s.education_level,'College') education_level,s.course,s.track,s.strand,s.year_level,s.section,s.is_irregular
                FROM daily_monitoring_exceptions e JOIN students s ON s.id=e.student_id
                WHERE $ws ORDER BY e.exception_date DESC,e.last_movement_at DESC,e.id DESC LIMIT 3000");
            if($r) while($row=$r->fetch_assoc()) $out[]=$row;
        }
    }
    echo json_encode($out); exit;
}

// ── AJAX: TIME-BOUND EVENT ATTENDANCE REPORT ────────────
// Physical campus movement is never inferred from this table. `attendance`
// is retained only for EVENT attendance qualified by an Intramurals window.
if (isset($_GET['action']) && $_GET['action'] === 'get_event_report') {
    header('Content-Type: application/json');
    $df      = $conn->real_escape_string($_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')));
    $dt      = $conn->real_escape_string($_GET['date_to']   ?? date('Y-m-d'));
    $level   = $conn->real_escape_string(trim($_GET['level'] ?? ''));
    $group   = $conn->real_escape_string(trim($_GET['group'] ?? ''));
    $fy      = $conn->real_escape_string(trim($_GET['year'] ?? ''));
    $section = $conn->real_escape_string(trim($_GET['section'] ?? ''));
    $search  = $conn->real_escape_string(trim($_GET['search'] ?? ''));
    $fst     = $conn->real_escape_string(trim($_GET['status'] ?? ''));
    $fi      = trim($_GET['irreg'] ?? '');
    $cluster_event = (int)($_GET['cluster_event'] ?? 0);
    $cluster_id    = (int)($_GET['cluster_id'] ?? 0);

    $out = [];
    if (!isset($db_error)) {
        // Recorded EVENT attendance. Movement logs remain the source of truth
        // for physical IN/OUT and are intentionally not queried here.
        if ($fst !== 'Absent') {
            $w = ["DATE(a.scan_time) BETWEEN '$df' AND '$dt'", "a.attendance_source='EVENT'"];
            if ($cluster_event > 0) $w[] = "a.event_id=$cluster_event";
            if ($level !== '') $w[] = "COALESCE(s.education_level,'College')='$level'";
            if ($group !== '') {
                if ($level === 'College') $w[] = "s.course='$group'";
                elseif ($level === 'Senior High School') $w[] = "s.strand='$group'";
            }
            if ($fy !== '') $w[] = "s.year_level='$fy'";
            if ($section !== '') $w[] = "s.section='$section'";
            if ($search !== '') $w[] = "(s.full_name LIKE '%$search%' OR s.student_id_no LIKE '%$search%' OR s.rfid_uid LIKE '%$search%')";
            if ($fst !== '') $w[] = "a.status='$fst'";
            if ($fi === '0' || $fi === '1') $w[] = "s.is_irregular=".(int)$fi;
            if ($cluster_id > 0) {
                $w[] = "EXISTS (SELECT 1 FROM intramural_cluster_members icm JOIN intramural_clusters ic ON ic.id=icm.cluster_id WHERE icm.student_id=s.id AND ic.id=$cluster_id" . ($cluster_event>0 ? " AND ic.event_id=$cluster_event" : "") . ")";
            }
            $ws = implode(' AND ', $w);
            $res = $conn->query("SELECT a.id,a.event_id,a.scan_time,a.time_out,a.status,a.scan_type,
                    s.rfid_uid,s.student_id_no,s.full_name,
                    COALESCE(s.education_level,'College') AS education_level,
                    s.course,s.track,s.strand,s.year_level,s.section,s.is_irregular
                FROM attendance a JOIN students s ON s.id=a.student_id
                WHERE $ws ORDER BY a.scan_time DESC,a.id DESC LIMIT 1500");
            if ($res) while ($r=$res->fetch_assoc()) $out[]=$r;
        }

        /*
         * Fallback / reconciliation for Intramurals:
         * derive EVENT attendance from verified physical movement logs when the
         * event attendance row was not created by the kiosk. This keeps the
         * selected event/cluster report useful even if the event queue was
         * temporarily offline. The event's own time window is used; normal
         * campus schedules are ignored for this event-only view.
         */
        if ($cluster_event > 0 && tracked_table_exists($conn,'student_movement_logs') && tracked_table_exists($conn,'intramural_events')) {
            $er2 = $conn->query("SELECT * FROM intramural_events WHERE id=$cluster_event LIMIT 1");
            if ($er2 && $er2->num_rows > 0) {
                $ev2 = $er2->fetch_assoc();
                $evStart = $ev2['start_date'] ?: $df;
                $evEnd   = $ev2['end_date'] ?: $dt;
                $rangeStart2 = max($df, $evStart);
                $rangeEnd2   = min($dt, $evEnd);

                if ($rangeStart2 <= $rangeEnd2) {
                    $already = [];
                    foreach ($out as $rr) {
                        if (!empty($rr['student_id']) && !empty($rr['scan_time'])) {
                            $already[$rr['student_id'].'|'.substr($rr['scan_time'],0,10)] = true;
                        }
                    }

                    $mw = ["DATE(m.verified_at) BETWEEN '$rangeStart2' AND '$rangeEnd2'", "s.is_active=1"];
                    if ($level !== '') $mw[] = "COALESCE(s.education_level,'College')='$level'";
                    if ($group !== '') {
                        if ($level === 'College') $mw[] = "s.course='$group'";
                        elseif ($level === 'Senior High School') $mw[] = "s.strand='$group'";
                    }
                    if ($fy !== '') $mw[] = "s.year_level='$fy'";
                    if ($section !== '') $mw[] = "s.section='$section'";
                    if ($search !== '') $mw[] = "(s.full_name LIKE '%$search%' OR s.student_id_no LIKE '%$search%' OR s.rfid_uid LIKE '%$search%')";
                    if ($fi === '0' || $fi === '1') $mw[] = "s.is_irregular=".(int)$fi;
                    // Intramurals roster: only students assigned to the selected event.
                    $mw[] = "EXISTS (SELECT 1 FROM intramural_cluster_members icm JOIN intramural_clusters ic ON ic.id=icm.cluster_id WHERE icm.student_id=s.id AND ic.event_id=$cluster_event" . ($cluster_id>0 ? " AND ic.id=$cluster_id" : "") . ")";
                    $mws = implode(' AND ', $mw);

                    $mr = $conn->query("SELECT s.id AS student_id,s.rfid_uid,s.student_id_no,s.full_name,
                            COALESCE(s.education_level,'College') AS education_level,s.course,s.track,s.strand,s.year_level,s.section,s.is_irregular,
                            DATE(m.verified_at) AS movement_date,
                            MIN(CASE WHEN m.movement_type='IN' THEN m.verified_at END) AS first_in,
                            MAX(CASE WHEN m.movement_type='OUT' THEN m.verified_at END) AS last_out
                        FROM student_movement_logs m JOIN students s ON s.id=m.student_id
                        WHERE $mws
                        GROUP BY s.id,DATE(m.verified_at)
                        ORDER BY movement_date DESC,first_in DESC");
                    if ($mr) {
                        while ($rr=$mr->fetch_assoc()) {
                            if (empty($rr['first_in'])) continue;
                            $key = $rr['student_id'].'|'.$rr['movement_date'];
                            if (isset($already[$key])) continue;

                            $isCollege = (($rr['education_level'] ?? 'College') === 'College');
                            $startT  = $isCollege ? ($ev2['college_time_in_start'] ?? '06:00:00') : ($ev2['hs_time_in_start'] ?? '06:30:00');
                            $onTimeT = $isCollege ? ($ev2['college_ontime_cutoff'] ?? '07:30:00') : ($ev2['hs_ontime_cutoff'] ?? '07:30:00');
                            $lateT   = $isCollege ? ($ev2['college_late_cutoff'] ?? '08:30:00') : ($ev2['hs_late_cutoff'] ?? '08:30:00');
                            $inTime = substr($rr['first_in'],11,8);

                            // The Intramurals schedule overrides normal class timing for event qualification.
                            if ($inTime < $startT || $inTime > $lateT) continue;
                            $status2 = ($inTime <= $onTimeT) ? 'On Time' : 'Late';
                            if ($fst !== '' && $fst !== $status2) continue;

                            $out[] = [
                                'id' => 'movement_event_'.$cluster_event.'_'.$rr['student_id'].'_'.$rr['movement_date'],
                                'student_id' => $rr['student_id'],
                                'event_id' => $cluster_event,
                                'scan_time' => $rr['first_in'],
                                'time_out' => $rr['last_out'],
                                'status' => $status2,
                                'scan_type' => 'IN',
                                'rfid_uid' => $rr['rfid_uid'],
                                'student_id_no' => $rr['student_id_no'],
                                'full_name' => $rr['full_name'],
                                'education_level' => $rr['education_level'],
                                'course' => $rr['course'],
                                'track' => $rr['track'],
                                'strand' => $rr['strand'],
                                'year_level' => $rr['year_level'],
                                'section' => $rr['section'],
                                'is_irregular' => $rr['is_irregular'],
                                'derived_from_movement' => 1
                            ];
                            $already[$key] = true;
                        }
                    }
                }
            }
        }

        // Event absence is only meaningful when a specific event is selected.
        // It uses the EVENT date range, not normal class schedules.
        if (($fst === '' || $fst === 'Absent') && $cluster_event > 0 && tracked_table_exists($conn,'intramural_events')) {
            $er = $conn->query("SELECT id,name,start_date,end_date,college_late_cutoff,hs_late_cutoff FROM intramural_events WHERE id=$cluster_event LIMIT 1");
            if ($er && $er->num_rows > 0) {
                $event = $er->fetch_assoc();
                $eventStart = $event['start_date'] ?: $df;
                $eventEnd   = $event['end_date'] ?: $dt;
                $rangeStart = max($df, $eventStart);
                $rangeEnd   = min($dt, $eventEnd);

                if ($rangeStart <= $rangeEnd) {
                    $sw = ["s.is_active=1"];
                    if ($level !== '') $sw[] = "COALESCE(s.education_level,'College')='$level'";
                    if ($group !== '') {
                        if ($level === 'College') $sw[] = "s.course='$group'";
                        elseif ($level === 'Senior High School') $sw[] = "s.strand='$group'";
                    }
                    if ($fy !== '') $sw[] = "s.year_level='$fy'";
                    if ($section !== '') $sw[] = "s.section='$section'";
                    if ($search !== '') $sw[] = "(s.full_name LIKE '%$search%' OR s.student_id_no LIKE '%$search%' OR s.rfid_uid LIKE '%$search%')";
                    if ($fi === '0' || $fi === '1') $sw[] = "s.is_irregular=".(int)$fi;
                    // An Event absence is computed only for members assigned to the selected event.
                    $sw[] = "EXISTS (SELECT 1 FROM intramural_cluster_members icm JOIN intramural_clusters ic ON ic.id=icm.cluster_id WHERE icm.student_id=s.id AND ic.event_id=$cluster_event" . ($cluster_id>0 ? " AND ic.id=$cluster_id" : "") . ")";
                    $sws = implode(' AND ', $sw);

                    $students = [];
                    $sr = $conn->query("SELECT s.id,s.rfid_uid,s.student_id_no,s.full_name,
                            COALESCE(s.education_level,'College') AS education_level,
                            s.course,s.track,s.strand,s.year_level,s.section,s.is_irregular
                        FROM students s WHERE $sws ORDER BY s.full_name");
                    if ($sr) while ($r=$sr->fetch_assoc()) $students[]=$r;

                    $attMap = [];
                    $ar = $conn->query("SELECT DISTINCT student_id,DATE(scan_time) d FROM attendance
                        WHERE attendance_source='EVENT' AND event_id=$cluster_event AND DATE(scan_time) BETWEEN '$rangeStart' AND '$rangeEnd'");
                    if ($ar) while ($r=$ar->fetch_assoc()) $attMap[$r['student_id'].'|'.$r['d']] = true;

                    $today = date('Y-m-d');
                    $now = date('H:i:s');
                    $period = new DatePeriod(new DateTime($rangeStart), new DateInterval('P1D'), (new DateTime($rangeEnd))->modify('+1 day'));
                    foreach ($period as $dateObj) {
                        $d = $dateObj->format('Y-m-d');
                        if ($d > $today) continue;
                        foreach ($students as $student) {
                            $cutoff = ($student['education_level'] === 'College')
                                ? ($event['college_late_cutoff'] ?? '08:30:00')
                                : ($event['hs_late_cutoff'] ?? '08:30:00');
                            if ($d === $today && $now < $cutoff) continue;
                            if (isset($attMap[$student['id'].'|'.$d])) continue;

                            $row = $student;
                            $row['id'] = 'event_abs_'.$cluster_event.'_'.$student['id'].'_'.$d;
                            $row['event_id'] = $cluster_event;
                            $row['scan_time'] = $d.' 00:00:00';
                            $row['time_out'] = null;
                            $row['status'] = 'Absent';
                            $row['scan_type'] = null;
                            $out[] = $row;
                        }
                    }
                }
            }
        }
    }

    echo json_encode(array_values($out));
    exit;
}


// ── AJAX: INTRAMURALS CLUSTER MANAGER ────────────────────
function cluster_tables_ready($conn) {
    foreach (['intramural_events','intramural_clusters','intramural_cluster_members','intramural_generations'] as $t) {
        $safe = $conn->real_escape_string($t);
        $r = $conn->query("SHOW TABLES LIKE '$safe'");
        if (!$r || $r->num_rows === 0) return false;
    }
    return true;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_intramural_events') {
    header('Content-Type: application/json');
    if (isset($db_error) || !cluster_tables_ready($conn)) { echo json_encode(['status'=>'migration_required','events'=>[]]); exit; }
    $out=[];
    $res=$conn->query("SELECT e.*,
        (SELECT COUNT(*) FROM intramural_clusters c WHERE c.event_id=e.id AND c.division='College') AS college_clusters,
        (SELECT COUNT(*) FROM intramural_clusters c WHERE c.event_id=e.id AND c.division='High School') AS hs_clusters
        FROM intramural_events e ORDER BY e.is_active DESC,e.school_year DESC,e.id DESC");
    if($res) while($r=$res->fetch_assoc()) $out[]=$r;
    echo json_encode(['status'=>'success','events'=>$out]); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_intramural_event') {
    header('Content-Type: application/json');
    if (isset($db_error) || !cluster_tables_ready($conn)) { echo json_encode(['status'=>'error','message'=>'Run sql_intramurals_cluster_migration.sql first.']); exit; }
    $id=(int)($_POST['id']??0);
    $name=$conn->real_escape_string(trim($_POST['name']??''));
    $sy=$conn->real_escape_string(trim($_POST['school_year']??''));
    $start=$conn->real_escape_string(trim($_POST['start_date']??''));
    $end=$conn->real_escape_string(trim($_POST['end_date']??''));
    $override=(int)($_POST['override_enabled']??1)===1?1:0;
    $normTime=function($v,$fallback){ $v=trim((string)$v); return preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$v) ? (strlen($v)===5?$v.':00':$v) : $fallback; };
    $cti=$conn->real_escape_string($normTime($_POST['college_time_in_start']??'','06:00:00'));
    $cot=$conn->real_escape_string($normTime($_POST['college_ontime_cutoff']??'','07:30:00'));
    $clt=$conn->real_escape_string($normTime($_POST['college_late_cutoff']??'','08:30:00'));
    $cle=(int)($_POST['college_lunch_enabled']??0)===1?1:0;
    $clo1=$conn->real_escape_string($normTime($_POST['college_lunch_out_start']??'','11:30:00'));
    $clo2=$conn->real_escape_string($normTime($_POST['college_lunch_out_end']??'','12:15:00'));
    $cli1=$conn->real_escape_string($normTime($_POST['college_lunch_in_start']??'','12:15:00'));
    $cli2=$conn->real_escape_string($normTime($_POST['college_lunch_in_end']??'','13:15:00'));
    $cto1=$conn->real_escape_string($normTime($_POST['college_time_out_start']??'','16:00:00'));
    $cto2=$conn->real_escape_string($normTime($_POST['college_time_out_end']??'','18:00:00'));
    $hti=$conn->real_escape_string($normTime($_POST['hs_time_in_start']??'','06:30:00'));
    $hot=$conn->real_escape_string($normTime($_POST['hs_ontime_cutoff']??'','07:30:00'));
    $hlt=$conn->real_escape_string($normTime($_POST['hs_late_cutoff']??'','08:30:00'));
    $hle=(int)($_POST['hs_lunch_enabled']??0)===1?1:0;
    $hlo1=$conn->real_escape_string($normTime($_POST['hs_lunch_out_start']??'','11:30:00'));
    $hlo2=$conn->real_escape_string($normTime($_POST['hs_lunch_out_end']??'','12:15:00'));
    $hli1=$conn->real_escape_string($normTime($_POST['hs_lunch_in_start']??'','12:15:00'));
    $hli2=$conn->real_escape_string($normTime($_POST['hs_lunch_in_end']??'','13:15:00'));
    $hto1=$conn->real_escape_string($normTime($_POST['hs_time_out_start']??'','15:30:00'));
    $hto2=$conn->real_escape_string($normTime($_POST['hs_time_out_end']??'','17:30:00'));
    if($name===''||$sy===''){ echo json_encode(['status'=>'error','message'=>'Event name and school year are required.']); exit; }
    if($start===''||$end===''){ echo json_encode(['status'=>'error','message'=>'Start and end dates are required for automatic schedule override.']); exit; }
    if($start>$end){ echo json_encode(['status'=>'error','message'=>'End date cannot be earlier than start date.']); exit; }
    $set="name='$name',school_year='$sy',start_date='$start',end_date='$end',override_enabled=$override,college_time_in_start='$cti',college_ontime_cutoff='$cot',college_late_cutoff='$clt',college_lunch_enabled=$cle,college_lunch_out_start='$clo1',college_lunch_out_end='$clo2',college_lunch_in_start='$cli1',college_lunch_in_end='$cli2',college_time_out_start='$cto1',college_time_out_end='$cto2',hs_time_in_start='$hti',hs_ontime_cutoff='$hot',hs_late_cutoff='$hlt',hs_lunch_enabled=$hle,hs_lunch_out_start='$hlo1',hs_lunch_out_end='$hlo2',hs_lunch_in_start='$hli1',hs_lunch_in_end='$hli2',hs_time_out_start='$hto1',hs_time_out_end='$hto2'";
    if($id>0){
        $conn->query("UPDATE intramural_events SET $set WHERE id=$id");
    } else {
        $conn->query("INSERT INTO intramural_events(name,school_year,start_date,end_date,is_active,override_enabled,college_time_in_start,college_ontime_cutoff,college_late_cutoff,college_lunch_enabled,college_lunch_out_start,college_lunch_out_end,college_lunch_in_start,college_lunch_in_end,college_time_out_start,college_time_out_end,hs_time_in_start,hs_ontime_cutoff,hs_late_cutoff,hs_lunch_enabled,hs_lunch_out_start,hs_lunch_out_end,hs_lunch_in_start,hs_lunch_in_end,hs_time_out_start,hs_time_out_end) VALUES('$name','$sy','$start','$end',1,$override,'$cti','$cot','$clt',$cle,'$clo1','$clo2','$cli1','$cli2','$cto1','$cto2','$hti','$hot','$hlt',$hle,'$hlo1','$hlo2','$hli1','$hli2','$hto1','$hto2')");
        $id=$conn->insert_id;
    }
    tracked_audit($conn,'INTRAMURALS','EVENT_SAVED','intramural_event',(string)$id,"Intramurals event '$name' saved.",['school_year'=>$sy,'start_date'=>$start,'end_date'=>$end]);
    echo json_encode(['status'=>'success','id'=>$id,'message'=>'Event saved. Its temporary attendance schedule automatically takes priority only during the event dates.']); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_intramural_event') {
    header('Content-Type: application/json');
    if (isset($db_error) || !cluster_tables_ready($conn)) { echo json_encode(['status'=>'error','message'=>'Intramurals tables are not ready.']); exit; }
    $id=(int)($_POST['event_id']??0);
    if($id<=0){ echo json_encode(['status'=>'error','message'=>'Invalid event.']); exit; }

    $er=$conn->query("SELECT id,name,is_active,start_date,end_date FROM intramural_events WHERE id=$id LIMIT 1");
    if(!$er || $er->num_rows===0){ echo json_encode(['status'=>'error','message'=>'Event no longer exists.']); exit; }
    $event=$er->fetch_assoc();
    $eventName=$event['name'];

    $clusterCount=0; $memberCount=0; $generationCount=0;
    if($r=$conn->query("SELECT COUNT(*) c FROM intramural_clusters WHERE event_id=$id")) $clusterCount=(int)$r->fetch_assoc()['c'];
    if($r=$conn->query("SELECT COUNT(*) c FROM intramural_cluster_members m JOIN intramural_clusters c ON c.id=m.cluster_id WHERE c.event_id=$id")) $memberCount=(int)$r->fetch_assoc()['c'];
    if($r=$conn->query("SELECT COUNT(*) c FROM intramural_generations WHERE event_id=$id")) $generationCount=(int)$r->fetch_assoc()['c'];

    $conn->begin_transaction();
    try {
        // Explicit cleanup keeps deletion reliable even on databases created before FK cascades were added.
        $conn->query("DELETE m FROM intramural_cluster_members m INNER JOIN intramural_clusters c ON c.id=m.cluster_id WHERE c.event_id=$id");
        $conn->query("DELETE FROM intramural_generations WHERE event_id=$id");
        $conn->query("DELETE FROM intramural_clusters WHERE event_id=$id");
        $conn->query("DELETE FROM intramural_events WHERE id=$id");
        $conn->commit();
        tracked_audit($conn,'INTRAMURALS','EVENT_DELETED','intramural_event',(string)$id,"Intramurals event '$eventName' deleted.",['clusters'=>$clusterCount,'assignments'=>$memberCount,'generations'=>$generationCount]);
        echo json_encode([
            'status'=>'success',
            'message'=>"Event '$eventName' deleted together with $clusterCount clusters, $memberCount assignments and $generationCount generation-history records. Regular schedules are unaffected."
        ]);
    } catch(Throwable $e){
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Could not delete the event. No Intramurals data was removed.']);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_intramural_override_status') {
    header('Content-Type: application/json');
    if (isset($db_error) || !cluster_tables_ready($conn)) { echo json_encode(['status'=>'migration_required']); exit; }
    $today=date('Y-m-d');
    $r=$conn->query("SELECT * FROM intramural_events WHERE is_active=1 AND override_enabled=1 AND start_date<='$today' AND end_date>='$today' ORDER BY id DESC LIMIT 1");
    if(!$r||$r->num_rows===0){ echo json_encode(['status'=>'success','active'=>false]); exit; }
    echo json_encode(['status'=>'success','active'=>true,'event'=>$r->fetch_assoc()]); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'set_active_intramural_event') {
    header('Content-Type: application/json');
    if (!cluster_tables_ready($conn)) { echo json_encode(['status'=>'error','message'=>'Migration required.']); exit; }
    $id=(int)($_POST['id']??0);
    $conn->begin_transaction();
    try { $conn->query("UPDATE intramural_events SET is_active=0"); $conn->query("UPDATE intramural_events SET is_active=1 WHERE id=$id"); $conn->commit(); tracked_audit($conn,'INTRAMURALS','EVENT_ACTIVATED','intramural_event',(string)$id,'Intramurals event set active.'); echo json_encode(['status'=>'success']); }
    catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>'error','message'=>'Could not activate event.']);}
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_cluster_dashboard') {
    header('Content-Type: application/json');
    if (isset($db_error) || !cluster_tables_ready($conn)) { echo json_encode(['status'=>'migration_required']); exit; }
    $event=(int)($_GET['event_id']??0); $division=($_GET['division']??'College')==='High School'?'High School':'College';
    $clusters=[]; $assigned=0;
    $res=$conn->query("SELECT c.*, (SELECT COUNT(*) FROM intramural_cluster_members m WHERE m.cluster_id=c.id) member_count FROM intramural_clusters c WHERE c.event_id=$event AND c.division='".$conn->real_escape_string($division)."' ORDER BY c.sort_order,c.id");
    if($res) while($r=$res->fetch_assoc()){
        $cid=(int)$r['id']; $r['members']=[];
        $mr=$conn->query("SELECT s.id,s.student_id_no,s.full_name,COALESCE(s.education_level,'College') education_level,s.course,s.track,s.strand,s.year_level,s.section,s.photo_path
            FROM intramural_cluster_members m JOIN students s ON s.id=m.student_id WHERE m.cluster_id=$cid ORDER BY s.full_name");
        if($mr) while($m=$mr->fetch_assoc()) $r['members'][]=$m;
        $assigned += (int)$r['member_count']; $clusters[]=$r;
    }
    $poolWhere=$division==='College'?"COALESCE(education_level,'College')='College'":"COALESCE(education_level,'College') IN ('Senior High School','Junior High School')";
    $pr=$conn->query("SELECT COUNT(*) c FROM students WHERE is_active=1 AND $poolWhere"); $pool=$pr?(int)$pr->fetch_assoc()['c']:0;
    $gr=$conn->query("SELECT * FROM intramural_generations WHERE event_id=$event AND division='".$conn->real_escape_string($division)."' ORDER BY generated_at DESC,id DESC LIMIT 8"); $history=[]; if($gr)while($g=$gr->fetch_assoc())$history[]=$g;
    echo json_encode(['status'=>'success','clusters'=>$clusters,'pool_count'=>$pool,'assigned_count'=>$assigned,'unassigned_count'=>max(0,$pool-$assigned),'history'=>$history]); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_clusters') {
    header('Content-Type: application/json');
    if (!cluster_tables_ready($conn)) { echo json_encode(['status'=>'error','message'=>'Migration required.']); exit; }
    $event=(int)($_POST['event_id']??0); $division=($_POST['division']??'College')==='High School'?'High School':'College';
    $names=json_decode($_POST['names']??'[]',true); if(!is_array($names))$names=[];
    $names=array_values(array_filter(array_map(fn($x)=>trim((string)$x),$names),fn($x)=>$x!==''));
    if($event<=0||count($names)<2){echo json_encode(['status'=>'error','message'=>'Create at least 2 cluster names.']);exit;}
    $div=$conn->real_escape_string($division);
    $conn->begin_transaction();
    try{
        $existing=[];$r=$conn->query("SELECT id FROM intramural_clusters WHERE event_id=$event AND division='$div' ORDER BY sort_order,id");if($r)while($x=$r->fetch_assoc())$existing[]=(int)$x['id'];
        foreach($names as $i=>$name){$n=$conn->real_escape_string($name);if(isset($existing[$i])){$id=$existing[$i];$conn->query("UPDATE intramural_clusters SET name='$n',sort_order=".($i+1)." WHERE id=$id");}else{$conn->query("INSERT INTO intramural_clusters(event_id,division,name,sort_order) VALUES($event,'$div','$n',".($i+1).")");}}
        if(count($existing)>count($names)){
            $remove=array_slice($existing,count($names));$ids=implode(',',array_map('intval',$remove));if($ids){$conn->query("DELETE FROM intramural_cluster_members WHERE cluster_id IN ($ids)");$conn->query("DELETE FROM intramural_clusters WHERE id IN ($ids)");}
        }
        $conn->commit();tracked_audit($conn,'INTRAMURALS','CLUSTERS_UPDATED','intramural_event',(string)$event,"$division cluster names updated.",['cluster_count'=>count($names)]);echo json_encode(['status'=>'success','message'=>'Cluster names saved.']);
    }catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>'error','message'=>'Could not save clusters.']);} exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'generate_cluster_assignments') {
    header('Content-Type: application/json');
    if (!cluster_tables_ready($conn)) { echo json_encode(['status'=>'error','message'=>'Migration required.']); exit; }
    $event=(int)($_POST['event_id']??0); $division=($_POST['division']??'College')==='High School'?'High School':'College'; $div=$conn->real_escape_string($division);
    $cr=$conn->query("SELECT id FROM intramural_clusters WHERE event_id=$event AND division='$div' ORDER BY sort_order,id");$clusterIds=[];if($cr)while($c=$cr->fetch_assoc())$clusterIds[]=(int)$c['id'];
    if(count($clusterIds)<2){echo json_encode(['status'=>'error','message'=>'Save at least 2 clusters before generating.']);exit;}
    $poolWhere=$division==='College'?"COALESCE(education_level,'College')='College'":"COALESCE(education_level,'College') IN ('Senior High School','Junior High School')";
    $sr=$conn->query("SELECT id FROM students WHERE is_active=1 AND $poolWhere ORDER BY RAND()");$studentIds=[];if($sr)while($x=$sr->fetch_assoc())$studentIds[]=(int)$x['id'];
    if(!$studentIds){echo json_encode(['status'=>'error','message'=>'No active students found in this division.']);exit;}
    $conn->begin_transaction();
    try{
        $ids=implode(',',$clusterIds);$conn->query("DELETE FROM intramural_cluster_members WHERE cluster_id IN ($ids)");
        foreach($studentIds as $i=>$sid){$cid=$clusterIds[$i%count($clusterIds)];$conn->query("INSERT INTO intramural_cluster_members(cluster_id,student_id,assigned_method) VALUES($cid,$sid,'random')");}
        $cnt=count($studentIds);$cc=count($clusterIds);$conn->query("INSERT INTO intramural_generations(event_id,division,student_count,cluster_count) VALUES($event,'$div',$cnt,$cc)");
        $conn->commit();tracked_audit($conn,'INTRAMURALS','CLUSTERS_RANDOMIZED','intramural_event',(string)$event,"$division cluster assignments regenerated.",['students'=>$cnt,'clusters'=>$cc]);echo json_encode(['status'=>'success','message'=>"Generated balanced random assignments for $cnt students across $cc clusters."]);
    }catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>'error','message'=>'Generation failed: '.$e->getMessage()]);} exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'move_cluster_member') {
    header('Content-Type: application/json');
    $student=(int)($_POST['student_id']??0);$cluster=(int)($_POST['cluster_id']??0);$event=(int)($_POST['event_id']??0);$division=($_POST['division']??'College')==='High School'?'High School':'College';$div=$conn->real_escape_string($division);
    $valid=$conn->query("SELECT id FROM intramural_clusters WHERE id=$cluster AND event_id=$event AND division='$div' LIMIT 1");if(!$valid||$valid->num_rows===0){echo json_encode(['status'=>'error','message'=>'Invalid cluster.']);exit;}
    $cids=[];$r=$conn->query("SELECT id FROM intramural_clusters WHERE event_id=$event AND division='$div'");if($r)while($x=$r->fetch_assoc())$cids[]=(int)$x['id'];$ids=implode(',',$cids);
    $conn->begin_transaction();try{if($ids)$conn->query("DELETE FROM intramural_cluster_members WHERE student_id=$student AND cluster_id IN ($ids)");$conn->query("INSERT INTO intramural_cluster_members(cluster_id,student_id,assigned_method) VALUES($cluster,$student,'manual')");$conn->commit();tracked_audit($conn,'INTRAMURALS','CLUSTER_MEMBER_MOVED','student',(string)$student,'Student manually moved to a different Intramurals cluster.',['event_id'=>$event,'cluster_id'=>$cluster,'division'=>$division]);echo json_encode(['status'=>'success']);}catch(Throwable $e){$conn->rollback();echo json_encode(['status'=>'error']);}exit;
}

// ── AJAX: GET SMS QUEUE STATUS ────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_sms_status') {
    header('Content-Type: application/json');
    $out = ['pending'=>0,'sent'=>0,'failed'=>0,'recent'=>[]];
    if (!isset($db_error)) {
        $res = $conn->query("SELECT status, COUNT(*) as cnt FROM sms_queue GROUP BY status");
        while ($r = $res->fetch_assoc()) $out[$r['status']] = (int)$r['cnt'];
        $res = $conn->query("SELECT mobile, LEFT(message,60) as msg, status, created_at FROM sms_queue ORDER BY created_at DESC LIMIT 10");
        while ($r = $res->fetch_assoc()) $out['recent'][] = $r;
    }
    echo json_encode($out); exit;
}

// ── AJAX: SAVE SYSTEM CONFIG ──────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_config') {
    header('Content-Type: application/json');
    $out = [];
    if (!isset($db_error)) {
        $res = $conn->query("SELECT config_key, config_value FROM system_config");
        while ($r = $res->fetch_assoc()) $out[$r['config_key']] = $r['config_value'];
    }
    echo json_encode($out); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
    header('Content-Type: application/json');
    $fields = ['school_name','sms_enabled'];
    if (!isset($db_error)) {
        foreach ($fields as $key) {
            if (isset($_POST[$key])) {
                $k = $conn->real_escape_string($key);
                $v = $conn->real_escape_string($_POST[$key]);
                $conn->query("INSERT INTO system_config (config_key,config_value) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE config_value='$v'");
            }
        }
        tracked_audit($conn,'SETTINGS','SYSTEM_CONFIG_UPDATED','system_config','','System configuration updated.',['fields'=>$fields]);
        echo json_encode(['status'=>'success','message'=>'Configuration saved.']);
    } else {
        echo json_encode(['status'=>'info','message'=>'Demo mode.']);
    }
    exit;
}


// ── AJAX: VERIFIED LOG RETENTION / BACKUPS ─────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_retention_status') {
    header('Content-Type: application/json');
    if (isset($db_error)) { echo json_encode(['status'=>'error','message'=>'Database unavailable.']); exit; }
    echo json_encode(['status'=>'success'] + tr_retention_status($conn)); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_retention_config') {
    header('Content-Type: application/json');
    if (isset($db_error)) { echo json_encode(['status'=>'error','message'=>'Database unavailable.']); exit; }
    $ret=max(7,min(365,(int)($_POST['log_retention_days']??30)));
    $months=max(12,min(120,(int)($_POST['archive_retention_months']??12)));
    $vals=[
        'log_retention_days'=>(string)$ret,
        'archive_retention_months'=>(string)$months,
        'daily_backup_enabled'=>!empty($_POST['daily_backup_enabled'])?'1':'0',
        'monthly_archive_enabled'=>!empty($_POST['monthly_archive_enabled'])?'1':'0',
        'auto_purge_enabled'=>!empty($_POST['auto_purge_enabled'])?'1':'0',
    ];
    foreach($vals as $k=>$v){$ks=$conn->real_escape_string($k);$vs=$conn->real_escape_string($v);$conn->query("INSERT INTO system_config(config_key,config_value) VALUES('$ks','$vs') ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)");}
    tracked_audit($conn,'BACKUP','RETENTION_POLICY_UPDATED','system_config','','Log retention and backup policy updated.',$vals);
    echo json_encode(['status'=>'success','message'=>'Retention & backup policy saved.']); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'run_retention_now') {
    header('Content-Type: application/json');
    if (isset($db_error) || !tracked_table_exists($conn,'log_backup_runs')) { echo json_encode(['status'=>'error','message'=>'Run sql_log_retention_backup_upgrade.sql first.']); exit; }
    $retResult=tr_run_retention($conn,true,true);tracked_audit($conn,'BACKUP','BACKUP_RUN_MANUAL','log_backup_runs','',$retResult['message']??'Manual retention backup run.',['status'=>$retResult['status']??'']);echo json_encode($retResult); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'add_retention_hold') {
    header('Content-Type: application/json');
    if (!tracked_table_exists($conn,'log_retention_holds')) { echo json_encode(['status'=>'error','message'=>'Run the log retention migration first.']); exit; }
    $name=trim((string)($_POST['case_name']??''));$from=(string)($_POST['date_from']??'');$to=(string)($_POST['date_to']??'');$note=trim((string)($_POST['note']??''));
    if($name===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to){echo json_encode(['status'=>'error','message'=>'Enter a case name and valid date range.']);exit;}
    $n=$conn->real_escape_string($name);$f=$conn->real_escape_string($from);$t=$conn->real_escape_string($to);$no=$conn->real_escape_string($note);
    $ok=$conn->query("INSERT INTO log_retention_holds(case_name,date_from,date_to,note) VALUES('$n','$f','$t','$no')");
    if($ok)tracked_audit($conn,'BACKUP','RETENTION_HOLD_ADDED','retention_hold',(string)$conn->insert_id,'Incident retention hold added.',['case_name'=>$name,'date_from'=>$from,'date_to'=>$to]);
    echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?'Incident hold added. These dates are protected from auto-purge.':$conn->error]);exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_retention_hold') {
    header('Content-Type: application/json');
    $id=(int)($_POST['id']??0);$ok=$id>0&&tracked_table_exists($conn,'log_retention_holds')&&$conn->query("UPDATE log_retention_holds SET is_active=0 WHERE id=$id");
    if($ok)tracked_audit($conn,'BACKUP','RETENTION_HOLD_RELEASED','retention_hold',(string)$id,'Incident retention hold released.');
    echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?'Incident hold released.':'Could not release hold.']);exit;
}

// ── AJAX: ID TEMPLATE MANAGER ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_id_templates') {
    header('Content-Type: application/json');
    $out = [];
    if (!isset($db_error)) {
        $exists = $conn->query("SHOW TABLES LIKE 'id_templates'");
        if ($exists && $exists->num_rows > 0) {
            $res = $conn->query("SELECT * FROM id_templates ORDER BY is_active DESC, updated_at DESC, id DESC");
            while ($r = $res->fetch_assoc()) {
                $r['config'] = json_decode($r['config_json'] ?? '{}', true) ?: [];
                $out[] = $r;
            }
        }
    }
    echo json_encode($out); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_id_template') {
    header('Content-Type: application/json');
    if (isset($db_error)) { echo json_encode(['status'=>'error','message'=>'Database unavailable.']); exit; }

    $exists = $conn->query("SHOW TABLES LIKE 'id_templates'");
    if (!$exists || $exists->num_rows === 0) {
        echo json_encode(['status'=>'error','message'=>'Run sql_id_generator_migration.sql first.']); exit;
    }

    $id   = (int)($_POST['template_id'] ?? 0);
    $name = trim($_POST['template_name'] ?? 'School ID Template');
    $nameSql = $conn->real_escape_string($name ?: 'School ID Template');
    $configRaw = $_POST['config_json'] ?? '{}';
    $decoded = json_decode($configRaw, true);
    if (!is_array($decoded)) $decoded = [];
    $configSql = $conn->real_escape_string(json_encode($decoded));

    $front = trim($_POST['existing_front'] ?? '');
    $back  = trim($_POST['existing_back'] ?? '');
    $uploadDir = __DIR__ . '/uploads/id_templates';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

    $saveUpload = function($key, $prefix) use ($uploadDir) {
        if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
        $tmp = $_FILES[$key]['tmp_name'];
        $mime = @mime_content_type($tmp) ?: '';
        $ext = in_array($mime, ['image/png'], true) ? 'png' : (in_array($mime, ['image/jpeg','image/jpg'], true) ? 'jpg' : '');
        if ($ext === '') return false;
        if ((int)$_FILES[$key]['size'] > 8*1024*1024) return false;
        $file = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $uploadDir . '/' . $file)) return false;
        return 'uploads/id_templates/' . $file;
    };

    $f = $saveUpload('front_template','front');
    if ($f === false) { echo json_encode(['status'=>'error','message'=>'Front template must be JPG/PNG and 8MB or less.']); exit; }
    if (is_string($f)) $front = $f;
    $b = $saveUpload('back_template','back');
    if ($b === false) { echo json_encode(['status'=>'error','message'=>'Back template must be JPG/PNG and 8MB or less.']); exit; }
    if (is_string($b)) $back = $b;

    if ($front === '') $front = '__builtin_portrait__';
    $frontSql = $conn->real_escape_string($front);
    $backSql  = $conn->real_escape_string($back);

    $conn->query("UPDATE id_templates SET is_active=0");
    if ($id > 0) {
        $ok = $conn->query("UPDATE id_templates SET template_name='$nameSql', front_image='$frontSql', back_image='$backSql', config_json='$configSql', is_active=1, updated_at=NOW() WHERE id=$id");
    } else {
        $ok = $conn->query("INSERT INTO id_templates (template_name,front_image,back_image,config_json,is_active,created_at,updated_at) VALUES ('$nameSql','$frontSql','$backSql','$configSql',1,NOW(),NOW())");
        $id = (int)$conn->insert_id;
    }
    if($ok)tracked_audit($conn,'ID','ID_TEMPLATE_SAVED','id_template',(string)$id,"ID template '$name' saved.");
    echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?'ID template saved.':$conn->error,'id'=>$id,'front_image'=>$front,'back_image'=>$back]); exit;
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrackED</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20260907-fresh-final">
    <link rel="icon" type="image/png" href="logo.png">
<link rel="shortcut icon" type="image/png" href="logo.png">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="ph-fill ph-fingerprint"></i></div>
        <div class="brand-text">
            <span class="brand-name">TrackED</span>
            <span class="brand-sub">SIBTECH Student Monitoring</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <p class="nav-label">MAIN MENU</p>
        <a href="#" class="nav-item active" data-section="dashboard"><i class="ph-fill ph-squares-four"></i><span>Dashboard</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="registration"><i class="ph-fill ph-user-plus"></i><span>Student Registration</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="records"><i class="ph-fill ph-users-three"></i><span>Student Records</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="idgenerator"><i class="ph-fill ph-identification-card"></i><span>ID Generator</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="reports"><i class="ph-fill ph-chart-bar"></i><span>Monitoring Reports</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="muster"><i class="ph-fill ph-siren"></i><span>Emergency Muster</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="clusters"><i class="ph-fill ph-trophy"></i><span>Intramurals Clusters</span><div class="nav-indicator"></div></a>
        <p class="nav-label" style="margin-top:2rem">OPERATIONS</p>
        <a href="#" class="nav-item" data-section="health"><i class="ph-fill ph-heartbeat"></i><span>System Health</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="audit"><i class="ph-fill ph-list-magnifying-glass"></i><span>Audit Trail</span><div class="nav-indicator"></div></a>
        <p class="nav-label" style="margin-top:2rem">CONFIGURATION</p>
        <a href="#" class="nav-item" data-section="schedules"><i class="ph-fill ph-shield-chevron"></i><span>Campus Policy</span><div class="nav-indicator"></div></a>
        <a href="#" class="nav-item" data-section="settings"><i class="ph-fill ph-gear-six"></i><span>Settings</span><div class="nav-indicator"></div></a>
    </nav>
    <div class="sidebar-footer">
        <div class="connection-status"><span class="status-dot" id="status-dot"></span><span id="status-text">Checking...</span></div>
        <p class="footer-credit">TrackED</p>
    </div>
</aside>

<div class="main-wrapper">
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle"><i class="ph ph-list"></i></button>
        <div class="page-breadcrumb"><span id="page-title">Dashboard</span></div>
    </div>
    <div class="topbar-right">
        <div class="topbar-datetime"><i class="ph ph-calendar-blank"></i><span id="topbar-date"><?= date('l, F j, Y') ?></span></div>
        <div class="topbar-clock"><i class="ph ph-clock"></i><span id="topbar-clock"></span></div>
        <div class="topbar-divider"></div>
        <button class="theme-toggle" id="theme-toggle" title="Toggle Dark/Light Mode">
            <i class="ph ph-moon" id="theme-icon"></i>
            <span id="theme-label">Dark</span>
        </button>
        <div class="topbar-divider"></div>
        <div class="admin-profile">
            <div class="admin-avatar"><img src="logo.png" alt="Admin Avatar"></div>
            <div class="admin-info"><span class="admin-name">SPMO</span><span class="admin-role">System Administrator</span></div>
        </div>
        <a class="topbar-logout" href="index.php?logout=1" title="Sign out"><i class="ph ph-sign-out"></i><span>Logout</span></a>
    </div>
</header>
<main class="content-area">

<!-- ══ DASHBOARD ══════════════════════════════════════════ -->
<section class="section active" id="section-dashboard">
    <div class="welcome-banner monitoring-welcome">
        <div class="welcome-text">
            <span class="monitoring-kicker"><i class="ph-fill ph-shield-check"></i> CAMPUS MOVEMENT CONTROL</span>
            <h1>Good <?= (date('H')<12)?'Morning':((date('H')<18)?'Afternoon':'Evening') ?>, Admin</h1>
            <p>Every verified gate movement is logged with an exact date and time. College uses open movement; High School uses controlled exit authorization.</p>
        </div>
        <div class="welcome-actions">
            <select id="dashboard-level-filter" class="level-filter-select" aria-label="Dashboard education level"><option value="">All Students</option><option value="College">College</option><option value="Senior High School">Senior High</option><option value="Junior High School">Junior High</option></select>
            <div class="welcome-date-badge"><i class="ph-fill ph-calendar-check"></i><span><?= date('M j, Y') ?></span></div>
        </div>
    </div>
    <div class="cards-grid monitoring-stat-grid">
        <div class="stat-card total"><div class="card-icon-wrap"><i class="ph-fill ph-student"></i></div><div class="card-body"><p class="card-label">Active Students</p><h2 class="card-value" id="stat-total"><?= $total_students ?></h2><p class="card-sub">Registered & active</p></div></div>
        <div class="stat-card present"><div class="card-icon-wrap"><i class="ph-fill ph-map-pin-area"></i></div><div class="card-body"><p class="card-label">Currently Inside</p><h2 class="card-value" id="stat-inside"><?= $inside_now ?></h2><p class="card-sub">Latest movement is IN</p></div></div>
        <div class="stat-card entry-card"><div class="card-icon-wrap"><i class="ph-fill ph-sign-in"></i></div><div class="card-body"><p class="card-label">Entries Today</p><h2 class="card-value" id="stat-entries"><?= $entries_today ?></h2><p class="card-sub">Verified IN movements</p></div></div>
        <div class="stat-card exit-card"><div class="card-icon-wrap"><i class="ph-fill ph-sign-out"></i></div><div class="card-body"><p class="card-label">Exits Today</p><h2 class="card-value" id="stat-exits"><?= $exits_today ?></h2><p class="card-sub">Verified OUT movements</p></div></div>
        <div class="stat-card late"><div class="card-icon-wrap"><i class="ph-fill ph-warning-circle"></i></div><div class="card-body"><p class="card-label">HS Early Exits</p><h2 class="card-value" id="stat-early-exits"><?= $early_exits_today ?></h2><p class="card-sub">Authorized before dismissal</p></div></div>
        <div class="stat-card rejected"><div class="card-icon-wrap"><i class="ph-fill ph-shield-warning"></i></div><div class="card-body"><p class="card-label">Denied Attempts</p><h2 class="card-value" id="stat-rejected"><?= $rejected_today ?></h2><p class="card-sub">Rejected verifications today</p></div></div>
    </div>
    <div class="panel campus-policy-panel" style="margin-bottom:1.5rem">
        <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-shield-chevron" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Active Campus Monitoring Policy</h3><span class="refresh-badge">Physical movement is always logged</span></div></div>
        <div class="policy-command-grid">
            <div class="policy-command-card college"><div class="policy-command-icon"><i class="ph-fill ph-buildings"></i></div><div><span>COLLEGE</span><strong>Open Movement Monitoring</strong><p>Unlimited verified IN / OUT. No class schedule, lunch stage, late cutoff, or outside-schedule block.</p></div><span class="policy-state live">LIVE</span></div>
            <div class="policy-command-card highschool"><div class="policy-command-icon"><i class="ph-fill ph-student"></i></div><div><span>HIGH SCHOOL</span><strong>Controlled Campus Exit</strong><p>Students stay inside campus. OUT before <b id="dashboard-hs-dismissal">--:--</b> requires an authorization reason.</p></div><span class="policy-state controlled">CONTROLLED</span></div>
            <div class="policy-command-card event"><div class="policy-command-icon"><i class="ph-fill ph-trophy"></i></div><div><span>EVENT LAYER</span><strong id="dashboard-event-title">Normal Campus Mode</strong><p id="dashboard-event-status">Intramurals schedules are time-bound but never suppress physical movement logs.</p></div><span class="policy-state event" id="dashboard-event-badge">STANDBY</span></div>
        </div>
    </div>
    <div class="panel denied-panel">
        <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-shield-warning" style="color:var(--danger);font-size:1.2rem"></i><h3 class="panel-title">Recent Denied Attempts</h3><span class="denied-info-badge">Rejected scans never change campus state</span></div><div class="panel-actions"><button class="btn-icon" id="refresh-rejected-btn" title="Refresh denied attempts"><i class="ph ph-arrows-clockwise"></i></button></div></div>
        <div class="table-wrapper"><table class="data-table denied-table"><thead><tr><th>#</th><th>Student</th><th>RFID UID</th><th>Movement</th><th>Reason</th><th>Date / Time</th><th>Attempts Today</th></tr></thead><tbody id="rejected-tbody"><tr><td colspan="7" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr></tbody></table></div>
    </div>
    <div class="panel movement-live-panel">
        <div class="panel-header"><div class="panel-title-group"><div class="live-pulse"><span class="pulse-ring"></span><span class="pulse-dot"></span></div><h3 class="panel-title">Live Campus Movement Feed</h3><span class="refresh-badge" id="last-refresh">Fetching...</span></div><div class="panel-actions"><select id="live-level-filter" class="level-filter-select" aria-label="Filter movements by education level"><option value="">All Levels</option><option value="College">College</option><option value="Senior High School">Senior High</option><option value="Junior High School">Junior High</option></select><button class="btn-icon" id="refresh-btn"><i class="ph ph-arrows-clockwise"></i></button><label class="toggle-label"><input type="checkbox" id="auto-refresh-toggle" checked><span class="toggle-switch"></span><span>Auto</span></label></div></div>
        <div class="table-wrapper"><table class="data-table"><thead><tr><th>#</th><th>Student</th><th>Academic Group</th><th>Section</th><th>Date / Time</th><th>Movement</th><th>Context</th></tr></thead><tbody id="live-tbody"><tr><td colspan="7" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr></tbody></table></div>
    </div>
</section>

<!-- ══ EMERGENCY MUSTER ═══════════════════════════════════ -->
<section class="section" id="section-muster">
    <div class="section-header operations-section-head">
        <div><span class="operations-kicker emergency"><i class="ph-fill ph-siren"></i> EMERGENCY READINESS</span><h2>Emergency Muster</h2><p>Create a frozen snapshot of everyone currently inside campus, then account for each student without changing their RFID movement history.</p></div>
        <div class="section-head-actions"><button class="btn btn-outline" id="muster-print-btn" type="button"><i class="ph ph-printer"></i> Print</button><button class="btn btn-outline" id="muster-export-btn" type="button"><i class="ph ph-file-csv"></i> Export CSV</button></div>
    </div>
    <div class="muster-start-panel panel" id="muster-start-panel">
        <div class="muster-start-copy"><div class="muster-start-icon"><i class="ph-fill ph-shield-warning"></i></div><div><h3>No active emergency muster</h3><p>When an emergency is declared, start a muster to capture all students whose latest movement today is <strong>IN</strong>.</p></div></div>
        <div class="muster-start-controls"><input class="form-input" id="muster-title" placeholder="Optional incident title"><input class="form-input" id="muster-notes" placeholder="Optional note / location"><button class="btn btn-danger" id="start-muster-btn" type="button"><i class="ph-fill ph-siren"></i> Start Emergency Muster</button></div>
    </div>
    <div class="muster-active-shell" id="muster-active-shell" style="display:none">
        <div class="muster-command panel">
            <div><span class="muster-live-pill"><span></span> MUSTER ACTIVE</span><h3 id="muster-session-title">Emergency Muster</h3><p id="muster-session-meta">Loading session...</p></div>
            <button class="btn btn-danger-outline" id="close-muster-btn" type="button"><i class="ph ph-stop-circle"></i> Close Muster</button>
        </div>
        <div class="muster-stat-grid">
            <div class="ops-stat"><span>Total Snapshot</span><strong id="muster-total">0</strong><small>Inside when muster started</small></div>
            <div class="ops-stat good"><span>Safe</span><strong id="muster-safe">0</strong><small>Accounted for</small></div>
            <div class="ops-stat warn"><span>Unconfirmed</span><strong id="muster-unconfirmed">0</strong><small>Needs verification</small></div>
            <div class="ops-stat danger"><span>Not Found</span><strong id="muster-notfound">0</strong><small>Escalate immediately</small></div>
            <div class="ops-stat muted"><span>Excused</span><strong id="muster-excused">0</strong><small>Confirmed exception</small></div>
        </div>
        <div class="panel muster-roster-panel">
            <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-users-three"></i><h3 class="panel-title">Muster Roster</h3><span class="refresh-badge" id="muster-count">0 students</span></div><div class="panel-actions muster-filters"><input class="form-input compact" id="muster-search" placeholder="Search student"><select class="form-input compact" id="muster-level-filter"><option value="">All Levels</option><option>College</option><option>Senior High School</option><option>Junior High School</option></select><select class="form-input compact" id="muster-status-filter"><option value="">All Status</option><option value="UNCONFIRMED">Unconfirmed</option><option value="SAFE">Safe</option><option value="NOT_FOUND">Not Found</option><option value="EXCUSED">Excused</option></select></div></div>
            <div class="table-wrapper muster-table-wrap"><table class="data-table muster-table"><thead><tr><th>#</th><th>Student</th><th>Level</th><th>Academic Group</th><th>Year / Grade</th><th>Section</th><th>Muster Status</th><th>Quick Action</th><th>Note</th></tr></thead><tbody id="muster-tbody"><tr><td colspan="9" class="table-loading">No active muster.</td></tr></tbody></table></div>
        </div>
    </div>
    <div class="panel muster-history-panel"><div class="panel-header"><div class="panel-title-group"><i class="ph ph-clock-counter-clockwise"></i><h3 class="panel-title">Recent Muster Sessions</h3></div></div><div id="muster-session-history" class="muster-session-history"><p class="student-meta">No previous sessions.</p></div></div>
</section>

<!-- ══ SYSTEM HEALTH ═══════════════════════════════════════ -->
<section class="section" id="section-health">
    <div class="section-header operations-section-head"><div><span class="operations-kicker"><i class="ph-fill ph-heartbeat"></i> OPERATIONS DIAGNOSTICS</span><h2>System Health Dashboard</h2><p>Live operational view of the Admin database, Orange Pi synchronization, offline queues, SMS queue, backups, RFID activity and server storage.</p></div><button class="btn btn-outline" id="health-refresh-btn" type="button"><i class="ph ph-arrows-clockwise"></i> Refresh Health</button></div>
    <div class="health-overview-grid">
        <article class="health-card" id="health-db-card"><div class="health-icon"><i class="ph-fill ph-database"></i></div><div><span>Admin Database</span><strong id="health-db-status">Checking...</strong><small id="health-db-detail">—</small></div><span class="health-dot"></span></article>
        <article class="health-card" id="health-opi-card"><div class="health-icon"><i class="ph-fill ph-cpu"></i></div><div><span>Orange Pi Sync</span><strong id="health-opi-status">Checking...</strong><small id="health-opi-detail">—</small></div><span class="health-dot"></span></article>
        <article class="health-card" id="health-queue-card"><div class="health-icon"><i class="ph-fill ph-arrows-clockwise"></i></div><div><span>Offline Sync Queue</span><strong id="health-queue-status">—</strong><small id="health-queue-detail">—</small></div><span class="health-dot"></span></article>
        <article class="health-card" id="health-sms-card"><div class="health-icon"><i class="ph-fill ph-chat-circle-text"></i></div><div><span>SMS Queue</span><strong id="health-sms-status">—</strong><small id="health-sms-detail">—</small></div><span class="health-dot"></span></article>
        <article class="health-card" id="health-backup-card"><div class="health-icon"><i class="ph-fill ph-cloud-check"></i></div><div><span>Verified Backup</span><strong id="health-backup-status">—</strong><small id="health-backup-detail">—</small></div><span class="health-dot"></span></article>
        <article class="health-card" id="health-rfid-card"><div class="health-icon"><i class="ph-fill ph-contactless-payment"></i></div><div><span>RFID Activity</span><strong id="health-rfid-status">—</strong><small id="health-rfid-detail">—</small></div><span class="health-dot"></span></article>
    </div>
    <div class="health-detail-grid">
        <div class="panel health-diagnostics-panel"><div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-stethoscope"></i><h3 class="panel-title">Diagnostics</h3><span class="refresh-badge" id="health-last-refresh">Waiting...</span></div></div><div class="health-detail-list"><div><span>Database query latency</span><strong id="health-db-latency">—</strong></div><div><span>Orange Pi last seen</span><strong id="health-opi-seen">—</strong></div><div><span>Last successful sync</span><strong id="health-opi-success">—</strong></div><div><span>Last sync error</span><strong id="health-opi-error">None</strong></div><div><span>Orange Pi worker version</span><strong id="health-opi-version">—</strong></div><div><span>Server free storage</span><strong id="health-storage">—</strong></div></div></div>
        <div class="panel health-guidance-panel"><div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-shield-check"></i><h3 class="panel-title">Operational Interpretation</h3></div></div><div class="health-guidance" id="health-guidance"><div class="health-guidance-item"><i class="ph ph-info"></i><p>Health information will appear after the operations migration and the updated Orange Pi sync worker are deployed.</p></div></div></div>
    </div>
</section>

<!-- ══ AUDIT TRAIL ════════════════════════════════════════ -->
<section class="section" id="section-audit">
    <div class="section-header operations-section-head"><div><span class="operations-kicker"><i class="ph-fill ph-list-magnifying-glass"></i> ACCOUNTABILITY</span><h2>Admin & Security Audit Trail</h2><p>Read-only history of sensitive SPMO actions, security access changes, policy changes, backups, student administration and emergency operations.</p></div><a class="btn btn-outline" id="audit-export-btn" href="index.php?action=export_audit_csv"><i class="ph ph-file-csv"></i> Export CSV</a></div>
    <div class="panel audit-panel"><div class="audit-filter-bar"><div class="search-box audit-search"><i class="ph ph-magnifying-glass"></i><input type="text" id="audit-search" class="search-input audit-search-input" placeholder="Search action, student, entity or administrator..." autocomplete="off"></div><select class="form-input compact" id="audit-category"><option value="">All Categories</option><option value="SECURITY">Security</option><option value="STUDENT">Student</option><option value="POLICY">Policy</option><option value="SETTINGS">Settings</option><option value="BACKUP">Backup</option><option value="INTRAMURALS">Intramurals</option><option value="EMERGENCY">Emergency</option><option value="ID">ID Generator</option></select><input type="date" class="form-input compact" id="audit-date-from"><input type="date" class="form-input compact" id="audit-date-to"><button class="btn btn-primary" id="audit-refresh-btn" type="button"><i class="ph ph-funnel"></i> Apply</button></div><div class="panel-header audit-result-head"><div class="panel-title-group"><i class="ph-fill ph-scroll"></i><h3 class="panel-title">Recorded Actions</h3><span class="refresh-badge" id="audit-count">0 entries</span></div></div><div class="table-wrapper audit-table-wrap"><table class="data-table audit-table"><thead><tr><th>Date / Time</th><th>Category</th><th>Action</th><th>Description</th><th>Entity</th><th>Performed By</th><th>Source / IP</th></tr></thead><tbody id="audit-tbody"><tr><td colspan="7" class="table-loading">Loading audit trail...</td></tr></tbody></table></div></div>
</section>

<!-- ══ REGISTRATION ════════════════════════════════════════ -->
<section class="section" id="section-registration">
    <div class="section-header">
        <h2>Student Registration</h2>
        <p>Register College, Senior High School, or Junior High School students. Academic fields adapt automatically to the selected level.</p>
    </div>
    <div class="reg-layout">
        <div class="panel reg-form-panel">
            <div class="panel-header">
                <div class="panel-title-group"><i class="ph-fill ph-user-plus" style="color:var(--primary);font-size:1.2rem;"></i><h3 class="panel-title">New Student Form</h3></div>
            </div>
            <form id="reg-form" class="reg-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">

                <!-- RFID -->
                <div class="form-group rfid-group" style="margin-bottom:1rem;">
                    <label><i class="ph ph-wifi-high"></i> RFID Card UID <span class="field-required">*</span></label>
                    <div class="rfid-input-wrap">
                        <input type="text" id="rfid_uid" name="rfid_uid" class="form-input rfid-input"
                               placeholder="Tap RFID card near scanner..." autofocus autocomplete="off" required>
                        <div class="rfid-scan-indicator" id="rfid-indicator"><i class="ph ph-wifi-high"></i></div>
                    </div>
                    <p class="field-hint">The UID will auto-fill once the card is scanned by the Orange Pi.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-identification-card"></i> Student ID No. <span class="field-required">*</span></label>
                        <input type="text" name="student_id_no" class="form-input" placeholder="e.g. 2024-00001" required>
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-user"></i> Full Name <span class="field-required">*</span></label>
                        <input type="text" name="full_name" class="form-input" placeholder="Last Name, First Name M.I." required>
                    </div>
                </div>

                <div class="academic-classification-card">
                    <div class="academic-card-head">
                        <div><span class="academic-eyebrow">ACADEMIC CLASSIFICATION</span><h4>Select education level</h4></div>
                        <i class="ph-fill ph-buildings academic-card-icon"></i>
                    </div>
                    <div class="academic-level-picker" id="reg-level-picker">
                        <label class="academic-level-option"><input type="radio" name="education_level" value="College" checked><span><i class="ph-fill ph-graduation-cap"></i><b>College</b><small>Course · Year · Section</small></span></label>
                        <label class="academic-level-option"><input type="radio" name="education_level" value="Senior High School"><span><i class="ph-fill ph-books"></i><b>Senior High</b><small>Track · Strand · Grade</small></span></label>
                        <label class="academic-level-option"><input type="radio" name="education_level" value="Junior High School"><span><i class="ph-fill ph-student"></i><b>Junior High</b><small>Grade · Section</small></span></label>
                    </div>

                    <div class="academic-fields-grid">
                        <div class="form-group academic-college-field" id="reg-course-group">
                            <label><i class="ph ph-book-open"></i> Course / Program <span class="field-required">*</span></label>
                            <select name="course" id="reg-course" class="form-input form-select">
                                <option value="">Select Course</option><?php foreach($COURSES as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group academic-shs-field" id="reg-track-group" style="display:none">
                            <label><i class="ph ph-git-branch"></i> Track <span class="field-required">*</span></label>
                            <select name="track" id="reg-track" class="form-input form-select"><option value="">Select Track</option><?php foreach($SHS_TRACKS as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group academic-shs-field" id="reg-strand-group" style="display:none">
                            <label><i class="ph ph-path"></i> Strand <span class="field-required">*</span></label>
                            <select name="strand" id="reg-strand" class="form-input form-select"><option value="">Select Strand</option><?php foreach($SHS_STRANDS as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group">
                            <label id="reg-year-label"><i class="ph ph-graduation-cap"></i> Year Level <span class="field-required">*</span></label>
                            <select name="year_level" id="reg-year" class="form-input form-select"></select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-chalkboard-teacher"></i> Section</label>
                        <input type="text" name="section" class="form-input" placeholder="e.g. A, B, C">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-phone"></i> Parent's Mobile <span class="field-required">*</span></label>
                        <input type="tel" name="parent_mobile" id="parent_mobile" class="form-input" placeholder="+639XXXXXXXXX" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-user-heart"></i> Parent / Guardian Name</label>
                        <input type="text" name="guardian_name" id="guardian_name" class="form-input" placeholder="e.g. Maria Dela Cruz">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-map-pin"></i> Emergency Address</label>
                        <input type="text" name="guardian_address" id="guardian_address" class="form-input" placeholder="Barangay, Municipality, Province">
                    </div>
                </div>

                <!-- Academic enrollment status only. Normal campus movement monitoring is not schedule-based. -->
                <div class="irregular-toggle-group">
                    <label class="irreg-toggle-label">
                        <input type="checkbox" id="is_irregular_chk" name="is_irregular">
                        <span class="irreg-toggle-switch"></span>
                        <span class="irreg-toggle-text">
                            <strong>Irregular Student</strong>
                            <small>Academic classification only. This does not change RFID IN/OUT monitoring or create a custom gate schedule.</small>
                        </span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" id="clear-form-btn"><i class="ph ph-eraser"></i> Clear</button>
                    <button type="submit" class="btn btn-primary" id="reg-submit-btn"><i class="ph ph-user-plus"></i> Register Student</button>
                </div>
            </form>
        </div>

        <!-- Photo + History Column -->
        <div class="photo-column">
            <div class="panel photo-panel">
                <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-camera" style="color:var(--primary);font-size:1.2rem;"></i><h3 class="panel-title">Student Photo</h3></div></div>
                <div class="photo-upload-area" id="photo-drop-zone">
                    <div class="photo-preview-circle" id="photo-preview-circle">
                        <img id="photo-preview-img" src="" alt="" style="display:none;">
                        <div class="photo-placeholder" id="photo-placeholder"><i class="ph ph-user-circle"></i></div>
                        <div class="photo-overlay"><i class="ph ph-camera"></i></div>
                    </div>
                    <p class="upload-hint-main">Upload Photo</p>
                    <p class="upload-hint-sub">Drag &amp; drop or click below</p>
                    <p class="upload-hint-sub">JPG, PNG · Max 2MB</p>
                    <label class="btn btn-ghost btn-sm upload-label" for="student_photo"><i class="ph ph-upload-simple"></i> Choose Photo</label>
                    <input type="file" id="student_photo" name="student_photo" accept="image/*" hidden form="reg-form">
                </div>
            </div>
            <div class="panel signature-upload-panel">
                <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-signature" style="color:var(--primary);font-size:1.1rem;"></i><h3 class="panel-title">Student Signature</h3></div></div>
                <div class="signature-upload-area">
                    <div class="signature-preview-box"><img id="signature-preview-img" alt="Student signature preview"><span id="signature-preview-placeholder">No signature uploaded</span></div>
                    <label class="btn btn-ghost btn-sm upload-label" for="student_signature"><i class="ph ph-upload-simple"></i> Upload Signature</label>
                    <input type="file" id="student_signature" name="student_signature" accept="image/png,image/jpeg,image/webp" hidden form="reg-form">
                    <p class="upload-hint-sub">Transparent PNG recommended · black/dark ink on clean background</p>
                </div>
            </div>
            <div class="panel">
                <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-clock-clockwise" style="color:var(--primary);font-size:1.1rem;"></i><h3 class="panel-title">Today's Registrations</h3></div></div>
                <div style="padding:.75rem 1rem 1rem;" id="reg-history-list"><p class="empty-history">No registrations yet today.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ══ STUDENT RECORDS ═════════════════════════════════════ -->
<section class="section" id="section-records">
    <div class="section-header"><h2>Student Records</h2><p>Manage College, Senior High School, and Junior High School students using level-aware academic filters.</p></div>
    <div class="panel records-panel">
        <div class="panel-header records-header">
            <div class="panel-title-group">
                <i class="ph-fill ph-users-three" style="color:var(--primary);font-size:1.2rem;"></i>
                <h3 class="panel-title">All Students</h3>
                <span class="count-badge" id="records-count">Loading...</span>
            </div>
            <div class="panel-actions records-filter-bar">
                <div class="search-box"><i class="ph ph-magnifying-glass"></i><input type="text" id="records-search" placeholder="Search name / ID / RFID..." class="search-input"></div>
                <select id="records-level" class="filter-select themed-select">
                    <option value="">All Levels</option>
                    <option value="College">College</option>
                    <option value="Senior High School">Senior High</option>
                    <option value="Junior High School">Junior High</option>
                </select>
                <select id="filter-group" class="filter-select themed-select"><option value="">All Academic Groups</option></select>
                <select id="filter-year" class="filter-select themed-select"><option value="">All Years / Grades</option></select>
                <select id="filter-irregular" class="filter-select themed-select">
                    <option value="-1">All Types</option><option value="0">Regular</option><option value="1">Irregular</option>
                </select>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Photo</th><th>Student</th><th>Education Level</th><th>Academic Profile</th><th>Campus State</th><th>Scan Access</th><th>Movements Today</th><th>Last Movement</th><th>Actions</th></tr></thead>
                <tbody id="records-tbody"><tr><td colspan="9" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<!-- ══ MONITORING REPORTS ═════════════════════════════════ -->
<section class="section" id="section-reports">
    <div class="section-header report-page-header">
        <div><h2>Monitoring Reports</h2><p>A cleaner investigation center for physical campus movement, end-of-day exceptions, and Intramurals attendance.</p></div>
        <div class="report-retention-note"><i class="ph-fill ph-shield-check"></i><span><strong>30-day live window</strong><small>Older operational logs are archived before purge.</small></span></div>
    </div>

    <div class="report-mode-switch panel report-mode-switch-3" role="tablist" aria-label="Report type">
        <button type="button" class="report-mode-btn active" data-mode="movement" role="tab"><i class="ph-fill ph-arrows-left-right"></i><span><strong>Movement Logs</strong><small>Verified physical IN / OUT</small></span></button>
        <button type="button" class="report-mode-btn" data-mode="exception" role="tab"><i class="ph-fill ph-warning-octagon"></i><span><strong>Daily Exceptions</strong><small>NO OUT and monitoring anomalies</small></span></button>
        <button type="button" class="report-mode-btn" data-mode="event" role="tab"><i class="ph-fill ph-trophy"></i><span><strong>Event Attendance</strong><small>Time-bound Intramurals only</small></span></button>
    </div>

    <div class="panel report-filter-card">
        <div class="report-filter-heading">
            <div><i class="ph-fill ph-funnel-simple"></i><strong id="report-scope-title">Movement Investigation</strong><span id="report-scope-copy">Search and filter exact verified campus movements.</span></div>
        </div>
        <div class="report-filter-grid">
            <div class="filter-group report-search-group"><label><i class="ph ph-magnifying-glass"></i> Student Search</label><input id="report-search" class="form-input" placeholder="Name, Student ID or RFID"></div>
            <div class="filter-group"><label><i class="ph ph-calendar-blank"></i> Date From</label><input type="date" id="report-from" class="form-input" value="<?= date('Y-m-d',strtotime('-7 days')) ?>"></div>
            <div class="filter-group"><label><i class="ph ph-calendar-check"></i> Date To</label><input type="date" id="report-to" class="form-input" value="<?= date('Y-m-d') ?>"></div>
            <div class="filter-group"><label><i class="ph ph-buildings"></i> Education Level</label><select id="report-level" class="form-input form-select"><option value="">All Levels</option><option value="College">College</option><option value="Senior High School">Senior High School</option><option value="Junior High School">Junior High School</option></select></div>

            <div class="filter-group" id="report-group-wrap"><label id="report-group-label"><i class="ph ph-book-open"></i> Course / Program</label><select id="report-group" class="form-input form-select"></select></div>
            <div class="filter-group"><label id="report-year-label"><i class="ph ph-graduation-cap"></i> Year / Grade</label><select id="report-year" class="form-input form-select"></select></div>
            <div class="filter-group"><label><i class="ph ph-users-three"></i> Section</label><input id="report-section" class="form-input" placeholder="All sections"></div>

            <div class="filter-group report-movement-only"><label><i class="ph ph-sign-in"></i> Movement</label><select id="report-movement" class="form-input form-select"><option value="">All IN / OUT</option><option value="IN">IN — Entered Campus</option><option value="OUT">OUT — Exited Campus</option></select></div>
            <div class="filter-group report-movement-only"><label><i class="ph ph-shield-check"></i> Context</label><select id="report-context" class="form-input form-select"><option value="">All Contexts</option><option value="NORMAL">Normal</option><option value="EARLY_EXIT">HS Early Exit</option><option value="AUTHORIZED_REENTRY">Authorized Re-entry</option></select></div>

            <div class="filter-group report-exception-only" style="display:none"><label><i class="ph ph-warning-circle"></i> Exception Status</label><select id="report-exception-status" class="form-input form-select"><option value="">All Exceptions</option><option value="open">Open / Unresolved</option><option value="resolved">Resolved</option></select></div>

            <div class="filter-group report-cluster-filter"><label><i class="ph ph-trophy"></i> Intramurals Event</label><select id="report-cluster-event" class="form-input form-select themed-select"><option value="">All / None</option></select></div>
            <div class="filter-group report-cluster-filter"><label><i class="ph ph-squares-four"></i> Cluster</label><select id="report-cluster" class="form-input form-select themed-select"><option value="">All Clusters</option></select></div>
            <div class="filter-group report-event-only" style="display:none"><label><i class="ph ph-funnel"></i> Event Status</label><select id="report-status" class="form-input form-select"><option value="">All Statuses</option><option>On Time</option><option>Late</option><option>Absent</option></select></div>
            <input type="hidden" id="report-irreg" value="">
        </div>
        <div class="report-filter-actions">
            <button class="btn btn-ghost" id="reset-report-btn" type="button"><i class="ph ph-arrow-counter-clockwise"></i> Reset Filters</button>
            <div class="report-filter-actions-right"><button class="btn btn-primary" id="generate-report-btn" type="button"><i class="ph ph-magnifying-glass"></i> Generate Report</button><button class="btn btn-ghost" id="export-csv-btn" type="button"><i class="ph ph-file-xls"></i> Export Excel</button></div>
        </div>
    </div>

    <div class="report-summary-row report-summary-grid" id="report-summary-row" style="display:none;">
        <div class="summary-chip total-chip"><i class="ph-fill ph-list-numbers"></i><span><small>Total Records</small><strong id="rpt-total">0</strong></span></div>
        <div class="summary-chip ontime-chip"><i class="ph-fill ph-sign-in"></i><span><small id="rpt-label-a">IN</small><strong id="rpt-ontime">0</strong></span></div>
        <div class="summary-chip late-chip"><i class="ph-fill ph-sign-out"></i><span><small id="rpt-label-b">OUT</small><strong id="rpt-late">0</strong></span></div>
        <div class="summary-chip monitoring-alert-chip"><i class="ph-fill ph-warning-circle"></i><span><small id="rpt-label-c">Early Exit</small><strong id="rpt-absent">0</strong></span></div>
        <div class="summary-chip irreg-chip"><i class="ph-fill ph-shield-check"></i><span><small id="rpt-label-d">Re-entry</small><strong id="rpt-irreg-count">0</strong></span></div>
    </div>

    <div class="panel report-results-card">
        <div class="panel-header report-results-header"><div class="panel-title-group"><i class="ph-fill ph-table" style="color:var(--primary);font-size:1.2rem"></i><div><h3 class="panel-title" id="report-results-title">Movement Log Results</h3><span class="panel-subtitle" id="report-results-subtitle">Actual verified campus movement only.</span></div><span class="count-badge" id="report-count">0 records</span></div></div>
        <div class="table-wrapper report-table-wrapper"><table class="data-table report-data-table"><thead id="report-thead"><tr><th>#</th><th>Student</th><th>Academic Group</th><th>Date / Time</th><th>Movement</th><th>Context</th><th>Authorization / Verification</th><th>Event</th></tr></thead><tbody id="report-tbody"><tr><td colspan="8" class="table-empty"><i class="ph ph-chart-bar" style="font-size:2rem;opacity:.3"></i><p>Select filters and click Generate Report.</p></td></tr></tbody></table></div>
    </div>
</section>

<!-- ══ CAMPUS POLICY ═════════════════════════════════════ -->
<section class="section" id="section-schedules">
    <div class="section-header"><h2>Campus Movement Policy</h2><p>Normal monitoring is intentionally separate from class schedules. College is unrestricted; High School uses a controlled-exit security policy. Intramurals remains the only time-bound attendance layer.</p></div>
    <div class="monitoring-policy-grid">
        <div class="panel policy-editor-card policy-college-card"><div class="policy-card-top"><div class="policy-large-icon"><i class="ph-fill ph-buildings"></i></div><div><span class="policy-eyebrow">COLLEGE</span><h3>Open Movement Monitoring</h3><p>Designed for students who may enter and leave campus multiple times in one day.</p></div><span class="policy-state live">ACTIVE</span></div><div class="policy-rule-list"><div><i class="ph-fill ph-check-circle"></i><span><strong>Unlimited verified IN / OUT</strong><small>Every accepted tap creates a new permanent movement row.</small></span></div><div><i class="ph-fill ph-clock"></i><span><strong>No regular time restriction</strong><small>No late cutoff, class-day restriction, lunch stage, or Outside Schedule state.</small></span></div><div><i class="ph-fill ph-shield-check"></i><span><strong>Guard verification remains mandatory</strong><small>Rejected scans never change the student's inside/outside state.</small></span></div><div><i class="ph-fill ph-file-xls"></i><span><strong>Investigation-ready history</strong><small>All movements can be filtered and exported with exact date/time.</small></span></div></div></div>
        <div class="panel policy-editor-card"><div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-student" style="color:var(--primary);font-size:1.25rem"></i><div><h3 class="panel-title">High School Controlled Exit</h3><span class="panel-subtitle">Applies to Senior High + Junior High</span></div></div><label class="policy-master-toggle" title="Enable or disable controlled High School exit authorization"><input type="checkbox" id="policy-hs-controlled-exit" checked><span class="irreg-toggle-switch"></span><span class="policy-master-copy"><strong id="policy-hs-controlled-label">CONTROLLED EXIT ON</strong><small>Security policy</small></span></label></div><form id="movement-policy-form" class="movement-policy-form"><div class="policy-setting-primary"><label><span><i class="ph ph-clock-countdown"></i><strong>Normal dismissal starts at</strong><small>Before this time, an OUT tap becomes an Early Exit authorization request.</small></span><input type="time" id="policy-hs-dismissal" class="form-input" value="15:30"></label></div><div class="policy-toggle-stack"><label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="policy-early-reason" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Require early-exit authorization reason</strong><small>Guard must select illness, guardian pickup, school activity, emergency, appointment, or other.</small></span></label><label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="policy-reentry" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Allow verified re-entry</strong><small>A student who left early may return; the new IN is marked Authorized Re-entry.</small></span></label><label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="policy-sms-movement" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Parent SMS on verified movement</strong><small>Send parent/guardian notification for accepted campus IN and OUT movements.</small></span></label><label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="policy-sms-early" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Highlight High School early exits in SMS</strong><small>Include the selected authorization reason in the parent notification.</small></span></label></div><div class="policy-technical-row"><label><span><i class="ph ph-timer"></i> Same-card safety cooldown</span><div class="policy-number-control"><input type="number" id="policy-cooldown" class="form-input" min="2" max="15" value="4"><small>seconds</small></div></label><p>Prevents accidental IN→OUT from a duplicate RFID read. Different students can still scan as soon as the kiosk resets.</p></div><div class="form-actions"><button type="submit" class="btn btn-primary" id="save-movement-policy"><i class="ph ph-floppy-disk"></i> Save Campus Policy</button></div></form></div>
    </div>
    <div class="panel event-separation-note"><div class="event-separation-icon"><i class="ph-fill ph-trophy"></i></div><div><span>TIME-BOUND ATTENDANCE IS NOW EVENT-ONLY</span><h3>Intramurals schedules stay in Intramurals Clusters</h3><p>Event dates and attendance windows may qualify a movement for event attendance, but they never suppress the permanent physical IN/OUT log.</p></div><button class="btn btn-secondary" type="button" onclick="document.querySelector('[data-section=clusters]')?.click()"><i class="ph ph-arrow-right"></i> Open Intramurals</button></div>
</section>

<!-- ══ ID GENERATOR ═══════════════════════════════════════ -->
<section class="section" id="section-idgenerator">
    <div class="section-header">
        <h2>AI-Assisted ID Generator</h2>
        <p>Generate portrait CR80 IDs for College, Senior High School, and Junior High School. The built-in template automatically adapts its department label and academic information to each student.</p>
    </div>

    <div class="idgen-notice"><i class="ph-fill ph-sparkle"></i><div><strong>Smart Academic ID Layout</strong><span>Student data is copied exactly from the database. The template automatically switches between College Department, Senior High School, and Junior High School while preserving the fixed 1×1-inch photo.</span></div></div>

    <div class="idgen-grid">
        <div class="panel">
            <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-image" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">1. Template Setup</h3></div></div>
            <div class="idgen-form">
                <input type="hidden" id="idtpl-id" value="0"><input type="hidden" id="idtpl-existing-front" value=""><input type="hidden" id="idtpl-existing-back" value="">
                <div class="form-group"><label>Template Name</label><input class="form-input" id="idtpl-name" value="Official Student ID"></div>
                <div class="form-group"><label>Custom Front Template <span style="color:var(--text-muted);font-weight:400">(optional)</span></label><input class="form-input" type="file" id="idtpl-front" accept="image/png,image/jpeg"><p class="field-hint">Default: adaptive portrait school ID. Custom uploads should use CR80 portrait ratio (53.98 × 85.60 mm).</p></div>
                <div class="idgen-fixed-back-note"><i class="ph-fill ph-lock-key"></i><div><strong>Fixed Emergency + Yearly Validation Back</strong><span>The back design is locked for consistent PVC printing. Parent/guardian details change per student, while the validation grid automatically starts from the current school year.</span></div></div>
                <div class="idgen-field-list"><p class="field-hint" style="margin-bottom:.55rem">Drag these fields directly on the preview.</p><div class="idgen-field-chip"><i class="ph ph-user-square"></i> Photo</div><div class="idgen-field-chip"><i class="ph ph-text-aa"></i> Full Name</div><div class="idgen-field-chip"><i class="ph ph-hash"></i> Student No.</div><div class="idgen-field-chip"><i class="ph ph-graduation-cap"></i> Academic Profile</div><div class="idgen-field-chip"><i class="ph ph-users"></i> Section</div><div class="idgen-field-chip"><i class="ph ph-signature"></i> Student Signature</div></div>
                <button type="button" class="btn btn-primary" id="idtpl-save"><i class="ph ph-floppy-disk"></i> Save Template & Layout</button>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-magic-wand" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">2. Front & Back Preview</h3></div><button class="btn btn-secondary btn-sm" id="idtpl-reset-layout"><i class="ph ph-arrow-counter-clockwise"></i> Reset Front Layout</button></div><div class="idgen-side-tabs"><button type="button" class="idgen-side-tab active" data-id-side="front"><i class="ph ph-identification-card"></i> Front</button><button type="button" class="idgen-side-tab" data-id-side="back"><i class="ph ph-seal-check"></i> Validation Back</button></div>
            <div class="id-card-stage-wrap"><div class="id-card-stage" id="id-card-stage">
                <div class="id-default-template" id="id-default-template" aria-hidden="true"><div class="idtpl-topline"></div><div class="idtpl-header"><img src="logo.png" alt="" class="idtpl-logo"><div class="idtpl-school"><strong>STUDENT IDENTIFICATION CARD</strong><span id="idtpl-level-subtitle">OFFICIAL STUDENT ID</span></div></div><div class="idtpl-year" id="idtpl-school-year">S.Y. 2026–2027</div><div class="idtpl-side" id="idtpl-side-label">COLLEGE DEPARTMENT</div><div class="idtpl-photo-frame"></div><div class="idtpl-bottom-shape"></div></div>
                <img id="id-card-bg" alt="ID template preview"><div class="id-field id-field-photo" data-field="photo"><img id="id-preview-photo" alt="Photo"><span>PHOTO</span></div><div class="id-field id-field-text" data-field="full_name" id="id-preview-name">STUDENT NAME</div><div class="id-field id-field-text" data-field="student_id_no" id="id-preview-no">2026-00001</div><div class="id-field id-field-text" data-field="course_year" id="id-preview-course">ACADEMIC PROFILE</div><div class="id-field id-field-text" data-field="section" id="id-preview-section">Section A</div><div class="id-field id-field-signature" data-field="signature"><img id="id-preview-signature" alt="Student Signature"><span>SIGNATURE</span><small>Student Signature</small></div>
            </div></div>
            <div class="id-card-stage-wrap id-back-stage-wrap" id="id-back-stage-wrap" style="display:none"><div class="id-card-stage id-card-back-stage">
                <div class="id-back-built id-back-validation" id="id-back-built">
                    <img src="logo.png" alt="" class="id-back-watermark id-back-validation-watermark">
                    <div class="id-back-school-head"><strong>SOUTHWESTERN INSTITUTE OF BUSINESS AND TECHNOLOGY, INC.</strong><small>PINAMALAYAN, ORIENTAL MINDORO</small></div>
                    <div class="id-back-emergency-box">
                        <div class="id-back-info-row"><span>IN CASE OF EMERGENCY</span><b><span id="id-back-guardian">PARENT / GUARDIAN</span> / <span id="id-back-mobile">09XXXXXXXXX</span></b></div>
                        <div class="id-back-info-row"><span>ADDRESS</span><b id="id-back-address">EMERGENCY ADDRESS</b></div>
                    </div>
                    <div class="id-back-rules">
                        <span>• THIS CARD IS NON-TRANSFERABLE AND VALID ONLY FOR SEMESTER LAST VALIDATED.</span>
                        <span>• IT MUST BE WORN AT ALL TIMES WHILE INSIDE SCHOOL PREMISES.</span>
                        <span>• ANY VISIBLE TAMPERING WILL INVALIDATE THIS CARD.</span>
                        <span>• IF LOST, FINDER IS REQUESTED TO SURRENDER THIS TO THE REGISTRAR OFFICE.</span>
                    </div>
                    <div class="id-back-validation-table" aria-label="Yearly validation table">
                        <div class="id-back-vh">School<br>Year</div><div class="id-back-vh">1st<br>Semester</div><div class="id-back-vh">2nd<br>Semester</div><div class="id-back-vh">Validation</div>
                        <div class="id-back-vy" id="id-back-year-1">2026-2027</div><div></div><div></div><div></div>
                        <div class="id-back-vy" id="id-back-year-2">2027-2028</div><div></div><div></div><div></div>
                        <div class="id-back-vy" id="id-back-year-3">2028-2029</div><div></div><div></div><div></div>
                        <div class="id-back-vy" id="id-back-year-4">2029-2030</div><div></div><div></div><div></div>
                    </div>
                    <div class="id-back-registrar id-back-validation-registrar"><i></i><b>DANICA CLAIRE P. LAT, LPT, CRS, CEPL</b><small>ASSISTANT COLLEGE REGISTRAR</small></div>
                </div>
            </div></div>
            <div class="idgen-preview-student"><label>Preview Student</label><select class="form-input form-select themed-select" id="idgen-preview-select"><option value="">Loading students...</option></select></div>
        </div>
    </div>

    <div class="panel" style="margin-top:1rem">
        <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-printer" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">3. Batch Generate & Print</h3></div><span class="count-badge" id="idgen-selected-count">0 selected</span></div>
        <div class="idgen-batch-bar academic-idgen-bar">
            <div class="form-group"><label>Education Level</label><select class="form-input form-select themed-select" id="idgen-level"><option value="">All Levels</option><option value="College">College</option><option value="Senior High School">Senior High</option><option value="Junior High School">Junior High</option></select></div>
            <div class="form-group" id="idgen-group-wrap"><label id="idgen-group-label">Academic Group</label><select class="form-input form-select themed-select" id="idgen-group"><option value="">All Academic Groups</option></select></div>
            <div class="form-group"><label id="idgen-year-label">Year / Grade</label><select class="form-input form-select themed-select" id="idgen-year"><option value="">All Years / Grades</option></select></div>
            <div class="form-group idgen-search-group"><label>Search</label><input class="form-input" id="idgen-search" placeholder="Name or student no."></div>
            <div class="idgen-batch-actions"><button class="btn btn-primary" id="idgen-generate"><i class="ph ph-printer"></i> Generate PVC Print · 2-Up</button></div>
        </div>
        <div class="table-wrapper idgen-student-table"><table class="data-table"><thead><tr><th class="idgen-select-column"><label class="idgen-check-control" title="Select all visible students"><input type="checkbox" id="idgen-check-all" aria-label="Select all visible students"><span class="idgen-check-visual" aria-hidden="true"></span></label></th><th>Photo</th><th>Student</th><th>Education Level</th><th>Academic Profile</th><th>Section</th></tr></thead><tbody id="idgen-tbody"><tr><td colspan="6" class="table-loading">Open ID Generator to load students.</td></tr></tbody></table></div>
        <p class="field-hint" style="margin-top:.75rem"><i class="ph ph-info"></i> Generates exact-size CR80 front/back sheets with <strong>2 PVC IDs per A4 page</strong>. Print at <strong>100% / Actual Size</strong>; for duplex use <strong>Flip on long edge</strong>.</p>
    </div>
</section>


<!-- ══ INTRAMURALS CLUSTERS ═══════════════════════════════ -->
<section class="section" id="section-clusters">
    <div class="section-header cluster-page-head">
        <div><h2>Intramurals Cluster Manager</h2><p>Create balanced random teams for College and High School while keeping each division completely separate.</p></div>
        <span class="cluster-engine-badge"><i class="ph-fill ph-sparkle"></i> BALANCED RANDOM ENGINE</span>
    </div>

    <div class="cluster-command panel">
        <div class="cluster-command-main">
            <div class="cluster-command-icon"><i class="ph-fill ph-trophy"></i></div>
            <div><span class="cluster-kicker">INTRAMURALS COMMAND CENTER</span><h3 id="cluster-event-title">Create or select an Intramurals event</h3><p id="cluster-event-subtitle">Events preserve team assignments by school year, so future Intramurals will never overwrite historical clusters.</p></div>
        </div>
        <div class="cluster-event-tools">
            <select id="cluster-event-select" class="form-input form-select themed-select"><option value="">No event yet</option></select>
            <button class="btn btn-secondary" id="cluster-new-event"><i class="ph ph-plus"></i> New Event</button>
            <button class="btn cluster-delete-event-btn" id="cluster-delete-event" disabled><i class="ph ph-trash"></i> Delete Event</button>
        </div>
    </div>

    <div class="cluster-division-tabs" id="cluster-division-tabs">
        <button class="cluster-division-tab active" data-division="College"><i class="ph-fill ph-graduation-cap"></i><span><strong>College</strong><small>College students only</small></span></button>
        <button class="cluster-division-tab" data-division="High School"><i class="ph-fill ph-student"></i><span><strong>High School</strong><small>Senior + Junior High</small></span></button>
    </div>

    <div class="event-override-status panel" id="event-override-status"><div class="event-override-status-icon"><i class="ph-fill ph-calendar-check"></i></div><div><span class="cluster-kicker">EVENT SCHEDULE ENGINE</span><strong id="event-override-status-title">Regular academic schedules active</strong><small id="event-override-status-copy">When an Intramurals event reaches its configured dates, its temporary College and High School times take priority automatically.</small></div><span class="event-mode-pill" id="event-mode-pill">NORMAL MODE</span></div>

    <div class="cluster-stat-grid">
        <div class="cluster-stat"><i class="ph-fill ph-users-three"></i><div><span>Eligible Students</span><strong id="cluster-pool-count">0</strong></div></div>
        <div class="cluster-stat"><i class="ph-fill ph-shuffle-angular"></i><div><span>Assigned</span><strong id="cluster-assigned-count">0</strong></div></div>
        <div class="cluster-stat"><i class="ph-fill ph-user-minus"></i><div><span>Unassigned</span><strong id="cluster-unassigned-count">0</strong></div></div>
        <div class="cluster-stat"><i class="ph-fill ph-squares-four"></i><div><span>Clusters</span><strong id="cluster-count">0</strong></div></div>
    </div>

    <div class="cluster-builder-grid">
        <div class="panel cluster-setup-panel">
            <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-stack" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Cluster Setup</h3></div><button class="btn-icon" id="cluster-add-name" title="Add cluster"><i class="ph ph-plus"></i></button></div>
            <div class="cluster-name-list" id="cluster-name-list"></div>
            <div class="cluster-setup-actions"><button class="btn btn-secondary" id="cluster-save-names"><i class="ph ph-floppy-disk"></i> Save Names</button><button class="btn btn-primary" id="cluster-generate"><i class="ph-fill ph-shuffle-angular"></i> Generate Balanced Random</button></div>
            <div class="cluster-logic-note"><i class="ph-fill ph-info"></i><span>The engine shuffles the selected division independently, then distributes students round-robin so cluster sizes differ by at most one student.</span></div>
        </div>
        <div class="panel cluster-history-panel">
            <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-clock-counter-clockwise" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Generation History</h3></div></div>
            <div id="cluster-history" class="cluster-history"><div class="cluster-empty-mini">No generation history yet.</div></div>
        </div>
    </div>

    <div class="panel cluster-roster-panel">
        <div class="panel-header"><div class="panel-title-group"><i class="ph-fill ph-users-four" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Cluster Rosters</h3></div><div class="cluster-roster-tools"><input class="form-input" id="cluster-member-search" placeholder="Search member..."><button class="btn btn-secondary" id="cluster-print"><i class="ph ph-printer"></i> Print Rosters</button></div></div>
        <div id="cluster-roster-grid" class="cluster-roster-grid"><div class="cluster-empty-state"><i class="ph ph-trophy"></i><strong>No clusters configured</strong><span>Add cluster names above, then generate balanced assignments.</span></div></div>
    </div>
</section>

<!-- INTRAMURALS EVENT DELETE MODAL -->
<div class="modal-overlay" id="cluster-delete-event-modal">
    <div class="modal modal-sm cluster-delete-confirm-modal">
        <div class="modal-header danger">
            <h3><i class="ph-fill ph-warning-octagon"></i> Delete Intramurals Event</h3>
            <button class="modal-close" id="cluster-delete-event-close"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body cluster-delete-confirm-body">
            <div class="cluster-delete-warning-icon"><i class="ph-fill ph-trash"></i></div>
            <span class="cluster-kicker">PERMANENT EVENT REMOVAL</span>
            <h4 id="cluster-delete-event-name">Selected event</h4>
            <p>This removes the event, its College and High School clusters, all student assignments, and its random-generation history.</p>
            <div class="cluster-delete-safety-note"><i class="ph-fill ph-shield-check"></i><span>Student records and permanent campus movement logs will <strong>not</strong> be deleted or changed. Deleting an active event only ends its time-bound event attendance layer and removes its cluster assignments.</span></div>
            <div class="form-actions cluster-delete-actions">
                <button class="btn btn-ghost" id="cluster-delete-event-cancel">Keep Event</button>
                <button class="btn btn-danger" id="cluster-delete-event-confirm"><i class="ph ph-trash"></i> Permanently Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- EVENT MODAL -->
<div class="modal-overlay" id="cluster-event-modal"><div class="modal cluster-event-modal"><div class="modal-header"><h3><i class="ph-fill ph-trophy"></i> Intramurals Event & Temporary Schedule</h3><button class="modal-close" data-cluster-modal-close><i class="ph ph-x"></i></button></div><div class="modal-body"><form id="cluster-event-form">
<input type="hidden" id="cluster-event-id">
<div class="event-override-note"><i class="ph-fill ph-arrows-clockwise"></i><div><strong>Automatic time-bound event attendance</strong><span>During the dates below, these times qualify Intramurals attendance. Permanent physical IN / OUT monitoring continues at all times and is never overwritten.</span></div></div>
<div class="form-group"><label>Event Name</label><input class="form-input" id="cluster-event-name" placeholder="e.g. SIBTECH Intramurals 2026" required></div>
<div class="form-row"><div class="form-group"><label>School Year</label><input class="form-input" id="cluster-event-school-year" placeholder="2026-2027" required></div><div class="form-group"><label>Start Date</label><input type="date" class="form-input" id="cluster-event-start" required></div><div class="form-group"><label>End Date</label><input type="date" class="form-input" id="cluster-event-end" required></div></div>
<label class="event-override-toggle"><input type="checkbox" id="cluster-event-override" checked><span class="irreg-toggle-switch"></span><span><strong>Enable automatic event schedule override</strong><small>Applies only while today is between Start Date and End Date.</small></span></label>
<div class="event-schedule-grid">
<section class="event-schedule-card"><div class="event-schedule-head"><i class="ph-fill ph-graduation-cap"></i><div><strong>College Event Schedule</strong><span>All College courses and sections use one Intramurals schedule.</span></div></div>
<div class="event-time-grid"><label>Time In Start<input type="time" class="form-input" id="college-event-time-in" value="06:00"></label><label>On Time Until<input type="time" class="form-input" id="college-event-ontime" value="07:30"></label><label>Late Cutoff<input type="time" class="form-input" id="college-event-late" value="08:30"></label><label>Final Out Start<input type="time" class="form-input" id="college-event-out-start" value="16:00"></label><label>Final Out End<input type="time" class="form-input" id="college-event-out-end" value="18:00"></label></div>
<label class="event-lunch-toggle"><input type="checkbox" id="college-event-lunch"><span class="irreg-toggle-switch"></span><span class="event-lunch-toggle-copy"><strong>Enable Lunch Out / Lunch In</strong><small>Use lunch exit and return scans during the College Intramurals schedule.</small></span></label><div class="event-lunch-times" id="college-event-lunch-times"><label>Lunch Out Start<input type="time" class="form-input" id="college-event-lunch-out-start" value="11:30"></label><label>Lunch Out End<input type="time" class="form-input" id="college-event-lunch-out-end" value="12:15"></label><label>Lunch In Start<input type="time" class="form-input" id="college-event-lunch-in-start" value="12:15"></label><label>Lunch In End<input type="time" class="form-input" id="college-event-lunch-in-end" value="13:15"></label></div></section>
<section class="event-schedule-card"><div class="event-schedule-head"><i class="ph-fill ph-student"></i><div><strong>High School Event Schedule</strong><span>Senior High + Junior High are combined into one event schedule.</span></div></div>
<div class="event-time-grid"><label>Time In Start<input type="time" class="form-input" id="hs-event-time-in" value="06:30"></label><label>On Time Until<input type="time" class="form-input" id="hs-event-ontime" value="07:30"></label><label>Late Cutoff<input type="time" class="form-input" id="hs-event-late" value="08:30"></label><label>Final Out Start<input type="time" class="form-input" id="hs-event-out-start" value="15:30"></label><label>Final Out End<input type="time" class="form-input" id="hs-event-out-end" value="17:30"></label></div>
<label class="event-lunch-toggle"><input type="checkbox" id="hs-event-lunch"><span class="irreg-toggle-switch"></span><span class="event-lunch-toggle-copy"><strong>Enable Lunch Out / Lunch In</strong><small>Use lunch exit and return scans during the combined High School Intramurals schedule.</small></span></label><div class="event-lunch-times" id="hs-event-lunch-times"><label>Lunch Out Start<input type="time" class="form-input" id="hs-event-lunch-out-start" value="11:30"></label><label>Lunch Out End<input type="time" class="form-input" id="hs-event-lunch-out-end" value="12:15"></label><label>Lunch In Start<input type="time" class="form-input" id="hs-event-lunch-in-start" value="12:15"></label><label>Lunch In End<input type="time" class="form-input" id="hs-event-lunch-in-end" value="13:15"></label></div></section>
</div><div class="form-actions"><button type="button" class="btn btn-secondary" data-cluster-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Save Event & Schedule</button></div></form></div></div></div>

<!-- MOVEMENT HISTORY MODAL -->
<div class="modal-overlay" id="movement-history-modal"><div class="modal-card movement-history-modal-card"><div class="modal-header"><div><span class="modal-kicker">STUDENT MONITORING</span><h3 id="movement-history-name">Movement History</h3><p id="movement-history-profile">Exact verified campus entries and exits</p></div><button class="modal-close" type="button" id="movement-history-close"><i class="ph ph-x"></i></button></div><div class="movement-history-summary" id="movement-history-summary"></div><div class="movement-timeline" id="movement-history-list"><div class="loader-row"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</div></div></div></div>

<!-- ══ SETTINGS ════════════════════════════════════════════ -->
<section class="section" id="section-settings">
    <div class="section-header">
        <h2>System & Security</h2>
        <p>Safe operational controls only. Sensitive scanner credentials and API secrets are intentionally hidden from the dashboard.</p>
    </div>

    <div class="security-hero panel">
        <div class="security-hero-icon"><i class="ph-fill ph-shield-check"></i></div>
        <div class="security-hero-copy">
            <span class="security-kicker">TRACKED SECURITY CENTER</span>
            <h3>Protected configuration surface</h3>
            <p>Campus movement rules remain in Campus Policy. Secret keys, database credentials, and scanner authentication values are not exposed here.</p>
        </div>
        <span class="security-state-badge"><span></span> Protected</span>
    </div>

    <div class="settings-security-grid">
        <div class="panel secure-settings-card">
            <div class="panel-header">
                <div class="panel-title-group"><i class="ph-fill ph-buildings" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">System Profile</h3></div>
            </div>
            <div class="settings-form">
                <div class="form-group">
                    <label><i class="ph ph-buildings"></i> School / Institution Name</label>
                    <input type="text" name="school_name" class="form-input config-input" value="SIBTECH" placeholder="School name">
                    <p class="field-hint">Used as the institution identity in system-generated notifications.</p>
                </div>
                <div class="irregular-toggle-group">
                    <label class="irreg-toggle-label secure-toggle" for="sms_enabled_chk">
                        <input type="checkbox" id="sms_enabled_chk" name="sms_enabled" value="1" checked>
                        <span class="irreg-toggle-switch"></span>
                        <span class="irreg-toggle-text"><strong>Parent SMS Notifications</strong><small>Enable or pause movement/event SMS processing without exposing modem credentials.</small></span>
                    </label>
                </div>
                <div class="form-actions"><button class="btn btn-primary" id="save-config-btn"><i class="ph ph-floppy-disk"></i> Save Safe Settings</button></div>
            </div>
        </div>

        <div class="panel secure-settings-card">
            <div class="panel-header">
                <div class="panel-title-group"><i class="ph-fill ph-lock-key" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Access Security</h3></div>
            </div>
            <div class="security-detail-list">
                <div class="security-detail-item"><div class="security-detail-icon"><i class="ph ph-user-circle"></i></div><div><span>Signed-in account</span><strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator') ?></strong></div><span class="security-ok"><i class="ph-fill ph-check-circle"></i></span></div>
                <div class="security-detail-item"><div class="security-detail-icon"><i class="ph ph-clock-countdown"></i></div><div><span>Session policy</span><strong>8-hour idle timeout</strong></div><span class="security-ok"><i class="ph-fill ph-check-circle"></i></span></div>
                <div class="security-detail-item"><div class="security-detail-icon"><i class="ph ph-eye-slash"></i></div><div><span>Scanner/API secrets</span><strong>Hidden from admin UI</strong></div><span class="security-ok"><i class="ph-fill ph-check-circle"></i></span></div>
                <div class="security-detail-item"><div class="security-detail-icon"><i class="ph ph-user-plus-minus"></i></div><div><span>Public registration</span><strong>Disabled</strong></div><span class="security-ok"><i class="ph-fill ph-check-circle"></i></span></div>
            </div>
            <a class="btn btn-secondary security-logout-btn" href="index.php?logout=1"><i class="ph ph-sign-out"></i> Sign Out of TrackED</a>
        </div>
    </div>

    <div class="panel retention-control-panel">
        <div class="panel-header retention-panel-head">
            <div class="panel-title-group"><div class="retention-icon"><i class="ph-fill ph-archive-tray"></i></div><div><h3 class="panel-title">Verified Log Retention & Backup</h3><p class="panel-subtitle">Keep the live monitoring database lean while preserving a verified incident-ready archive before any deletion.</p></div></div>
            <span class="retention-state-badge" id="retention-state-badge"><i class="ph-fill ph-shield-check"></i> Protected Archive</span>
        </div>
        <div class="retention-stats-grid">
            <div class="retention-stat"><span>Live Movement Logs</span><strong id="retention-live-count">—</strong><small id="retention-oldest">Oldest live record: —</small></div>
            <div class="retention-stat"><span>Verified Daily Archives</span><strong id="retention-archive-days">—</strong><small>CSV + manifest verification</small></div>
            <div class="retention-stat"><span>Last Maintenance</span><strong id="retention-last-status">Never</strong><small id="retention-last-time">Task has not run yet</small></div>
            <div class="retention-stat"><span>Archive Location</span><strong class="retention-path-title">Outside Web Root</strong><small id="retention-backup-path">C:\xampp\tracked_backups</small></div>
        </div>
        <div class="retention-layout">
            <div class="retention-policy-box">
                <div class="retention-box-title"><div><span>RETENTION POLICY</span><h4>Automatic archive-before-purge</h4></div><i class="ph-fill ph-clock-countdown"></i></div>
                <div class="retention-form-grid">
                    <label class="form-group"><span>Live Log Retention</span><div class="retention-number-shell"><input type="number" class="form-input" id="retention-days" min="7" max="365" value="30"><small>days</small></div><p class="field-hint">Movement and rejected-scan logs older than this are eligible for purge only after verified backup.</p></label>
                    <label class="form-group"><span>Archive Retention</span><div class="retention-number-shell"><input type="number" class="form-input" id="retention-archive-months" min="12" max="120" value="12"><small>months</small></div><p class="field-hint">Verified monthly archives are retained for at least 12 months.</p></label>
                </div>
                <div class="retention-toggle-stack">
                    <label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="retention-daily-backup" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Automatic Daily Backup</strong><small>Archives the completed day as Excel-friendly CSV files with SHA-256 manifest verification.</small></span></label>
                    <label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="retention-monthly-archive" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Monthly Archive Bundle</strong><small>Builds a monthly archive from verified daily snapshots.</small></span></label>
                    <label class="irreg-toggle-label secure-toggle"><input type="checkbox" id="retention-auto-purge" checked><span class="irreg-toggle-switch"></span><span class="irreg-toggle-text"><strong>Auto-Purge After Verification</strong><small>Deletion is blocked whenever a verified archive is missing or an incident hold covers the date.</small></span></label>
                </div>
                <div class="retention-actions"><button type="button" class="btn btn-secondary" id="run-backup-now"><i class="ph ph-cloud-arrow-up"></i> Backup Now</button><button type="button" class="btn btn-primary" id="save-retention-policy"><i class="ph ph-floppy-disk"></i> Save Retention Policy</button></div>
            </div>
            <div class="retention-hold-box">
                <div class="retention-box-title"><div><span>INCIDENT SAFEGUARD</span><h4>Protected Log Holds</h4></div><i class="ph-fill ph-lock-key"></i></div>
                <p class="retention-hold-intro">Protect any investigation period from automatic deletion—even after the normal retention window expires.</p>
                <div class="retention-hold-form">
                    <input type="text" class="form-input" id="hold-case-name" placeholder="Case / incident name">
                    <div class="retention-date-pair"><label><span>From</span><input type="date" class="form-input" id="hold-date-from"></label><label><span>To</span><input type="date" class="form-input" id="hold-date-to"></label></div>
                    <input type="text" class="form-input" id="hold-note" placeholder="Optional note / reference">
                    <button type="button" class="btn btn-secondary" id="add-retention-hold"><i class="ph ph-lock-simple"></i> Protect Date Range</button>
                </div>
                <div class="retention-hold-list" id="retention-hold-list"><div class="retention-empty">No protected incident ranges.</div></div>
            </div>
        </div>
        <div class="retention-safety-strip"><i class="ph-fill ph-shield-check"></i><div><strong>Backup first. Verify second. Purge last.</strong><span>Students, photos, signatures, settings, ID templates, clusters, and academic master data are never included in automatic deletion.</span></div></div>
    </div>

    <div class="panel" style="margin-top:1rem">
        <div class="panel-header">
            <div class="panel-title-group"><i class="ph-fill ph-pulse" style="color:var(--primary);font-size:1.2rem"></i><h3 class="panel-title">Operational Health</h3></div>
            <button class="btn-icon" id="reload-sms-btn" title="Refresh SMS queue status"><i class="ph ph-arrows-clockwise"></i></button>
        </div>
        <div class="operations-health-grid">
            <div class="health-tile"><div class="health-icon"><i class="ph-fill ph-database"></i></div><div><span>Database</span><strong><?= isset($db_error) ? 'Unavailable' : 'Connected' ?></strong><small>Central TrackED data source</small></div><span class="health-dot <?= isset($db_error) ? 'warn' : '' ?>"></span></div>
            <div class="health-tile sms-health-tile">
                <div class="health-icon"><i class="ph-fill ph-chat-circle-text"></i></div>
                <div class="sms-health-content">
                    <span class="health-label">SMS Queue</span>
                    <div class="sms-health-metrics" aria-label="SMS queue status">
                        <div class="sms-health-metric sms-health-pending">
                            <strong id="sms-pending">—</strong>
                            <span>Pending</span>
                        </div>
                        <div class="sms-health-metric sms-health-sent">
                            <strong id="sms-sent">—</strong>
                            <span>Sent</span>
                        </div>
                        <div class="sms-health-metric sms-health-failed">
                            <strong id="sms-failed">—</strong>
                            <span>Failed</span>
                        </div>
                    </div>
                </div>
                <span class="health-dot"></span>
            </div>
            <div class="health-tile"><div class="health-icon"><i class="ph-fill ph-arrows-left-right"></i></div><div><span>Monitoring Engine</span><strong>Movement Logs</strong><small>College open • High School controlled</small></div><span class="health-dot"></span></div>
            <div class="health-tile"><div class="health-icon"><i class="ph-fill ph-fingerprint"></i></div><div><span>Scanner Security</span><strong>Server-side only</strong><small>Credentials are not rendered in Settings</small></div><span class="health-dot"></span></div>
        </div>
        <div class="security-note"><i class="ph-fill ph-info"></i><span>For security, scanner keys, database passwords, UART paths, and API credentials must be changed directly in protected server configuration files—not through the browser dashboard.</span></div>
    </div>
</section>

</main>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header"><h3><i class="ph-fill ph-pencil-simple"></i> Edit Student Profile</h3><button class="modal-close" id="modal-close"><i class="ph ph-x"></i></button></div>
        <div class="modal-body" style="overflow-y:auto; flex:1;">
            <form id="edit-form">
                <input type="hidden" id="edit-id">
                <div class="form-row">
                    <div class="form-group"><label>RFID UID</label><input type="text" id="edit-rfid" class="form-input" readonly style="background:var(--bg-secondary)"></div>
                    <div class="form-group"><label>Student ID No.</label><input type="text" id="edit-idno" class="form-input"></div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;"><label>Full Name *</label><input type="text" id="edit-name" class="form-input" required></div>
                <div class="academic-edit-block">
                    <div class="form-group"><label>Education Level *</label><select id="edit-level" class="form-input form-select themed-select"><option value="College">College</option><option value="Senior High School">Senior High School</option><option value="Junior High School">Junior High School</option></select></div>
                    <div class="form-row">
                        <div class="form-group" id="edit-course-group"><label>Course / Program *</label><select id="edit-course" class="form-input form-select themed-select"><?php foreach($COURSES as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
                        <div class="form-group" id="edit-track-group" style="display:none"><label>Track *</label><select id="edit-track" class="form-input form-select themed-select"><?php foreach($SHS_TRACKS as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></div>
                        <div class="form-group" id="edit-strand-group" style="display:none"><label>Strand *</label><select id="edit-strand" class="form-input form-select themed-select"><?php foreach($SHS_STRANDS as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label id="edit-year-label">Year Level *</label><select id="edit-year" class="form-input form-select themed-select"></select></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Section</label><input type="text" id="edit-section" class="form-input"></div>
                    <div class="form-group"><label>Parent Mobile</label><input type="tel" id="edit-mobile" class="form-input"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Parent / Guardian Name</label><input type="text" id="edit-guardian-name" class="form-input" placeholder="Emergency contact name"></div>
                    <div class="form-group"><label>Emergency Address</label><input type="text" id="edit-guardian-address" class="form-input" placeholder="Barangay, Municipality, Province"></div>
                </div>
                <div class="form-group edit-signature-group">
                    <label>Student Signature</label>
                    <div class="edit-signature-row"><div class="edit-signature-preview"><img id="edit-signature-preview-img" alt="Current student signature"><span id="edit-signature-preview-placeholder">No signature</span></div><label class="btn btn-secondary btn-sm" for="edit-student-signature"><i class="ph ph-upload-simple"></i> Replace Signature</label><input type="file" id="edit-student-signature" accept="image/png,image/jpeg,image/webp" hidden></div>
                    <p class="field-hint">Leave unchanged to keep the current signature.</p>
                </div>
                <div class="irregular-toggle-group" style="margin-bottom:1rem;">
                    <label class="irreg-toggle-label">
                        <input type="checkbox" id="edit-is-irregular">
                        <span class="irreg-toggle-switch"></span>
                        <span class="irreg-toggle-text"><strong>Irregular Student</strong><small>Academic classification only; RFID IN/OUT rules remain unchanged.</small></span>
                    </label>
                </div>
                <div class="form-actions"><button type="button" class="btn btn-ghost" id="modal-cancel">Cancel</button><button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<!-- SUBJECTS MODAL -->
<div class="modal-overlay" id="subjects-modal">
    <div class="modal" style="max-width:680px; max-height:90vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h3><i class="ph-fill ph-books"></i> Subjects — <span id="subjects-modal-title"></span></h3>
            <button class="modal-close" id="subjects-modal-close"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="overflow-y:auto; flex:1;">
            <form id="subject-form" class="sched-form" style="margin-bottom:1rem;">
                <input type="hidden" id="subj-id" value="0">
                <input type="hidden" id="subj-schedule-id" value="0">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-book"></i> Subject Name <span class="field-required">*</span></label>
                        <input type="text" id="subj-name" class="form-input" placeholder="e.g. Data Structures" required>
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-hash"></i> Subject Code</label>
                        <input type="text" id="subj-code" class="form-input" placeholder="e.g. IT301">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-user"></i> Instructor</label>
                        <input type="text" id="subj-instructor" class="form-input" placeholder="e.g. Mr. Dela Cruz">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-door-open"></i> Room</label>
                        <input type="text" id="subj-room" class="form-input" placeholder="e.g. Room 204">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="ph ph-calendar-dots"></i> Day</label>
                        <select id="subj-day" class="form-input form-select">
                            <option value="">— Any / Not Set —</option>
                            <?php foreach($DAYS as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-clock"></i> Time Start</label>
                        <input type="time" id="subj-time-start" class="form-input">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-clock-countdown"></i> Time End</label>
                        <input type="time" id="subj-time-end" class="form-input">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" id="subj-clear-btn"><i class="ph ph-eraser"></i> Clear</button>
                    <button type="submit" class="btn btn-primary" id="subj-save-btn"><i class="ph ph-floppy-disk"></i> Save Subject</button>
                </div>
            </form>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Subject</th><th>Code</th><th>Instructor</th><th>Room</th><th>Day</th><th>Time</th><th>Action</th></tr></thead>
                    <tbody id="subjects-tbody"><tr><td colspan="7" class="table-loading"><div class="loader-dots"><span></span><span></span><span></span></div> Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal modal-sm">
        <div class="modal-header danger"><h3><i class="ph-fill ph-warning"></i> Confirm Deletion</h3><button class="modal-close" id="delete-modal-close"><i class="ph ph-x"></i></button></div>
        <div class="modal-body" style="text-align:center;padding:2rem;">
            <div style="width:68px;height:68px;border-radius:50%;background:var(--danger-light);display:flex;align-items:center;justify-content:center;margin:0 auto;"><i class="ph-fill ph-trash" style="font-size:2.5rem;color:var(--danger);"></i></div>
            <p style="margin-top:1rem;">You are about to delete:</p>
            <p id="delete-student-name" style="font-weight:700;font-size:1.1rem;margin:.5rem 0;"></p>
            <p style="color:var(--text-muted);font-size:.82rem;">This action cannot be undone.</p>
            <div class="form-actions" style="justify-content:center;margin-top:1.5rem;">
                <button class="btn btn-ghost" id="delete-cancel">Cancel</button>
                <button class="btn btn-danger" id="delete-confirm"><i class="ph ph-trash"></i> Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- INVALID RFID MODAL -->
<div class="modal-overlay" id="invalid-rfid-modal">
    <div class="modal modal-sm rfid-alert-modal">
        <div class="rfid-alert-icon"><i class="ph-fill ph-warning-circle"></i></div>
        <h3>Unregistered RFID Card</h3>
        <p>The scanned card is not in the system.</p>
        <code id="invalid-rfid-uid" class="rfid-code-display"></code>
        <p class="rfid-alert-hint">Please register this student before scanning.</p>
        <button class="btn btn-primary" id="rfid-alert-close"><i class="ph ph-arrow-right"></i> Register Now</button>
    </div>
</div>

<div id="toast-container"></div>

<?php if (isset($db_error)): ?>
<div id="demo-banner" class="demo-banner">
    <i class="ph ph-info"></i>
    <span>Demo mode — <?= htmlspecialchars($db_error) ?></span>
    <button onclick="this.parentElement.style.display='none'"><i class="ph ph-x"></i></button>
</div>
<?php endif; ?>

<!-- Pass PHP arrays to JS -->
<script>
    const COURSES       = <?= json_encode($COURSES) ?>;
    const YEAR_LEVELS   = <?= json_encode($YEAR_LEVELS) ?>;
    const GRADE_LEVELS  = <?= json_encode($GRADE_LEVELS) ?>;
    const SHS_TRACKS    = <?= json_encode($SHS_TRACKS) ?>;
    const SHS_STRANDS   = <?= json_encode($SHS_STRANDS) ?>;
    const DAYS_OF_WEEK  = <?= json_encode($DAYS) ?>;
</script>
<script src="app.js?v=20260907-fresh-final"></script>
</body>
</html>
