
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('LUNA_ROOT', dirname(__FILE__));
require_once LUNA_ROOT . '/inc/db.php';

echo "<h1>Adding Required Settings</h1>";

try {
    $db = db()->getConnection();
    
    // Generate API token
    $apiToken = bin2hex(random_bytes(16));
    
    // Settings to add
    $settings = [
        'api_token' => $apiToken,
        'openai_key' => 'sk-your-openai-key', // Replace with your actual key
        'gpt_model' => 'gpt-4.1',
        'fallback_model' => 'o4-mini',
        'fallback_response' => 'Sorry, I could not process your request at this time. Please try again later.',
        'max_retries' => '3',
        'rate_limit_per_minute' => '10',
        'log_retention_days' => '90'
    ];
    
    echo "<ul>";
    
    // Add each setting
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $value, $value]);
        
        echo "<li>Added setting: $key = " . ($key === 'api_token' ? substr($value, 0, 4) . "..." . substr($value, -4) : $value) . "</li>";
    }
    
    echo "</ul>";
    
    echo "<p>Settings added successfully!</p>";
    echo "<p>Your API Token: <strong>$apiToken</strong> (save this for API requests)</p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
