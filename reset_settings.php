<?php
/**
 * Reset Settings Utility for Luna Chatbot
 * 
 * This script allows you to directly set the OpenAI API Key and generate a new API Token
 * Place this file in the root directory of your Luna Chatbot installation
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Function to update setting directly using PDO
function forceUpdateSetting($key, $value) {
    $db = db()->getConnection();
    
    try {
        // Check if setting exists
        $checkStmt = $db->prepare("SELECT id FROM settings WHERE `key` = ? LIMIT 1");
        $checkStmt->execute([$key]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing setting
            $stmt = $db->prepare("UPDATE settings SET value = ? WHERE `key` = ?");
            $result = $stmt->execute([$value, $key]);
        } else {
            // Insert new setting
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?)");
            $result = $stmt->execute([$key, $value]);
        }
        
        return $result;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle OpenAI API Key update
    if (isset($_POST['openai_key']) && !empty($_POST['openai_key'])) {
        $apiKey = trim($_POST['openai_key']);
        if (forceUpdateSetting('openai_key', $apiKey)) {
            $success .= "OpenAI API Key updated successfully.<br>";
        } else {
            $error .= "Failed to update OpenAI API Key.<br>";
        }
    }
    
    // Handle API Token generation
    if (isset($_POST['generate_token']) && $_POST['generate_token'] === '1') {
        try {
            $newToken = bin2hex(random_bytes(16)); // Generate a secure 32-character token
            if (forceUpdateSetting('api_token', $newToken)) {
                $success .= "New API Token generated successfully: <strong>" . $newToken . "</strong><br>";
                $success .= "Please save this token in a secure location.<br>";
            } else {
                $error .= "Failed to generate new API Token.<br>";
            }
        } catch (Exception $e) {
            $error .= "Token generation error: " . $e->getMessage() . "<br>";
        }
    }
}

// Get current settings
try {
    $openaiKey = getSetting('openai_key', '');
    $apiToken = getSetting('api_token', '');
    
    // Mask the API key and token for display
    $maskedKey = !empty($openaiKey) ? substr($openaiKey, 0, 4) . '...' . substr($openaiKey, -4) : 'Not set';
    $maskedToken = !empty($apiToken) ? substr($apiToken, 0, 4) . '...' . substr($apiToken, -4) : 'Not set';
} catch (Exception $e) {
    $error .= "Failed to retrieve current settings: " . $e->getMessage() . "<br>";
    $maskedKey = 'Error';
    $maskedToken = 'Error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Reset Settings</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        .current-settings {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .checkbox-group {
            margin-top: 10px;
        }
        .btn {
            padding: 10px 15px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .back-link {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Luna Chatbot - Reset Settings</h1>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="current-settings">
            <h3>Current Settings</h3>
            <p><strong>OpenAI API Key:</strong> <?php echo $maskedKey; ?></p>
            <p><strong>API Token:</strong> <?php echo $maskedToken; ?></p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="openai_key">OpenAI API Key</label>
                <input type="text" id="openai_key" name="openai_key" placeholder="Enter your OpenAI API key">
                <small>Leave blank if you don't want to update the API key</small>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="generate_token" name="generate_token" value="1">
                    <label for="generate_token">Generate New API Token</label>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Update Settings</button>
            </div>
        </form>
        
        <div class="back-link">
            <p><a href="admin/settings.php">Back to Settings Page</a> | <a href="admin/index.php">Back to Admin Dashboard</a></p>
        </div>
        
        <div class="instructions">
            <h3>Instructions</h3>
            <ol>
                <li>Enter your OpenAI API Key if you want to update it</li>
                <li>Check the "Generate New API Token" box if you want a new token</li>
                <li>Click "Update Settings" to apply the changes</li>
                <li>If successful, the updated settings will be displayed above</li>
                <li>Make sure to save the new API Token somewhere secure if you generate one</li>
                <li>After updating, you can return to the regular settings page</li>
            </ol>
            
            <p><strong>Note:</strong> Delete this file after use for security reasons.</p>
        </div>
    </div>
</body>
</html>