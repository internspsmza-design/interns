<?php
// lecturer/verify_weekly.php
require_once __DIR__.'/../app/auth.php';  require_role(['lecturer']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id  = (int)($_POST['id'] ?? 0);
  $act = $_POST['action'] ?? '';
  $cmt = trim($_POST['comment'] ?? '');
  if ($id && in_array($act, ['approved','rejected'], true)) {
    $st = $pdo->prepare("UPDATE weekly_reports SET status=?, reviewer_id=?, reviewer_comment=? WHERE id=?");
    $st->execute([$act, $_SESSION['user']['id'], $cmt, $id]);
  }
}

$filter = $_GET['status'] ?? 'submitted'; // submitted | approved | rejected | all
$where  = ($filter==='all') ? '' : 'WHERE wr.status = ?';
$sql    = "SELECT wr.*, u.name FROM weekly_reports wr JOIN users u ON u.id=wr.user_id $where ORDER BY wr.week_start DESC";
$st     = $pdo->prepare($sql);
($where ? $st->execute([$filter]) : $st->execute());
$rows   = $st->fetchAll();

$imgStmt = $pdo->prepare("SELECT path FROM weekly_images WHERE weekly_id=?");

include __DIR__.'/../app/header.php';
?>
<h2>Verify Weekly Reports (Lecturer)</h2>

<form method="get" style="margin:.5rem 0">
  <label>Status
    <select name="status" onchange="this.form.submit()">
      <option value="submitted" <?= $filter==='submitted'?'selected':'' ?>>Submitted</option>
      <option value="approved"  <?= $filter==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected"  <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="all"       <?= $filter==='all'?'selected':'' ?>>All</option>
    </select>
  </label>
</form>

<?php if (!$rows): ?>
  <p>No reports found.</p>
<?php else: ?>
  <table class="tbl">
    <tr>
      <th>Week</th>
      <th>Student</th>
      <th>Summary</th>
      <th>Images</th>
      <th>Status</th>
      <th>Review</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <?php $imgStmt->execute([$r['id']]); $imgs = $imgStmt->fetchAll(PDO::FETCH_COLUMN); ?>
      <tr>
        <td><?= h($r['week_start']) ?> → <?= h($r['week_end']) ?></td>
        <td><?= h($r['name']) ?></td>
        <td style="max-width:420px"><?= nl2br(h($r['summary'])) ?></td>
        <td>
          <?php if ($imgs): foreach ($imgs as $p): ?>
            <a href="<?= url($p) ?>" target="_blank">
              <img src="<?= url($p) ?>" alt="" style="height:50px;max-width:80px;object-fit:cover;margin-right:.25rem;border-radius:.25rem">
            </a>
          <?php endforeach; else: ?>
            <em>—</em>
          <?php endif; ?>
        </td>
        <td><?= h($r['status']) ?></td>
        <td>
          <?php if ($r['status']==='submitted'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="comment" placeholder="comment" style="width:180px">
              <button name="action" value="approved">Approve</button>
              <button name="action" value="rejected">Reject</button>
            </form>
          <?php else: ?>
            <small><?= h($r['reviewer_comment'] ?: '—') ?></small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</main></body></html>
