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

// Create table (safe if it already exists)
$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX token_hash_idx (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$sent = false;
$msg  = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email.';
  } else {
    $st = $pdo->prepare("SELECT id, name, email FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // Always act the same even if no user found (don’t reveal existence)
    if ($user) {
      $userId = (int)$user['id'];
      $raw = bin2hex(random_bytes(32)); // 64 hex chars
      $hash = hash('sha256', $raw);
      $expires = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

      $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)")
          ->execute([$userId, $hash, $expires]);

      // Build reset URL
      $resetUrl = url('/auth/reset.php?t=' . urlencode($raw));

      // Try to send email; if mail() not configured, show the link as fallback (dev mode)
      $subject = 'Password reset';
      $body = "Hi {$user['name']},\n\nUse this link to reset your password (valid for 30 minutes):\n{$resetUrl}\n\nIf you did not request this, you can ignore this email.";
      $mailed = @mail($user['email'], $subject, $body, "From: no-reply@localhost");

      if ($mailed) {
        $msg = 'If that email exists, a reset link has been sent.';
      } else {
        // Developer-friendly fallback
        $msg = 'If that email exists, a reset link is shown below (mail() not configured on this machine).';
        $devLink = $resetUrl;
      }
    } else {
      $msg = 'If that email exists, a reset link has been sent.';
    }
    $sent = true;
  }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot Password · INTERNS</title>
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body class="auth">
  <main class="container">
    <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:560px;margin:2rem auto">
      <h1 style="margin-top:0">Forgot password</h1>

      <?php if ($sent): ?>
        <div class="ok" style="margin-bottom:.75rem"><?= h($msg) ?></div>
        <?php if (!empty($devLink)): ?>
          <div class="card" style="background:var(--card);border:1px solid var(--line);padding:.75rem;border-radius:.5rem">
            <strong>Developer reset link:</strong><br>
            <a href="<?= h($devLink) ?>"><?= h($devLink) ?></a>
            <div style="font-size:.85rem;color:var(--muted);margin-top:.5rem">This is displayed because <code>mail()</code> is not configured locally.</div>
          </div>
        <?php endif; ?>
        <p style="margin-top:1rem"><a href="<?= url('/auth/login.php') ?>">Back to login</a></p>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="error-list" style="margin-bottom:.75rem">
            <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post">
          <label>Email
            <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
          </label>
          <button class="btn">Send reset link</button>
          <p style="margin-top:10px"><a href="<?= url('/auth/login.php') ?>">Back to login</a></p>
        </form>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
