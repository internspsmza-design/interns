<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

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

/* CSRF */
if (!function_exists('csrf_token')) {
  function csrf_token(): string { $_SESSION['_csrf']=$_SESSION['_csrf']??bin2hex(random_bytes(16)); return $_SESSION['_csrf']; }
}
if (!function_exists('csrf_check')) {
  function csrf_check(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
    if(!$ok){ http_response_code(400); exit('Bad CSRF'); }
  }
}

$students = $pdo->query("SELECT id,name,email FROM users WHERE role='student' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$lects    = $pdo->query("SELECT id,name,email FROM users WHERE role='lecturer' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$supvs    = $pdo->query("SELECT id,name,email FROM users WHERE role='supervisor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $student    = (int)($_POST['student'] ?? 0);
  $lecturer   = (int)($_POST['lecturer'] ?? 0);
  $supervisor = (int)($_POST['supervisor'] ?? 0);
  $lecturer   = $lecturer>0 ? $lecturer : null;
  $supervisor = $supervisor>0 ? $supervisor : null;

  if ($student>0) {
    $st=$pdo->prepare("INSERT INTO student_assignments (student_user_id,lecturer_user_id,supervisor_user_id)
                       VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE lecturer_user_id=VALUES(lecturer_user_id),
                                               supervisor_user_id=VALUES(supervisor_user_id)");
    $st->execute([$student,$lecturer,$supervisor]);
    $msg='Saved.';
  }
}

$rows = $pdo->query("
  SELECT s.student_user_id,
         su.name  AS student_name,
         lu.name  AS lecturer_name,
         pu.name  AS supervisor_name
  FROM student_assignments s
  LEFT JOIN users su ON su.id = s.student_user_id
  LEFT JOIN users lu ON lu.id = s.lecturer_user_id
  LEFT JOIN users pu ON pu.id = s.supervisor_user_id
  ORDER BY su.name
")->fetchAll(PDO::FETCH_ASSOC);

$title='Admin · Assignments';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="<?= $APP_BASE ?>/assets/style.css">
</head><body>
<?php include __DIR__.'/../app/header.php'; ?>
<div class="container">
  <h1>Student Assignments</h1>
  <?php if($msg): ?><div class="alert success"><?= h($msg) ?></div><?php endif; ?>

  <form method="post" class="form" style="display:grid;gap:.75rem;max-width:640px">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <label>Student
      <select name="student" required>
        <option value="">-- choose student --</option>
        <?php foreach($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($s['name'].' ('.$s['email'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Lecturer
      <select name="lecturer">
        <option value="">-- none --</option>
        <?php foreach($lects as $l): ?>
          <option value="<?= (int)$l['id'] ?>"><?= h($l['name'].' ('.$l['email'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Supervisor
      <select name="supervisor">
        <option value="">-- none --</option>
        <?php foreach($supvs as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= h($p['name'].' ('.$p['email'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit">Save</button>
  </form>

  <h2 class="mt">Current</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Student</th><th>Lecturer</th><th>Supervisor</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h($r['student_name'] ?? '-') ?></td>
            <td><?= h($r['lecturer_name'] ?? '-') ?></td>
            <td><?= h($r['supervisor_name'] ?? '-') ?></td>
          </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="3">No assignments yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
