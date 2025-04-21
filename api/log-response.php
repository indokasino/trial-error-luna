<?php
/**
 * Log Response API Endpoint
 * 
 * Receives GPT responses from Laravel backend and logs them
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', [], 405);
}

// Validate API token
if (!hasValidApiToken()) {
    jsonResponse(false, 'Unauthorized', [], 401);
}

// Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Validate input
if (!$data || !isset($data['user_message']) || !isset($data['ai_response'])) {
    jsonResponse(false, 'Invalid request. Required fields missing.', [], 400);
}

$userMessage = trim($data['user_message']);
$aiResponse = trim($data['ai_response']);
$source = isset($data['source']) ? trim($data['source']) : 'gpt';
$score = isset($data['score']) ? floatval($data['score']) : null;
$feedback = isset($data['feedback']) ? trim($data['feedback']) : null;

// Log the response
$logId = logResponse($userMessage, $aiResponse, $source, $score, $feedback);

if ($logId) {
    jsonResponse(true, 'Response logged successfully', [
        'log_id' => $logId
    ]);
} else {
    jsonResponse(false, 'Failed to log response', [], 500);
}