<?php
// lecturer/verify_leaves.php (resilient)
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['lecturer']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

ini_set('display_errors','1'); error_reporting(E_ALL);

function col_exists(PDO $pdo, string $table, string $col): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$table,$col]); return (bool)$st->fetchColumn();
}

// What columns do we actually have?
$hasStatus   = col_exists($pdo,'leaves','status');
$hasRevId    = col_exists($pdo,'leaves','reviewer_id');
$hasRevCmt   = col_exists($pdo,'leaves','reviewer_comment');
$hasDateCol  = col_exists($pdo,'leaves','leave_date');
$hasDateAlt  = col_exists($pdo,'leaves','date');
$dateCol     = $hasDateCol ? 'leave_date' : ($hasDateAlt ? 'date' : 'created_at');

// Handle POST (approve/reject) only if status column exists
if ($hasStatus && $_SERVER['REQUEST_METHOD']==='POST') {
  $id  = (int)($_POST['id'] ?? 0);
  $act = $_POST['action'] ?? '';
  $cmt = trim($_POST['comment'] ?? '');
  if ($id && in_array($act, ['approved','rejected'], true)) {
    // build dynamic update
    $sets = ["status=?"];
    $vals = [$act];
    if ($hasRevId)  { $sets[]="reviewer_id=?";      $vals[]=$_SESSION['user']['id']; }
    if ($hasRevCmt) { $sets[]="reviewer_comment=?"; $vals[]=$cmt; }
    $vals[] = $id;
    $sql = "UPDATE leaves SET ".implode(',', $sets)." WHERE id=?";
    $st  = $pdo->prepare($sql); $st->execute($vals);
  }
}

// Build list query
$filter   = $hasStatus ? ($_GET['status'] ?? 'pending') : 'all';
$whereSql = $hasStatus && $filter!=='all' ? "WHERE l.status = :status" : "";
$sql = "SELECT l.*, u.name 
        FROM leaves l 
        JOIN users u ON u.id=l.user_id 
        $whereSql
        ORDER BY l.$dateCol DESC, l.id DESC";
$st = $pdo->prepare($sql);
if ($whereSql) $st->bindValue(':status',$filter);
$st->execute();
$rows = $st->fetchAll();

include __DIR__.'/../app/header.php';
?>
<h2>Verify Leave Requests (Lecturer)</h2>

<?php if(!$hasStatus): ?>
  <p class="error" style="white-space:pre-line">
    Approve/Reject is disabled because your <code>leaves</code> table has no <code>status</code> column.
    Add it with:
    ALTER TABLE leaves
      ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending',
      ADD COLUMN reviewer_id INT NULL,
      ADD COLUMN reviewer_comment TEXT NULL;
  </p>
<?php endif; ?>

<form method="get" style="margin:.5rem 0">
  <?php if($hasStatus): ?>
    <label>Status
      <select name="status" onchange="this.form.submit()">
        <option value="pending"  <?= $filter==='pending'?'selected':'' ?>>Pending</option>
        <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
        <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
        <option value="all"      <?= $filter==='all'?'selected':'' ?>>All</option>
      </select>
    </label>
  <?php else: ?>
    <em>Filtering requires a <code>status</code> column.</em>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <p>No leave requests found.</p>
<?php else: ?>
  <table class="tbl">
    <tr>
      <th><?= h(ucwords(str_replace('_',' ',$dateCol))) ?></th>
      <th>Student</th>
      <th>Reason</th>
      <?php if($hasStatus): ?><th>Status</th><?php endif; ?>
      <th>Review</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r[$dateCol] ?? '') ?></td>
        <td><?= h($r['name'] ?? '') ?></td>
        <td style="max-width:420px"><?= nl2br(h($r['reason'] ?? '')) ?></td>
        <?php if($hasStatus): ?><td><?= h($r['status']) ?></td><?php endif; ?>
        <td>
          <?php if($hasStatus && ($r['status'] ?? 'pending')==='pending'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="comment" placeholder="comment" style="width:180px">
              <button name="action" value="approved">Approve</button>
              <button name="action" value="rejected">Reject</button>
            </form>
          <?php else: ?>
            <?php if($hasRevCmt && !empty($r['reviewer_comment'])): ?>
              <small><?= nl2br(h($r['reviewer_comment'])) ?></small>
            <?php else: ?>
              <em>—</em>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

</main></body></html>
