<?php
declare(strict_types=1);
require_once __DIR__.'/../app/auth.php';  require_role(['student']);
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/helpers.php';

function col_exists(PDO $pdo, string $t, string $c): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
  $st->execute([$t,$c]); return (bool)$st->fetchColumn();
}
function table_exists(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}

$tbl = 'weekly_reports';
$imgTable = table_exists($pdo,'weekly_images') ? 'weekly_images' : 'weekly_images';
$uid = (int)($_SESSION['user']['id'] ?? 0);

$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $report_date = $_POST['report_date'] ?? '';
  $week_no     = (int)($_POST['week_no'] ?? 0);
  $activity    = trim($_POST['activity_summary'] ?? '');
  $skills      = trim($_POST['skills_gained'] ?? '');
  $impact      = trim($_POST['impact_on_student'] ?? '');

  if(!$report_date || !$week_no || !$activity || !$skills || !$impact){
    $err = 'Please fill in Date, Week, Activity Summary, Skills Gained and Impact.';
  }

  if(!$err){
    try{
      $base = [
        'user_id'          => $uid,
        'report_date'      => $report_date,
        'week_no'          => $week_no,
        'activity_summary' => $activity,
        'skills_gained'    => $skills,
        'impact_on_student'=> $impact,
        'status'           => 'submitted',
      ];
      // backward-compat mappings if your columns differ
      if(col_exists($pdo,$tbl,'date'))     $base['date'] = $report_date;
      if(col_exists($pdo,$tbl,'week'))     $base['week'] = $week_no;
      if(col_exists($pdo,$tbl,'summary'))  $base['summary'] = $activity;
      if(col_exists($pdo,$tbl,'knowledge'))$base['knowledge'] = $skills;
      if(col_exists($pdo,$tbl,'impact'))   $base['impact'] = $impact;

      $payload=[];
      foreach($base as $k=>$v) if(col_exists($pdo,$tbl,$k)) $payload[$k]=$v;

      foreach (['user_id','report_date'] as $must)
        if(!col_exists($pdo,$tbl,$must)) throw new RuntimeException("Missing column `$must` in weekly_reports.");

      $cols=implode(',',array_map(fn($c)=>"`$c`",array_keys($payload)));
      $qst =implode(',',array_fill(0,count($payload),'?'));
      $st=$pdo->prepare("INSERT INTO `$tbl` ($cols) VALUES ($qst)");
      $st->execute(array_values($payload));
      $wid=(int)$pdo->lastInsertId();

      // images
      if(!empty($_FILES['images']['name'][0])){
        $dir = __DIR__.'/../uploads/weekly';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $fk = col_exists($pdo,$imgTable,'weekly_id') ? 'weekly_id' : 'weekly_id';
        $ist=$pdo->prepare("INSERT INTO `$imgTable` (`$fk`,`path`) VALUES (?,?)");

        $files=$_FILES['images']; $fi = new finfo(FILEINFO_MIME_TYPE);
        for($i=0;$i<count($files['name']);$i++){
          if($files['error'][$i]!==UPLOAD_ERR_OK) continue;
          $tmp=$files['tmp_name'][$i]; $mime=$fi->file($tmp);
          if(strpos($mime,'image/')!==0) continue;
          if(filesize($tmp)>5*1024*1024) continue;

          $ext=strtolower(pathinfo($files['name'][$i],PATHINFO_EXTENSION));
          $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',pathinfo($files['name'][$i],PATHINFO_FILENAME));
          $name=sprintf('w_%d_%s_%s.%s',$uid,date('YmdHis'),$safe,$ext);
          $dest=$dir.'/'.$name;
          if(move_uploaded_file($tmp,$dest)){
            $ist->execute([$wid, '/uploads/weekly/'.$name]);
          }
        }
      }

      $msg='Weekly reflection submitted.';
    }catch(Throwable $e){ $err='Save failed: '.$e->getMessage(); }
  }
}

include __DIR__.'/../app/header.php';
?>
<h2>Weekly Reflection</h2>
<?php if($msg):?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if($err):?><p class="error"><?= h($err) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <label>Date <input type="date" name="report_date" required></label>
    <label>Week <input type="number" name="week_no" min="1" step="1" required></label>
  </div>
  <fieldset style="margin-top:12px;border:1px solid var(--line);border-radius:.5rem">
    <legend>To be completed by Student</legend>
    <label>Weekly activities carried out (brief)
      <textarea name="activity_summary" rows="6" required></textarea></label>
    <label>Knowledge/skills acquired (during the week)
      <textarea name="skills_gained" rows="6" required></textarea></label>
    <label>Impact and effects on the student
      <textarea name="impact_on_student" rows="6" required></textarea></label>
  </fieldset>

  <label style="margin-top:10px">Images (you may select multiple)
    <input type="file" name="images[]" accept="image/*" multiple>
  </label>

  <p style="opacity:.8;margin:.5rem 0 1rem"><em>The section below is for the Industry Supervisor and is not editable here.</em></p>
  <button class="btn">Submit</button>
</form>

<p><a href="<?= url('/student/weekly_list.php') ?>">My weekly reflections</a></p>
<p><a href="<?= url('/student/dashboard.php') ?>">Back to dashboard</a></p>
</main></body></html>
