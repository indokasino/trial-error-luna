<?php
/**
 * Public Frontend Index Page
 * 
 * Displays all active Q&A entries with pagination
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Initialize database
$db = db()->getConnection();

// Get tag filter if provided
$tagFilter = isset($_GET['tag']) ? trim($_GET['tag']) : '';

// Build SQL query
$sql = "SELECT * FROM prompt_data WHERE status = 'active'";
$params = [];

if (!empty($tagFilter)) {
    $sql .= " AND tags LIKE ?";
    $params[] = "%$tagFilter%";
}

$sql .= " ORDER BY id DESC";

// Count total records for pagination
$totalRecords = db()->count("SELECT COUNT(*) FROM prompt_data WHERE status = 'active'" . 
                           (!empty($tagFilter) ? " AND tags LIKE ?" : ""), 
                           (!empty($tagFilter) ? ["%$tagFilter%"] : []));

// Pagination
$recordsPerPage = 30;
$totalPages = ceil($totalRecords / $recordsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Add limit to SQL
$sql .= " LIMIT $offset, $recordsPerPage";

// Execute query
$qaEntries = db()->fetchAll($sql, $params);

// Get all unique tags for tag cloud
$allTags = [];
$tagsSql = "
    SELECT DISTINCT tags 
    FROM prompt_data 
    WHERE status = 'active' AND tags IS NOT NULL AND tags != ''
";
$tagsResult = db()->fetchAll($tagsSql);

foreach ($tagsResult as $tagRow) {
    $tagsList = explode(',', $tagRow['tags']);
    foreach ($tagsList as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            if (!isset($allTags[$tag])) {
                $allTags[$tag] = 1;
            } else {
                $allTags[$tag]++;
            }
        }
    }
}

// Sort tags by frequency
arsort($allTags);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - Knowledge Base</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <a href="index.php">Luna Chatbot</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Knowledge Base</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h1>Knowledge Base</h1>
            <?php if (!empty($tagFilter)): ?>
            <div class="current-filter">
                <span>Filtering by tag: </span>
                <span class="tag-badge"><?php echo sanitize($tagFilter); ?></span>
                <a href="index.php" class="clear-filter">Clear Filter</a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($allTags)): ?>
        <div class="tag-cloud">
            <h2>Popular Topics</h2>
            <div class="tags">
                <?php foreach (array_slice($allTags, 0, 20) as $tag => $count): ?>
                <a href="index.php?tag=<?php echo urlencode($tag); ?>" class="tag <?php echo $tag === $tagFilter ? 'active' : ''; ?>">
                    <?php echo sanitize($tag); ?> <span class="count">(<?php echo $count; ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="qa-listing">
            <?php if (count($qaEntries) > 0): ?>
            <?php foreach ($qaEntries as $qa): ?>
            <div class="qa-item">
                <h2>
                    <a href="qna.php?id=<?php echo $qa['id']; ?>">
                        <?php echo sanitize($qa['question']); ?>
                    </a>
                </h2>
                
                <div class="qa-preview">
                    <?php 
                    $previewText = strip_tags($qa['answer']);
                    $previewText = strlen($previewText) > 200 ? substr($previewText, 0, 200) . '...' : $previewText;
                    echo sanitize($previewText); 
                    ?>
                </div>
                
                <?php if (!empty($qa['tags'])): ?>
                <div class="qa-tags">
                    <?php 
                    $tagsList = explode(',', $qa['tags']);
                    foreach ($tagsList as $tag): 
                        $tag = trim($tag);
                        if (!empty($tag)):
                    ?>
                    <a href="index.php?tag=<?php echo urlencode($tag); ?>" class="tag-badge">
                        <?php echo sanitize($tag); ?>
                    </a>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <?php
                // Build URL for pagination links
                $paginationUrl = 'index.php?';
                if (!empty($tagFilter)) $paginationUrl .= "tag=" . urlencode($tagFilter) . "&";
                $paginationUrl .= "page=%d";
                
                echo getPagination($currentPage, $totalPages, $paginationUrl);
                ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-results">
                <p>No Q&A entries found<?php echo !empty($tagFilter) ? ' with the tag "' . sanitize($tagFilter) . '"' : ''; ?>.</p>
                <?php if (!empty($tagFilter)): ?>
                <p><a href="index.php">View all entries</a></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Luna Chatbot. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>