<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
$id=(int)($_GET['id']??0); $uid=(int)$_SESSION['user']['id'];
$tbl='leaves';
$dateCol = col_exists($pdo,$tbl,'leave_date') ? 'leave_date' : (col_exists($pdo,$tbl,'date') ? 'date' : 'created_at');
$daysCol = col_exists($pdo,$tbl,'days') ? 'days' : (col_exists($pdo,$tbl,'num_days') ? 'num_days' : (col_exists($pdo,$tbl,'no_of_days') ? 'no_of_days' : null));

$st=$pdo->prepare("SELECT * FROM `$tbl` WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$id,$uid]); $row=$st->fetch(); if(!$row){ http_response_code(404); echo 'Not found'; exit; }

$base = rtrim((require __DIR__.'/../app/config.php')['APP_BASE'],'/');

ob_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><title>Leave Request</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:28px;font-size:13px;color:#111}
 h1{font-size:20px;margin:0 0 8px}
 .label{font-weight:700;margin-top:8px}.box{border:1px solid #bbb;border-radius:6px;padding:8px;min-height:38px}
 .row{display:flex;gap:12px}.col{flex:1} .badge{background:#eee;border-radius:999px;padding:2px 8px;font-size:12px;display:inline-block}
 @media print{.noprint{display:none}}
</style></head><body>
<div class="noprint" style="text-align:right;margin-bottom:6px"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>Leave Request</h1>
<?php if(isset($row['status'])): ?><div class="badge">Status: <?= h($row['status']) ?></div><?php endif; ?>

<div class="row" style="margin-top:8px">
  <div class="col"><div class="label">Date</div><div class="box"><?= h($row[$dateCol]??'') ?></div></div>
  <?php if($daysCol): ?><div class="col"><div class="label">No. of days</div><div class="box"><?= h($row[$daysCol]??'') ?></div></div><?php endif; ?>
</div>

<div class="label">Reason</div><div class="box"><?= nl2br(h($row['reason']??'')) ?></div>

<?php if(!empty($row['supervisor_comment']) || !empty($row['supervisor_signed_at']) || !empty($row['supervisor_signature_path'])): ?>
  <div class="label">Supervisor section</div>
  <div class="box">
    <?php if(!empty($row['supervisor_comment'])): ?><div><strong>Comment:</strong> <?= nl2br(h($row['supervisor_comment'])) ?></div><?php endif; ?>
    <?php if(!empty($row['supervisor_signed_at'])): ?><div><strong>Signed at:</strong> <?= h($row['supervisor_signed_at']) ?></div><?php endif; ?>
    <?php if(!empty($row['supervisor_signature_path'])): ?><div style="margin-top:6px"><img src="<?= h($base.$row['supervisor_signature_path']) ?>" style="height:60px"></div><?php endif; ?>
  </div>
<?php endif; ?>
</body></html>
<?php
$html=ob_get_clean();
$autoload=__DIR__.'/../vendor/autoload.php';
if(file_exists($autoload)){
  require_once $autoload;
  $opt=new Dompdf\Options(); $opt->set('isRemoteEnabled',true); $opt->set('isHtml5ParserEnabled',true);
  $pdf=new Dompdf\Dompdf($opt); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','portrait'); $pdf->render();
  $pdf->stream("leave_$id.pdf",['Attachment'=>false]); exit;
}
echo $html;
