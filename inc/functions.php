<?php
/**
 * Utility Functions
 * 
 * Common helper functions for Luna Chatbot system
 */

// Prevent direct access
if (!defined('LUNA_ROOT')) {
    die('Access denied');
}

// Require dependencies
require_once LUNA_ROOT . '/inc/db.php';

/**
 * Sanitize input to prevent XSS
 * 
 * @param string $input Input string to be sanitized
 * @return string Sanitized string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Check and enforce rate limiting
 * 
 * @param string $ip IP address to check
 * @param int $limit Maximum number of requests within time window
 * @param int $window Time window in seconds
 * @return bool True if limit is reached, false otherwise
 */
function checkRateLimit($ip, $limit = 10, $window = 60) {
    $db = db()->getConnection();
    
    try {
        // Clean up old entries first
        $cleanupSql = "DELETE FROM rate_limiter WHERE last_hit < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $cleanupStmt = $db->prepare($cleanupSql);
        $cleanupStmt->execute([$window]);
        
        // Check if IP exists in current window
        $checkSql = "SELECT id, hit_count FROM rate_limiter WHERE ip_address = ? AND last_hit > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$ip, $window]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // IP exists, update hit count
            $newCount = $result['hit_count'] + 1;
            $updateSql = "UPDATE rate_limiter SET hit_count = ?, last_hit = NOW() WHERE id = ?";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([$newCount, $result['id']]);
            
            // Check if limit is reached
            return ($newCount > $limit);
        } else {
            // IP doesn't exist, insert new record
            $insertSql = "INSERT INTO rate_limiter (ip_address, hit_count, last_hit) VALUES (?, 1, NOW())";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([$ip]);
            
            // First hit, limit not reached
            return false;
        }
    } catch (PDOException $e) {
        error_log("Rate limiter error: " . $e->getMessage());
        // On error, don't block the request
        return false;
    }
}

/**
 * Check for badwords in text
 * 
 * @param string $text Text to check for bad words
 * @return bool True if badwords found, false otherwise
 */
function containsBadwords($text) {
    $db = db()->getConnection();
    
    try {
        // Get all badwords from the database
        $stmt = $db->prepare("SELECT word FROM badwords");
        $stmt->execute();
        $badwords = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Convert text to lowercase for case-insensitive matching
        $lowerText = strtolower($text);
        
        // Check each badword
        foreach ($badwords as $word) {
            if (stripos($lowerText, strtolower($word)) !== false) {
                return true;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Badword check error: " . $e->getMessage());
        // On error, assume no badwords
        return false;
    }
}

/**
 * Get application setting from the database
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null) {
    $db = db()->getConnection();
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['value'] : $default;
    } catch (PDOException $e) {
        error_log("Get setting error: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update application setting
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool True on success, false on failure
 */
function updateSetting($key, $value) {
    $db = db()->getConnection();
    
    try {
        // Check if setting exists
        $checkStmt = $db->prepare("SELECT id FROM settings WHERE key = ? LIMIT 1");
        $checkStmt->execute([$key]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing setting
            $updateStmt = $db->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $updateStmt->execute([$value, $key]);
        } else {
            // Insert new setting
            $insertStmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
            $insertStmt->execute([$key, $value]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Update setting error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate JSON response
 * 
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Additional data
 * @param int $statusCode HTTP status code
 */
function jsonResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    
    exit;
}

/**
 * Call GPT API via Laravel webhook
 * 
 * @param string $question User's question
 * @return array Response from GPT API
 */
function callGptApi($question) {
    $webhookUrl = getSetting('laravel_webhook_url', '');
    $apiToken = getSetting('api_token', '');
    
    if (empty($webhookUrl) || empty($apiToken)) {
        return [
            'success' => false,
            'message' => 'API configuration missing',
            'response' => 'Sorry, the system is not properly configured to handle your question.'
        ];
    }
    
    // Prepare request data
    $data = json_encode([
        'question' => $question
    ]);
    
    // Set cURL options
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 seconds timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken
    ]);
    
    // Execute request with retry logic
    $retries = 0;
    $maxRetries = 3;
    $response = null;
    
    while ($retries < $maxRetries) {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // Success
        if ($httpCode >= 200 && $httpCode < 300 && !$error) {
            break;
        }
        
        // Wait before retry (exponential backoff)
        $waitTime = pow(2, $retries) * 500000; // in microseconds
        usleep($waitTime);
        $retries++;
    }
    
    curl_close($ch);
    
    // Check for errors
    if ($retries >= $maxRetries || !$response) {
        error_log("GPT API error: " . ($error ?: "Unknown error") . " | HTTP Code: " . $httpCode);
        
        // Get fallback response
        $fallbackResponse = getSetting('fallback_response', 'Sorry, I could not process your request at this time. Please try again later.');
        
        return [
            'success' => false,
            'message' => 'API request failed after ' . $retries . ' attempts',
            'response' => $fallbackResponse
        ];
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($responseData['ai_response'])) {
        return [
            'success' => false,
            'message' => 'Invalid API response',
            'response' => 'Sorry, I received an invalid response. Please try again later.'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'API request successful',
        'response' => $responseData['ai_response'],
        'score' => $responseData['score'] ?? null,
        'feedback' => $responseData['feedback'] ?? null
    ];
}

/**
 * Log AI response
 * 
 * @param string $userMessage User message
 * @param string $aiResponse AI response
 * @param string $source Source of response (manual/gpt)
 * @param float $score Score (optional)
 * @param string $feedback Feedback (optional)
 * @return int|bool ID of inserted log or false on failure
 */
function logResponse($userMessage, $aiResponse, $source = 'gpt', $score = null, $feedback = null) {
    $db = db()->getConnection();
    
    try {
        $sql = "
            INSERT INTO response_logs 
            (user_message, ai_response, source, score, feedback, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            $userMessage,
            $aiResponse,
            $source,
            $score,
            $feedback,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        return db()->insert($sql, $params);
    } catch (PDOException $e) {
        error_log("Log response error: " . $e->getMessage());
        return false;
    }
}

/**
 * Find answer in the database
 * 
 * @param string $question User's question
 * @return array|false Answer data or false if not found
 */
function findAnswer($question) {
    $db = db()->getConnection();
    
    try {
        // First, search for exact match
        $exactSql = "
            SELECT id, question, answer, confidence_level 
            FROM prompt_data 
            WHERE LOWER(question) = LOWER(?) AND status = 'active'
            LIMIT 1
        ";
        $exactStmt = $db->prepare($exactSql);
        $exactStmt->execute([trim($question)]);
        $exactMatch = $exactStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exactMatch) {
            return $exactMatch;
        }
        
        // No exact match, try keyword matching
        // This is a simple approach - in production you might want to use FULLTEXT search or a more sophisticated algorithm
        $keywords = preg_split('/\s+/', trim($question));
        $keywords = array_filter($keywords, function($word) {
            return strlen($word) > 3; // Ignore short words
        });
        
        if (empty($keywords)) {
            return false;
        }
        
        // Build query with keyword conditions
        $whereClauses = [];
        $params = [];
        
        foreach ($keywords as $keyword) {
            $whereClauses[] = "LOWER(question) LIKE ?";
            $params[] = "%" . strtolower($keyword) . "%";
        }
        
        $keywordSql = "
            SELECT id, question, answer, confidence_level,
                   (LENGTH(question) - LENGTH(REPLACE(LOWER(question), LOWER(?), ''))) / LENGTH(?) AS match_score
            FROM prompt_data 
            WHERE (" . implode(" OR ", $whereClauses) . ") AND status = 'active'
            ORDER BY match_score DESC
            LIMIT 1
        ";
        
        // Add the full question at the beginning of params for match_score calculation
        array_unshift($params, $question, $question);
        
        $keywordStmt = $db->prepare($keywordSql);
        $keywordStmt->execute($params);
        $keywordMatch = $keywordStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($keywordMatch && $keywordMatch['match_score'] > 0.5) { // Threshold for relevance
            return $keywordMatch;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Find answer error: " . $e->getMessage());
        return false;
    }
}

/**
 * Format pagination
 *
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern with %d placeholder for page number
 * @return string HTML pagination
 */
function getPagination($currentPage, $totalPages, $urlPattern) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $output = '<div class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $output .= '<a href="' . sprintf($urlPattern, $currentPage - 1) . '" class="page-link">&laquo; Previous</a>';
    } else {
        $output .= '<span class="page-link disabled">&laquo; Previous</span>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $output .= '<a href="' . sprintf($urlPattern, 1) . '" class="page-link">1</a>';
        if ($startPage > 2) {
            $output .= '<span class="page-link">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $output .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $output .= '<a href="' . sprintf($urlPattern, $i) . '" class="page-link">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $output .= '<span class="page-link">...</span>';
        }
        $output .= '<a href="' . sprintf($urlPattern, $totalPages) . '" class="page-link">' . $totalPages . '</a>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $output .= '<a href="' . sprintf($urlPattern, $currentPage + 1) . '" class="page-link">Next &raquo;</a>';
    } else {
        $output .= '<span class="page-link disabled">Next &raquo;</span>';
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Clean up old logs
 * 
 * @param int $days Number of days to keep logs
 * @return int Number of deleted logs
 */
function cleanupOldLogs($days = 90) {
    $db = db()->getConnection();
    
    try {
        $sql = "DELETE FROM response_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Cleanup logs error: " . $e->getMessage());
        return 0;
    }
}