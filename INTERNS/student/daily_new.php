<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

$msg=''; $err='';

function table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}

$dailyTable = table_exists($pdo,'daily_logs') ? 'daily_logs' : (table_exists($pdo,'logs') ? 'logs' : 'daily_logs');
$imgTable   = table_exists($pdo,'daily_images') ? 'daily_images' : (table_exists($pdo,'log_images') ? 'log_images' : 'daily_images');

$uid = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $log_date  = $_POST['log_date'] ?? '';
  $hari_post = trim($_POST['hari'] ?? '');
  $tugas     = trim($_POST['tugas'] ?? '');
  $objektif  = trim($_POST['objektif'] ?? '');
  $peralatan = trim($_POST['peralatan'] ?? '');
  $prosedur  = trim($_POST['prosedur'] ?? '');
  $kesimpulan= trim($_POST['kesimpulan'] ?? '');

  if (!$log_date || !$tugas || !$objektif || !$prosedur || !$kesimpulan) {
    $err = 'Sila isi TARIKH, TUGAS/AKTIVITI/PROJEK, OBJEKTIF, PROSEDUR KERJA dan KESIMPULAN.';
  }

  // derive HARI (Malay) on the server too
  $hari = $hari_post;
  if (!$hari && $log_date) {
    $names = ['Ahad','Isnin','Selasa','Rabu','Khamis','Jumaat','Sabtu'];
    $hari = $names[(int)date('w', strtotime($log_date))] ?? '';
  }

  if (!$err) {
    try {
      // Build dynamic insert with whatever columns exist
      $base = [
        'user_id'   => $uid,
        'log_date'  => $log_date,
        'hari'      => $hari,
        'tugas'     => $tugas,
        'objektif'  => $objektif,
        'peralatan' => $peralatan,
        'prosedur'  => $prosedur,
        'kesimpulan'=> $kesimpulan,
        'status'    => 'submitted',
      ];
      $colsAvail = [];
      foreach ($base as $k=>$v) if (col_exists($pdo,$dailyTable,$k)) $colsAvail[$k]=$v;

      // Always ensure user_id/log_date exist
      if (empty($colsAvail['user_id']) || empty($colsAvail['log_date'])) {
        throw new RuntimeException("Jadual harian anda belum mempunyai lajur wajib (user_id/log_date).");
      }

      $cList = implode(',', array_map(fn($c)=>"`$c`", array_keys($colsAvail)));
      $place = implode(',', array_fill(0,count($colsAvail),'?'));
      $st = $pdo->prepare("INSERT INTO `$dailyTable` ($cList) VALUES ($place)");
      $st->execute(array_values($colsAvail));
      $daily_id = (int)$pdo->lastInsertId();

      // Upload images (optional)
      if (!empty($_FILES['images']['name'][0])) {
        $dir = __DIR__.'/../uploads/daily';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        $fk = col_exists($pdo,$imgTable,'daily_id') ? 'daily_id' : (col_exists($pdo,$imgTable,'log_id') ? 'log_id' : 'daily_id');
        $imgStmt = $pdo->prepare("INSERT INTO `$imgTable` (`$fk`, `path`) VALUES (?,?)");

        $files = $_FILES['images'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i=0; $i<count($files['name']); $i++) {
          if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
          $tmp  = $files['tmp_name'][$i];
          $mime = $finfo->file($tmp);
          if (strpos($mime,'image/') !== 0) continue;
          if (filesize($tmp) > 5*1024*1024) continue;

          $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
          $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', pathinfo($files['name'][$i], PATHINFO_FILENAME));
          $name = sprintf('%s_%d_%s.%s', date('YmdHis'), $uid, $safe, $ext);
          $dest = $dir.'/'.$name;
          if (move_uploaded_file($tmp, $dest)) {
            $web = '/uploads/daily/'.$name;
            $imgStmt->execute([$daily_id, $web]);
          }
        }
      }

      $msg = 'Laporan harian berjaya dihantar.';
    } catch (Throwable $e) {
      $err = 'Gagal menyimpan: '.$e->getMessage();
    }
  }
}

include __DIR__.'/../app/header.php';
?>
<h2>Daily Log</h2>
<?php if($msg):?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if($err):?><p class="error"><?= h($err) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <label>DAY
      <input name="hari" id="hari" placeholder="cth: Isnin">
    </label>
    <label>DATE
      <input type="date" name="log_date" id="log_date" required>
    </label>
  </div>

  <label>PROJECT
    <textarea name="tugas" rows="2" required></textarea>
  </label>

  <label>OBJECTIVE
    <textarea name="objektif" rows="3" required></textarea>
  </label>

  <label>TOOLS (optional)
    <textarea name="peralatan" rows="2"></textarea>
  </label>

  <label>WORK PROCEDURE
    <textarea name="prosedur" rows="8" required></textarea>
  </label>

  <label>CONCLUSION
    <textarea name="kesimpulan" rows="5" required></textarea>
  </label>

  <label>UPLOAD PHOTOS
    <input type="file" name="images[]" accept="image/*" multiple>
  </label>

  <button class="btn">Submit</button>
</form>

<p><a href="<?= url('/student/daily_list.php') ?>">See my recorded reports</a></p>
<p><a href="<?= url('/student/dashboard.php') ?>">Back to Dashboard</a></p>

<script>
// Auto-fill "HARI" from TARIKH in Malay
(function(){
  const days = ['Ahad','Isnin','Selasa','Rabu','Khamis','Jumaat','Sabtu'];
  const d = document.getElementById('log_date');
  const h = document.getElementById('hari');
  if (d && h) d.addEventListener('change', () => {
    const val = d.value;
    if (!val) return;
    const dt = new Date(val+'T12:00:00'); // avoid TZ edge
    h.value = days[dt.getDay()] || '';
  });
})();
</script>

</main></body></html>
