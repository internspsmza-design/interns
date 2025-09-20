<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['lecturer']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function scalar(PDO $pdo,string $sql,array $p=[]): int {
  $st=$pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn();
}

$dailyTable = table_exists($pdo,'daily_logs') ? 'daily_logs' : (table_exists($pdo,'logs') ? 'logs' : null);

$counts = [
  'daily_pending'  => $dailyTable ? scalar($pdo, "SELECT COUNT(*) FROM `$dailyTable` WHERE status='submitted'") : 0,
  'weekly_pending' => table_exists($pdo,'weekly_reports') ? scalar($pdo, "SELECT COUNT(*) FROM weekly_reports WHERE status='submitted'") : 0,
  'leave_pending'  => table_exists($pdo,'leaves') ? scalar($pdo, "SELECT COUNT(*) FROM leaves WHERE status='pending'") : 0,
];

include __DIR__.'/../app/header.php';
?>
<h2>Lecturer Dashboard</h2>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>Daily logs awaiting review</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0"><?= (int)$counts['daily_pending'] ?></div>
    <a class="btn" href="<?= url('/lecturer/verify_daily.php') ?>">Review daily logs</a>
  </div>
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>Weekly reports awaiting review</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0"><?= (int)$counts['weekly_pending'] ?></div>
    <a class="btn" href="<?= url('/lecturer/verify_weekly.php') ?>">Review weekly reports</a>
  </div>
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>Leave requests awaiting review</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0"><?= (int)$counts['leave_pending'] ?></div>
    <a class="btn" href="<?= url('/lecturer/verify_leaves.php') ?>">Review leave requests</a>
  </div>
</div>

<h3>Quick links</h3>
<ul>
  <li><a href="<?= url('/lecturer/verify_daily.php') ?>">Verify Daily Logs</a></li>
  <li><a href="<?= url('/lecturer/verify_weekly.php') ?>">Verify Weekly Reports</a></li>
  <li><a href="<?= url('/lecturer/verify_leaves.php') ?>">Verify Leave Requests</a></li>
</ul>

</main></body></html>
