<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['supervisor']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo,string $t,string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}

$dailyTbl = table_exists($pdo,'daily_logs') ? 'daily_logs' : (table_exists($pdo,'logs') ? 'logs' : 'daily_logs');
$imgTbl   = table_exists($pdo,'daily_images') ? 'daily_images' : (table_exists($pdo,'log_images') ? 'log_images' : 'daily_images');
$dateCol  = col_exists($pdo,$dailyTbl,'log_date') ? 'log_date' : (col_exists($pdo,$dailyTbl,'date') ? 'date' : 'created_at');
$uid=(int)$_SESSION['user']['id'];

// handle sign/comment
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0);
  $cmt=trim($_POST['comment']??'');
  $now=date('Y-m-d H:i:s');
  $sigPath=null;

  if(!empty($_FILES['signature']['name'])){
    $dir=__DIR__.'/../uploads/signatures'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    if($_FILES['signature']['error']===UPLOAD_ERR_OK){
      $tmp=$_FILES['signature']['tmp_name']; $fi=new finfo(FILEINFO_MIME_TYPE); $mime=$fi->file($tmp);
      if(strpos($mime,'image/')===0 && filesize($tmp)<=5*1024*1024){
        $ext=strtolower(pathinfo($_FILES['signature']['name'],PATHINFO_EXTENSION));
        $name=sprintf('sig_d_%d_%s.%s',$uid,date('YmdHis'),$ext);
        if(move_uploaded_file($tmp,$dir.'/'.$name)) $sigPath='/uploads/signatures/'.$name;
      }
    }
  }

  $set=[]; $vals=[];
  if(col_exists($pdo,$dailyTbl,'reviewer_comment')) {$set[]='reviewer_comment=?'; $vals[]=$cmt;}
  if(col_exists($pdo,$dailyTbl,'reviewer_id'))      {$set[]='reviewer_id=?';      $vals[]=$uid;}
  if(col_exists($pdo,$dailyTbl,'supervisor_signed_at')) {$set[]='supervisor_signed_at=?'; $vals[]=$now;}
  if($sigPath && col_exists($pdo,$dailyTbl,'supervisor_signature_path')) {$set[]='supervisor_signature_path=?'; $vals[]=$sigPath;}
  if(col_exists($pdo,$dailyTbl,'status')) {$set[]='status=?'; $vals[]='approved';}

  if($id && $set){ $vals[]=$id; $sql="UPDATE `$dailyTbl` SET ".implode(',',$set)." WHERE id=?"; $pdo->prepare($sql)->execute($vals); }
}

// listing
$filter=$_GET['status']??'submitted'; $where=''; $bind=[];
if(col_exists($pdo,$dailyTbl,'status') && in_array($filter,['submitted','approved','rejected','all'],true)){
  if($filter!=='all'){ $where='WHERE d.status=?'; $bind[]=$filter; }
}
$st=$pdo->prepare("SELECT d.*,u.name FROM `$dailyTbl` d JOIN users u ON u.id=d.user_id $where ORDER BY d.`$dateCol` DESC, d.id DESC");
$st->execute($bind); $rows=$st->fetchAll();

$fk=col_exists($pdo,$imgTbl,'daily_id')?'daily_id':(col_exists($pdo,$imgTbl,'log_id')?'log_id':'daily_id');
$si=$pdo->prepare("SELECT path FROM `$imgTbl` WHERE `$fk`=? ORDER BY id");

include __DIR__.'/../app/header.php';
?>
<h2>Verify Daily Logs (Supervisor)</h2>

<form method="get" style="margin:.5rem 0">
  <label>Status
    <select name="status" onchange="this.form.submit()">
      <option value="submitted" <?= $filter==='submitted'?'selected':'' ?>>Pending</option>
      <option value="approved"  <?= $filter==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected"  <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="all"       <?= $filter==='all'?'selected':'' ?>>All</option>
    </select>
  </label>
</form>

<?php if(!$rows): ?>
  <p>No daily logs found.</p>
<?php else: ?>
  <table class="tbl">
    <tr>
      <th>Student</th><th>Date</th><th>Task</th><th>Objective</th><th>Procedure</th><th>Images</th><th>Status</th><th>Supervisor</th>
    </tr>
    <?php foreach($rows as $r): $si->execute([$r['id']]); $imgs=$si->fetchAll(PDO::FETCH_COLUMN); ?>
      <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r[$dateCol] ?? '') ?></td>
        <td><?= isset($r['tugas'])?nl2br(h($r['tugas'])): (isset($r['activity'])?nl2br(h($r['activity'])):'—') ?></td>
        <td><?= isset($r['objektif'])?nl2br(h($r['objektif'])):'—' ?></td>
        <td style="max-width:360px"><?= isset($r['prosedur'])?nl2br(h($r['prosedur'])):'—' ?></td>
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
              <input name="comment" placeholder="Supervisor comment" style="min-width:220px">
              <input type="file" name="signature" accept="image/*">
              <button class="btn">Sign / Approve</button>
            </form>
          <?php else: ?>
            <?php if(!empty($r['reviewer_comment'])): ?><div><strong>Comment:</strong> <?= nl2br(h($r['reviewer_comment'])) ?></div><?php endif; ?>
            <?php if(!empty($r['supervisor_signed_at'])): ?><div><strong>Signed:</strong> <?= h($r['supervisor_signed_at']) ?></div><?php endif; ?>
            <?php if(!empty($r['supervisor_signature_path'])): ?><img src="<?= url($r['supervisor_signature_path']) ?>" style="height:36px;margin-top:.25rem"><?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</main></body></html>
