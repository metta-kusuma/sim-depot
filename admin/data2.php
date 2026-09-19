<?php

/**
 * =====================================================================
 * data.php - Pengelola Aksi Backend Admin
 * =====================================================================
 * File ini menangani berbagai operasi Create, Read, Update, Delete (CRUD)
 * untuk modul-modul admin seperti aktivitas, admin, member, produk,
 * pemesanan, pengantaran, laporan, arsip dinkes, dan pengaturan.
 * Termasuk integrasi dengan Cloudflare R2 untuk penyimpanan file.
 *
 * Lokasi File: htdocs/admin/data.php
 */

// --- Pengaturan Awal ---
ini_set('display_errors', 1); // Tampilkan error untuk debugging (nonaktifkan di produksi)
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta'); // Atur zona waktu
session_start(); // Mulai atau lanjutkan sesi

if (!isset($_SESSION['level']) || $_SESSION['level'] == 'pelanggan' || empty($_SESSION['level'])) {
    http_response_code(403); // 403 Forbidden
    die("Akses ditolak. Anda bukan staf.");
}

// --- Integrasi Cloudflare R2 ---
// Memuat library AWS SDK PHP yang diinstal via Composer
require_once __DIR__ . '/laporan/vendor/autoload.php'; // Path: htdocs/admin/laporan/vendor/autoload.php
// Memuat konfigurasi kredensial R2
$r2ConfigPath = __DIR__ . '/../config/r2_config.php'; // Path: htdocs/config/r2_config.php
if (!file_exists($r2ConfigPath)) {
    // Hentikan eksekusi jika file konfigurasi penting tidak ada
    die("Error Kritis: File konfigurasi R2 (r2_config.php) tidak ditemukan. Periksa path: " . realpath(__DIR__ . '/../config/'));
}
$r2Config = require $r2ConfigPath;
// Menggunakan class S3Client dari AWS SDK
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
// --- Akhir Integrasi R2 ---

// --- Koneksi Database ---
include "../config/koneksi.php"; // Path: htdocs/config/koneksi.php (Harap periksa isinya)
// Membuat koneksi menggunakan mysqli
$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) {
    // Hentikan eksekusi jika koneksi DB gagal
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4'); // Disarankan untuk mendukung berbagai karakter

// --- Fungsi Helper Upload ke R2 ---
/**
 * Mengupload file ke Cloudflare R2.
 * Fungsi ini menangani koneksi ke R2, pembuatan nama file unik,
 * dan mengembalikan URL publik jika upload berhasil.
 *
 * @param array $fileInfo Data file dari array $_FILES (misal: $_FILES['bukti_pembayaran']).
 * @param string $targetFolder Nama folder tujuan di dalam bucket R2 (misal: 'bukti_pembayaran').
 * @param array $r2Config Array berisi kredensial dan detail R2 dari file config.
 * @return string|false URL publik file jika berhasil, atau false jika terjadi error.
 */
function uploadToR2(array $fileInfo, string $targetFolder, array $r2Config): string|false
{
    // Cek error upload dasar dari PHP
    if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
        error_log("Error upload PHP code " . $fileInfo['error'] . " for file: " . $fileInfo['name']);
        return false; // Gagal jika ada error PHP
    }

    // Validasi kelengkapan konfigurasi R2
    if (empty($r2Config['accountId']) || empty($r2Config['accessKeyId']) || empty($r2Config['secretAccessKey']) || empty($r2Config['bucketName']) || empty($r2Config['publicUrlBase'])) {
        error_log("Konfigurasi R2 tidak lengkap dalam file r2_config.php.");
        return false; // Gagal jika config tidak lengkap
    }

    // Ambil detail konfigurasi
    $accountId = $r2Config['accountId'];
    $accessKeyId = $r2Config['accessKeyId'];
    $secretAccessKey = $r2Config['secretAccessKey'];
    $bucketName = $r2Config['bucketName'];
    $publicUrlBase = rtrim($r2Config['publicUrlBase'], '/'); // Pastikan URL dasar tidak ada slash di akhir

    // Tentukan endpoint R2 (format spesifik Cloudflare)
    $endpoint = "https://{$accountId}.r2.cloudflarestorage.com";

    // Buat nama file yang aman dan unik di R2
    $fileExtension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
    $baseName = basename($fileInfo['name'], "." . $fileExtension); // Nama file asli tanpa ekstensi
    $safeBaseName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $baseName); // Bersihkan nama file dari karakter aneh
    // Gabungkan folder target, nama bersih, ID unik, dan ekstensi
    $key = rtrim($targetFolder, '/') . '/' . $safeBaseName . '_' . uniqid() . '.' . $fileExtension;

    try {
        // Inisialisasi S3 Client yang diarahkan ke R2
        $s3Client = new S3Client([
            'version'     => 'latest',          // Gunakan versi SDK terbaru
            'region'      => 'auto',            // R2 tidak butuh region spesifik, 'auto' biasanya cukup
            'endpoint'    => $endpoint,         // Endpoint R2 yang sudah dibuat
            'credentials' => [                  // Kredensial dari file config
                'key'    => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
            'use_path_style_endpoint' => true // Penting agar SDK bisa bekerja dengan endpoint custom R2
        ]);

        // Lakukan proses upload file
        $result = $s3Client->putObject([
            'Bucket'     => $bucketName,           // Nama bucket tujuan
            'Key'        => $key,                  // Nama file unik yang sudah dibuat (termasuk folder)
            'SourceFile' => $fileInfo['tmp_name'], // Path file sementara hasil upload PHP di server
            'ACL'        => 'public-read'          // Atur agar file bisa diakses publik melalui URL R2.dev
        ]);

        // Kembalikan URL publik lengkap jika berhasil
        return $publicUrlBase . '/' . $key;
    } catch (AwsException $e) {
        // Tangani error spesifik dari AWS SDK (termasuk error koneksi, permission, dll)
        error_log("Gagal upload ke R2 (AWS SDK Exception): [Bucket: {$bucketName}, Key: {$key}] Pesan: " . $e->getMessage() . " AWS Error Code: " . $e->getAwsErrorCode());
        return false; // Gagal
    } catch (Exception $e) {
        // Tangani error umum lainnya (misal: gagal inisialisasi S3Client)
        error_log("Error S3 Client saat upload ke R2: [Bucket: {$bucketName}, Key: {$key}] Pesan: " . $e->getMessage());
        return false; // Gagal
    }
}
// --- Akhir Fungsi Helper Upload R2 ---

// --- Fungsi Helper Hapus dari R2 ---
/**
 * Menghapus file dari Cloudflare R2 berdasarkan URL publiknya.
 *
 * @param string $publicUrl URL publik file R2 yang akan dihapus.
 * @param array $r2Config Array berisi kredensial dan detail R2.
 * @return bool True jika berhasil dihapus atau URL tidak valid (tidak perlu dihapus), false jika gagal menghapus dari R2.
 */
function deleteFromR2(string $publicUrl, array $r2Config): bool
{
    // Validasi URL dan kelengkapan config
    if (empty($publicUrl) || !filter_var($publicUrl, FILTER_VALIDATE_URL)) {
        error_log("URL tidak valid atau kosong saat mencoba hapus dari R2: " . $publicUrl);
        return true; // Anggap sukses jika URL tidak valid (tidak ada yang perlu dihapus)
    }
    if (empty($r2Config['accountId']) || empty($r2Config['accessKeyId']) || empty($r2Config['secretAccessKey']) || empty($r2Config['bucketName'])) {
        error_log("Konfigurasi R2 tidak lengkap saat mencoba hapus file.");
        return false; // Gagal karena config tidak lengkap
    }

    // Ambil detail konfigurasi
    $accountId = $r2Config['accountId'];
    $accessKeyId = $r2Config['accessKeyId'];
    $secretAccessKey = $r2Config['secretAccessKey'];
    $bucketName = $r2Config['bucketName'];

    // Ekstrak 'Key' (path/nama file di dalam bucket) dari URL
    $urlParts = parse_url($publicUrl);
    // Hapus slash di awal path (jika ada) untuk mendapatkan Key S3/R2
    $r2KeyToDelete = ltrim($urlParts['path'] ?? '', '/');

    if (empty($r2KeyToDelete)) {
        error_log("Gagal mengekstrak R2 Key dari URL untuk dihapus: " . $publicUrl);
        return false; // Gagal jika Key tidak bisa didapatkan dari URL
    }

    try {
        // Tentukan endpoint R2
        $endpoint = "https://{$accountId}.r2.cloudflarestorage.com";

        // Inisialisasi S3 Client
        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => 'auto',
            'endpoint'    => $endpoint,
            'credentials' => ['key' => $accessKeyId, 'secret' => $secretAccessKey],
            'use_path_style_endpoint' => true
        ]);

        // Hapus objek/file dari R2
        $s3Client->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $r2KeyToDelete, // Key yang sudah diekstrak
        ]);
        error_log("Berhasil menghapus file dari R2: " . $r2KeyToDelete);
        return true; // Sukses

    } catch (AwsException $e) {
        // Log error AWS SDK
        error_log("Gagal menghapus file dari R2 (AWS SDK Exception): [Bucket: {$bucketName}, Key: {$r2KeyToDelete}] Pesan: " . $e->getMessage());
        return false; // Gagal
    } catch (Exception $e) {
        // Log error umum
        error_log("Error S3 Client saat hapus R2: [Bucket: {$bucketName}, Key: {$r2KeyToDelete}] Pesan: " . $e->getMessage());
        return false; // Gagal
    }
}
// --- Akhir Fungsi Helper Hapus R2 ---


// --- Router Utama ---
// Ambil parameter 'menu' dan 'act' dari URL (GET) dengan sanitasi dasar
$menu = isset($_GET['menu']) ? htmlspecialchars($_GET['menu'], ENT_QUOTES, 'UTF-8') : '';
$act = isset($_GET['act']) ? htmlspecialchars($_GET['act'], ENT_QUOTES, 'UTF-8') : '';

// Gunakan struktur switch-case untuk mengarahkan request ke logika yang sesuai
switch ($menu) {

    /**
     * =================================================================
     * Menu: Aktivitas Data (Pengecekan Kualitas Air, dll)
     * =================================================================
     * Tidak melibatkan upload file.
     */
    case 'aktivitasdata':
        switch ($act) {
            case 'input': // Aksi untuk menambah data aktivitas baru
                $tgl_aktivitas_otomatis = date('Y-m-d H:i:s'); // Waktu saat ini
                // Ambil data dari form POST
                $jenis_pengecekan = $_POST['jenis_pengecekan'] ?? '';
                $hasil = $_POST['hasil'] ?? '';
                $keterangan = $_POST['keterangan'] ?? '';
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0; // Pastikan integer

                // Validasi input wajib
                if (empty($jenis_pengecekan) || $id_user <= 0) {
                    echo "Data wajib (Jenis Pengecekan, ID User) tidak boleh kosong.<br><a href='javascript:history.back()'>Kembali</a>";
                    exit;
                }
                // Query INSERT menggunakan prepared statement
                $sql = "INSERT INTO data_aktivitas (tgl_aktivitas, jenis_pengecekan, hasil, keterangan, id_user) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $tgl_aktivitas_otomatis, $jenis_pengecekan, $hasil, $keterangan, $id_user);
                if ($stmt->execute()) {
                    header('Location: index.php?menu=aktivitasdata'); // Redirect ke daftar aktivitas jika sukses
                } else {
                    error_log("Gagal insert aktivitas: " . $stmt->error); // Catat error di log
                    echo "Terjadi error saat menyimpan data aktivitas."; // Pesan umum ke user
                }
                $stmt->close();
                break;

            case 'update': // Aksi untuk memperbarui data aktivitas
                // Ambil data dari form POST
                $id_aktivitas = isset($_POST['id_aktivitas']) ? (int)$_POST['id_aktivitas'] : 0;
                $jenis_pengecekan = $_POST['jenis_pengecekan'] ?? '';
                $hasil = $_POST['hasil'] ?? '';
                $keterangan = $_POST['keterangan'] ?? '';

                // Validasi input wajib
                if ($id_aktivitas <= 0 || empty($jenis_pengecekan)) {
                    echo "Data wajib (ID Aktivitas, Jenis Pengecekan) tidak boleh kosong.<br><a href='javascript:history.back()'>Kembali</a>";
                    exit;
                }
                // Query UPDATE menggunakan prepared statement
                $sql = "UPDATE data_aktivitas SET jenis_pengecekan = ?, hasil = ?, keterangan = ? WHERE id_aktivitas = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $jenis_pengecekan, $hasil, $keterangan, $id_aktivitas);
                if ($stmt->execute()) {
                    header('Location: index.php?menu=aktivitasdata'); // Redirect jika sukses
                } else {
                    error_log("Gagal update aktivitas ID {$id_aktivitas}: " . $stmt->error);
                    echo "Terjadi error saat memperbarui data aktivitas.";
                }
                $stmt->close();
                break;

            case 'batal': // Aksi untuk menghapus data aktivitas (nama 'batal' mungkin kurang tepat?)
                $id_aktivitas = isset($_GET['id_aktivitas']) ? (int)$_GET['id_aktivitas'] : 0;
                if ($id_aktivitas <= 0) {
                    echo "ID Aktivitas tidak valid.";
                    exit;
                }
                // Query DELETE menggunakan prepared statement
                $sql = "DELETE FROM data_aktivitas WHERE id_aktivitas = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id_aktivitas);
                if ($stmt->execute()) {
                    header('Location: index.php?menu=aktivitasdata'); // Redirect jika sukses
                } else {
                    error_log("Gagal hapus aktivitas ID {$id_aktivitas}: " . $stmt->error);
                    echo "Terjadi error saat menghapus data aktivitas.";
                }
                $stmt->close();
                break;
        }
        break; // Akhir case 'aktivitasdata'

    /**
         * =================================================================
         * Menu: Admin (Manajemen User/Admin)
         * =================================================================
         * Tidak melibatkan upload file. Penting: Gunakan hashing password!
         */
    case 'admin':
        switch ($act) {
            case 'input': // Aksi tambah admin/user baru
                $user_login = mysqli_real_escape_string($conn, $_POST['user_login']);
                $password = mysqli_real_escape_string($conn, $_POST['password']);
                $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                $level = mysqli_real_escape_string($conn, $_POST['level']);

                // --- PENTING: Hash password sebelum disimpan! ---
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if ($hashed_password === false) {
                    error_log("Password hashing failed for user: " . $user_login);
                    die("Gagal memproses password."); // Berhenti jika hashing gagal
                }

                // Query INSERT menggunakan prepared statement
                $sql_insert = "INSERT INTO user(user_login, password, nama, level) VALUES(?, ?, ?, ?)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bind_param("ssss", $user_login, $hashed_password, $nama, $level);
                if (!$stmt->execute()) {
                    error_log("Gagal insert user: " . $stmt->error);
                    // Tampilkan pesan error jika perlu, tapi jangan tampilkan detail SQL
                }
                $stmt->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;

            case 'update': // Aksi update data admin/user
                $user_login_old = mysqli_real_escape_string($conn, $_POST['user_login']); // ID lama
                $user_login_new = mysqli_real_escape_string($conn, $_POST['user_login1']); // Username baru
                $nama = mysqli_real_escape_string($conn, $_POST['nama']);
                $level = mysqli_real_escape_string($conn, $_POST['level']);

                // Query UPDATE menggunakan prepared statement
                $sql_update = "UPDATE user SET user_login=?, nama=?, level=? WHERE user_login=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("ssss", $user_login_new, $nama, $level, $user_login_old);
                if (!$stmt->execute()) {
                    error_log("Gagal update user {$user_login_old}: " . $stmt->error);
                }
                $stmt->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;

            case 'batal': // Aksi hapus admin/user (nama 'batal'?)
                $user_login = mysqli_real_escape_string($conn, $_GET['user_login']);
                // Query DELETE menggunakan prepared statement
                $sql_delete = "DELETE FROM user WHERE user_login=?";
                $stmt = $conn->prepare($sql_delete);
                $stmt->bind_param("s", $user_login);
                if (!$stmt->execute()) {
                    error_log("Gagal hapus user {$user_login}: " . $stmt->error);
                }
                $stmt->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;
        }
        break; // Akhir case 'admin'

    /**
         * =================================================================
         * Menu: Member (Manajemen Pelanggan)
         * =================================================================
         * Tidak melibatkan upload file.
         */
    case 'member':
        switch ($act) {
            case 'input': // Aksi tambah member baru
                // Ambil data & sanitasi
                $nama_plng = mysqli_real_escape_string($conn, $_POST['nama_plng']);
                $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
                $lat = mysqli_real_escape_string($conn, $_POST['lat']);
                $lng = mysqli_real_escape_string($conn, $_POST['lng']);
                $username_login = $_POST['username_login'] ?? '';
                $password_hash_kirim = $_POST['password_hash'] ?? '';
                if (empty($username_login) || empty($password_hash_kirim)) {
                echo "<script> alert('Error: Akun login (username/password) belum di-generate. Klik tombol Generate Akun.'); window.history.back(); </script>";
                exit();
            }

                // Cek duplikasi nomor telepon menggunakan prepared statement
                $sql_cek = "SELECT id_plng FROM data_plng WHERE no_telp = ?";
                $stmt_cek = mysqli_prepare($conn, $sql_cek);
                mysqli_stmt_bind_param($stmt_cek, "s", $no_telp);
                mysqli_stmt_execute($stmt_cek);
                $result_cek = mysqli_stmt_get_result($stmt_cek);
                if (mysqli_num_rows($result_cek) > 0) {
                    $errorMessage = "Gagal: Nomor telepon " . htmlspecialchars($no_telp) . " sudah terdaftar.";
                    echo "<script> alert('{$errorMessage}'); window.history.back(); </script>";
                    exit();
                }
                mysqli_stmt_close($stmt_cek);

                // Insert data baru menggunakan prepared statement (asumsi id_plng auto increment)
                $sql_insert = "INSERT INTO data_plng(nama_plng, alamat, no_telp, lat, lng, username_login, password) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)"; // Tambah 2 kolom
            $stmt_insert = $conn->prepare($sql_insert);
            // Tambah 2 parameter ('s' untuk username, 's' untuk password hash)
            $stmt_insert->bind_param("sssssss", $nama_plng, $alamat, $no_telp, $lat, $lng, $username_login, $password_hash_kirim);
                if (!$stmt_insert->execute()) {
                    error_log("Gagal insert member: " . $stmt_insert->error);
                }
                $stmt_insert->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;

            case 'update': // Aksi update data member
    // Ambil data dari POST (tidak perlu escape string, kita akan pakai bind_param)
    $id_plng = $_POST['id_plng'] ?? 0;
    $nama_plng = $_POST['nama_plng'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $no_telp = $_POST['no_telp'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';
    
    // Ambil data akun
    $username_login = $_POST['username_login'] ?? null;
    $new_password_hash = $_POST['new_password_hash'] ?? null;
    $action_akun = $_POST['action_akun'] ?? '';

    // Validasi ID
    if ($id_plng <= 0) {
        die("ID Pelanggan tidak valid.");
    }

    // Cek duplikasi nomor telepon (kecuali untuk ID yang sedang diedit)
    $sql_cek = "SELECT id_plng FROM data_plng WHERE no_telp = ? AND id_plng != ?";
    $stmt_cek = mysqli_prepare($conn, $sql_cek);
    mysqli_stmt_bind_param($stmt_cek, "si", $no_telp, $id_plng); // "s" untuk string no_telp, "i" untuk integer id_plng
    mysqli_stmt_execute($stmt_cek);
    $result_cek = mysqli_stmt_get_result($stmt_cek);
    if (mysqli_num_rows($result_cek) > 0) {
        $errorMessage = "Gagal: Nomor telepon " . htmlspecialchars($no_telp) . " sudah terdaftar untuk pelanggan lain.";
        echo "<script> alert('{$errorMessage}'); window.history.back(); </script>";
        exit();
    }
    mysqli_stmt_close($stmt_cek);

    // --- LOGIKA UPDATE DINAMIS ---
    
    // 1. Siapkan query dasar dan parameter dasar
    $sql_update_base = "UPDATE data_plng SET nama_plng=?, alamat=?, no_telp=?, lat=?, lng=?";
    $params = [$nama_plng, $alamat, $no_telp, $lat, $lng];
    $types = "sssss"; // 5 tipe data string

    // 2. Cek apakah ada aksi untuk akun
    if ($action_akun === 'create' && !empty($username_login) && !empty($new_password_hash)) {
        // Jika AKUN BARU DIBUAT: tambahkan username dan password ke query
        $sql_update_base .= ", username_login = ?, password = ?";
        $types .= "ss"; // Tambah 2 string
        $params[] = $username_login;
        $params[] = $new_password_hash;
        
    } else if ($action_akun === 'reset' && !empty($new_password_hash)) {
        // Jika HANYA RESET PASSWORD: tambahkan password saja ke query
        $sql_update_base .= ", password = ?";
        $types .= "s"; // Tambah 1 string
        $params[] = $new_password_hash;
    }
    // Jika $action_akun kosong, tidak ada SQL tambahan, hanya data diri yang di-update.

    // 3. Tambahkan WHERE clause di akhir
    $sql_update_base .= " WHERE id_plng = ?";
    $types .= "i"; // Tambah 1 integer
    $params[] = $id_plng; // Tambah nilai ID

    // 4. Eksekusi query dinamis
    $stmt = $conn->prepare($sql_update_base);
    // Gunakan 'splat operator' (...) untuk memasukkan array $params ke bind_param
    $stmt->bind_param($types, ...$params); 
    
    if (!$stmt->execute()) {
        error_log("Gagal update member ID {$id_plng}: " . $stmt->error);
        // Tampilkan error jika perlu (opsional)
        // echo "Error: " . $stmt->error; 
    }
    $stmt->close();
    header('location:index.php?menu=' . $menu);
    exit();
    break;

            case 'batal': // Aksi hapus member (nama 'batal'?)
                $id_plng = isset($_GET['id_plng']) ? (int)$_GET['id_plng'] : 0;
                if ($id_plng <= 0) {
                    die("ID Pelanggan tidak valid.");
                }
                // Query DELETE menggunakan prepared statement
                $sql_delete = "DELETE FROM data_plng WHERE id_plng=?";
                $stmt = $conn->prepare($sql_delete);
                $stmt->bind_param("i", $id_plng);
                if (!$stmt->execute()) {
                    error_log("Gagal hapus member ID {$id_plng}: " . $stmt->error);
                }
                $stmt->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;
        }
        break; // Akhir case 'member'

    /**
         * =================================================================
         * Menu: Produk (Manajemen Stok Produk)
         * =================================================================
         * Tidak melibatkan upload file.
         */
    case 'produk':
        // Cek level. Ambil $level_lowercase dari $conn atau session
        // Pastikan $conn ada di sini
        if (session_status() == PHP_SESSION_NONE) session_start();
        $level_lowercase = strtolower($_SESSION['level'] ?? '');
        $current_user_id = $_SESSION['id_user'] ?? null;

        switch ($act) {
            // --- HANYA ADMIN ---
            case 'input':
                if ($level_lowercase != 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>"; exit;
                }
                
                $nama_produk = $_POST['nama_produk'] ?? '';
                $jenis_produk = $_POST['jenis_produk'] ?? '';
                $harga = isset($_POST['harga']) ? (float)$_POST['harga'] : 0;
                $stock_awal = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;

                if (empty($nama_produk) || empty($jenis_produk)) {
                    echo "<script>alert('Nama dan Jenis produk wajib diisi.'); window.history.back();</script>"; exit;
                }

                $conn->begin_transaction();
                try {
                    // 1. Insert produk baru
                    $sql_insert = "INSERT INTO produk (nama_produk, jenis_produk, stock, harga) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if (!$stmt_insert) throw new Exception("Prepare insert produk gagal: " . $conn->error);
                    $stmt_insert->bind_param("sssd", $nama_produk, $jenis_produk, $stock_awal, $harga);
                    if (!$stmt_insert->execute()) throw new Exception("Execute insert produk gagal: " . $stmt_insert->error);
                    $id_produk_baru = $conn->insert_id;
                    $stmt_insert->close();

                    // 2. Catat stok awal ke histori
                    if ($stock_awal > 0) {
                        $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update)
                                        VALUES (?, ?, 0, ?, ?, NOW())";
                        $stmt_histori = $conn->prepare($sql_histori);
                        if (!$stmt_histori) throw new Exception("Prepare insert histori gagal: " . $conn->error);
                        $stmt_histori->bind_param("iiii", $id_produk_baru, $stock_awal, $stock_awal, $current_user_id);
                        if (!$stmt_histori->execute()) throw new Exception("Execute insert histori gagal: " . $stmt_histori->error);
                        $stmt_histori->close();
                    }
                    
                    $conn->commit();
                    header('Location: index.php?menu=produk');

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error input produk: " . $e->getMessage());
                    echo "<script>alert('Gagal menyimpan produk: ".addslashes($e->getMessage())."'); window.history.back();</script>";
                }
                exit;
                break; // Akhir case input

            // --- HANYA ADMIN ---
            case 'update':
                if ($level_lowercase != 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>"; exit;
                }

                $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $nama_produk = $_POST['nama_produk'] ?? '';
                $jenis_produk = $_POST['jenis_produk'] ?? '';
                $harga_baru = isset($_POST['harga']) ? (float)$_POST['harga'] : 0;
                $stock_baru = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;

                if ($id_produk <= 0 || empty($nama_produk) || empty($jenis_produk)) {
                    echo "<script>alert('Data tidak lengkap.'); window.history.back();</script>"; exit;
                }
                if ($id_produk == 4) { // Proteksi ID 4
                     echo "<script>alert('Produk ID 4 (Galon) tidak boleh diedit manual.'); window.history.back();</script>"; exit;
                }

                $conn->begin_transaction();
                try {
                    // 1. Ambil stok lama
                    $stok_sebelum = 0;
                    $sql_get = "SELECT stock FROM produk WHERE id = ? FOR UPDATE";
                    $stmt_get = $conn->prepare($sql_get);
                    if (!$stmt_get) throw new Exception("Prepare get stok lama gagal: " . $conn->error);
                    $stmt_get->bind_param("i", $id_produk);
                    $stmt_get->execute();
                    $result_get = $stmt_get->get_result();
                    if ($row_get = $result_get->fetch_assoc()) {
                        $stok_sebelum = (int)$row_get['stock'];
                    } else {
                        throw new Exception("Produk tidak ditemukan.");
                    }
                    $stmt_get->close();

                    // 2. Update produk
                    $sql_update = "UPDATE produk SET nama_produk = ?, jenis_produk = ?, stock = ?, harga = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare update produk gagal: " . $conn->error);
                    $stmt_update->bind_param("ssidi", $nama_produk, $jenis_produk, $stock_baru, $harga_baru, $id_produk);
                    if (!$stmt_update->execute()) throw new Exception("Execute update produk gagal: " . $stmt_update->error);
                    $stmt_update->close();

                    // 3. Catat ke histori HANYA JIKA stok berubah
                    $selisih_stok = $stock_baru - $stok_sebelum;
                    if ($selisih_stok != 0) {
                        $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update)
                                        VALUES (?, ?, ?, ?, ?, NOW())";
                        $stmt_histori = $conn->prepare($sql_histori);
                        if (!$stmt_histori) throw new Exception("Prepare insert histori (update) gagal: " . $conn->error);
                        $stmt_histori->bind_param("iiiii", $id_produk, $selisih_stok, $stok_sebelum, $stock_baru, $current_user_id);
                        if (!$stmt_histori->execute()) throw new Exception("Execute insert histori (update) gagal: " . $stmt_histori->error);
                        $stmt_histori->close();
                    }

                    $conn->commit();
                    header('Location: index.php?menu=produk');

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error update produk: " . $e->getMessage());
                    echo "<script>alert('Gagal update produk: ".addslashes($e->getMessage())."'); window.history.back();</script>";
                }
                exit;
                break; // Akhir case update

            // --- HANYA ADMIN ---
            case 'batal':
                if ($level_lowercase != 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                $id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
                if ($id_produk <= 0) {
                     echo "<script>alert('ID Produk tidak valid.'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                if ($id_produk == 4 || $id_produk == 5) { // Proteksi ID 4 (Galon) dan 5 (Tutup)
                     echo "<script>alert('Produk inti (ID 4 atau 5) tidak boleh dihapus.'); window.location.href='index.php?menu=produk';</script>"; exit;
                }

                // Perlu cek foreign key (di pesanan / histori) sebelum hapus
                // Untuk saat ini, kita coba hapus langsung
                $sql_delete = "DELETE FROM produk WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                if (!$stmt_delete) {
                     echo "<script>alert('Gagal prepare delete: ".$conn->error."'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                $stmt_delete->bind_param("i", $id_produk);

                if ($stmt_delete->execute()) {
                    header('Location: index.php?menu=produk');
                } else {
                     // Kemungkinan gagal karena foreign key constraint (produk sudah pernah dipesan)
                     echo "<script>alert('Gagal menghapus produk. Mungkin produk ini sudah pernah digunakan dalam pesanan atau histori.'); window.location.href='index.php?menu=produk';</script>";
                }
                $stmt_delete->close();
                exit;
                break; // Akhir case batal

            // --- ADMIN & KASIR ---
            case 'tambah_stok':
                // Boleh diakses admin atau kasir
                if ($level_lowercase != 'admin' && $level_lowercase != 'kasir') {
                echo "<script>alert('Akses Ditolak!'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $jumlah_tambah = isset($_POST['jumlah_tambah']) ? (int)$_POST['jumlah_tambah'] : 0;
                // Ambil harga pembelian yang dikirim dari form
                $harga_pembelian = isset($_POST['harga_pembelian']) ? (int)$_POST['harga_pembelian'] : 0;
                $jenis_transaksi = $_POST['jenis_transaksi'] ?? '';
                
                if ($id_produk <= 0 || $jumlah_tambah <= 0) {
                    echo "<script>alert('ID Produk atau Jumlah tidak valid.'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                if ($id_produk == 4) { // Proteksi ID 4
                    echo "<script>alert('Stok Galon (ID 4) tidak boleh ditambah manual.'); window.location.href='index.php?menu=produk';</script>"; exit;
                }
                
                // Menggunakan kolom id_produk yang lebih umum
                $conn->begin_transaction();
                try {  
                    // 1. Ambil stok lama (Menggunakan id_produk)
                    $stok_sebelum = 0;
                    // Gunakan `id_produk` di sini, dan pastikan di DB Anda namanya `id_produk` atau `id`
                    $sql_get = "SELECT stock FROM produk WHERE id_produk = ? FOR UPDATE";
                    $stmt_get = $conn->prepare($sql_get);
                    if (!$stmt_get) throw new Exception("Prepare get stok gagal: " . $conn->error);
                    $stmt_get->bind_param("i", $id_produk);
                    $stmt_get->execute();
                    $result_get = $stmt_get->get_result();
                    if ($row_get = $result_get->fetch_assoc()) {
                        $stok_sebelum = (int)$row_get['stock'];
                    } else {
                        throw new Exception("Produk tidak ditemukan.");
                    }
                    $stmt_get->close();
                    
                    $stok_sesudah = $stok_sebelum + $jumlah_tambah;
                    
                    // 2. Update stok produk (Menggunakan id_produk)
                    // Tambahkan updated_at untuk pencatatan waktu update stok.
                    $sql_update = "UPDATE produk SET stock = ? WHERE id_produk = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare update stok gagal: " . $conn->error);
                    $stmt_update->bind_param("ii", $stok_sesudah, $id_produk);
                    if (!$stmt_update->execute()) throw new Exception("Execute update stok gagal: " . $stmt_update->error);
                    $stmt_update->close();
                    
                    // 3. Masukkan ke histori (Ditambahkan kolom harga_pembelian_unit)
                    $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, harga_pembelian_unit, id_user, waktu_update, jenis_transaksi)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
                    $stmt_histori = $conn->prepare($sql_histori);
                    if (!$stmt_histori) throw new Exception("Prepare insert histori gagal: " . $conn->error);
        
                    // Perhatikan urutan dan jenis parameter: i i i i i i (integer, integer, integer, integer, integer, integer)
                    $stmt_histori->bind_param("iiiiiis", 
                    $id_produk, 
        
                    $jumlah_tambah, 
                    $stok_sebelum, 
                    $stok_sesudah, 
                    $harga_pembelian, // <-- Harga Pembelian ditambahkan di sini
                    $current_user_id,
                    $jenis_transaksi
                    ); 
                    if (!$stmt_histori->execute()) throw new Exception("Execute insert histori gagal: " . $stmt_histori->error);
                    $stmt_histori->close();
                    
                    $conn->commit();
                    echo "<script>alert('Stok berhasil ditambahkan!'); window.location.href='index.php?menu=produk';</script>";
                    exit;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error tambah stok: " . $e->getMessage());
                    // Arahkan kembali ke halaman produk agar URL bersih
                    echo "<script>alert('Gagal menambah stok: ".addslashes($e->getMessage())."'); window.location.href='index.php?menu=produk';</script>";
                }
                exit;
                break; // Akhir case tambah_stok
            }
                
            
            
    
    /**
         * =================================================================
         * Menu: Pemesanan (Input, Update, Batal, Pelunasan)
         * =================================================================
         * Melibatkan upload bukti pembayaran ke R2 pada aksi 'update_pembayaran'.
         */
    case 'pemesanan':
        switch ($act) {
            case 'input': // Aksi menambah pesanan baru
                // ... (Logika input pesanan seperti sebelumnya, tidak ada upload file di sini) ...
                $id_plng = $_POST['id_plng'] ?? null;
                $nama_plng = $_POST['nama_plng'] ?? null;
                $no_telp = $_POST['no_telp'] ?? null;
                $tglpesan = $_POST['tgl_pesan'] ?? null;
                $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $nama_produk = $_POST['nama_produk'] ?? '';
                $jumlah = isset($_POST['galon']) ? (int)$_POST['galon'] : 0;
                $status_antar = $_POST['status_antar'] ?? 'Diproses';
                $status_bayar = $_POST['status_bayar'] ?? 'Belum Lunas';
                if (empty($id_plng) || empty($no_telp) || empty($tglpesan) || $id_produk <= 0 || empty($nama_produk) || $jumlah <= 0) {
                    echo "<script>alert('Error: Data pelanggan, tanggal, produk, atau jumlah tidak valid.'); window.history.back();</script>";
                    exit;
                }
                $harga_produk = 0;
                $stok_sekarang = 0;
                $sql_produk_info = "SELECT harga, stock FROM produk WHERE id = ?";
                $stmt_produk_info = $conn->prepare($sql_produk_info);
                if (!$stmt_produk_info) {
                    error_log("Error prepare produk info: " . $conn->error);
                    echo "<script>alert('Error: Gagal memeriksa info produk.'); window.history.back();</script>";
                    exit;
                }
                $stmt_produk_info->bind_param("i", $id_produk);
                $stmt_produk_info->execute();
                $result_produk_info = $stmt_produk_info->get_result();
                if ($row_produk = $result_produk_info->fetch_assoc()) {
                    $harga_produk = (float)$row_produk['harga'];
                    $stok_sekarang = (int)$row_produk['stock'];
                } else {
                    $stmt_produk_info->close();
                    echo "<script>alert('Error: Produk dengan ID {$id_produk} tidak ditemukan.'); window.history.back();</script>";
                    exit;
                }
                $stmt_produk_info->close();
                if ($jumlah > $stok_sekarang) {
                    echo "<script>alert('Error: Stok produk \\'" . htmlspecialchars($nama_produk, ENT_QUOTES) . "\\' tidak mencukupi ({$stok_sekarang} tersedia).'); window.history.back();</script>";
                    exit;
                }
                if ($id_produk == 4) {
                    $sql_cek_tutup = "SELECT stock FROM produk WHERE id = 5";
                    $result_tutup = $conn->query($sql_cek_tutup);
                    $stok_tutup = $result_tutup->fetch_assoc()['stock'] ?? 0;
                    if ($jumlah > $stok_tutup) {
                        echo "<script>alert('Error: Stok Tutup Galon (ID 5) tidak mencukupi ({$stok_tutup} tersedia) untuk pesanan {$jumlah} galon.'); window.history.back();</script>";
                        exit;
                    }
                }
                $total_tagihan = $jumlah * $harga_produk;
                $conn->begin_transaction();
                try {
                    $sql_pesanan = "INSERT INTO pesanan(id_plng, nama_plng, no_telp, id_produk, nama_produk, galon, tgl_pesan) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_pesanan = $conn->prepare($sql_pesanan);
                    if (!$stmt_pesanan) throw new Exception("Prepare insert pesanan gagal: " . $conn->error);
                    $stmt_pesanan->bind_param("sssisss", $id_plng, $nama_plng, $no_telp, $id_produk, $nama_produk, $jumlah, $tglpesan);
                    if (!$stmt_pesanan->execute()) throw new Exception("Execute insert pesanan gagal: " . $stmt_pesanan->error);
                    $id_pesanan_baru = $conn->insert_id;
                    $stmt_pesanan->close();
                    $sql_update_stok = "UPDATE produk SET stock = stock - ? WHERE id = ?";
                    $stmt_stok = $conn->prepare($sql_update_stok);
                    if (!$stmt_stok) throw new Exception("Prepare update stok produk gagal: " . $conn->error);
                    $stmt_stok->bind_param("ii", $jumlah, $id_produk);
                    if (!$stmt_stok->execute()) throw new Exception("Execute update stok produk gagal: " . $stmt_stok->error);
                    $stmt_stok->close();
                    if ($id_produk == 4) {
                        $sql_update_tutup = "UPDATE produk SET stock = stock - ? WHERE id = 5";
                        $stmt_tutup = $conn->prepare($sql_update_tutup);
                        if (!$stmt_tutup) throw new Exception("Prepare update stok tutup gagal: " . $conn->error);
                        $stmt_tutup->bind_param("i", $jumlah);
                        if (!$stmt_tutup->execute()) throw new Exception("Execute update stok tutup gagal: " . $stmt_tutup->error);
                        $stmt_tutup->close();
                    }
                    $sql_pengantaran = "INSERT INTO pengantaran (id_pesanan, status_pengantaran) VALUES (?, ?)";
                    $stmt_pengantaran = $conn->prepare($sql_pengantaran);
                    if (!$stmt_pengantaran) throw new Exception("Prepare insert pengantaran gagal: " . $conn->error);
                    $stmt_pengantaran->bind_param("is", $id_pesanan_baru, $status_antar);
                    if (!$stmt_pengantaran->execute()) throw new Exception("Execute insert pengantaran gagal: " . $stmt_pengantaran->error);
                    $stmt_pengantaran->close();
                    $sql_pembayaran = "INSERT INTO pembayaran (id_pesanan, jumlah_pembayaran, status_pembayaran) VALUES (?, ?, ?)";
                    $stmt_pembayaran = $conn->prepare($sql_pembayaran);
                    if (!$stmt_pembayaran) throw new Exception("Prepare insert pembayaran gagal: " . $conn->error);
                    $stmt_pembayaran->bind_param("ids", $id_pesanan_baru, $total_tagihan, $status_bayar);
                    if (!$stmt_pembayaran->execute()) throw new Exception("Execute insert pembayaran gagal: " . $stmt_pembayaran->error);
                    $stmt_pembayaran->close();
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Gagal input pesanan: " . $e->getMessage());
                    $error_message_js = str_replace(['"', "'", "\n", "\r"], ['\"', "\'", "\\n", "\\r"], $e->getMessage());
                    echo "<script>alert('Gagal menyimpan data pesanan: " . $error_message_js . "'); window.history.back();</script>";
                    exit;
                }
                $redirect_params = [];
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                foreach ($filters_to_check as $filter_key) if (!empty($_POST[$filter_key])) $redirect_params[$filter_key] = $_POST[$filter_key];
                $redirect_url = 'index.php?menu=pemesanan' . (!empty($redirect_params) ? '&' . http_build_query($redirect_params) : '');
                header('Location: ' . $redirect_url);
                exit();
                break;

            case 'update': // Aksi memperbarui pesanan
                // ... (Logika update pesanan seperti sebelumnya, tidak ada upload file di sini) ...
                $id_pesanan = isset($_POST['id_pesanan']) ? (int)$_POST['id_pesanan'] : 0;
                $id_plng = $_POST['id_plng'] ?? null;
                $nama_plng = $_POST['nama_plng'] ?? null;
                $no_telp = $_POST['no_telp'] ?? null;
                $tglpesan = $_POST['tgl_pesan'] ?? null;
                $id_produk_baru = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $nama_produk_baru = $_POST['nama_produk'] ?? '';
                $jumlah_baru = isset($_POST['galon']) ? (int)$_POST['galon'] : 0;
                $status_antar_baru = $_POST['status_antar'] ?? null;
                if ($id_pesanan <= 0 || empty($id_plng) || empty($no_telp) || empty($tglpesan) || $id_produk_baru <= 0 || empty($nama_produk_baru) || $jumlah_baru <= 0 || empty($status_antar_baru)) {
                    echo "<script>alert('Error: Semua data wajib diisi saat update.'); window.history.back();</script>";
                    exit;
                }
                $conn->begin_transaction();
                try {
                    $sql_get_old = "SELECT id_produk, galon as jumlah_lama FROM pesanan WHERE id_pesanan = ?";
                    $stmt_get = $conn->prepare($sql_get_old);
                    if (!$stmt_get) throw new Exception("Prepare get old data gagal: " . $conn->error);
                    $stmt_get->bind_param("i", $id_pesanan);
                    $stmt_get->execute();
                    $result_old = $stmt_get->get_result();
                    $data_lama = $result_old->fetch_assoc();
                    $stmt_get->close();
                    if (!$data_lama) {
                        throw new Exception("Pesanan lama tidak ditemukan.");
                    }
                    $id_produk_lama = (int)$data_lama['id_produk'];
                    $jumlah_lama = (int)$data_lama['jumlah_lama'];
                    $harga_produk_baru = 0;
                    if ($id_produk_lama != $id_produk_baru || $jumlah_lama != $jumlah_baru) {
                        if ($jumlah_lama > 0 && $id_produk_lama > 0) {
                            $sql_stok_lama = "UPDATE produk SET stock = stock + ? WHERE id = ?";
                            $stmt_stok_lama = $conn->prepare($sql_stok_lama);
                            if (!$stmt_stok_lama) throw new Exception("Prepare kembalikan stok gagal: " . $conn->error);
                            $stmt_stok_lama->bind_param("ii", $jumlah_lama, $id_produk_lama);
                            if (!$stmt_stok_lama->execute()) throw new Exception("Execute kembalikan stok gagal: " . $stmt_stok_lama->error);
                            $stmt_stok_lama->close();
                        }
                        $stok_produk_baru = 0;
                        $sql_cek_baru = "SELECT stock, harga FROM produk WHERE id = ?";
                        $stmt_cek_baru = $conn->prepare($sql_cek_baru);
                        if (!$stmt_cek_baru) throw new Exception("Prepare cek stok baru gagal: " . $conn->error);
                        $stmt_cek_baru->bind_param("i", $id_produk_baru);
                        $stmt_cek_baru->execute();
                        $result_cek_baru = $stmt_cek_baru->get_result();
                        if ($row_baru = $result_cek_baru->fetch_assoc()) {
                            $stok_produk_baru = (int)$row_baru['stock'];
                            $harga_produk_baru = (float)$row_baru['harga'];
                        } else {
                            throw new Exception("Produk baru (ID: {$id_produk_baru}) tidak ditemukan.");
                        }
                        $stmt_cek_baru->close();
                        if ($jumlah_baru > $stok_produk_baru) {
                            throw new Exception("Stok produk '" . htmlspecialchars($nama_produk_baru, ENT_QUOTES) . "' tidak mencukupi ({$stok_produk_baru} tersedia) untuk jumlah baru ({$jumlah_baru}).");
                        }
                        $sql_stok_baru = "UPDATE produk SET stock = stock - ? WHERE id = ?";
                        $stmt_stok_baru = $conn->prepare($sql_stok_baru);
                        if (!$stmt_stok_baru) throw new Exception("Prepare kurangi stok baru gagal: " . $conn->error);
                        $stmt_stok_baru->bind_param("ii", $jumlah_baru, $id_produk_baru);
                        if (!$stmt_stok_baru->execute()) throw new Exception("Execute kurangi stok baru gagal: " . $stmt_stok_baru->error);
                        $stmt_stok_baru->close();
                    } else {
                        $sql_harga = "SELECT harga FROM produk WHERE id = ?";
                        $stmt_harga = $conn->prepare($sql_harga);
                        if (!$stmt_harga) throw new Exception("Prepare ambil harga gagal: " . $conn->error);
                        $stmt_harga->bind_param("i", $id_produk_baru);
                        $stmt_harga->execute();
                        $result_harga = $stmt_harga->get_result();
                        $row_harga = $result_harga->fetch_assoc();
                        $harga_produk_baru = $row_harga ? (float)$row_harga['harga'] : 0;
                        $stmt_harga->close();
                    }
                    $selisih_tutup = 0;
                    if ($id_produk_lama == 4 && $id_produk_baru != 4) {
                        $selisih_tutup = -$jumlah_lama;
                    } elseif ($id_produk_lama != 4 && $id_produk_baru == 4) {
                        $selisih_tutup = $jumlah_baru;
                    } elseif ($id_produk_lama == 4 && $id_produk_baru == 4) {
                        $selisih_tutup = $jumlah_baru - $jumlah_lama;
                    }
                    if ($selisih_tutup != 0) {
                        if ($selisih_tutup > 0) {
                            $sql_cek_tutup_upd = "SELECT stock FROM produk WHERE id = 5 FOR UPDATE";
                            $result_tutup_upd = $conn->query($sql_cek_tutup_upd);
                            $stok_tutup_upd = $result_tutup_upd->fetch_assoc()['stock'] ?? 0;
                            if ($selisih_tutup > $stok_tutup_upd) {
                                throw new Exception("Stok Tutup Galon (ID 5) tidak mencukupi ({$stok_tutup_upd} tersedia) untuk perubahan pesanan.");
                            }
                        }
                        $sql_update_tutup_upd = "UPDATE produk SET stock = stock - ? WHERE id = 5";
                        $stmt_tutup_upd = $conn->prepare($sql_update_tutup_upd);
                        if (!$stmt_tutup_upd) throw new Exception("Prepare update stok tutup (update) gagal: " . $conn->error);
                        $stmt_tutup_upd->bind_param("i", $selisih_tutup);
                        if (!$stmt_tutup_upd->execute()) throw new Exception("Execute update stok tutup (update) gagal: " . $stmt_tutup_upd->error);
                        $stmt_tutup_upd->close();
                    }
                    $total_tagihan_baru = $jumlah_baru * $harga_produk_baru;
                    $sql_update_pesanan = "UPDATE pesanan SET id_plng=?, nama_plng=?, no_telp=?, id_produk=?, nama_produk=?, galon=?, tgl_pesan=? WHERE id_pesanan = ?";
                    $stmt_update_pesanan = $conn->prepare($sql_update_pesanan);
                    if (!$stmt_update_pesanan) throw new Exception("Prepare update pesanan gagal: " . $conn->error);
                    $stmt_update_pesanan->bind_param("sssisssi", $id_plng, $nama_plng, $no_telp, $id_produk_baru, $nama_produk_baru, $jumlah_baru, $tglpesan, $id_pesanan);
                    if (!$stmt_update_pesanan->execute()) throw new Exception("Execute update pesanan gagal: " . $stmt_update_pesanan->error);
                    $stmt_update_pesanan->close();
                    $sql_update_pengantaran = "UPDATE pengantaran SET status_pengantaran=? WHERE id_pesanan=?";
                    $stmt_update_pengantaran = $conn->prepare($sql_update_pengantaran);
                    if (!$stmt_update_pengantaran) throw new Exception("Prepare update pengantaran gagal: " . $conn->error);
                    $stmt_update_pengantaran->bind_param("si", $status_antar_baru, $id_pesanan);
                    if (!$stmt_update_pengantaran->execute()) throw new Exception("Execute update pengantaran gagal: " . $stmt_update_pengantaran->error);
                    $stmt_update_pengantaran->close();
                    $sql_update_pembayaran = "UPDATE pembayaran SET jumlah_pembayaran=? WHERE id_pesanan=?";
                    $stmt_update_pembayaran = $conn->prepare($sql_update_pembayaran);
                    if (!$stmt_update_pembayaran) throw new Exception("Prepare update pembayaran gagal: " . $conn->error);
                    $stmt_update_pembayaran->bind_param("di", $total_tagihan_baru, $id_pesanan);
                    if (!$stmt_update_pembayaran->execute()) throw new Exception("Execute update pembayaran gagal: " . $stmt_update_pembayaran->error);
                    $stmt_update_pembayaran->close();
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Gagal update pesanan ID {$id_pesanan}: " . $e->getMessage());
                    $error_message_js = str_replace(['"', "'", "\n", "\r"], ['\"', "\'", "\\n", "\\r"], $e->getMessage());
                    echo "<script>alert('Gagal mengupdate data pesanan: " . $error_message_js . "'); window.history.back();</script>";
                    exit;
                }
                $redirect_params = [];
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                foreach ($filters_to_check as $filter_key) if (!empty($_POST[$filter_key])) $redirect_params[$filter_key] = $_POST[$filter_key];
                $redirect_url = 'index.php?menu=pemesanan' . (!empty($redirect_params) ? '&' . http_build_query($redirect_params) : '');
                header('Location: ' . $redirect_url);
                exit();
                break;

            case 'batal': // Aksi membatalkan pesanan
                // ... (Logika batal pesanan seperti sebelumnya, tidak ada upload file di sini) ...
                $id_pesanan = isset($_GET['id_pesanan']) ? (int)$_GET['id_pesanan'] : 0;
                if ($id_pesanan <= 0) {
                    echo "<script>alert('Error: ID pesanan tidak valid untuk dibatalkan.'); window.location.href='index.php?menu=pemesanan';</script>";
                    exit;
                }
                $conn->begin_transaction();
                try {
                    $sql_get_batal = "SELECT id_produk, galon as jumlah FROM pesanan WHERE id_pesanan = ?";
                    $stmt_get = $conn->prepare($sql_get_batal);
                    if (!$stmt_get) throw new Exception("Prepare get data batal gagal: " . $conn->error);
                    $stmt_get->bind_param("i", $id_pesanan);
                    $stmt_get->execute();
                    $result_batal = $stmt_get->get_result();
                    $data_batal = $result_batal->fetch_assoc();
                    $stmt_get->close();
                    if ($data_batal) {
                        $id_produk_batal = (int)$data_batal['id_produk'];
                        $jumlah_batal = (int)$data_batal['jumlah'];
                        if ($jumlah_batal > 0 && $id_produk_batal > 0) {
                            $sql_stok = "UPDATE produk SET stock = stock + ? WHERE id = ?";
                            $stmt_stok = $conn->prepare($sql_stok);
                            if (!$stmt_stok) throw new Exception("Prepare kembalikan stok (batal) gagal: " . $conn->error);
                            $stmt_stok->bind_param("ii", $jumlah_batal, $id_produk_batal);
                            if (!$stmt_stok->execute()) throw new Exception("Execute kembalikan stok (batal) gagal: " . $stmt_stok->error);
                            $stmt_stok->close();
                        }
                        if ($id_produk_batal == 4 && $jumlah_batal > 0) {
                            $sql_stok_tutup = "UPDATE produk SET stock = stock + ? WHERE id = 5";
                            $stmt_stok_tutup = $conn->prepare($sql_stok_tutup);
                            if (!$stmt_stok_tutup) throw new Exception("Prepare kembalikan stok tutup (batal) gagal: " . $conn->error);
                            $stmt_stok_tutup->bind_param("i", $jumlah_batal);
                            if (!$stmt_stok_tutup->execute()) throw new Exception("Execute kembalikan stok tutup (batal) gagal: " . $stmt_stok_tutup->error);
                            $stmt_stok_tutup->close();
                        }
                    } else {
                        error_log("Pesanan ID {$id_pesanan} tidak ditemukan saat proses batal.");
                    }
                    $tables_to_delete = ['pembayaran', 'pengantaran'];
                    foreach ($tables_to_delete as $table) {
                        $sql_del_child = "DELETE FROM {$table} WHERE id_pesanan = ?";
                        $stmt_del_child = $conn->prepare($sql_del_child);
                        if ($stmt_del_child) {
                            $stmt_del_child->bind_param("i", $id_pesanan);
                            if (!$stmt_del_child->execute()) error_log("Gagal delete dari {$table} untuk ID {$id_pesanan}: " . $stmt_del_child->error);
                            $stmt_del_child->close();
                        } else {
                            error_log("Gagal prepare delete {$table}: " . $conn->error);
                        }
                    }
                    $sql_delete = "DELETE FROM pesanan WHERE id_pesanan = ?";
                    $stmt = $conn->prepare($sql_delete);
                    if (!$stmt) throw new Exception("Prepare delete pesanan gagal: " . $conn->error);
                    $stmt->bind_param("i", $id_pesanan);
                    if (!$stmt->execute()) throw new Exception("Execute delete pesanan gagal: " . $stmt->error);
                    $stmt->close();
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Gagal menghapus pesanan ID {$id_pesanan}: " . $e->getMessage());
                    $error_message_js = str_replace(['"', "'", "\n", "\r"], ['\"', "\'", "\\n", "\\r"], $e->getMessage());
                    echo "<script>alert('Gagal membatalkan pesanan: " . $error_message_js . "'); window.location.href='index.php?menu=pemesanan';</script>";
                    exit;
                }
                $redirect_params = [];
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                foreach ($filters_to_check as $filter_key) if (!empty($_GET[$filter_key])) $redirect_params[$filter_key] = $_GET[$filter_key];
                $redirect_url = 'index.php?menu=pemesanan' . (!empty($redirect_params) ? '&' . http_build_query($redirect_params) : '');
                header('Location: ' . $redirect_url);
                exit();
                break;

            case 'update_pembayaran': // Aksi untuk melunasi pembayaran
                // Ambil data dari form pelunasan
                $id_pembayaran = isset($_POST['id_pembayaran']) ? (int)$_POST['id_pembayaran'] : 0;
                $id_pesanan = isset($_POST['id_pesanan']) ? (int)$_POST['id_pesanan'] : 0;
                $metode_pembayaran = $_POST['metode_pembayaran'] ?? null;
                $tgl_bayar_input = $_POST['tgl_bayar'] ?? null; // Dari datetime-local (Y-m-d\TH:i)
                $bukti_pembayaran_url = null; // Akan diisi URL R2

                // Validasi input
                if ($id_pembayaran <= 0 || $id_pesanan <= 0 || empty($metode_pembayaran) || empty($tgl_bayar_input)) {
                    echo "<script>alert('Error: Data pembayaran tidak lengkap.'); window.history.back();</script>";
                    exit;
                }
                // Ubah format tanggal dari datetime-local ke format DATETIME MySQL (Y-m-d H:i:s)
                $tgl_bayar_db = date('Y-m-d H:i:s', strtotime($tgl_bayar_input));


                // Proses Upload ke R2 jika ada file baru diinput
                if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_OK) {
                    // Validasi file (tipe, ukuran)
                    $file_info = $_FILES['bukti_pembayaran'];
                    $file_extension = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (!in_array($file_extension, $allowed_ext)) {
                        echo "<script>alert('Error: Format file bukti bayar tidak diizinkan.'); window.history.back();</script>";
                        exit;
                    }
                    if ($file_info["size"] > 5000000) {
                        echo "<script>alert('Error: Ukuran file bukti bayar terlalu besar (maks 5MB).'); window.history.back();</script>";
                        exit;
                    }

                    // Panggil helper upload R2, target folder 'bukti_pembayaran'
                    $uploadResult = uploadToR2($file_info, 'bukti_pembayaran', $r2Config);
                    if ($uploadResult) {
                        $bukti_pembayaran_url = $uploadResult; // Simpan URL baru

                        // Ambil URL lama dan hapus dari R2
                        $sql_get_old_bukti = "SELECT bukti_pembayaran FROM pembayaran WHERE id_pembayaran = ?";
                        $stmt_get_old = $conn->prepare($sql_get_old_bukti);
                        $stmt_get_old->bind_param("i", $id_pembayaran);
                        $stmt_get_old->execute();
                        $old_url = $stmt_get_old->get_result()->fetch_assoc()['bukti_pembayaran'] ?? null;
                        $stmt_get_old->close();
                        if (!empty($old_url)) {
                            deleteFromR2($old_url, $r2Config); // Hapus file lama dari R2
                        }
                    } else {
                        // Gagal upload ke R2, tampilkan error
                        echo "<script>alert('Error: Gagal meng-upload bukti pembayaran baru ke R2. Cek log server.'); window.history.back();</script>";
                        exit;
                    }
                } else {
                    // Jika tidak ada file baru diupload, pertahankan URL bukti lama dari DB
                    $sql_get_bukti = "SELECT bukti_pembayaran FROM pembayaran WHERE id_pembayaran = ?";
                    $stmt_get_bukti = $conn->prepare($sql_get_bukti);
                    $stmt_get_bukti->bind_param("i", $id_pembayaran);
                    $stmt_get_bukti->execute();
                    $bukti_pembayaran_url = $stmt_get_bukti->get_result()->fetch_assoc()['bukti_pembayaran'] ?? null;
                    $stmt_get_bukti->close();
                }

                // Update tabel pembayaran dengan status 'Lunas' dan data lainnya
                $sql = "UPDATE pembayaran SET status_pembayaran = 'Lunas', metode_pembayaran = ?, tgl_pembayaran = ?, bukti_pembayaran = ? WHERE id_pembayaran = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("Prepare update pembayaran gagal: " . $conn->error);
                    echo "<script>alert('Gagal menyiapkan update pembayaran.'); window.history.back();</script>";
                    exit;
                }
                // Bind parameter: metode(s), tgl_db(s), url_bukti(s), id(i)
                $stmt->bind_param("sssi", $metode_pembayaran, $tgl_bayar_db, $bukti_pembayaran_url, $id_pembayaran);

                // Eksekusi dan redirect
                if ($stmt->execute()) {
                    // Redirect kembali ke daftar pesanan dengan filter yang sama
                    $redirect_params = [];
                    $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                    foreach ($filters_to_check as $filter_key) if (!empty($_POST[$filter_key])) $redirect_params[$filter_key] = $_POST[$filter_key];
                    $redirect_url = 'index.php?menu=pemesanan' . (!empty($redirect_params) ? '&' . http_build_query($redirect_params) : '');
                    header('Location: ' . $redirect_url);
                    exit();
                } else {
                    error_log("Gagal execute update pembayaran ID {$id_pembayaran}: " . $stmt->error);
                    echo "<script>alert('Error saat menyimpan data pelunasan.'); window.history.back();</script>";
                    exit;
                }
                $stmt->close();
                break; // Akhir case update_pembayaran
        }
        break; // Akhir case 'pemesanan'

    /**
         * =================================================================
         * Menu: Pengantaran
         * =================================================================
         * Tidak melibatkan upload file.
         */
    case 'pengantaran':
        // ... (Kode pengantaran Anda tidak berubah, tapi dirapikan) ...
        switch ($act) {
            case 'ambil_multi': // Aksi untuk anggota mengambil beberapa pesanan sekaligus
            if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'anggota') {
                echo "Akses ditolak. Hanya Anggota.";
                exit;
            }
            
            $daftar_id_pengantaran = $_POST['id_pengantaran'] ?? [];

            if (!empty($daftar_id_pengantaran)) {
                $id_user_pegawai = (int)$_SESSION['id_user'];
                $user_login_pegawai = $_SESSION['user_login'] ?? 'Anggota';
                
                // --- PERBAIKAN WAKTU (MENGGANTIKAN NOW()) ---
                $waktu_sekarang_jakarta = date('Y-m-d H:i:s');

                $conn->begin_transaction();
                try {
                    // Update status pengantaran yang dipilih menjadi 'Dalam Perjalanan'
                    $placeholders = implode(',', array_fill(0, count($daftar_id_pengantaran), '?'));
                    
                    // --- PERBAIKAN SQL (menggunakan $waktu_sekarang_jakarta) ---
                    $sql_update = "UPDATE pengantaran SET id_user = ?, nama_pegawai = ?, status_pengantaran = 'Dalam Perjalanan', waktu_ambil = ? WHERE id_pengantaran IN ($placeholders) AND status_pengantaran = 'Diproses'";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare update pengantaran gagal: " . $conn->error);
                    
                    // Tipe data: i (id_user), s (nama), s (waktu_ambil), i, i, i...
                    $types = 'iss' . str_repeat('i', count($daftar_id_pengantaran));
                    $params = array_merge([$id_user_pegawai, $user_login_pegawai, $waktu_sekarang_jakarta], $daftar_id_pengantaran);
                    // --- AKHIR PERBAIKAN SQL ---
                    
                    $stmt_update->bind_param($types, ...$params);
                    if (!$stmt_update->execute()) throw new Exception("Execute update pengantaran gagal: " . $stmt_update->error);
                    $affected_rows = $stmt_update->affected_rows;
                    $stmt_update->close();

                    if ($affected_rows == 0) {
                        $conn->commit();
                        header('location:index.php?menu=pengantaran_anggota');
                        exit();
                    }

                    // --- KODE NOTIFIKASI WA SUDAH DIHAPUS ---
                    
                    // Loop HANYA untuk generate token pelacakan
                    foreach ($daftar_id_pengantaran as $id_pengantaran) {
                        // Cek lagi apakah pesanan ini benar-benar diambil oleh user ini
                        $sql_check_owner = "SELECT id_user FROM pengantaran WHERE id_pengantaran = ?";
                        $stmt_check_owner = $conn->prepare($sql_check_owner);
                        $stmt_check_owner->bind_param("i", $id_pengantaran);
                        $stmt_check_owner->execute();
                        $owner_result = $stmt_check_owner->get_result()->fetch_assoc();
                        $stmt_check_owner->close();

                        if ($owner_result && $owner_result['id_user'] == $id_user_pegawai) {
                            // Generate & simpan token pelacakan (TETAP DIPERTAHANKAN)
                            $tracking_token = bin2hex(random_bytes(16));
                            $sql_token = "UPDATE pengantaran SET tracking_token = ? WHERE id_pengantaran = ?";
                            $stmt_token = $conn->prepare($sql_token);
                            if (!$stmt_token) throw new Exception("Prepare update token gagal: " . $conn->error);
                            $stmt_token->bind_param("si", $tracking_token, $id_pengantaran);
                            if (!$stmt_token->execute()) throw new Exception("Execute update token gagal: " . $stmt_token->error);
                            $stmt_token->close();
                        } // end if owner match
                    } // end foreach
                    
                    $conn->commit(); // Simpan semua perubahan
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error di ambil_multi: " . $e->getMessage());
                    echo "Terjadi error saat mengambil pesanan: " . htmlspecialchars($e->getMessage()) . " <a href='index.php?menu=pengantaran_anggota'>Kembali</a>";
                    exit;
                }
            } // end if (!empty)
            
            header('location:index.php?menu=pengantaran_saya'); // Redirect ke daftar tugas anggota
            exit();
            break;

            case 'selesai': // Aksi untuk menandai pengantaran selesai oleh anggota
                if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'anggota') {
                    die("Akses ditolak.");
                }
                $id_pengantaran = isset($_GET['id_pengantaran']) ? (int)$_GET['id_pengantaran'] : 0;
                $id_user_login = (int)$_SESSION['id_user'];
                if ($id_pengantaran <= 0 || $id_user_login <= 0) {
                    die("ID tidak valid.");
                }

                mysqli_begin_transaction($conn);
                try {
                    // Update status hanya jika ID pengantaran dan ID user cocok
                    $sql_update = "UPDATE pengantaran SET status_pengantaran = 'Selesai', waktu_selesai = NOW() WHERE id_pengantaran = ? AND id_user = ?";
                    $stmt_update = mysqli_prepare($conn, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "ii", $id_pengantaran, $id_user_login);
                    mysqli_stmt_execute($stmt_update);
                    $affected = mysqli_stmt_affected_rows($stmt_update); // Cek apakah ada baris yang terupdate
                    mysqli_stmt_close($stmt_update);

                    if ($affected > 0) {
                        mysqli_commit($conn); // Simpan jika berhasil update
                        header('location: index.php?menu=pengantaran_anggota'); // Kembali ke daftar tugas utama anggota
                    } else {
                        throw new Exception("Gagal update status atau Anda bukan pemilik pengantaran ini.");
                    }
                } catch (mysqli_sql_exception $e) {
                    mysqli_rollback($conn);
                    error_log("Gagal selesaikan pengantaran ID {$id_pengantaran}: " . $e->getMessage());
                    die("Gagal menyelesaikan pengantaran: " . htmlspecialchars($e->getMessage()));
                }
                exit();
                break;
        }
        break; // Akhir case 'pengantaran'


    /**
         * =================================================================
         * Menu: Laporan (Generate PDF)
         * =================================================================
         */
    case 'laporan':
    switch ($act) {
        case 'hasil': // Pastikan 'act' di form Anda adalah 'hasil'
            require_once __DIR__ . '/laporan/vendor/autoload.php';
            date_default_timezone_set('Asia/Jakarta');
            setlocale(LC_TIME, 'id_ID.utf8', 'id_ID');

            // --- FUNGSI HELPER (VERSI TANPA INTL) ---
            if (!function_exists('format_rupiah')) {
                function format_rupiah($angka) {
                    return "Rp " . number_format($angka, 0, ',', '.');
                }
            }
            if (!function_exists('format_tanggal_indonesia')) {
                function format_tanggal_indonesia($date) {
                    $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                    $timestamp = strtotime($date);
                    if (!$timestamp) return $date;
                    return date('d', $timestamp) . ' ' . ($bulan[date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
                }
            }
            function format_bulan_tahun_label($yyyy_mm) {
                if (empty($yyyy_mm) || strpos($yyyy_mm, '-') === false) return $yyyy_mm;
                $timestamp = strtotime($yyyy_mm . '-01');
                if (!$timestamp) return $yyyy_mm;
                $bulan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                return ($bulan_map[date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
            }
            // Fungsi baru untuk format tanggal pendek (e.g., 26 Okt 2025)
            function format_tanggal_indo_short($yyyy_mm_dd) {
                if (empty($yyyy_mm_dd)) return '';
                $timestamp = strtotime($yyyy_mm_dd);
                if (!$timestamp) return $yyyy_mm_dd;
                return date('d M Y', $timestamp); // 'M' for short month name
            }

            // --- AMBIL DATA INPUT ---
            // Ambil tanggal awal & akhir ATAU bulan-tahun
            $jenis_laporan = $_POST['jenis_laporan'] ?? '';
            $tanggal_awal = $_POST['tanggal_awal'] ?? ''; // Untuk histori stok
            $tanggal_akhir = $_POST['tanggal_akhir'] ?? ''; // Untuk histori stok
            $bulan_tahun_laporan_input = $_POST['bulan_tahun_laporan'] ?? ''; // Untuk penjualan & kualitas air

            // Validasi Input
            if (empty($jenis_laporan)) {
                echo "Error: Silakan pilih jenis laporan."; exit;
            }
            if ($jenis_laporan == 'histori_stok') {
                if (empty($tanggal_awal) || empty($tanggal_akhir)) {
                    echo "Error: Silakan pilih periode tanggal untuk Laporan Histori Stok."; exit;
                }
                if (!strtotime($tanggal_awal) || !strtotime($tanggal_akhir) || $tanggal_akhir < $tanggal_awal) {
                     echo "Error: Format tanggal tidak valid atau tanggal akhir sebelum tanggal awal."; exit;
                }
                 // Buat label periode untuk histori
                $periode_laporan = format_tanggal_indo_short($tanggal_awal) . " s/d " . format_tanggal_indo_short($tanggal_akhir);
            } else { // Untuk penjualan & kualitas air
                if (empty($bulan_tahun_laporan_input)) {
                     echo "Error: Silakan pilih periode bulan-tahun."; exit;
                }
                 // Pisahkan Tahun dan Bulan
                $parts = explode('-', $bulan_tahun_laporan_input);
                if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || (int)$parts[1] < 1 || (int)$parts[1] > 12) {
                    echo "Error: Format periode tidak valid (seharusnya YYYY-MM)."; exit;
                }
                $tahun_laporan = (int)$parts[0];
                $bulan_laporan_angka = (int)$parts[1];
                 // Buat label periode bulan-tahun
                $periode_laporan = format_bulan_tahun_label($bulan_tahun_laporan_input);
                $nama_bulan_laporan_map = [ 1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                $nama_bulan_laporan = $nama_bulan_laporan_map[$bulan_laporan_angka] ?? '';
            }

            $tanggal_cetak_hari_ini = format_tanggal_indonesia(date('Y-m-d'));
            $judul_laporan = "";
            $laporan_data = []; // Akan diisi sesuai jenis laporan

            // --- LOGIKA QUERY DATA ---

            if ($jenis_laporan == 'penjualan') {
                $judul_laporan = "Laporan Penjualan Harian";
                $grand_total_galon = 0;
                $grand_total_bayar = 0;

                // Inisialisasi data untuk SEMUA HARI dalam bulan
                $jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $bulan_laporan_angka, $tahun_laporan);
                for ($i = 1; $i <= $jumlah_hari; $i++) {
                    $laporan_data[$i] = [
                        'tanggal' => str_pad($i, 2, '0', STR_PAD_LEFT),
                        'total_galon' => 0,
                        'total_bayar' => 0
                    ];
                }

                // Query harian berdasarkan bulan dan tahun
                $sql = "SELECT
                            DAY(p.tgl_pesan) AS hari_angka,
                            SUM(p.galon) AS total_galon,
                            SUM(pem.jumlah_pembayaran) AS total_bayar
                        FROM pesanan p
                        JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                        WHERE
                            YEAR(p.tgl_pesan) = ? AND MONTH(p.tgl_pesan) = ?
                        GROUP BY DAY(p.tgl_pesan)
                        ORDER BY hari_angka ASC";

                $stmt = $conn->prepare($sql);
                // Bind bulan dan tahun
                $stmt->bind_param("ii", $tahun_laporan, $bulan_laporan_angka);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $hari_angka = (int)$row['hari_angka'];
                        if (isset($laporan_data[$hari_angka])) {
                            $laporan_data[$hari_angka]['total_galon'] = $row['total_galon'];
                            $laporan_data[$hari_angka]['total_bayar'] = $row['total_bayar'];
                        }
                    }
                    $stmt->close();
                } else {
                    echo "Error query penjualan: " . $conn->error;
                }

                // Hitung Grand Total
                foreach ($laporan_data as $data) {
                    $grand_total_galon += $data['total_galon'];
                    $grand_total_bayar += $data['total_bayar'];
                }

            } elseif ($jenis_laporan == 'kualitas_air') {
                $judul_laporan = "Laporan Kualitas Air Harian";
                 // $laporan_data dimulai kosong []

                // Query detail berdasarkan bulan dan tahun
                $sql = "SELECT
                            da.tgl_aktivitas,
                            da.jenis_pengecekan,
                            da.hasil,
                            u.user_login
                        FROM data_aktivitas da
                        LEFT JOIN user u ON da.id_user = u.id
                        WHERE
                            YEAR(da.tgl_aktivitas) = ? AND MONTH(da.tgl_aktivitas) = ?
                            AND (da.jenis_pengecekan = 'Tes pH' OR da.jenis_pengecekan = 'TDS')
                        ORDER BY da.tgl_aktivitas ASC";

                $stmt = $conn->prepare($sql);
                 // Bind bulan dan tahun
                $stmt->bind_param("ii", $tahun_laporan, $bulan_laporan_angka);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $laporan_data[] = $row;
                    }
                    $stmt->close();
                } else {
                    echo "Error query kualitas air: " . $conn->error;
                }

            } elseif ($jenis_laporan == 'histori_stok') {
                $judul_laporan = "Laporan Histori Stok Masuk";
                $sql = "SELECT
                            hs.waktu_update,
                            p.nama_produk,
                            hs.jumlah_tambah,
                            hs.stok_sebelum,
                            hs.stok_sesudah,
                            u.user_login AS nama_petugas,
                            hs.jenis_transaksi,
                            hs.harga_pembelian_unit
                        FROM histori_stok hs
                        JOIN produk p ON hs.id_produk = p.id_produk
                        LEFT JOIN user u ON hs.id_user = u.id_user
                        WHERE
                            DATE(hs.waktu_update) BETWEEN ? AND ?
                        ORDER BY hs.waktu_update ASC";

                $stmt = $conn->prepare($sql);
                // Bind tanggal awal dan akhir
                $stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $laporan_data[] = $row;
                    }
                     $stmt->close();
                } else {
                     echo "Error query histori stok: " . $conn->error;
                }
            }

            // --- MULAI REKAM HTML ---
            ob_start();
            ?>

            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title><?php echo htmlspecialchars($judul_laporan); ?> - <?php echo htmlspecialchars($periode_laporan); ?></title>
                <style>
                    /* CSS Anda */
                    body { font-family: Arial, sans-serif; font-size: 14px; }
                    .page-container { width: 100%; }
                    .report-header { text-align: center; border-bottom: 2px solid black; padding-bottom: 10px; margin-bottom: 20px; }
                    .report-header h1 { margin: 0; font-size: 24px; }
                    .report-header p { margin: 5px 0 0; font-size: 14px; }
                    .report-title { text-align: center; margin-bottom: 25px; }
                    .report-title h2 { margin: 0; font-size: 22px; text-decoration: underline; }
                    .report-title h3 { margin: 5px 0 0; font-size: 16px; font-weight: normal; }
                    .report-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; }
                    .report-table th, .report-table td { border: 1px solid black; padding: 8px; text-align: left; vertical-align: top; }
                    .report-table th { background-color: #f2f2f2; text-align: center; }
                    .report-table td.angka, .report-table th.angka { text-align: right; }
                    .report-table .total-row td { font-weight: bold; background-color: #f2f2f2; }
                    /* CSS Tanda Tangan Tunggal (Rata Kiri) */
                    /* Sesuaikan blok tanda tangan agar agak ke kanan */
/* Atur posisi blok tanda tangan */
.report-signature {
    margin-top: 50px;
    width: 250px; /* Lebar blok */
    margin-left: auto; /* Otomatis geser ke kanan */
    margin-right: -30px; /* Jarak dari tepi kanan */
    font-size: 14px;
}

/* Atur paragraf LANGSUNG di bawah .report-signature (Pekanbaru & Dibuat oleh) */
.report-signature > p {
    margin-top: 0;
    margin-bottom: 2px; 
    text-align: left; /* Pastikan ini rata kiri */
}

/* Ruang kosong */
.signature-space {
    height: 75px;
    margin-top: 5px;
    margin-bottom: 5px;
}

/* Atur div baru agar isinya rata TENGAH */
.signature-details {
    text-align: center; /* Terapkan rata tengah ke div ini */
}

/* Atur style NAMA di dalam div baru */
.signature-details .signature-name { /* Lebih spesifik */
    font-weight: bold;
    text-decoration: underline;
    margin-top: 0;
    margin-bottom: 2px;
    margin-right: 50px;
}

/* Atur style JABATAN di dalam div baru */
.signature-details .signature-job { /* Lebih spesifik */
    font-weight: bold;
    margin-top: 0;
    margin-bottom: 2px;
    margin-right: 50px;
}
                </style>
            </head>
            <body>
                <div class="page-container">
                    <div class="report-header">
                        <h1>Depot Thank You Water</h1>
                        <p>Jl. Paus Kelurahan No.26, Tengkerang Tengah, Kec. Marpoyan Damai, Kota Pekanbaru, Riau 28282, Indonesia</p>
                    </div>
                    <div class="report-title">
                        <h2><?php echo htmlspecialchars($judul_laporan); ?></h2>
                        <h3>Periode: <?php echo htmlspecialchars($periode_laporan); ?></h3>
                    </div>

                    <?php if ($jenis_laporan == 'penjualan'): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Tanggal</th>
                                    <th class="angka" style="width: 30%;">Total Galon</th>
                                    <th class="angka" style="width: 45%;">Total Bayar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($laporan_data as $data): ?>
                                    <tr>
                                        <td><?php echo $data['tanggal']; ?> <?php echo $nama_bulan_laporan; ?> <?php echo $tahun_laporan; ?></td>
                                        <td class="angka"><?php echo ($data['total_galon'] > 0) ? number_format($data['total_galon']) : '0'; ?></td>
                                        <td class="angka"><?php echo ($data['total_bayar'] > 0) ? format_rupiah($data['total_bayar']) : 'Rp 0'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td><b>Total</b></td>
                                    <td class="angka"><b><?php echo number_format($grand_total_galon); ?> Galon</b></td>
                                    <td class="angka"><b><?php echo format_rupiah($grand_total_bayar); ?></b></td>
                                </tr>
                            </tbody>
                        </table>

                    <?php elseif ($jenis_laporan == 'kualitas_air'): ?>
                        <table class="report-table">
                           <thead>
                                <tr>
                                    <th style="width: 30%;">Tanggal & Waktu</th>
                                    <th style="width: 25%;">Jenis Pengecekan</th>
                                    <th class="angka" style="width: 20%;">Hasil</th>
                                    <th style="width: 25%;">Petugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($laporan_data)): ?>
                                    <tr><td colspan="4" style="text-align: center;">Tidak ada data pengecekan untuk bulan <?php echo $periode_laporan; ?>.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($laporan_data as $data): ?>
                                        <tr>
                                            <td><?php echo date('d M Y - H:i', strtotime($data['tgl_aktivitas'])); ?></td>
                                            <td><?php echo htmlspecialchars($data['jenis_pengecekan']); ?></td>
                                            <td class="angka"><?php echo htmlspecialchars($data['hasil']); ?></td>
                                            <td><?php echo htmlspecialchars($data['user_login'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php elseif ($jenis_laporan == 'histori_stok'): ?>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 15%;">Tanggal & Waktu</th>
                <th style="width: 15%;">Nama Produk</th>
                <th style="width: 15%;">Jenis Transaksi</th>
                <th class="angka" style="width: 10%;">Jumlah</th>
                <th class="angka" style="width: 10%;">Stok Sebelum</th>
                <th class="angka" style="width: 10%;">Stok Sesudah</th>
                <th class="angka" style="width: 15%;">Harga Beli/Unit</th> <th style="width: 15%;">Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($laporan_data)): ?>
                <tr><td colspan="8" style="text-align: center;">Tidak ada data histori stok untuk periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($laporan_data as $data): ?>
                    <?php
                        // Format mata uang untuk Harga Beli/Unit
                        $harga_beli_formatted = number_format($data['harga_pembelian_unit'] ?? 0, 0, ',', '.');
                    ?>
                    <tr>
                        <td><?php echo date('d M Y, H:i', strtotime($data['waktu_update'])); ?></td>
                        <td><?php echo htmlspecialchars($data['nama_produk']); ?></td>
                        <td><?php echo htmlspecialchars($data['jenis_transaksi']); ?></td> <td class="angka"><?php echo htmlspecialchars($data['jumlah_tambah']); ?></td>
                        <td class="angka"><?php echo htmlspecialchars($data['stok_sebelum']); ?></td>
                        <td class="angka"><?php echo htmlspecialchars($data['stok_sesudah']); ?></td>
                        <td class="angka">Rp <?php echo $harga_beli_formatted; ?></td> <td><?php echo htmlspecialchars($data['nama_petugas'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
                    <?php endif; ?>

<div class="report-signature">
    <p>Pekanbaru, <?php echo $tanggal_cetak_hari_ini; ?></p>
    <p>Dibuat oleh,</p>
    <div class="signature-space"></div>
<div class="signature-details">
        <p class="signature-name"><?php echo htmlspecialchars($_SESSION['user_login'] ?? 'Nama Pengguna'); ?></p>
        <p class="signature-job"><?php echo htmlspecialchars(ucfirst($_SESSION['level'] ?? 'Jabatan')); ?></p>
    </div>
</div>

                </div>
            </body>
            </html>
            <?php
            // --- SELESAI REKAM HTML ---
            $html_content = ob_get_clean();

            // --- GENERATE PDF ---
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'tempDir' => __DIR__ . '/laporan/temp_mpdf',
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'margin_left' => 15, 'margin_right' => 15,
                    'margin_top' => 15, 'margin_bottom' => 15,
                ]);

                $mpdf->WriteHTML($html_content);

                // Buat nama file berdasarkan jenis laporan dan periode
                $nama_file_base = strtolower(str_replace(' ', '_', $judul_laporan));
                if ($jenis_laporan == 'histori_stok') {
                     $nama_file = $nama_file_base . '_' . $tanggal_awal . '_sd_' . $tanggal_akhir . '.pdf';
                } else {
                     $nama_file = $nama_file_base . '_' . $bulan_tahun_laporan_input . '.pdf';
                }
                $mpdf->Output($nama_file, 'I'); // 'I' = Tampilkan di tab browser

            } catch (\Mpdf\MpdfException $e) {
                echo "Gagal membuat PDF: <pre>" . $e->getMessage() . "</pre>";
            } catch (Exception $e) {
                echo "Terjadi kesalahan umum: " . $e->getMessage();
            }

            break; // Tutup case 'hasil'
    }
    break; // Tutup case 'laporan'

    /**
         * =================================================================
         * Menu: Arsip Dinkes (Upload & Hapus Dokumen ke/dari R2)
         * =================================================================
         */
    case 'arsip_dinkes':
        switch ($act) {
            case 'upload': // Aksi upload dokumen baru
                // Ambil data POST
                $judul_dokumen = $_POST['judul_dokumen'] ?? '';
                $tgl_laporan = $_POST['tgl_laporan'] ?? ''; // Format YYYY-MM-DD
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
                $dokumen_url = null; // Variabel untuk menyimpan URL R2

                // Validasi input teks
                if (empty($judul_dokumen) || empty($tgl_laporan) || $id_user <= 0 || !strtotime($tgl_laporan)) { // Cek format tanggal juga
                    die("Error: Judul, Tanggal Laporan (format valid), dan ID User harus diisi. <a href='javascript:history.back()'>Kembali</a>");
                }

                // Validasi dan Proses Upload ke R2
                if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['file_laporan'];
                    $file_extension = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png']; // Ekstensi yang diizinkan

                    // Cek ekstensi
                    if (!in_array($file_extension, $allowed_ext)) {
                        die("Error: Format file tidak diizinkan (hanya PDF, JPG, JPEG, PNG). <a href='javascript:history.back()'>Kembali</a>");
                    }
                    // Cek ukuran (maks 5MB)
                    if ($file_info["size"] > 5 * 1024 * 1024) { // 5 MB dalam bytes
                        die("Error: Ukuran file terlalu besar (maks 5MB). <a href='javascript:history.back()'>Kembali</a>");
                    }

                    // Panggil fungsi uploadToR2, target folder 'dokumen_dinkes'
                    $uploadResult = uploadToR2($file_info, 'dokumen_dinkes', $r2Config);
                    if ($uploadResult) {
                        $dokumen_url = $uploadResult; // Simpan URL R2 jika upload berhasil
                    } else {
                        // Jika upload ke R2 gagal, hentikan proses
                        echo "Error: Gagal meng-upload file dokumen ke Cloudflare R2. Periksa log server untuk detail.<br><a href='javascript:history.back()'>Kembali</a>";
                        exit;
                    }
                } else {
                    // Jika tidak ada file atau ada error upload PHP
                    $php_upload_error = $_FILES['file_laporan']['error'] ?? UPLOAD_ERR_NO_FILE; // Dapatkan kode error PHP
                    die("Error: File laporan wajib diisi atau gagal diupload dari browser (Error code: {$php_upload_error}). <a href='javascript:history.back()'>Kembali</a>");
                }

                // Simpan URL R2 ke Database menggunakan prepared statement
                $sql = "INSERT INTO dokumen_resmi (tgl_laporan, judul_dokumen, path_file, id_user) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                // Bind parameter: tgl(s), judul(s), url(s), id_user(i)
                $stmt->bind_param("sssi", $tgl_laporan, $judul_dokumen, $dokumen_url, $id_user);

                // Eksekusi query
                if ($stmt->execute()) {
                    header('Location: index.php?menu=arsip_dinkes'); // Redirect ke daftar arsip jika sukses
                } else {
                    // Jika gagal simpan ke DB, log error dan coba hapus file dari R2
                    error_log("Gagal menyimpan data Dinkes ke DB: " . $stmt->error . " | URL R2: " . $dokumen_url);
                    echo "Error: Gagal menyimpan data ke database. " . htmlspecialchars($stmt->error);
                    // Coba hapus file dari R2 karena insert DB gagal
                    if ($dokumen_url) {
                        deleteFromR2($dokumen_url, $r2Config);
                        error_log("File R2 dihapus karena insert DB gagal: " . $dokumen_url);
                    }
                }
                $stmt->close();
                break; // Akhir case upload

            case 'hapus': // Aksi menghapus dokumen arsip
                $id_dokumen = isset($_GET['id_dokumen']) ? (int)$_GET['id_dokumen'] : 0;
                if ($id_dokumen <= 0) {
                    die("ID Dokumen tidak valid.");
                }

                // Ambil URL file dari database
                $sql_select = "SELECT path_file FROM dokumen_resmi WHERE id_dokumen = ?";
                $stmt_select = $conn->prepare($sql_select);
                $stmt_select->bind_param("i", $id_dokumen);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $row = $result->fetch_assoc();
                $stmt_select->close();

                $file_url_to_delete = $row['path_file'] ?? null;
                $r2_delete_success = false; // Flag status hapus R2

                if ($row) { // Hanya proses jika data ada di DB
                    // Coba Hapus file dari Cloudflare R2 jika URL tersimpan
                    if (!empty($file_url_to_delete)) {
                        $r2_delete_success = deleteFromR2($file_url_to_delete, $r2Config); // Panggil helper hapus
                        if (!$r2_delete_success) {
                            // Tampilkan peringatan jika R2 gagal dihapus, tapi proses hapus DB tetap lanjut
                            // Anda bisa mengubah ini menjadi `exit()` jika gagal hapus R2 dianggap fatal
                            echo "<script>alert('Peringatan: Gagal menghapus file dari Cloudflare R2. Data database akan tetap dihapus.');</script>";
                        }
                    } else {
                        // Jika URL kosong di DB, tidak ada yang perlu dihapus di R2
                        $r2_delete_success = true; // Anggap sukses
                    }

                    // Hapus data dari database (selalu coba lakukan ini)
                    $sql_delete = "DELETE FROM dokumen_resmi WHERE id_dokumen = ?";
                    $stmt_delete = $conn->prepare($sql_delete);
                    $stmt_delete->bind_param("i", $id_dokumen);
                    if ($stmt_delete->execute()) {
                        header('Location: index.php?menu=arsip_dinkes'); // Redirect hanya jika DB berhasil dihapus
                        exit();
                    } else {
                        // Gagal hapus dari DB
                        error_log("Gagal menghapus data Dinkes dari DB ID {$id_dokumen}: " . $stmt_delete->error);
                        echo "Error: Gagal menghapus data dari database. " . htmlspecialchars($stmt_delete->error);
                    }
                    $stmt_delete->close();
                    exit(); // Pastikan script berhenti di sini

                } else {
                    // Jika data dokumen tidak ditemukan di DB
                    echo "Error: Data dokumen tidak ditemukan di database.";
                    // Tidak ada redirect
                }
                break; // Akhir case hapus
        }
        break; // Akhir case 'arsip_dinkes'

    /**
         * =================================================================
         * Menu: Pengaturan Akun (Ganti Password)
         * =================================================================
         * Tidak melibatkan upload file. Gunakan password_verify() dan password_hash().
         */
    case 'pengaturan_akun':
        switch ($act) {
            case 'update_password': // Aksi untuk user mengganti passwordnya sendiri
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                } // Pastikan sesi ada

                // Ambil data dari form
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
                $password_lama = $_POST['password_lama'] ?? '';
                $password_baru = $_POST['password_baru'] ?? '';
                $min_length = 6; // Minimal panjang password baru

                // Validasi input dasar
                if ($id_user <= 0 || empty($password_lama) || empty($password_baru)) {
                    $_SESSION['flash_message'] = 'Semua field password wajib diisi.';
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_akun');
                    exit;
                }
                if (strlen($password_baru) < $min_length) {
                    $_SESSION['flash_message'] = "Password baru minimal harus {$min_length} karakter.";
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_akun');
                    exit;
                }

                // Ambil hash password lama dari database
                $sql_get_pass = "SELECT password FROM user WHERE id = ?"; // Sesuaikan nama tabel/kolom jika beda
                $stmt_get = $conn->prepare($sql_get_pass);
                if (!$stmt_get) {
                    error_log("Prepare get password gagal: " . $conn->error);
                    $_SESSION['flash_message'] = 'Terjadi kesalahan saat memeriksa data.';
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_akun');
                    exit;
                }
                $stmt_get->bind_param("i", $id_user);
                $stmt_get->execute();
                $result = $stmt_get->get_result();
                $user_data = $result->fetch_assoc();
                $stmt_get->close();

                if (!$user_data) {
                    $_SESSION['flash_message'] = 'Data pengguna tidak ditemukan.';
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_akun');
                    exit;
                }

                $hash_password_lama_db = $user_data['password'];

                // Verifikasi password lama menggunakan password_verify()
                if (password_verify($password_lama, $hash_password_lama_db)) {
                    // Password lama cocok, hash password baru
                    $hash_password_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                    if ($hash_password_baru === false) {
                        error_log("Password hashing failed.");
                        $_SESSION['flash_message'] = 'Terjadi kesalahan sistem saat memproses password baru.';
                        $_SESSION['flash_type'] = 'alert-danger';
                        header('Location: index.php?menu=pengaturan_akun');
                        exit;
                    }

                    // Update password baru ke database
                    $sql_update_pass = "UPDATE user SET password = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update_pass);
                    if (!$stmt_update) {
                        error_log("Prepare update password gagal: " . $conn->error);
                        $_SESSION['flash_message'] = 'Terjadi kesalahan saat menyimpan password baru.';
                        $_SESSION['flash_type'] = 'alert-danger';
                        header('Location: index.php?menu=pengaturan_akun');
                        exit;
                    }
                    $stmt_update->bind_param("si", $hash_password_baru, $id_user);
                    if ($stmt_update->execute()) {
                        $_SESSION['flash_message'] = 'Password berhasil diperbarui.';
                        $_SESSION['flash_type'] = 'alert-success';
                    } else {
                        error_log("Execute update password gagal: " . $stmt_update->error);
                        $_SESSION['flash_message'] = 'Gagal memperbarui password di database.';
                        $_SESSION['flash_type'] = 'alert-danger';
                    }
                    $stmt_update->close();
                } else {
                    // Password lama TIDAK cocok
                    $_SESSION['flash_message'] = 'Password lama yang Anda masukkan salah.';
                    $_SESSION['flash_type'] = 'alert-danger';
                }
                // Redirect kembali ke halaman pengaturan
                header('Location: index.php?menu=pengaturan_akun');
                exit;
                break; // Akhir case update_password
        }
        break; // Akhir case 'pengaturan_akun'

    /**
         * =================================================================
         * Menu: Pengaturan Admin (Konfigurasi WhatsApp)
         * =================================================================
         * Tidak melibatkan upload file R2.
         */
    case 'pengaturan_admin':
        // ... (Kode pengaturan admin Anda tidak berubah, tapi pastikan path benar) ...
        if (session_status() == PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') {
            die("Akses ditolak.");
        }
        $config_path = __DIR__ . '/config_wa.php';
        $webhook_file_path = __DIR__ . '/../config/webhook.php';
        switch ($act) {
            case 'update_webhook_file':
                if (!file_exists($config_path)) {
                    if (file_put_contents($config_path, "<?php\n\nreturn [];\n") === false) {
                        $_SESSION['flash_message'] = 'Error: Gagal membuat file konfigurasi. Pastikan folder admin writable.';
                        $_SESSION['flash_type'] = 'alert-danger';
                        header('Location: index.php?menu=pengaturan_admin');
                        exit;
                    }
                    if (!is_writable($config_path)) {
                        $_SESSION['flash_message'] = 'Error: File konfigurasi (config_wa.php) tidak dapat ditulis (permission denied). Pengaturan tidak disimpan.';
                        $_SESSION['flash_type'] = 'alert-danger';
                        header('Location: index.php?menu=pengaturan_admin');
                        exit;
                    }
                } elseif (!is_writable($config_path)) {
                    $_SESSION['flash_message'] = 'Error: File konfigurasi (config_wa.php) tidak dapat ditulis (permission denied). Pengaturan tidak disimpan.';
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_admin');
                    exit;
                }
                $new_access_token_input = $_POST['access_token'] ?? '';
                $new_phone_id = $_POST['phone_number_id'] ?? '';
                $new_verify_token = $_POST['webhook_verify_token'] ?? '';
                if (empty($new_phone_id) || empty($new_verify_token)) {
                    $_SESSION['flash_message'] = 'Error: Phone Number ID dan Verify Token wajib diisi.';
                    $_SESSION['flash_type'] = 'alert-danger';
                    header('Location: index.php?menu=pengaturan_admin');
                    exit;
                }
                $config_lama = include $config_path;
                if (!is_array($config_lama)) {
                    $config_lama = ['webhook_verify_token' => '', 'access_token' => '', 'phone_number_id' => ''];
                    error_log("Warning: Gagal membaca format array dari config_wa.php");
                }
                $access_token_lama = $config_lama['access_token'] ?? '';
                $access_token_to_write = !empty($new_access_token_input) ? $new_access_token_input : $access_token_lama;
                $config_baru = ['webhook_verify_token' => $new_verify_token, 'access_token' => $access_token_to_write, 'phone_number_id' => $new_phone_id,];
                $isi_file = "<?php\n\n// Konfigurasi WhatsApp Cloud API (Generated: " . date('Y-m-d H:i:s') . ")\n\nreturn " . var_export($config_baru, true) . ";\n";
                if (file_put_contents($config_path, $isi_file) !== false) {
                    $_SESSION['flash_message'] = 'Konfigurasi WhatsApp berhasil disimpan ke config_wa.php.';
                    $_SESSION['flash_type'] = 'alert-success';
                    if (function_exists('opcache_invalidate')) {
                        opcache_invalidate($config_path, true);
                    }
                } else {
                    $_SESSION['flash_message'] = 'Error: Gagal menulis perubahan ke file konfigurasi config_wa.php.';
                    $_SESSION['flash_type'] = 'alert-danger';
                }
                header('Location: index.php?menu=pengaturan_admin');
                exit;
                break;
            case 'test_webhook':
                $config_wa = [];
                if (file_exists($config_path) && is_readable($config_path)) {
                    $config_wa = include $config_path;
                    if (!is_array($config_wa)) $config_wa = [];
                }
                $accessToken = $config_wa['access_token'] ?? '';
                $phoneNumberId = $config_wa['phone_number_id'] ?? '';
                $test_number = $_POST['test_number'] ?? '';
                if (empty($accessToken) || empty($phoneNumberId)) {
                    $_SESSION['flash_message'] = 'Error: Access Token atau Phone Number ID belum dikonfigurasi di config_wa.php.';
                    $_SESSION['flash_type'] = 'alert-danger';
                } elseif (empty($test_number) || !preg_match('/^62\d{9,15}$/', $test_number)) {
                    $_SESSION['flash_message'] = 'Error: Nomor telepon tujuan tes tidak valid (harus diawali 62).';
                    $_SESSION['flash_type'] = 'alert-danger';
                } else {
                    if (file_exists($webhook_file_path)) {
                        require_once $webhook_file_path;
                        $message_text = "Ini adalah pesan tes dari Pengaturan Admin Sipamis pada " . date('d-m-Y H:i:s');
                        if (function_exists('sendWhatsAppMessage')) {
                            if (sendWhatsAppMessage($test_number, $message_text, $accessToken, $phoneNumberId)) {
                                $_SESSION['flash_message'] = 'Pesan tes berhasil dikirim ke ' . htmlspecialchars($test_number) . '.';
                                $_SESSION['flash_type'] = 'alert-success';
                            } else {
                                $_SESSION['flash_message'] = 'Gagal mengirim pesan tes ke ' . htmlspecialchars($test_number) . '. Cek log error API.';
                                $_SESSION['flash_type'] = 'alert-danger';
                            }
                        } else {
                            $_SESSION['flash_message'] = 'Error internal: Fungsi sendWhatsAppMessage tidak ditemukan.';
                            $_SESSION['flash_type'] = 'alert-danger';
                        }
                    } else {
                        $_SESSION['flash_message'] = 'Error: File webhook tidak ditemukan.';
                        $_SESSION['flash_type'] = 'alert-danger';
                    }
                }
                header('Location: index.php?menu=pengaturan_admin');
                exit;
                break;
        }
        break;

        case 'kelola_user':
        // Pastikan hanya admin yang bisa mengakses ini
        if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') {
            echo "<script>alert('Akses ditolak!'); window.location.href='index.php';</script>";
            exit;
        }

        switch ($act) {
            // --- PROSES TAMBAH PENGGUNA BARU ---
            case 'input':
                $nama_lengkap = $_POST['nama_lengkap'] ?? '';
                $user_login = $_POST['user_login'] ?? '';
                $level = $_POST['level'] ?? '';
                $password_baru = $_POST['password_baru'] ?? '';

                // Validasi input
                if (empty($nama_lengkap) || empty($user_login) || empty($level) || empty($password_baru)) {
                    echo "<script>alert('Error: Semua field wajib diisi.'); window.history.back();</script>";
                    exit;
                }
                if (strlen($password_baru) < 6) {
                    echo "<script>alert('Error: Password minimal harus 6 karakter.'); window.history.back();</script>";
                    exit;
                }

                // Cek apakah username sudah ada
                $sql_cek = "SELECT id FROM user WHERE user_login = ?";
                $stmt_cek = $conn->prepare($sql_cek);
                $stmt_cek->bind_param("s", $user_login);
                $stmt_cek->execute();
                $result_cek = $stmt_cek->get_result();
                if ($result_cek->num_rows > 0) {
                    $stmt_cek->close();
                    echo "<script>alert('Error: Username \\'".htmlspecialchars($user_login, ENT_QUOTES)."\\' sudah digunakan. Silakan pilih username lain.'); window.history.back();</script>";
                    exit;
                }
                $stmt_cek->close();

                // Hash password baru
                $hash_password = password_hash($password_baru, PASSWORD_DEFAULT);
                if ($hash_password === false) {
                     echo "<script>alert('Error: Gagal memproses password.'); window.history.back();</script>";
                     exit;
                }

                // Masukkan ke database
                $sql_insert = "INSERT INTO user (user_login, password, nama_lengkap, level) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                if (!$stmt_insert) {
                     echo "<script>alert('Error SQL: Gagal menyiapkan query insert.'); window.history.back();</script>";
                     exit;
                }
                $stmt_insert->bind_param("ssss", $user_login, $hash_password, $nama_lengkap, $level);
                
                if ($stmt_insert->execute()) {
                    header('Location: index.php?menu=kelola_user');
                } else {
                     echo "<script>alert('Error: Gagal menyimpan data pengguna ke database.'); window.history.back();</script>";
                }
                $stmt_insert->close();
                break;

            // --- PROSES UPDATE PENGGUNA ---
            case 'update':
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
                $nama_lengkap = $_POST['nama_lengkap'] ?? '';
                $user_login = $_POST['user_login'] ?? '';
                $level = $_POST['level'] ?? '';
                $password_baru = $_POST['password_baru'] ?? ''; // Opsional

                // Validasi
                if ($id_user <= 0 || empty($nama_lengkap) || empty($user_login) || empty($level)) {
                    echo "<script>alert('Error: Data dasar pengguna (ID, Nama, Username, Level) wajib diisi.'); window.history.back();</script>";
                    exit;
                }

                // Cek apakah username diubah dan sudah ada yang pakai
                $sql_cek_user = "SELECT id FROM user WHERE user_login = ? AND id != ?";
                $stmt_cek_user = $conn->prepare($sql_cek_user);
                $stmt_cek_user->bind_param("si", $user_login, $id_user);
                $stmt_cek_user->execute();
                $result_cek_user = $stmt_cek_user->get_result();
                if ($result_cek_user->num_rows > 0) {
                     $stmt_cek_user->close();
                     echo "<script>alert('Error: Username \\'".htmlspecialchars($user_login, ENT_QUOTES)."\\' sudah digunakan oleh pengguna lain.'); window.history.back();</script>";
                     exit;
                }
                $stmt_cek_user->close();

                // Persiapkan query update
                $params = [];
                $types = "";

                // Cek apakah password diisi
                if (!empty($password_baru)) {
                    if (strlen($password_baru) < 6) {
                         echo "<script>alert('Error: Password baru minimal harus 6 karakter.'); window.history.back();</script>";
                         exit;
                    }
                    // Jika diisi, update password
                    $hash_password_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                    $sql_update = "UPDATE user SET user_login = ?, nama_lengkap = ?, level = ?, password = ? WHERE id = ?";
                    $types = "ssssi"; // s=user_login, s=nama, s=level, s=pass, i=id
                    array_push($params, $user_login, $nama_lengkap, $level, $hash_password_baru, $id_user);
                } else {
                    // Jika password dikosongkan, JANGAN update password
                    $sql_update = "UPDATE user SET user_login = ?, nama_lengkap = ?, level = ? WHERE id = ?";
                    $types = "sssi"; // s=user_login, s=nama, s=level, i=id
                    array_push($params, $user_login, $nama_lengkap, $level, $id_user);
                }
                
                $stmt_update = $conn->prepare($sql_update);
                if (!$stmt_update) {
                     echo "<script>alert('Error SQL: Gagal menyiapkan query update.'); window.history.back();</script>";
                     exit;
                }
                
                $stmt_update->bind_param($types, ...$params);

                if ($stmt_update->execute()) {
                    header('Location: index.php?menu=kelola_user');
                } else {
                     echo "<script>alert('Error: Gagal mengupdate data pengguna.'); window.history.back();</script>";
                }
                $stmt_update->close();
                break;

            // --- PROSES HAPUS PENGGUNA ---
            case 'batal':
                $id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;
                $current_user_id = $_SESSION['id_user'] ?? 0;

                // Validasi
                if ($id_user <= 0) {
                    echo "<script>alert('Error: ID pengguna tidak valid.'); window.location.href='index.php?menu=kelola_user';</script>";
                    exit;
                }

                // Cek agar tidak hapus diri sendiri
                if ($id_user == $current_user_id) {
                     echo "<script>alert('Error: Anda tidak dapat menghapus akun Anda sendiri.'); window.location.href='index.php?menu=kelola_user';</script>";
                     exit;
                }

                $sql_delete = "DELETE FROM user WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                 if (!$stmt_delete) {
                     echo "<script>alert('Error SQL: Gagal menyiapkan query hapus.'); window.history.back();</script>";
                     exit;
                }
                $stmt_delete->bind_param("i", $id_user);
                
                if ($stmt_delete->execute()) {
                    header('Location: index.php?menu=kelola_user');
                } else {
                     echo "<script>alert('Error: Gagal menghapus pengguna.'); window.location.href='index.php?menu=kelola_user';</script>";
                }
                $stmt_delete->close();
                break;
        }
        break; // Akhir case 'kelola_user'
        
        
        
        
    // --- Default Case ---
    default:
        // Jika parameter 'menu' tidak cocok dengan case di atas
        // Bisa redirect ke halaman dashboard admin atau tampilkan pesan 'Aksi tidak dikenal'
        // header('Location: index.php'); // Contoh redirect ke dashboard
        // exit;
        echo "Aksi atau menu tidak valid."; // Pesan sederhana
        break; // Biarkan kosong jika tidak ada aksi default
} // Akhir switch $menu

// --- Tutup Koneksi Database ---
// Menutup koneksi di akhir script adalah praktik yang baik
$conn->close();
