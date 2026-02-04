<?php
// router.php
$path = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];

if (is_file($path)) {
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    if ($extension === 'js') {
        header('Content-Type: application/javascript');
    } elseif ($extension === 'css') {
        header('Content-Type: text/css');
    }
    return false; // Serve the requested resource as-is
}

// Fallback to Drupal's index.php for clean URLs
include_once $_SERVER['DOCUMENT_ROOT'] . '/index.php';