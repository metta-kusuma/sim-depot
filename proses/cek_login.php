<?php
session_start();
include "../config/koneksi.php";

$username_login = $_POST['username_login'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username_login) || empty($password)) {
    die("ID Login dan Password harus diisi. <a href='login_pelanggan.php'>Kembali</a>");
}

// 1. Cari pelanggan berdasarkan USERNAME_LOGIN (pg0001)
$sql = "SELECT * FROM data_plng WHERE username_login = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username_login);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

// 2. Verifikasi password
// PENTING: Cek $data dulu, baru password_verify
if ($data && password_verify($password, $data['password'])) {

    // 3. Buat Sesi (SESSION) khusus PELANGGAN
    // Kita simpan ID asli (angka) dan nama
    $_SESSION['id_plng']    = $data['id_plng'];
    $_SESSION['nama_plng']  = $data['nama_plng'];
    $_SESSION['level']      = 'Pelanggan';

    // Arahkan ke dashboard pelanggan (buat file ini di langkah 5)
    header("Location: dashboard_pelanggan.php");
    exit;
} else {
    // Gagal login
    echo "Login Gagal! ID Pelanggan atau Password salah. 
          <a href='login_pelanggan.php'>Coba Lagi</a>";
    exit;
}
