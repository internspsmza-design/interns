<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
$tbl='leaves'; $uid=(int)$_SESSION['user']['id'];
$dateCol = col_exists($pdo,$tbl,'leave_date') ? 'leave_date' : (col_exists($pdo,$tbl,'date') ? 'date' : 'created_at');
$daysCol = col_exists($pdo,$tbl,'days') ? 'days' : (col_exists($pdo,$tbl,'num_days') ? 'num_days' : (col_exists($pdo,$tbl,'no_of_days') ? 'no_of_days' : null));

$st=$pdo->prepare("SELECT * FROM `$tbl` WHERE user_id=? ORDER BY `$dateCol` ASC, id ASC"); $st->execute([$uid]); $rows=$st->fetchAll();
$base=rtrim((require __DIR__.'/../app/config.php')['APP_BASE'],'/');

ob_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><title>Student Leave Record</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:28px;font-size:12px;color:#111}
 h1{font-size:18px;text-align:center;margin:0 0 12px}
 table{width:100%;border-collapse:collapse}
 th,td{border:1px solid #222;padding:6px 8px;vertical-align:top}
 th{text-transform:uppercase}
 .sig{height:28px}
 @media print{.noprint{display:none}}
</style></head><body>
<div class="noprint" style="text-align:right;margin-bottom:6px"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>STUDENT LEAVE RECORD</h1>
<table>
  <tr>
    <th style="width:40px">No.</th>
    <th style="width:120px">Date</th>
    <th>Reason</th>
    <th style="width:80px">No. of days</th>
    <th style="width:160px">Officer signature</th>
  </tr>
  <?php $i=1; foreach($rows as $r): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= h($r[$dateCol] ?? '') ?></td>
      <td><?= nl2br(h($r['reason'] ?? '')) ?></td>
      <td><?= $daysCol ? h($r[$daysCol] ?? '') : '' ?></td>
      <td>
        <?php if(!empty($r['supervisor_signature_path'])): ?>
          <img class="sig" src="<?= h($base.$r['supervisor_signature_path']) ?>">
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</body></html>
<?php
$html=ob_get_clean();
$autoload=__DIR__.'/../vendor/autoload.php';
if(file_exists($autoload)){
  require_once $autoload;
  $opt=new Dompdf\Options(); $opt->set('isRemoteEnabled',true); $opt->set('isHtml5ParserEnabled',true);
  $pdf=new Dompdf\Dompdf($opt); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','portrait'); $pdf->render();
  $pdf->stream("leave_record.pdf",['Attachment'=>false]); exit;
}
echo $html;
