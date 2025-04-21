<?php
/**
 * OpenAI API Test Script - Updated for Luna
 * 
 * Specifically supports custom model names like gpt-4.1 and o4-mini
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

// Check if logs directory exists, create if not
$logsDir = LUNA_ROOT . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create log file
$logFile = $logsDir . '/api_test_' . date('Y-m-d_H-i-s') . '.log';
file_put_contents($logFile, "=== OpenAI API Test Log ===\n");

// Log function
function logTest($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $logFile,
        "[{$timestamp}] {$message}" . PHP_EOL,
        FILE_APPEND
    );
    
    // Also echo for browser output
    echo "<div class='log-entry'><span class='timestamp'>[{$timestamp}]</span> {$message}</div>\n";
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenAI API Test</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f7fa;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .log-container {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            height: 400px;
            overflow-y: auto;
            font-family: monospace;
            margin-bottom: 20px;
        }
        .log-entry {
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .timestamp {
            color: #6c757d;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .warning {
            color: orange;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #e9ecef;
            text-align: left;
        }
        th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OpenAI API Test</h1>
        <p>This script will test your OpenAI API integration and help identify issues.</p>
    </div>
    
    <div class="container">
        <h2>Test Results</h2>
        <div class="log-container" id="logOutput">
';

// Log system information
logTest("PHP Version: " . phpversion());
logTest("Server Software: " . $_SERVER['SERVER_SOFTWARE']);
logTest("Operating System: " . PHP_OS);
logTest("Script Path: " . __FILE__);

// Check for required extensions
$requiredExtensions = ['curl', 'json', 'pdo', 'pdo_mysql'];
foreach ($requiredExtensions as $ext) {
    logTest("Extension {$ext}: " . (extension_loaded($ext) ? "Loaded" : "MISSING"));
}

// Try to include required files
logTest("Loading required files...");
try {
    require_once LUNA_ROOT . '/inc/db.php';
    logTest("  - db.php loaded successfully");
    
    require_once LUNA_ROOT . '/inc/functions.php';
    logTest("  - functions.php loaded successfully");
} catch (Exception $e) {
    logTest("<span class='error'>ERROR: Failed to load required files: " . $e->getMessage() . "</span>");
    echo '</div></div></body></html>';
    exit;
}

// Get OpenAI settings
logTest("Retrieving API settings...");
try {
    $apiKey = getSetting('openai_key', '');
    $primaryModel = getSetting('gpt_model', 'gpt-3.5-turbo');
    $fallbackModel = getSetting('fallback_model', 'gpt-3.5-turbo');
    
    // Mask API key for display
    $maskedKey = !empty($apiKey) ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'empty';
    logTest("  - API Key: " . $maskedKey);
    logTest("  - Primary Model: " . $primaryModel);
    logTest("  - Fallback Model: " . $fallbackModel);
    
    // Check for custom model names and map to standard OpenAI models for testing
    $modelMappings = [
        'gpt-4.1' => 'gpt-4-turbo',
        'o4-mini' => 'gpt-4'
    ];
    
    $primaryStandardModel = $modelMappings[$primaryModel] ?? $primaryModel;
    $fallbackStandardModel = $modelMappings[$fallbackModel] ?? $fallbackModel;
    
    if ($primaryStandardModel !== $primaryModel) {
        logTest("<span class='warning'>  - Note: Primary model '" . $primaryModel . "' mapped to '" . $primaryStandardModel . "' for testing</span>");
    }
    
    if ($fallbackStandardModel !== $fallbackModel && $fallbackModel !== 'none') {
        logTest("<span class='warning'>  - Note: Fallback model '" . $fallbackModel . "' mapped to '" . $fallbackStandardModel . "' for testing</span>");
    }
    
    // Validate API key format
    if (empty($apiKey)) {
        logTest("<span class='error'>ERROR: API key is empty</span>");
    } else if ($apiKey === 'sk-your-openai-key' || $apiKey === 'placeholder_key') {
        logTest("<span class='error'>ERROR: API key is a placeholder value</span>");
    } else if (strpos($apiKey, 'sk-') !== 0) {
        logTest("<span class='warning'>WARNING: API key doesn't start with 'sk-' which is unusual for OpenAI keys</span>");
    }
} catch (Exception $e) {
    logTest("<span class='error'>ERROR: Failed to retrieve API settings: " . $e->getMessage() . "</span>");
}

// Test OpenAI API function
function testOpenAI($apiKey, $model, $question = "What is the capital of France?") {
    global $logFile;
    
    if (empty($apiKey)) {
        return [
            'success' => false,
            'error' => 'API key is empty'
        ];
    }
    
    logTest("Preparing API request with model: " . $model);
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    
    // Prepare request data
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant.'
            ],
            [
                'role' => 'user',
                'content' => $question
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 150,
    ];
    
    $jsonData = json_encode($data);
    logTest("Request data: " . substr($jsonData, 0, 100) . "...");
    
    // Initialize cURL
    $ch = curl_init($endpoint);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // For detailed debugging
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Execute request
    logTest("Sending request to OpenAI...");
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Get verbose information
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    // Log only important parts of verbose output
    $verboseExcerpt = preg_match('/Connected to .+?\(([0-9\.]+)\)/', $verboseLog, $matches) ? 
        "Connected to IP: " . $matches[1] : 
        "Connection details not found in log";
    logTest("CURL connection: " . $verboseExcerpt);
    
    // Check connection time
    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $executionTime = round($endTime - $startTime, 2);
    logTest("Connection time: " . round($connectTime, 2) . "s, Total time: " . round($totalTime, 2) . "s, Execution time: " . $executionTime . "s");
    
    // Log HTTP info
    logTest("HTTP Code: " . $httpCode);
    
    // Close cURL
    curl_close($ch);
    
    // Handle errors
    if ($error) {
        logTest("<span class='error'>CURL Error: " . $error . "</span>");
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode
        ];
    }
    
    // Log response
    logTest("Response received (" . strlen($response) . " bytes)");
    
    // Write full response to log file but not to screen
    file_put_contents($logFile, "Response: " . $response . "\n", FILE_APPEND);
    
    // Parse response
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logTest("<span class='error'>JSON decode error: " . json_last_error_msg() . "</span>");
        return [
            'success' => false,
            'error' => 'JSON decode error: ' . json_last_error_msg(),
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
    
    // Check for error in response
    if (isset($responseData['error'])) {
        $errorMsg = $responseData['error']['message'] ?? 'Unknown error';
        logTest("<span class='error'>API Error: " . $errorMsg . "</span>");
        return [
            'success' => false,
            'error' => 'API error: ' . $errorMsg,
            'http_code' => $httpCode,
            'response' => $responseData
        ];
    }
    
    // Extract content
    $content = $responseData['choices'][0]['message']['content'] ?? null;
    if ($content) {
        logTest("<span class='success'>Successfully received content (" . strlen($content) . " chars)</span>");
        logTest("Content preview: \"" . substr($content, 0, 50) . "...\"");
    } else {
        logTest("<span class='error'>No content found in response</span>");
    }
    
    return [
        'success' => true,
        'http_code' => $httpCode,
        'content' => $content,
        'response' => $responseData
    ];
}

// Setup test models
$testModels = [];

// Add primary model
if (!empty($primaryModel)) {
    $testModels[$primaryModel] = [
        'description' => 'Primary Model',
        'test_model' => $primaryStandardModel
    ];
}

// Add fallback model if not 'none' and different from primary
if (!empty($fallbackModel) && $fallbackModel !== 'none' && $fallbackModel !== $primaryModel) {
    $testModels[$fallbackModel] = [
        'description' => 'Fallback Model',
        'test_model' => $fallbackStandardModel
    ];
}

// Always include gpt-3.5-turbo for reference if not already included
if (!isset($testModels['gpt-3.5-turbo'])) {
    $testModels['gpt-3.5-turbo'] = [
        'description' => 'Reference Model (most reliable)',
        'test_model' => 'gpt-3.5-turbo'
    ];
}

// Test results array
$testResults = [];

// Test each model
foreach ($testModels as $modelName => $modelInfo) {
    logTest("\n=== Testing model: " . $modelName . " (" . $modelInfo['description'] . ") ===");
    
    // If test_model is different from model name, we're using a mapped standard name
    $testModel = $modelInfo['test_model'];
    if ($testModel !== $modelName) {
        logTest("Using '" . $testModel . "' as standard equivalent for testing");
    }
    
    $result = testOpenAI($apiKey, $testModel);
    $testResults[$modelName] = $result;
    
    if ($result['success']) {
        logTest("<span class='success'>✅ SUCCESS with model " . $modelName . "</span>");
    } else {
        logTest("<span class='error'>❌ FAILED with model " . $modelName . "</span>");
        logTest("Error: " . $result['error']);
    }
    
    // Add a separator
    logTest("-------------------------------------------");
}

// Check common issues
logTest("\n=== Checking for common issues ===");

// Test if system allows outbound connections
logTest("Testing general internet connectivity...");
$testUrl = 'https://www.google.com';
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$testResponse = curl_exec($ch);
$testError = curl_error($ch);
curl_close($ch);

if ($testError) {
    logTest("<span class='error'>❌ General connectivity test failed: " . $testError . "</span>");
    logTest("This suggests your server might have outbound connection issues");
} else {
    logTest("<span class='success'>✅ General connectivity test successful</span>");
}

// Check OpenAI API connectivity without authentication
logTest("Checking OpenAI API connectivity (without auth)...");
$ch = curl_init('https://api.openai.com/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$testResponse = curl_exec($ch);
$testError = curl_error($ch);
curl_close($ch);

if ($testError) {
    logTest("<span class='error'>❌ OpenAI API connectivity test failed: " . $testError . "</span>");
    logTest("This suggests your server might be blocking connections to OpenAI");
} else {
    logTest("<span class='success'>✅ OpenAI API connectivity test successful</span>");
}

// Check SSL certificates
logTest("Checking SSL certificate handling...");
$ch = curl_init('https://api.openai.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$testResponse = curl_exec($ch);
$testError = curl_error($ch);
curl_close($ch);

if ($testError && strpos($testError, 'SSL certificate') !== false) {
    logTest("<span class='error'>❌ SSL certificate verification failed: " . $testError . "</span>");
    logTest("This suggests your server has SSL certificate issues");
} else {
    logTest("<span class='success'>✅ SSL certificate verification successful</span>");
}

// Final summary
logTest("\n=== TEST SUMMARY ===");
logTest("Log file saved to: " . $logFile);

$anySuccess = false;
foreach ($testResults as $modelName => $result) {
    if ($result['success']) {
        $anySuccess = true;
        break;
    }
}

if ($anySuccess) {
    logTest("<span class='success'>At least one model test succeeded! The API connection works.</span>");
} else {
    logTest("<span class='error'>All tests failed. Your OpenAI API integration is not working.</span>");
}

echo '</div>'; // Close log-container

// Results table
echo '<h3>Model Test Results</h3>';
echo '<table>
    <tr>
        <th>Model Name</th>
        <th>Description</th>
        <th>Test Model</th>
        <th>Status</th>
        <th>Details</th>
    </tr>';

foreach ($testModels as $modelName => $modelInfo) {
    $result = $testResults[$modelName];
    echo '<tr>';
    echo '<td>' . htmlspecialchars($modelName) . '</td>';
    echo '<td>' . htmlspecialchars($modelInfo['description']) . '</td>';
    echo '<td>' . htmlspecialchars($modelInfo['test_model']) . '</td>';
    
    if ($result['success']) {
        echo '<td style="color: green;">Success</td>';
        echo '<td>Response received (' . strlen($result['content']) . ' chars)</td>';
    } else {
        echo '<td style="color: red;">Failed</td>';
        echo '<td>' . htmlspecialchars($result['error']) . '</td>';
    }
    
    echo '</tr>';
}

echo '</table>';

// Recommendations
echo '<h3>Recommendations</h3>';
echo '<ul>';

if (empty($apiKey) || $apiKey === 'sk-your-openai-key' || $apiKey === 'placeholder_key') {
    echo '<li><strong style="color: red;">Set a valid OpenAI API key</strong> in your settings.</li>';
}

if (isset($testResults['gpt-3.5-turbo']) && !$testResults['gpt-3.5-turbo']['success']) {
    echo '<li><strong style="color: red;">Fix API connectivity issues</strong> - even the most reliable model is failing.</li>';
} else if (!$anySuccess) {
    echo '<li><strong style="color: red;">Check model names</strong> - try using gpt-3.5-turbo instead of custom model names.</li>';
}

// Specific recommendations for custom model names
if (isset($testModels[$primaryModel]) && $testModels[$primaryModel]['test_model'] !== $primaryModel) {
    if (isset($testResults[$primaryModel]) && !$testResults[$primaryModel]['success']) {
        echo '<li><strong style="color: orange;">Your primary model "' . htmlspecialchars($primaryModel) . '" is non-standard</strong>. Consider changing to "' . htmlspecialchars($testModels[$primaryModel]['test_model']) . '".</li>';
    }
}

if (isset($testModels[$fallbackModel]) && $fallbackModel !== 'none' && $testModels[$fallbackModel]['test_model'] !== $fallbackModel) {
    if (isset($testResults[$fallbackModel]) && !$testResults[$fallbackModel]['success']) {
        echo '<li><strong style="color: orange;">Your fallback model "' . htmlspecialchars($fallbackModel) . '" is non-standard</strong>. Consider changing to "' . htmlspecialchars($testModels[$fallbackModel]['test_model']) . '".</li>';
    }
}

echo '<li>Review the log output above for detailed information about any failures.</li>';
echo '</ul>';

echo '<div style="margin-top: 20px;">
    <a href="fix_openai_model_updated.php" class="btn">Fix Model Settings</a>
    <a href="admin/settings.php" class="btn">Back to Settings</a>
</div>';

echo '</div>'; // Close container

echo '</body></html>';
?>