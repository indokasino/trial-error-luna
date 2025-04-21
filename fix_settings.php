<?php
/**
 * Luna Settings Diagnostic Tool
 * This script will diagnose and fix settings issues directly
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

echo "<h1>Luna Chatbot Settings Diagnostic</h1>";

// Connect directly to the database
try {
    $host = 'localhost'; // Sesuaikan dengan host Anda
    $dbname = 'admin_luna_gpt'; // Sesuaikan dengan nama database Anda
    $username = 'admin_luna_gpt'; // Sesuaikan dengan username database Anda
    $password = 'MioSmile5566@@'; // Sesuaikan dengan password database Anda
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Check if settings table exists
    $tables = $conn->query("SHOW TABLES LIKE 'settings'")->fetchAll();
    if (count($tables) == 0) {
        echo "<p style='color: red;'>Error: settings table does not exist!</p>";
        echo "<p>Creating settings table...</p>";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS `settings` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `key` VARCHAR(100) UNIQUE NOT NULL,
          `value` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $conn->exec($createTable);
        echo "<p style='color: green;'>Settings table created successfully!</p>";
    } else {
        echo "<p>Settings table exists.</p>";
    }
    
    // Check current settings
    echo "<h2>Current Settings</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Key</th><th>Value</th><th>Created At</th><th>Updated At</th></tr>";
    
    $settingsQuery = $conn->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    if (count($settingsQuery) == 0) {
        echo "<tr><td colspan='5'>No settings found in the database</td></tr>";
    } else {
        foreach ($settingsQuery as $setting) {
            echo "<tr>";
            echo "<td>" . $setting['id'] . "</td>";
            echo "<td>" . $setting['key'] . "</td>";
            echo "<td>" . (in_array($setting['key'], ['api_token', 'openai_key']) ? substr($setting['value'], 0, 3) . '...' . substr($setting['value'], -3) : $setting['value']) . "</td>";
            echo "<td>" . $setting['created_at'] . "</td>";
            echo "<td>" . $setting['updated_at'] . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'fix_settings') {
            echo "<h2>Fixing Settings</h2>";
            
            // Generate new token
            $newToken = bin2hex(random_bytes(16));
            
            // Prepare settings to insert/update
            $defaultSettings = [
                'api_token' => $newToken,
                'openai_key' => isset($_POST['openai_key']) && !empty($_POST['openai_key']) ? $_POST['openai_key'] : 'placeholder_key',
                'gpt_model' => 'gpt-4.1',
                'fallback_model' => 'o4-mini',
                'fallback_response' => 'Sorry, I could not process your request at this time. Please try again later.',
                'max_retries' => '3',
                'rate_limit_per_minute' => '10',
                'log_retention_days' => '90'
            ];
            
            // Check each setting and insert/update as needed
            foreach ($defaultSettings as $key => $value) {
                // Check if setting exists
                $checkStmt = $conn->prepare("SELECT id FROM settings WHERE `key` = ?");
                $checkStmt->execute([$key]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($exists) {
                    // Update existing setting
                    $updateStmt = $conn->prepare("UPDATE settings SET value = ? WHERE `key` = ?");
                    $updateStmt->execute([$value, $key]);
                    echo "<p>Updated setting: $key</p>";
                } else {
                    // Insert new setting
                    $insertStmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?)");
                    $insertStmt->execute([$key, $value]);
                    echo "<p>Added new setting: $key</p>";
                }
            }
            
            echo "<p style='color: green;'>Settings fixed successfully!</p>";
            echo "<p>New API Token: <strong>$newToken</strong></p>";
            echo "<p>Make sure to save this token somewhere secure.</p>";
        }
    }
    
    // Form for fixing settings
    echo "<h2>Fix Settings</h2>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='action' value='fix_settings'>";
    echo "<label>OpenAI API Key (optional):</label><br>";
    echo "<input type='text' name='openai_key' size='50' placeholder='Enter your OpenAI API key here'><br><br>";
    echo "<p>Clicking the button below will:</p>";
    echo "<ul>";
    echo "<li>Generate a new API Token</li>";
    echo "<li>Add/update all required settings</li>";
    echo "<li>Fix any database issues with the settings table</li>";
    echo "</ul>";
    echo "<input type='submit' value='Fix Settings Now'>";
    echo "</form>";
    
    // Check admin/settings.php file
    echo "<h2>File Check</h2>";
    $settingsFile = LUNA_ROOT . '/admin/settings.php';
    if (file_exists($settingsFile)) {
        echo "<p>admin/settings.php file exists.</p>";
        
        // Check if file is readable
        if (is_readable($settingsFile)) {
            echo "<p>admin/settings.php is readable.</p>";
            
            // Check file contents
            $fileContent = file_get_contents($settingsFile);
            if (strpos($fileContent, 'getSetting') !== false) {
                echo "<p>admin/settings.php appears to have proper getSetting function calls.</p>";
            } else {
                echo "<p style='color: red;'>Warning: admin/settings.php may be missing getSetting function calls.</p>";
            }
        } else {
            echo "<p style='color: red;'>Warning: admin/settings.php is not readable.</p>";
        }
    } else {
        echo "<p style='color: red;'>Warning: admin/settings.php file does not exist!</p>";
    }
    
    // Check inc/functions.php file
    $functionsFile = LUNA_ROOT . '/inc/functions.php';
    if (file_exists($functionsFile)) {
        echo "<p>inc/functions.php file exists.</p>";
        
        // Check if file is readable
        if (is_readable($functionsFile)) {
            echo "<p>inc/functions.php is readable.</p>";
            
            // Check file contents
            $fileContent = file_get_contents($functionsFile);
            if (strpos($fileContent, 'function getSetting') !== false) {
                echo "<p>inc/functions.php has getSetting function defined.</p>";
            } else {
                echo "<p style='color: red;'>Warning: inc/functions.php may be missing getSetting function definition.</p>";
            }
        } else {
            echo "<p style='color: red;'>Warning: inc/functions.php is not readable.</p>";
        }
    } else {
        echo "<p style='color: red;'>Warning: inc/functions.php file does not exist!</p>";
    }
    
    echo "<h2>Next Steps</h2>";
    echo "<p>After fixing the settings, try to access the admin settings page again:</p>";
    echo "<p><a href='admin/settings.php'>Go to Admin Settings</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
}
?>