<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}

$id  = (int)($_GET['id'] ?? 0);
$uid = (int)($_SESSION['user']['id'] ?? 0);
$tbl = 'weekly_reports';
$imgTbl = 'weekly_images';

$dateCol = col_exists($pdo,$tbl,'report_date') ? 'report_date' : (col_exists($pdo,$tbl,'date') ? 'date' : 'created_at');
$weekCol = col_exists($pdo,$tbl,'week_no') ? 'week_no' : (col_exists($pdo,$tbl,'week') ? 'week' : null);

$st=$pdo->prepare("SELECT * FROM `$tbl` WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$id,$uid]);
$w=$st->fetch(); if(!$w){ http_response_code(404); echo 'Not found'; exit; }

$fk = col_exists($pdo,$imgTbl,'weekly_id') ? 'weekly_id' : 'weekly_id';
$si=$pdo->prepare("SELECT path FROM `$imgTbl` WHERE `$fk`=? ORDER BY id");
$si->execute([$id]); $imgs=$si->fetchAll(PDO::FETCH_COLUMN);

$base = rtrim((require __DIR__.'/../app/config.php')['APP_BASE'],'/');

ob_start(); ?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Weekly Reflection</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:28px;font-size:13px;color:#111}
 h1{font-size:20px;margin:0 0 8px} .label{font-weight:700;margin-top:8px}
 .box{border:1px solid #bbb;border-radius:6px;padding:8px;min-height:44px}
 .row{display:flex;gap:12px} .col{flex:1}
 .imgs{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
 .imgs img{height:120px;object-fit:cover;border:1px solid #bbb;border-radius:4px}
 .badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#eee;font-size:12px}
 @media print{.noprint{display:none}}
</style>
</head>
<body>
  <div class="noprint" style="text-align:right;margin-bottom:6px">
    <button onclick="window.print()">Print / Save as PDF</button>
  </div>

  <h1>Weekly Reflection</h1>
  <div><?php if(isset($w['status'])): ?><span class="badge">Status: <?= htmlspecialchars($w['status']) ?></span><?php endif; ?></div>

  <div class="row" style="margin-top:8px">
    <div class="col"><div class="label">Date</div><div class="box"><?= htmlspecialchars($w[$dateCol] ?? '') ?></div></div>
    <?php if($weekCol): ?><div class="col"><div class="label">Week</div><div class="box"><?= htmlspecialchars($w[$weekCol] ?? '') ?></div></div><?php endif; ?>
  </div>

  <?php if(isset($w['activity_summary'])): ?><div class="label">Weekly activities carried out (brief)</div>
  <div class="box"><?= nl2br(htmlspecialchars($w['activity_summary'])) ?></div><?php endif; ?>

  <?php if(isset($w['skills_gained'])): ?><div class="label">Knowledge/skills acquired (during the week)</div>
  <div class="box"><?= nl2br(htmlspecialchars($w['skills_gained'])) ?></div><?php endif; ?>

  <?php if(isset($w['impact_on_student'])): ?><div class="label">Impact and effects on the student</div>
  <div class="box"><?= nl2br(htmlspecialchars($w['impact_on_student'])) ?></div><?php endif; ?>

  <?php if($imgs): ?><div class="label">Images</div>
    <div class="imgs">
      <?php foreach($imgs as $p): ?><img src="<?= htmlspecialchars($base.$p) ?>" alt=""><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if(!empty($w['supervisor_comment']) || !empty($w['supervisor_signature_path'])): ?>
    <div class="label">Supervisor section</div>
    <div class="box">
      <?php if(!empty($w['supervisor_comment'])): ?>
        <div><strong>Comment: </strong><?= nl2br(htmlspecialchars($w['supervisor_comment'])) ?></div>
      <?php endif; ?>
      <?php if(!empty($w['supervisor_signed_at'])): ?>
        <div><strong>Signed at: </strong><?= htmlspecialchars($w['supervisor_signed_at']) ?></div>
      <?php endif; ?>
      <?php if(!empty($w['supervisor_signature_path'])): ?>
        <div style="margin-top:6px"><img src="<?= htmlspecialchars($base.$w['supervisor_signature_path']) ?>" style="height:60px"></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();
$autoload = __DIR__.'/../vendor/autoload.php';
if(file_exists($autoload)){
  require_once $autoload;
  $opt=new Dompdf\Options(); $opt->set('isRemoteEnabled',true); $opt->set('isHtml5ParserEnabled',true);
  $pdf=new Dompdf\Dompdf($opt); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','portrait'); $pdf->render();
  $pdf->stream("weekly_$id.pdf",['Attachment'=>false]); exit;
}
echo $html;
