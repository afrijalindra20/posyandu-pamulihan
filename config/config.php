<?php

require_once __DIR__ . '/../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
require_once __DIR__ . '/../vendor/autoload.php';


use FPDF\FPDF;

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

function showNotification($message, $type = 'success') {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type
    ];
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

function getAllPengukuran($db, $id_balita) {
    $query = "SELECT * FROM pengukuran_balita WHERE id_balita = :id_balita ORDER BY tanggal_pengukuran";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_balita', $id_balita, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBalita2AndPengukuran($db, $id_balita) {
    $sql = "SELECT b.*, p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan, p.status_gizi, p.bulan
            FROM balita_2 b
            LEFT JOIN pengukuran_balita_2 p ON b.id_balita = p.id_balita
            WHERE b.id_balita = :id_balita";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita]);
    $result = $stmt->fetchAll();
    return $result ? $result : [];
}

function getPengukuranByBulan2($db, $id_balita, $bulan) {
    $sql = "SELECT * FROM pengukuran_balita_2
            WHERE id_balita = :id_balita AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita, ':bulan' => $bulan]);
    $result = $stmt->fetchAll();
    return $result ? $result : [];
}


function getAllPengukuran2($db, $id_balita) {
    $query = "SELECT * FROM pengukuran_balita_2 WHERE id_balita = :id_balita ORDER BY tanggal_pengukuran";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_balita', $id_balita, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBalita3AndPengukuran($db, $id_balita) {
    $sql = "SELECT b.*, p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan, p.status_gizi, p.bulan
            FROM balita_3 b
            LEFT JOIN pengukuran_balita_3 p ON b.id_balita = p.id_balita
            WHERE b.id_balita = :id_balita";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan pengukuran berdasarkan bulan untuk balita_3
function getPengukuranByBulan3($db, $id_balita, $bulan) {
    $sql = "SELECT * FROM pengukuran_balita_3
            WHERE id_balita = :id_balita AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

function getAllPengukuran3($db, $id_balita) {
    $query = "SELECT * FROM pengukuran_balita_3 WHERE id_balita = :id_balita ORDER BY tanggal_pengukuran";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_balita', $id_balita, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBalita4AndPengukuran($db, $id_balita) {
    $sql = "SELECT b.*, p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan, p.status_gizi, p.bulan
            FROM balita_4 b
            LEFT JOIN pengukuran_balita_4 p ON b.id_balita = p.id_balita
            WHERE b.id_balita = :id_balita";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan pengukuran berdasarkan bulan untuk balita_3
function getPengukuranByBulan4($db, $id_balita, $bulan) {
    $sql = "SELECT * FROM pengukuran_balita_4
            WHERE id_balita = :id_balita AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_balita' => $id_balita, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

function getAllPengukuran4($db, $id_balita) {
    $query = "SELECT * FROM pengukuran_balita_4 WHERE id_balita = :id_balita ORDER BY tanggal_pengukuran";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_balita', $id_balita, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan data ibu hamil dan catatan kehamilan
function getIbuHamilAndCatatanKehamilan($db, $id_ibu) {
    $sql = "SELECT i.*, c.id_kehamilan, c.hamil_keberapa, c.hpht, c.hpl, c.usia_kehamilan, 
                   c.status_kehamilan, c.tinggi_badan, c.berat_badan, c.lila, c.laboratorium, 
                   c.imunisasi, c.mendapatkan_bantuan, c.mempunyai_bpjs, c.bulan
            FROM ibu_hamil i
            LEFT JOIN catatan_kehamilan c ON i.id_ibu = c.id_ibu
            WHERE i.id_ibu = :id_ibu";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan catatan kehamilan berdasarkan bulan
function getCatatanKehamilanByBulan($db, $id_ibu, $bulan) {
    $sql = "SELECT * FROM catatan_kehamilan
            WHERE id_ibu = :id_ibu AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan data ibu hamil dan catatan kehamilan
function getIbuHamil2AndCatatanKehamilan($db, $id_ibu) {
    $sql = "SELECT i.*, c.id_kehamilan, c.hamil_keberapa, c.hpht, c.hpl, c.usia_kehamilan, 
                   c.status_kehamilan, c.tinggi_badan, c.berat_badan, c.lila, c.laboratorium, 
                   c.imunisasi, c.mendapatkan_bantuan, c.mempunyai_bpjs, c.bulan
            FROM ibu_hamil_2 i
            LEFT JOIN catatan_kehamilan_2 c ON i.id_ibu = c.id_ibu
            WHERE i.id_ibu = :id_ibu";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ?: []; // Mengembalikan array kosong jika tidak ada hasil
}

// Fungsi untuk mendapatkan catatan kehamilan berdasarkan bulan
function getCatatanKehamilanByBulan2($db, $id_ibu, $bulan) {
    $sql = "SELECT * FROM catatan_kehamilan_2
            WHERE id_ibu = :id_ibu AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

function getAllCatatan2($db, $id_ibu) {
    $query = "SELECT * FROM catatan_kehamilan_2 WHERE id_ibu = :id_ibu ORDER BY hamil_keberapa";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_ibu', $id_ibu, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan data ibu hamil dan catatan kehamilan
function getIbuHamil3AndCatatanKehamilan($db, $id_ibu) {
    $sql = "SELECT i.*, c.id_kehamilan, c.hamil_keberapa, c.hpht, c.hpl, c.usia_kehamilan, 
                   c.status_kehamilan, c.tinggi_badan, c.berat_badan, c.lila, c.laboratorium, 
                   c.imunisasi, c.mendapatkan_bantuan, c.mempunyai_bpjs, c.bulan
            FROM ibu_hamil_3 i
            LEFT JOIN catatan_kehamilan_3 c ON i.id_ibu = c.id_ibu
            WHERE i.id_ibu = :id_ibu";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ?: []; // Mengembalikan array kosong jika tidak ada hasil
}

// Fungsi untuk mendapatkan catatan kehamilan berdasarkan bulan
function getCatatanKehamilanByBulan3($db, $id_ibu, $bulan) {
    $sql = "SELECT * FROM catatan_kehamilan_3
            WHERE id_ibu = :id_ibu AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan data ibu hamil dan catatan kehamilan
function getIbuHamil4AndCatatanKehamilan($db, $id_ibu) {
    $sql = "SELECT i.*, c.id_kehamilan, c.hamil_keberapa, c.hpht, c.hpl, c.usia_kehamilan, 
                   c.status_kehamilan, c.tinggi_badan, c.berat_badan, c.lila, c.laboratorium, 
                   c.imunisasi, c.mendapatkan_bantuan, c.mempunyai_bpjs, c.bulan
            FROM ibu_hamil_4 i
            LEFT JOIN catatan_kehamilan_4 c ON i.id_ibu = c.id_ibu
            WHERE i.id_ibu = :id_ibu";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ?: []; // Mengembalikan array kosong jika tidak ada hasil
}

// Fungsi untuk mendapatkan catatan kehamilan berdasarkan bulan
function getCatatanKehamilanByBulan4($db, $id_ibu, $bulan) {
    $sql = "SELECT * FROM catatan_kehamilan_4
            WHERE id_ibu = :id_ibu AND bulan = :bulan";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_ibu' => $id_ibu, ':bulan' => $bulan]);
    return $stmt->fetchAll();
}







