<?php

// Router for PHP built-in server
// This mimics Apache's mod_rewrite behavior

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the request is for a real file, serve it
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false; // Let PHP's built-in server serve the file
}

// Otherwise, forward to index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Update PHP_SELF for Symfony
$_SERVER['PHP_SELF'] = '/index.php';

// Load the front controller
require __DIR__ . '/public/index.php';


