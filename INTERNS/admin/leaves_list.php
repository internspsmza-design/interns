<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

/*
 * Tables/columns used:
 *   leaves(id, user_id, date_from, date_to, reason, status)
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

$q      = trim($_GET['q'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = []; $args=[];
if ($q     !== '') { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if ($from  !== '') { $where[] = "l.date_from >= ?"; $args[]=$from; }
if ($to    !== '') { $where[] = "l.date_to   <= ?"; $args[]=$to; }
if ($status!== '') { $where[] = "l.status = ?";     $args[]=$status; }
$sqlWhere = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$sql = "
  SELECT l.id, l.user_id, l.date_from, l.date_to, l.reason, l.status,
         u.name, u.email
  FROM leaves l
  JOIN users u ON u.id = l.user_id
  $sqlWhere
  ORDER BY l.date_from DESC, l.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll(PDO::FETCH_ASSOC);

$statuses = ['pending','approved','rejected'];
$title = 'Admin · Leaves';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>Leaves (All Students)</h1>

  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search student (name/email)">
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to"   value="<?= h($to) ?>">
    <select name="status">
      <option value="">Any status</option>
      <?php foreach($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Filter</button>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>Dates</th><th>Student</th><th>Reason</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h($r['date_from']) ?> → <?= h($r['date_to']) ?></td>
          <td><?= h($r['name']) ?> <small>(<?= h($r['email']) ?>)</small></td>
          <td><?= h(mb_strimwidth((string)($r['reason'] ?? ''), 0, 120, '…')) ?></td>
          <td><?= h($r['status']) ?></td>
          <td><a class="btn small" href="<?= $APP_BASE ?>/admin/verify_leaves.php?id=<?= (int)$r['id'] ?>">View / Verify</a></td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="5">No results.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
