<?php
// student/daily_pdf.php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

$id  = (int)($_GET['id'] ?? 0);
$uid = (int)($_SESSION['user']['id'] ?? 0);

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
$dateCol    = col_exists($pdo,$dailyTable,'log_date') ? 'log_date' : (col_exists($pdo,$dailyTable,'date') ? 'date' : 'created_at');
$fk         = col_exists($pdo,$imgTable,'daily_id') ? 'daily_id' : (col_exists($pdo,$imgTable,'log_id') ? 'log_id' : 'daily_id');

// fetch the entry (owned by current user)
$st = $pdo->prepare("SELECT * FROM `$dailyTable` WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$id,$uid]);
$log = $st->fetch();
if (!$log) { http_response_code(404); echo "Not found"; exit; }

// images
$si = $pdo->prepare("SELECT path FROM `$imgTable` WHERE `$fk`=? ORDER BY id ASC");
$si->execute([$id]);
$imgs = $si->fetchAll(PDO::FETCH_COLUMN);

$hari       = $log['hari']        ?? '';
$tarikh     = $log[$dateCol]      ?? '';
$tugas      = $log['tugas']       ?? ($log['activity'] ?? '');
$objektif   = $log['objektif']    ?? '';
$peralatan  = $log['peralatan']   ?? '';
$prosedur   = $log['prosedur']    ?? '';
$kesimpulan = $log['kesimpulan']  ?? '';
$status     = $log['status']      ?? '';

$config = require __DIR__.'/../app/config.php';
$base   = rtrim($config['APP_BASE'],'/');

// convert web path (/uploads/...) to disk path for Dompdf images
function web_to_disk(string $web): string {
  $clean = ltrim($web,'/');
  return realpath(__DIR__ . '/../' . $clean) ?: (__DIR__ . '/../' . $clean);
}

// Build HTML for PDF/print
ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Laporan Harian</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; margin:28px; font-size:13px; color:#111}
  h1{font-size:20px;margin:0 0 8px}
  .meta{margin-bottom:10px}
  .row{display:flex;gap:12px;margin-bottom:8px}
  .col{flex:1}
  .label{font-weight:700;margin-bottom:4px}
  .box{border:1px solid #bbb; border-radius:6px; padding:8px; min-height:44px}
  table{width:100%; border-collapse:collapse; margin-top:10px}
  th,td{border:1px solid #bbb; padding:6px 8px; vertical-align:top}
  .imgs{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .imgs img{height:120px; object-fit:cover; border:1px solid #bbb; border-radius:4px}
  .badge{display:inline-block; padding:2px 8px; border-radius:999px; background:#eee; font-size:12px}
  @media print { .noprint{display:none} }
</style>
</head>
<body>
  <div class="noprint" style="text-align:right;margin-bottom:6px">
    <button onclick="window.print()">Print / Save as PDF</button>
  </div>

  <h1>Laporan Harian</h1>
  <div class="meta">
    <?php if($status!==''): ?><span class="badge">Status: <?= htmlspecialchars($status) ?></span><?php endif; ?>
  </div>

  <div class="row">
    <div class="col">
      <div class="label">HARI</div>
      <div class="box"><?= htmlspecialchars($hari) ?></div>
    </div>
    <div class="col">
      <div class="label">TARIKH</div>
      <div class="box"><?= htmlspecialchars($tarikh) ?></div>
    </div>
  </div>

  <div class="label">TUGAS/AKTIVITI/PROJEK</div>
  <div class="box"><?= nl2br(htmlspecialchars($tugas)) ?></div>

  <div class="label" style="margin-top:8px">OBJEKTIF</div>
  <div class="box"><?= nl2br(htmlspecialchars($objektif)) ?></div>

  <div class="label" style="margin-top:8px">PERALATAN (jika perlu)</div>
  <div class="box"><?= nl2br(htmlspecialchars($peralatan)) ?></div>

  <div class="label" style="margin-top:8px">PROSEDUR KERJA</div>
  <div class="box"><?= nl2br(htmlspecialchars($prosedur)) ?></div>

  <div class="label" style="margin-top:8px">KESIMPULAN</div>
  <div class="box"><?= nl2br(htmlspecialchars($kesimpulan)) ?></div>

  <?php if($imgs): ?>
    <div class="label" style="margin-top:8px">RAJAH / GAMBAR</div>
    <div class="imgs">
      <?php foreach($imgs as $p):
        $src = $base . $p; ?>
        <img src="<?= htmlspecialchars($src) ?>" alt="">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

// If Dompdf is available, render a real PDF
$dompdfAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($dompdfAutoload)) {
  require_once $dompdfAutoload;
  $opts = new Dompdf\Options();
  $opts->set('isRemoteEnabled', true);
  $opts->set('isHtml5ParserEnabled', true);
  $dompdf = new Dompdf\Dompdf($opts);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream("daily_log_$id.pdf", ['Attachment'=>false]); // inline
  exit;
}

// Fallback: show the print view HTML (user can Save as PDF)
echo $html;
