<?php
/**
 * Admin Footer Include
 * 
 * Common footer for all admin pages
 */

// Prevent direct access
if (!defined('LUNA_ROOT')) {
    die('Access denied');
}
?>
<footer class="admin-footer">
    <div class="container">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Luna Chatbot Admin Panel. All rights reserved.</p>
            <p class="version">Version 1.0.0</p>
        </div>
    </div>
</footer>