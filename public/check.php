<?php
/**
 * Quick diagnostic script to check Laravel deployment
 * Access this file directly: https://your-domain.com/check.php
 * Remove this file after troubleshooting for security
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laravel Deployment Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Laravel Deployment Diagnostic</h1>
        
        <div class="section">
            <h2>1. PHP Information</h2>
            <?php
            $phpVersion = phpversion();
            $requiredVersion = '8.2.0';
            if (version_compare($phpVersion, $requiredVersion, '>=')) {
                echo "<p class='success'>✓ PHP Version: $phpVersion (Required: $requiredVersion+)</p>";
            } else {
                echo "<p class='error'>✗ PHP Version: $phpVersion (Required: $requiredVersion+)</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>2. File System Checks</h2>
            <?php
            $basePath = dirname(__DIR__);
            $checks = [
                'Project Root' => $basePath,
                'Public Directory' => $basePath . '/public',
                'Storage Directory' => $basePath . '/storage',
                'Bootstrap Cache' => $basePath . '/bootstrap/cache',
                'Vendor Directory' => $basePath . '/vendor',
                '.env File' => $basePath . '/.env',
            ];

            foreach ($checks as $name => $path) {
                if (file_exists($path)) {
                    $writable = is_writable($path) ? ' (writable)' : ' (not writable)';
                    echo "<p class='success'>✓ $name exists$writable</p>";
                } else {
                    echo "<p class='error'>✗ $name NOT FOUND at: $path</p>";
                }
            }
            ?>
        </div>

        <div class="section">
            <h2>3. Composer & Dependencies</h2>
            <?php
            $vendorPath = $basePath . '/vendor/autoload.php';
            if (file_exists($vendorPath)) {
                echo "<p class='success'>✓ Composer dependencies installed</p>";
            } else {
                echo "<p class='error'>✗ Composer dependencies NOT installed. Run: composer install</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>4. Laravel Bootstrap</h2>
            <?php
            try {
                require $basePath . '/vendor/autoload.php';
                $app = require_once $basePath . '/bootstrap/app.php';
                echo "<p class='success'>✓ Laravel application bootstrapped successfully</p>";
                
                // Check APP_KEY
                $envPath = $basePath . '/.env';
                if (file_exists($envPath)) {
                    $envContent = file_get_contents($envPath);
                    if (strpos($envContent, 'APP_KEY=') !== false && strpos($envContent, 'APP_KEY=base64:') !== false) {
                        echo "<p class='success'>✓ APP_KEY is set</p>";
                    } else {
                        echo "<p class='warning'>⚠ APP_KEY may not be set. Run: php artisan key:generate</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Laravel bootstrap failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>5. Web Server Configuration</h2>
            <?php
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Not set';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? 'Not set';
            $requestUri = $_SERVER['REQUEST_URI'] ?? 'Not set';
            
            echo "<p class='info'>Document Root: <code>$documentRoot</code></p>";
            echo "<p class='info'>Script Name: <code>$scriptName</code></p>";
            echo "<p class='info'>Request URI: <code>$requestUri</code></p>";
            
            // Check if we're in the public directory
            $expectedPublicPath = $basePath . '/public';
            if (strpos($documentRoot, 'public') !== false || $documentRoot === $expectedPublicPath) {
                echo "<p class='success'>✓ Document root appears to be set correctly</p>";
            } else {
                echo "<p class='error'>✗ Document root may not be pointing to the 'public' directory!</p>";
                echo "<p class='warning'>Expected: <code>$expectedPublicPath</code></p>";
                echo "<p class='warning'>Current: <code>$documentRoot</code></p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>6. Mod_Rewrite (Apache)</h2>
            <?php
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                if (in_array('mod_rewrite', $modules)) {
                    echo "<p class='success'>✓ mod_rewrite is enabled</p>";
                } else {
                    echo "<p class='error'>✗ mod_rewrite is NOT enabled</p>";
                }
            } else {
                echo "<p class='info'>ℹ Cannot check mod_rewrite (not Apache or function not available)</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>7. Permissions</h2>
            <?php
            $storagePath = $basePath . '/storage';
            $cachePath = $basePath . '/bootstrap/cache';
            
            if (is_writable($storagePath)) {
                echo "<p class='success'>✓ Storage directory is writable</p>";
            } else {
                echo "<p class='error'>✗ Storage directory is NOT writable</p>";
                echo "<p class='warning'>Run: chmod -R 775 $storagePath</p>";
            }
            
            if (is_writable($cachePath)) {
                echo "<p class='success'>✓ Bootstrap cache directory is writable</p>";
            } else {
                echo "<p class='error'>✗ Bootstrap cache directory is NOT writable</p>";
                echo "<p class='warning'>Run: chmod -R 775 $cachePath</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>8. Next Steps</h2>
            <p>If you see errors above, fix them in this order:</p>
            <ol>
                <li>Ensure document root points to the <code>public</code> directory</li>
                <li>Install dependencies: <code>composer install</code></li>
                <li>Create <code>.env</code> file and set <code>APP_KEY</code></li>
                <li>Set proper file permissions</li>
                <li>Clear and cache config: <code>php artisan config:cache</code></li>
            </ol>
            <p><strong>⚠️ IMPORTANT:</strong> Delete this file (<code>check.php</code>) after troubleshooting for security!</p>
        </div>
    </div>
</body>
</html>

