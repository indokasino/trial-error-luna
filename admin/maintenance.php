<?php
/**
 * Admin Maintenance Page
 * 
 * Handles maintenance tasks like cleaning old logs
 */

// Define root path
define('LUNA_ROOT', dirname(__DIR__));

// Include required files
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/auth.php';
require_once LUNA_ROOT . '/inc/functions.php';

// Require login
requireLogin();

// Generate CSRF token
$csrfToken = auth()->generateCsrfToken();

// Initialize variables
$error = '';
$success = '';

// Check action
$action = isset($_GET['action']) ? $_GET['action'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Validate CSRF token
if (!empty($action) && (!empty($token) && auth()->verifyCsrfToken($token))) {
    // Process actions
    switch ($action) {
        case 'clean_logs':
            // Get retention days from settings
            $retentionDays = (int)getSetting('log_retention_days', 90);
            
            try {
                // Clean old logs
                $count = cleanupOldLogs($retentionDays);
                
                if ($count > 0) {
                    $success = "Successfully deleted $count old log entries.";
                } else {
                    $success = "No logs older than $retentionDays days were found.";
                }
            } catch (Exception $e) {
                $error = "Error cleaning logs: " . $e->getMessage();
            }
            break;
            
        default:
            $error = 'Invalid action specified.';
            break;
    }
} elseif (!empty($action)) {
    $error = 'Invalid security token. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Chatbot - System Maintenance</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .maintenance-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .task-item {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .task-description {
            color: #555;
            margin-bottom: 10px;
        }
        .task-meta {
            font-size: 14px;
            color: #666;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .maintenance-summary {
            background-color: #e1f0fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            margin-bottom: 10px;
        }
        .summary-label {
            width: 150px;
            font-weight: 600;
            color: #2c3e50;
        }
        .summary-value {
            flex: 1;
        }
        .task-actions {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>System Maintenance</h1>
            <a href="settings.php" class="btn btn-secondary">Back to Settings</a>
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
        
        <?php if ($action === 'clean_logs' && !$error): ?>
        <div class="maintenance-card">
            <h2>Maintenance Results</h2>
            
            <div class="maintenance-summary">
                <div class="summary-row">
                    <div class="summary-label">Task:</div>
                    <div class="summary-value">Clean Old Logs</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Retention Period:</div>
                    <div class="summary-value"><?php echo $retentionDays; ?> days</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Status:</div>
                    <div class="summary-value"><?php echo $success; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="maintenance-card">
            <h2>Available Maintenance Tasks</h2>
            
            <div class="task-item">
                <div class="task-header">
                    <h3 class="task-title">Clean Old Logs</h3>
                </div>
                <div class="task-description">
                    Removes log entries older than the configured retention period.
                </div>
                <div class="task-meta">
                    Current retention period: <strong><?php echo getSetting('log_retention_days', 90); ?> days</strong>
                </div>
                <div class="task-actions">
                    <a href="maintenance.php?action=clean_logs&token=<?php echo $csrfToken; ?>" 
                       class="btn btn-primary" onclick="return confirm('Are you sure you want to clean old logs?');">
                        Run Task
                    </a>
                </div>
            </div>
            
            <!-- Additional maintenance tasks can be added here in the future -->
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>