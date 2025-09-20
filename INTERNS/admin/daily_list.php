<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';
require_role(['admin']);

function app_base(?array $cfg): string {
  return rtrim((string)($cfg['APP_BASE'] ?? ''), '/');
}
$APP_BASE = app_base($config);

/* Filters */
$q    = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

$where = []; $args=[];
if ($q   !== '') { $where[]="(u.name LIKE ? OR u.email LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if ($from!== '') { $where[]="d.log_date >= ?"; $args[]=$from; }
if ($to  !== '') { $where[]="d.log_date <= ?"; $args[]=$to; }
$sqlWhere = $where ? "WHERE ".implode(" AND ", $where) : "";

/* Query */
$sql = "
  SELECT d.id, d.log_date, d.activity, d.status,
         u.name, u.email
  FROM daily_logs d
  JOIN users u ON u.id = d.user_id
  $sqlWhere
  ORDER BY d.log_date DESC, d.id DESC
  LIMIT 500
";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* CSV export */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="admin_daily_logs.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['Date','Student','Email','Activity','Status']);
  foreach($rows as $r){
    fputcsv($out,[$r['log_date'],$r['name'],$r['email'],$r['activity'],$r['status']]);
  }
  exit;
}

$title="Admin · Daily Logs";
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?></title>
<link rel="stylesheet" href="<?=$APP_BASE?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>All Daily Logs</h1>

  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?=h($q)?>" placeholder="Search student">
    <input type="date" name="from" value="<?=h($from)?>">
    <input type="date" name="to"   value="<?=h($to)?>">
    <button class="btn">Filter</button>
    <button class="btn" name="export" value="csv">Export CSV</button>
    <button class="btn" type="button" onclick="window.print()">Print</button>
  </form>

  <table class="table">
    <thead><tr><th>Date</th><th>Student</th><th>Activity</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?=h($r['log_date'])?></td>
        <td><?=h($r['name'])?> <small>(<?=h($r['email'])?>)</small></td>
        <td><?=h($r['activity'])?></td>
        <td><span class="status <?=h(strtolower($r['status']))?>"><?=h($r['status'])?></span></td>
        <td><a class="btn small" href="<?=$APP_BASE?>/admin/verify_daily.php?id=<?=(int)$r['id']?>">View / Verify</a></td>
      </tr>
    <?php endforeach; if(!$rows): ?>
      <tr><td colspan="5">No results.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</body></html>
