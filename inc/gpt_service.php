<?php
/**
 * GPT Service Class - REVISI FINAL
 * 
 * Handles direct communication with OpenAI API from PHP
 */

// Prevent direct access
if (!defined('LUNA_ROOT')) {
    die('Access denied');
}

class GptService {
    private static $instance = null;
    private $db;
    private $logFile;
    
    // Singleton pattern
    private function __construct() {
        $this->db = db()->getConnection();
        $this->logFile = LUNA_ROOT . '/logs/openai_calls.log';
        
        // Ensure logs directory exists
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    // Get instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Log messages
    private function logDebug($message) {
        file_put_contents(
            $this->logFile, 
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 
            FILE_APPEND
        );
        
        // Also write to error_log for easier viewing
        error_log('LUNA-OPENAI: ' . $message);
    }
    
    /**
     * Call OpenAI API with the given question
     * 
     * @param string $question User's question
     * @return array Response data
     */
    public function getResponse($question) {
        // Create a detailed log file
        $this->logDebug("Starting API call for: " . substr($question, 0, 50));
        
        try {
            // Get API settings
            $apiKey = getSetting('openai_key', '');
            $this->logDebug("API Key (first 4 chars): " . substr($apiKey, 0, 4));
            
            $primaryModel = getSetting('gpt_model', 'gpt-3.5-turbo');
            $this->logDebug("Primary Model: " . $primaryModel);
            
            // Map custom model names to standard OpenAI models if needed
            $modelMap = [
                'gpt-4.1' => 'gpt-4-turbo',
                'o4-mini' => 'gpt-4'
            ];
            
            // Use mapped model if available
            $actualModel = isset($modelMap[$primaryModel]) ? $modelMap[$primaryModel] : $primaryModel;
            if ($actualModel !== $primaryModel) {
                $this->logDebug("Mapped model '$primaryModel' to standard model '$actualModel'");
            }
            
            $fallbackModel = getSetting('fallback_model', 'gpt-3.5-turbo');
            // PENTING: Pastikan fallback response selalu diperoleh dari settings
            $fallbackResponse = getSetting('fallback_response', 'Maaf, saya tidak dapat memproses permintaan Anda saat ini. Silakan coba lagi nanti.');
            $maxRetries = (int)getSetting('max_retries', 3);
            
            // Validate API key
            if (empty($apiKey) || $apiKey === 'sk-your-openai-key' || $apiKey === 'placeholder_key') {
                $this->logDebug("ERROR: Invalid API key");
                throw new Exception('OpenAI API key is not properly configured. Please update it in settings.');
            }
            
            // Load system prompt from file if exists
            $systemPrompt = 'You are a helpful assistant that provides accurate, concise, and informative answers.';
            $promptFilePath = LUNA_ROOT . '/prompt-luna.txt';
            
            if (file_exists($promptFilePath) && is_readable($promptFilePath)) {
                $fileContent = file_get_contents($promptFilePath);
                if ($fileContent !== false && !empty(trim($fileContent))) {
                    $systemPrompt = trim($fileContent);
                    $this->logDebug("Loaded system prompt from file");
                }
            }
            
            $this->logDebug("System prompt length: " . strlen($systemPrompt) . " chars");
            
            // Try to get response with retries
            $retryCount = 0;
            $lastError = null;
            
            while ($retryCount < $maxRetries) {
                try {
                    $this->logDebug("Attempt #" . ($retryCount + 1));
                    $answer = $this->directOpenAICall($question, $actualModel, $apiKey, $systemPrompt);
                    
                    if ($answer) {
                        $this->logDebug("✅ Successful response");
                        
                        return [
                            'success' => true,
                            'user_message' => $question,
                            'ai_response' => $answer,
                            'source' => 'gpt',
                            'score' => 8.5,
                            'feedback' => 'Good response'
                        ];
                    } else {
                        $this->logDebug("❌ Empty response");
                        throw new Exception('Empty response from API');
                    }
                } catch (Exception $e) {
                    $lastError = $e;
                    $retryCount++;
                    $this->logDebug("Error on attempt $retryCount: " . $e->getMessage());
                    
                    // Wait before retry
                    if ($retryCount < $maxRetries) {
                        $sleepTime = pow(2, $retryCount - 1) * 1000000; // Eksponential backoff in microseconds
                        usleep($sleepTime);
                    }
                }
            }
            
            // All attempts failed with primary model
            $this->logDebug("All attempts failed with primary model. Trying fallback model.");
            
            // Try fallback model if available
            if (!empty($fallbackModel) && $fallbackModel !== 'none' && $fallbackModel !== $primaryModel) {
                $actualFallbackModel = isset($modelMap[$fallbackModel]) ? $modelMap[$fallbackModel] : $fallbackModel;
                
                $this->logDebug("Using fallback model: " . $actualFallbackModel);
                
                try {
                    $answer = $this->directOpenAICall($question, $actualFallbackModel, $apiKey, $systemPrompt);
                    
                    if ($answer) {
                        $this->logDebug("✅ Successful response with fallback model");
                        
                        return [
                            'success' => true,
                            'user_message' => $question,
                            'ai_response' => $answer,
                            'source' => 'gpt-fallback',
                            'score' => 7.0, // Lower score for fallback
                            'feedback' => 'Fallback response'
                        ];
                    }
                } catch (Exception $e) {
                    $this->logDebug("❌ Fallback model also failed: " . $e->getMessage());
                    // Continue to return fallback response
                }
            }
            
            // Jika semua upaya gagal, gunakan fallbackResponse dari settings
            $this->logDebug("Returning fallback response: " . substr($fallbackResponse, 0, 50) . "...");
            
            return [
                'success' => false,
                'user_message' => $question,
                'ai_response' => $fallbackResponse, // Menggunakan fallbackResponse dari settings
                'source' => 'gpt-error',
                'score' => 0,
                'feedback' => 'API Error: ' . ($lastError ? $lastError->getMessage() : 'Unknown error')
            ];
            
        } catch (Exception $e) {
            $this->logDebug("❌ Error: " . $e->getMessage());
            
            // Get fallback response
            $fallbackResponse = getSetting('fallback_response', 'Maaf, saya tidak dapat memproses permintaan Anda saat ini. Silakan coba lagi nanti.');
            
            return [
                'success' => false,
                'user_message' => $question,
                'ai_response' => $fallbackResponse,
                'source' => 'gpt-error',
                'score' => 0,
                'feedback' => 'API Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Direct call to OpenAI API
     */
    private function directOpenAICall($question, $model, $apiKey, $systemPrompt) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        // Prepare request data
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $question
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500,
        ];
        
        $jsonData = json_encode($data);
        $this->logDebug("Request data: " . substr($jsonData, 0, 200) . "...");
        
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
        
        // Execute cURL request
        $this->logDebug("Sending request to OpenAI...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // Get verbose information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        
        $this->logDebug("HTTP Code: " . $httpCode);
        
        // Close cURL
        curl_close($ch);
        
        // Handle CURL errors
        if ($error) {
            $this->logDebug("CURL Error: " . $error);
            throw new Exception('Connection error: ' . $error);
        }
        
        // Handle HTTP errors
        if ($httpCode >= 400) {
            $this->logDebug("API Error Response: " . $response);
            throw new Exception('API error (HTTP ' . $httpCode . '): ' . $response);
        }
        
        // Log response for debugging
        $this->logDebug("API Response: " . substr($response, 0, 200) . "...");
        
        // Decode response
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logDebug("JSON decode error: " . json_last_error_msg());
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        // Extract answer
        if (isset($responseData['choices'][0]['message']['content'])) {
            $answer = $responseData['choices'][0]['message']['content'];
            return $answer;
        } else {
            $this->logDebug("No valid content in response");
            throw new Exception('No valid content in API response');
        }
    }
}

// Helper function to get GPT service instance
function gptService() {
    return GptService::getInstance();
}