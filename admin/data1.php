<?php
// --- Pengaturan Awal ---
ini_set('display_errors', 1); // Tampilkan error untuk debugging (nonaktifkan di produksi)
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta'); // Atur zona waktu
session_start(); // Mulai atau lanjutkan sesi

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
$id_plng = isset($_GET['id_plng']) ? (int)$_GET['id_plng'] : 0;
$status_webhook = $_GET['status'] ?? '';


function generate_random_password($length = 8)
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

function send_wa_notification_direct($target_number, $message_text, $wa_config)
{
    $token = $wa_config['access_token'] ?? null;
    $phone_id = $wa_config['phone_number_id'] ?? null;

    if (empty($token) || empty($phone_id)) {
        // Konfigurasi belum lengkap atau salah
        error_log("WA Config missing Token or Phone ID.");
        return false;
    }
    $api_url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";

    // Payload JSON
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $target_number,
        'type' => 'text',
        'text' => ['body' => $message_text],
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Logging sederhana untuk debugging server-side
    error_log("WA Response Status: {$http_code}, Body: {$response}");

    // Sukses jika HTTP 200/201
    return ($http_code == 200 || $http_code == 201);
}

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
                if (empty($jenis_pengecekan) || empty($hasil) || $id_user <= 0) {
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
                $sql_insert = "INSERT INTO data_plng(nama_plng, alamat, no_telp, lat, lng) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sssss", $nama_plng, $alamat, $no_telp, $lat, $lng);
                if (!$stmt_insert->execute()) {
                    error_log("Gagal insert member: " . $stmt_insert->error);
                }
                $stmt_insert->close();
                header('location:index.php?menu=' . $menu);
                exit();
                break;

            case 'update': // Aksi update data member
                // Ambil data & sanitasi
                $id_plng = mysqli_real_escape_string($conn, $_POST['id_plng']);
                $nama_plng = mysqli_real_escape_string($conn, $_POST['nama_plng']);
                $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
                $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
                $lat = mysqli_real_escape_string($conn, $_POST['lat']);
                $lng = mysqli_real_escape_string($conn, $_POST['lng']);

                // Cek duplikasi nomor telepon (kecuali untuk ID yang sedang diedit)
                $sql_cek = "SELECT id_plng FROM data_plng WHERE no_telp = ? AND id_plng != ?";
                $stmt_cek = mysqli_prepare($conn, $sql_cek);
                mysqli_stmt_bind_param($stmt_cek, "ss", $no_telp, $id_plng);
                mysqli_stmt_execute($stmt_cek);
                $result_cek = mysqli_stmt_get_result($stmt_cek);
                if (mysqli_num_rows($result_cek) > 0) {
                    $errorMessage = "Gagal: Nomor telepon " . htmlspecialchars($no_telp) . " sudah terdaftar untuk pelanggan lain.";
                    echo "<script> alert('{$errorMessage}'); window.history.back(); </script>";
                    exit();
                }
                mysqli_stmt_close($stmt_cek);

                // Update data menggunakan prepared statement
                $sql_update = "UPDATE data_plng SET nama_plng=?, alamat=?, no_telp=?, lat=?, lng=? WHERE id_plng=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("sssssi", $nama_plng, $alamat, $no_telp, $lat, $lng, $id_plng);
                if (!$stmt->execute()) {
                    error_log("Gagal update member ID {$id_plng}: " . $stmt->error);
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
                    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>";
                    exit;
                }

                $nama_produk = $_POST['nama_produk'] ?? '';
                $jenis_produk = $_POST['jenis_produk'] ?? '';
                $harga = isset($_POST['harga']) ? (float)$_POST['harga'] : 0;
                $stock_awal = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;

                if (empty($nama_produk) || empty($jenis_produk)) {
                    echo "<script>alert('Nama dan Jenis produk wajib diisi.'); window.history.back();</script>";
                    exit;
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
                        // --- PERUBAHAN DI BLOK INI ---
                        $waktu_sekarang_jakarta = date('Y-m-d H:i:s'); // 1. Ambil waktu PHP (Asia/Jakarta)
                        $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update)
                                    VALUES (?, ?, 0, ?, ?, ?)"; // 2. Ganti NOW() dengan ?
                        $stmt_histori = $conn->prepare($sql_histori);
                        if (!$stmt_histori) throw new Exception("Prepare insert histori gagal: " . $conn->error);
                        // 3. Tambahkan 's' untuk string waktu dan variabel $waktu_sekarang_jakarta
                        $stmt_histori->bind_param("iiiis", $id_produk_baru, $stock_awal, $stock_awal, $current_user_id, $waktu_sekarang_jakarta);
                        if (!$stmt_histori->execute()) throw new Exception("Execute insert histori gagal: " . $stmt_histori->error);
                        $stmt_histori->close();
                        // --- AKHIR PERUBAHAN ---
                    }

                    $conn->commit();
                    header('Location: index.php?menu=produk');
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error input produk: " . $e->getMessage());
                    echo "<script>alert('Gagal menyimpan produk: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
                }
                exit;
                break; // Akhir case input

            // --- HANYA ADMIN ---
            case 'update':
                if ($level_lowercase != 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>";
                    exit;
                }

                $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $nama_produk = $_POST['nama_produk'] ?? '';
                $jenis_produk = $_POST['jenis_produk'] ?? '';
                $harga_baru = isset($_POST['harga']) ? (float)$_POST['harga'] : 0;
                $stock_baru = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;

                if ($id_produk <= 0 || empty($nama_produk) || empty($jenis_produk)) {
                    echo "<script>alert('Data tidak lengkap.'); window.history.back();</script>";
                    exit;
                }
                if ($id_produk == 4) { // Proteksi ID 4
                    echo "<script>alert('Produk ID 4 (Galon) tidak boleh diedit manual.'); window.history.back();</script>";
                    exit;
                }

                $conn->begin_transaction();
                try {
                    // 1. Ambil stok lama (Kode Anda tidak berubah)
                    $stok_sebelum = 0;
                    $sql_get = "SELECT stock FROM produk WHERE id_produk = ? FOR UPDATE";
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

                    // 2. Update produk (Kode Anda tidak berubah)
                    $sql_update = "UPDATE produk SET nama_produk = ?, jenis_produk = ?, stock = ?, harga = ? WHERE id_produk = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare update produk gagal: " . $conn->error);
                    $stmt_update->bind_param("ssidi", $nama_produk, $jenis_produk, $stock_baru, $harga_baru, $id_produk);
                    if (!$stmt_update->execute()) throw new Exception("Execute update produk gagal: " . $stmt_update->error);
                    $stmt_update->close();

                    // 3. Catat ke histori HANYA JIKA stok berubah
                    $selisih_stok = $stock_baru - $stok_sebelum;
                    if ($selisih_stok != 0) {
                        // --- PERUBAHAN DI BLOK INI ---
                        $waktu_sekarang_jakarta = date('Y-m-d H:i:s'); // 1. Ambil waktu PHP (Asia/Jakarta)
                        $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update)
                                    VALUES (?, ?, ?, ?, ?, ?)"; // 2. Ganti NOW() dengan ?
                        $stmt_histori = $conn->prepare($sql_histori);
                        if (!$stmt_histori) throw new Exception("Prepare insert histori (update) gagal: " . $conn->error);
                        // 3. Tambahkan 's' untuk string waktu dan variabel $waktu_sekarang_jakarta
                        $stmt_histori->bind_param("iiiiis", $id_produk, $selisih_stok, $stok_sebelum, $stock_baru, $current_user_id, $waktu_sekarang_jakarta);
                        if (!$stmt_histori->execute()) throw new Exception("Execute insert histori (update) gagal: " . $stmt_histori->error);
                        $stmt_histori->close();
                        // --- AKHIR PERUBAHAN ---
                    }

                    $conn->commit();
                    header('Location: index.php?menu=produk');
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error update produk: " . $e->getMessage());
                    echo "<script>alert('Gagal update produk: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
                }
                exit;
                break; // Akhir case update

            // --- HANYA ADMIN ---
            case 'batal':
                if ($level_lowercase != 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.location.href='index.php?menu=produk';</script>";
                    exit;
                }
                $id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
                if ($id_produk <= 0) {
                    echo "<script>alert('ID Produk tidak valid.'); window.location.href='index.php?menu=produk';</script>";
                    exit;
                }
                if ($id_produk == 4 || $id_produk == 5) { // Proteksi ID 4 (Galon) dan 5 (Tutup)
                    echo "<script>alert('Produk inti (ID 4 atau 5) tidak boleh dihapus.'); window.location.href='index.php?menu=produk';</script>";
                    exit;
                }

                // Perlu cek foreign key (di pesanan / histori) sebelum hapus
                // Untuk saat ini, kita coba hapus langsung
                $sql_delete = "DELETE FROM produk WHERE id_produk = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                if (!$stmt_delete) {
                    echo "<script>alert('Gagal prepare delete: " . $conn->error . "'); window.location.href='index.php?menu=produk';</script>";
                    exit;
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

            // --- KASIR ---
            case 'tambah_stok':
                // Boleh diakses admin atau kasir
                if ($level_lowercase != 'kasir') {
                    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>";
                    exit;
                }

                $id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
                $jumlah_tambah = isset($_POST['jumlah_tambah']) ? (int)$_POST['jumlah_tambah'] : 0;

                // --- BARIS TAMBAHAN: Ambil data baru untuk histori ---
                $harga_beli_unit = isset($_POST['harga_pembelian']) ? (int)$_POST['harga_pembelian'] : 0;
                $jenis_transaksi = mysqli_real_escape_string($conn, $_POST['jenis_transaksi'] ?? 'Pembelian Manual');
                // --- AKHIR BARIS TAMBAHAN ---

                if ($id_produk <= 0 || $jumlah_tambah <= 0 || $harga_beli_unit <= 0) { // Validasi harga_beli
                    echo "<script>alert('ID Produk, Jumlah, atau Harga Pembelian tidak valid.'); window.history.back();</script>";
                    exit;
                }
                if ($id_produk == 4) { // Proteksi ID 4
                    echo "<script>alert('Stok Galon (ID 4) tidak boleh ditambah manual.'); window.history.back();</script>";
                    exit;
                }

                $conn->begin_transaction();
                try {
                    // 1. Ambil stok lama
                    $stok_sebelum = 0;
                    // Gunakan id_produk, bukan id, sesuai skema tabel 'produk' yang umum
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

                    // 2. Update stok produk
                    $sql_update = "UPDATE produk SET stock = ? WHERE id_produk = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare update stok gagal: " . $conn->error);
                    $stmt_update->bind_param("ii", $stok_sesudah, $id_produk);
                    if (!$stmt_update->execute()) throw new Exception("Execute update stok gagal: " . $stmt_update->error);
                    $stmt_update->close();

                    // 3. Masukkan ke histori
                    $waktu_sekarang_jakarta = date('Y-m-d H:i:s');

                    // --- PERBAIKAN QUERY INSERT HISTORI ---
                    $sql_histori = "INSERT INTO histori_stok (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update, jenis_transaksi, harga_pembelian_unit)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt_histori = $conn->prepare($sql_histori);
                    if (!$stmt_histori) throw new Exception("Prepare insert histori gagal: " . $conn->error);

                    // --- PERBAIKAN BINDING PARAMETER (8 placeholders: iiiiiisi) ---
                    // iiiiiis i: id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update (s), jenis_transaksi (s), harga_pembelian_unit (i)
                    $stmt_histori->bind_param(
                        "iiiiissi",
                        $id_produk,
                        $jumlah_tambah,
                        $stok_sebelum,
                        $stok_sesudah,
                        $current_user_id,
                        $waktu_sekarang_jakarta,
                        $jenis_transaksi,
                        $harga_beli_unit
                    );

                    if (!$stmt_histori->execute()) throw new Exception("Execute insert histori gagal: " . $stmt_histori->error);
                    $stmt_histori->close();
                    // --- AKHIR PERBAIKAN QUERY INSERT HISTORI ---

                    $conn->commit();
                    echo "<script>";
                    echo "alert('BERHASIL: Data stok dan histori berhasil disimpan.');";
                    // Tambahkan baris ini untuk redirect setelah alert ditutup
                    echo "window.location.href = 'index.php?menu=produk'";
                    echo "</script>";
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Error tambah stok: " . $e->getMessage());
                    echo "<script>alert('Gagal menambah stok: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
                }
                exit;
                break; // Akhir case tambah_stok
        }
        break; // Akhir case 'produk'

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

            case 'update':
                $id_pesanan = isset($_POST['id_pesanan']) ? (int)mysqli_real_escape_string($conn, $_POST['id_pesanan']) : 0;

                $id_plng = mysqli_real_escape_string($conn, $_POST['id_plng'] ?? '');
                $nama_plng = mysqli_real_escape_string($conn, $_POST['nama_plng'] ?? '');
                $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp'] ?? '');
                $tglpesan = mysqli_real_escape_string($conn, $_POST['tgl_pesan'] ?? '');

                $id_produk_baru = isset($_POST['id_produk']) ? (int)mysqli_real_escape_string($conn, $_POST['id_produk']) : 0;
                $nama_produk_baru = mysqli_real_escape_string($conn, $_POST['nama_produk'] ?? '');
                $jumlah_baru = isset($_POST['galon']) ? (int)mysqli_real_escape_string($conn, $_POST['galon']) : 0;
                $status_antar_baru = mysqli_real_escape_string($conn, $_POST['status_antar'] ?? '');
                $error_message_js = '';

                // Pengecekan dasar. Mengizinkan $jumlah_baru = 0 HANYA jika statusnya Batal.
                if ($id_pesanan <= 0 || $id_produk_baru <= 0 || empty($nama_produk_baru) || empty($status_antar_baru) || ($jumlah_baru <= 0 && $status_antar_baru != 'Batal')) {
                    echo "<script>alert('Error: Semua data wajib diisi saat update, kecuali jumlah saat status Batal.'); window.history.back();</script>";
                    exit;
                }

                $conn->begin_transaction();
                try {
                    // 1. Ambil data lama pesanan & status pengantaran lama (DARI TABEL PENGANTARAN)
                    $sql_get_old = "SELECT p.id_produk, p.galon as jumlah_lama, COALESCE(peng.status_pengantaran, 'N/A') as status_lama_antar
                        FROM pesanan p
                        LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                        WHERE p.id_pesanan = ?";
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
                    $status_lama = $data_lama['status_lama_antar'] == 'N/A' ? 'Belum Diproses' : $data_lama['status_lama_antar'];
                    $update_success_message = 'Data pesanan berhasil diupdate.';


                    if ($status_antar_baru == 'Batal') {

                        // =======================================================
                        // --- LOGIKA PEMBATALAN VIA UPDATE FORM ---
                        // =======================================================

                        $update_success_message = 'Pesanan berhasil dibatalkan. Stok telah dikembalikan.';

                        // A. Kembalikan Stok (Hanya jika status lama BUKAN Batal)
                        if ($status_lama != 'Batal' && $id_produk_lama == 4) {
                            // 1. Kembalikan stok Galon Isi Ulang (ID 4)
                            $sql_return_stok4 = "UPDATE produk SET stock = stock + ? WHERE id_produk = 4";
                            $stmt_return_stok4 = $conn->prepare($sql_return_stok4);
                            if (!$stmt_return_stok4) throw new Exception("Prepare return stok galon (Batal) gagal: " . $conn->error);
                            $stmt_return_stok4->bind_param("i", $jumlah_lama);
                            if (!$stmt_return_stok4->execute()) throw new Exception("Execute return stok galon (Batal) gagal: " . $stmt_return_stok4->error);
                            $stmt_return_stok4->close();

                            // 2. Kembalikan Tutup Galon (ID 5)
                            $sql_return_stok5 = "UPDATE produk SET stock = stock + ? WHERE id_produk = 5";
                            $stmt_return_stok5 = $conn->prepare($sql_return_stok5);
                            if (!$stmt_return_stok5) throw new Exception("Prepare return stok tutup (Batal) gagal: " . $conn->error);
                            $stmt_return_stok5->bind_param("i", $jumlah_lama);
                            if (!$stmt_return_stok5->execute()) throw new Exception("Execute return stok tutup (Batal) gagal: " . $stmt_return_stok5->error);
                            $stmt_return_stok5->close();
                        }

                        // B. Perbarui/Hapus data dari tabel terkait

                        // B.1. UPDATE Pengantaran menjadi 'Batal' (Cek apakah sudah ada entri, jika tidak, INSERT)
                        $sql_check_pengantaran = "SELECT id_pesanan FROM pengantaran WHERE id_pesanan = ?";
                        $stmt_check_pengantaran = $conn->prepare($sql_check_pengantaran);
                        $stmt_check_pengantaran->bind_param("i", $id_pesanan);
                        $stmt_check_pengantaran->execute();
                        $result_pengantaran = $stmt_check_pengantaran->get_result();
                        $stmt_check_pengantaran->close();

                        if ($result_pengantaran->num_rows > 0) {
                            // UPDATE status menjadi Batal
                            $sql_update_pengantaran = "UPDATE pengantaran SET status_pengantaran = 'Batal', waktu_selesai = NOW() WHERE id_pesanan = ?";
                        } else {
                            // INSERT baru dengan status Batal
                            $sql_update_pengantaran = "INSERT INTO pengantaran (id_pesanan, status_pengantaran, waktu_ambil) VALUES (?, 'Batal', NOW())";
                        }

                        $stmt_update_pengantaran = $conn->prepare($sql_update_pengantaran);
                        if (!$stmt_update_pengantaran) throw new Exception("Prepare update pengantaran (Batal) gagal: " . $conn->error);
                        $stmt_update_pengantaran->bind_param("i", $id_pesanan);
                        if (!$stmt_update_pengantaran->execute()) throw new Exception("Execute update pengantaran (Batal) gagal: " . $stmt_update_pengantaran->error);
                        $stmt_update_pengantaran->close();


                        // B.2. Hapus Pembayaran (DELETE)
                        $sql_delete_pembayaran = "DELETE FROM pembayaran WHERE id_pesanan = ?";
                        $stmt_delete_pembayaran = $conn->prepare($sql_delete_pembayaran);
                        if (!$stmt_delete_pembayaran) throw new Exception("Prepare delete pembayaran (Batal) gagal: " . $conn->error);
                        $stmt_delete_pembayaran->bind_param("i", $id_pesanan);
                        if (!$stmt_delete_pembayaran->execute()) throw new Exception("Execute delete pembayaran (Batal) gagal: " . $stmt_delete_pembayaran->error);
                        $stmt_delete_pembayaran->close();

                        // C. Update Pesanan Utama (Menggunakan query ASLI Anda)
                        // Note: Tidak ada harga_total di query ini.
                        $sql_update_pesanan = "UPDATE pesanan SET id_plng=?, nama_plng=?, no_telp=?, id_produk=?, nama_produk=?, galon=?, tgl_pesan=? WHERE id_pesanan = ?";

                        // Variabel untuk binding
                        $id_produk_for_update = $id_produk_lama;
                        $nama_produk_for_update = $nama_produk_baru;
                        $jumlah_for_update = $jumlah_lama; // Simpan kuantitas lama

                        $stmt_update_pesanan = $conn->prepare($sql_update_pesanan);
                        if (!$stmt_update_pesanan) throw new Exception("Prepare update pesanan (Batal) gagal: " . $conn->error);

                        // Binding (8 parameters): sssisisi
                        $stmt_update_pesanan->bind_param("sssisisi", $id_plng, $nama_plng, $no_telp, $id_produk_for_update, $nama_produk_for_update, $jumlah_for_update, $tglpesan, $id_pesanan);

                        if (!$stmt_update_pesanan->execute()) throw new Exception("Execute update pesanan (Batal) gagal: " . $stmt_update_pesanan->error);
                        $stmt_update_pesanan->close();
                    } else {

                        // =======================================================
                        // --- LOGIKA UPDATE NORMAL (Non-Batal) ---
                        // =======================================================

                        $harga_produk_baru = 0;
                        $perlu_kurangi_stok_baru = false;

                        // 1. Rollback Stok Lama (Jika ada perubahan kuantitas/produk ATAU jika status lama adalah Batal)
                        if ($id_produk_lama != $id_produk_baru || $jumlah_lama != $jumlah_baru || $status_lama == 'Batal') {

                            // Rollback stok lama (Hanya jika produk lama adalah Galon ID 4)
                            if ($jumlah_lama > 0 && $id_produk_lama == 4) {
                                $sql_stok_lama = "UPDATE produk SET stock = stock + ? WHERE id_produk = 4";
                                $stmt_stok_lama = $conn->prepare($sql_stok_lama);
                                if (!$stmt_stok_lama) throw new Exception("Prepare kembalikan stok galon gagal: " . $conn->error);
                                $stmt_stok_lama->bind_param("i", $jumlah_lama);
                                if (!$stmt_stok_lama->execute()) throw new Exception("Execute kembalikan stok galon gagal: " . $stmt_stok_lama->error);
                                $stmt_stok_lama->close();

                                $sql_stok_tutup_lama = "UPDATE produk SET stock = stock + ? WHERE id_produk = 5";
                                $stmt_stok_tutup_lama = $conn->prepare($sql_stok_tutup_lama);
                                if (!$stmt_stok_tutup_lama) throw new Exception("Prepare kembalikan stok tutup gagal: " . $conn->error);
                                $stmt_stok_tutup_lama->bind_param("i", $jumlah_lama);
                                if (!$stmt_stok_tutup_lama->execute()) throw new Exception("Execute kembalikan stok tutup gagal: " . $stmt_stok_tutup_lama->error);
                                $stmt_stok_tutup_lama->close();
                            }

                            $perlu_kurangi_stok_baru = true;
                        }

                        // 2. Cek dan Kurangi Stok Baru (jika diperlukan dan jika produknya Galon Isi Ulang)
                        if ($perlu_kurangi_stok_baru && $id_produk_baru == 4) {

                            $stok_produk_baru = 0;

                            // Ambil harga dan stok Galon Isi Ulang (ID 4)
                            $sql_cek_baru = "SELECT stock, harga FROM produk WHERE id_produk = 4";
                            $stmt_cek_baru = $conn->prepare($sql_cek_baru);
                            if (!$stmt_cek_baru) throw new Exception("Prepare cek stok baru gagal: " . $conn->error);
                            $stmt_cek_baru->execute();
                            $result_cek_baru = $stmt_cek_baru->get_result();
                            if ($row_baru = $result_cek_baru->fetch_assoc()) {
                                $stok_produk_baru = (int)$row_baru['stock'];
                                $harga_produk_baru = (float)$row_baru['harga'];
                            } else {
                                throw new Exception("Produk galon (ID: 4) tidak ditemukan.");
                            }
                            $stmt_cek_baru->close();

                            if ($jumlah_baru > $stok_produk_baru) {
                                throw new Exception("Stok Galon Isi Ulang ({$stok_produk_baru} tersedia) tidak mencukupi untuk jumlah baru ({$jumlah_baru}).");
                            }

                            $sql_cek_tutup = "SELECT stock FROM produk WHERE id_produk = 5";
                            $result_tutup = $conn->query($sql_cek_tutup);
                            $stok_tutup = $result_tutup->fetch_assoc()['stock'] ?? 0;

                            if ($jumlah_baru > $stok_tutup) {
                                throw new Exception("Stok Tutup Galon (ID 5) tidak mencukupi ({$stok_tutup} tersedia) untuk jumlah baru ({$jumlah_baru}).");
                            }

                            // Kurangi stok Galon Isi Ulang baru
                            $sql_stok_baru = "UPDATE produk SET stock = stock - ? WHERE id_produk = 4";
                            $stmt_stok_baru = $conn->prepare($sql_stok_baru);
                            if (!$stmt_stok_baru) throw new Exception("Prepare kurangi stok galon baru gagal: " . $conn->error);
                            $stmt_stok_baru->bind_param("i", $jumlah_baru);
                            if (!$stmt_stok_baru->execute()) throw new Exception("Execute kurangi stok galon baru gagal: " . $stmt_stok_baru->error);
                            $stmt_stok_baru->close();

                            // Kurangi stok Tutup Galon baru
                            $sql_stok_tutup_baru = "UPDATE produk SET stock = stock - ? WHERE id_produk = 5";
                            $stmt_stok_tutup_baru = $conn->prepare($sql_stok_tutup_baru);
                            if (!$stmt_stok_tutup_baru) throw new Exception("Prepare kurangi stok tutup baru gagal: " . $conn->error);
                            $stmt_stok_tutup_baru->bind_param("i", $jumlah_baru);
                            if (!$stmt_stok_tutup_baru->execute()) throw new Exception("Execute kurangi stok tutup baru gagal: " . $stmt_stok_tutup_baru->error);
                            $stmt_stok_tutup_baru->close();
                        } elseif ($id_produk_baru != 4) {
                            // Jika produk diubah ke NON-GALON, ambil harga produk tersebut
                            $sql_harga = "SELECT harga FROM produk WHERE id_produk = ?";
                            $stmt_harga = $conn->prepare($sql_harga);
                            if (!$stmt_harga) throw new Exception("Prepare ambil harga gagal: " . $conn->error);
                            $stmt_harga->bind_param("i", $id_produk_baru);
                            $stmt_harga->execute();
                            $result_harga = $stmt_harga->get_result();
                            $row_harga = $result_harga->fetch_assoc();
                            $harga_produk_baru = $row_harga ? (float)$row_harga['harga'] : 0;
                            $stmt_harga->close();
                        } else {
                            // Jika tidak ada perubahan kuantitas/produk (dan produknya Galon), ambil harga Galon
                            $sql_harga = "SELECT harga FROM produk WHERE id_produk = 4";
                            $result_harga = $conn->query($sql_harga);
                            $row_harga = $result_harga->fetch_assoc();
                            $harga_produk_baru = $row_harga ? (float)$row_harga['harga'] : 0;
                        }

                        // Hitung total tagihan baru
                        $total_tagihan_baru = $jumlah_baru * $harga_produk_baru;

                        // 3. Update pesanan utama (Perbaiki query SQL dan Binding agar sesuai dengan 8 kolom + id_pesanan)
                        $sql_update_pesanan = "UPDATE pesanan SET id_plng=?, nama_plng=?, no_telp=?, id_produk=?, nama_produk=?, galon=?, tgl_pesan=? WHERE id_pesanan = ?";

                        $stmt_update_pesanan = $conn->prepare($sql_update_pesanan);
                        if (!$stmt_update_pesanan) throw new Exception("Prepare update pesanan gagal: " . $conn->error);

                        // Binding: 8 parameter (sssisisi)
                        // JANGAN masukkan total_tagihan_baru di sini karena tabel pesanan HANYA memiliki 8 kolom!
                        $stmt_update_pesanan->bind_param("sssisisi", $id_plng, $nama_plng, $no_telp, $id_produk_baru, $nama_produk_baru, $jumlah_baru, $tglpesan, $id_pesanan);

                        if (!$stmt_update_pesanan->execute()) throw new Exception("Execute update pesanan gagal: " . $stmt_update_pesanan->error);
                        $stmt_update_pesanan->close();


                        // 4. Update/Insert Pengantaran dan Pembayaran

                        // 4a. Update/Insert Pengantaran
                        $sql_check_entry_antar = "SELECT id_pesanan FROM pengantaran WHERE id_pesanan = ?";
                        $stmt_check_antar = $conn->prepare($sql_check_entry_antar);
                        $stmt_check_antar->bind_param("i", $id_pesanan);
                        $stmt_check_antar->execute();
                        $result_check_antar = $stmt_check_antar->get_result();
                        $stmt_check_antar->close();

                        if ($result_check_antar->num_rows == 0) {
                            // INSERT baru
                            $sql_insert_pengantaran = "INSERT INTO pengantaran (id_pesanan, status_pengantaran) VALUES (?, ?)";
                            $stmt_insert_pengantaran = $conn->prepare($sql_insert_pengantaran);
                            if (!$stmt_insert_pengantaran) throw new Exception("Prepare insert pengantaran gagal: " . $conn->error);
                            $stmt_insert_pengantaran->bind_param("is", $id_pesanan, $status_antar_baru);
                            if (!$stmt_insert_pengantaran->execute()) throw new Exception("Execute insert pengantaran gagal: " . $stmt_insert_pengantaran->error);
                            $stmt_insert_pengantaran->close();
                        } else {
                            // UPDATE status pengantaran
                            $sql_update_pengantaran = "UPDATE pengantaran SET status_pengantaran=? WHERE id_pesanan=?";
                            $stmt_update_pengantaran = $conn->prepare($sql_update_pengantaran);
                            if (!$stmt_update_pengantaran) throw new Exception("Prepare update pengantaran gagal: " . $conn->error);
                            $stmt_update_pengantaran->bind_param("si", $status_antar_baru, $id_pesanan);
                            if (!$stmt_update_pengantaran->execute()) throw new Exception("Execute update pengantaran gagal: " . $stmt_update_pengantaran->error);
                            $stmt_update_pengantaran->close();
                        }


                        // 4b. Update/Insert Pembayaran
                        $sql_check_entry_bayar = "SELECT id_pesanan FROM pembayaran WHERE id_pesanan = ?";
                        $stmt_check_bayar = $conn->prepare($sql_check_entry_bayar);
                        $stmt_check_bayar->bind_param("i", $id_pesanan);
                        $stmt_check_bayar->execute();
                        $result_check_bayar = $stmt_check_bayar->get_result();
                        $stmt_check_bayar->close();

                        if ($result_check_bayar->num_rows == 0) {
                            // INSERT baru (diasumsikan Belum Lunas)
                            // Di sini kita TIDAK BISA tahu harga_total karena tidak ada di tabel pesanan.
                            // Kita harus mengandalkan perhitungan $total_tagihan_baru.
                            $sql_insert_pembayaran = "INSERT INTO pembayaran (id_pesanan, jumlah_pembayaran, status_pembayaran) VALUES (?, ?, 'Belum Lunas')";
                            $stmt_insert_pembayaran = $conn->prepare($sql_insert_pembayaran);
                            if (!$stmt_insert_pembayaran) throw new Exception("Prepare insert pembayaran gagal: " . $conn->error);
                            // Binding: id (integer, double)
                            $stmt_insert_pembayaran->bind_param("id", $id_pesanan, $total_tagihan_baru);
                            if (!$stmt_insert_pembayaran->execute()) throw new Exception("Execute insert pembayaran gagal: " . $stmt_insert_pembayaran->error);
                            $stmt_insert_pembayaran->close();
                        } else {
                            // UPDATE jumlah pembayaran
                            $sql_update_pembayaran = "UPDATE pembayaran SET jumlah_pembayaran=? WHERE id_pesanan=?";
                            $stmt_update_pembayaran = $conn->prepare($sql_update_pembayaran);
                            if (!$stmt_update_pembayaran) throw new Exception("Prepare update pembayaran gagal: " . $conn->error);
                            $stmt_update_pembayaran->bind_param("di", $total_tagihan_baru, $id_pesanan);
                            if (!$stmt_update_pembayaran->execute()) throw new Exception("Execute update pembayaran gagal: " . $stmt_update_pembayaran->error);
                            $stmt_update_pembayaran->close();
                        }
                    }

                    $conn->commit();
                    echo "<script>alert('" . $update_success_message . "');</script>";
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Gagal update pesanan ID {$id_pesanan}: " . $e->getMessage());
                    $error_message_js = str_replace(['"', "'", "\n", "\r"], ['\"', "\'", "\\n", "\\r"], $e->getMessage());
                    echo "<script>alert('Gagal mengupdate data pesanan: " . $error_message_js . "'); window.history.back();</script>";
                    exit;
                }

                // Redirect setelah sukses
                $redirect_params = [];
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar', 'limit', 'page'];
                foreach ($filters_to_check as $filter_key) {
                    if (isset($_POST[$filter_key]) && !empty($_POST[$filter_key])) {
                        $redirect_params[$filter_key] = $_POST[$filter_key];
                    } else if (isset($_GET[$filter_key]) && !empty($_GET[$filter_key])) {
                        $redirect_params[$filter_key] = $_GET[$filter_key];
                    }
                }
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
                            $sql_stok = "UPDATE produk SET stock = stock + ? WHERE id_produk = ?";
                            $stmt_stok = $conn->prepare($sql_stok);
                            if (!$stmt_stok) throw new Exception("Prepare kembalikan stok (batal) gagal: " . $conn->error);
                            $stmt_stok->bind_param("ii", $jumlah_batal, $id_produk_batal);
                            if (!$stmt_stok->execute()) throw new Exception("Execute kembalikan stok (batal) gagal: " . $stmt_stok->error);
                            $stmt_stok->close();
                        }
                        if ($id_produk_batal == 4 && $jumlah_batal > 0) {
                            $sql_stok_tutup = "UPDATE produk SET stock = stock + ? WHERE id_produk = 5";
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
                $tgl_bayar_input = date('Y-m-d H:i:s'); // Dari datetime-local (Y-m-d\TH:i)
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
            case 'verifikasi_lunas':
                $id_pesanan = isset($_GET['id_pesanan']) ? (int)$_GET['id_pesanan'] : 0;

                if ($id_pesanan <= 0) {
                    echo "<script>alert('Error: ID Pesanan tidak valid.'); window.history.back();</script>";
                    exit;
                }

                // Update status pembayaran menjadi 'Lunas'
                $sql = "UPDATE pembayaran SET status_pembayaran = 'Lunas' WHERE id_pesanan = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    error_log("Prepare verifikasi lunas gagal: " . $conn->error);
                    echo "<script>alert('Gagal menyiapkan verifikasi pembayaran.'); window.history.back();</script>";
                    exit;
                }

                $stmt->bind_param("i", $id_pesanan);

                if ($stmt->execute()) {
                    // Redirect kembali ke daftar pesanan, ambil filter dari GET
                    $redirect_params = ['menu' => 'pemesanan'];
                    $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                    foreach ($filters_to_check as $filter_key) if (!empty($_GET[$filter_key])) $redirect_params[$filter_key] = $_GET[$filter_key];
                    $redirect_url = 'index.php?' . http_build_query($redirect_params);
                    header('Location: ' . $redirect_url);
                    exit();
                } else {
                    error_log("Gagal execute verifikasi lunas ID {$id_pesanan}: " . $stmt->error);
                    echo "<script>alert('Error saat mengkonfirmasi pembayaran.'); window.history.back();</script>";
                    exit;
                }
                $stmt->close();
                break;

            // ====================================================================
            // 2. Aksi Tolak Bukti (dari status 'Menunggu Konfirmasi')
            // ====================================================================
            case 'tolak_bukti':
                $id_pesanan = isset($_GET['id_pesanan']) ? (int)$_GET['id_pesanan'] : 0;

                if ($id_pesanan <= 0) {
                    echo "<script>alert('Error: ID Pesanan tidak valid.'); window.history.back();</script>";
                    exit;
                }

                // 1. Ambil ID pembayaran dan URL bukti lama
                $sql_get_info = "SELECT id_pembayaran, bukti_pembayaran FROM pembayaran WHERE id_pesanan = ?";
                $stmt_get_info = $conn->prepare($sql_get_info);
                $stmt_get_info->bind_param("i", $id_pesanan);
                $stmt_get_info->execute();
                $result_info = $stmt_get_info->get_result();
                $info = $result_info->fetch_assoc();
                $stmt_get_info->close();

                if (!$info) {
                    echo "<script>alert('Error: Data pembayaran tidak ditemukan.'); window.history.back();</script>";
                    exit;
                }

                $id_pembayaran = $info['id_pembayaran'];
                $old_url = $info['bukti_pembayaran'];

                // 2. Hapus file lama dari R2 jika ada (asumsi deleteFromR2() tersedia)
                if (!empty($old_url)) {
                    deleteFromR2($old_url, $r2Config);
                }

                // 3. Update status pembayaran menjadi 'Belum Lunas' dan set bukti_pembayaran ke NULL
                $sql = "UPDATE pembayaran SET status_pembayaran = 'Belum Lunas', bukti_pembayaran = NULL WHERE id_pembayaran = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    error_log("Prepare tolak bukti gagal: " . $conn->error);
                    echo "<script>alert('Gagal menyiapkan tolak bukti pembayaran.'); window.history.back();</script>";
                    exit;
                }

                $stmt->bind_param("i", $id_pembayaran);

                if ($stmt->execute()) {
                    // Redirect kembali ke daftar pesanan, ambil filter dari GET
                    $redirect_params = ['menu' => 'pemesanan'];
                    $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                    foreach ($filters_to_check as $filter_key) if (!empty($_GET[$filter_key])) $redirect_params[$filter_key] = $_GET[$filter_key];
                    $redirect_url = 'index.php?' . http_build_query($redirect_params);
                    header('Location: ' . $redirect_url);
                    exit();
                } else {
                    error_log("Gagal execute tolak bukti ID {$id_pesanan}: " . $stmt->error);
                    echo "<script>alert('Error saat menolak bukti pembayaran.'); window.history.back();</script>";
                    exit;
                }
                $stmt->close();
                break;
        }
        break; // Akhir case 'pemesanan'

    /**
     * =================================================================
     * Menu: Pengantaran
     * =================================================================
     * Tidak melibatkan upload file.
     */
    case 'pengantaran':
        switch ($act) {
            case 'ambil_multi': // Aksi untuk anggota mengambil beberapa pesanan sekaligus
                if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'anggota') {
                    echo "Akses ditolak. Hanya Anggota.";
                    exit;
                }
                define('ADMIN_CONTEXT', true); // Tandai konteks admin untuk webhook.php
                $webhook_file_for_function = __DIR__ . '/../config/webhook.php';
                if (file_exists($webhook_file_for_function)) {
                    require_once $webhook_file_for_function;
                } else {
                    error_log("FATAL: File webhook tidak ditemukan di " . $webhook_file_for_function);
                    echo "Error: Komponen pengiriman notifikasi tidak ditemukan.";
                    exit;
                }
                $daftar_id_pengantaran = $_POST['id_pengantaran'] ?? [];

                if (!empty($daftar_id_pengantaran)) {
                    $id_user_pegawai = (int)$_SESSION['id_user'];
                    $user_login_pegawai = $_SESSION['user_login'] ?? 'Anggota';
                    // Baca Konfigurasi WA (Kode Anda tidak berubah)
                    $config_path_wa = __DIR__ . '/config_wa.php';
                    $config_wa = [];
                    if (file_exists($config_path_wa)) {
                        $config_wa = include $config_path_wa;
                    }
                    $accessToken = $config_wa['access_token'] ?? '';
                    $phoneNumberId = $config_wa['phone_number_id'] ?? '';
                    $canSendNotif = !empty($accessToken) && !empty($phoneNumberId);
                    if (!$canSendNotif) {
                        error_log("Peringatan di ambil_multi: Access Token atau Phone ID kosong. Tidak bisa kirim notif.");
                    }

                    $conn->begin_transaction();
                    try {
                        // --- PERUBAHAN DI BLOK INI ---
                        $waktu_sekarang_jakarta = date('Y-m-d H:i:s'); // 1. Ambil waktu PHP (Asia/Jakarta)

                        // Update status pengantaran yang dipilih menjadi 'Dalam Perjalanan'
                        $placeholders = implode(',', array_fill(0, count($daftar_id_pengantaran), '?'));
                        $sql_update = "UPDATE pengantaran SET id_user = ?, nama_pegawai = ?, status_pengantaran = 'Dalam Perjalanan', waktu_ambil = ? WHERE id_pengantaran IN ($placeholders) AND status_pengantaran = 'Diproses'"; // 2. Ganti NOW() dengan ?
                        $stmt_update = $conn->prepare($sql_update);
                        if (!$stmt_update) throw new Exception("Prepare update pengantaran gagal: " . $conn->error);

                        // 3. Tambahkan 's' untuk waktu dan $waktu_sekarang_jakarta ke parameter
                        $types = 'iss' . str_repeat('i', count($daftar_id_pengantaran)); // (id_user=i, nama=s, waktu=s)
                        $params = array_merge([$id_user_pegawai, $user_login_pegawai, $waktu_sekarang_jakarta], $daftar_id_pengantaran);
                        $stmt_update->bind_param($types, ...$params);
                        // --- AKHIR PERUBAHAN ---

                        if (!$stmt_update->execute()) throw new Exception("Execute update pengantaran gagal: " . $stmt_update->error);
                        $affected_rows = $stmt_update->affected_rows;
                        $stmt_update->close();

                        // Jika tidak ada pesanan yang berhasil diambil (mungkin sudah diambil orang lain), langsung redirect
                        if ($affected_rows == 0) {
                            $conn->commit();
                            header('location:index.php?menu=pengantaran_anggota');
                            exit();
                        }

                        // Loop untuk generate token & kirim notif WA (Kode Anda tidak berubah)
                        foreach ($daftar_id_pengantaran as $id_pengantaran) {
                            // Cek lagi apakah pesanan ini benar-benar diambil oleh user ini
                            $sql_check_owner = "SELECT id_user FROM pengantaran WHERE id_pengantaran = ?";
                            $stmt_check_owner = $conn->prepare($sql_check_owner);
                            $stmt_check_owner->bind_param("i", $id_pengantaran);
                            $stmt_check_owner->execute();
                            $owner_result = $stmt_check_owner->get_result()->fetch_assoc();
                            $stmt_check_owner->close();

                            if ($owner_result && $owner_result['id_user'] == $id_user_pegawai) {
                                // Generate & simpan token pelacakan
                                $tracking_token = bin2hex(random_bytes(16));
                                $sql_token = "UPDATE pengantaran SET tracking_token = ? WHERE id_pengantaran = ?";
                                $stmt_token = $conn->prepare($sql_token);
                                if (!$stmt_token) throw new Exception("Prepare update token gagal: " . $conn->error);
                                $stmt_token->bind_param("si", $tracking_token, $id_pengantaran);
                                if (!$stmt_token->execute()) throw new Exception("Execute update token gagal: " . $stmt_token->error);
                                $stmt_token->close();

                                // Ambil nomor telepon pelanggan
                                $sql_get_telp = "SELECT pes.no_telp FROM pengantaran peng JOIN pesanan pes ON peng.id_pesanan = pes.id_pesanan WHERE peng.id_pengantaran = ?";
                                $stmt_get_telp = $conn->prepare($sql_get_telp);
                                if (!$stmt_get_telp) throw new Exception("Prepare get telp gagal: " . $conn->error);
                                $stmt_get_telp->bind_param("i", $id_pengantaran);
                                $stmt_get_telp->execute();
                                $data_pesanan = $stmt_get_telp->get_result()->fetch_assoc();
                                $stmt_get_telp->close();
                            } // end if owner match
                        } // end foreach
                        $conn->commit(); // Simpan semua perubahan jika loop berhasil
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

                // --- TAMBAHAN ---
                // Ambil string daftar ID yang sedang dikerjakan (dari URL)
                $selected_ids_str = isset($_GET['selected_ids']) ? $_GET['selected_ids'] : '';
                // --- AKHIR TAMBAHAN ---

                $id_user_login = (int)$_SESSION['id_user'];
                if ($id_pengantaran <= 0 || $id_user_login <= 0) {
                    die("ID tidak valid.");
                }

                mysqli_begin_transaction($conn);
                try {
                    // --- PERUBAHAN DI BLOK INI ---
                    $waktu_sekarang_jakarta = date('Y-m-d H:i:s'); // 1. Ambil waktu PHP (Asia/Jakarta)

                    // Update status hanya jika ID pengantaran dan ID user cocok
                    $sql_update = "UPDATE pengantaran SET status_pengantaran = 'Selesai', waktu_selesai = ? WHERE id_pengantaran = ? AND id_user = ?"; // 2. Ganti NOW() dengan ?
                    $stmt_update = mysqli_prepare($conn, $sql_update);

                    // 3. Tambahkan 's' untuk string waktu dan ubah urutan parameter
                    mysqli_stmt_bind_param($stmt_update, "sii", $waktu_sekarang_jakarta, $id_pengantaran, $id_user_login);
                    mysqli_stmt_execute($stmt_update);
                    $affected = mysqli_stmt_affected_rows($stmt_update); // Cek apakah ada baris yang terupdate
                    mysqli_stmt_close($stmt_update);
                    // --- AKHIR PERUBAHAN ---

                    if ($affected > 0) {
                        mysqli_commit($conn); // Simpan jika berhasil update

                        // --- BLOK YANG DIUBAH (Logika Redirect) ---

                        // 1. Tentukan URL default (kembali ke daftar)
                        $redirect_url = 'index.php?menu=pengantaran_anggota';

                        // 2. Cek apakah kita punya daftar 'selected_ids'
                        if (!empty($selected_ids_str)) {
                            // Ubah string "101,102,103" menjadi array [101, 102, 103]
                            $id_array = explode(',', $selected_ids_str);

                            // 3. Cari index dari item yang baru saja selesai
                            $current_index = array_search($id_pengantaran, $id_array);

                            // 4. Cek apakah ada item SETELAH index ini
                            if ($current_index !== false && isset($id_array[$current_index + 1])) {
                                // Jika YA, ambil ID item berikutnya
                                $next_id_pengantaran = (int)$id_array[$current_index + 1];

                                // 5. Ubah URL redirect ke halaman detail item berikutnya
                                // Kita teruskan 'selected_ids_str' agar siklus ini berlanjut
                                $redirect_url = "index.php?menu=pengantaran_saya&act=detail&id_pengantaran=$next_id_pengantaran&selected_ids=" . urlencode($selected_ids_str);
                            }
                            // Jika tidak ada item berikutnya (itu yang terakhir), 
                            // $redirect_url akan tetap menjadi URL default (kembali ke daftar)
                        }

                        // 6. Lakukan redirect
                        header('Location: ' . $redirect_url);

                        // --- AKHIR BLOK YANG DIUBAH ---

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
        }// akhir case selesai


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
                    function format_rupiah($angka)
                    {
                        return "Rp " . number_format($angka, 0, ',', '.');
                    }
                }
                if (!function_exists('format_tanggal_indonesia')) {
                    function format_tanggal_indonesia($date)
                    {
                        $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                        $timestamp = strtotime($date);
                        if (!$timestamp) return $date;
                        return date('d', $timestamp) . ' ' . ($bulan[date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
                    }
                }
                function format_bulan_tahun_label($yyyy_mm)
                {
                    if (empty($yyyy_mm) || strpos($yyyy_mm, '-') === false) return $yyyy_mm;
                    $timestamp = strtotime($yyyy_mm . '-01');
                    if (!$timestamp) return $yyyy_mm;
                    $bulan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                    return ($bulan_map[date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
                }
                // Fungsi baru untuk format tanggal pendek (e.g., 26 Okt 2025)
                function format_tanggal_indo_short($yyyy_mm_dd)
                {
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
                    echo "Error: Silakan pilih jenis laporan.";
                    exit;
                }
                if ($jenis_laporan == 'histori_stok') {
                    if (empty($tanggal_awal) || empty($tanggal_akhir)) {
                        echo "Error: Silakan pilih periode tanggal untuk Laporan Histori Stok.";
                        exit;
                    }
                    if (!strtotime($tanggal_awal) || !strtotime($tanggal_akhir) || $tanggal_akhir < $tanggal_awal) {
                        echo "Error: Format tanggal tidak valid atau tanggal akhir sebelum tanggal awal.";
                        exit;
                    }
                    // Buat label periode untuk histori
                    $periode_laporan = format_tanggal_indo_short($tanggal_awal) . " s/d " . format_tanggal_indo_short($tanggal_akhir);
                } else { // Untuk penjualan & kualitas air
                    if (empty($bulan_tahun_laporan_input)) {
                        echo "Error: Silakan pilih periode bulan-tahun.";
                        exit;
                    }
                    // Pisahkan Tahun dan Bulan
                    $parts = explode('-', $bulan_tahun_laporan_input);
                    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || (int)$parts[1] < 1 || (int)$parts[1] > 12) {
                        echo "Error: Format periode tidak valid (seharusnya YYYY-MM).";
                        exit;
                    }
                    $tahun_laporan = (int)$parts[0];
                    $bulan_laporan_angka = (int)$parts[1];
                    // Buat label periode bulan-tahun
                    $periode_laporan = format_bulan_tahun_label($bulan_tahun_laporan_input);
                    $nama_bulan_laporan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
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
                        LEFT JOIN user u ON da.id_user = u.id_user
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
                    // $laporan_data dimulai kosong []

                    // Query berdasarkan range tanggal
                    $sql = "SELECT
                            hs.waktu_update,
                            p.nama_produk,
                            hs.jumlah_tambah,
                            hs.stok_sebelum,
                            hs.stok_sesudah,
                            u.user_login AS nama_petugas,
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
                        body {
                            font-family: Arial, sans-serif;
                            font-size: 14px;
                        }

                        .page-container {
                            width: 100%;
                        }

                        .report-header {
                            text-align: center;
                            border-bottom: 2px solid black;
                            padding-bottom: 10px;
                            margin-bottom: 20px;
                        }

                        .report-header h1 {
                            margin: 0;
                            font-size: 24px;
                        }

                        .report-header p {
                            margin: 5px 0 0;
                            font-size: 14px;
                        }

                        .report-title {
                            text-align: center;
                            margin-bottom: 25px;
                        }

                        .report-title h2 {
                            margin: 0;
                            font-size: 22px;
                            text-decoration: underline;
                        }

                        .report-title h3 {
                            margin: 5px 0 0;
                            font-size: 16px;
                            font-weight: normal;
                        }

                        .report-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 30px;
                            font-size: 14px;
                        }

                        .report-table th,
                        .report-table td {
                            border: 1px solid black;
                            padding: 8px;
                            text-align: left;
                            vertical-align: top;
                        }

                        .report-table th {
                            background-color: #f2f2f2;
                            text-align: center;
                        }

                        .report-table td.angka,
                        .report-table th.angka {
                            text-align: right;
                        }

                        .report-table .total-row td {
                            font-weight: bold;
                            background-color: #f2f2f2;
                        }

                        /* CSS Tanda Tangan Tunggal (Rata Kiri) */
                        /* Sesuaikan blok tanda tangan agar agak ke kanan */
                        /* Atur posisi blok tanda tangan */
                        .report-signature {
                            margin-top: 50px;
                            width: 250px;
                            /* Lebar blok */
                            margin-left: auto;
                            /* Otomatis geser ke kanan */
                            margin-right: -30px;
                            /* Jarak dari tepi kanan */
                            font-size: 14px;
                        }

                        /* Atur paragraf LANGSUNG di bawah .report-signature (Pekanbaru & Dibuat oleh) */
                        .report-signature>p {
                            margin-top: 0;
                            margin-bottom: 2px;
                            text-align: left;
                            /* Pastikan ini rata kiri */
                        }

                        /* Ruang kosong */
                        .signature-space {
                            height: 75px;
                            margin-top: 5px;
                            margin-bottom: 5px;
                        }

                        /* Atur div baru agar isinya rata TENGAH */
                        .signature-details {
                            text-align: center;
                            /* Terapkan rata tengah ke div ini */
                        }

                        /* Atur style NAMA di dalam div baru */
                        .signature-details .signature-name {
                            /* Lebih spesifik */
                            font-weight: bold;
                            text-decoration: underline;
                            margin-top: 0;
                            margin-bottom: 2px;
                            margin-right: 50px;
                        }

                        /* Atur style JABATAN di dalam div baru */
                        .signature-details .signature-job {
                            /* Lebih spesifik */
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
                                        <tr>
                                            <td colspan="4" style="text-align: center;">Tidak ada data pengecekan untuk bulan <?php echo $periode_laporan; ?>.</td>
                                        </tr>
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
                                        <th style="width: 20%;">Tanggal dan Waktu</th>
                                        <th style="width: 20%;">Nama Produk</th>
                                        <th class="angka" style="width: 13%;">Jumlah Tambah</th>
                                        <th class="angka" style="width: 13%;">Stok Sebelum</th>
                                        <th class="angka" style="width: 13%;">Stok Sesudah</th>
                                        <th class="angka" style="width: 13%;">Harga</th>
                                        <th style="width: 15%;">Petugas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporan_data)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center;">Tidak ada data histori stok untuk periode ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($laporan_data as $data): ?>
                                            <tr>
                                                <td><?php echo date('d M Y, H:i', strtotime($data['waktu_update'])); ?></td>
                                                <td><?php echo htmlspecialchars($data['nama_produk']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['jumlah_tambah']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['stok_sebelum']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['stok_sesudah']); ?></td>
                                                <td class="angka"><?php echo "Rp " . number_format($data['harga_pembelian_unit'], 0, ',', '.'); ?></td>
                                                <td><?php echo htmlspecialchars($data['nama_petugas'] ?? 'N/A'); ?></td>
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
                        'margin_left' => 15,
                        'margin_right' => 15,
                        'margin_top' => 15,
                        'margin_bottom' => 15,
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
        switch ($act) {
            case 'simpan_konfigurasi':

                // 1. Ambil dan bersihkan (sanitize) data POST
                $new_access_token = trim($_POST['access_token'] ?? '');
                $new_phone_id = trim($_POST['phone_number_id'] ?? '');
                $new_verify_token = trim($_POST['webhook_verify_token'] ?? '');

                // Cek koneksi DB dan field yang wajib diisi
                if (!isset($conn) || !$conn) {
                    $_SESSION['flash_message'] = "Koneksi database gagal. Pengaturan tidak disimpan.";
                    $_SESSION['flash_type'] = "alert-danger";
                    header("Location: index.php?menu=" . htmlspecialchars($menu));
                    exit;
                }

                if (empty($new_phone_id) || empty($new_verify_token)) {
                    $_SESSION['flash_message'] = "Phone Number ID dan Verify Token wajib diisi.";
                    $_SESSION['flash_type'] = "alert-danger";
                    header("Location: index.php?menu=" . htmlspecialchars($menu));
                    exit;
                }

                // 2. Logic Access Token: Jika input token BARU kosong, kita harus mempertahankan token LAMA
                $token_to_save = $new_access_token;

                if (empty($new_access_token)) {
                    // Ambil token lama dari database
                    $sql_get = "SELECT value FROM settings WHERE name = 'wa_access_token' LIMIT 1";
                    $result_get = mysqli_query($conn, $sql_get);
                    if ($result_get && $config = mysqli_fetch_assoc($result_get)) {
                        $token_to_save = $config['value']; // Gunakan nilai lama
                    }
                }

                // 3. Susun data dalam format key => value
                $data_to_save = [
                    'wa_access_token'     => $token_to_save,
                    'wa_phone_number_id'  => $new_phone_id,
                    'wa_verify_token'     => $new_verify_token
                ];

                // 4. Query untuk menyimpan/update (INSERT...ON DUPLICATE KEY UPDATE)
                $success = true;

                // Query ini akan melakukan INSERT jika 'name' belum ada, atau UPDATE jika sudah ada
                // ASUMSI: Kolom 'name' di tabel 'settings' adalah Primary Key atau Unique Index.
                $sql_template = "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)";

                // Gunakan Prepared Statement untuk keamanan
                if ($stmt = mysqli_prepare($conn, $sql_template)) {
                    foreach ($data_to_save as $name => $value) {
                        // Bind parameter: "ss" karena name (key) dan value (nilai) adalah string
                        mysqli_stmt_bind_param($stmt, "ss", $name, $value);
                        if (!mysqli_stmt_execute($stmt)) {
                            $success = false;
                            // Opsional: log error ke file
                            // error_log("Gagal menyimpan key {$name}: " . mysqli_stmt_error($stmt));
                            break; // Hentikan jika ada kegagalan
                        }
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $success = false;
                    // Opsional: log error prepare statement
                    // error_log("Gagal prepare statement: " . mysqli_error($conn));
                }

                // 5. Atur pesan flash dan redirect
                if ($success) {
                    $_SESSION['flash_message'] = "Konfigurasi WhatsApp berhasil disimpan ke database.";
                    $_SESSION['flash_type'] = "alert-success";
                } else {
                    $_SESSION['flash_message'] = "Gagal menyimpan konfigurasi ke database. Pastikan koneksi dan struktur tabel benar.";
                    $_SESSION['flash_type'] = "alert-danger";
                }

                // Redirect kembali ke halaman pengaturan admin
                header("Location: index.php?menu=" . htmlspecialchars($menu));
                exit;

                break;
            case 'test_webhook':

                // --- Logika untuk Test Webhook (Memerlukan data dari DB) ---

                $webhook_file_path = __DIR__ . '/../config/webhook.php';
                $test_number = trim($_POST['test_number'] ?? '');

                // 1. Ambil token dan ID terbaru dari DB
                $sql_get = "SELECT name, value FROM settings WHERE name IN ('wa_access_token', 'wa_phone_number_id') LIMIT 2";
                $result_get = mysqli_query($conn, $sql_get);

                $config_test = [];
                if ($result_get) {
                    while ($row = mysqli_fetch_assoc($result_get)) {
                        $config_test[$row['name']] = $row['value'];
                    }
                }

                $accessToken = $config_test['wa_access_token'] ?? null;
                $phoneNumberId = $config_test['wa_phone_number_id'] ?? null;

                // 2. Cek validasi data
                if (empty($accessToken) || empty($phoneNumberId)) {
                    $_SESSION['flash_message'] = 'Error: Access Token atau Phone Number ID belum dikonfigurasi di database settings.';
                    $_SESSION['flash_type'] = 'alert-danger';
                } elseif (empty($test_number) || !preg_match('/^62\d{9,15}$/', $test_number)) {
                    $_SESSION['flash_message'] = 'Error: Nomor telepon tujuan tes tidak valid (harus diawali 62).';
                    $_SESSION['flash_type'] = 'alert-danger';
                } else {
                    // 3. Lakukan pengiriman pesan tes
                    if (file_exists($webhook_file_path)) {
                        require_once $webhook_file_path;
                        $message_text = "Ini adalah pesan tes dari Pengaturan Admin Sipamis pada " . date('d-m-Y H:i:s');

                        if (function_exists('sendWhatsAppMessage')) {
                            // ASUMSI: sendWhatsAppMessage menggunakan $accessToken dan $phoneNumberId yang baru diambil dari DB
                            if (sendWhatsAppMessage($test_number, $message_text, $accessToken, $phoneNumberId)) {
                                $_SESSION['flash_message'] = 'Pesan tes berhasil dikirim ke ' . htmlspecialchars($test_number) . '.';
                                $_SESSION['flash_type'] = 'alert-success';
                            } else {
                                $_SESSION['flash_message'] = 'Gagal mengirim pesan tes ke ' . htmlspecialchars($test_number) . '. Cek log error API.';
                                $_SESSION['flash_type'] = 'alert-danger';
                            }
                        } else {
                            $_SESSION['flash_message'] = 'Error internal: Fungsi sendWhatsAppMessage tidak ditemukan di file webhook.';
                            $_SESSION['flash_type'] = 'alert-danger';
                        }
                    } else {
                        $_SESSION['flash_message'] = 'Error: File webhook tidak ditemukan.';
                        $_SESSION['flash_type'] = 'alert-danger';
                    }
                }

                // Redirect setelah selesai tes
                header('Location: index.php?menu=pengaturan_admin');
                exit;

                break;
        }
        break;

    case 'pengelola':
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
                $sql_cek = "SELECT id_user FROM user WHERE user_login = ?";
                $stmt_cek = $conn->prepare($sql_cek);
                $stmt_cek->bind_param("s", $user_login);
                $stmt_cek->execute();
                $result_cek = $stmt_cek->get_result();
                if ($result_cek->num_rows > 0) {
                    $stmt_cek->close();
                    echo "<script>alert('Error: Username \\'" . htmlspecialchars($user_login, ENT_QUOTES) . "\\' sudah digunakan. Silakan pilih username lain.'); window.history.back();</script>";
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
                    header('Location: index.php?menu=pengelola');
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
                $sql_cek_user = "SELECT id_user FROM user WHERE user_login = ? AND id_user != ?";
                $stmt_cek_user = $conn->prepare($sql_cek_user);
                $stmt_cek_user->bind_param("si", $user_login, $id_user);
                $stmt_cek_user->execute();
                $result_cek_user = $stmt_cek_user->get_result();
                if ($result_cek_user->num_rows > 0) {
                    $stmt_cek_user->close();
                    echo "<script>alert('Error: Username \\'" . htmlspecialchars($user_login, ENT_QUOTES) . "\\' sudah digunakan oleh pengguna lain.'); window.history.back();</script>";
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
                    $sql_update = "UPDATE user SET user_login = ?, nama_lengkap = ?, level = ?, password = ? WHERE id_user = ?";
                    $types = "ssssi"; // s=user_login, s=nama, s=level, s=pass, i=id
                    array_push($params, $user_login, $nama_lengkap, $level, $hash_password_baru, $id_user);
                } else {
                    // Jika password dikosongkan, JANGAN update password
                    $sql_update = "UPDATE user SET user_login = ?, nama_lengkap = ?, level = ? WHERE id_user = ?";
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
                    header('Location: index.php?menu=pengelola');
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
                    echo "<script>alert('Error: ID pengguna tidak valid.'); window.location.href='index.php?menu=pengelola';</script>";
                    exit;
                }

                // Cek agar tidak hapus diri sendiri
                if ($id_user == $current_user_id) {
                    echo "<script>alert('Error: Anda tidak dapat menghapus akun Anda sendiri.'); window.location.href='index.php?menu=pengelola';</script>";
                    exit;
                }

                $sql_delete = "DELETE FROM user WHERE id_user = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                if (!$stmt_delete) {
                    echo "<script>alert('Error SQL: Gagal menyiapkan query hapus.'); window.history.back();</script>";
                    exit;
                }
                $stmt_delete->bind_param("i", $id_user);

                if ($stmt_delete->execute()) {
                    header('Location: index.php?menu=pengelola');
                } else {
                    echo "<script>alert('Error: Gagal menghapus pengguna.'); window.location.href='index.php?menu=pengelola';</script>";
                }
                $stmt_delete->close();
                break;
        }
        break; // Akhir case 'pengelola'

    case 'pelanggan':

        if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') {
            echo "<script>alert('Akses ditolak!'); window.location.href='index.php';</script>";
            exit;
        }

        // --- 1. Ambil Pengaturan Webhook dari DB ---
        $webhook_status = 'nonaktif';
        $webhook_api_url = '';
        $query_settings = mysqli_query($conn, "SELECT name, value FROM settings WHERE name IN ('webhook_status', 'webhook_api_url')");
        if ($query_settings) {
            while ($row = mysqli_fetch_assoc($query_settings)) {
                if ($row['name'] === 'webhook_status') $webhook_status = $row['value'];
                if ($row['name'] === 'webhook_api_url') $webhook_api_url = $row['value'];
            }
        }
        $is_webhook_active = ($webhook_status === 'aktif' && !empty($webhook_api_url));

        switch ($act) {

            case 'buat_akun':
            case 'reset_akun':

                if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') {
                    echo "<script>alert('Akses ditolak!'); window.location.href='index.php';</script>";
                    exit;
                }

                // --- START: PENGAMBILAN KONFIGURASI WEBHOOK & WA ---
                $webhook_status = 'nonaktif';
                $wa_config = ['access_token' => null, 'phone_number_id' => null];

                $query_settings = mysqli_query($conn, "SELECT name, value FROM settings WHERE name IN ('webhook_status', 'wa_access_token', 'wa_phone_number_id')");

                if ($query_settings) {
                    while ($row = mysqli_fetch_assoc($query_settings)) {
                        if ($row['name'] === 'webhook_status') $webhook_status = $row['value'];
                        if ($row['name'] === 'wa_access_token') $wa_config['access_token'] = $row['value'];
                        if ($row['name'] === 'wa_phone_number_id') $wa_config['phone_number_id'] = $row['value'];
                    }
                }
                $is_webhook_active = ($webhook_status === 'aktif' && !empty($wa_config['access_token']) && !empty($wa_config['phone_number_id']));
                // --- END: PENGAMBILAN KONFIGURASI WEBHOOK & WA ---


                switch ($act) {

                    // --- PROSES BUAT AKUN & RESET AKUN ---
                    case 'buat_akun':
                    case 'reset_akun':

                        if ($id_plng <= 0) {
                            echo "<script>alert('Error: ID Pelanggan tidak valid.'); window.location.href='index.php?menu=pelanggan';</script>";
                            exit;
                        }

                        // Ambil data pelanggan yang relevan (Nama & No. Telp)
                        $sql_plng = "SELECT nama_plng, no_telp FROM data_plng WHERE id_plng = ?";
                        $stmt_plng = $conn->prepare($sql_plng);
                        $stmt_plng->bind_param("i", $id_plng);
                        $stmt_plng->execute();
                        $result_plng = $stmt_plng->get_result();
                        $data_plng = $result_plng->fetch_assoc();
                        $stmt_plng->close();

                        if (!$data_plng) {
                            echo "<script>alert('Error: Data pelanggan tidak ditemukan.'); window.location.href='index.php?menu=pelanggan';</script>";
                            exit;
                        }

                        // Format Nomor WA (628...)
                        $nomor_raw = $data_plng['no_telp'];
                        $nomor_wa_target = (substr($nomor_raw, 0, 1) === '0') ? ('62' . substr($nomor_raw, 1)) : $nomor_raw;

                        $username_baru = $nomor_wa_target;
                        $password_raw = generate_random_password(8);
                        $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
                        $action_label = ($act === 'buat_akun') ? 'Pembuatan Akun' : 'Reset Akun';

                        // Eksekusi Update ke Database
                        $sql_update = "UPDATE data_plng SET username_login = ?, password = ? WHERE id_plng = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        $stmt_update->bind_param("ssi", $username_baru, $password_hash, $id_plng);

                        if ($stmt_update->execute()) {

                            $pesan_wa = false; // Status pengiriman WA
                            $nomor_alert = $data_plng['no_telp'];

                            // --- 3. KIRIM NOTIFIKASI WA JIKA WEBHOOK AKTIF ---
                            if ($is_webhook_active) {
                                $message_wa = "*Notifikasi Akun {$action_label}*\n\n";
                                $message_wa .= "Yth. Pelanggan {$data_plng['nama_plng']},\n";
                                $message_wa .= "Akun Anda telah berhasil {$action_label} oleh Admin.\n\n";
                                $message_wa .= "*Detail Akun Baru:*\n";
                                $message_wa .= "Username: `{$username_baru}`\n";
                                $message_wa .= "Password: `{$password_raw}`\n\n";
                                $message_wa .= "Silakan gunakan detail ini untuk login.";

                                $pesan_wa = send_wa_notification_direct($nomor_wa_target, $message_wa, $wa_config);
                            }
                            // ----------------------------------------------------

                            // 4. Tampilkan Alert Admin
                            $pesan_alert = "{$action_label} Berhasil! Akun untuk {$data_plng['nama_plng']} berhasil diproses.\\n\\n";
                            $pesan_alert .= "Detail Akun:\\nUsername: {$username_baru}\\nPassword Baru: {$password_raw}";

                            if ($is_webhook_active) {
                                if ($pesan_wa) {
                                    $pesan_alert .= "\\n\\nNotifikasi WA berhasil dikirim ke {$nomor_alert}.";
                                } else {
                                    $pesan_alert .= "\\n\\n(WA GAGAL TERKIRIM). Cek log server. Harap kirimkan detail ini secara manual.";
                                }
                            } else {
                                $pesan_alert .= "\\n\\n(Webhook NONAKTIF / Konfigurasi WA belum lengkap). Harap kirimkan detail ini secara manual.";
                            }

                            echo "<script>alert('{$pesan_alert}'); window.location.href='index.php?menu=pelanggan';</script>";
                        } else {
                            echo "<script>alert('Error: Gagal memproses akun pelanggan.'); window.location.href='index.php?menu=pelanggan';</script>";
                        }
                        $stmt_update->close();
                        break;
                }


            case 'toggle_webhook':

                // Validasi Akses Admin
                if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') {
                    echo "<script>alert('Akses Ditolak!'); window.location.href='index.php';</script>";
                    exit();
                }

                // Validasi status
                if ($status_webhook === 'aktif' || $status_webhook === 'nonaktif') {

                    // 1. UPDATE STATUS WEBHOOK di tabel 'settings'
                    $sql = "UPDATE settings SET value = ? WHERE name = 'webhook_status'";
                    $stmt = mysqli_prepare($conn, $sql);

                    if (!$stmt) {
                        echo "<script>alert('Error SQL: Gagal menyiapkan query update webhook.'); window.location.href='index.php?menu=pelanggan';</script>";
                        exit();
                    }

                    mysqli_stmt_bind_param($stmt, "s", $status_webhook);
                    $success = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $pesan_status = ($status_webhook === 'aktif') ? 'diaktifkan' : 'dinonaktifkan';
                    $msg = $success ? "Status WA Webhook berhasil {$pesan_status}!" : "Gagal memperbarui status Webhook.";

                    // Menggunakan alert dan redirect
                    echo "<script>alert('{$msg}'); window.location.href='index.php?menu=pelanggan';</script>";
                } else {
                    echo "<script>alert('Status Webhook tidak valid.'); window.location.href='index.php?menu=pelanggan';</script>";
                }
                exit();
                break;
        }
        break;


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
