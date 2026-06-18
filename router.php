<?php
// router.php - Used only for PHP built-in server during development
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|woff2?|ttf|svg|ico)$/', $_SERVER["REQUEST_URI"])) {
    return false; // serve the requested resource as-is.
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($path === '/register') {
    require __DIR__ . '/register_intern.php';
    return true;
}

if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Check if file exists, if not, maybe it's a 404
$file = __DIR__ . $path;
if (file_exists($file) && is_file($file)) {
    require $file;
    return true;
}

return false;
