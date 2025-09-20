<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__.'/../app/auth.php';  require_role(['lecturer']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function table_exists(PDO $pdo, string $t): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $st->execute([$t]);
  return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo, string $t, string $c): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $st->execute([$t, $c]);
  return (bool)$st->fetchColumn();
}
function assignment_columns(PDO $pdo): array {
  $check = function(array $cols) use ($pdo): bool {
    $in  = implode(',', array_fill(0, count($cols), '?'));
    $sql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'student_assignments' AND column_name IN ($in)";
    $st  = $pdo->prepare($sql);
    $st->execute($cols);
    return (int)$st->fetchColumn() === count($cols);
  };
  if ($check(['student_user_id','lecturer_user_id'])) {
    return ['student' => 'student_user_id', 'reviewer' => 'lecturer_user_id'];
  }
  if ($check(['student_id','lecturer_id'])) {
    return ['student' => 'student_id', 'reviewer' => 'lecturer_id'];
  }
  return ['student' => 'student_user_id', 'reviewer' => 'lecturer_user_id'];
}

$tbl      = 'weekly_reports';
$hasTable = table_exists($pdo, $tbl);
$me       = (int)($_SESSION['user']['id'] ?? 0);
$msg = '';
$err = '';

$assignmentJoin = '';
$assignmentCond = '';
if (table_exists($pdo, 'student_assignments')) {
  $cols = assignment_columns($pdo);
  $assignmentJoin = "JOIN student_assignments sa ON sa.`{$cols['student']}` = w.user_id";
  $assignmentCond = "sa.`{$cols['reviewer']}` = ?";
}

$imgStmt = null;
$imgFk   = null;
if (table_exists($pdo, 'weekly_images')) {
  foreach (['weekly_id','weekly_report_id'] as $cand) {
    if (col_exists($pdo, 'weekly_images', $cand)) { $imgFk = $cand; break; }
  }
  if ($imgFk && col_exists($pdo, 'weekly_images', 'path')) {
    $imgStmt = $pdo->prepare("SELECT path FROM `weekly_images` WHERE `$imgFk`=? ORDER BY id");
  }
}

$dateExpr = "COALESCE(w.week_start, w.report_date, w.created_at)";
$weekCol  = null;
if (col_exists($pdo, $tbl, 'week_no'))      $weekCol = 'week_no';
elseif (col_exists($pdo, $tbl, 'week'))     $weekCol = 'week';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$hasTable) {
    $err = 'weekly_reports table not found.';
  } else {
    $id      = (int)($_POST['id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    if (!$id || !in_array($action, ['approved','rejected'], true)) {
      $err = 'Invalid request.';
    } else {
      $checkSql = "SELECT w.id FROM `$tbl` w" . ($assignmentJoin ? " $assignmentJoin" : '') . " WHERE w.id = ?";
      $args = [$id];
      if ($assignmentCond) { $checkSql .= " AND $assignmentCond"; $args[] = $me; }
      $chk = $pdo->prepare($checkSql);
      $chk->execute($args);
      if (!$chk->fetchColumn()) {
        $err = 'Report not found or not assigned to you.';
      } else {
        $newStatus = ($action === 'approved') ? 'approved' : 'rejected';
        $set = [];
        $vals = [];
        if (col_exists($pdo, $tbl, 'status'))            { $set[] = '`status`=?';            $vals[] = $newStatus; }
        if (col_exists($pdo, $tbl, 'lecturer_comment'))  { $set[] = '`lecturer_comment`=?';  $vals[] = $comment; }
        if (col_exists($pdo, $tbl, 'reviewer_comment'))  { $set[] = '`reviewer_comment`=?';  $vals[] = $comment; }
        if (col_exists($pdo, $tbl, 'lecturer_id'))       { $set[] = '`lecturer_id`=?';       $vals[] = $me; }
        elseif (col_exists($pdo, $tbl, 'reviewer_id'))   { $set[] = '`reviewer_id`=?';       $vals[] = $me; }
        if (col_exists($pdo, $tbl, 'lecturer_signed_at')) {
          if ($action === 'approved') { $set[] = '`lecturer_signed_at`=?'; $vals[] = date('Y-m-d H:i:s'); }
          else { $set[] = '`lecturer_signed_at`=NULL'; }
        }
        if (col_exists($pdo, $tbl, 'updated_at')) { $set[] = '`updated_at`=NOW()'; }
        if (!$set) {
          $err = 'No writable columns available for weekly reports.';
        } else {
          $vals[] = $id;
          $sql = "UPDATE `$tbl` SET " . implode(', ', $set) . " WHERE id=?";
          $pdo->prepare($sql)->execute($vals);
          $msg = $newStatus === 'approved' ? 'Weekly report approved.' : 'Weekly report rejected.';
        }
      }
    }
  }
}

$filter = $_GET['status'] ?? 'submitted';
$allowedFilters = ['submitted','approved','rejected','signed','all'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'submitted';
$q = trim($_GET['q'] ?? '');

$rows = [];
if ($hasTable) {
  $where = [];
  $args  = [];
  if ($assignmentCond) { $where[] = $assignmentCond; $args[] = $me; }
  if ($q !== '') { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
  if ($filter !== 'all' && col_exists($pdo, $tbl, 'status')) { $where[] = "w.`status` = ?"; $args[] = $filter; }
  $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  $sql = "
    SELECT
      w.*,
      u.name,
      u.email,
      $dateExpr AS week_val,
      COALESCE(w.activities_summary, w.activity_summary, w.highlights, '') AS summary_text
    FROM `$tbl` w
    JOIN users u ON u.id = w.user_id
    " . ($assignmentJoin ? " $assignmentJoin" : '') . "
    $sqlWhere
    ORDER BY $dateExpr DESC, w.id DESC
    LIMIT 200
  ";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__.'/../app/header.php';
?>
<h2>Verify Weekly Reports (Lecturer)</h2>

<?php if($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if($err): ?><p class="error"><?= h($err) ?></p><?php endif; ?>

<form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
  <input name="q" value="<?= h($q) ?>" placeholder="Search student">
  <select name="status">
    <?php foreach(['submitted'=>'Submitted','approved'=>'Approved','rejected'=>'Rejected','signed'=>'Signed','all'=>'All'] as $val=>$label): ?>
      <option value="<?= h($val) ?>" <?= $filter===$val?'selected':'' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn">Filter</button>
</form>

<?php if(!$hasTable): ?>
  <p>weekly_reports table not found.</p>
<?php elseif(!$rows): ?>
  <p>No weekly reports found.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Week</th>
        <th>Student</th>
        <th>Summary &amp; Comments</th>
        <th>Images</th>
        <th>Status</th>
        <th>Lecturer Review</th>
        <th>PDF</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <?php
        $weekStart = array_key_exists('week_start', $r) ? $r['week_start'] : null;
        $weekEnd   = array_key_exists('week_end', $r)   ? $r['week_end']   : null;
        $weekDisp  = '';
        if ($weekStart || $weekEnd) {
          $parts = [];
          if ($weekStart) $parts[] = $weekStart;
          if ($weekEnd)   $parts[] = $weekEnd;
          $weekDisp = implode(' → ', $parts);
        } else {
          $weekDisp = $r['week_val'] ?? '';
        }
        $summary = trim((string)($r['summary_text'] ?? ''));
        $supComment = trim((string)($r['supervisor_comment'] ?? ''));
        $lecComment = trim((string)($r['lecturer_comment'] ?? ''));
        $statusVal  = $r['status'] ?? '';
        $imgs = [];
        if ($imgStmt) {
          $imgStmt->execute([$r['id']]);
          $imgs = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $canReview = in_array($statusVal, ['submitted','rejected',''], true);
      ?>
      <tr>
        <td>
          <?= h($weekDisp) ?>
          <?php if($weekCol && isset($r[$weekCol]) && $r[$weekCol] !== null && $r[$weekCol] !== ''): ?>
            <div><small>Week <?= h((string)$r[$weekCol]) ?></small></div>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= h($r['name'] ?? '') ?></strong><br>
          <small><?= h($r['email'] ?? '') ?></small>
        </td>
        <td style="max-width:360px">
          <?php if($summary !== ''): ?>
            <div><?= nl2br(h($summary)) ?></div>
          <?php else: ?>
            <em>No summary provided.</em>
          <?php endif; ?>
          <?php if($supComment !== ''): ?>
            <div style="margin-top:.35rem"><small><strong>Supervisor:</strong> <?= nl2br(h($supComment)) ?></small></div>
          <?php endif; ?>
          <?php if(!$canReview && $lecComment !== ''): ?>
            <div style="margin-top:.35rem"><small><strong>Your comment:</strong> <?= nl2br(h($lecComment)) ?></small></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if($imgs): ?>
            <div style="display:flex;gap:.25rem;flex-wrap:wrap">
              <?php foreach($imgs as $p): ?>
                <a href="<?= url($p) ?>" target="_blank"><img src="<?= url($p) ?>" alt="" style="height:48px;width:72px;object-fit:cover;border-radius:.3rem"></a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <em>—</em>
          <?php endif; ?>
        </td>
        <td>
          <?php if($statusVal !== ''): ?>
            <span class="status <?= h(strtolower($statusVal)) ?>"><?= h($statusVal) ?></span>
          <?php else: ?>
            <em>—</em>
          <?php endif; ?>
          <?php if(!empty($r['lecturer_signed_at'])): ?>
            <div><small>Signed: <?= h($r['lecturer_signed_at']) ?></small></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if($canReview): ?>
            <form method="post" style="display:flex;flex-direction:column;gap:.35rem">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <textarea name="comment" rows="2" placeholder="Comment" style="min-width:220px;resize:vertical"><?= h($lecComment) ?></textarea>
              <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                <button class="btn small" name="action" value="approved">Approve</button>
                <button class="btn small" name="action" value="rejected">Reject</button>
              </div>
            </form>
          <?php else: ?>
            <?php if($lecComment !== ''): ?>
              <div><?= nl2br(h($lecComment)) ?></div>
            <?php else: ?>
              <em>No lecturer comment.</em>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td><a class="btn small" target="_blank" href="<?= url('/student/weekly_pdf.php?id='.(int)$r['id']) ?>">PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

</main></body></html>
