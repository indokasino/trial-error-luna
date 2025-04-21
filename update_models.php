<?php
/**
 * Script untuk memperbarui konfigurasi model GPT
 * 
 * Mengatur model utama ke GPT-4.1 dan model cadangan ke GPT-4o
 */

// Set error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definisi path root
define('LUNA_ROOT', dirname(__FILE__));

// Impor file yang diperlukan
require_once LUNA_ROOT . '/inc/db.php';
require_once LUNA_ROOT . '/inc/functions.php';

echo "<h1>Luna Chatbot Model Configuration Update</h1>";

try {
    // Dapatkan koneksi database
    $db = db()->getConnection();
    
    // Model yang akan diatur
    $primaryModel = 'gpt-4.1';
    $fallbackModel = 'gpt-4o';
    
    // Update pengaturan model
    $updatePrimary = updateSetting('gpt_model', $primaryModel);
    $updateFallback = updateSetting('fallback_model', $fallbackModel);
    
    // Periksa hasil update
    if ($updatePrimary && $updateFallback) {
        echo "<p style='color: green;'>✓ Konfigurasi model berhasil diperbarui!</p>";
        echo "<ul>";
        echo "<li>Model Utama: <strong>$primaryModel</strong></li>";
        echo "<li>Model Cadangan: <strong>$fallbackModel</strong></li>";
        echo "</ul>";
        
        // Juga perbarui gpt_service.php untuk mendukung gpt-4o
        $gptServicePath = LUNA_ROOT . '/inc/gpt_service.php';
        if (file_exists($gptServicePath) && is_writable($gptServicePath)) {
            $gptServiceContent = file_get_contents($gptServicePath);
            
            // Perbarui mapping model
            $pattern = '/\$modelMap\s*=\s*\[\s*\'gpt-4\.1\'\s*=>\s*\'gpt-4-turbo\',\s*\'o4-mini\'\s*=>\s*\'gpt-4\'\s*\];/';
            $replacement = "\$modelMap = [\n                'gpt-4.1' => 'gpt-4-turbo',\n                'o4-mini' => 'gpt-4',\n                'gpt-4o' => 'gpt-4o'\n            ];";
            
            if (preg_match($pattern, $gptServiceContent)) {
                $updatedContent = preg_replace($pattern, $replacement, $gptServiceContent);
                file_put_contents($gptServicePath, $updatedContent);
                echo "<p style='color: green;'>✓ File gpt_service.php diperbarui untuk mendukung model gpt-4o</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Pola model tidak ditemukan di gpt_service.php. Perbarui secara manual untuk mendukung gpt-4o.</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Tidak dapat memperbarui gpt_service.php (tidak ada atau tidak dapat ditulis)</p>";
        }
        
        // Update webhook.php untuk mendukung token URL
        $webhookPath = LUNA_ROOT . '/api/webhook.php';
        if (file_exists($webhookPath) && is_writable($webhookPath)) {
            $webhookContent = file_get_contents($webhookPath);
            
            // Cek apakah sudah ada penanganan token URL
            if (strpos($webhookContent, "isset(\$_GET['token'])") === false) {
                // Cari posisi setelah bagian OPTIONS request
                $pattern = "/if \(\\\$_SERVER\['REQUEST_METHOD'\] === 'OPTIONS'\) \{.*?exit;\s*\}/s";
                if (preg_match($pattern, $webhookContent, $matches, PREG_OFFSET_CAPTURE)) {
                    $insertPosition = $matches[0][1] + strlen($matches[0][0]);
                    
                    // Kode untuk dimasukkan: penanganan token URL
                    $codeToInsert = "\n\n// Check token from URL if available\nif (isset(\$_GET['token'])) {\n    // Set Authorization header for webhooks that send token via URL\n    \$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . \$_GET['token'];\n}\n";
                    
                    // Sisipkan kode
                    $updatedWebhook = substr($webhookContent, 0, $insertPosition) . $codeToInsert . substr($webhookContent, $insertPosition);
                    file_put_contents($webhookPath, $updatedWebhook);
                    echo "<p style='color: green;'>✓ File webhook.php diperbarui untuk mendukung token URL</p>";
                } else {
                    echo "<p style='color: orange;'>⚠ Tidak dapat menemukan lokasi yang tepat di webhook.php untuk menyisipkan penanganan token URL</p>";
                }
            } else {
                echo "<p style='color: blue;'>ℹ File webhook.php sudah mendukung token URL</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Tidak dapat memperbarui webhook.php (tidak ada atau tidak dapat ditulis)</p>";
        }
        
        echo "<p>Konfigurasi selesai! Luna chatbot sekarang menggunakan:</p>";
        echo "<ul>";
        echo "<li>Model utama: <strong>GPT-4.1</strong> (akan dipetakan ke gpt-4-turbo di API)</li>";
        echo "<li>Model cadangan: <strong>GPT-4o</strong> (akan digunakan jika model utama gagal)</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Gagal memperbarui pengaturan model. Periksa koneksi database dan izin.</p>";
    }
    
    // Tambahkan link kembali
    echo "<p><a href='admin/settings.php'>Kembali ke Halaman Pengaturan</a> | <a href='api/webhook_test.php'>Tes Webhook</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>