<?php
require_once __DIR__.'/../app/auth.php'; require_role(['lecturer']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)$_POST['id'];
  $act=$_POST['action'] ?? '';
  $cmt=trim($_POST['comment'] ?? '');
  if(in_array($act,['approved','rejected'])){
    $st=$pdo->prepare("UPDATE daily_logs SET status=?, reviewer_id=?, reviewer_comment=? WHERE id=?");
    $st->execute([$act, $_SESSION['user']['id'], $cmt, $id]);
  }
}
$rows=$pdo->query("SELECT d.*, u.name FROM daily_logs d JOIN users u ON u.id=d.user_id WHERE d.status IN ('submitted','rejected') ORDER BY d.log_date DESC")->fetchAll();
include __DIR__.'/../app/header.php';
?>
<h2>Verify Daily Logs</h2>
<table class="tbl">
<tr><th>Date</th><th>Student</th><th>Activity</th><th>Hours</th><th>Action</th></tr>
<?php foreach($rows as $r): ?>
<tr>
  <td><?= h($r['log_date']) ?></td>
  <td><?= h($r['name']) ?></td>
  <td><?= nl2br(h($r['activity'])) ?></td>
  <td><?= h($r['hours']) ?></td>
  <td>
    <form method="post">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input name="comment" placeholder="comment">
      <button name="action" value="approved">Approve</button>
      <button name="action" value="rejected">Reject</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</main></body></html>
