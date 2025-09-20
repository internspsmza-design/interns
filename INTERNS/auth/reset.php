<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!function_exists('h')) {
  function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$token = (string)($_GET['t'] ?? '');
$valid = false; $error = ''; $done = false;
$user = null;

if ($token !== '' && ctype_xdigit($token) && strlen($token) === 64) {
  $hash = hash('sha256', $token);

  // Find matching, unused, unexpired reset record
  $st = $pdo->prepare("SELECT pr.id, pr.user_id, u.email, u.name
                       FROM password_resets pr
                       JOIN users u ON u.id = pr.user_id
                       WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()
                       ORDER BY pr.id DESC LIMIT 1");
  $st->execute([$hash]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) { $valid = true; $user = $row; }
} else {
  $error = 'Invalid or malformed token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
  $p1 = (string)($_POST['password'] ?? '');
  $p2 = (string)($_POST['password2'] ?? '');
  if (strlen($p1) < 6) { $error = 'Password must be at least 6 characters.'; }
  elseif ($p1 !== $p2) { $error = 'Passwords do not match.'; }
  else {
    $hashPwd = password_hash($p1, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashPwd, (int)$user['user_id']]);
    // Mark this reset token as used (one-time)
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([(int)$user['id']]);
    $done = true;
  }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password · INTERNS</title>
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body class="auth">
  <main class="container">
    <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:560px;margin:2rem auto">
      <h1 style="margin-top:0">Reset password</h1>

      <?php if ($done): ?>
        <div class="ok">Password updated. You can now <a href="<?= url('/auth/login.php') ?>">log in</a>.</div>
      <?php elseif (!$valid): ?>
        <div class="error"><?= h($error ?: 'Invalid or expired token.') ?></div>
        <p style="margin-top:10px"><a href="<?= url('/auth/forgot.php') ?>">Request a new link</a></p>
      <?php else: ?>
        <?php if ($error): ?><div class="error" style="margin-bottom:.75rem"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <label>New password
            <input type="password" name="password" required>
          </label>
          <label>Confirm new password
            <input type="password" name="password2" required>
          </label>
          <button class="btn">Update password</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
