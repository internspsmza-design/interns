<?php
require_once __DIR__.'/session.php';
require_once __DIR__.'/db.php';

function require_login() {
  if (empty($_SESSION['user'])) {
    $base = (require __DIR__.'/config.php')['APP_BASE'];
    header("Location: {$base}/auth/login.php");
    exit;
  }
}
function require_role(array $roles) {
  require_login();
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, $roles, true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}
