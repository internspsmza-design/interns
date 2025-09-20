<?php
require_once __DIR__ . '/session.php';

$cfg   = require __DIR__ . '/config.php';
$base  = $cfg['APP_BASE'];
$user  = $_SESSION['user'] ?? null;
$role  = $user['role'] ?? '';
$public = !empty($PUBLIC_PAGE); // set to true in login/register

// current path (for "active" link)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('ends_with')) {
  function ends_with(string $haystack, string $needle): bool {
    return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
  }
}
function nav_item(string $base, string $href, string $label, string $path, string $match): string {
  $url   = htmlspecialchars(rtrim($base, '/') . $href, ENT_QUOTES);
  $label = htmlspecialchars($label, ENT_QUOTES);
  $active = ends_with($path, $match) ? ' class="active"' : '';
  $aria   = $active ? ' aria-current="page"' : '';
  return "<li{$active}><a href=\"{$url}\"{$aria}>{$label}</a></li>";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
  <script defer src="<?= $base ?>/assets/app.js"></script>
  <title>INTERNS</title>
</head>
<body>
<header class="topbar">
  <?php if (!$public): ?>
    <button id="hamburger" aria-label="menu">☰</button>
  <?php endif; ?>

  <a href="<?= $base ?>/" class="logo-wrap" aria-label="Home">
    <img src="<?= $base ?>/assets/logo.svg" class="logo-img" alt="INTERNS logo">
  </a>

  <strong class="brand">INTERNS</strong>
  <div class="spacer"></div>

  <button id="theme-toggle" class="icon-btn" aria-label="Toggle theme" title="Toggle theme">🌞</button>

  <?php if ($user && !$public): ?>
    <span class="who"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
    <a class="btn" href="<?= $base ?>/auth/logout.php">Logout</a>
  <?php elseif (!$user && !$public): ?>
    <a class="btn" href="<?= $base ?>/auth/login.php">Login</a>
  <?php endif; ?>
</header>

<?php if (!$public): ?>
<aside id="sidebar" class="sidebar hidden" aria-label="Sidebar">
  <nav>
    <ul>
      <?php if ($user): ?>
        <?= nav_item($base, '/profile.php', 'Profile', $path, '/profile.php') ?>

        <?php if ($role === 'student'): ?>
          <?= nav_item($base, '/student/dashboard.php', 'Student Dashboard', $path, '/student/dashboard.php') ?>
          <?= nav_item($base, '/student/daily_new.php', 'New Daily Log', $path, '/student/daily_new.php') ?>
          <?= nav_item($base, '/student/daily_list.php', 'My Daily Logs', $path, '/student/daily_list.php') ?>
          <?= nav_item($base, '/student/weekly_new.php', 'New Weekly Report', $path, '/student/weekly_new.php') ?>
          <?= nav_item($base, '/student/weekly_list.php', 'My Weekly Reports', $path, '/student/weekly_list.php') ?>
          <?= nav_item($base, '/student/leave_new.php', 'Request Leave', $path, '/student/leave_new.php') ?>
          <?= nav_item($base, '/student/leave_list.php', 'My Leave Requests', $path, '/student/leave_list.php') ?>

        <?php elseif ($role === 'lecturer'): ?>
          <?= nav_item($base, '/lecturer/dashboard.php', 'Lecturer Dashboard', $path, '/lecturer/dashboard.php') ?>

          <!-- New: lists -->
          <?= nav_item($base, '/lecturer/daily_list.php',  'My Daily Logs',       $path, '/lecturer/daily_list.php') ?>
          <?= nav_item($base, '/lecturer/weekly_list.php', 'My Weekly Reports',   $path, '/lecturer/weekly_list.php') ?>
          <?= nav_item($base, '/lecturer/leaves_list.php', 'My Students’ Leaves', $path, '/lecturer/leaves_list.php') ?>

          <hr>
          <!-- Existing verify pages -->
          <?= nav_item($base, '/lecturer/verify_daily.php',  'Verify Daily Logs',       $path, '/lecturer/verify_daily.php') ?>
          <?= nav_item($base, '/lecturer/verify_weekly.php', 'Verify Weekly Reflections',$path, '/lecturer/verify_weekly.php') ?>
          <?= nav_item($base, '/lecturer/verify_leaves.php', 'Verify Leave Requests',   $path, '/lecturer/verify_leaves.php') ?>

        <?php elseif ($role === 'supervisor'): ?>
          <?= nav_item($base, '/supervisor/dashboard.php', 'Supervisor Dashboard', $path, '/supervisor/dashboard.php') ?>

          <!-- New: lists -->
          <?= nav_item($base, '/supervisor/daily_list.php',  'My Daily Logs',       $path, '/supervisor/daily_list.php') ?>
          <?= nav_item($base, '/supervisor/weekly_list.php', 'My Weekly Reports',   $path, '/supervisor/weekly_list.php') ?>
          <?= nav_item($base, '/supervisor/leaves_list.php', 'My Students’ Leaves', $path, '/supervisor/leaves_list.php') ?>

          <hr>
          <!-- Existing verify pages -->
          <?= nav_item($base, '/supervisor/verify_daily.php',  'Verify Daily Logs',        $path, '/supervisor/verify_daily.php') ?>
          <?= nav_item($base, '/supervisor/verify_weekly.php', 'Verify Weekly Reflections',$path, '/supervisor/verify_weekly.php') ?>
          <?= nav_item($base, '/supervisor/verify_leaves.php', 'Verify Leave Requests',    $path, '/supervisor/verify_leaves.php') ?>

        <?php elseif ($role === 'admin'): ?>
          <?= nav_item($base, '/admin/dashboard.php', 'Admin Dashboard', $path, '/admin/dashboard.php') ?>
          <?= nav_item($base, '/admin/users.php', 'Manage Users', $path, '/admin/users.php') ?>
          <?= nav_item($base, '/admin/user_form.php', 'New User', $path, '/admin/user_form.php') ?>
          <?= nav_item($base, '/admin/assignments.php', 'Assignments', $path, '/admin/assignments.php') ?>
          <hr>
          <?= nav_item($base, '/admin/daily_list.php',  'All Daily Logs',     $path, '/admin/daily_list.php') ?>
          <?= nav_item($base, '/admin/weekly_list.php', 'All Weekly Reports', $path, '/admin/weekly_list.php') ?>
          <?= nav_item($base, '/admin/leaves_list.php', 'All Leaves',         $path, '/admin/leaves_list.php') ?>
        <?php endif; ?>
      <?php endif; ?>
    </ul>
  </nav>
</aside>
<?php endif; ?>

<main class="container">
