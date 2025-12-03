<?php
session_start();

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_peminjaman');

// Koneksi Database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset untuk mencegah SQL injection
$conn->set_charset("utf8mb4");

/**
 * Fungsi untuk membersihkan input (sanitasi)
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Fungsi untuk escape output (mencegah XSS)
 */
function escape_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF Token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('CSRF token validation failed!');
    }
    return true;
}

/**
 * Helper function untuk prepared statement SELECT
 */
function db_select($conn, $query, $params = [], $types = "") {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Helper function untuk prepared statement INSERT/UPDATE/DELETE
 */
function db_execute($conn, $query, $params = [], $types = "") {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    return $stmt->execute();
}

/**
 * Validasi tanggal
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function check_login($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        redirect('../log/login.php');
    }
    
    if ($required_role && $_SESSION['role'] != $required_role) {
        redirect('../log/login.php');
    }
}
?>