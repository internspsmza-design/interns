<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
function scalar(PDO $pdo,string $sql,array $p=[]): int {
  $st=$pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn();
}

$uid = (int)($_SESSION['user']['id'] ?? 0);

// support old schema names
$dailyTable = table_exists($pdo,'daily_logs') ? 'daily_logs' : (table_exists($pdo,'logs') ? 'logs' : null);

// counts for this student
$counts = [
  'daily'   => $dailyTable ? scalar($pdo, "SELECT COUNT(*) FROM `$dailyTable` WHERE user_id=?", [$uid]) : 0,
  'weekly'  => table_exists($pdo,'weekly_reports') ? scalar($pdo, "SELECT COUNT(*) FROM weekly_reports WHERE user_id=?", [$uid]) : 0,
  'leaves'  => table_exists($pdo,'leaves') ? scalar($pdo, "SELECT COUNT(*) FROM leaves WHERE user_id=?", [$uid]) : 0,
  'pending' => (table_exists($pdo,'leaves') && col_exists($pdo,'leaves','status'))
                ? scalar($pdo, "SELECT COUNT(*) FROM leaves WHERE user_id=? AND status='pending'", [$uid])
                : 0,
];

include __DIR__.'/../app/header.php';
?>
<h2>Student Dashboard</h2>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>My daily logs</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0"><?= (int)$counts['daily'] ?></div>
    <a class="btn" href="<?= url('/student/daily_new.php') ?>">New daily log</a>
    <a style="margin-left:.5rem" href="<?= url('/student/daily_list.php') ?>">View all</a>
  </div>
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>My weekly reports</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0"><?= (int)$counts['weekly'] ?></div>
    <a class="btn" href="<?= url('/student/weekly_new.php') ?>">New weekly report</a>
    <a style="margin-left:.5rem" href="<?= url('/student/weekly_list.php') ?>">View all</a>
  </div>
  <div class="card" style="padding:14px;background:var(--card);border:1px solid var(--line);border-radius:10px">
    <div>My leave requests</div>
    <div style="font-size:28px;font-weight:700;margin:.25rem 0">
      <?= (int)$counts['leaves'] ?><?php if($counts['pending']): ?> <small>(<?= (int)$counts['pending'] ?> pending)</small><?php endif; ?>
    </div>
    <a class="btn" href="<?= url('/student/leave_new.php') ?>">Request leave</a>
    <a style="margin-left:.5rem" href="<?= url('/student/leave_list.php') ?>">View all</a>
  </div>
</div>

<h3>Quick links</h3>
<ul>
  <li><a href="<?= url('/student/daily_new.php') ?>">New Daily Log</a></li>
  <li><a href="<?= url('/student/daily_list.php') ?>">My Daily Logs</a></li>
  <li><a href="<?= url('/student/weekly_new.php') ?>">New Weekly Report</a></li>
  <li><a href="<?= url('/student/weekly_list.php') ?>">My Weekly Reports</a></li>
  <li><a href="<?= url('/student/leave_new.php') ?>">Request Leave</a></li>
  <li><a href="<?= url('/student/leave_list.php') ?>">My Leave Requests</a></li>
</ul>

</main></body></html>
