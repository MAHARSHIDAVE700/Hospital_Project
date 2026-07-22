<?php

// Load environment variables from .env or .env.local if present
if (!function_exists('hospitalProjectLoadEnv')) {
    function hospitalProjectLoadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Remove surrounding quotes if any
                if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match('/^\'(.*)\'$/', $value, $matches)) {
                    $value = $matches[1];
                }
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load env files
hospitalProjectLoadEnv(dirname(__DIR__) . '/.env.local');
hospitalProjectLoadEnv(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/neon_db.php';

$host = "ep-blue-boat-auib76so-pooler.c-10.us-east-1.aws.neon.tech";
$user = "neondb_owner";
$pass = "npg_1RJYwpx8SvVK";
$db = "neondb";

try {
    $conn = new NeonDB($host, $user, $pass, $db);
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

require_once __DIR__ . '/activity_logger.php';
?>
