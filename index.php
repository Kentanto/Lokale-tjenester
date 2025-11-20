<?php
// Minimal homepage with a safe, optional DB-backed posts feed.
session_start();

$posts = [];















$db_ok = false;
// Try to connect to DB and fetch posts — fail quietly to avoid 500s.
$dbHost = 'localhost'; $dbUser = 'pyx'; $dbPass = 'admin'; $dbName = 'DB';
if (extension_loaded('mysqli')){
  $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($conn && !$conn->connect_error){
    $db_ok = true;
    $sql = "SELECT p.id, p.title, p.description, p.image_path, u.username, p.created_at FROM posts p LEFT JOIN users u ON p.user_id=u.id ORDER BY p.created_at DESC LIMIT 20";
    $res = @$conn->query($sql);
    if($res && $res instanceof mysqli_result){
      while($r = $res->fetch_assoc()) $posts[] = $r;
      $res->free();
    }
    
    $conn->close();
  } else {
    error_log('DB not available for front page: ' . ($conn ? $conn->connect_error : 'no mysqli connection'));
  }
} else {
  error_log('mysqli extension not loaded; skipping DB feed');
}

// If DB not available, show a couple of sample posts so the feed isn't empty.
if(empty($posts)){
  $posts = [
    ['title'=>'Local handyman help','description'=>'Need help assembling furniture? Local, vetted help nearby.','username'=>'Admin','image_path'=>'','created_at'=>date('Y-m-d H:i:s')],
    ['title'=>'Gardening service','description'=>'Affordable gardening and yard cleanup. Message for details.','username'=>'Jenny','image_path'=>'','created_at'=>date('Y-m-d H:i:s')]
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="static/style.css">
  <title>Finn Hustle</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:0}
    nav{background:#0b6;display:flex;align-items:center;justify-content:space-between;padding:12px}
    nav a{color:#000;text-decoration:none;font-weight:700}
    .container{display:flex;gap:18px;padding:20px}
    .card{background:#fff;padding:16px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.1);flex:1}
  </style>
</head>
<body>
  <nav>
    <div><a href="index.php">Finn Hustle</a></div>
    <div>
      <a href="pages.php?page=about">About</a> |
      <a href="pages.php?page=services">Services</a> |
      <a href="pages.php?page=contact">Contact</a>
    </div>
  </nav>

  <main class="container">
    <section class="card">
      <h2>Welcome</h2>
      <p>This is a minimal fallback homepage. The dynamic features (signup/login) were temporarily disabled to restore site availability.</p>
      <p>If you need the full functionality restored, we can re-enable the auth flow after ensuring the database is healthy.</p>
    </section>
    <section class="card">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="pages.php?page=about">About</a></li>
        <li><a href="pages.php?page=services">Services</a></li>
        <li><a href="pages.php?page=contact">Contact</a></li>
      </ul>
    </section>
  </main>

  <section class="container" style="padding:20px">
    <div class="card" style="width:100%">
      <h2>Marketplace Feed</h2>
      <?php foreach($posts as $post): ?>
        <article style="border-bottom:1px solid #eee;padding:12px 0">
          <h3><?php echo htmlspecialchars($post['title'] ?? 'Untitled'); ?></h3>
          <p><?php echo nl2br(htmlspecialchars($post['description'] ?? '')); ?></p>
          <p style="color:#666;font-size:13px">Posted by: <?php echo htmlspecialchars($post['username'] ?? 'Guest'); ?> — <?php echo htmlspecialchars($post['created_at'] ?? ''); ?></p>
          <?php if(!empty($post['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Post image" style="max-width:180px;margin-top:8px;display:block">
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <footer style="padding:12px;text-align:center;font-size:13px;color:#666">
    &copy; <?php echo date('Y'); ?> Finn Hustle
  </footer>

</body>
</html>
