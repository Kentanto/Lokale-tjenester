<?php
/**
 * Database Diagnostic Script
 * This script checks what's wrong with the database connection
 */

echo "<h1>Database Diagnostics</h1>";
echo "<pre>";

// Database credentials from display.php
$DB_HOST = 'localhost';
$DB_NAME = 'lokale-tjenester';
$DB_USER = 'lokale-tjenester';
$DB_PASS = 'pwlt01!';

echo "Testing connection with:\n";
echo "  Host: $DB_HOST\n";
echo "  User: $DB_USER\n";
echo "  Database: $DB_NAME\n";
echo "\n---\n\n";

// Try connecting
echo "Attempting connection...\n";
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    echo "❌ CONNECTION FAILED:\n";
    echo "   Error: " . $conn->connect_error . "\n\n";
    
    // Try connecting to localhost without DB to check credentials
    echo "Trying to connect to MySQL server only (no database)...\n";
    $conn2 = @new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    if ($conn2->connect_error) {
        echo "❌ Cannot connect to MySQL server at all.\n";
        echo "   Error: " . $conn2->connect_error . "\n";
        echo "\nPossible issues:\n";
        echo "   - MySQL server is not running\n";
        echo "   - Host is wrong (try 'localhost', '127.0.0.1', or a different host)\n";
        echo "   - Username/password is incorrect\n";
    } else {
        echo "✓ Connected to MySQL server!\n";
        echo "✗ But the database '$DB_NAME' doesn't exist or you don't have permission.\n\n";
        
        // List available databases
        $result = $conn2->query("SHOW DATABASES");
        echo "Available databases:\n";
        while ($row = $result->fetch_array()) {
            echo "  - " . $row[0] . "\n";
        }
        echo "\nTo fix: Create the database '$DB_NAME' or update DB_NAME in display.php\n";
    }
} else {
    echo "✓ Connected to database successfully!\n\n";
    
    // Check tables
    echo "Checking tables...\n";
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
        echo "  - " . $row[0] . "\n";
    }
    
    if (empty($tables)) {
        echo "\n⚠️  No tables found! Run setup_db.php to initialize.\n";
    } else {
        echo "\n✓ Tables exist.\n";
        
        // Check for users
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch_assoc();
        echo "  Users in database: " . $row['count'] . "\n";
    }
}

echo "\n</pre>";
echo "<hr>";
echo "<p><a href='javascript:location.reload()'>Refresh</a> | <a href='setup_db.php'>Run Setup</a></p>";
?>
