<?php
session_start();
define('APP_NAME', 'ERP-TRP');
define('APP_VERSION', '1.0.0');
/*
 * BASE_URL: Sesuaikan dengan lokasi instalasi
 * - Di XAMPP lokal:  '/ERP-TRP/'
 * - Di Hosting (root domain): '/'
 * - Di subfolder hosting: '/subfolder/'
 */
define('BASE_URL', '/ERP-TRP/');  // Ganti jadi '/' saat deploy ke hosting

require_once __DIR__ . '/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
