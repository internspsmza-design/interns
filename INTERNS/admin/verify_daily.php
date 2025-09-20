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
    return (bool) $st->fetchColumn();
}

function col_exists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
}

function pick_table(PDO $pdo, array $candidates, string $fallback): string {
    foreach ($candidates as $name) {
        if (table_exists($pdo, $name)) {
            return $name;
        }
    }
    return $fallback;
}

$dailyTable = pick_table($pdo, ['daily_logs', 'logs'], 'daily_logs');
$imageTable = pick_table($pdo, ['daily_images', 'log_images'], 'daily_images');
$dateColumn = col_exists($pdo, $dailyTable, 'log_date')
    ? 'log_date'
    : (col_exists($pdo, $dailyTable, 'date') ? 'date' : 'created_at');

$hasStatus   = col_exists($pdo, $dailyTable, 'status');
$hasComment  = col_exists($pdo, $dailyTable, 'reviewer_comment');
$hasReviewer = col_exists($pdo, $dailyTable, 'reviewer_id');
$hasReviewed = col_exists($pdo, $dailyTable, 'reviewed_at');
$statuses    = ['submitted', 'approved', 'rejected'];

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    header('Location: daily_list.php');
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
        $sql = "UPDATE `$dailyTable` SET " . implode(',', $sets) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($vals);
    }

    header('Location: verify_daily.php?id=' . $id . '&saved=1');
    exit;
}

$st = $pdo->prepare("SELECT d.*, u.name AS student_name, u.email FROM `$dailyTable` d JOIN users u ON u.id = d.user_id WHERE d.id = ? LIMIT 1");
$st->execute([$id]);
$log = $st->fetch(PDO::FETCH_ASSOC);

$imagePaths = [];
if ($imageTable && table_exists($pdo, $imageTable)) {
    $fk = null;
    if (col_exists($pdo, $imageTable, 'daily_id')) {
        $fk = 'daily_id';
    } elseif (col_exists($pdo, $imageTable, 'log_id')) {
        $fk = 'log_id';
    }

    if ($fk) {
        $imgSt = $pdo->prepare("SELECT path FROM `$imageTable` WHERE `$fk` = ? ORDER BY id");
        $imgSt->execute([$id]);
        $imagePaths = $imgSt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$reviewerName = null;
if ($hasReviewer && !empty($log['reviewer_id'])) {
    $who = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $who->execute([(int)$log['reviewer_id']]);
    $reviewerName = $who->fetchColumn() ?: null;
}

include __DIR__ . '/../app/header.php';
?>
<h1>Daily Log Review</h1>
<p><a class="btn ghost" href="<?= url('/admin/daily_list.php') ?>">← Back to Daily Logs</a></p>

<?php if (!$log): ?>
  <div class="error">Daily log not found.</div>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?>
    <div class="ok">Review updated.</div>
  <?php endif; ?>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Student</h2>
    <p><strong><?= h($log['student_name'] ?? '') ?></strong><br>
       <small><?= h($log['email'] ?? '') ?></small></p>
  </section>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Log Details</h2>
    <dl class="detail-grid" style="display:grid;grid-template-columns:160px 1fr;gap:.35rem 1rem;">
      <?php
        $fieldMap = [
          $dateColumn        => 'Date',
          'hari'             => 'Day',
          'tugas'            => 'Project / Activity',
          'activity'         => 'Activity',
          'objektif'         => 'Objective',
          'peralatan'        => 'Tools / Equipment',
          'prosedur'         => 'Procedure',
          'kesimpulan'       => 'Conclusion',
          'hours'            => 'Hours',
          'notes'            => 'Notes',
          'status'           => 'Current Status',
        ];
        foreach ($fieldMap as $key => $label):
          if (!array_key_exists($key, $log) || $log[$key] === null || $log[$key] === '') continue;
      ?>
        <dt style="font-weight:600;"><?= h($label) ?></dt>
        <dd style="margin:0 0 .35rem;white-space:pre-wrap;"><?= nl2br(h((string)$log[$key])) ?></dd>
      <?php endforeach; ?>
    </dl>

    <?php if ($imagePaths): ?>
      <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:.5rem;">
        <?php foreach ($imagePaths as $path): $full = url(strpos($path, '/uploads/') === 0 ? $path : ('/'.ltrim($path,'/'))); ?>
          <a href="<?= h($full) ?>" target="_blank" rel="noopener">
            <img src="<?= h($full) ?>" alt="Daily log attachment" style="height:90px;width:120px;object-fit:cover;border-radius:.4rem;box-shadow:0 1px 4px rgba(0,0,0,.12);">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" style="margin:1rem 0;padding:1rem;border:1px solid var(--line);border-radius:.75rem;">
    <h2 style="margin-top:0">Admin Review</h2>
    <?php if ($reviewerName): ?>
      <p><strong>Last reviewed by:</strong> <?= h($reviewerName) ?></p>
    <?php endif; ?>
    <?php if ($hasReviewed && !empty($log['reviewed_at'])): ?>
      <p><strong>Reviewed at:</strong> <?= h($log['reviewed_at']) ?></p>
    <?php endif; ?>

    <?php if ($hasComment && !empty($log['reviewer_comment'])): ?>
      <p><strong>Existing comment:</strong><br><?= nl2br(h($log['reviewer_comment'])) ?></p>
    <?php endif; ?>

    <?php if ($hasStatus || $hasComment): ?>
      <form method="post" class="stack" style="display:grid;gap:.75rem;margin-top:1rem;">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php if ($hasStatus): ?>
          <label>Status
            <select name="status" required>
              <?php foreach ($statuses as $state): ?>
                <option value="<?= h($state) ?>" <?= ($log['status'] ?? '') === $state ? 'selected' : '' ?>><?= ucfirst($state) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>

        <?php if ($hasComment): ?>
          <label>Comment
            <textarea name="comment" rows="4" placeholder="Add notes for the student or supervisors."><?= h($log['reviewer_comment'] ?? '') ?></textarea>
          </label>
        <?php endif; ?>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button class="btn">Save review</button>
          <a class="btn ghost" href="<?= url('/admin/daily_list.php') ?>">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <p class="error">This daily log table does not have status/comment columns to update.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

</main></body></html>
