<?php
/**
 * Admin GPT Review Page
 * 
 * Reviews GPT-generated responses and allows converting them to trained Q&A
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

// Generate CSRF token
$csrfToken = auth()->generateCsrfToken();

// Handle approval/training of GPT responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'train') {
    // Validate CSRF token
    if (!auth()->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $logId = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $confidence = floatval($_POST['confidence'] ?? 1.0);
        $tags = trim($_POST['tags'] ?? '');
        
        if ($logId <= 0 || empty($question) || empty($answer)) {
            $error = 'Invalid data provided.';
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Process tags if provided
                if (!empty($tags)) {
                    $tagList = explode(',', $tags);
                    foreach ($tagList as $tagName) {
                        $tagName = trim($tagName);
                        if (!empty($tagName)) {
                            // Check if tag exists
                            $tagExists = db()->count("SELECT COUNT(*) FROM tags WHERE tag_name = ?", [$tagName]);
                            if (!$tagExists) {
                                // Add new tag
                                db()->insert("INSERT INTO tags (tag_name) VALUES (?)", [$tagName]);
                            }
                        }
                    }
                }
                
                // Check if question already exists
                $existingId = db()->fetchOne(
                    "SELECT id FROM prompt_data WHERE LOWER(question) = LOWER(?) LIMIT 1", 
                    [strtolower($question)]
                );
                
                if ($existingId) {
                    // Update existing record
                    $sql = "
                        UPDATE prompt_data 
                        SET answer = ?, tags = ?, confidence_level = ?, is_trained = 1, status = 'active' 
                        WHERE id = ?
                    ";
                    $params = [$answer, $tags, $confidence, $existingId['id']];
                    db()->update($sql, $params);
                    
                    $success = 'Existing Q&A updated and trained.';
                } else {
                    // Insert new record
                    $sql = "
                        INSERT INTO prompt_data 
                        (question, answer, tags, confidence_level, is_trained, status) 
                        VALUES (?, ?, ?, ?, 1, 'active')
                    ";
                    $params = [$question, $answer, $tags, $confidence];
                    $newId = db()->insert($sql, $params);
                    
                    if (!$newId) {
                        throw new Exception('Failed to create new Q&A entry.');
                    }
                    
                    $success = 'New Q&A created and trained.';
                }
                
                // Mark log as trained
                $updateLogSql = "UPDATE response_logs SET trained = 1 WHERE id = ?";
                db()->update($updateLogSql, [$logId]);
                
                // Commit transaction
                $db->commit();
                
                // Redirect to avoid form resubmission
                header("Location: review.php?success=" . urlencode($success));
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                error_log("Train GPT response error: " . $e->getMessage());
                $error = 'Database error occurred.';
            }
        }
    }
}

// Get success/error message from URL
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Pagination setup
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Get untrained GPT responses with high scores
$sql = "
    SELECT r.*, 
           (SELECT COUNT(*) FROM prompt_data WHERE LOWER(question) = LOWER(r.user_message)) as exists_in_db
    FROM response_logs r
    WHERE r.source = 'gpt' 
      AND (r.trained IS NULL OR r.trained = 0)
      AND (r.score IS NULL OR r.score >= 7.0)
    ORDER BY r.created_at DESC
    LIMIT $offset, $recordsPerPage
";

$responses = db()->fetchAll($sql);

// Count total records for pagination
$totalRecords = db()->count("
    SELECT COUNT(*) 
    FROM response_logs 
    WHERE source = 'gpt' 
      AND (trained IS NULL OR trained = 0)
      AND (score IS NULL OR score >= 7.0)
");

$totalPages = ceil($totalRecords / $recordsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - GPT Review</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>GPT Response Review</h1>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>
        
        <div class="data-summary">
            <p>Showing GPT responses with score >= 7.0 that haven't been trained yet</p>
            <p>Total: <?php echo $totalRecords; ?> responses</p>
        </div>
        
        <?php if (count($responses) > 0): ?>
        <div class="gpt-responses">
            <?php foreach ($responses as $index => $response): ?>
            <div class="gpt-response-card" id="response-<?php echo $response['id']; ?>">
                <div class="card-header">
                    <div class="meta-info">
                        <span class="datetime"><?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?></span>
                        <?php if ($response['exists_in_db'] > 0): ?>
                        <span class="badge badge-warning">Already in database</span>
                        <?php endif; ?>
                        <?php if ($response['score']): ?>
                        <span class="score-badge">
                            Score: <strong><?php echo number_format($response['score'], 1); ?></strong>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="controls">
                        <button class="btn btn-sm btn-secondary toggle-train-form" 
                                data-target="train-form-<?php echo $response['id']; ?>">
                            Train
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="qa-container">
                        <div class="question">
                            <h4>Question:</h4>
                            <p><?php echo sanitize($response['user_message']); ?></p>
                        </div>
                        <div class="answer">
                            <h4>Answer:</h4>
                            <p><?php echo nl2br(sanitize($response['ai_response'])); ?></p>
                        </div>
                        <?php if ($response['feedback']): ?>
                        <div class="feedback">
                            <h4>Feedback:</h4>
                            <p><?php echo sanitize($response['feedback']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="train-form" id="train-form-<?php echo $response['id']; ?>" style="display: none;">
                        <h4>Add to Trained Database</h4>
                        <form method="POST" action="review.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="train">
                            <input type="hidden" name="log_id" value="<?php echo $response['id']; ?>">
                            
                            <div class="form-group">
                                <label for="question-<?php echo $response['id']; ?>">Question</label>
                                <textarea id="question-<?php echo $response['id']; ?>" name="question" rows="2" required><?php 
                                    echo sanitize($response['user_message']); 
                                ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="answer-<?php echo $response['id']; ?>">Answer</label>
                                <textarea id="answer-<?php echo $response['id']; ?>" name="answer" rows="5" required><?php 
                                    echo sanitize($response['ai_response']); 
                                ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="tags-<?php echo $response['id']; ?>">Tags (comma separated)</label>
                                    <input type="text" id="tags-<?php echo $response['id']; ?>" name="tags" value="">
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="confidence-<?php echo $response['id']; ?>">Confidence Level</label>
                                    <input type="number" id="confidence-<?php echo $response['id']; ?>" name="confidence" 
                                           step="0.1" min="0" max="1" value="<?php echo ($response['score'] ? min(1, $response['score'] / 10) : 0.8); ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save to Database</button>
                                <button type="button" class="btn btn-link cancel-train">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <?php
            echo getPagination($currentPage, $totalPages, 'review.php?page=%d');
            ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-records">
            <p>No GPT responses to review at this time.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle train form visibility
        const toggleButtons = document.querySelectorAll('.toggle-train-form');
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const form = document.getElementById(targetId);
                
                // Hide all other forms
                document.querySelectorAll('.train-form').forEach(function(f) {
                    if (f.id !== targetId) {
                        f.style.display = 'none';
                    }
                });
                
                // Toggle this form
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            });
        });
        
        // Cancel button functionality
        const cancelButtons = document.querySelectorAll('.cancel-train');
        cancelButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                this.closest('.train-form').style.display = 'none';
            });
        });
    });
    </script>
</body>
</html>