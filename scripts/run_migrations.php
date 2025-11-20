<?php
// Simple migration runner — reads migrations/create_tables.sql and executes via mysqli->multi_query
// Usage (CLI): php scripts/run_migrations.php
// Usage (browser): visit http://your-server/scripts/run_migrations.php (only for local dev)

$host = 'localhost';
$user = 'pyx';
$pass = 'admin';
$db   = 'DB';

$path = __DIR__ . '/../migrations/create_tables.sql';
if(!file_exists($path)){
    echo "Migration file not found: $path\n";
    exit(1);
}
$sql = file_get_contents($path);
if($sql === false){
    echo "Unable to read migration file.\n";
    exit(1);
}

$mysqli = new mysqli($host, $user, $pass, $db);
if($mysqli->connect_error){
    echo "DB connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

if($mysqli->multi_query($sql)){
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    echo "Migration executed.\n";
} else {
    echo "Migration failed: " . $mysqli->error . "\n";
}

$mysqli->close();

?>
