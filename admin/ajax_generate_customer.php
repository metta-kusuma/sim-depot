<?php
// File ini dipanggil oleh JavaScript (AJAX) untuk membuat data login baru

session_start();
date_default_timezone_set('Asia/Jakarta');

// Keamanan: Pastikan hanya staf (admin/kasir) yang bisa mengakses file ini
if (!isset($_SESSION['level']) || $_SESSION['level'] == 'pelanggan' || empty($_SESSION['level'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

// Panggil koneksi (yang berisi 2 fungsi generate)
include "../config/koneksi.php";

// Panggil kedua fungsi generator dari koneksi.php
$username_baru = generateNewPelangganID($conn);
$password_polos = generateRandomPassword(8); // Buat password 8 karakter

// Hash passwordnya untuk disimpan ke database
$password_hash = password_hash($password_polos, PASSWORD_DEFAULT);

// Kembalikan data sebagai JSON
header('Content-Type: application/json');
echo json_encode([
    'username' => $username_baru,
    'password_polos' => $password_polos,
    'password_hash' => $password_hash
]);

$conn->close();
?>