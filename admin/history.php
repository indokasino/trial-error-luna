<?php
/**
 * Admin Interaction History Page
 * 
 * View and filter chatbot interaction logs
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Require login
requireLogin();

// Initialize database
$db = db()->getConnection();

// Handle filters
$source = $_GET['source'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$minScore = isset($_GET['min_score']) && $_GET['min_score'] !== '' ? floatval($_GET['min_score']) : null;

// Build SQL query with filters
$sql = "SELECT * FROM response_logs WHERE 1=1";
$params = [];

if ($source !== 'all') {
    $sql .= " AND source = ?";
    $params[] = $source;
}

if (!empty($dateFrom)) {
    $sql .= " AND created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sql .= " AND created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($search)) {
    $sql .= " AND (user_message LIKE ? OR ai_response LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($minScore !== null) {
    $sql .= " AND score >= ?";
    $params[] = $minScore;
}

$sql .= " ORDER BY created_at DESC";

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM response_logs WHERE 1=1";
$countParams = $params;

// Pagination
$recordsPerPage = 20;
$totalRecords = db()->count($countSql, $countParams);
$totalPages = ceil($totalRecords / $recordsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Add limit to SQL
$sql .= " LIMIT $offset, $recordsPerPage";

// Execute query
$logs = db()->fetchAll($sql, $params);

// Generate CSRF token
$csrfToken = auth()->generateCsrfToken();

// Get the current base URL
$baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl .= "://{$_SERVER['HTTP_HOST']}";
$baseDir = dirname(dirname($_SERVER['PHP_SELF']));
if ($baseDir == '\\' || $baseDir == '/') $baseDir = '';
$baseUrl .= $baseDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Interaction History</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/admin.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Interaction History</h1>
        </div>
        
        <div class="filter-bar">
            <form action="history.php" method="GET" class="filter-form">
                <div class="filter-form-row">
                    <div class="filter-group col-md-3">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search in messages" 
                               value="<?php echo sanitize($search); ?>">
                    </div>
                    
                    <div class="filter-group col-md-2">
                        <label for="source">Source</label>
                        <select id="source" name="source">
                            <option value="all" <?php echo $source === 'all' ? 'selected' : ''; ?>>All Sources</option>
                            <option value="manual" <?php echo $source === 'manual' ? 'selected' : ''; ?>>Database</option>
                            <option value="gpt" <?php echo $source === 'gpt' ? 'selected' : ''; ?>>GPT</option>
                        </select>
                    </div>
                    
                    <div class="filter-group col-md-2">
                        <label for="min_score">Min Score</label>
                        <input type="number" id="min_score" name="min_score" step="0.1" min="0" max="10" 
                               value="<?php echo $minScore !== null ? sanitize($minScore) : ''; ?>">
                    </div>
                    
                    <div class="filter-group col-md-2">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    
                    <div class="filter-group col-md-2">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    
                    <div class="filter-group col-md-1 filter-btn-wrapper">
                        <button type="submit" class="btn btn-secondary btn-block">Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="data-summary">
            <p>Showing <?php echo count($logs); ?> of <?php echo $totalRecords; ?> interactions</p>
        </div>
        
        <?php if (count($logs) > 0): ?>
        <div class="log-entries">
            <?php foreach ($logs as $log): ?>
            <div class="log-entry">
                <div class="log-header">
                    <div class="meta-info">
                        <span class="datetime"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></span>
                        <span class="source-badge source-<?php echo $log['source']; ?>">
                            <?php echo $log['source'] === 'manual' ? 'Database' : 'GPT'; ?>
                        </span>
                        <?php if (isset($log['score']) && $log['score']): ?>
                        <span class="score-badge">
                            Score: <strong><?php echo number_format($log['score'], 1); ?></strong>
                        </span>
                        <?php endif; ?>
                        <span class="ip-address">IP: <?php echo sanitize($log['ip_address']); ?></span>
                    </div>
                    <div class="controls">
                        <?php if ($log['source'] === 'gpt' && (!isset($log['trained']) || !$log['trained'])): ?>
                        <a href="review.php?log_id=<?php echo $log['id']; ?>" class="btn btn-sm btn-secondary">Review</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="log-body">
                    <div class="message user-message">
                        <div class="message-header">
                            <span>User Question:</span>
                        </div>
                        <div class="message-content">
                            <?php echo sanitize($log['user_message']); ?>
                        </div>
                    </div>
                    
                    <div class="message ai-message">
                        <div class="message-header">
                            <span>AI Response:</span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(sanitize($log['ai_response'])); ?>
                        </div>
                    </div>
                    
                    <?php if (isset($log['feedback']) && $log['feedback']): ?>
                    <div class="feedback-section">
                        <div class="feedback-header">Feedback:</div>
                        <div class="feedback-content">
                            <?php echo sanitize($log['feedback']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <?php
            // Build URL for pagination links
            $paginationUrl = 'history.php?';
            if ($source !== 'all') $paginationUrl .= "source=$source&";
            if (!empty($dateFrom)) $paginationUrl .= "date_from=" . urlencode($dateFrom) . "&";
            if (!empty($dateTo)) $paginationUrl .= "date_to=" . urlencode($dateTo) . "&";
            if (!empty($search)) $paginationUrl .= "search=" . urlencode($search) . "&";
            if ($minScore !== null) $paginationUrl .= "min_score=" . $minScore . "&";
            $paginationUrl .= "page=%d";
            
            echo getPagination($currentPage, $totalPages, $paginationUrl);
            ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-records">
            <p>No interaction logs found matching your criteria.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>