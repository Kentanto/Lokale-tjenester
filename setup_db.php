<?php
/**
 * Database Reset & Setup Script
 * This script initializes/resets the database with just the system admin account.
 * Usage: Visit http://your-server/setup_db.php in your browser
 * 
 * WARNING: This will clear all existing data!
 */

// Database credentials
$DB_HOST = 'localhost';
$DB_NAME = 'DB';
$DB_USER = 'pyx';
$DB_PASS = 'admin';

// Connect to MySQL
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn && $conn->connect_error) {
    die('Database connection failed: ' . htmlspecialchars($conn->connect_error));
}

// Drop existing tables to start fresh
$tables = ['remember_tokens', 'contacts', 'posts', 'users'];
foreach ($tables as $table) {
    $conn->query("DROP TABLE IF EXISTS `$table`");
}

// Create users table
$create_users = "CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `email_verified` TINYINT(1) DEFAULT 0,
  `verify_token` VARCHAR(128) DEFAULT NULL,
  `is_admin` TINYINT(1) DEFAULT 0,
  `session_duration` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($create_users)) {
    die('Error creating users table: ' . htmlspecialchars($conn->error));
}

// Create posts table
$create_posts = "CREATE TABLE `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `image_path` VARCHAR(512) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($create_posts)) {
    die('Error creating posts table: ' . htmlspecialchars($conn->error));
}

// Create contacts table
$create_contacts = "CREATE TABLE `contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `message` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($create_contacts)) {
    die('Error creating contacts table: ' . htmlspecialchars($conn->error));
}

// Create remember_tokens table
$create_remember = "CREATE TABLE `remember_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY (token),
  KEY (expires_at)
)";

if (!$conn->query($create_remember)) {
    die('Error creating remember_tokens table: ' . htmlspecialchars($conn->error));
}

// Create system admin account
// Username: system, Password: system123
$system_username = 'system';
$system_email = 'system@lokale-tjenester.local';
$system_password = 'system123';
$password_hash = password_hash($system_password, PASSWORD_DEFAULT);
$is_admin = 1;

$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, email_verified, is_admin) VALUES (?, ?, ?, 1, ?)");

if (!$stmt) {
    die('Error preparing statement: ' . htmlspecialchars($conn->error));
}

$stmt->bind_param('sssi', $system_username, $system_email, $password_hash, $is_admin);

if (!$stmt->execute()) {
    die('Error creating system account: ' . htmlspecialchars($stmt->error));
}

$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup Complete</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
        h1 { color: #155724; }
        code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
        .credentials { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="success">
        <h1>✓ Database Setup Complete</h1>
        <p>All tables have been created and the system account has been initialized.</p>
    </div>

    <div class="credentials">
        <h2>System Admin Account</h2>
        <p><strong>Username:</strong> <code>system</code></p>
        <p><strong>Password:</strong> <code>system123</code></p>
        <p><strong>Email:</strong> <code>system@lokale-tjenester.local</code></p>
        <p style="margin-top: 20px; font-style: italic;">You can now log in on the <a href="index.php">coming soon page</a> to access the admin panel.</p>
    </div>

    <p style="margin-top: 40px; color: #666; font-size: 12px;">
        ⚠️ <strong>Important:</strong> Delete this file (<code>setup_db.php</code>) after setup is complete for security reasons.
    </p>
</body>
</html>
