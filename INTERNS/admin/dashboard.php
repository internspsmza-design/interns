<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['admin']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

ini_set('display_errors','1'); error_reporting(E_ALL);

function table_exists(PDO $pdo, string $t): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $st->execute([$t]);
  return (bool)$st->fetchColumn();
}
function safe_count(PDO $pdo, string $t): int {
  if (!table_exists($pdo,$t)) return 0;
  return (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

$counts = [
  'users'  => safe_count($pdo,'users'),
  'daily'  => table_exists($pdo,'daily_logs') ? safe_count($pdo,'daily_logs') : safe_count($pdo,'logs'),
  'weekly' => safe_count($pdo,'weekly_reports'),
  'leaves' => safe_count($pdo,'leaves'),
];

include __DIR__.'/../app/header.php';
?>
<h2>Admin Dashboard</h2>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
  <div style="padding:16px;background:var(--card,#fff);color:var(--text,#0f172a);border:1px solid var(--line,#e5e7eb);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)">
    <span style="color:var(--muted,#475569)">Users:</span> <strong><?= (int)$counts['users'] ?></strong>
  </div>
  <div style="padding:16px;background:var(--card,#fff);color:var(--text,#0f172a);border:1px solid var(--line,#e5e7eb);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)">
    <span style="color:var(--muted,#475569)">Daily logs:</span> <strong><?= (int)$counts['daily'] ?></strong>
  </div>
  <div style="padding:16px;background:var(--card,#fff);color:var(--text,#0f172a);border:1px solid var(--line,#e5e7eb);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)">
    <span style="color:var(--muted,#475569)">Weekly reports:</span> <strong><?= (int)$counts['weekly'] ?></strong>
  </div>
  <div style="padding:16px;background:var(--card,#fff);color:var(--text,#0f172a);border:1px solid var(--line,#e5e7eb);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)">
    <span style="color:var(--muted,#475569)">Leaves:</span> <strong><?= (int)$counts['leaves'] ?></strong>
  </div>
</div>

<p style="margin-top:16px">
  <a class="btn" href="<?= url('/admin/users.php') ?>">Manage Users</a>
</p>

</main></body></html>
