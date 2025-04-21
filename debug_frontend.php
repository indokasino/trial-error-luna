<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('LUNA_ROOT', dirname(__FILE__));

echo "<h2>Frontend Debugging:</h2>";

// Check if frontend folder exists
$frontendPath = LUNA_ROOT . '/frontend';
echo "Frontend folder path: $frontendPath<br>";
echo "Frontend folder exists: " . (is_dir($frontendPath) ? 'Yes' : 'No') . "<br><br>";

// Check if index.php exists in frontend folder
$frontendIndexPath = $frontendPath . '/index.php';
echo "Frontend index path: $frontendIndexPath<br>";
echo "Frontend index exists: " . (file_exists($frontendIndexPath) ? 'Yes' : 'No') . "<br><br>";

// Check if we can create the frontend folder if it doesn't exist
if (!is_dir($frontendPath)) {
    echo "Attempting to create frontend folder... ";
    $result = mkdir($frontendPath, 0755, true);
    echo $result ? 'Success!' : 'Failed!';
    echo "<br><br>";
}

// Check for permissions
echo "Directory permissions:<br>";
echo "Luna root: " . substr(sprintf('%o', fileperms(LUNA_ROOT)), -4) . "<br>";
if (is_dir($frontendPath)) {
    echo "Frontend folder: " . substr(sprintf('%o', fileperms($frontendPath)), -4) . "<br>";
}

// Create basic frontend index if it doesn't exist
if (!file_exists($frontendIndexPath)) {
    echo "<br>Creating basic frontend index.php...<br>";
    
    $basicFrontend = <<<'EOT'
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

echo "<h1>Luna Chatbot Knowledge Base</h1>";
echo "<p>This is a basic frontend page. The full frontend implementation needs to be uploaded.</p>";

try {
    // Initialize database
    $db = db()->getConnection();
    
    // Count active Q&A entries
    $stmt = $db->prepare("SELECT COUNT(*) FROM prompt_data WHERE status = 'active'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "<p>Total active Q&A entries: $count</p>";
    
    // Show some entries if available
    if ($count > 0) {
        $stmt = $db->prepare("SELECT id, question FROM prompt_data WHERE status = 'active' ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Recent Entries:</h2>";
        echo "<ul>";
        foreach ($entries as $entry) {
            echo "<li>" . htmlspecialchars($entry['question']) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='../admin/login.php'>Admin Login</a></p>";
EOT;

    $result = file_put_contents($frontendIndexPath, $basicFrontend);
    echo $result ? "Frontend index created successfully!" : "Failed to create frontend index!";
}

echo "<br><br><a href='frontend/index.php'>Try accessing the frontend</a>";
?>