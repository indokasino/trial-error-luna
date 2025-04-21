<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 menit timeout

define('LUNA_ROOT', dirname(__FILE__));

echo "<h1>Luna Chatbot Database Installation</h1>";

try {
    // Koneksi database (gunakan kredensial yang sama dengan debug.php)
    $host = 'localhost'; // Sesuaikan dengan host Anda
    $dbname = 'admin_luna_gpt'; // Sesuaikan dengan nama database Anda
    $username = 'admin_luna_gpt'; // Sesuaikan dengan username database Anda
    $password = 'MioSmile5566@@'; // Sesuaikan dengan password database Anda
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>Database connection successful!</p>";
    
    // Baca file SQL
    $sqlFile = LUNA_ROOT . '/migrations/create_tables.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    echo "<p>SQL file found.</p>";
    
    // Baca isi file SQL
    $sql = file_get_contents($sqlFile);
    
    // Pisahkan query-query SQL (berdasarkan delimiter)
    $queries = explode(';', $sql);
    
    echo "<p>Total queries found: " . count($queries) . "</p>";
    echo "<ul>";
    
    $successCount = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        // Tangani DELIMITER secara khusus untuk prosedur tersimpan dan trigger
        if (stripos($query, 'DELIMITER') === 0) {
            continue; // Skip DELIMITER statements
        }
        
        try {
            $conn->exec($query);
            echo "<li style='color: green;'>Success: " . substr($query, 0, 50) . "...</li>";
            $successCount++;
        } catch (PDOException $e) {
            echo "<li style='color: red;'>Error: " . $e->getMessage() . "<br>Query: " . substr($query, 0, 100) . "...</li>";
        }
    }
    
    echo "</ul>";
    echo "<p>Installation completed! $successCount queries executed successfully.</p>";
    echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>