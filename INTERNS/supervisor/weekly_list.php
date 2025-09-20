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
$dateExpr="COALESCE(w.week_start,w.report_date,w.created_at)";
$where=["sa.`{$sa['reviewer']}`=?"]; $args=[$me];
if($q!==''){ $where[]="(u.name LIKE ? OR u.email LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if($from!==''){ $where[]="$dateExpr >= ?"; $args[]=$from; }
if($to!==''){ $where[]="$dateExpr <= ?"; $args[]=$to; }
$sqlWhere='WHERE '.implode(' AND ',$where);

$sql="
  SELECT
    w.id,w.user_id,w.week_no,w.week_start,w.report_date,w.created_at,w.status,
    COALESCE(w.activities_summary,w.activity_summary,w.highlights,'') AS summary,
    COALESCE(w.week_start,w.report_date,w.created_at) AS week_val,
    u.name,u.email
  FROM weekly_reports w
  JOIN users u ON u.id=w.user_id
  JOIN student_assignments sa ON sa.`{$sa['student']}`=u.id
  $sqlWhere
  ORDER BY $dateExpr DESC, w.id DESC
  LIMIT 200";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

$title='Supervisor · Weekly Reports';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>My Students · Weekly Reports</h1>
  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search student">
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to" value="<?= h($to) ?>">
    <button class="btn">Filter</button>
  </form>
  <table class="table">
  <thead>
    <tr><th>Week</th><th>Student</th><th>Summary</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td>
          <?= h($r['week_val']) ?>
          <?php if (!empty($r['week_no'])): ?>
            <small>(Week <?= (int)$r['week_no'] ?>)</small>
          <?php endif; ?>
        </td>
        <td><?= h($r['name']) ?> <small>(<?= h($r['email']) ?>)</small></td>
        <td><?= h(mb_strimwidth((string)$r['summary'], 0, 120, '…')) ?></td>
        <td><span class="status <?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>

        <td>
          <a class="btn small" href="<?= $APP_BASE ?>/supervisor/verify_weekly.php?id=<?= (int)$r['id'] ?>">View / Verify</a>
        </td>
      </tr>
    <?php endforeach; if(!$rows): ?>
      <tr><td colspan="5">No results.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

</div>
</body></html>
