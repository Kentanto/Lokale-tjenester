<?php
// Simple migration runner — reads migrations/*.sql and executes via mysqli->multi_query

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'lokale-tjenester';
$pass = 'pwlt01!';
$db   = 'lokale-tjenester';

echo "Migration Runner Started\n\n";

$migrationsDir = __DIR__ . '/../migrations';
echo "Looking for migrations in: $migrationsDir\n";

if(!is_dir($migrationsDir)){
    echo "ERROR: Migrations directory not found!\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
if(!$files){
    echo "No migration files found.\n";
    exit(0);
}

echo "Found " . count($files) . " migration file(s):\n";
foreach($files as $f) echo "  - " . basename($f) . "\n";
echo "\n";

sort($files, SORT_STRING);

$mysqli = new mysqli($host, $user, $pass, $db);
if($mysqli->connect_error){
    echo "ERROR: DB connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}
echo "Connected to database successfully.\n\n";

$allSuccess = true;
foreach($files as $path){
    $filename = basename($path);
    echo "Running: $filename ... ";
    
    $sql = file_get_contents($path);
    if($sql === false){
        echo "FAILED (could not read file)\n";
        $allSuccess = false;
        continue;
    }

    if($mysqli->multi_query($sql)){
        do {
            if ($res = $mysqli->store_result()) {
                $res->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
        echo "OK\n";
    } else {
        echo "FAILED: " . $mysqli->error . "\n";
        $allSuccess = false;
    }
}

$mysqli->close();

echo "\n";
if($allSuccess) {
    echo "✓ All migrations completed successfully!\n";
} else {
    echo "✗ Some migrations failed.\n";
}

?>
