<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) ? $config : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';
require_role(['supervisor']);

$me = (int)($_SESSION['user']['id'] ?? 0);
function app_base(?array $cfg): string { return rtrim(defined('APP_BASE') ? (string)APP_BASE : (string)($cfg['APP_BASE']??''), '/'); }
$APP_BASE = app_base($config);

function sa_cols(PDO $pdo): array {
  $have=function(array $c) use($pdo){$in=implode(',',array_fill(0,count($c),'?'));$st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='student_assignments' AND column_name IN ($in)");$st->execute($c);return (int)$st->fetchColumn()===count($c);};
  if ($have(['student_user_id','supervisor_user_id'])) return ['student'=>'student_user_id','reviewer'=>'supervisor_user_id'];
  if ($have(['student_id','supervisor_id']))           return ['student'=>'student_id','reviewer'=>'supervisor_id'];
  return ['student'=>'student_user_id','reviewer'=>'supervisor_user_id'];
}
$sa = sa_cols($pdo);

$q=trim($_GET['q']??''); $from=trim($_GET['from']??''); $to=trim($_GET['to']??'');
$where=["sa.`{$sa['reviewer']}`=?"]; $args=[$me];
if($q!==''){ $where[]="(u.name LIKE ? OR u.email LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if($from!==''){ $where[]="d.log_date >= ?"; $args[]=$from; }
if($to!==''){ $where[]="d.log_date <= ?"; $args[]=$to; }
$sqlWhere='WHERE '.implode(' AND ',$where);

$sql="
  SELECT d.id,d.user_id,d.log_date,d.activity,d.status,u.name,u.email
  FROM daily_logs d
  JOIN users u ON u.id=d.user_id
  JOIN student_assignments sa ON sa.`{$sa['student']}`=u.id
  $sqlWhere
  ORDER BY d.log_date DESC,d.id DESC
  LIMIT 200";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

$title='Supervisor · Daily Logs';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>My Students · Daily Logs</h1>
  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search student">
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to" value="<?= h($to) ?>">
    <button class="btn">Filter</button>
  </form>
  <table class="table">
  <thead>
    <tr><th>Date</th><th>Student</th><th>Activity</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= h($r['log_date']) ?></td>
        <td><?= h($r['name']) ?> <small>(<?= h($r['email']) ?>)</small></td>
        <td><?= h($r['activity']) ?></td>
        <td><span class="status <?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>

        <td>
          <a class="btn small" href="<?= $APP_BASE ?>/supervisor/verify_daily.php?id=<?= (int)$r['id'] ?>">View / Verify</a>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?>
      <tr><td colspan="5">No results.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

</div>
</body></html>
