<?php
declare(strict_types=1);
require_once __DIR__.'/../app/init.php';
require_once __DIR__.'/../app/db.php';
require_role(['supervisor']);

$me = (int)($_SESSION['user']['id'] ?? 0);

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS student_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  lecturer_id INT,
  supervisor_id INT,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB");

// Action
if (isset($_POST['act'], $_POST['id'])) {
  $id = (int)$_POST['id'];
  $act = $_POST['act']==='approve' ? 'approved' : 'rejected';
  $st = $pdo->prepare("UPDATE student_assignments SET status=? WHERE id=? AND supervisor_id=?");
  $st->execute([$act, $id, $me]);
  header('Location: '.url('/supervisor/assignments.php')); exit;
}

// Pending list
$rows = $pdo->prepare("SELECT sa.id, u.name, u.email
                       FROM student_assignments sa
                       JOIN users u ON u.id=sa.student_id
                       WHERE sa.supervisor_id=? AND sa.status='pending'
                       ORDER BY sa.created_at DESC");
$rows->execute([$me]);
$rows = $rows->fetchAll(PDO::FETCH_ASSOC);

include __DIR__.'/../app/header.php';
?>
<main class="container">
  <h2>Pending Assignment Requests</h2>
  <div class="card">
    <table class="table">
      <thead><tr><th>Student</th><th>Email</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small" name="act" value="approve">Approve</button>
                <button class="btn small" name="act" value="reject">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="3">No pending requests.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:14px"><a class="btn ghost" href="<?= url('/supervisor/dashboard.php') ?>">Back</a></p>
</main>
</body></html>
