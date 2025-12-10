<?php
/**
 * staging/feed.php
 * Read-only jobs feed (safe prototype).
 * Returns JSON. Uses prepared statements and avoids selecting columns that may be missing
 * (e.g. `category`), to prevent SQL errors on databases with different migration states.
 */

require_once __DIR__ . '/../display.php';

header('Content-Type: application/json; charset=utf-8');

// Safety: always use an integer limit and clamp it
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 25;

// Select a conservative set of columns that are present in both migration variations
$sql = "SELECT p.id, p.title, p.description, COALESCE(p.image_path, '') AS image_path, COALESCE(p.budget, NULL) AS budget, COALESCE(p.location, '') AS location, p.created_at, COALESCE(u.username,'Guest') AS username FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT ?";

$stmt = safe_prepare($conn, $sql);
if(!$stmt){
    echo json_encode(['status'=>'error','message'=>'Database prepare failed']);
    exit;
}

$stmt->bind_param('i', $limit);
$stmt->execute();

$rows = [];
// Use get_result() when available, otherwise fallback to bind_result
if(method_exists($stmt, 'get_result')){
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $rows[] = $r;
} else {
    // Fallback fetching: bind by metadata
    $stmt->store_result();
    $meta = $stmt->result_metadata();
    if($meta){
        $fields = [];
        while($f = $meta->fetch_field()) $fields[] = $f->name;
        $meta->free();

        $row = [];
        $bindVars = [];
        foreach($fields as $fld){
            $row[$fld] = null;
            $bindVars[] = & $row[$fld];
        }
        if(count($bindVars)){
            call_user_func_array([$stmt, 'bind_result'], $bindVars);
            while($stmt->fetch()){
                $out = [];
                foreach($row as $k => $v) $out[$k] = $v;
                $rows[] = $out;
            }
        }
    }
}

echo json_encode(['status'=>'success','jobs'=>$rows]);
exit;
