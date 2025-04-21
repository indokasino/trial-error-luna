
<?php
/**
 * Direct Webhook - Bypasses getSetting() function
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

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get API token directly from database
function getApiToken() {
    require_once LUNA_ROOT . '/inc/db.php';
    
    try {
        $db = db()->getConnection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'api_token' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['value'] : null;
    } catch (Exception $e) {
        error_log("Error getting API token: " . $e->getMessage());
        return null;
    }
}

// Simple authorization check
function checkAuthorization() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return false;
    }
    
    $token = $matches[1];
    $storedToken = getApiToken();
    
    return $storedToken && $token === $storedToken;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $apiToken = getApiToken();
    echo json_encode([
        'success' => true,
        'message' => 'Direct webhook is working',
        'token_status' => $apiToken ? 'Token found in database' : 'No token in database',
        'token_value' => $apiToken ? substr($apiToken, 0, 4) . '...' . substr($apiToken, -4) : 'none'
    ]);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check authorization
    if (!checkAuthorization()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized. Invalid or missing API token.',
            'api_token_found' => getApiToken() ? true : false
        ]);
        exit;
    }
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    // Basic validation
    if (!$data || !isset($data['question']) || empty(trim($data['question']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request. Question is required.'
        ]);
        exit;
    }
    
    $question = trim($data['question']);
    
    // Simple response
    echo json_encode([
        'success' => true,
        'message' => 'Response generated successfully',
        'data' => [
            'question' => $question,
            'answer' => "This is a direct webhook response to: \"$question\"",
            'source' => 'direct_webhook'
        ]
    ]);
    exit;
}

// Handle other methods
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);
