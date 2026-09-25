<?php
// Router for PHP's built-in web server: static files as-is, everything else through WordPress.
// Test environment only.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    if (substr($file, -4) === '.php') {
        require $file;
        return;
    }
    return false;
}
if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
