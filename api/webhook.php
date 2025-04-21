<?php
/**
 * Webhook API Endpoint - REVISI FINAL
 * 
 * Menerima request dari chatbot.com dan menanggapinya
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Enable CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Log semua request masuk untuk debugging
$requestInfo = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input')
];
file_put_contents(
    LUNA_ROOT . '/webhook_debug.log',
    date('Y-m-d H:i:s') . ' - ' . json_encode($requestInfo, JSON_PRETTY_PRINT) . "\n\n",
    FILE_APPEND
);

// PENTING: Menangani challenge verification dari chatbot.com
if (isset($_GET['challenge']) || isset($_REQUEST['challenge'])) {
    $challenge = $_GET['challenge'] ?? $_REQUEST['challenge'] ?? '';
    echo $challenge;
    exit;
}

// Handle OPTIONS request (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/functions.php';
require_once LUNA_ROOT . '/inc/gpt_service.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    // Log the received data untuk debugging
    error_log("Received webhook data: " . json_encode($data));
    
    // Look for question in berbagai format yang bisa datang dari chatbot.com
    $question = null;
    if (isset($data['question'])) {
        $question = trim($data['question']);
    } elseif (isset($data['message'])) {
        $question = trim($data['message']);
    } elseif (isset($data['text'])) {
        $question = trim($data['text']);
    } elseif (isset($data['responses']) && is_array($data['responses'])) {
        // Extract input from responses array
        foreach ($data['responses'] as $response) {
            if (isset($response['type']) && $response['type'] === 'INPUT_MESSAGE' && isset($response['value'])) {
                $question = trim($response['value']);
                break;
            }
        }
    }
    
    // Basic validation
    if (!$question) {
        // Periksa format yang lebih kompleks dari chatbot.com
        if (isset($data['userId']) && isset($data['externalId']) && isset($data['messageId'])) {
            // Format dari chatbot.com yang terdeteksi dari log
            if (isset($data['message']) && is_string($data['message'])) {
                $question = trim($data['message']);
            } else {
                http_response_code(400);
                echo json_encode([
                    'responses' => [
                        [
                            'type' => 'TEXT',
                            'message' => 'Maaf, saya tidak dapat memahami pertanyaan Anda.'
                        ]
                    ]
                ]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'responses' => [
                    [
                        'type' => 'TEXT',
                        'message' => 'Pertanyaan tidak ditemukan dalam request'
                    ]
                ]
            ]);
            exit;
        }
    }
    
    // Dapatkan fallback response dari settings untuk jaga-jaga
    $fallbackResponse = getSetting('fallback_response', 'Maaf, saya tidak dapat memproses permintaan Anda saat ini. Silakan coba lagi nanti.');
    
    // Try to find answer in database
    try {
        $db = db()->getConnection();
        
        // Search for exact match
        $exactSql = "
            SELECT id, question, answer, confidence_level 
            FROM prompt_data 
            WHERE LOWER(question) = LOWER(?) AND status = 'active'
            LIMIT 1
        ";
        $exactStmt = $db->prepare($exactSql);
        $exactStmt->execute([trim($question)]);
        $databaseAnswer = $exactStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($databaseAnswer) {
            // Log the response
            $sql = "
                INSERT INTO response_logs 
                (user_message, ai_response, source, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $question,
                $databaseAnswer['answer'],
                'manual',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Return jawaban dari database dengan format yang sesuai chatbot.com
            echo json_encode([
                'responses' => [
                    [
                        'type' => 'TEXT',
                        'message' => $databaseAnswer['answer']
                    ]
                ]
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        // Continue to GPT if database lookup fails
    }
    
    // No database answer found, call GPT service
    try {
        // Get response from GPT
        $gptResponse = gptService()->getResponse($question);
        
        // PENTING: Periksa jika response dari GPT berhasil atau tidak
        $aiResponseText = isset($gptResponse['ai_response']) && !empty($gptResponse['ai_response']) 
            ? $gptResponse['ai_response'] 
            : $fallbackResponse;
        
        // Log the response
        $sql = "
            INSERT INTO response_logs 
            (user_message, ai_response, source, score, feedback, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $question,
            $aiResponseText,
            $gptResponse['source'] ?? 'gpt',
            $gptResponse['score'] ?? null,
            $gptResponse['feedback'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Return response in the format chatbot.com expects
        echo json_encode([
            'responses' => [
                [
                    'type' => 'TEXT',
                    'message' => $aiResponseText
                ]
            ]
        ]);
    } catch (Exception $e) {
        error_log("GPT service error: " . $e->getMessage());
        
        // Return fallback response in the correct format
        echo json_encode([
            'responses' => [
                [
                    'type' => 'TEXT',
                    'message' => $fallbackResponse
                ]
            ]
        ]);
    }
    exit;
}

// Handle GET requests - Return minimal information
echo json_encode([
    'status' => 'ok',
    'message' => 'Luna webhook siap menerima requests'
]);
?>