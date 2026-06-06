<?php
session_start();
session_destroy();
require_once __DIR__ . '/config/database.php';
// Read BASE_URL
$configContent = file_get_contents(__DIR__ . '/config/app.php');
$baseUrl = '/';
if (preg_match("/define\('BASE_URL',\s*'([^']+)'\)/", $configContent, $m)) $baseUrl = $m[1];
header('Location: ' . $baseUrl . 'login.php');
exit;
