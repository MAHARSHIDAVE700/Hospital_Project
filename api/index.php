<?php
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// Default to index.php if path is empty
if ($path === '' || $path === '/') {
    $path = 'index.php';
}

// Check if file exists directly first
$file = realpath(__DIR__ . '/../' . $path);
$base_dir = realpath(__DIR__ . '/../');

if (!$file || !file_exists($file) || !is_file($file)) {
    // Try appending .php if missing extension
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (empty($ext)) {
        $pathWithPhp = $path . '.php';
        $fileTest = realpath(__DIR__ . '/../' . $pathWithPhp);
        if ($fileTest && file_exists($fileTest) && is_file($fileTest)) {
            $file = $fileTest;
        }
    }
    
    // If path is a directory, look for index.php inside it
    if (is_dir(__DIR__ . '/../' . $path)) {
        $dirIndex = realpath(__DIR__ . '/../' . rtrim($path, '/') . '/index.php');
        if ($dirIndex && file_exists($dirIndex)) {
            $file = $dirIndex;
        }
    }
}

// Security check: ensure the resolved file is within the project directory
if ($file && strpos($file, $base_dir) === 0 && file_exists($file) && is_file($file)) {
    chdir(dirname($file));
    require $file;
} else {
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($path);
}
?>
