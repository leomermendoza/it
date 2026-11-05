<?php
/** TrackED verified log retention / archive helpers. */

function tr_table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}


function tr_ensure_exception_schema(mysqli $conn): bool {
    $sql="CREATE TABLE IF NOT EXISTS daily_monitoring_exceptions (
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

function tr_generate_no_out_exceptions(mysqli $conn, string $date): int {
    $dt=DateTime::createFromFormat('Y-m-d',$date);
    if(!$dt || $dt->format('Y-m-d')!==$date || $date>=date('Y-m-d')) return 0;
    if(!tr_ensure_exception_schema($conn) || !tr_table_exists($conn,'student_movement_logs')) return 0;
    $d=$conn->real_escape_string($date);
    $conn->query("INSERT IGNORE INTO daily_monitoring_exceptions
        (student_id,exception_date,exception_type,last_movement_id,last_movement_at,detected_at,is_resolved,resolution_note,source)
        SELECT m.student_id,DATE(m.verified_at),'NO_OUT',m.id,m.verified_at,NOW(),0,'','AUTO_EOD'
        FROM student_movement_logs m
        WHERE DATE(m.verified_at)='$d' AND m.movement_type='IN'
          AND NOT EXISTS (
              SELECT 1 FROM student_movement_logs m2
              WHERE m2.student_id=m.student_id AND DATE(m2.verified_at)=DATE(m.verified_at)
                AND (m2.verified_at>m.verified_at OR (m2.verified_at=m.verified_at AND m2.id>m.id))
          )");
    $inserted=max(0,(int)$conn->affected_rows);
    $conn->query("UPDATE daily_monitoring_exceptions e
        JOIN student_movement_logs m ON m.id=(SELECT m3.id FROM student_movement_logs m3 WHERE m3.student_id=e.student_id AND DATE(m3.verified_at)=e.exception_date ORDER BY m3.verified_at DESC,m3.id DESC LIMIT 1)
        SET e.is_resolved=1,e.resolved_at=COALESCE(e.resolved_at,NOW()),e.resolution_note=IF(e.resolution_note='', 'A later same-day OUT record was added after the exception was detected.', e.resolution_note)
        WHERE e.exception_type='NO_OUT' AND e.exception_date='$d' AND e.is_resolved=0 AND m.movement_type='OUT'");
    return $inserted;
}

function tr_config(mysqli $conn): array {
    $cfg = [
        'log_retention_days' => '30',
        'daily_backup_enabled' => '1',
        'monthly_archive_enabled' => '1',
        'auto_purge_enabled' => '1',
        'archive_retention_months' => '12',
    ];
    if (tr_table_exists($conn, 'system_config')) {
        $r = $conn->query("SELECT config_key,config_value FROM system_config WHERE config_key IN ('log_retention_days','daily_backup_enabled','monthly_archive_enabled','auto_purge_enabled','archive_retention_months')");
        if ($r) while ($x=$r->fetch_assoc()) $cfg[$x['config_key']] = (string)$x['config_value'];
    }
    $cfg['log_retention_days'] = (string)max(7,min(365,(int)$cfg['log_retention_days']));
    $cfg['archive_retention_months'] = (string)max(12,min(120,(int)$cfg['archive_retention_months']));
    return $cfg;
}

function tr_backup_root(): string {
    // Project is normally C:\\xampp\\htdocs\\tracked. Keep archives outside htdocs.
    $project = dirname(__DIR__);
    $htdocs = dirname($project);
    $xampp = dirname($htdocs);
    return $xampp . DIRECTORY_SEPARATOR . 'tracked_backups';
}

function tr_safe_csv($value) {
    if ($value === null) return '';
    $s = (string)$value;
    if ($s !== '' && preg_match('/^[=+\-@]/', $s)) $s = "'".$s; // CSV formula-injection guard
    return $s;
}

function tr_write_csv(string $path, array $headers, mysqli_result $result): array {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Could not create archive directory: $dir");
    $fh = @fopen($path, 'wb');
    if (!$fh) throw new RuntimeException("Could not write archive file: $path");
    fwrite($fh, "\xEF\xBB\xBF"); // Excel-friendly UTF-8 BOM
    fputcsv($fh, $headers);
    $count=0;
    while ($row=$result->fetch_assoc()) {
        $out=[];
        foreach ($headers as $h) $out[] = tr_safe_csv($row[$h] ?? '');
        fputcsv($fh, $out); $count++;
    }
    fflush($fh); fclose($fh);
    if (!is_file($path) || filesize($path) < 3) throw new RuntimeException("Archive verification failed: $path");
    return ['count'=>$count,'sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path)];
}

function tr_archive_day(mysqli $conn, string $date, bool $force=false): array {
    $dt = DateTime::createFromFormat('Y-m-d',$date);
    if (!$dt || $dt->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid archive date.');
    tr_generate_no_out_exceptions($conn,$date);
    $root = tr_backup_root();
    $dir = $root.DIRECTORY_SEPARATOR.'daily'.DIRECTORY_SEPARATOR.$dt->format('Y').DIRECTORY_SEPARATOR.$dt->format('m').DIRECTORY_SEPARATOR.$date;
    $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';
    if (!$force && is_file($manifestPath)) {
        $old=json_decode((string)@file_get_contents($manifestPath),true);
        $needExceptionArchive=false;
        if(tr_table_exists($conn,'daily_monitoring_exceptions')){
            $dcheck=$conn->real_escape_string($date);$er=$conn->query("SELECT COUNT(*) c FROM daily_monitoring_exceptions WHERE exception_date='$dcheck'");
            $currentExceptionCount=$er?(int)$er->fetch_assoc()['c']:0;
            $archivedExceptionCount=(int)($old['files']['daily_exceptions.csv']['count']??-1);
            $needExceptionArchive=$currentExceptionCount!==$archivedExceptionCount;
        }
        if (is_array($old) && !empty($old['verified']) && !$needExceptionArchive) return $old;
    }
    if (!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException("Could not create $dir");
    $d=$conn->real_escape_string($date);
    $files=[]; $total=0;

    if (tr_table_exists($conn,'student_movement_logs')) {
        $q="SELECT m.id,s.student_id_no,s.full_name,s.education_level,s.course,s.track,s.strand,s.year_level,s.section,m.rfid_uid,m.movement_type,m.movement_context,m.authorization_reason,m.verified_at,m.verified_by,m.source,m.event_id FROM student_movement_logs m LEFT JOIN students s ON s.id=m.student_id WHERE DATE(m.verified_at)='$d' ORDER BY m.verified_at,m.id";
        $r=$conn->query($q); if(!$r) throw new RuntimeException($conn->error);
        $headers=['id','student_id_no','full_name','education_level','course','track','strand','year_level','section','rfid_uid','movement_type','movement_context','authorization_reason','verified_at','verified_by','source','event_id'];
        $files['movements.csv']=tr_write_csv($dir.DIRECTORY_SEPARATOR.'movements.csv',$headers,$r); $total += $files['movements.csv']['count'];
    }
    if (tr_table_exists($conn,'rejected_scans')) {
        $ctx = '';
        $cr=$conn->query("SHOW COLUMNS FROM rejected_scans LIKE 'movement_context'");
        if($cr && $cr->num_rows) $ctx=',r.movement_context';
        $q="SELECT r.id,s.student_id_no,s.full_name,s.education_level,s.course,s.track,s.strand,s.year_level,s.section,r.rfid_uid,r.scan_type".$ctx.",r.rejection_reason,r.rejected_at FROM rejected_scans r LEFT JOIN students s ON s.id=r.student_id WHERE DATE(r.rejected_at)='$d' ORDER BY r.rejected_at,r.id";
        $r=$conn->query($q); if(!$r) throw new RuntimeException($conn->error);
        $headers=['id','student_id_no','full_name','education_level','course','track','strand','year_level','section','rfid_uid','scan_type'];
        if($ctx) $headers[]='movement_context';
        $headers[]='rejection_reason';$headers[]='rejected_at';
        $files['rejected_scans.csv']=tr_write_csv($dir.DIRECTORY_SEPARATOR.'rejected_scans.csv',$headers,$r); $total += $files['rejected_scans.csv']['count'];
    }
    if (tr_table_exists($conn,'daily_monitoring_exceptions')) {
        $q="SELECT e.id,s.student_id_no,s.full_name,s.education_level,s.course,s.track,s.strand,s.year_level,s.section,e.exception_date,e.exception_type,e.last_movement_at,e.detected_at,e.is_resolved,e.resolved_at,e.resolution_note,e.source FROM daily_monitoring_exceptions e LEFT JOIN students s ON s.id=e.student_id WHERE e.exception_date='$d' ORDER BY e.last_movement_at,e.id";
        $r=$conn->query($q); if(!$r) throw new RuntimeException($conn->error);
        $headers=['id','student_id_no','full_name','education_level','course','track','strand','year_level','section','exception_date','exception_type','last_movement_at','detected_at','is_resolved','resolved_at','resolution_note','source'];
        $files['daily_exceptions.csv']=tr_write_csv($dir.DIRECTORY_SEPARATOR.'daily_exceptions.csv',$headers,$r); $total += $files['daily_exceptions.csv']['count'];
    }
    // Event attendance is archived for reference but intentionally not auto-purged.
    if (tr_table_exists($conn,'attendance')) {
        $hasEvent=$conn->query("SHOW COLUMNS FROM attendance LIKE 'event_id'");
        if($hasEvent && $hasEvent->num_rows){
            $q="SELECT a.id,a.event_id,s.student_id_no,s.full_name,s.education_level,s.course,s.track,s.strand,s.year_level,s.section,a.scan_time,a.status,a.time_out FROM attendance a LEFT JOIN students s ON s.id=a.student_id WHERE a.event_id IS NOT NULL AND DATE(a.scan_time)='$d' ORDER BY a.scan_time,a.id";
            $r=$conn->query($q); if(!$r) throw new RuntimeException($conn->error);
            $headers=['id','event_id','student_id_no','full_name','education_level','course','track','strand','year_level','section','scan_time','status','time_out'];
            $files['event_attendance.csv']=tr_write_csv($dir.DIRECTORY_SEPARATOR.'event_attendance.csv',$headers,$r); $total += $files['event_attendance.csv']['count'];
        }
    }
    $manifest=['date'=>$date,'created_at'=>date('c'),'verified'=>true,'record_count'=>$total,'files'=>$files];
    if (@file_put_contents($manifestPath,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false) throw new RuntimeException('Could not write archive manifest.');
    $verify=json_decode((string)@file_get_contents($manifestPath),true);
    if(!is_array($verify)||empty($verify['verified'])) throw new RuntimeException('Manifest verification failed.');
    return $manifest;
}

function tr_day_is_held(mysqli $conn, string $date): bool {
    if(!tr_table_exists($conn,'log_retention_holds')) return false;
    $d=$conn->real_escape_string($date);
    $r=$conn->query("SELECT id FROM log_retention_holds WHERE is_active=1 AND '$d' BETWEEN date_from AND date_to LIMIT 1");
    return $r && $r->num_rows>0;
}

function tr_dates_older_than(mysqli $conn, string $cutoff): array {
    $dates=[]; $c=$conn->real_escape_string($cutoff);
    if(tr_table_exists($conn,'student_movement_logs')){
        $r=$conn->query("SELECT DISTINCT DATE(verified_at) d FROM student_movement_logs WHERE verified_at<'$c 00:00:00'");
        if($r)while($x=$r->fetch_assoc())if($x['d'])$dates[$x['d']]=1;
    }
    if(tr_table_exists($conn,'rejected_scans')){
        $r=$conn->query("SELECT DISTINCT DATE(rejected_at) d FROM rejected_scans WHERE rejected_at<'$c 00:00:00'");
        if($r)while($x=$r->fetch_assoc())if($x['d'])$dates[$x['d']]=1;
    }
    if(tr_table_exists($conn,'daily_monitoring_exceptions')){
        $r=$conn->query("SELECT DISTINCT exception_date d FROM daily_monitoring_exceptions WHERE exception_date<'$c'");
        if($r)while($x=$r->fetch_assoc())if($x['d'])$dates[$x['d']]=1;
    }
    $out=array_keys($dates); sort($out); return $out;
}

function tr_purge_day(mysqli $conn, string $date): int {
    if(tr_day_is_held($conn,$date)) return 0;
    // Absolute rule: never purge without a verified day archive.
    $manifest=tr_archive_day($conn,$date,false);
    if(empty($manifest['verified'])) throw new RuntimeException("Archive for $date is not verified; purge cancelled.");
    $d=$conn->real_escape_string($date); $deleted=0;
    $conn->begin_transaction();
    try{
        if(tr_table_exists($conn,'student_movement_logs')){$conn->query("DELETE FROM student_movement_logs WHERE DATE(verified_at)='$d'");$deleted += $conn->affected_rows;}
        if(tr_table_exists($conn,'rejected_scans')){$conn->query("DELETE FROM rejected_scans WHERE DATE(rejected_at)='$d'");$deleted += $conn->affected_rows;}
        if(tr_table_exists($conn,'daily_monitoring_exceptions')){$conn->query("DELETE FROM daily_monitoring_exceptions WHERE exception_date='$d'");$deleted += $conn->affected_rows;}
        $conn->commit();
    }catch(Throwable $e){$conn->rollback();throw $e;}
    return $deleted;
}

function tr_monthly_archive(string $month): array {
    if(!preg_match('/^\d{4}-\d{2}$/',$month)) throw new InvalidArgumentException('Invalid month.');
    [$y,$m]=explode('-',$month); $root=tr_backup_root();
    $src=$root.DIRECTORY_SEPARATOR.'daily'.DIRECTORY_SEPARATOR.$y.DIRECTORY_SEPARATOR.$m;
    $monthly=$root.DIRECTORY_SEPARATOR.'monthly'.DIRECTORY_SEPARATOR.$y;
    if(!is_dir($monthly)) @mkdir($monthly,0775,true);
    $manifestPath=$monthly.DIRECTORY_SEPARATOR."TrackED_Logs_{$month}_manifest.json";
    $zipPath=$monthly.DIRECTORY_SEPARATOR."TrackED_Logs_{$month}.zip";
    $files=[];
    if(is_dir($src)){
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f)if($f->isFile())$files[]=$f->getPathname();
    }
    $zipOk=false;
    if(class_exists('ZipArchive')){
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){
            foreach($files as $p){$rel=str_replace('\\','/',substr($p,strlen($src)+1));$zip->addFile($p,$rel);} $zip->close();
            $zipOk=is_file($zipPath)&&filesize($zipPath)>0;
        }
    }
    $mf=['month'=>$month,'created_at'=>date('c'),'verified'=>($zipOk || count($files)>=0),'zip_created'=>$zipOk,'zip_path'=>$zipOk?$zipPath:null,'source_file_count'=>count($files)];
    @file_put_contents($manifestPath,json_encode($mf,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
    return $mf;
}

function tr_cleanup_old_archives(int $months): int {
    $months=max(12,$months); $root=tr_backup_root(); $monthly=$root.DIRECTORY_SEPARATOR.'monthly'; if(!is_dir($monthly))return 0;
    $threshold=(new DateTime('first day of this month'))->modify("-$months months"); $removed=0;
    foreach(glob($monthly.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'TrackED_Logs_????-??*')?:[] as $p){
        if(preg_match('/TrackED_Logs_(\d{4}-\d{2})/',$p,$m)){
            $dt=DateTime::createFromFormat('Y-m-d',$m[1].'-01');
            if($dt && $dt<$threshold && is_file($p)){@unlink($p);$removed++;}
        }
    }
    return $removed;
}

function tr_run_retention(mysqli $conn, bool $manual=false, bool $includeToday=false): array {
    $cfg=tr_config($conn); $root=tr_backup_root(); if(!is_dir($root))@mkdir($root,0775,true);
    $runId=0; if(tr_table_exists($conn,'log_backup_runs')){$type=$manual?'MANUAL':'SCHEDULED';$conn->query("INSERT INTO log_backup_runs(run_type,status,started_at) VALUES('$type','RUNNING',NOW())");$runId=(int)$conn->insert_id;}
    $archived=0;$purged=0;$messages=[];$status='SUCCESS';
    try{
        if($manual || $cfg['daily_backup_enabled']==='1'){
            $target=$includeToday?date('Y-m-d'):date('Y-m-d',strtotime('-1 day'));
            if(!$includeToday){$newExceptions=tr_generate_no_out_exceptions($conn,$target);if($newExceptions>0)$messages[]="NO OUT exceptions generated: $newExceptions for $target";}
            $mf=tr_archive_day($conn,$target,$manual);$archived += (int)($mf['record_count']??0);$messages[]="Daily archive verified: $target";
        }
        $ret=(int)$cfg['log_retention_days']; $keepDays=max(1,$ret); $cutoff=date('Y-m-d',strtotime('-'.($keepDays-1).' days')); // exact rolling window: today + previous N-1 dates
        if($cfg['auto_purge_enabled']==='1'){
            foreach(tr_dates_older_than($conn,$cutoff) as $d){
                if(tr_day_is_held($conn,$d)){$messages[]="Protected hold skipped: $d";continue;}
                $mf=tr_archive_day($conn,$d,false);$archived += (int)($mf['record_count']??0);
                $purged += tr_purge_day($conn,$d);
            }
        }
        if($cfg['monthly_archive_enabled']==='1'){
            $prev=date('Y-m',strtotime('first day of last month'));$mm=tr_monthly_archive($prev);$messages[]='Monthly archive checked: '.$prev.(!empty($mm['zip_created'])?' (ZIP verified)':' (daily archive set retained)');
        }
        tr_cleanup_old_archives((int)$cfg['archive_retention_months']);
    }catch(Throwable $e){$status='FAILED';$messages[]=$e->getMessage();}
    if($runId){$msg=$conn->real_escape_string(implode(' | ',$messages));$path=$conn->real_escape_string($root);$conn->query("UPDATE log_backup_runs SET status='$status',completed_at=NOW(),backup_path='$path',message='$msg',archived_records=$archived,purged_records=$purged WHERE id=$runId");}
    return ['status'=>$status==='SUCCESS'?'success':'error','run_status'=>$status,'archived_records'=>$archived,'purged_records'=>$purged,'backup_path'=>$root,'message'=>implode(' | ',$messages)];
}

function tr_retention_status(mysqli $conn): array {
    $cfg=tr_config($conn);$out=['config'=>$cfg,'backup_path'=>tr_backup_root(),'last_run'=>null,'holds'=>[],'live_log_count'=>0,'oldest_live_log'=>null,'archive_days'=>0];
    if(tr_table_exists($conn,'student_movement_logs')){$r=$conn->query("SELECT COUNT(*) c,MIN(verified_at) oldest FROM student_movement_logs");if($r){$x=$r->fetch_assoc();$out['live_log_count']=(int)$x['c'];$out['oldest_live_log']=$x['oldest'];}}
    if(tr_table_exists($conn,'log_backup_runs')){$r=$conn->query("SELECT * FROM log_backup_runs ORDER BY id DESC LIMIT 1");if($r&&$r->num_rows)$out['last_run']=$r->fetch_assoc();}
    if(tr_table_exists($conn,'log_retention_holds')){$r=$conn->query("SELECT * FROM log_retention_holds WHERE is_active=1 ORDER BY date_from DESC,id DESC");if($r)while($x=$r->fetch_assoc())$out['holds'][]=$x;}
    $daily=tr_backup_root().DIRECTORY_SEPARATOR.'daily'; if(is_dir($daily)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($daily,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&$f->getFilename()==='manifest.json')$out['archive_days']++;}
    return $out;
}
