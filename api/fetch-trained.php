<?php
/**
 * Fetch Trained Data API Endpoint
 * 
 * Exports JSON of all trained Q&A (is_trained=1)
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed', [], 405);
}

// Validate API token
if (!hasValidApiToken()) {
    jsonResponse(false, 'Unauthorized', [], 401);
}

try {
    // Get all trained Q&A entries
    $db = db()->getConnection();
    $sql = "
        SELECT 
            id, 
            question, 
            answer, 
            tags, 
            confidence_level
        FROM 
            prompt_data 
        WHERE 
            is_trained = 1 
            AND status = 'active'
        ORDER BY 
            id ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for export
    $formattedData = [];
    foreach ($data as $item) {
        // Process tags if present
        $tags = [];
        if (!empty($item['tags'])) {
            $tags = explode(',', $item['tags']);
            $tags = array_map('trim', $tags);
        }
        
        $formattedData[] = [
            'id' => (int)$item['id'],
            'question' => $item['question'],
            'answer' => $item['answer'],
            'tags' => $tags,
            'confidence' => (float)$item['confidence_level']
        ];
    }
    
    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="trained_data_' . date('Y-m-d') . '.json"');
    
    // Output JSON
    echo json_encode([
        'success' => true,
        'count' => count($formattedData),
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $formattedData
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    exit;
} catch (PDOException $e) {
    error_log("Export trained data error: " . $e->getMessage());
    jsonResponse(false, 'Failed to export data', [], 500);
}