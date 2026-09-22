<?php
require_once(__DIR__ . '/auth.php');
fg_admin_require_login(false);
fg_admin_logout();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? '';
$path  = rtrim(dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/admin'), '/');
header('Location: ' . $protocol . '://' . $host . $path . '/login.php?bye');
exit;
