<?php
declare(strict_types=1);
ini_set('display_errors', '1'); ini_set('display_startup_errors', '1'); error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) ? $config : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';
require_role(['admin']);

function table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function col_exists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $st->execute([$table, $col]);
    return (bool)$st->fetchColumn();
}

function pick_table(PDO $pdo, array $candidates, string $fallback): string {
    foreach ($candidates as $name) {
        if (table_exists($pdo, $name)) {
            return $name;
        }
    }
    return $fallback;
}

$leaveTable = pick_table($pdo, ['leaves', 'leave_requests'], 'leaves');
$fileTable  = pick_table($pdo, ['leaves_files', 'leave_files'], 'leaves_files');

$userFk = 'user_id';
if (!col_exists($pdo, $leaveTable, $userFk) && col_exists($pdo, $leaveTable, 'student_id')) {
    $userFk = 'student_id';
}

$hasStatus   = col_exists($pdo, $leaveTable, 'status');
$hasComment  = col_exists($pdo, $leaveTable, 'reviewer_comment');
$hasReviewer = col_exists($pdo, $leaveTable, 'reviewer_id');
$hasReviewed = col_exists($pdo, $leaveTable, 'reviewed_at');
$statuses    = ['pending', 'approved', 'rejected'];

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    header('Location: leaves_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status  = $_POST['status'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $sets = [];
    $vals = [];

    if ($hasStatus && in_array($status, $statuses, true)) {
        $sets[] = 'status = ?';
        $vals[] = $status;
    }

    if ($hasComment) {
        $sets[] = 'reviewer_comment = ?';
        $vals[] = $comment;
    }

    if ($hasReviewer && $sets) {
        $sets[] = 'reviewer_id = ?';
        $vals[] = $_SESSION['user']['id'];
    }

    if ($hasReviewed && $sets) {
        $sets[] = 'reviewed_at = ?';
        $vals[] = date('Y-m-d H:i:s');
    }

    if ($sets) {
        $vals[] = $id;
        $sql = "UPDATE `$leaveTable` SET " . implode(',', $sets) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($vals);
    }

    header('Location: verify_leaves.php?id=' . $id . '&saved=1');
    exit;
}

$st = $pdo->prepare("SELECT l.*, u.name AS student_name, u.email FROM `$leaveTable` l JOIN users u ON u.id = l.`$userFk` WHERE l.id = ? LIMIT 1");
$st->execute([$id]);
$leave = $st->fetch(PDO::FETCH_ASSOC);

$files = [];
if ($fileTable && table_exists($pdo, $fileTable)) {
    $fk = null;
    if (col_exists($pdo, $fileTable, 'leave_id')) {
        $fk = 'leave_id';
    } elseif (col_exists($pdo, $fileTable, 'leaves_id')) {
        $fk = 'leaves_id';
    }

    if ($fk) {
        $fSt = $pdo->prepare("SELECT path, original_name FROM `$fileTable` WHERE `$fk` = ? ORDER BY id");
        $fSt->execute([$id]);
        $files = $fSt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$reviewerName = null;
if ($hasReviewer && !empty($leave['reviewer_id'])) {
    $who = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $who->execute([(int)$leave['reviewer_id']]);
    $reviewerName = $who->fetchColumn() ?: null;
}

function leave_file_url(string $path): ?string {
    $clean = str_replace('\\', '/', $path);
    if (strpos($clean, '/uploads/') !== false) {
        $pos = strpos($clean, '/uploads/');
        return substr($clean, $pos);
    }
    if (strpos($clean, 'uploads/') === 0) {
        return '/' . $clean;
    }
    if ($clean && $clean[0] === '/') {
        return $clean;
    }
    return null;
}

include __DIR__ . '/../app/header.php';
?>
<h1>Leave Request Review</h1>
<p><a class="btn ghost" href="<?= url('/admin/leaves_list.php') ?>">← Back to Leaves</a></p>

<?php if (!$leave): ?>
  <div class="error">Leave request not found.</div>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?>
    <div class="ok">Review updated.</div>
  <?php endif; ?>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Student</h2>
    <p><strong><?= h($leave['student_name'] ?? '') ?></strong><br>
       <small><?= h($leave['email'] ?? '') ?></small></p>
  </section>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Leave Details</h2>
    <dl class="detail-grid" style="display:grid;grid-template-columns:180px 1fr;gap:.35rem 1rem;">
      <?php
        $fieldMap = [
          'date_from'  => 'Start Date',
          'date_to'    => 'End Date',
          'leave_date' => 'Leave Date',
          'days'       => 'Days',
          'reason'     => 'Reason',
          'status'     => 'Current Status',
        ];
        foreach ($fieldMap as $key => $label):
          if (!array_key_exists($key, $leave) || $leave[$key] === null || $leave[$key] === '') continue;
      ?>
        <dt style="font-weight:600;"><?= h($label) ?></dt>
        <dd style="margin:0 0 .35rem;white-space:pre-wrap;">
          <?= nl2br(h((string)$leave[$key])) ?>
        </dd>
      <?php endforeach; ?>
    </dl>

    <?php if ($files): ?>
      <div style="margin-top:1rem;display:flex;flex-direction:column;gap:.35rem;">
        <?php foreach ($files as $file): $web = $file['path'] ? leave_file_url($file['path']) : null; ?>
          <?php if ($web): $href = url($web); ?>
            <a href="<?= h($href) ?>" target="_blank" rel="noopener" class="btn ghost" style="width:fit-content;">
              <?= h($file['original_name'] ?: basename($web)) ?>
            </a>
          <?php else: ?>
            <span><?= h($file['original_name'] ?: basename((string)$file['path'])) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Admin Review</h2>
    <?php if ($reviewerName): ?>
      <p><strong>Last reviewed by:</strong> <?= h($reviewerName) ?></p>
    <?php endif; ?>
    <?php if ($hasReviewed && !empty($leave['reviewed_at'])): ?>
      <p><strong>Reviewed at:</strong> <?= h($leave['reviewed_at']) ?></p>
    <?php endif; ?>

    <?php if ($hasComment && !empty($leave['reviewer_comment'])): ?>
      <p><strong>Existing comment:</strong><br><?= nl2br(h($leave['reviewer_comment'])) ?></p>
    <?php endif; ?>

    <?php if ($hasStatus || $hasComment): ?>
      <form method="post" class="stack" style="display:grid;gap:.75rem;margin-top:1rem;">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php if ($hasStatus): ?>
          <label>Status
            <select name="status" required>
              <?php foreach ($statuses as $state): ?>
                <option value="<?= h($state) ?>" <?= ($leave['status'] ?? '') === $state ? 'selected' : '' ?>><?= ucfirst($state) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>

        <?php if ($hasComment): ?>
          <label>Comment
            <textarea name="comment" rows="4" placeholder="Add notes for the student or supervisors."><?= h($leave['reviewer_comment'] ?? '') ?></textarea>
          </label>
        <?php endif; ?>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button class="btn">Save review</button>
          <a class="btn ghost" href="<?= url('/admin/leaves_list.php') ?>">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <p class="error">This leave table does not have status/comment columns to update.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

</main></body></html>
