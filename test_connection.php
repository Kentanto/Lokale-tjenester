<?php
// Quick connection test with timeout
set_time_limit(10);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = 'localhost';
$DB_NAME = 'lokale-tjenester';
$DB_USER = 'lokale-tjenester';
$DB_PASS = 'pwlt01!';

echo "<h1>Database Connection Test</h1>";
echo "<pre>";
echo "Testing database connection...\n";
echo "Host: $DB_HOST\n";
echo "User: $DB_USER\n";
echo "Database: $DB_NAME\n\n";

// Attempt connection with timeout
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    echo "❌ Connection FAILED\n";
    echo "Error: " . htmlspecialchars($conn->connect_error) . "\n";
    exit(1);
} else {
    echo "✅ Connection successful!\n\n";
    
    // List tables
    $result = $conn->query("SHOW TABLES;");
    if ($result) {
        echo "Tables in database:\n";
        while ($row = $result->fetch_row()) {
            echo "  - " . $row[0] . "\n";
        }
        $result->free();
    } else {
        echo "Error listing tables: " . $conn->error . "\n";
    }
    
    // Check users table
    echo "\nChecking users table:\n";
    $result = $conn->query("SELECT COUNT(*) as count FROM users;");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "  Users in database: " . $row['count'] . "\n";
        $result->free();
    } else {
        echo "  Error: " . $conn->error . "\n";
    }
    
    $conn->close();
    echo "\n✅ All tests passed!\n";
}
echo "</pre>";
?>
