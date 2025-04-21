<?php
/**
 * Admin Logout
 * 
 * Handles user logout and session destruction
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Perform logout
auth()->logout();

// Redirect to login page
header('Location: login.php');
exit;