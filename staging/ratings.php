<?php
/**
 * staging/ratings.php
 * Prototype rating system. Read-only by default.
 * - GET: renders a small page with jobs and rating widgets
 * - POST (action=rate): accepts a rating for a job and stores it in session if no DB table exists
 * - GET json endpoint: ?action=get&post_id=ID returns aggregated rating data
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/schema_helper.php';

// POST rating
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate'){
    header('Content-Type: application/json');
    $post_id = intval($_POST['post_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    if($post_id <= 0 || $rating < 1 || $rating > 5){ echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

    if(staging_table_exists($conn, 'ratings')){
        // Example insert if real table exists (non-destructive here)
        $stmt = safe_prepare($conn, "INSERT INTO ratings (post_id, user_id, rating, created_at) VALUES (?, ?, ?, NOW())");
        if(!$stmt){ echo json_encode(['status'=>'error','message'=>'DB error']); exit; }
        $uid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $stmt->bind_param('iii', $post_id, $uid, $rating);
        if($stmt->execute()) echo json_encode(['status'=>'success','message'=>'Thanks for rating']);
        else echo json_encode(['status'=>'error','message'=>'Failed']);
        exit;
    }

    // Fallback: store in session for demo
    if(!isset($_SESSION['staging_ratings'])) $_SESSION['staging_ratings'] = [];
    if(!isset($_SESSION['staging_ratings'][$post_id])) $_SESSION['staging_ratings'][$post_id] = [];
    $_SESSION['staging_ratings'][$post_id][] = ['user'=>$_SESSION['user_id'] ?? null, 'rating'=>$rating, 'at'=>time()];
    echo json_encode(['status'=>'success','message'=>'Rating saved (session demo)']);
    exit;
}

// GET aggregated rating (JSON)
if(isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['post_id'])){
    header('Content-Type: application/json');
    $pid = intval($_GET['post_id']);
    $out = ['count'=>0,'avg'=>0.0,'ratings'=>[]];
    if(staging_table_exists($conn, 'ratings')){
        $stmt = safe_prepare($conn, "SELECT rating, COUNT(*) AS c FROM ratings WHERE post_id=? GROUP BY rating");
        if($stmt){ $stmt->bind_param('i',$pid); $stmt->execute(); $res = $stmt->get_result(); while($r = $res->fetch_assoc()) $out['ratings'][$r['rating']] = intval($r['c']); }
    } else {
        $data = $_SESSION['staging_ratings'][$pid] ?? [];
        $sum = 0; $count = 0;
        foreach($data as $row){ $sum += $row['rating']; $count++; $out['ratings'][$row['rating']] = ($out['ratings'][$row['rating']] ?? 0) + 1; }
        $out['count'] = $count;
        $out['avg'] = $count ? ($sum / $count) : 0.0;
    }
    echo json_encode($out);
    exit;
}

// Render simple UI listing a few jobs and rating widgets
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Ratings (Staging)</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:18px} .card{background:#fff;padding:12px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:900px} .job{border-bottom:1px solid #eee;padding:12px 0}</style>
</head>
<body>
  <div class="card">
    <h1>Ratings (Staging)</h1>
    <p>This is a prototype. Ratings are stored in session unless a `ratings` table exists.</p>
    <?php
    // fetch a few jobs
    $stmt = safe_prepare($conn, "SELECT id, title, description FROM posts ORDER BY created_at DESC LIMIT 10");
    $jobs = [];
    if($stmt){
        $stmt->execute();
        if(method_exists($stmt,'get_result')){ $res = $stmt->get_result(); while($r = $res->fetch_assoc()) $jobs[] = $r; }
        else { $stmt->store_result(); $meta = $stmt->result_metadata(); if($meta){ $fields=[]; while($f=$meta->fetch_field()) $fields[]=$f->name; $meta->free(); $row=[];$binds=[]; foreach($fields as $fld){$row[$fld]=null;$binds[]=&$row[$fld];} call_user_func_array([$stmt,'bind_result'],$binds); while($stmt->fetch()){ $out=[]; foreach($row as $k=>$v) $out[$k]=$v; $jobs[]=$out; } } }
    }

    foreach($jobs as $job): ?>
      <div class="job" data-post-id="<?php echo intval($job['id']); ?>">
        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
        <p style="color:#555"><?php echo htmlspecialchars(substr($job['description'] ?? '',0,160)); ?></p>
        <div class="rating" data-id="<?php echo intval($job['id']); ?>">
          <span class="avg">Loading...</span>
          <span style="margin-left:12px">Rate: </span>
          <?php for($i=1;$i<=5;$i++): ?>
            <button class="star" data-score="<?php echo $i; ?>"><?php echo $i; ?></button>
          <?php endfor; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <script>
    async function fetchAvg(pid, el){
        try{ let r = await fetch('ratings.php?action=get&post_id=' + pid); let j = await r.json(); el.querySelector('.avg').textContent = 'Avg: ' + (j.avg? j.avg.toFixed(2) : '—') + ' (' + (j.count||0) + ')'; }
        catch(e){ el.querySelector('.avg').textContent = 'Avg: N/A'; }
    }
    document.querySelectorAll('.rating').forEach(function(el){
        const pid = el.dataset.id;
        fetchAvg(pid, el);
        el.querySelectorAll('.star').forEach(function(btn){
            btn.addEventListener('click', async function(){
                const score = btn.dataset.score;
                const fd = new FormData(); fd.append('action','rate'); fd.append('post_id', pid); fd.append('rating', score);
                try{ const res = await fetch('ratings.php',{method:'POST', body: fd, credentials:'same-origin'}); const j = await res.json(); alert(j.message || 'OK'); fetchAvg(pid, el); }
                catch(e){ alert('Network error'); }
            });
        });
    });
    </script>
  </div>
</body>
</html>
