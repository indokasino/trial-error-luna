<?php
/**
 * Permission Checker for Luna Chatbot
 * Place this file in the root directory of your Luna Chatbot installation
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

echo "<h1>Luna Chatbot Permission Checker</h1>";

// Check PHP version
echo "<h2>PHP Environment</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Web Server User: " . get_current_user() . "</p>";
echo "<p>Current Working Directory: " . getcwd() . "</p>";

// Check if key directories exist and are writable
echo "<h2>Directory Permissions</h2>";
$directories = [
    LUNA_ROOT => 'Root Directory',
    LUNA_ROOT . '/admin' => 'Admin Directory',
    LUNA_ROOT . '/inc' => 'Include Directory',
    LUNA_ROOT . '/assets' => 'Assets Directory',
    LUNA_ROOT . '/frontend' => 'Frontend Directory',
];

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Directory</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Permissions</th></tr>";

foreach ($directories as $path => $name) {
    $exists = is_dir($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
    
    echo "<tr>";
    echo "<td>$name ($path)</td>";
    echo "<td style='color: " . ($exists ? 'green' : 'red') . "'>" . ($exists ? 'Yes' : 'No') . "</td>";
    echo "<td style='color: " . ($readable ? 'green' : 'red') . "'>" . ($readable ? 'Yes' : 'No') . "</td>";
    echo "<td style='color: " . ($writable ? 'green' : 'red') . "'>" . ($writable ? 'Yes' : 'No') . "</td>";
    echo "<td>$perms</td>";
    echo "</tr>";
}
echo "</table>";

// Check key files
echo "<h2>Critical Files</h2>";
$files = [
    LUNA_ROOT . '/prompt-luna.txt' => 'Prompt Template',
    LUNA_ROOT . '/inc/db.php' => 'Database Connection',
    LUNA_ROOT . '/inc/auth.php' => 'Authentication Module',
    LUNA_ROOT . '/inc/functions.php' => 'Helper Functions',
    LUNA_ROOT . '/inc/gpt_service.php' => 'GPT Service',
    LUNA_ROOT . '/admin/edit.php' => 'Edit Page',
    LUNA_ROOT . '/admin/delete.php' => 'Delete Page',
    LUNA_ROOT . '/admin/settings.php' => 'Settings Page'
];

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>File</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Size</th><th>Permissions</th></tr>";

foreach ($files as $path => $name) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    $size = $exists ? filesize($path) . " bytes" : 'N/A';
    $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
    
    echo "<tr>";
    echo "<td>$name ($path)</td>";
    echo "<td style='color: " . ($exists ? 'green' : 'red') . "'>" . ($exists ? 'Yes' : 'No') . "</td>";
    echo "<td style='color: " . ($readable ? 'green' : 'red') . "'>" . ($readable ? 'Yes' : 'No') . "</td>";
    echo "<td style='color: " . ($writable ? 'green' : 'red') . "'>" . ($writable ? 'Yes' : 'No') . "</td>";
    echo "<td>$size</td>";
    echo "<td>$perms</td>";
    echo "</tr>";
}
echo "</table>";

// Check for prompt-luna.txt specifically
$promptPath = LUNA_ROOT . '/prompt-luna.txt';
echo "<h2>Prompt Template File</h2>";

if (!file_exists($promptPath)) {
    echo "<p style='color: red;'>The prompt template file doesn't exist. Attempting to create it...</p>";
    
    try {
        $result = file_put_contents($promptPath, "You are Luna, a helpful AI assistant.");
        if ($result !== false) {
            echo "<p style='color: green;'>Successfully created prompt template file!</p>";
        } else {
            echo "<p style='color: red;'>Failed to create prompt template file.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>The prompt template file exists.</p>";
    
    if (!is_writable($promptPath)) {
        echo "<p style='color: red;'>WARNING: The prompt template file is not writable. The settings page won't be able to update it.</p>";
        echo "<p>Trying to fix permissions...</p>";
        
        try {
            $chmod_result = chmod($promptPath, 0666);
            if ($chmod_result) {
                echo "<p style='color: green;'>Successfully updated file permissions to 0666.</p>";
            } else {
                echo "<p style='color: red;'>Failed to update file permissions.</p>";
                echo "<p>Please manually run this command via SSH or file manager:</p>";
                echo "<code>chmod 666 " . $promptPath . "</code>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>The prompt template file is writable. This is good.</p>";
    }
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once LUNA_ROOT . '/inc/db.php';
    
    $db = db()->getConnection();
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Test query execution
    $stmt = $db->prepare("SELECT COUNT(*) FROM prompt_data");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "<p>Total Q&A entries in database: $count</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Possible solutions:</p>";
    echo "<ul>";
    echo "<li>Check if database credentials in inc/db.php are correct</li>";
    echo "<li>Ensure the database server is running</li>";
    echo "<li>Verify that the tables have been created using install.php</li>";
    echo "</ul>";
}

// Recommended fixes
echo "<h2>Recommendations</h2>";
echo "<p>Based on common issues:</p>";
echo "<ol>";
echo "<li>Ensure the prompt-luna.txt file has write permissions (chmod 666)</li>";
echo "<li>Make sure all PHP files have read permissions (chmod 644 at minimum)</li>";
echo "<li>If delete functionality doesn't work, check that your PDO extension is correctly installed</li>";
echo "<li>Verify that both db()->delete() and db()->update() methods in inc/db.php are returning the correct values</li>";
echo "<li>If you're getting database errors, run the install.php script to ensure all tables are created</li>";
echo "</ol>";

echo "<p>If you're still experiencing issues after fixing permissions and database connections, check your PHP error logs for more details.</p>";
echo "<p><a href='admin/index.php'>Go back to Admin Dashboard</a></p>";
?>