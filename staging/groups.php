<?php
/**
 * staging/groups.php
 * Prototype for group (organization) accounts and admin-approve flow.
 * This staging page stores groups in DB if a `groups` table exists, otherwise keeps
 * them in session for demo purposes. Admin approval is simulated.
 */

require_once __DIR__ . '/../display.php';
require_once __DIR__ . '/schema_helper.php';
header('Content-Type: text/html; charset=utf-8');

// POST actions: create_group, request_join, approve_group (admin)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    $act = $_POST['action'];
    header('Content-Type: application/json');
    if($act === 'create_group'){
        $name = trim($_POST['name'] ?? '');
        if(!$name) { echo json_encode(['status'=>'error','message'=>'Name required']); exit; }

        if(staging_table_exists($conn, 'groups')){
            $stmt = safe_prepare($conn, "INSERT INTO groups (name, owner_user_id, created_at, approved) VALUES (?, ?, NOW(), 0)");
            if(!$stmt){ echo json_encode(['status'=>'error','message'=>'DB error']); exit; }
            $uid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
            $stmt->bind_param('si', $name, $uid);
            if($stmt->execute()) echo json_encode(['status'=>'success','message'=>'Group created (pending approval)']);
            else echo json_encode(['status'=>'error','message'=>'Insert failed']);
            exit;
        }

        // session fallback
        if(!isset($_SESSION['staging_groups'])) $_SESSION['staging_groups'] = [];
        $id = time() . rand(100,999);
        $_SESSION['staging_groups'][$id] = ['id'=>$id,'name'=>$name,'owner'=>$_SESSION['user_id'] ?? null,'approved'=>false];
        echo json_encode(['status'=>'success','message'=>'Group created (session demo)','id'=>$id]); exit;
    }

    if($act === 'request_join'){
        $gid = $_POST['group_id'] ?? null;
        if(!$gid){ echo json_encode(['status'=>'error','message'=>'group_id required']); exit; }
        // In real app we'd create a pending membership record; here we simulate
        if(!isset($_SESSION['staging_group_requests'])) $_SESSION['staging_group_requests'] = [];
        $_SESSION['staging_group_requests'][] = ['group'=>$gid, 'user'=>$_SESSION['user_id'] ?? null, 'at'=>time()];
        echo json_encode(['status'=>'success','message'=>'Join request submitted']); exit;
    }

    if($act === 'approve_group'){
        // Simulated admin: toggle approved in session store
        $gid = $_POST['group_id'] ?? null;
        if(!$gid){ echo json_encode(['status'=>'error','message'=>'group_id required']); exit; }
        if(staging_table_exists($conn,'groups')){
            $stmt = safe_prepare($conn, "UPDATE groups SET approved=1 WHERE id=?");
            if(!$stmt){ echo json_encode(['status'=>'error','message'=>'DB error']); exit; }
            $stmt->bind_param('i', $gid);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>'Group approved']); exit;
        }
        if(isset($_SESSION['staging_groups'][$gid])){
            $_SESSION['staging_groups'][$gid]['approved'] = true;
            echo json_encode(['status'=>'success','message'=>'Group approved (session demo)']); exit;
        }
        echo json_encode(['status'=>'error','message'=>'Group not found']); exit;
    }

    echo json_encode(['status'=>'error','message'=>'Unknown action']); exit;
}

// Render simple UI
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Groups (Staging)</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:18px} .card{background:#fff;padding:12px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:900px} .group{border-bottom:1px solid #eee;padding:8px 0}</style>
</head>
<body>
  <div class="card">
    <h1>Groups / Dugnad (Staging)</h1>
    <?php if($is_logged_in): ?>
      <form id="createGroupForm">
        <label>Group name <input type="text" name="name" required></label>
        <button type="submit">Create Group</button>
      </form>
      <h3 style="margin-top:18px">Existing groups</h3>
      <div id="groupsList">
        <?php
        // show session or DB groups
        if(staging_table_exists($conn,'groups')){
            $stmt = safe_prepare($conn, "SELECT id, name, approved FROM groups ORDER BY created_at DESC LIMIT 50");
            if($stmt){ $stmt->execute(); if(method_exists($stmt,'get_result')){ $res = $stmt->get_result(); while($r=$res->fetch_assoc()){ echo '<div class="group" data-id="'.intval($r['id']).'"><strong>'.htmlspecialchars($r['name']).'</strong> — '.($r['approved']? 'Approved':'Pending').'</div>'; } } }
        } else {
            $sg = $_SESSION['staging_groups'] ?? [];
            foreach($sg as $g) echo '<div class="group" data-id="'.htmlspecialchars($g['id']).'"><strong>'.htmlspecialchars($g['name']).'</strong> — '.($g['approved']? 'Approved':'Pending').'</div>';
        }
        ?>
      </div>

      <script>
      document.getElementById('createGroupForm').addEventListener('submit', async function(e){
          e.preventDefault();
          const fd = new FormData(e.target); fd.append('action','create_group');
          const res = await fetch('groups.php',{method:'POST', body: fd, credentials:'same-origin'}); const j = await res.json(); alert(j.message || j.status);
          if(j.status==='success') location.reload();
      });
      </script>

    <?php else: ?>
      <p>Please <a href="/pages.php?page=login">log in</a> to create or join groups.</p>
    <?php endif; ?>
  </div>
</body>
</html>
