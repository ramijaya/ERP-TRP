<?php
/*
 * DATABASE CONFIGURATION
 * ----------------------
 * Untuk XAMPP lokal: biarkan default (root, tanpa password)
 * Untuk HOSTING: ubah sesuai info dari cPanel Domainesia
 *   - DB_HOST: biasanya 'localhost'
 *   - DB_NAME: nama database yang dibuat di cPanel (contoh: username_erp)
 *   - DB_USER: user database dari cPanel (contoh: username_erp)
 *   - DB_PASS: password database yang kamu set di cPanel
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'erp_trp');         // Ganti dengan nama database di cPanel
define('DB_USER', 'root');            // Ganti dengan user database di cPanel
define('DB_PASS', '');                // Ganti dengan password database di cPanel

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
