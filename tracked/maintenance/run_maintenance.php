<?php
// TrackED scheduled maintenance entry point. Run via PHP CLI once daily.
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/log_retention_lib.php';
$host='localhost';$user='root';$pass='';$db='tracked_db';
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error){fwrite(STDERR,"TrackED maintenance DB connection failed: {$conn->connect_error}\n");exit(2);}
$result=tr_run_retention($conn,false,false);
echo '['.date('Y-m-d H:i:s').'] '.json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
$conn->close();
exit(($result['status']??'error')==='success'?0:1);
