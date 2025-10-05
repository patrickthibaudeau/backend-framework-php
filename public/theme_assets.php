<?php
// Simple theme asset server for images located under src/Core/Theme/default/images
// Supports logo.(svg|png|jpg) and can be extended. Prevents directory traversal.

declare(strict_types=1);

$allowedExts = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
$preference = ['jpg','jpeg','png','svg']; // preferred order when no extension supplied
$name = isset($_GET['path']) ? trim((string)$_GET['path']) : '';

// Accept only simple filenames like logo or logo.svg (no slashes)
if ($name === '' || strpos($name, '/') !== false || strpos($name, "..") !== false) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid asset path';
    exit;
}

$root = dirname(__DIR__);
$themeImagesDir = $root . '/src/Core/Theme/default/images';

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$base = $ext === '' ? $name : substr($name, 0, -(strlen($ext) + 1));

$targetFile = null;
$targetExt = null;

if ($ext === '') {
    // Auto-detect existing file by preference
    foreach ($preference as $candidateExt) {
        $candidate = $themeImagesDir . '/' . $base . '.' . $candidateExt;
        if (is_file($candidate)) {
            $targetFile = $candidate;
            $targetExt = $candidateExt;
            break;
        }
    }
    if ($targetFile === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Asset not found';
        exit;
    }
} else {
    if (!isset($allowedExts[$ext])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unsupported asset type';
        exit;
    }
    $file = $themeImagesDir . '/' . $name;
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Asset not found';
        exit;
    }
    $targetFile = $file;
    $targetExt = $ext;
}

// Basic caching headers
$mtime = filemtime($targetFile) ?: time();
$etag = 'W/"' . sha1($base . '|' . $targetExt . '|' . $mtime . '|' . filesize($targetFile)) . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=3600');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $allowedExts[$targetExt]);
header('Content-Length: ' . filesize($targetFile));
readfile($targetFile);
