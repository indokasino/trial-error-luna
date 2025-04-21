<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('LUNA_ROOT', dirname(__FILE__));

echo "<h1>Reset Admin Password</h1>";

try {
    // Koneksi database (gunakan kredensial yang sama dengan debug.php)
    $host = 'localhost'; // Sesuaikan dengan host Anda
    $dbname = 'admin_luna_gpt'; // Sesuaikan dengan nama database Anda
    $username = 'admin_luna_gpt'; // Sesuaikan dengan username database Anda
    $password = 'MioSmile5566@@'; // Sesuaikan dengan password database Anda
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>Database connection successful!</p>";
    
    // Password baru
    $newPassword = 'admin123'; // Anda bisa mengubah ini sesuai keinginan
    
    // Hash password baru menggunakan password_hash
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password admin
    $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashedPassword]);
    
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        echo "<p style='color: green;'>Success! Admin password has been reset.</p>";
        echo "<p>New password: <strong>$newPassword</strong></p>";
        echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>";
    } else {
        echo "<p style='color: orange;'>No admin user found with username 'admin'. Creating new admin user...</p>";
        
        // Coba buat user admin baru
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email, status) VALUES ('admin', ?, 'admin@example.com', 'active')");
        $stmt->execute([$hashedPassword]);
        
        echo "<p style='color: green;'>New admin user created successfully!</p>";
        echo "<p>Username: <strong>admin</strong></p>";
        echo "<p>Password: <strong>$newPassword</strong></p>";
        echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>