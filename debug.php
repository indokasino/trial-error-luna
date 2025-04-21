<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Info:</h2>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>Path Test:</h2>";
$rootPath = dirname(__FILE__);
echo "Root Path: " . $rootPath . "<br>";

echo "<h2>Database Test:</h2>";
try {
    $host = 'localhost'; // Ganti dengan host database Anda
    $dbname = 'admin_luna_gpt'; // Ganti dengan nama database
    $username = 'admin_luna_gpt'; // Ganti dengan username database
    $password = 'MioSmile5566@@'; // Ganti dengan password database
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

echo "<h2>File Existence Check:</h2>";
$files = [
    '/inc/db.php',
    '/inc/auth.php',
    '/inc/functions.php',
    '/inc/gpt_service.php'
];

foreach ($files as $file) {
    $fullPath = $rootPath . $file;
    echo "$fullPath: " . (file_exists($fullPath) ? "Exists" : "Missing") . "<br>";
}
?>