<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);
$__errlog = __DIR__ . '/../app/php_errors.log';
ini_set('error_log', $__errlog);

$PUBLIC_PAGE = true;

require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!function_exists('h')) {
  function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$errors = [];
$ok = false;
$roles = ['student','lecturer','supervisor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name        = trim($_POST['name'] ?? '');
  $email       = trim($_POST['email'] ?? '');
  $password    = (string)($_POST['password'] ?? '');
  $role        = strtolower(trim($_POST['role'] ?? 'student'));
  $matrikStaff = trim($_POST['matrik_staff'] ?? '');

  $start_date = $role === 'student' ? trim($_POST['start_date'] ?? '') : '';
  $end_date   = $role === 'student' ? trim($_POST['end_date']   ?? '') : '';

  if ($name === '') $errors['name'] = 'Name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
  if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
  if (!in_array($role, $roles, true)) $errors['role'] = 'Invalid role selected.';

  if (!$errors) {
    $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetch()) $errors['email'] = 'Email already registered.';
  }

  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // columns present
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(fn($r) => strtolower($r['Field']), $cols);

    // decide status value by column type
    $statusVal = null;
    if (in_array('status', $colNames, true)) {
      $row = array_values(array_filter($cols, fn($r)=>strtolower($r['Field'])==='status'))[0] ?? null;
      $type = strtolower((string)($row['Type'] ?? ''));
      $statusVal = (preg_match('/int|decimal|bit|bool/', $type)) ? 1 : 'active';
    }

    // password column name
    $pwdCol = in_array('password_hash', $colNames, true) ? 'password_hash'
            : (in_array('password', $colNames, true) ? 'password' : null);

    $payload = [
      'name'  => $name,
      'email' => $email,
      'role'  => $role,
    ];
    if ($pwdCol)  $payload[$pwdCol] = $hash;
    if ($statusVal !== null) $payload['status'] = $statusVal;

    $insertCols = [];
    $place = [];
    $values = [];
    foreach ($payload as $k => $v) {
      if (in_array(strtolower($k), $colNames, true)) {
        $insertCols[] = "`$k`";
        $place[] = '?';
        $values[] = $v;
      }
    }

    $sql = "INSERT INTO `users` (".implode(',', $insertCols).") VALUES (".implode(',', $place).")";
    $pdo->prepare($sql)->execute($values);

    if ($role === 'student') {
      $uid = (int)$pdo->lastInsertId();
      $tCheck = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='students' LIMIT 1");
      $tCheck->execute();
      if ($tCheck->fetchColumn()) {
        $sCols = $pdo->query("SHOW COLUMNS FROM `students`")->fetchAll(PDO::FETCH_COLUMN);
        $sCols = array_map('strtolower', $sCols);

        $studentPayload = ['user_id' => $uid];
        if (in_array('matric_no', $sCols, true))  $studentPayload['matric_no']  = $matrikStaff ?: null;
        if (in_array('start_date', $sCols, true)) $studentPayload['start_date'] = $start_date ?: null;
        if (in_array('end_date',   $sCols, true)) $studentPayload['end_date']   = $end_date   ?: null;

        $c = implode(',', array_map(fn($k) => "`$k`", array_keys($studentPayload)));
        $q = implode(',', array_fill(0, count($studentPayload), '?'));
        $pdo->prepare("INSERT INTO `students` ($c) VALUES ($q)")->execute(array_values($studentPayload));
      }
    }

    $ok = true;
  }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register · INTERNS</title>
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body class="auth">
  <main class="container">
    <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:560px;margin:2rem auto">
      <h1 style="margin-top:0">Create Account</h1>

      <?php if ($ok): ?>
        <div class="ok">Registration successful. <a href="<?= url('/auth/login.php') ?>">Login</a></div>
      <?php endif; ?>

      <?php if ($errors && !$ok): ?>
        <div class="error-list">
          <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <label>Name
          <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
        </label>
        <label>Email
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </label>
        <label>Password
          <input type="password" name="password" required>
        </label>

        <label>Role
          <select name="role" id="role-select" onchange="toggleStudentDates()" required>
            <option value="student"   <?= (($_POST['role'] ?? '')==='student'   ? 'selected' : '') ?>>Student</option>
            <option value="lecturer"  <?= (($_POST['role'] ?? '')==='lecturer'  ? 'selected' : '') ?>>Lecturer</option>
            <option value="supervisor"<?= (($_POST['role'] ?? '')==='supervisor'? 'selected' : '') ?>>Supervisor</option>
          </select>
        </label>

        <label>Matric/Staff No.
          <input type="text" name="matrik_staff" value="<?= h($_POST['matrik_staff'] ?? '') ?>">
        </label>

        <div id="student-dates" style="display:none">
          <label>Start Date
            <input type="date" name="start_date" value="<?= h($_POST['start_date'] ?? '') ?>">
          </label>
          <label>End Date
            <input type="date" name="end_date" value="<?= h($_POST['end_date'] ?? '') ?>">
          </label>
        </div>

        <button class="btn">Register</button>
        <p style="margin-top:10px">Already have an account? <a href="<?= url('/auth/login.php') ?>">Login</a></p>
      </form>
    </div>
  </main>

<script>
function toggleStudentDates() {
  const role = document.getElementById('role-select').value;
  document.getElementById('student-dates').style.display = (role === 'student') ? '' : 'none';
}
toggleStudentDates();
</script>
</body>
</html>
