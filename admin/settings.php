<?php
/**
 * Admin Settings Page - Simplified Version
 * 
 * Manage API keys, tokens, system settings, and prompt template
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';  // Path ini sudah benar
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Require login
requireLogin();

// Generate CSRF token
$csrfToken = auth()->generateCsrfToken();

// Initialize variables
$error = '';
$success = '';

// Path to prompt template file
$promptFilePath = LUNA_ROOT . '/prompt-luna.txt';

// Create the prompt file if it doesn't exist
if (!file_exists($promptFilePath)) {
    try {
        file_put_contents($promptFilePath, "You are Luna, a helpful AI assistant.");
    } catch (Exception $e) {
        $error = "Failed to create prompt template file: " . $e->getMessage();
    }
}

// Get the current content of the prompt file
$promptContent = '';
if (file_exists($promptFilePath)) {
    try {
        $promptContent = file_get_contents($promptFilePath);
        if ($promptContent === false) {
            $error = "Failed to read prompt template file.";
            $promptContent = "";
        }
    } catch (Exception $e) {
        $error = "Failed to read prompt template file: " . $e->getMessage();
        $promptContent = "";
    }
}

// Get current settings directly from database
try {
    $db = db()->getConnection();
    
    // Get settings directly
    $settings = [];
    $stmt = $db->query("SELECT `key`, value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    // Set default values if not found
    $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
    $openai_key = isset($settings['openai_key']) ? $settings['openai_key'] : '';
    $gpt_model = isset($settings['gpt_model']) ? $settings['gpt_model'] : 'gpt-4.1';
    $fallback_model = isset($settings['fallback_model']) ? $settings['fallback_model'] : 'o4-mini';
    $fallback_response = isset($settings['fallback_response']) ? $settings['fallback_response'] : 'Sorry, I could not process your request at this time. Please try again later.';
    $max_retries = isset($settings['max_retries']) ? $settings['max_retries'] : '3';
    $rate_limit_per_minute = isset($settings['rate_limit_per_minute']) ? $settings['rate_limit_per_minute'] : '10';
    $log_retention_days = isset($settings['log_retention_days']) ? $settings['log_retention_days'] : '90';
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    
    // Set default values on error
    $api_token = '';
    $openai_key = '';
    $gpt_model = 'gpt-4.1';
    $fallback_model = 'o4-mini';
    $fallback_response = 'Sorry, I could not process your request at this time. Please try again later.';
    $max_retries = '3';
    $rate_limit_per_minute = '10';
    $log_retention_days = '90';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!auth()->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        try {
            // Begin transaction for database settings
            $db->beginTransaction();
            
            // Get values from form
            $formApiToken = isset($_POST['api_token']) ? trim($_POST['api_token']) : $api_token;
            $formOpenaiKey = isset($_POST['openai_key']) ? trim($_POST['openai_key']) : $openai_key;
            $formGptModel = isset($_POST['gpt_model']) ? $_POST['gpt_model'] : $gpt_model;
            $formFallbackModel = isset($_POST['fallback_model']) ? $_POST['fallback_model'] : $fallback_model;
            $formFallbackResponse = isset($_POST['fallback_response']) ? trim($_POST['fallback_response']) : $fallback_response;
            $formMaxRetries = isset($_POST['max_retries']) ? trim($_POST['max_retries']) : $max_retries;
            $formRateLimit = isset($_POST['rate_limit_per_minute']) ? trim($_POST['rate_limit_per_minute']) : $rate_limit_per_minute;
            $formLogRetention = isset($_POST['log_retention_days']) ? trim($_POST['log_retention_days']) : $log_retention_days;
            
            // Generate new token if requested
            if (isset($_POST['generate_new_token']) && $_POST['generate_new_token'] === '1') {
                $formApiToken = bin2hex(random_bytes(16));
            }
            
            // Update settings directly in database
            $updateStmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE value = ?");
            
            $updateStmt->execute(['api_token', $formApiToken, $formApiToken]);
            $updateStmt->execute(['openai_key', $formOpenaiKey, $formOpenaiKey]);
            $updateStmt->execute(['gpt_model', $formGptModel, $formGptModel]);
            $updateStmt->execute(['fallback_model', $formFallbackModel, $formFallbackModel]);
            $updateStmt->execute(['fallback_response', $formFallbackResponse, $formFallbackResponse]);
            $updateStmt->execute(['max_retries', $formMaxRetries, $formMaxRetries]);
            $updateStmt->execute(['rate_limit_per_minute', $formRateLimit, $formRateLimit]);
            $updateStmt->execute(['log_retention_days', $formLogRetention, $formLogRetention]);
            
            // Commit transaction
            $db->commit();
            
            // Update variables with new values
            $api_token = $formApiToken;
            $openai_key = $formOpenaiKey;
            $gpt_model = $formGptModel;
            $fallback_model = $formFallbackModel;
            $fallback_response = $formFallbackResponse;
            $max_retries = $formMaxRetries;
            $rate_limit_per_minute = $formRateLimit;
            $log_retention_days = $formLogRetention;
            
            // Handle prompt template update if provided
            if (isset($_POST['prompt_template'])) {
                $newPromptContent = $_POST['prompt_template'];
                
                if ((file_exists($promptFilePath) && is_writable($promptFilePath)) || is_writable(dirname($promptFilePath))) {
                    // Write to the prompt file
                    $writeResult = file_put_contents($promptFilePath, $newPromptContent);
                    if ($writeResult !== false) {
                        $promptContent = $newPromptContent;
                        $success = 'Settings and prompt template updated successfully.';
                    } else {
                        $error = 'Settings updated but failed to save prompt template. Check file permissions.';
                    }
                } else {
                    $error = 'Settings updated but failed to save prompt template. File not writable.';
                }
            } else {
                $success = 'Settings updated successfully.';
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Database error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Settings</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Additional styles for prompt template section */
        .prompt-editor {
            margin-top: 20px;
        }
        #prompt_template {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            width: 100%;
            min-height: 300px;
            padding: 12px;
            line-height: 1.5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .editor-controls {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
        }
        .editor-info {
            margin-top: 10px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>System Settings</h1>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="settings.php" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="settings-card">
                <h2>API Authentication</h2>
                
                <div class="form-group">
                    <label for="api_token">API Token</label>
                    <div class="token-input-group">
                        <input type="text" id="api_token" name="api_token" 
                               value="<?php echo sanitize($api_token); ?>" readonly>
                        <div class="token-actions">
                            <button type="button" id="show-token" class="btn btn-sm btn-secondary">Show</button>
                            <label class="token-checkbox">
                                <input type="checkbox" name="generate_new_token" value="1"> Generate New
                            </label>
                        </div>
                    </div>
                    <small class="form-text text-muted">This token is used to authenticate API requests. Keep it secure.</small>
                </div>
            </div>
            
            <div class="settings-card">
                <h2>OpenAI Integration</h2>
                
                <div class="form-group">
                    <label for="openai_key">OpenAI API Key</label>
                    <input type="password" id="openai_key" name="openai_key" 
                           value="<?php echo sanitize($openai_key); ?>">
                    <small class="form-text text-muted">Your OpenAI API key for GPT access.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="gpt_model">Primary GPT Model</label>
                        <select id="gpt_model" name="gpt_model">
                            <option value="gpt-4.1" <?php echo $gpt_model === 'gpt-4.1' ? 'selected' : ''; ?>>GPT-4.1</option>
                            <option value="gpt-4" <?php echo $gpt_model === 'gpt-4' ? 'selected' : ''; ?>>GPT-4</option>
                            <option value="gpt-3.5-turbo" <?php echo $gpt_model === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="fallback_model">Fallback Model</label>
                        <select id="fallback_model" name="fallback_model">
                            <option value="o4-mini" <?php echo $fallback_model === 'o4-mini' ? 'selected' : ''; ?>>o4-mini</option>
                            <option value="gpt-3.5-turbo" <?php echo $fallback_model === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                            <option value="none" <?php echo $fallback_model === 'none' ? 'selected' : ''; ?>>No Fallback</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="settings-card">
                <h2>System Configuration</h2>
                
                <div class="form-group">
                    <label for="fallback_response">Fallback Response Message</label>
                    <textarea id="fallback_response" name="fallback_response" rows="3"><?php echo sanitize($fallback_response); ?></textarea>
                    <small class="form-text text-muted">Message shown when both primary and fallback models fail.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="max_retries">Max API Retries</label>
                        <input type="number" id="max_retries" name="max_retries" min="1" max="5" 
                               value="<?php echo sanitize($max_retries); ?>">
                    </div>
                    
                    <div class="form-group col-md-4">
                        <label for="rate_limit_per_minute">Rate Limit (requests/minute)</label>
                        <input type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" min="1" max="60" 
                               value="<?php echo sanitize($rate_limit_per_minute); ?>">
                    </div>
                    
                    <div class="form-group col-md-4">
                        <label for="log_retention_days">Log Retention (days)</label>
                        <input type="number" id="log_retention_days" name="log_retention_days" min="1" max="365" 
                               value="<?php echo sanitize($log_retention_days); ?>">
                    </div>
                </div>
            </div>
            
            <div class="settings-card">
                <h2>Prompt Template</h2>
                <p>Configure the system prompt that defines your chatbot's behavior, personality, and knowledge scope.</p>
                
                <div class="prompt-editor">
                    <textarea id="prompt_template" name="prompt_template" rows="12"><?php echo htmlspecialchars($promptContent); ?></textarea>
                    
                    <div class="editor-info">
                        <p>This prompt will be sent as the "system message" to the GPT model, establishing how the AI should behave.</p>
                        <p>File location: <?php echo sanitize($promptFilePath); ?></p>
                        <p>File permissions: <?php 
                            echo file_exists($promptFilePath) 
                                ? (is_writable($promptFilePath) ? 'Writable' : 'Not writable (please fix this)')
                                : 'File does not exist';
                        ?></p>
                    </div>
                    
                    <div class="editor-controls">
                        <button type="button" id="expand-editor" class="btn btn-sm btn-secondary">Expand Editor</button>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save All Settings</button>
                <a href="index.php" class="btn btn-link">Cancel</a>
            </div>
        </form>
        
        <div class="settings-card maintenance-card">
            <h2>Maintenance</h2>
            
            <div class="maintenance-actions">
                <a href="maintenance.php?action=clean_logs&token=<?php echo $csrfToken; ?>" 
                   class="btn btn-warning" onclick="return confirm('Are you sure you want to clean old logs?');">
                    Clean Old Logs
                </a>
                
                <a href="../api/fetch-trained.php" target="_blank" class="btn btn-secondary">
                    Export Trained Data (JSON)
                </a>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show/hide API token
        const showTokenBtn = document.getElementById('show-token');
        const apiTokenInput = document.getElementById('api_token');
        
        if (showTokenBtn && apiTokenInput) {
            showTokenBtn.addEventListener('click', function() {
                if (apiTokenInput.type === 'text') {
                    apiTokenInput.type = 'password';
                    showTokenBtn.textContent = 'Show';
                } else {
                    apiTokenInput.type = 'text';
                    showTokenBtn.textContent = 'Hide';
                }
            });
        }
        
        // Expand/collapse prompt editor
        const expandEditorBtn = document.getElementById('expand-editor');
        const promptEditor = document.getElementById('prompt_template');
        
        if (expandEditorBtn && promptEditor) {
            expandEditorBtn.addEventListener('click', function() {
                if (promptEditor.rows === 12) {
                    promptEditor.rows = 25;
                    expandEditorBtn.textContent = 'Collapse Editor';
                } else {
                    promptEditor.rows = 12;
                    expandEditorBtn.textContent = 'Expand Editor';
                }
            });
        }
    });
    </script>
</body>
</html>