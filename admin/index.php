<?php
/**
 * Admin Index Page
 * 
 * Lists all Q&A entries with filtering and pagination
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
$status = $_GET['status'] ?? 'all';
$trained = isset($_GET['trained']) ? (int)$_GET['trained'] : -1;
$search = $_GET['search'] ?? '';
$tag = $_GET['tag'] ?? '';

// Build SQL query with filters
$sql = "SELECT p.*, COUNT(r.id) as response_count 
        FROM prompt_data p 
        LEFT JOIN response_logs r ON p.question = r.user_message 
        WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if ($trained !== -1) {
    $sql .= " AND p.is_trained = ?";
    $params[] = $trained;
}

if (!empty($search)) {
    $sql .= " AND (p.question LIKE ? OR p.answer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($tag)) {
    $sql .= " AND p.tags LIKE ?";
    $params[] = "%$tag%";
}

$sql .= " GROUP BY p.id ORDER BY p.id DESC";

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM prompt_data WHERE 1=1";
$countParams = [];

if ($status !== 'all') {
    $countSql .= " AND status = ?";
    $countParams[] = $status;
}

if ($trained !== -1) {
    $countSql .= " AND is_trained = ?";
    $countParams[] = $trained;
}

if (!empty($search)) {
    $countSql .= " AND (question LIKE ? OR answer LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}

if (!empty($tag)) {
    $countSql .= " AND tags LIKE ?";
    $countParams[] = "%$tag%";
}

$totalRecords = db()->count($countSql, $countParams);

// Pagination
$recordsPerPage = 20;
$totalPages = ceil($totalRecords / $recordsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Add limit to SQL
$sql .= " LIMIT $offset, $recordsPerPage";

// Execute query
$records = db()->fetchAll($sql, $params);

// Get all tags for filter dropdown
$tags = db()->fetchAll("SELECT DISTINCT tag_name FROM tags ORDER BY tag_name");

// Generate CSRF token for forms
$csrfToken = auth()->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Q&A Management</h1>
            <a href="edit.php" class="btn btn-primary">Add New Q&A</a>
        </div>
        
        <div class="filter-bar">
            <form action="index.php" method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search questions/answers" 
                           value="<?php echo sanitize($search); ?>">
                </div>
                
                <div class="form-group">
                    <select name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <select name="trained">
                        <option value="-1" <?php echo $trained === -1 ? 'selected' : ''; ?>>All Training</option>
                        <option value="1" <?php echo $trained === 1 ? 'selected' : ''; ?>>Trained</option>
                        <option value="0" <?php echo $trained === 0 ? 'selected' : ''; ?>>Untrained</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <select name="tag">
                        <option value="">All Tags</option>
                        <?php foreach ($tags as $t): ?>
                        <option value="<?php echo sanitize($t['tag_name']); ?>" 
                                <?php echo $tag === $t['tag_name'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($t['tag_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="index.php" class="btn btn-link">Reset</a>
                </div>
            </form>
        </div>
        
        <div class="data-summary">
            <p>Showing <?php echo count($records); ?> of <?php echo $totalRecords; ?> entries</p>
        </div>
        
        <?php if (count($records) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Status</th>
                        <th>Trained</th>
                        <th>Confidence</th>
                        <th>Usage</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo $record['id']; ?></td>
                        <td>
                            <a href="edit.php?id=<?php echo $record['id']; ?>" class="question-link">
                                <?php echo strlen($record['question']) > 80 ? 
                                    sanitize(substr($record['question'], 0, 80)) . '...' : 
                                    sanitize($record['question']); ?>
                            </a>
                            <?php if (!empty($record['tags'])): ?>
                            <div class="tags">
                                <?php 
                                $tagArray = explode(',', $record['tags']);
                                foreach ($tagArray as $t): 
                                ?>
                                <span class="tag"><?php echo sanitize(trim($t)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $record['status']; ?>">
                                <?php echo ucfirst($record['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="trained-badge <?php echo $record['is_trained'] ? 'trained' : 'untrained'; ?>">
                                <?php echo $record['is_trained'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td><?php echo number_format($record['confidence_level'], 2); ?></td>
                        <td><?php echo $record['response_count']; ?></td>
                        <td class="actions">
                            <a href="edit.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            
                            <form method="POST" action="delete.php" class="inline-form" 
                                  onsubmit="return confirm('Are you sure you want to delete this Q&A?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <?php
            // Build URL for pagination links
            $paginationUrl = 'index.php?';
            if (!empty($status) && $status !== 'all') $paginationUrl .= "status=$status&";
            if ($trained !== -1) $paginationUrl .= "trained=$trained&";
            if (!empty($search)) $paginationUrl .= "search=" . urlencode($search) . "&";
            if (!empty($tag)) $paginationUrl .= "tag=" . urlencode($tag) . "&";
            $paginationUrl .= "page=%d";
            
            echo getPagination($currentPage, $totalPages, $paginationUrl);
            ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-records">
            <p>No records found matching your criteria.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="../assets/js/validation.js"></script>
</body>
</html>