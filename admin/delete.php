<?php
/**
 * Admin Delete Q&A Page
 * 
 * Deletes Q&A entries
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Require login
requireLogin();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !auth()->verifyCsrfToken($_POST['csrf_token'])) {
    header('Location: index.php?error=invalid_token');
    exit;
}

// Get record ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

try {
    // Delete the record
    $result = db()->delete("DELETE FROM prompt_data WHERE id = ?", [$id]);
    
    if ($result !== false) {
        header('Location: index.php?success=deleted');
    } else {
        header('Location: index.php?error=delete_failed');
    }
} catch (PDOException $e) {
    error_log("Delete Q&A error: " . $e->getMessage());
    header('Location: index.php?error=database_error&message=' . urlencode($e->getMessage()));
}
exit;