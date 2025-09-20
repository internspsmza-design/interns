<?php
require_once __DIR__.'/app/session.php';
$config = require __DIR__.'/app/config.php';
if(!empty($_SESSION['user'])){
  $r=$_SESSION['user']['role'];
  $to = [
    'student'=>"/student/dashboard.php",
    'lecturer'=>"/lecturer/dashboard.php",
    'supervisor'=>"/supervisor/dashboard.php",
    'admin'=>"/admin/dashboard.php",
  ][$r] ?? '/auth/login.php';
  header("Location: {$config['APP_BASE']}{$to}");
} else {
  header("Location: {$config['APP_BASE']}/auth/login.php");
}
