<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
$config = (isset($config) && is_array($config)) 
  ? $config 
  : (is_array(@require __DIR__ . '/../app/config.php') ? require __DIR__ . '/../app/config.php' : []);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/helpers.php';

require_role(['admin']);

/* Base URL helper compatible with both styles (constant or array) */
function app_base(?array $cfg): string {
  if (defined('APP_BASE')) return rtrim((string)APP_BASE, '/');
  $b = (string)($cfg['APP_BASE'] ?? '');
  return rtrim($b, '/');
}
$APP_BASE = app_base($config);

/* ===== CSRF ===== */
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    $_SESSION['_csrf'] = $_SESSION['_csrf'] ?? bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
  }
}
if (!function_exists('csrf_check')) {
  function csrf_check(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf']) 
       && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
    if (!$ok) { http_response_code(400); exit('Bad CSRF'); }
  }
}

/* ===== Actions: toggle / delete ===== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($action==='toggle' && $id>0) {
    $st=$pdo->prepare("UPDATE users SET status = IF(status=1,0,1) WHERE id=?");
    $st->execute([$id]);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  if ($action==='delete' && $id>0) {
    if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
      header('Location: '.$_SERVER['REQUEST_URI']); exit; // prevent self-delete
    }
    $st=$pdo->prepare("DELETE FROM users WHERE id=?");
    $st->execute([$id]);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
}

/* ===== Filters & pagination ===== */
$roles = ['student','lecturer','supervisor','admin'];
$q     = trim($_GET['q'] ?? '');
$role  = trim($_GET['role'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 20;
$off   = ($page-1)*$per;

$where = []; $args=[];
if ($q!=='') { $where[]="(name LIKE ? OR email LIKE ?)"; $args[]="%$q%"; $args[]="%$q%"; }
if ($role!=='' && in_array($role,$roles,true)) { $where[]="role=?"; $args[]=$role; }
$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ===== Count ===== */
$st = $pdo->prepare("SELECT COUNT(*) FROM users $sqlWhere");
$st->execute($args);
$total = (int)$st->fetchColumn();

/* ===== Data ===== */
$st = $pdo->prepare("SELECT id,name,email,role,status,created_at FROM users $sqlWhere ORDER BY id DESC LIMIT $per OFFSET $off");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, (int)ceil($total / $per));
$title = 'Admin · Users';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>

<div class="container">
  <header class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin:.75rem 0 1rem">
    <h1 style="margin:0">Users</h1>
    <a class="btn" href="<?= $APP_BASE ?>/admin/user_form.php">New User</a>
  </header>

  <form method="get" class="filters" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.5rem 0 1rem">
    <input name="q" value="<?= h($q) ?>" placeholder="Search name/email">
    <select name="role">
      <option value="">All roles</option>
      <?php foreach($roles as $r): ?>
        <option value="<?= $r ?>" <?= $r===$role?'selected':'' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap" style="overflow:auto">
    <table class="table" style="min-width:720px">
      <thead><tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td><?= h($r['role']) ?></td>
            <td><?= ((int)$r['status']===1 ? 'Active' : 'Disabled') ?></td>
            <td><?= h($r['created_at']) ?></td>
            <td style="display:flex;gap:.25rem;flex-wrap:wrap">
              <a class="btn small" href="<?= $APP_BASE ?>/admin/user_form.php?id=<?= (int)$r['id'] ?>">Edit</a>

              <form method="post" onsubmit="return confirm('Toggle active/disabled?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small" type="submit"><?= ((int)$r['status']===1?'Disable':'Enable') ?></button>
              </form>

              <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn small danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?>
          <tr><td colspan="7">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <nav class="pagination" style="margin-top:1rem;display:flex;gap:.25rem;flex-wrap:wrap">
    <?php for($i=1;$i<=$totalPages;$i++): 
      $u = htmlspecialchars($_SERVER['PHP_SELF'].'?'.http_build_query(['q'=>$q,'role'=>$role,'page'=>$i]),ENT_QUOTES,'UTF-8'); ?>
      <a class="btn small <?= $i===$page?'active':'' ?>" href="<?= $u ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
</div>
</body></html>
