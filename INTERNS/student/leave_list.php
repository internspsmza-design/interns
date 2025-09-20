<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) ? $config : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';
require_role(['student']);

$me = (int)($_SESSION['user']['id'] ?? 0);

function app_base(?array $cfg): string {
  return rtrim(defined('APP_BASE') ? (string)APP_BASE : (string)($cfg['APP_BASE'] ?? ''), '/');
}
$APP_BASE = app_base($config);

/* Filters */
$q      = trim($_GET['q'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = ["l.user_id = ?"]; $args = [$me];
if ($q     !== '') { $where[]="l.reason LIKE ?"; $args[]="%$q%"; }
if ($from  !== '') { $where[]="l.date_from >= ?"; $args[]=$from; }
if ($to    !== '') { $where[]="l.date_to   <= ?"; $args[]=$to; }
if ($status!== '') { $where[]="l.status = ?";     $args[]=$status; }
$sqlWhere = 'WHERE '.implode(' AND ', $where);

/* Query */
$sql = "
  SELECT l.id, l.date_from, l.date_to, l.reason, l.status
  FROM leaves l
  $sqlWhere
  ORDER BY l.date_from DESC, l.id DESC
  LIMIT 500
";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* CSV export */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="my_leaves.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['From','To','Reason','Status']);
  foreach ($rows as $r) {
    fputcsv($out, [$r['date_from'], $r['date_to'], $r['reason'], $r['status']]);
  }
  exit;
}

$statuses = ['pending','approved','rejected'];
$title = 'Student · My Leave Requests';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>My Leave Requests</h1>

  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search reason">
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to"   value="<?= h($to) ?>">
    <select name="status">
      <option value="">Any status</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
    <button class="btn" name="export" value="csv">Export CSV</button>
  </form>

  <table class="table">
    <thead><tr><th>Dates</th><th>Reason</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['date_from']) ?> → <?= h($r['date_to']) ?></td>
          <td><?= h(mb_strimwidth((string)$r['reason'], 0, 160, '…')) ?></td>
          <td><span class="status <?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="3">No results.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body></html>
