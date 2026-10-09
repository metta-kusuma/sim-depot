<?php
// Mulai sesi di awal skrip
session_start();

// Sertakan file koneksi database
include "../config/koneksi.php";
// $conn = mysqli_connect($servername, $username, $password, $database);
// Anda tidak perlu koneksi ulang, $conn sudah ada dari "koneksi.php"

// Periksa koneksi
if (!$conn) {
    // Sebaiknya log error server, jangan tampilkan detail ke user
    error_log("Koneksi ke database gagal: " . mysqli_connect_error());
    die("Terjadi masalah koneksi. Silakan coba lagi nanti.");
}

// Ambil input dari form
$username_login = $_POST['username_login'] ?? '';
$password_input = $_POST['password'] ?? ''; // Ganti nama variabel agar lebih jelas

// Validasi input dasar
if (empty($username_login) || empty($password_input)) {
    echo "<script>alert('Username dan Password tidak boleh kosong'); javascript:history.go(-1);</script>";
    exit();
}

// Gunakan Prepared Statement HANYA untuk username
// Ambil ID, username, level, DAN HASH PASSWORD
$stmt = $conn->prepare("SELECT * FROM data_plng WHERE username_login = ?");

if ($stmt === false) {
    error_log("Error saat menyiapkan query: " . $conn->error);
    echo "<script>alert('Terjadi kesalahan pada sistem. Silakan coba lagi.'); javascript:history.go(-1);</script>";
    exit();
}

$stmt->bind_param("s", $username_login);
$stmt->execute();
$result = $stmt->get_result();

// Periksa apakah username ditemukan
if ($result->num_rows === 1) {
    // Username ditemukan, ambil data user
    $row = $result->fetch_assoc();
    $hashed_password_from_db = $row['password']; // Ambil hash password dari DB

    // Verifikasi password yang diinput dengan hash dari DB
    if (password_verify($password_input, $hashed_password_from_db)) {
        // Password cocok! Login berhasil

        // Regenerate session ID untuk keamanan setelah login
        session_regenerate_id(true);

        // Set session
        $_SESSION['id_plng'] = $row['id_plng'];
        $_SESSION['nama_plng'] = $row['nama_plng'];
        $_SESSION['username_login'] = $row['username_login'];
        $_SESSION['no_telp'] = $row['no_telp'];
        
        // --- TAMBAHAN PENTING ---
        $_SESSION['level'] = 'pelanggan'; // Tandai level mereka
        // --- AKHIR TAMBAHAN ---

        // Alihkan ke halaman dashboard atau index
        header("Location: ../pelanggan/index.php");
        exit();
    } else {
        // Password TIDAK cocok
        echo "<script>alert('Maaf, Password salah'); javascript:history.go(-1);</script>";
        exit();
    }
} else {
    // Username TIDAK ditemukan
    echo "<script>alert('Maaf, Username tidak ditemukan'); javascript:history.go(-1);</script>";
    exit();
}

// Tutup statement dan koneksi
$stmt->close();
$conn->close();
?>