<?php
// Mulai sesi di awal skrip
session_start();

// Sertakan file koneksi database
include "../config/koneksi.php";
$conn = mysqli_connect($servername, $username, $password, $database);

// Periksa koneksi
if (!$conn) {
    // Sebaiknya log error server, jangan tampilkan detail ke user
    error_log("Koneksi ke database gagal: " . mysqli_connect_error());
    die("Terjadi masalah koneksi. Silakan coba lagi nanti.");
}

// Ambil input dari form
$user_login = $_POST['user_login'] ?? '';
$password_input = $_POST['password'] ?? ''; // Ganti nama variabel agar lebih jelas

// Validasi input dasar
if (empty($user_login) || empty($password_input)) {
    echo "<script>alert('Username dan Password tidak boleh kosong'); javascript:history.go(-1);</script>";
    exit();
}

// Gunakan Prepared Statement HANYA untuk username
// Ambil ID, username, level, DAN HASH PASSWORD
$stmt = $conn->prepare("SELECT id_user, user_login, level, password FROM user WHERE user_login = ?");

if ($stmt === false) {
    error_log("Error saat menyiapkan query: " . $conn->error);
    echo "<script>alert('Terjadi kesalahan pada sistem. Silakan coba lagi.'); javascript:history.go(-1);</script>";
    exit();
}

$stmt->bind_param("s", $user_login);
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
        $_SESSION['id_user'] = $row['id_user'];
        $_SESSION['user_login'] = $row['user_login'];
        $_SESSION['level'] = $row['level'];

        // Alihkan ke halaman dashboard atau index
        header("Location: index.php");
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

// Tutup statement dan koneksi (sebenarnya tidak akan tercapai karena ada exit() di atas)
$stmt->close();
$conn->close();
?>