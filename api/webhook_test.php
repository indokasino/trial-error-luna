<?php
// Simple diagnostic script for webhook.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

echo "<h1>Webhook Diagnostic Tool</h1>";

// Test if required files exist
echo "<h2>Required Files Check</h2>";
$requiredFiles = [
    LUNA_ROOT . '/inc/db.php',
    LUNA_ROOT . '/inc/auth.php',
    LUNA_ROOT . '/inc/functions.php',
    LUNA_ROOT . '/inc/gpt_service.php'
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ File exists: " . basename($file) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ File missing: " . basename($file) . "</p>";
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "<p style='color: red;'><strong>Error: Some required files are missing.</strong></p>";
    exit;
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once LUNA_ROOT . '/inc/db.php';
    $db = db()->getConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}

// Test auth functions
echo "<h2>Auth Functions Test</h2>";
try {
    require_once LUNA_ROOT . '/inc/auth.php';
    echo "<p style='color: green;'>✓ Auth functions loaded successfully</p>";
    
    // Test API token retrieval
    try {
        require_once LUNA_ROOT . '/inc/functions.php';
        $apiToken = getSetting('api_token', 'not_found');
        if ($apiToken !== 'not_found') {
            echo "<p style='color: green;'>✓ API Token retrieved: " . substr($apiToken, 0, 4) . "..." . substr($apiToken, -4) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ API Token not found in database</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error retrieving API token: " . $e->getMessage() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Auth functions error: " . $e->getMessage() . "</p>";
    exit;
}

// Test GPT service
echo "<h2>GPT Service Test</h2>";
try {
    require_once LUNA_ROOT . '/inc/gpt_service.php';
    echo "<p style='color: green;'>✓ GPT Service loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ GPT Service error: " . $e->getMessage() . "</p>";
    exit;
}

// Test for settings
echo "<h2>Settings Check</h2>";
try {
    $settings = [
        'openai_key' => getSetting('openai_key', 'not_set'),
        'gpt_model' => getSetting('gpt_model', 'not_set'),
        'fallback_model' => getSetting('fallback_model', 'not_set'),
        'fallback_response' => getSetting('fallback_response', 'not_set'),
        'max_retries' => getSetting('max_retries', 'not_set'),
        'rate_limit_per_minute' => getSetting('rate_limit_per_minute', 'not_set')
    ];
    
    foreach ($settings as $key => $value) {
        if ($key === 'openai_key') {
            $displayValue = $value !== 'not_set' ? substr($value, 0, 4) . "..." . substr($value, -4) : 'not_set';
        } else {
            $displayValue = $value;
        }
        
        if ($value !== 'not_set') {
            echo "<p style='color: green;'>✓ Setting found: {$key} = {$displayValue}</p>";
        } else {
            echo "<p style='color: red;'>✗ Setting missing: {$key}</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Settings retrieval error: " . $e->getMessage() . "</p>";
}

// Test webhook access manually
echo "<h2>Manual Webhook Test</h2>";
echo "<p>You can use the form below to test the webhook manually:</p>";

echo "<form method='post' action='' id='webhookTestForm'>";
echo "<p><label for='question'>Test Question:</label><br>";
echo "<input type='text' id='question' name='question' value='What is Luna?' style='width: 300px;'></p>";
echo "<input type='submit' value='Test Webhook'>";
echo "</form>";

echo "<div id='webhookResults' style='margin-top: 20px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd; display: none;'></div>";

// Show API token and test cURL command
echo "<h2>API Testing Information</h2>";
echo "<p>API Endpoint: <code>" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['REQUEST_URI']) . "/webhook.php</code></p>";
echo "<p>API Token: <code>" . $apiToken . "</code></p>";

echo "<p>Test with cURL:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo "curl -X POST \\\n";
echo "  " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['REQUEST_URI']) . "/webhook.php \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'Authorization: Bearer " . $apiToken . "' \\\n";
echo "  -d '{\"question\":\"What is Luna?\"}' \\\n";
echo "  --verbose";
echo "</pre>";

?>

<script>
document.getElementById('webhookTestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var question = document.getElementById('question').value;
    var resultsDiv = document.getElementById('webhookResults');
    
    resultsDiv.innerHTML = '<p>Testing webhook...</p>';
    resultsDiv.style.display = 'block';
    
    // Make AJAX request to test the webhook
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'webhook.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Authorization', 'Bearer <?php echo $apiToken; ?>');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            resultsDiv.innerHTML = '<h3>Success!</h3><pre>' + JSON.stringify(JSON.parse(xhr.responseText), null, 2) + '</pre>';
            resultsDiv.style.backgroundColor = '#d4edda';
        } else {
            resultsDiv.innerHTML = '<h3>Error - Status: ' + xhr.status + '</h3><pre>' + xhr.responseText + '</pre>';
            resultsDiv.style.backgroundColor = '#f8d7da';
        }
    };
    
    xhr.onerror = function() {
        resultsDiv.innerHTML = '<h3>Request Failed</h3><p>Check the console for more information.</p>';
        resultsDiv.style.backgroundColor = '#f8d7da';
    };
    
    xhr.send(JSON.stringify({question: question}));
});
</script>