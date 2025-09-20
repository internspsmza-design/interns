<?php
require_once __DIR__.'/../app/session.php';
$config = require __DIR__.'/../app/config.php';
$_SESSION=[]; session_destroy();
header("Location: {$config['APP_BASE']}/auth/login.php");
