<?php
/**
 * Public Frontend Q&A Detail Page
 * 
 * Displays individual Q&A entry
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Initialize database
$db = db()->getConnection();

// Get Q&A ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get Q&A entry
$qa = db()->fetchOne("SELECT * FROM prompt_data WHERE id = ? AND status = 'active'", [$id]);

if (!$qa) {
    header('Location: index.php');
    exit;
}

// Get related Q&A entries based on tags
$relatedQA = [];

if (!empty($qa['tags'])) {
    $tagsList = explode(',', $qa['tags']);
    $placeholders = [];
    $params = [];
    
    foreach ($tagsList as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            $placeholders[] = "tags LIKE ?";
            $params[] = "%$tag%";
        }
    }
    
    if (!empty($placeholders)) {
        $params[] = $id; // Exclude current Q&A
        
        $sql = "
            SELECT id, question
            FROM prompt_data
            WHERE (" . implode(' OR ', $placeholders) . ")
            AND id != ?
            AND status = 'active'
            ORDER BY id DESC
            LIMIT 5
        ";
        
        $relatedQA = db()->fetchAll($sql, $params);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($qa['question']); ?> - Luna Chatbot</title>
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
                    <li><a href="index.php">Knowledge Base</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Knowledge Base</a> &gt; 
            <span>Q&A Detail</span>
        </div>
        
        <article class="qa-detail">
            <header class="qa-header">
                <h1><?php echo sanitize($qa['question']); ?></h1>
                
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
            </header>
            
            <div class="qa-content">
                <?php echo nl2br(sanitize($qa['answer'])); ?>
            </div>
        </article>
        
        <?php if (!empty($relatedQA)): ?>
        <div class="related-qa">
            <h2>Related Questions</h2>
            <ul>
                <?php foreach ($relatedQA as $related): ?>
                <li>
                    <a href="qna.php?id=<?php echo $related['id']; ?>">
                        <?php echo sanitize($related['question']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="index.php" class="btn btn-secondary">&larr; Back to Knowledge Base</a>
        </div>
    </div>
    
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Luna Chatbot. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>