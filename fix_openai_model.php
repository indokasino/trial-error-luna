<?php
/**
 * OpenAI Model Fix Utility (Updated)
 * 
 * This script detects your current model settings and helps fix them
 * Specifically handling gpt-4.1 and o4-mini models
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__FILE__));

echo "<h1>OpenAI Model Fix Utility</h1>";

// Include required files
try {
    require_once LUNA_ROOT . '/inc/db.php';
    require_once LUNA_ROOT . '/inc/functions.php';
    echo "<p style='color: green;'>Required files loaded successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading required files: " . $e->getMessage() . "</p>";
    exit;
}

// Get current model settings
$currentPrimaryModel = getSetting('gpt_model', 'gpt-3.5-turbo');
$currentFallbackModel = getSetting('fallback_model', 'gpt-3.5-turbo');

// List of known working models
$knownModels = [
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Most reliable)',
    'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
    'gpt-4' => 'GPT-4 (Slower but more capable)',
    'gpt-4-turbo' => 'GPT-4 Turbo',
    'gpt-4-vision-preview' => 'GPT-4 Vision Preview',
    'gpt-4o' => 'GPT-4o (Requires recent API key)'
];

// Handle special model names specific to Luna
function getRecommendedSubstitution($model) {
    $specialModels = [
        'gpt-4.1' => [
            'recommended' => 'gpt-4-turbo',
            'explanation' => 'gpt-4.1 is not a standard OpenAI model. gpt-4-turbo is the equivalent model.'
        ],
        'o4-mini' => [
            'recommended' => 'gpt-4',
            'explanation' => 'o4-mini is not a standard OpenAI model. gpt-4 is recommended as a substitute.'
        ]
    ];
    
    return $specialModels[$model] ?? null;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_models'])) {
        $primaryModel = $_POST['primary_model'] ?? $currentPrimaryModel;
        $fallbackModel = $_POST['fallback_model'] ?? $currentFallbackModel;
        
        // Update settings
        $primaryUpdated = updateSetting('gpt_model', $primaryModel);
        $fallbackUpdated = updateSetting('fallback_model', $fallbackModel);
        
        if ($primaryUpdated && $fallbackUpdated) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>";
            echo "Models updated successfully! New settings:";
            echo "<ul>";
            echo "<li><strong>Primary Model:</strong> $primaryModel</li>";
            echo "<li><strong>Fallback Model:</strong> $fallbackModel</li>";
            echo "</ul>";
            echo "</div>";
            
            // Update current values
            $currentPrimaryModel = $primaryModel;
            $currentFallbackModel = $fallbackModel;
        } else {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>";
            echo "Error updating settings. Please check database connection.";
            echo "</div>";
        }
    }
}

// Check for special model cases
$primaryRecommendation = getRecommendedSubstitution($currentPrimaryModel);
$fallbackRecommendation = getRecommendedSubstitution($currentFallbackModel);

// Test API with current model
function testModel($model, $apiKey) {
    if (empty($apiKey)) {
        return [
            'success' => false,
            'message' => 'API key not set. Please configure your API key first.'
        ];
    }
    
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
                'content' => 'Say hello'
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 50,
    ];
    
    // Initialize cURL
    $ch = curl_init($endpoint);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    // Close cURL
    curl_close($ch);
    
    // Handle errors
    if ($error) {
        return [
            'success' => false,
            'message' => "Connection error: $error"
        ];
    }
    
    if ($httpCode >= 400) {
        $responseData = json_decode($response, true);
        $errorMessage = $responseData['error']['message'] ?? "HTTP error $httpCode";
        
        return [
            'success' => false,
            'message' => $errorMessage
        ];
    }
    
    return [
        'success' => true,
        'message' => "Model works correctly"
    ];
}

// Get API key
$apiKey = getSetting('openai_key', '');
$primaryModelTest = null;
$fallbackModelTest = null;

// Test models if requested
if (isset($_GET['test_models']) && $_GET['test_models'] === '1') {
    $primaryModelTest = testModel($currentPrimaryModel, $apiKey);
    if ($currentFallbackModel !== 'none') {
        $fallbackModelTest = testModel($currentFallbackModel, $apiKey);
    }
}

?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Current Model Settings</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Model Type</th>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Current Value</th>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Status</th>
                <?php if (isset($_GET['test_models']) && $_GET['test_models'] === '1'): ?>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Test Result</th>
                <?php endif; ?>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">Primary Model</td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;"><code><?php echo htmlspecialchars($currentPrimaryModel); ?></code></td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                    <?php if (array_key_exists($currentPrimaryModel, $knownModels)): ?>
                        <span style="color: green;">✓ Standard Model</span>
                    <?php elseif ($primaryRecommendation): ?>
                        <span style="color: orange;">⚠ Non-standard Model</span>
                    <?php else: ?>
                        <span style="color: orange;">⚠ Unknown Model</span>
                    <?php endif; ?>
                </td>
                <?php if (isset($_GET['test_models']) && $_GET['test_models'] === '1'): ?>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                    <?php if ($primaryModelTest): ?>
                        <?php if ($primaryModelTest['success']): ?>
                            <span style="color: green;">✓ Working</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Failed: <?php echo htmlspecialchars($primaryModelTest['message']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">Fallback Model</td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;"><code><?php echo htmlspecialchars($currentFallbackModel); ?></code></td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                    <?php if ($currentFallbackModel === 'none'): ?>
                        <span style="color: blue;">No Fallback</span>
                    <?php elseif (array_key_exists($currentFallbackModel, $knownModels)): ?>
                        <span style="color: green;">✓ Standard Model</span>
                    <?php elseif ($fallbackRecommendation): ?>
                        <span style="color: orange;">⚠ Non-standard Model</span>
                    <?php else: ?>
                        <span style="color: orange;">⚠ Unknown Model</span>
                    <?php endif; ?>
                </td>
                <?php if (isset($_GET['test_models']) && $_GET['test_models'] === '1'): ?>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                    <?php if ($fallbackModelTest): ?>
                        <?php if ($fallbackModelTest['success']): ?>
                            <span style="color: green;">✓ Working</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Failed: <?php echo htmlspecialchars($fallbackModelTest['message']); ?></span>
                        <?php endif; ?>
                    <?php elseif ($currentFallbackModel === 'none'): ?>
                        <span>Not Tested</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        </table>
        
        <?php if (!isset($_GET['test_models'])): ?>
        <p><a href="?test_models=1" style="background-color: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;">Test Current Models</a></p>
        <?php endif; ?>
    </div>
    
    <?php if ($primaryRecommendation): ?>
    <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Primary Model Recommendation</h3>
        <p>Your current primary model <code><?php echo htmlspecialchars($currentPrimaryModel); ?></code> is non-standard.</p>
        <p><?php echo $primaryRecommendation['explanation']; ?></p>
        <p>Recommended replacement: <code><?php echo $primaryRecommendation['recommended']; ?></code></p>
    </div>
    <?php endif; ?>
    
    <?php if ($fallbackRecommendation && $currentFallbackModel !== 'none'): ?>
    <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Fallback Model Recommendation</h3>
        <p>Your current fallback model <code><?php echo htmlspecialchars($currentFallbackModel); ?></code> is non-standard.</p>
        <p><?php echo $fallbackRecommendation['explanation']; ?></p>
        <p>Recommended replacement: <code><?php echo $fallbackRecommendation['recommended']; ?></code></p>
    </div>
    <?php endif; ?>
    
    <form method="post">
        <div style="background-color: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h3>Update Models</h3>
            
            <div style="margin-bottom: 15px;">
                <label for="primary_model" style="display: block; margin-bottom: 5px; font-weight: bold;">Primary Model:</label>
                <select id="primary_model" name="primary_model" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php if (!array_key_exists($currentPrimaryModel, $knownModels)): ?>
                        <option value="<?php echo htmlspecialchars($currentPrimaryModel); ?>" selected>
                            <?php echo htmlspecialchars($currentPrimaryModel); ?> (Current Custom Model)
                        </option>
                    <?php endif; ?>
                    
                    <?php if ($primaryRecommendation): ?>
                        <option value="<?php echo htmlspecialchars($primaryRecommendation['recommended']); ?>">
                            <?php echo htmlspecialchars($primaryRecommendation['recommended']); ?> (Recommended replacement)
                        </option>
                    <?php endif; ?>
                    
                    <?php foreach ($knownModels as $model => $description): ?>
                        <option value="<?php echo $model; ?>" <?php echo ($model === $currentPrimaryModel) ? 'selected' : ''; ?>>
                            <?php echo $model; ?> - <?php echo $description; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="fallback_model" style="display: block; margin-bottom: 5px; font-weight: bold;">Fallback Model:</label>
                <select id="fallback_model" name="fallback_model" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="none" <?php echo ($currentFallbackModel === 'none') ? 'selected' : ''; ?>>No Fallback</option>
                    
                    <?php if (!array_key_exists($currentFallbackModel, $knownModels) && $currentFallbackModel !== 'none'): ?>
                        <option value="<?php echo htmlspecialchars($currentFallbackModel); ?>" selected>
                            <?php echo htmlspecialchars($currentFallbackModel); ?> (Current Custom Model)
                        </option>
                    <?php endif; ?>
                    
                    <?php if ($fallbackRecommendation): ?>
                        <option value="<?php echo htmlspecialchars($fallbackRecommendation['recommended']); ?>">
                            <?php echo htmlspecialchars($fallbackRecommendation['recommended']); ?> (Recommended replacement)
                        </option>
                    <?php endif; ?>
                    
                    <?php foreach ($knownModels as $model => $description): ?>
                        <option value="<?php echo $model; ?>" <?php echo ($model === $currentFallbackModel) ? 'selected' : ''; ?>>
                            <?php echo $model; ?> - <?php echo $description; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="update_models" style="padding: 10px 15px; background-color: #3498db; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                Update Models
            </button>
        </div>
    </form>
    
    <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Recommendations</h3>
        <p>If you're experiencing issues with the API:</p>
        <ul>
            <li>Start with <code>gpt-3.5-turbo</code> which is the most reliable model</li>
            <li>If you're using custom model names like <code>gpt-4.1</code> or <code>o4-mini</code>, consider using standard OpenAI model names</li>
            <li>Make sure your API key has access to the models you're trying to use</li>
            <li>Some newer models require recent API keys</li>
        </ul>
    </div>
    
    <div>
        <p><a href="test_openai_api.php">Run API Test</a> | <a href="admin/settings.php">Back to Settings</a></p>
    </div>
</div>