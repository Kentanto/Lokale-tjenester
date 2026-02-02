<?php
// Simple migration runner — reads migrations/create_tables.sql and executes via mysqli->multi_query
// Usage (CLI): php scripts/run_migrations.php
// Usage (browser): visit http://your-server/scripts/run_migrations.php (only for local dev)

$host = 'localhost';
$user = 'lokale-tjenester';
$pass = 'pwlt01!';
$db   = 'lokale-tjenester';

$migrationsDir = __DIR__ . '/../migrations';
if(!is_dir($migrationsDir)){
    echo "Migrations directory not found: $migrationsDir\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
if(!$files){
    echo "No migration files found in: $migrationsDir\n";
    exit(0);
}

// sort to ensure deterministic order
sort($files, SORT_STRING);

$mysqli = new mysqli($host, $user, $pass, $db);
if($mysqli->connect_error){
    echo "DB connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

$allSuccess = true;
foreach($files as $path){
    echo "Running migration: " . basename($path) . "\n";
    $sql = file_get_contents($path);
    if($sql === false){
        echo "Unable to read migration file: $path\n";
        $allSuccess = false;
        continue;
    }

    if($mysqli->multi_query($sql)){
        do {
            if ($res = $mysqli->store_result()) {
                $res->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
        echo "  OK\n";
    } else {
        echo "  Failed: " . $mysqli->error . "\n";
        $allSuccess = false;
    }
}

$mysqli->close();

if($allSuccess) echo "All migrations executed.\n";

?>
