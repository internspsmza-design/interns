<?php
declare(strict_types=1);
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

require_once __DIR__.'/../app/auth.php';  require_role(['supervisor']);
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

$tbl    = table_exists($pdo,'weekly_reports') ? 'weekly_reports' : 'weekly_reports'; // required
$imgTbl = table_exists($pdo,'weekly_images')  ? 'weekly_images'  : null;             // optional
$uid    = (int)($_SESSION['user']['id'] ?? 0);

// pick best date/week columns available
$dateCol = col_exists($pdo,$tbl,'report_date') ? 'report_date' : (col_exists($pdo,$tbl,'date') ? 'date' : 'created_at');
$weekCol = col_exists($pdo,$tbl,'week_no') ? 'week_no' : (col_exists($pdo,$tbl,'week') ? 'week' : null);

// Handle supervisor sign/comment
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id  = (int)($_POST['id'] ?? 0);
  $cmt = trim($_POST['supervisor_comment'] ?? '');
  $now = date('Y-m-d H:i:s');
  $sigPath = null;

  // signature upload (optional)
  if(!empty($_FILES['signature']['name'])){
    $dir = __DIR__.'/../uploads/signatures';
    if(!is_dir($dir)) @mkdir($dir,0775,true);
    if($_FILES['signature']['error']===UPLOAD_ERR_OK){
      $tmp = $_FILES['signature']['tmp_name'];
      $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
      if(strpos($mime,'image/')===0 && filesize($tmp) <= 5*1024*1024){
        $ext = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
        $name = sprintf('sig_w_%d_%s.%s',$uid,date('YmdHis'),$ext);
        if(move_uploaded_file($tmp,$dir.'/'.$name)) $sigPath = '/uploads/signatures/'.$name;
      }
    }
  }

  // Only supervisor columns get updated
  $set=[]; $vals=[];
  if(col_exists($pdo,$tbl,'supervisor_comment'))   { $set[]='supervisor_comment=?';   $vals[]=$cmt; }
  if(col_exists($pdo,$tbl,'supervisor_id'))        { $set[]='supervisor_id=?';        $vals[]=$uid; }
  if(col_exists($pdo,$tbl,'supervisor_signed_at')) { $set[]='supervisor_signed_at=?'; $vals[]=$now; }
  if($sigPath && col_exists($pdo,$tbl,'supervisor_signature_path')) { $set[]='supervisor_signature_path=?'; $vals[]=$sigPath; }
  if(col_exists($pdo,$tbl,'status'))               { $set[]='status=?';               $vals[]='approved'; }

  if($id && $set){
    $vals[]=$id;
    $sql="UPDATE `$tbl` SET ".implode(',',$set)." WHERE id=?";
    $pdo->prepare($sql)->execute($vals);
  }
}

// List reports
$filter = $_GET['status'] ?? 'submitted';
$where  = ''; $bind=[];
if(col_exists($pdo,$tbl,'status') && in_array($filter,['submitted','approved','rejected','all'],true)){
  if($filter!=='all'){ $where = "WHERE status=?"; $bind[]=$filter; }
}
$st = $pdo->prepare("SELECT * FROM `$tbl` $where ORDER BY `$dateCol` DESC, id DESC");
$st->execute($bind); $rows = $st->fetchAll();

$si = null; $fk = null;
if ($imgTbl){
  $fk = col_exists($pdo,$imgTbl,'weekly_id') ? 'weekly_id' : 'weekly_id';
  $si = $pdo->prepare("SELECT path FROM `$imgTbl` WHERE `$fk`=? ORDER BY id");
}

include __DIR__.'/../app/header.php';
?>
<h2>Verify Weekly Reflections (Supervisor)</h2>

<form method="get" style="margin:.5rem 0">
  <label>Status
    <select name="status" onchange="this.form.submit()">
      <option value="submitted" <?= $filter==='submitted'?'selected':'' ?>>Pending</option>
      <option value="approved"  <?= $filter==='approved'?'selected':'' ?>>Signed/approved</option>
      <option value="rejected"  <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="all"       <?= $filter==='all'?'selected':'' ?>>All</option>
    </select>
  </label>
</form>

<?php if(!$rows): ?>
  <p>No weekly reports found.</p>
<?php else: ?>
  <table class="tbl">
    <tr>
      <th>Date</th>
      <?php if($weekCol): ?><th>Week</th><?php endif; ?>
      <?php if(col_exists($pdo,$tbl,'activity_summary')): ?><th>Activity</th><?php endif; ?>
      <?php if(col_exists($pdo,$tbl,'skills_gained')): ?><th>Skills</th><?php endif; ?>
      <?php if(col_exists($pdo,$tbl,'impact_on_student')): ?><th>Impact</th><?php endif; ?>
      <th>Images</th>
      <?php if(col_exists($pdo,$tbl,'status')): ?><th>Status</th><?php endif; ?>
      <th>Supervisor</th>
      <th>PDF</th>
    </tr>
    <?php foreach($rows as $r): ?>
      <?php
        $imgs=[];
        if($si){ $si->execute([$r['id']]); $imgs = $si->fetchAll(PDO::FETCH_COLUMN); }
      ?>
      <tr>
        <td><?= h($r[$dateCol] ?? '') ?></td>
        <?php if($weekCol): ?><td><?= h($r[$weekCol] ?? '') ?></td><?php endif; ?>
        <td><?= isset($r['activity_summary'])?nl2br(h($r['activity_summary'])):'—' ?></td>
        <td><?= isset($r['skills_gained'])?nl2br(h($r['skills_gained'])):'—' ?></td>
        <td><?= isset($r['impact_on_student'])?nl2br(h($r['impact_on_student'])):'—' ?></td>
        <td>
          <?php if($imgs): foreach($imgs as $p): ?>
            <a href="<?= url($p) ?>" target="_blank"><img src="<?= url($p) ?>" style="height:40px;object-fit:cover;margin-right:.2rem;border-radius:.2rem"></a>
          <?php endforeach; else: ?>—<?php endif; ?>
        </td>
        <td><?= h($r['status'] ?? '') ?></td>
        <td>
          <?php if(($r['status'] ?? 'submitted')==='submitted'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="supervisor_comment" placeholder="Comment / suggestion" style="min-width:220px">
              <input type="file" name="signature" accept="image/*">
              <button class="btn">Sign / Approve</button>
            </form>
          <?php else: ?>
            <?php if(!empty($r['supervisor_comment'])): ?><div><strong>Comment:</strong> <?= nl2br(h($r['supervisor_comment'])) ?></div><?php endif; ?>
            <?php if(!empty($r['supervisor_signed_at'])): ?><div><strong>Signed:</strong> <?= h($r['supervisor_signed_at']) ?></div><?php endif; ?>
            <?php if(!empty($r['supervisor_signature_path'])): ?><img src="<?= url($r['supervisor_signature_path']) ?>" style="height:36px;margin-top:.25rem"><?php endif; ?>
          <?php endif; ?>
        </td>
        <td><a class="btn" target="_blank" href="<?= url('/student/weekly_pdf.php?id='.(int)$r['id']) ?>">PDF</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

</main></body></html>
