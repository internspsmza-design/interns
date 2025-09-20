<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);

$PUBLIC_PAGE = true;

require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!function_exists('h')) {
  function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$errors = [];
$email  = '';

// figure out which password column is present
$uCols  = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
$uCols  = array_map('strtolower', $uCols);
$pwdCol = in_array('password_hash', $uCols, true) ? 'password_hash'
        : (in_array('password', $uCols, true) ? 'password' : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
  if ($password === '') $errors['password'] = 'Password is required.';
  if (!$pwdCol) $errors['login'] = 'Password column not found on users table.';

  if (!$errors) {
    $st = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $storedHash = $user[$pwdCol] ?? null;

    if (!$user || !$storedHash || !password_verify($password, (string)$storedHash)) {
      $errors['login'] = 'Incorrect email or password.';
    } else {
      // robust "active" detection for numeric or text status
      $active = true;
      if (array_key_exists('status', $user)) {
        $raw = $user['status'];
        if (is_numeric($raw)) {
          $active = ((int)$raw === 1);               // 1 = active
        } else {
          $v = strtolower(trim((string)$raw));
          $active = ($v === '' || $v === 'active' || $v === '1' || $v === 'enabled' || $v === 'approved' || $v === 'true');
        }
      }

      if (!$active) {
        $errors['login'] = 'Your account is not active.';
      } else {
        $_SESSION['user'] = [
          'id'    => (int)$user['id'],
          'name'  => (string)($user['name'] ?? ''),
          'email' => (string)($user['email'] ?? ''),
          'role'  => strtolower((string)($user['role'] ?? ''))
        ];
        $role = $_SESSION['user']['role'];
        $to = '/';
        if     ($role === 'student'    && file_exists(__DIR__.'/../student/dashboard.php'))    $to = '/student/dashboard.php';
        elseif ($role === 'lecturer'   && file_exists(__DIR__.'/../lecturer/dashboard.php'))   $to = '/lecturer/dashboard.php';
        elseif ($role === 'supervisor' && file_exists(__DIR__.'/../supervisor/dashboard.php')) $to = '/supervisor/dashboard.php';
        elseif ($role === 'admin'      && file_exists(__DIR__.'/../admin/dashboard.php'))      $to = '/admin/dashboard.php';
        elseif (file_exists(__DIR__.'/../index.php'))                                          $to = '/index.php';
        header('Location: '.url($to)); exit;
      }
    }
  }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login · INTERNS</title>
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body class="auth">
  <main class="container">
    <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:560px;margin:2rem auto">
      <h1 style="margin-top:0">Login</h1>

      <?php if ($errors): ?>
        <div class="error-list" style="margin-bottom:.75rem">
          <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <label>Email
          <input type="email" name="email" value="<?= h($email) ?>" required>
        </label>
        <label>Password
          <input type="password" name="password" required>
        </label>

        <div style="display:flex;justify-content:space-between;align-items:center;margin:.25rem 0 .75rem">
          <a href="<?= url('/auth/forgot.php') ?>">Forgot password?</a>
        </div>

        <button class="btn">Login</button>
        <p style="margin-top:10px">No account? <a href="<?= url('/auth/register.php') ?>">Register</a></p>
      </form>
    </div>
  </main>
</body>
</html>
