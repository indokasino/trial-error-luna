<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle OPTIONS request (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log all requests regardless of method
$now = date('Y-m-d H:i:s');
$requestInfo = [
    'time' => $now,
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input')
];
file_put_contents(
    dirname(__DIR__) . '/webhook_debug.log',
    $now . ' - ' . json_encode($requestInfo, JSON_PRETTY_PRINT) . "\n\n",
    FILE_APPEND
);

// Just return a simple response for any request
echo json_encode([
    'text' => "Ini adalah respons test dari open_webhook.php. Chatbot anda berhasil terhubung ke server."
]);