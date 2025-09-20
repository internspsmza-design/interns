<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) ? $config : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';
require_role(['admin']);

function app_base(?array $cfg): string {
  if (defined('APP_BASE')) return rtrim((string)APP_BASE, '/');
  return rtrim((string)($cfg['APP_BASE'] ?? ''), '/');
}
$APP_BASE = app_base($config);

/* CSRF */
if (!function_exists('csrf_token')) {
  function csrf_token(): string { $_SESSION['_csrf']=$_SESSION['_csrf']??bin2hex(random_bytes(16)); return $_SESSION['_csrf']; }
}
if (!function_exists('csrf_check')) {
  function csrf_check(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
    if(!$ok){ http_response_code(400); exit('Bad CSRF'); }
  }
}

$roles = ['student','lecturer','supervisor','admin'];
$id    = (int)($_GET['id'] ?? 0);
$edit  = $id > 0;

$name=''; $email=''; $role='student'; $status=1;

if ($edit) {
  $st=$pdo->prepare("SELECT id,name,email,role,status FROM users WHERE id=?");
  $st->execute([$id]);
  $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); exit('User not found'); }
  $name=$row['name']; $email=$row['email']; $role=$row['role']; $status=(int)$row['status'];
}

$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role  = in_array($_POST['role'] ?? 'student',$roles,true) ? $_POST['role'] : 'student';
  $status= isset($_POST['status']) ? 1 : 0;
  $pwd   = (string)($_POST['password'] ?? '');

  if ($name==='' || $email==='') { $err='Name and Email are required.'; }
  if (!$err && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err='Invalid email.'; }

  if (!$err) {
    if ($edit) {
      $pdo->prepare("UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?")
          ->execute([$name,$email,$role,$status,$id]);
      if ($pwd!=='') {
        $hash=password_hash($pwd,PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$id]);
      }
    } else {
      if ($pwd==='') { $pwd = bin2hex(random_bytes(4)); } // fallback temp pass
      $hash=password_hash($pwd,PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users (name,email,password_hash,role,status,created_at) VALUES (?,?,?,?,?,NOW())")
          ->execute([$name,$email,$hash,$role,$status]);
    }
    header('Location: '.$APP_BASE.'/admin/users.php'); exit;
  }
}
$title = $edit ? 'Edit User' : 'New User';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1><?= h($title) ?></h1>
  <?php if($err): ?><div class="alert danger"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="form" style="max-width:560px;display:grid;gap:.75rem">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <label>Name
      <input name="name" required value="<?= h($name) ?>">
    </label>
    <label>Email
      <input type="email" name="email" required value="<?= h($email) ?>">
    </label>
    <label>Role
      <select name="role">
        <?php foreach($roles as $r): ?>
          <option value="<?= $r ?>" <?= $r===$role?'selected':'' ?>><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><input type="checkbox" name="status" value="1" <?= $status?'checked':''; ?>> Active</label>

    <fieldset class="mt" style="border:1px solid #ddd;padding:.75rem;border-radius:.5rem">
      <legend><?= $edit?'Set New Password (optional)':'Password' ?></legend>
      <input type="password" name="password" placeholder="<?= $edit?'Leave blank to keep current':'' ?>">
    </fieldset>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <button class="btn" type="submit">Save</button>
      <a class="btn secondary" href="<?= $APP_BASE ?>/admin/users.php">Cancel</a>
    </div>
  </form>
</div>
</body></html>
