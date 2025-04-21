<?php
/**
 * OpenAI API Key Validator
 * 
 * Tests if your OpenAI API key is valid and has proper permissions
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

// Initialize log file
$logFile = LUNA_ROOT . '/logs/api_key_validation.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Log function
function logValidation($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $logFile,
        "[{$timestamp}] {$message}" . PHP_EOL,
        FILE_APPEND
    );
}

// Start log
logValidation("===== Starting API Key Validation =====");

// Include required files
try {
    require_once LUNA_ROOT . '/inc/db.php';
    require_once LUNA_ROOT . '/inc/functions.php';
    logValidation("Required files loaded successfully");
} catch (Exception $e) {
    logValidation("ERROR: Failed to load required files: " . $e->getMessage());
}

// Get API key from settings or POST request
$apiKey = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
    $apiKey = trim($_POST['api_key']);
    logValidation("Using API key from form submission (" . substr($apiKey, 0, 4) . "...)");
} else {
    $apiKey = getSetting('openai_key', '');
    logValidation("Using API key from database settings (" . substr($apiKey, 0, 4) . "...)");
}

// Process form submission
$validationResult = null;
$models = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate'])) {
    // Perform validation
    logValidation("Validating API key...");
    $validationResult = validateApiKey($apiKey);
    
    // If validation succeeded, check available models
    if ($validationResult['valid']) {
        logValidation("API key is valid. Checking available models...");
        $models = getAvailableModels($apiKey);
    }
    
    // Update API key in settings if requested
    if (isset($_POST['update_key']) && $_POST['update_key'] === '1' && $validationResult['valid']) {
        logValidation("Updating API key in settings...");
        if (updateSetting('openai_key', $apiKey)) {
            logValidation("API key updated successfully in settings");
            $keyUpdated = true;
        } else {
            logValidation("Failed to update API key in settings");
            $keyUpdated = false;
        }
    }
}

/**
 * Validate OpenAI API Key
 * 
 * @param string $apiKey API key to validate
 * @return array Validation result
 */
function validateApiKey($apiKey) {
    global $logFile;
    
    logValidation("Starting key validation...");
    
    // Check if key is empty
    if (empty($apiKey)) {
        logValidation("ERROR: API key is empty");
        return [
            'valid' => false,
            'message' => 'API key is empty'
        ];
    }
    
    // Check format
    if (strpos($apiKey, 'sk-') !== 0) {
        logValidation("WARNING: API key doesn't start with 'sk-', which is unusual");
    }
    
    // Simple format validation
    $pattern = '/^sk-[A-Za-z0-9]{32,}$/';
    if (!preg_match($pattern, $apiKey)) {
        logValidation("ERROR: API key format appears invalid");
        return [
            'valid' => false,
            'message' => 'API key format appears invalid. Should start with "sk-" followed by at least 32 alphanumeric characters.'
        ];
    }
    
    // Test with a simple API call
    logValidation("Testing API key with a simple API call...");
    
    // Get models endpoint requires less permissions than chat endpoint
    $endpoint = 'https://api.openai.com/v1/models';
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    // For detailed debugging
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Get verbose log
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    logValidation("CURL verbose log:\n" . $verboseLog);
    
    // Log results
    logValidation("HTTP Code: " . $httpCode);
    if ($error) {
        logValidation("CURL Error: " . $error);
    }
    
    curl_close($ch);
    
    // Check for errors
    if ($error) {
        return [
            'valid' => false,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    // Check HTTP code
    if ($httpCode === 200) {
        logValidation("API key is valid! Successfully accessed models endpoint.");
        return [
            'valid' => true,
            'message' => 'API key is valid and working correctly'
        ];
    } elseif ($httpCode === 401) {
        logValidation("ERROR: API key is invalid (Unauthorized)");
        return [
            'valid' => false,
            'message' => 'Invalid API key - authentication failed'
        ];
    } elseif ($httpCode === 403) {
        logValidation("ERROR: API key lacks permissions (Forbidden)");
        return [
            'valid' => false,
            'message' => 'API key is valid but lacks necessary permissions'
        ];
    } elseif ($httpCode === 429) {
        logValidation("ERROR: Rate limited (Too Many Requests)");
        return [
            'valid' => false,
            'message' => 'Rate limited - your API key has hit its rate limit'
        ];
    } else {
        logValidation("ERROR: Unexpected HTTP code: " . $httpCode);
        return [
            'valid' => false,
            'message' => 'Unexpected error (HTTP ' . $httpCode . '): ' . $response
        ];
    }
}

/**
 * Get available models for the API key
 * 
 * @param string $apiKey API key to use
 * @return array List of available models
 */
function getAvailableModels($apiKey) {
    logValidation("Getting available models...");
    
    $endpoint = 'https://api.openai.com/v1/models';
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Check for errors
    if ($error || $httpCode !== 200) {
        logValidation("ERROR: Failed to get models: " . ($error ?: "HTTP " . $httpCode));
        return [];
    }
    
    // Parse response
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data'])) {
        logValidation("ERROR: Invalid response format");
        return [];
    }
    
    // Filter chat models (those that start with gpt-)
    $chatModels = [];
    $modelCount = 0;
    foreach ($data['data'] as $model) {
        $modelCount++;
        if (isset($model['id']) && strpos($model['id'], 'gpt-') === 0) {
            $chatModels[] = $model['id'];
        }
    }
    
    logValidation("Found " . $modelCount . " total models, " . count($chatModels) . " chat models");
    return $chatModels;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenAI API Key Validator</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #2980b9;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .valid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .invalid {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .models-list {
            margin-top: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .checkbox-group {
            margin-top: 10px;
        }
        .back-link {
            margin-top: 20px;
        }
        .key-display {
            font-family: monospace;
            padding: 5px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OpenAI API Key Validator</h1>
        
        <p>This tool verifies if your API key is working and has the necessary permissions for Luna Chatbot.</p>
        
        <?php if (isset($keyUpdated)): ?>
            <?php if ($keyUpdated): ?>
                <div class="result valid">
                    <p><strong>Success!</strong> API key has been updated in your settings.</p>
                </div>
            <?php else: ?>
                <div class="result invalid">
                    <p><strong>Error!</strong> Failed to update API key in settings.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="api_key">OpenAI API Key</label>
                <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($apiKey); ?>" placeholder="sk-...">
                <?php if (!empty($apiKey)): ?>
                    <p>Current key (first/last 4 chars): <span class="key-display"><?php echo substr($apiKey, 0, 4) . '...' . substr($apiKey, -4); ?></span></p>
                <?php endif; ?>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="update_key" name="update_key" value="1" checked>
                <label for="update_key" style="display:inline;">Update API key in settings if valid</label>
            </div>
            
            <div class="form-group">
                <button type="submit" name="validate">Validate API Key</button>
            </div>
        </form>
        
        <?php if ($validationResult !== null): ?>
            <div class="result <?php echo $validationResult['valid'] ? 'valid' : 'invalid'; ?>">
                <h3>Validation Result</h3>
                <p><?php echo $validationResult['message']; ?></p>
            </div>
            
            <?php if ($validationResult['valid'] && !empty($models)): ?>
                <div class="models-list">
                    <h3>Available GPT Models</h3>
                    <p>Your API key has access to the following GPT models:</p>
                    <ul>
                        <?php foreach ($models as $model): ?>
                            <li><?php echo htmlspecialchars($model); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><strong>Recommendation:</strong> Use one of these models in your Luna settings.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!$validationResult['valid']): ?>
                <div class="models-list">
                    <h3>Troubleshooting</h3>
                    <p>Common API key issues:</p>
                    <ul>
                        <li>Incorrect or expired API key</li>
                        <li>API key doesn't have permission to access the models</li>
                        <li>API usage limit reached</li>
                        <li>Account payment issues</li>
                    </ul>
                    <p>Make sure your key is correctly copied from the <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API keys page</a>.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="back-link">
            <p><a href="admin/settings.php">Back to Settings</a> | <a href="fix_openai_model.php">Check Model Names</a> | <a href="test_openai_api.php">Run Full API Test</a></p>
        </div>
    </div>
</body>
</html>