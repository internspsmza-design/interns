<?php
declare(strict_types=1); // must be first

// Dev error visibility (remove later if you like)
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/app/session.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

if (!function_exists('h')) {
  function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ===== Require login ===== */
if (empty($_SESSION['user']['id'])) {
  header('Location: ' . url('/auth/login.php')); exit;
}
$me     = $_SESSION['user'];
$userId = (int)$me['id'];
$role   = strtolower((string)($me['role'] ?? ''));

/* ===== Small helpers ===== */
function table_exists(PDO $pdo, string $name): bool {
  $q = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $q->execute([$name]); return (bool)$q->fetchColumn();
}
function cols(PDO $pdo, string $table): array {
  $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
  $out = [];
  foreach ($rows as $r) $out[strtolower($r['Field'])] = strtolower($r['Type'] ?? '');
  return $out;
}
function active_sql(string $col = 'status'): string {
  // Works for numeric or text status columns
  return " ( $col IS NULL OR $col='' OR $col='active' OR $col='1' OR $col=1 OR $col='enabled' OR $col='approved' ) ";
}
function include_first(array $relativePaths): void {
  foreach ($relativePaths as $rel) {
    $p = __DIR__ . $rel;
    if (file_exists($p)) { include $p; return; }
  }
}

/* ===== Load my user row ===== */
$u = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$u->execute([$userId]);
$userRow = $u->fetch(PDO::FETCH_ASSOC) ?: [];

/* ===== Students table / row ===== */
$studentsTblExists = table_exists($pdo, 'students');
$studentRow = [];
$studentUserCol = null;
if ($studentsTblExists) {
  $sCols = cols($pdo, 'students');
  $studentUserCol = array_key_exists('user_id', $sCols) ? 'user_id'
                  : (array_key_exists('student_id', $sCols) ? 'student_id' : null);
  if ($studentUserCol) {
    $s = $pdo->prepare("SELECT * FROM `students` WHERE `$studentUserCol`=? LIMIT 1");
    $s->execute([$userId]);
    $studentRow = $s->fetch(PDO::FETCH_ASSOC) ?: [];
  }
}

/* ===== Student assignments table (create if missing) ===== */
if (!table_exists($pdo, 'student_assignments')) {
  $pdo->exec("
    CREATE TABLE `student_assignments` (
      `student_id`     INT NOT NULL,
      `lecturer_id`    INT DEFAULT NULL,
      `supervisor_id`  INT DEFAULT NULL,
      `status`         VARCHAR(20) NOT NULL DEFAULT 'pending',
      `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at`     TIMESTAMP NULL DEFAULT NULL,
      INDEX (`student_id`), INDEX (`lecturer_id`), INDEX (`supervisor_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}
$aCols = cols($pdo, 'student_assignments');
$aStudentCol    = array_key_exists('student_id',    $aCols) ? 'student_id'    : (array_key_exists('student_user_id', $aCols) ? 'student_user_id' : null);
$aLecturerCol   = array_key_exists('lecturer_id',   $aCols) ? 'lecturer_id'   : (array_key_exists('lecturer_user_id',$aCols) ? 'lecturer_user_id' : null);
$aSupervisorCol = array_key_exists('supervisor_id', $aCols) ? 'supervisor_id' : (array_key_exists('sv_id',          $aCols) ? 'sv_id'           : null);
$aStatusCol     = array_key_exists('status',        $aCols) ? 'status'        : null;

/* ===== Handle POST (1) edit profile, (2) save assignment) ===== */
$notice = ''; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['_action'] ?? '';

  // (1) Student profile save
  if ($role === 'student' && $action === 'save_profile') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $matric_no  = trim($_POST['matric_no'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');

    if ($name === '') $errors['name'] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
    if (!$errors) {
      $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
      $chk->execute([$email, $userId]);
      if ($chk->fetch()) $errors['email'] = 'Email already used by another account.';
    }

    if (!$errors) {
      // update users
      $uCols = cols($pdo, 'users');
      $set = []; $vals=[];
      if (array_key_exists('name',$uCols))  { $set[]="`name`=?";  $vals[]=$name; }
      if (array_key_exists('email',$uCols)) { $set[]="`email`=?"; $vals[]=$email; }
      if ($set) {
        $vals[] = $userId;
        $pdo->prepare("UPDATE `users` SET ".implode(',', $set)." WHERE id=?")->execute($vals);
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['email'] = $email;
      }
      // upsert students
      if ($studentsTblExists && $studentUserCol) {
        $sCols2 = cols($pdo, 'students');
        $payload = [];
        if (array_key_exists('matric_no', $sCols2))  $payload['matric_no']  = ($matric_no !== '' ? $matric_no : null);
        if (array_key_exists('start_date',$sCols2))  $payload['start_date'] = ($start_date !== '' ? $start_date : null);
        if (array_key_exists('end_date',  $sCols2))  $payload['end_date']   = ($end_date   !== '' ? $end_date   : null);

        $exists = !empty($studentRow);
        if ($exists) {
          if ($payload) {
            $parts=[]; $vals=[];
            foreach ($payload as $k=>$v){ $parts[]="`$k`=?"; $vals[]=$v; }
            $vals[]=$userId;
            $pdo->prepare("UPDATE `students` SET ".implode(',', $parts)." WHERE `$studentUserCol`=?")->execute($vals);
          }
        } else {
          $payload[$studentUserCol] = $userId;
          $colsList = implode(',', array_map(fn($k)=>"`$k`", array_keys($payload)));
          $q = implode(',', array_fill(0, count($payload), '?'));
          $pdo->prepare("INSERT INTO `students` ($colsList) VALUES ($q)")->execute(array_values($payload));
        }
      }
      $notice = 'Profile updated.';
      // refresh
      if ($studentsTblExists && $studentUserCol) {
        $s = $pdo->prepare("SELECT * FROM `students` WHERE `$studentUserCol`=? LIMIT 1");
        $s->execute([$userId]);
        $studentRow = $s->fetch(PDO::FETCH_ASSOC) ?: [];
      }
      $u = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
      $u->execute([$userId]);
      $userRow = $u->fetch(PDO::FETCH_ASSOC) ?: $userRow;
    }
  }

  // (2) Student assignment save
  if ($role === 'student' && $action === 'save_assignment' && $aStudentCol) {
    $lecturer_id   = max(0, (int)($_POST['lecturer_id'] ?? 0));
    $supervisor_id = max(0, (int)($_POST['supervisor_id'] ?? 0));

    // validate chosen users
    if ($lecturer_id) {
      $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND LOWER(role)='lecturer' AND ".active_sql('status')." LIMIT 1");
      $q->execute([$lecturer_id]); if(!$q->fetch()) $lecturer_id=0;
    }
    if ($supervisor_id) {
      $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND LOWER(role)='supervisor' AND ".active_sql('status')." LIMIT 1");
      $q->execute([$supervisor_id]); if(!$q->fetch()) $supervisor_id=0;
    }

    // upsert to student_assignments (status -> pending)
    $st = $pdo->prepare("SELECT * FROM `student_assignments` WHERE `$aStudentCol`=? LIMIT 1");
    $st->execute([$userId]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    $parts=[]; $vals=[];
    if ($aLecturerCol)   { $parts[]="`$aLecturerCol`=?";   $vals[] = $lecturer_id ?: null; }
    if ($aSupervisorCol) { $parts[]="`$aSupervisorCol`=?"; $vals[] = $supervisor_id ?: null; }
    if ($aStatusCol)     { $parts[]="`$aStatusCol`=?";     $vals[] = 'pending'; }
    if (array_key_exists('updated_at',$aCols)) $parts[]="`updated_at`=CURRENT_TIMESTAMP";

    if ($existing) {
      $vals[] = $userId;
      $pdo->prepare("UPDATE `student_assignments` SET ".implode(',', $parts)." WHERE `$aStudentCol`=?")->execute($vals);
      $notice = 'Assignment updated (pending approval).';
    } else {
      $payload = [];
      $payload[$aStudentCol] = $userId;
      if ($aLecturerCol)   $payload[$aLecturerCol]   = $lecturer_id ?: null;
      if ($aSupervisorCol) $payload[$aSupervisorCol] = $supervisor_id ?: null;
      if ($aStatusCol)     $payload[$aStatusCol]     = 'pending';
      $colsList = implode(',', array_map(fn($k)=>"`$k`", array_keys($payload)));
      $q = implode(',', array_fill(0, count($payload), '?'));
      $pdo->prepare("INSERT INTO `student_assignments` ($colsList) VALUES ($q)")->execute(array_values($payload));
      $notice = 'Assignment saved (pending approval).';
    }
  }
}

/* ===== Data for display ===== */
$name      = (string)($userRow['name']  ?? '');
$email     = (string)($userRow['email'] ?? '');
$matricNo  = (string)($studentRow['matric_no']  ?? '');
$startDate = (string)($studentRow['start_date'] ?? '');
$endDate   = (string)($studentRow['end_date']   ?? '');

$assignment = null;
if ($aStudentCol) {
  $st = $pdo->prepare("SELECT * FROM `student_assignments` WHERE `$aStudentCol`=? LIMIT 1");
  $st->execute([$userId]);
  $assignment = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$currentLecturerId   = (int)($assignment[$aLecturerCol]   ?? 0);
$currentSupervisorId = (int)($assignment[$aSupervisorCol] ?? 0);

$lecturers   = $pdo->query("SELECT id,name,email FROM users WHERE LOWER(role)='lecturer'  AND ".active_sql('status')." ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$supervisors = $pdo->query("SELECT id,name,email FROM users WHERE LOWER(role)='supervisor' AND ".active_sql('status')." ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profile · INTERNS</title>
  <link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body>

  <?php
  // Use your existing topbar/header if present (NO new markup created here)
  include_first([
    '/partials/topbar.php','/partials/header.php',
    '/includes/topbar.php','/includes/header.php',
    '/inc/topbar.php','/inc/header.php',
    '/layout/topbar.php','/layout/header.php'
  ]);

  // Use your existing sidebar if present
  include_first([
    '/partials/sidebar.php','/includes/sidebar.php',
    '/inc/sidebar.php','/layout/sidebar.php'
  ]);
  ?>

  <main class="container"><!-- your CSS controls layout with existing header/sidebar -->
    <h2>My Profile</h2>

    <?php if ($notice): ?>
      <div class="ok" style="margin-bottom:.75rem"><?= h($notice) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="error-list" style="margin-bottom:.75rem">
        <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($role === 'student'): ?>
      <!-- Edit profile -->
      <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:820px">
        <h3 style="margin-top:0">Edit profile</h3>
        <form method="post" novalidate>
          <input type="hidden" name="_action" value="save_profile">
          <label>Name
            <input type="text" name="name" value="<?= h($name) ?>" required>
          </label>
          <label>Email
            <input type="email" name="email" value="<?= h($email) ?>" required>
          </label>
          <?php if ($studentsTblExists): ?>
            <label>Matric No.
              <input type="text" name="matric_no" value="<?= h($matricNo) ?>">
            </label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
              <label>Start Date
                <input type="date" name="start_date" value="<?= h($startDate) ?>">
              </label>
              <label>End Date
                <input type="date" name="end_date" value="<?= h($endDate) ?>">
              </label>
            </div>
          <?php endif; ?>
          <button class="btn" style="margin-top:.5rem">Save changes</button>
        </form>
      </div>

      <div style="height:12px"></div>

      <!-- Assign Lecturer & Supervisor -->
      <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:820px">
        <h3 style="margin-top:0">Assigned Lecturer & Supervisor</h3>

        <form method="post">
          <input type="hidden" name="_action" value="save_assignment">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
            <label>Lecturer
              <select name="lecturer_id">
                <option value="0">— Select lecturer —</option>
                <?php foreach ($lecturers as $L): ?>
                  <option value="<?= (int)$L['id'] ?>" <?= $currentLecturerId===(int)$L['id']?'selected':'' ?>>
                    <?= h($L['name'] ?? 'Unknown') ?> (<?= h($L['email'] ?? '') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>Supervisor
              <select name="supervisor_id">
                <option value="0">— Select supervisor —</option>
                <?php foreach ($supervisors as $S): ?>
                  <option value="<?= (int)$S['id'] ?>" <?= $currentSupervisorId===(int)$S['id']?'selected':'' ?>>
                    <?= h($S['name'] ?? 'Unknown') ?> (<?= h($S['email'] ?? '') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <button class="btn" style="margin-top:.75rem">Save</button>
          <?php if ($aStatusCol && $assignment): ?>
            <div style="margin-top:.5rem">Current status:
              <span class="status <?= h(strtolower((string)$assignment[$aStatusCol])) ?>">
                <?= h((string)$assignment[$aStatusCol]) ?>
              </span>
            </div>
          <?php endif; ?>
        </form>
      </div>

    <?php else: ?>
      <!-- Non-student: read-only -->
      <div class="auth-card" style="background:var(--card);padding:1rem;border:1px solid var(--line);border-radius:.75rem;max-width:820px">
        <h3 style="margin-top:0">Account</h3>
        <div><strong>Name:</strong> <?= h($userRow['name'] ?? '') ?></div>
        <div><strong>Email:</strong> <?= h($userRow['email'] ?? '') ?></div>
        <div><strong>Role:</strong> <?= h($role) ?></div>
      </div>
    <?php endif; ?>
  </main>

</body>
</html>
