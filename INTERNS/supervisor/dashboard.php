<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/db.php';
require_role(['supervisor']);

include __DIR__ . '/../app/header.php';
?>
<main class="container">
  <h1>Supervisor Dashboard</h1>

  <div class="cards">
    <div class="card">
      <h3>Welcome</h3>
      <p>Here you can manage your assigned students and review submissions.</p>
      <a class="btn" href="<?= url('/supervisor/assignments.php') ?>">Approve Student Assignments</a>
    </div>
  </div>

</main>
</body></html>
