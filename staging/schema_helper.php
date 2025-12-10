<?php
/**
 * staging/schema_helper.php
 * Small helper utilities to detect tables/columns on the connected database.
 * These are intentionally read-only and safe to call on production or dev DBs.
 */

require_once __DIR__ . '/../display.php';

function staging_table_exists($conn, $table){
    $sql = "SHOW TABLES LIKE ?";
    $stmt = safe_prepare($conn, $sql);
    if(!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

function staging_column_exists($conn, $table, $col){
    $q = "SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "` LIKE ?";
    $stmt = safe_prepare($conn, $q);
    if(!$stmt) return false;
    $stmt->bind_param('s', $col);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function staging_get_columns($conn, $table){
    $out = [];
    $stmt = safe_prepare($conn, "SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if(!$stmt) return $out;
    $stmt->execute();
    if(method_exists($stmt,'get_result')){
        $res = $stmt->get_result();
        while($r = $res->fetch_assoc()) $out[] = $r['Field'];
    } else {
        $stmt->store_result();
        $meta = $stmt->result_metadata();
        if($meta){
            while($f = $meta->fetch_field()) $out[] = $f->name;
            $meta->free();
        }
    }
    $stmt->close();
    return $out;
}

?>
