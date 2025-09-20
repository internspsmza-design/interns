<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['supervisor']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
$tbl='leaves'; $uid=(int)$_SESSION['user']['id'];
$dateCol = col_exists($pdo,$tbl,'leave_date') ? 'leave_date' : (col_exists($pdo,$tbl,'date') ? 'date' : 'created_at');
$daysCol = col_exists($pdo,$tbl,'days') ? 'days' : (col_exists($pdo,$tbl,'num_days') ? 'num_days' : (col_exists($pdo,$tbl,'no_of_days') ? 'no_of_days' : null));

if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0);
  $act=$_POST['action'] ?? '';
  $cmt=trim($_POST['supervisor_comment'] ?? '');
  $now=date('Y-m-d H:i:s');
  $sigPath=null;

  if(!empty($_FILES['signature']['name'])){
    $dir=__DIR__.'/../uploads/signatures'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    if($_FILES['signature']['error']===UPLOAD_ERR_OK){
      $tmp=$_FILES['signature']['tmp_name']; $fi=new finfo(FILEINFO_MIME_TYPE); $mime=$fi->file($tmp);
      if(strpos($mime,'image/')===0 && filesize($tmp)<=5*1024*1024){
        $ext=strtolower(pathinfo($_FILES['signature']['name'],PATHINFO_EXTENSION));
        $name=sprintf('sig_l_%d_%s.%s',$uid,date('YmdHis'),$ext);
        if(move_uploaded_file($tmp,$dir.'/'.$name)) $sigPath='/uploads/signatures/'.$name;
      }
    }
  }

  $set=[]; $vals=[];
  if(col_exists($pdo,$tbl,'supervisor_comment'))   {$set[]='supervisor_comment=?';   $vals[]=$cmt;}
  if(col_exists($pdo,$tbl,'supervisor_id'))        {$set[]='supervisor_id=?';        $vals[]=$uid;}
  if(col_exists($pdo,$tbl,'supervisor_signed_at') && $act==='approve') {$set[]='supervisor_signed_at=?'; $vals[]=$now;}
  if($sigPath && col_exists($pdo,$tbl,'supervisor_signature_path')) {$set[]='supervisor_signature_path=?'; $vals[]=$sigPath;}
  if(col_exists($pdo,$tbl,'status')){
    $set[]='status=?'; $vals[] = ($act==='reject') ? 'rejected' : 'approved';
  }

  if($id && $set){
    $vals[]=$id; $sql="UPDATE `$tbl` SET ".implode(',',$set)." WHERE id=?";
    $pdo->prepare($sql)->execute($vals);
  }
}

$filter=$_GET['status']??'pending';
$where=''; $bind=[];
if(col_exists($pdo,$tbl,'status') && in_array($filter,['pending','approved','rejected','all'],true)){
  if($filter!=='all'){ $where='WHERE l.status=?'; $bind[]=$filter; }
}
$st=$pdo->prepare("SELECT l.*, u.name FROM `$tbl` l JOIN users u ON u.id=l.user_id $where ORDER BY l.`$dateCol` DESC, l.id DESC");
$st->execute($bind); $rows=$st->fetchAll();

include __DIR__.'/../app/header.php';
?>
<h2>Verify Leave Requests (Supervisor)</h2>

<form method="get" style="margin:.5rem 0">
  <label>Status
    <select name="status" onchange="this.form.submit()">
      <option value="pending"  <?= $filter==='pending'?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="all"      <?= $filter==='all'?'selected':'' ?>>All</option>
    </select>
  </label>
</form>

<?php if(!$rows): ?><p>No leave requests found.</p>
<?php else: ?>
<table class="tbl">
  <tr>
    <th>Student</th>
    <th>Date</th>
    <?php if($daysCol): ?><th>No. of days</th><?php endif; ?>
    <th>Reason</th>
    <th>Status</th>
    <th>Supervisor section</th>
    <th>PDF</th>
  </tr>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= h($r['name']) ?></td>
      <td><?= h($r[$dateCol] ?? '') ?></td>
      <?php if($daysCol): ?><td><?= h($r[$daysCol] ?? '') ?></td><?php endif; ?>
      <td style="max-width:360px"><?= nl2br(h($r['reason'] ?? '')) ?></td>
      <td><?= h($r['status'] ?? '') ?></td>
      <td>
        <?php if(($r['status'] ?? 'pending')==='pending'): ?>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input name="supervisor_comment" placeholder="Comment" style="min-width:220px">
            <input type="file" name="signature" accept="image/*">
            <button class="btn" name="action" value="approve">Sign / Approve</button>
            <button class="btn" name="action" value="reject">Reject</button>
          </form>
        <?php else: ?>
          <?php if(!empty($r['supervisor_comment'])): ?><div><strong>Comment:</strong> <?= nl2br(h($r['supervisor_comment'])) ?></div><?php endif; ?>
          <?php if(!empty($r['supervisor_signed_at'])): ?><div><strong>Signed:</strong> <?= h($r['supervisor_signed_at']) ?></div><?php endif; ?>
          <?php if(!empty($r['supervisor_signature_path'])): ?><img src="<?= url($r['supervisor_signature_path']) ?>" style="height:36px;margin-top:.25rem"><?php endif; ?>
        <?php endif; ?>
      </td>
      <td><a class="btn" target="_blank" href="<?= url('/student/leave_pdf.php?id='.(int)$r['id']) ?>">PDF</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
</main></body></html>
