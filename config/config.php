<?php
// config/config.php

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definisikan path database dan admin default
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'Admin@123');

try {
    // Inisialisasi koneksi database
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Buat tabel jika belum ada
    createTables($db);
    // Tambahkan admin default
    addDefaultAdmin($db);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk membuat tabel
function createTables($db) {
    // Buat tabel admin
    $sql = "CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        is_default BOOLEAN DEFAULT 0
    )";
    $db->exec($sql);

    // Buat tabel tasks
    $sql = "CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task TEXT NOT NULL,
        due_date DATE NOT NULL
    )";
    $db->exec($sql);

    // Tambahkan pembuatan tabel lain jika diperlukan
}

// Fungsi untuk menambahkan admin default
function addDefaultAdmin($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin WHERE username = :username");
    $stmt->execute([':username' => DEFAULT_ADMIN_USERNAME]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $hashedPassword = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admin (username, password, is_default) VALUES (:username, :password, 1)";
        $stmt = $db->prepare($sql);
        $stmt->execute([':username' => DEFAULT_ADMIN_USERNAME, ':password' => $hashedPassword]);
    }
}

// Fungsi query umum
function query($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Fungsi untuk mengambil semua hasil
function fetchAll($result) {
    return $result->fetchAll();
}

// Fungsi untuk mengambil satu hasil
function fetchOne($result) {
    return $result->fetch();
}

// Fungsi untuk escape string
function escapeString($string) {
    global $db;
    return $db->quote($string);
}

// Fungsi untuk mendapatkan data balita dan pengukuran
function getBalitaAndPengukuran($db, $id_balita) {
    $sql = "SELECT b.*, p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan, p.status_gizi, p.bulan
            FROM balita b
            LEFT JOIN pengukuran_balita p ON b.id_balita = p.id_balita
            WHERE b.id_balita = :id_balita";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan pengukuran berdasarkan bulan
function getPengukuranByBulan($db, $id_balita, $bulan) {
    $sql = "SELECT * FROM pengukuran_balita
            WHERE id_balita = :id_balita AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

// Fungsi untuk validasi password
function validatePassword($password) {
    $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
    return preg_match($regex, $password);
}

// Fungsi untuk mendapatkan koneksi database
function getDbConnection() {
    global $db;
    return $db;
}

// Fungsi untuk menambahkan admin
function addAdmin($username, $password) {
    global $db;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO admin (username, password) VALUES (:username, :password)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([':username' => $username, ':password' => $hashedPassword]);
}

// Fungsi untuk verifikasi login admin
function verifyAdminLogin($username, $password) {
    global $db;
    if ($username === DEFAULT_ADMIN_USERNAME && $password === DEFAULT_ADMIN_PASSWORD) {
        return true;
    }
    
    $sql = "SELECT * FROM admin WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return true;
    }
    return false;
}

// Anda dapat menambahkan fungsi-fungsi lain yang diperlukan di sini