<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/db.php';
require_role(['student']);

$uid = (int)($_SESSION['user']['id'] ?? 0);
$ok = false; $msg = ''; $errors = [];

// Ensure base table exists (safe if it does)
$pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  leave_date DATE,
  days INT DEFAULT 1,
  reason TEXT,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_date = trim($_POST['leave_date'] ?? '');
  $days       = (int)($_POST['days'] ?? 1);
  $reason     = trim($_POST['reason'] ?? '');

  if ($leave_date === '') $errors['leave_date'] = 'Leave date required.';
  if ($days <= 0)         $errors['days'] = 'Days must be positive.';

  if (!$errors) {
    $cols = ['user_id','leave_date','days','reason','status'];
    $vals = [$uid, $leave_date, $days, $reason, 'pending'];
    $pdo->prepare("INSERT INTO `leaves` (".implode(',',$cols).") VALUES (?,?,?,?,?)")->execute($vals);

    /* PATCH_LEAVE_FILES */
    $leaveId = (int)$pdo->lastInsertId();
    $upDir = __DIR__ . '/../uploads/leaves';
    if (!is_dir($upDir)) @mkdir($upDir, 0777, true);

    if (!empty($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
      // Ensure file table
      $pdo->exec("CREATE TABLE IF NOT EXISTS leaves_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        leave_id INT NOT NULL,
        path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (leave_id) REFERENCES leaves(id) ON DELETE CASCADE
      ) ENGINE=InnoDB");

      for ($i=0; $i<count($_FILES['evidence']['name']); $i++) {
        if (empty($_FILES['evidence']['name'][$i])) continue;
        $tmp  = $_FILES['evidence']['tmp_name'][$i] ?? '';
        $name = basename($_FILES['evidence']['name'][$i] ?? '');
        if (!$tmp) continue;
        $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
        $dest = $upDir.'/'.time().'_'.$safe;
        if (is_uploaded_file($tmp) && move_uploaded_file($tmp, $dest)) {
          $pdo->prepare("INSERT INTO leaves_files (leave_id,path,original_name) VALUES (?,?,?)")
              ->execute([$leaveId, $dest, $name]);
        }
      }
    }

    $ok = true;
    $msg = 'Leave request submitted.';
  }
}
include __DIR__ . '/../app/header.php';
?>
<main class="container">
  <h1>New Leave Application</h1>

  <?php if ($ok): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="error-list"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="grid">
      <label>Leave Date
        <input type="date" name="leave_date" value="<?= h($_POST['leave_date'] ?? '') ?>" required>
      </label>
      <label>Days
        <input type="number" name="days" min="1" value="<?= h($_POST['days'] ?? '1') ?>" required>
      </label>
    </div>

    <label>Upload evidence (images/files)
      <input type="file" name="evidence[]" multiple>
    </label>

    <label>Reason
      <textarea name="reason" rows="4"><?= h($_POST['reason'] ?? '') ?></textarea>
    </label>

    <button class="btn">Submit</button>
    <a class="btn ghost" href="<?= url('/student/leaves_list.php') ?>">Back</a>
  </form>
</main>
</body></html>
