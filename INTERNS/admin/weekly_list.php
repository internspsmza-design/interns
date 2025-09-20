<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

/*
 * Tables/columns used (from your screenshot):
 *   weekly_reports(
 *     id, user_id,
 *     week_start DATE, week_no INT,
 *     activities_summary TEXT, activity_summary TEXT, highlights TEXT,
 *     report_date DATE, created_at TIMESTAMP,
 *     status ENUM('submitted','approved','rejected','signed'),
 *     ...
 *   )
 *   users(id, name, email)
 */

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) ? $config : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';

require_role(['admin']);

function app_base(?array $cfg): string {
  if (defined('APP_BASE')) return rtrim((string)APP_BASE, '/');
  return rtrim((string)($cfg['APP_BASE'] ?? ''), '/');
}
$APP_BASE = app_base($config);

/* --------- Filters --------- */
$q    = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

$where = []; $args = [];

if ($q !== '') {
  $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
  $args[] = "%$q%"; $args[] = "%$q%";
}

/* Use COALESCE(week_start, report_date, created_at) for date filtering */
$dateExpr = "COALESCE(w.week_start, w.report_date, w.created_at)";
if ($from !== '') { $where[] = "$dateExpr >= ?"; $args[] = $from; }
if ($to   !== '') { $where[] = "$dateExpr <= ?"; $args[] = $to; }

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* --------- Query --------- */
$sql = "
  SELECT
    w.id,
    w.user_id,
    w.week_no,
    w.week_start,
    w.report_date,
    w.created_at,
    w.status,
    /* pick first non-null summary-ish field */
    COALESCE(w.activities_summary, w.activity_summary, w.highlights, '') AS summary,
    /* computed display date */
    COALESCE(w.week_start, w.report_date, w.created_at) AS week_val,
    u.name,
    u.email
  FROM weekly_reports w
  JOIN users u ON u.id = w.user_id
  $sqlWhere
  ORDER BY $dateExpr DESC, w.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$title = 'Admin · Weekly Reports';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>Weekly Reports (All Students)</h1>

  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search student (name/email)">
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to"   value="<?= h($to) ?>">
    <button class="btn">Filter</button>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>Week</th><th>Student</th><th>Summary</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <?= h((string)$r['week_val']) ?>
            <?php if ($r['week_no'] !== null): ?>
              <small>(Week <?= (int)$r['week_no'] ?>)</small>
            <?php endif; ?>
          </td>
          <td><?= h($r['name']) ?> <small>(<?= h($r['email']) ?>)</small></td>
          <td><?= h(mb_strimwidth((string)$r['summary'], 0, 120, '…')) ?></td>
          <td><?= h($r['status']) ?></td>
          <td><a class="btn small" href="<?= $APP_BASE ?>/admin/verify_weekly.php?id=<?= (int)$r['id'] ?>">View / Verify</a></td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="5">No results.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
