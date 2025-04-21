<?php
/**
 * Admin Edit Q&A Page
 * 
 * Add or edit Q&A entries
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

// Initialize variables
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$question = '';
$answer = '';
$tags = '';
$confidence = 1.0;
$status = 'active';
$isTrained = 0;
$error = '';
$success = '';
$allTags = [];

// Get all tags for autocomplete
try {
    $tagRecords = db()->fetchAll("SELECT tag_name FROM tags ORDER BY tag_name");
    foreach ($tagRecords as $tag) {
        $allTags[] = $tag['tag_name'];
    }
} catch (Exception $e) {
    error_log("Tag fetch error: " . $e->getMessage());
    $error = 'Could not fetch tags: ' . $e->getMessage();
}

// If ID is provided, load existing record
if ($id > 0) {
    $record = db()->fetchOne("SELECT * FROM prompt_data WHERE id = ?", [$id]);
    
    if ($record) {
        $question = $record['question'];
        $answer = $record['answer'];
        $tags = $record['tags'];
        $confidence = $record['confidence_level'];
        $status = $record['status'];
        $isTrained = $record['is_trained'];
    } else {
        $error = 'Record not found.';
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!auth()->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $confidence = floatval($_POST['confidence'] ?? 1.0);
        $status = $_POST['status'] ?? 'active';
        $isTrained = isset($_POST['is_trained']) ? 1 : 0;
        
        // Validate input
        if (empty($question)) {
            $error = 'Question is required.';
        } elseif (empty($answer)) {
            $error = 'Answer is required.';
        } elseif ($confidence < 0 || $confidence > 1) {
            $error = 'Confidence level must be between 0 and 1.';
        } else {
            try {
                // Process tags - add new ones to tags table
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
                
                // Save the record
                if ($id > 0) {
                    // Update existing record
                    $sql = "
                        UPDATE prompt_data 
                        SET question = ?, answer = ?, tags = ?, confidence_level = ?, status = ?, is_trained = ? 
                        WHERE id = ?
                    ";
                    $params = [$question, $answer, $tags, $confidence, $status, $isTrained, $id];
                    $result = db()->update($sql, $params);
                    
                    if ($result !== false) {
                        $success = 'Q&A updated successfully.';
                    } else {
                        $error = 'Failed to update Q&A.';
                    }
                } else {
                    // Insert new record
                    $sql = "
                        INSERT INTO prompt_data 
                        (question, answer, tags, confidence_level, status, is_trained) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ";
                    $params = [$question, $answer, $tags, $confidence, $status, $isTrained];
                    $newId = db()->insert($sql, $params);
                    
                    if ($newId) {
                        // Redirect to edit page with new ID
                        header("Location: edit.php?id=$newId&success=created");
                        exit;
                    } else {
                        $error = 'Failed to create Q&A.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Save Q&A error: " . $e->getMessage());
                $error = 'Database error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] === 'created') {
    $success = 'Q&A created successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - <?php echo $id > 0 ? 'Edit' : 'Add'; ?> Q&A</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo $id > 0 ? 'Edit' : 'Add New'; ?> Q&A</h1>
            <a href="index.php" class="btn btn-secondary">Back to List</a>
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
        
        <form method="POST" action="edit.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>" class="edit-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="form-group">
                <label for="question">Question <span class="required">*</span></label>
                <textarea id="question" name="question" rows="3" required><?php echo sanitize($question); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="answer">Answer <span class="required">*</span></label>
                <textarea id="answer" name="answer" rows="8" required><?php echo sanitize($answer); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="tags">Tags (comma separated)</label>
                <input type="text" id="tags" name="tags" value="<?php echo sanitize($tags); ?>">
                <div class="tag-suggestions">
                    <?php foreach ($allTags as $tag): ?>
                    <span class="tag-suggestion" data-tag="<?php echo sanitize($tag); ?>"><?php echo sanitize($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="confidence">Confidence Level</label>
                    <input type="number" id="confidence" name="confidence" step="0.1" min="0" max="1" 
                           value="<?php echo sanitize($confidence); ?>">
                </div>
                
                <div class="form-group col-md-4">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                
                <div class="form-group col-md-4">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_trained" name="is_trained" value="1" 
                               <?php echo $isTrained ? 'checked' : ''; ?>>
                        <label for="is_trained">Trained</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="index.php" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Tag suggestion functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tagsInput = document.getElementById('tags');
        const tagSuggestions = document.querySelectorAll('.tag-suggestion');
        
        tagSuggestions.forEach(function(tag) {
            tag.addEventListener('click', function() {
                const tagValue = this.getAttribute('data-tag');
                const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t !== '');
                
                if (!currentTags.includes(tagValue)) {
                    if (currentTags.length > 0) {
                        tagsInput.value = currentTags.join(', ') + ', ' + tagValue;
                    } else {
                        tagsInput.value = tagValue;
                    }
                }
            });
        });
    });
    </script>
</body>
</html>