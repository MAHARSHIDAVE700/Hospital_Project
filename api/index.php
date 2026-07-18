<?php
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash and /api prefix if present
$path = ltrim($path, '/');
if (preg_match('/^api\//', $path)) {
    $path = substr($path, 4);
}

// Default to index.php if path is empty
if ($path === '' || $path === '/') {
    $path = 'index.php';
}

// If the path accesses a directory, append index.php
if (is_dir(__DIR__ . '/../' . $path)) {
    $path = rtrim($path, '/') . '/index.php';
}

// Add .php extension if no extension is present and it's not a directory
$ext = pathinfo($path, PATHINFO_EXTENSION);
if (empty($ext)) {
    $path .= '.php';
}

$file = realpath(__DIR__ . '/../' . $path);

// Security check: ensure the resolved file is within the project directory
$base_dir = realpath(__DIR__ . '/../');
if ($file && strpos($file, $base_dir) === 0 && file_exists($file) && is_file($file)) {
    // Set the working directory to the file's directory so relative includes work
    chdir(dirname($file));
    require $file;
} else {
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($path);
}