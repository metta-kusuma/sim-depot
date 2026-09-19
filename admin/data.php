<?php

error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta'); // Atur zona waktu
session_start(); // Mulai atau lanjutkan sesi

require_once __DIR__ . '/laporan/vendor/autoload.php';


// --- Koneksi Database ---
include "../config/koneksi.php";
$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) {
    // Hentikan eksekusi jika koneksi DB gagal
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');




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
// Untuk Pengiriman Notifikasi Pembuatan Akun
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

switch ($menu) {

    case 'aktivitasdata':
        $menuParam = 'aktivitasdata';
        // Folder lokal tujuan
        $targetDir = __DIR__ . '/../assets/aktivitas_bukti/';

        // Pastikan folder tersedia
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        switch ($act) {
            case 'input':
                $tgl_aktivitas_otomatis = date('Y-m-d H:i:s');
                $jenis_pengecekan = $_POST['jenis_pengecekan'] ?? '';
                $hasil = $_POST['hasil'] ?? '';
                $keterangan = $_POST['keterangan'] ?? '';
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
                $file_name_db = '';

                if (empty($jenis_pengecekan) || $id_user <= 0) {
                    echo "<script>alert('Error: Data Jenis Pengecekan atau Petugas Tidak Boleh Kosong.'); window.history.back();</script>";
                    exit;
                }

                // --- 1. UPLOAD BUKTI KE LOKAL ---
                if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['bukti_bayar'];
                    $ext = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $new_file_name = "act_" . time() . "_" . rand(100, 999) . "." . $ext;
                    $target_file = $targetDir . $new_file_name;

                    if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                        $file_name_db = $new_file_name;
                    } else {
                        echo "<script>alert('Gagal memindahkan file ke folder server.'); window.history.back();</script>";
                        exit;
                    }
                }

                // --- 2. INSERT DB ---
                $sql = "INSERT INTO data_aktivitas (tgl_aktivitas, jenis_pengecekan, hasil, keterangan, id_user, bukti_url) 
                    VALUES (?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssssis', $tgl_aktivitas_otomatis, $jenis_pengecekan, $hasil, $keterangan, $id_user, $file_name_db);

                    if (mysqli_stmt_execute($stmt)) {
                        echo "<script>alert('BERHASIL: Data aktivitas berhasil disimpan.'); window.location.href = 'index.php?menu={$menuParam}';</script>";
                    } else {
                        // Hapus file jika DB gagal
                        if (!empty($file_name_db) && file_exists($targetDir . $file_name_db)) {
                            unlink($targetDir . $file_name_db);
                        }
                        echo "<script>alert('Error: Gagal Simpan Data ke database.'); window.history.back();</script>";
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'update':
                $id_aktivitas = $_POST['id_aktivitas'] ?? 0;
                $jenis_pengecekan = $_POST['jenis_pengecekan'] ?? '';
                $hasil = $_POST['hasil'] ?? '';
                $keterangan = $_POST['keterangan'] ?? '';
                $file_lama = $_POST['bukti_url_lama'] ?? '';
                $file_final = $file_lama;

                if ($id_aktivitas <= 0 || empty($jenis_pengecekan)) {
                    echo "<script>alert('Error: Data Wajib Diisi.'); window.history.back();</script>";
                    exit;
                }

                // --- 1. UPLOAD FILE BARU JIKA ADA ---
                if (isset($_FILES['bukti_bayar_edit']) && $_FILES['bukti_bayar_edit']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['bukti_bayar_edit'];
                    $ext = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $new_file_name = "act_upd_" . time() . "_" . rand(100, 999) . "." . $ext;
                    $target_file = $targetDir . $new_file_name;

                    if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                        $file_final = $new_file_name;
                        // Hapus file lama dari lokal
                        if (!empty($file_lama) && file_exists($targetDir . $file_lama)) {
                            unlink($targetDir . $file_lama);
                        }
                    } else {
                        echo "<script>alert('Gagal mengupload file baru.'); window.history.back();</script>";
                        exit;
                    }
                }

                // --- 2. UPDATE DB ---
                $sql = "UPDATE data_aktivitas SET jenis_pengecekan = ?, hasil = ?, keterangan = ?, bukti_url = ? WHERE id_aktivitas = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssssi', $jenis_pengecekan, $hasil, $keterangan, $file_final, $id_aktivitas);
                    if (mysqli_stmt_execute($stmt)) {
                        echo "<script>alert('BERHASIL: Data aktivitas berhasil diupdate!'); window.location.href = 'index.php?menu={$menuParam}';</script>";
                    } else {
                        echo "<script>alert('Error: Gagal mengupdate data.'); window.history.back();</script>";
                    }
                    mysqli_stmt_close($stmt);
                }
                break;

            case 'batal':
                $id_aktivitas = $_GET['id_aktivitas'] ?? 0;
                if ($id_aktivitas <= 0) {
                    echo "<script>alert('Error: ID Aktivitas Salah.'); window.history.back();</script>";
                    exit;
                }

                // 1. Ambil nama file sebelum hapus DB
                $res = mysqli_query($conn, "SELECT bukti_url FROM data_aktivitas WHERE id_aktivitas = $id_aktivitas");
                $row = mysqli_fetch_assoc($res);
                $file_to_delete = $row['bukti_url'] ?? '';

                // 2. Hapus dari database
                $sql_del = "DELETE FROM data_aktivitas WHERE id_aktivitas = ?";
                $stmt = mysqli_prepare($conn, $sql_del);
                mysqli_stmt_bind_param($stmt, 'i', $id_aktivitas);

                if (mysqli_stmt_execute($stmt)) {
                    // 3. Hapus file fisik jika ada
                    if (!empty($file_to_delete) && file_exists($targetDir . $file_to_delete)) {
                        unlink($targetDir . $file_to_delete);
                    }
                    echo "<script>alert('BERHASIL: Data berhasil dihapus!'); window.location.href='index.php?menu={$menuParam}';</script>";
                } else {
                    echo "<script>alert('Gagal menghapus data dari database.'); window.history.back();</script>";
                }
                break;
        }
        break;


    /** Menu: Member (Manajemen Pelanggan)
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
                    echo "<script>alert('Error: Terjadi Kesalahan.'); window.history.back();</script>";
                }
                $stmt_insert->close();
                echo "<script>";
                echo "alert('BERHASIL: Data pelanggan berhasil disimpan.');";
                // Tambahkan baris ini untuk redirect setelah alert ditutup
                echo "window.location.href = 'index.php?menu=member';";
                echo "</script>";
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

                // Cek duplikasi nomor telepon
                $sql_cek = "SELECT id_plng FROM data_plng WHERE no_telp = ? AND id_plng != ?";
                $stmt_cek = mysqli_prepare($conn, $sql_cek);
                mysqli_stmt_bind_param($stmt_cek, "ss", $no_telp, $id_plng);
                mysqli_stmt_execute($stmt_cek);
                $result_cek = mysqli_stmt_get_result($stmt_cek);

                // Penanganan Duplikasi
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

                // --- PENANGANAN SUKSES/GAGAL DAN ALERT SEDERHANA ---
                if ($stmt->execute()) {
                    // Jika Sukses: Tampilkan alert sukses dan redirect
                    echo "<script>";
                    echo "alert('BERHASIL: Data pelanggan telah diperbarui.');";
                    echo "window.location.href = 'index.php?menu=" . urlencode($menu) . "';";
                    echo "</script>";
                } else {
                    // Jika Gagal: Tampilkan alert gagal dan redirect
                    error_log("Gagal update member ID {$id_plng}: " . $stmt->error);
                    echo "<script>";
                    echo "alert('GAGAL: Terjadi kesalahan saat memperbarui data pelanggan.');";
                    echo "window.location.href = 'index.php?menu=" . urlencode($menu) . "';";
                    echo "</script>";
                }

                $stmt->close();

                exit();
                break;

            case 'batal':
                $id_plng = isset($_GET['id_plng']) ? (int)$_GET['id_plng'] : 0;
                if ($id_plng <= 0) {
                    die("ID Pelanggan tidak valid.");
                }
                // Query DELETE menggunakan prepared statement
                $sql_delete = "DELETE FROM data_plng WHERE id_plng=?";
                $stmt = $conn->prepare($sql_delete);
                $stmt->bind_param("i", $id_plng);
                if ($stmt->execute()) {
                    // Jika Sukses: Tampilkan alert sukses dan redirect
                    echo "<script>";
                    echo "alert('BERHASIL: Data pelanggan telah dihapus.');";
                    echo "window.location.href = 'index.php?menu=" . urlencode($menu) . "';";
                    echo "</script>";
                } else {
                    // Jika Gagal: Tampilkan alert gagal dan redirect
                    error_log("Gagal hapus member ID {$id_plng}: " . $stmt->error);
                    echo "<script>";
                    echo "alert('GAGAL: Terjadi kesalahan saat menghapus data pelanggan.');";
                    echo "window.location.href = 'index.php?menu=" . urlencode($menu) . "';";
                    echo "</script>";
                }
                exit();
                break;
        }
        break; // Akhir case 'member'

    /** Menu: Produk (Manajemen Stok Produk)
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
                    echo "<script>";
                    echo "alert('BERHASIL: Data produk berhasil disimpan.');";
                    // Tambahkan baris ini untuk redirect setelah alert ditutup
                    echo "window.location.href = 'index.php?menu=produk';";
                    echo "</script>";
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
                    echo "<script>";
                    echo "alert('BERHASIL: Data produk berhasil diupdate.');";
                    // Tambahkan baris ini untuk redirect setelah alert ditutup
                    echo "window.location.href = 'index.php?menu=produk';";
                    echo "</script>";
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
                    echo "<script>";
                    echo "alert('BERHASIL: Data produk berhasil dihapus.');";
                    // Tambahkan baris ini untuk redirect setelah alert ditutup
                    echo "window.location.href = 'index.php?menu=produk';";
                    echo "</script>";
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
                    echo "window.location.href = 'index.php?menu=produk';";
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

    /** Menu: Pemesanan (Input, Update, Batal, Pelunasan)
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
            case 'proses_pesanan':
                $id_pesanan = mysqli_real_escape_string($conn, $_GET['id_pesanan'] ?? 0);
                $filter_query = $_GET['filter_url_string'] ?? '';

                if (empty($id_pesanan) || !is_numeric($id_pesanan)) {
                    // Tampilkan pesan error atau redirect ke halaman list
                    header('Location: index.php?menu=pemesanan' . $filter_query);
                    exit;
                }

                // 1. Cek apakah record di tabel pengantaran sudah ada
                $sql_cek = "SELECT id_pengantaran, status_pengantaran FROM pengantaran WHERE id_pesanan = ?";
                $stmt_cek = mysqli_prepare($conn, $sql_cek);
                mysqli_stmt_bind_param($stmt_cek, "i", $id_pesanan);
                mysqli_stmt_execute($stmt_cek);
                $result_cek = mysqli_stmt_get_result($stmt_cek);
                $data_pengantaran = mysqli_fetch_assoc($result_cek);
                mysqli_stmt_close($stmt_cek);

                if ($data_pengantaran && $data_pengantaran['status_pengantaran'] != 'Belum Diproses') {
                    // Pesanan sudah diproses atau status lain
                    // Tampilkan pesan error atau redirect
                    $_SESSION['notif'] = ['type' => 'warning', 'msg' => 'Status pengantaran Pesanan #' . $id_pesanan . ' sudah ' . $data_pengantaran['status_pengantaran'] . '.'];
                    header('Location: index.php?menu=pemesanan' . $filter_query);
                    exit;
                }

                // 2. Jika belum ada, lakukan INSERT. Jika sudah ada (dan statusnya Belum Diproses), lakukan UPDATE.
                $status_baru = 'Diproses';
                if (!$data_pengantaran) {
                    // INSERT baru ke tabel pengantaran
                    $sql_ubah = "INSERT INTO pengantaran (id_pesanan, status_pengantaran) VALUES (?, ?)";
                } else {
                    // UPDATE status di tabel pengantaran
                    $sql_ubah = "UPDATE pengantaran SET status_pengantaran = ? WHERE id_pesanan = ?";
                }

                $stmt_ubah = mysqli_prepare($conn, $sql_ubah);

                if (!$data_pengantaran) {
                    mysqli_stmt_bind_param($stmt_ubah, "is", $id_pesanan, $status_baru);
                } else {
                    mysqli_stmt_bind_param($stmt_ubah, "si", $status_baru, $id_pesanan);
                }

                if (mysqli_stmt_execute($stmt_ubah)) {
                    // Sukses
                    $_SESSION['notif'] = ['type' => 'success', 'msg' => 'Status pengantaran Pesanan #' . $id_pesanan . ' berhasil diubah menjadi **Diproses**'];
                } else {
                    // Gagal
                    $_SESSION['notif'] = ['type' => 'danger', 'msg' => 'Gagal mengubah status pengantaran Pesanan #' . $id_pesanan . ': ' . mysqli_error($conn)];
                }

                mysqli_stmt_close($stmt_ubah);

                // Redirect kembali ke halaman list
                header('Location: index.php?menu=pemesanan' . $filter_query);
                exit;
                break;
            case 'update_pembayaran': // Aksi untuk melunasi pembayaran
                // Ambil data dari form pelunasan
                $id_pembayaran = isset($_POST['id_pembayaran']) ? (int)$_POST['id_pembayaran'] : 0;
                $id_pesanan = isset($_POST['id_pesanan']) ? (int)$_POST['id_pesanan'] : 0;
                $metode_pembayaran = $_POST['metode_pembayaran'] ?? null;
                $tgl_bayar_input = date('Y-m-d H:i:s');
                $file_name_db = null;

                // Konfigurasi Folder Lokal
                $target_dir = __DIR__ . '/../assets/bukti_pembayaran/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                // Validasi input dasar
                if ($id_pembayaran <= 0 || $id_pesanan <= 0 || empty($metode_pembayaran)) {
                    echo "<script>alert('Error: Data pembayaran tidak lengkap.'); window.history.back();</script>";
                    exit;
                }

                $tgl_bayar_db = date('Y-m-d H:i:s', strtotime($tgl_bayar_input));

                // --- 1. PROSES UPLOAD FILE (Jika ada file baru) ---
                if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['bukti_pembayaran'];
                    $file_extension = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (!in_array($file_extension, $allowed_ext)) {
                        echo "<script>alert('Error: Format file tidak diizinkan (Gunakan JPG, PNG, atau PDF).'); window.history.back();</script>";
                        exit;
                    }

                    if ($file_info["size"] > 5000000) {
                        echo "<script>alert('Error: Ukuran file terlalu besar (maks 5MB).'); window.history.back();</script>";
                        exit;
                    }

                    // Buat nama file unik
                    $new_file_name = "bayar_" . $id_pembayaran . "_" . time() . "." . $file_extension;
                    $target_file = $target_dir . $new_file_name;

                    if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                        $file_name_db = $new_file_name;

                        // --- 2. HAPUS FILE LAMA DARI SERVER ---
                        $sql_old = "SELECT bukti_pembayaran FROM pembayaran WHERE id_pembayaran = ?";
                        $stmt_old = $conn->prepare($sql_old);
                        $stmt_old->bind_param("i", $id_pembayaran);
                        $stmt_old->execute();
                        $result_old = $stmt_old->get_result()->fetch_assoc();
                        $old_file_name = $result_old['bukti_pembayaran'] ?? null;
                        $stmt_old->close();

                        if (!empty($old_file_name) && file_exists($target_dir . $old_file_name)) {
                            unlink($target_dir . $old_file_name);
                        }
                    } else {
                        echo "<script>alert('Error: Gagal menyimpan file ke server.'); window.history.back();</script>";
                        exit;
                    }
                } else {
                    // Jika tidak upload file baru, ambil nama file lama dari database
                    $sql_old = "SELECT bukti_pembayaran FROM pembayaran WHERE id_pembayaran = ?";
                    $stmt_old = $conn->prepare($sql_old);
                    $stmt_old->bind_param("i", $id_pembayaran);
                    $stmt_old->execute();
                    $file_name_db = $stmt_old->get_result()->fetch_assoc()['bukti_pembayaran'] ?? null;
                    $stmt_old->close();
                }

                // --- 3. UPDATE DATABASE ---
                $sql = "UPDATE pembayaran SET status_pembayaran = 'Lunas', metode_pembayaran = ?, tgl_pembayaran = ?, bukti_pembayaran = ? WHERE id_pembayaran = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    echo "<script>alert('Database Error: Gagal menyiapkan perintah.'); window.history.back();</script>";
                    exit;
                }

                $stmt->bind_param("sssi", $metode_pembayaran, $tgl_bayar_db, $file_name_db, $id_pembayaran);

                if ($stmt->execute()) {
                    // Logika Redirect dengan filter tetap terjaga
                    $redirect_params = [];
                    $filters = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                    foreach ($filters as $f) if (!empty($_POST[$f])) $redirect_params[$f] = $_POST[$f];

                    $query_string = !empty($redirect_params) ? '&' . http_build_query($redirect_params) : '';

                    echo "<script>
                alert('Berhasil: Pembayaran telah dilunasi.');
                window.location.href = 'index.php?menu=pemesanan$query_string';
              </script>";
                    exit();
                } else {
                    error_log("Update Pembayaran Gagal: " . $stmt->error);
                    echo "<script>alert('Error: Gagal memperbarui data di database.'); window.history.back();</script>";
                    exit;
                }
                break;

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

                // Path folder lokal tempat menyimpan bukti
                $target_dir = __DIR__ . '/../assets/bukti_pembayaran/';

                // 1. Ambil ID pembayaran dan Nama File bukti lama
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
                $old_file_name = $info['bukti_pembayaran'];

                // 2. Hapus file fisik dari folder lokal jika ada
                if (!empty($old_file_name)) {
                    $full_path = $target_dir . $old_file_name;
                    if (file_exists($full_path)) {
                        unlink($full_path); // Menghapus file secara permanen
                    }
                }

                // 3. Update status pembayaran menjadi 'Belum Lunas' dan kosongkan kolom bukti
                $sql = "UPDATE pembayaran SET status_pembayaran = 'Belum Lunas', bukti_pembayaran = NULL WHERE id_pembayaran = ?";
                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    error_log("Prepare tolak bukti gagal: " . $conn->error);
                    echo "<script>alert('Gagal menyiapkan proses penolakan.'); window.history.back();</script>";
                    exit;
                }

                $stmt->bind_param("i", $id_pembayaran);

                if ($stmt->execute()) {
                    // Redirect kembali dengan filter yang ada di URL (GET)
                    $redirect_params = ['menu' => 'pemesanan'];
                    $filters = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar'];
                    foreach ($filters as $f) {
                        if (!empty($_GET[$f])) $redirect_params[$f] = $_GET[$f];
                    }

                    $query_string = http_build_query($redirect_params);

                    echo "<script>
                alert('Bukti pembayaran berhasil ditolak dan dihapus.');
                window.location.href = 'index.php?$query_string';
              </script>";
                    exit();
                } else {
                    error_log("Gagal execute tolak bukti ID {$id_pesanan}: " . $stmt->error);
                    echo "<script>alert('Error saat memproses penolakan di database.'); window.history.back();</script>";
                    exit;
                }
                break;
        }
        break; // Akhir case 'pemesanan'

    /** Menu: Pengantaran
     */
    case 'pengantaran':
        switch ($act) {
            case 'ambil_multi': // Aksi untuk anggota mengambil beberapa pesanan sekaligus
                if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'anggota') {
                    echo "<script>alert('Error: Akses Ditolak Hanya Pengantar.'); window.history.back();</script>";
                    exit;
                }
                define('ADMIN_CONTEXT', true); // Tandai konteks admin untuk webhook.php
                $webhook_file_for_function = __DIR__ . '/../config/webhook.php';
                if (file_exists($webhook_file_for_function)) {
                    require_once $webhook_file_for_function;
                } else {
                    error_log("FATAL: File webhook tidak ditemukan di " . $webhook_file_for_function);
                    echo "<script>alert('Error: File Webhook Tidak Ditemukan.'); window.history.back();</script>";
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
                        echo "<script>alert('Error: Terjadi Kesalahan Saat Mengambil Pesanan.'); window.history.back();</script>";
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


    /** Menu: Laporan (Generate PDF)
     */
    case 'laporan':
        switch ($act) {
            case 'hasil': // Pastikan 'act' di form Anda adalah 'hasil'
                // Pastikan Anda sudah menyertakan koneksi $conn sebelum blok ini

                // Pengaturan Awal
                require_once __DIR__ . '/laporan/vendor/autoload.php';
                date_default_timezone_set('Asia/Jakarta');
                setlocale(LC_TIME, 'id_ID.utf8', 'id_ID');

                // --- FUNGSI HELPER ---
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
                function format_tanggal_indo_short($yyyy_mm_dd)
                {
                    if (empty($yyyy_mm_dd)) return '';
                    $timestamp = strtotime($yyyy_mm_dd);
                    if (!$timestamp) return $yyyy_mm_dd;
                    return date('d M Y', $timestamp);
                }

                // --- AMBIL DATA INPUT DARI FORM BARU ---
                $jenis_laporan = $_POST['jenis_laporan'] ?? '';
                $tipe_filter = $_POST['tipe_filter'] ?? '';
                $filter_value = $_POST['filter_value'] ?? '';
                $tanggal_awal = $_POST['tanggal_awal'] ?? '';
                $tanggal_akhir = $_POST['tanggal_akhir'] ?? '';
                $id_produk_filter = $_POST['id_produk'] ?? ''; // <--- INPUT PRODUK BARU

                // Variabel untuk Query dan Label
                $where_clause = "";
                $periode_laporan = "Semua Data"; // Default

                // --- LOGIKA PENENTUAN FILTER DAN VALIDASI ---

                if (empty($jenis_laporan)) {
                    echo "Error: Silakan pilih jenis laporan.";
                    exit;
                }

                if ($tipe_filter === 'rentang') {
                    if (empty($tanggal_awal) || empty($tanggal_akhir) || $tanggal_akhir < $tanggal_awal) {
                        echo "<script>alert('Error: Rentang tanggal tidak valid.'); window.history.back();</script>";
                        exit;
                    }
                    $where_clause = " AND tgl_kolom_tanggal BETWEEN '{$tanggal_awal}' AND '{$tanggal_akhir}'";
                    $periode_laporan = format_tanggal_indo_short($tanggal_awal) . " s/d " . format_tanggal_indo_short($tanggal_akhir);
                } elseif ($tipe_filter === 'bulan') {
                    if (empty($filter_value) || strpos($filter_value, '-') === false) {
                        echo "<script>alert('Error: Periode Bulan-Tahun tidak valid.'); window.history.back();</script>";
                        exit;
                    }
                    $parts = explode('-', $filter_value);
                    $tahun_laporan = (int)$parts[0];
                    $bulan_laporan_angka = (int)$parts[1];

                    $where_clause = " AND YEAR(tgl_kolom_tanggal) = {$tahun_laporan} AND MONTH(tgl_kolom_tanggal) = {$bulan_laporan_angka}";
                    $periode_laporan = format_bulan_tahun_label($filter_value);

                    $nama_bulan_laporan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                    $nama_bulan_laporan = $nama_bulan_laporan_map[$bulan_laporan_angka] ?? '';
                } elseif ($tipe_filter === 'tahun') {
                    if (empty($filter_value) || !ctype_digit($filter_value)) {
                        echo "<script>alert('Error: Periode Tahun tidak valid.'); window.history.back();</script>";
                        exit;
                    }
                    $tahun_laporan = (int)$filter_value;
                    $where_clause = " AND YEAR(tgl_kolom_tanggal) = {$tahun_laporan}";
                    $periode_laporan = "Tahun {$tahun_laporan}";
                }

                $tanggal_cetak_hari_ini = format_tanggal_indonesia(date('Y-m-d'));
                $judul_laporan = "";
                $laporan_data = [];

                // --- LOGIKA QUERY DATA ---

                if ($jenis_laporan == 'penjualan') {
                    $judul_laporan = "Laporan Penjualan Harian";
                    $grand_total_galon = 0;
                    $grand_total_bayar = 0;
                    $laporan_data = [];

                    // Gunakan filter_value dari POST (ini yang dikirim oleh script asli Anda untuk Bulan/Tahun)
                    $filter_value = $_POST['filter_value'] ?? '';
                    $tipe_filter = $_POST['tipe_filter'] ?? '';

                    if ($tipe_filter === 'bulan') {
                        // --- LOGIKA BULAN (Struktur Asli Anda) ---
                        $parts = explode('-', $filter_value);
                        $tahun_laporan = (int)($parts[0] ?? date('Y'));
                        $bulan_laporan_angka = (int)($parts[1] ?? date('m'));

                        $jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $bulan_laporan_angka, $tahun_laporan);
                        for ($i = 1; $i <= $jumlah_hari; $i++) {
                            $laporan_data[$i] = [
                                'tanggal' => str_pad($i, 2, '0', STR_PAD_LEFT),
                                'total_galon' => 0,
                                'total_bayar' => 0
                            ];
                        }

                        $sql = "SELECT DAY(p.tgl_pesan) AS hari_angka, SUM(p.galon) AS total_galon, SUM(pem.jumlah_pembayaran) AS total_bayar
                FROM pesanan p
                JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                WHERE YEAR(p.tgl_pesan) = ? AND MONTH(p.tgl_pesan) = ?
                AND pem.status_pembayaran = 'Lunas'
                GROUP BY DAY(p.tgl_pesan) ORDER BY hari_angka ASC";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $tahun_laporan, $bulan_laporan_angka);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $hari_angka = (int)$row['hari_angka'];
                            if (isset($laporan_data[$hari_angka])) {
                                $laporan_data[$hari_angka]['total_galon'] = $row['total_galon'];
                                $laporan_data[$hari_angka]['total_bayar'] = $row['total_bayar'];
                            }
                        }
                        $stmt->close();
                    }  elseif ($tipe_filter === 'rentang') {
                        $tgl_awal = $_POST['tanggal_awal'] ?? '';
                        $tgl_akhir = $_POST['tanggal_akhir'] ?? '';

                        // 1. KOSONGKAN ARRAY (Agar tidak ada data sisa/duplikat dari proses sebelumnya)
                        $laporan_data = [];

                        // 2. QUERY DENGAN GROUP BY DATE YANG KETAT
                        $sql = "SELECT 
                DATE(p.tgl_pesan) AS tgl_harian, 
                SUM(p.galon) AS total_galon, 
                SUM(pem.jumlah_pembayaran) AS total_bayar
            FROM pesanan p
            INNER JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
            WHERE p.tgl_pesan BETWEEN ? AND ?
            AND pem.status_pembayaran = 'Lunas'
            GROUP BY DATE(p.tgl_pesan) 
            ORDER BY tgl_harian ASC";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            // Simpan ke array dengan key tanggal agar tidak mungkin duplikat
                            $key = $row['tgl_harian'];
                            $laporan_data[$key] = [
                                'tanggal_label' => date('d M Y', strtotime($row['tgl_harian'])),
                                'total_galon' => $row['total_galon'],
                                'total_bayar' => $row['total_bayar']
                            ];
                        }
                        $stmt->close();
                    }

                    // Hitung Grand Total Akhir
                    foreach ($laporan_data as $data) {
                        $grand_total_galon += (float)($data['total_galon'] ?? 0);
                        $grand_total_bayar += (float)($data['total_bayar'] ?? 0);
                    }
                } elseif ($jenis_laporan == 'kualitas_air') {
                    $judul_laporan = "Laporan Kualitas Air Harian";

                    // Ganti kolom tanggal di $where_clause dengan tgl_aktivitas
                    $current_where_clause = str_replace('tgl_kolom_tanggal', 'da.tgl_aktivitas', $where_clause);

                    // Query detail berdasarkan filter (selalu detail per baris)
                    $sql = "SELECT
                            da.tgl_aktivitas,
                            da.jenis_pengecekan,
                            da.hasil,
                            u.user_login
                        FROM data_aktivitas da
                        LEFT JOIN user u ON da.id_user = u.id_user
                        WHERE da.tgl_aktivitas IS NOT NULL 
                            AND (da.jenis_pengecekan = 'Tes pH' OR da.jenis_pengecekan = 'TDS')
                            {$current_where_clause}
                        ORDER BY da.tgl_aktivitas ASC";

                    $result = mysqli_query($conn, $sql);

                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $laporan_data[] = $row;
                        }
                    }
                } elseif ($jenis_laporan == 'histori_stok') {
                    $judul_laporan = "Laporan Histori Stok Masuk";
                    $grand_total_harga = 0; // <-- INISIALISASI GRAND TOTAL HARGA

                    // --- LOGIKA FILTER PRODUK UNTUK HISTORI STOK ---
                    $filter_produk_clause = "";
                    if (!empty($id_produk_filter)) {
                        $id_produk_filter = (int) $id_produk_filter;
                        $filter_produk_clause = " AND hs.id_produk = {$id_produk_filter}";

                        // Opsional: Tambahkan detail filter ke label periode
                        $periode_laporan .= " (ID Produk: {$id_produk_filter})";
                    }
                    // --- AKHIR LOGIKA FILTER PRODUK ---


                    // Ganti kolom tanggal di $where_clause dengan waktu_update
                    $current_where_clause = str_replace('tgl_kolom_tanggal', 'hs.waktu_update', $where_clause);

                    // Query histori stok (DITAMBAH PERHITUNGAN TOTAL HARGA & FILTER PRODUK)
                    $sql = "SELECT
                            hs.waktu_update,
                            p.nama_produk,
                            hs.jumlah_tambah,
                            hs.stok_sebelum,
                            hs.stok_sesudah,
                            u.user_login AS nama_petugas,
                            hs.harga_pembelian_unit,
                            (hs.harga_pembelian_unit * hs.jumlah_tambah) AS total_harga_pembelian, 
                            hs.id_produk                                                            
                        FROM histori_stok hs
                        JOIN produk p ON hs.id_produk = p.id_produk
                        LEFT JOIN user u ON hs.id_user = u.id_user
                        WHERE hs.waktu_update IS NOT NULL
                            {$current_where_clause}
                            {$filter_produk_clause} /* <--- APLIKASI FILTER PRODUK */
                        ORDER BY hs.waktu_update ASC";

                    $result = mysqli_query($conn, $sql);

                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $laporan_data[] = $row;
                            // HITUNG GRAND TOTAL HARGA DI SINI
                            $grand_total_harga += (float)$row['total_harga_pembelian'];
                        }
                    } else {
                        error_log("Error query histori stok: " . $conn->error);
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
                        /* Style PDF */
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

                        .report-signature {
                            margin-top: 50px;
                            width: 250px;
                            margin-left: auto;
                            margin-right: -30px;
                            font-size: 14px;
                        }

                        .report-signature>p {
                            margin-top: 0;
                            margin-bottom: 2px;
                            text-align: left;
                        }

                        .signature-space {
                            height: 75px;
                            margin-top: 5px;
                            margin-bottom: 5px;
                        }

                        .signature-details {
                            text-align: center;
                        }

                        .signature-details .signature-name {
                            font-weight: bold;
                            text-decoration: underline;
                            margin-top: 0;
                            margin-bottom: 2px;
                            margin-right: 50px;
                        }

                        .signature-details .signature-job {
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
                                        <th style="width: 25%;">Tanggal / Periode</th>
                                        <th class="angka" style="width: 30%;">Total Galon</th>
                                        <th class="angka" style="width: 45%;">Total Bayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporan_data)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align:center;">Data tidak ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($laporan_data as $data): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    if ($tipe_filter === 'bulan') {
                                                        // Kembali ke format awal Anda: 01 Desember 2025
                                                        echo ($data['tanggal'] ?? '') . " " . ($nama_bulan_laporan ?? '') . " " . ($tahun_laporan ?? '');
                                                    } else {
                                                        // Gunakan label untuk Rentang dan Tahun
                                                        echo $data['tanggal_label'] ?? '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="angka"><?= number_format($data['total_galon'] ?? 0) ?></td>
                                                <td class="angka"><?= number_format($data['total_bayar'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                            <td colspan="4" style="text-align: center;">Tidak ada data pengecekan untuk periode <?php echo htmlspecialchars($periode_laporan); ?>.</td>
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
                            <?php
                            // Tentukan apakah kolom harga harus dicetak (Hanya dicetak jika BUKAN produk ID 4 yang difilter)
                            // Note: $id_produk_filter sudah di-set di PHP logic di atas.
                            $GALON_BERISI_ID = 4; // Asumsi ID Galon Berisi adalah 4
                            $show_harga_cols = (empty($id_produk_filter) || (int)$id_produk_filter != $GALON_BERISI_ID);

                            // Hitung jumlah kolom total untuk colspan
                            $total_cols_histori = $show_harga_cols ? 8 : 6;
                            ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th style="width: 17%;">Tanggal dan Waktu</th>
                                        <th style="width: 20%;">Nama Produk</th>
                                        <th class="angka" style="width: 10%;">Tambah</th>
                                        <th class="angka" style="width: 10%;">Stok Sebelum</th>
                                        <th class="angka" style="width: 10%;">Stok Sesudah</th>

                                        <?php if ($show_harga_cols): ?>
                                            <th class="angka" style="width: 15%;">Harga Unit</th>
                                            <th class="angka" style="width: 18%;">Total Harga Pembelian</th>
                                        <?php endif; ?>

                                        <th style="width: 10%;">Petugas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporan_data)): ?>
                                        <tr>
                                            <td colspan="<?php echo $total_cols_histori; ?>" style="text-align: center;">Tidak ada data histori stok untuk periode ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($laporan_data as $data): ?>
                                            <tr>
                                                <td><?php echo date('d M Y, H:i', strtotime($data['waktu_update'])); ?></td>
                                                <td><?php echo htmlspecialchars($data['nama_produk']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['jumlah_tambah']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['stok_sebelum']); ?></td>
                                                <td class="angka"><?php echo htmlspecialchars($data['stok_sesudah']); ?></td>

                                                <?php if ($show_harga_cols): ?>
                                                    <td class="angka">
                                                        <?php echo format_rupiah($data['harga_pembelian_unit']); ?>
                                                    </td>

                                                    <td class="angka">
                                                        <?php
                                                        if ((int)$data['id_produk'] == $GALON_BERISI_ID && $data['total_harga_pembelian'] == 0) {
                                                            echo 'Produksi Internal';
                                                        } else {
                                                            echo format_rupiah($data['total_harga_pembelian']);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endif; ?>

                                                <td><?php echo htmlspecialchars($data['nama_petugas'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (!empty($id_produk_filter)): ?>
                                            <tr class="total-row">
                                                <td colspan="6" style="text-align: right;"><b>GRAND TOTAL </b></td>

                                                <?php if ($show_harga_cols): ?>

                                                    <td class="angka" colspan="2" style="text-align: center;"><b><?php echo format_rupiah($grand_total_harga); ?></b></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endif; ?>

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

                    // Buat nama file yang akurat
                    $nama_file_base = strtolower(str_replace(' ', '_', $judul_laporan));
                    if ($tipe_filter == 'rentang') {
                        $nama_file = $nama_file_base . '_' . $tanggal_awal . '_sd_' . $tanggal_akhir . '.pdf';
                    } elseif ($tipe_filter == 'bulan') {
                        $nama_file = $nama_file_base . '_' . $filter_value . '.pdf';
                    } elseif ($tipe_filter == 'tahun') {
                        $nama_file = $nama_file_base . '_' . $filter_value . '.pdf';
                    } else {
                        $nama_file = $nama_file_base . '_all_data.pdf';
                    }

                    // Tambahkan ID produk ke nama file jika difilter
                    if (!empty($id_produk_filter) && $jenis_laporan == 'histori_stok') {
                        $nama_file = str_replace('.pdf', '_id' . $id_produk_filter . '.pdf', $nama_file);
                    }

                    $mpdf->Output($nama_file, 'I');
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
        // Konfigurasi Folder Lokal
        $target_dir = __DIR__ . '/../assets/dokumen_dinkes/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        switch ($act) {
            case 'upload':
                $judul_dokumen = $_POST['judul_dokumen'] ?? '';
                $tgl_laporan = $_POST['tgl_laporan'] ?? '';
                $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
                $file_name_db = null;

                // Validasi input teks
                if (empty($judul_dokumen) || empty($tgl_laporan) || $id_user <= 0 || !strtotime($tgl_laporan)) {
                    echo "<script>alert('Error: Judul, Tanggal Laporan, dan Petugas wajib diisi.'); window.history.back();</script>";
                    exit;
                }

                // --- 1. PROSES UPLOAD FILE KE LOKAL ---
                if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['file_laporan'];
                    $file_extension = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];

                    if (!in_array($file_extension, $allowed_ext)) {
                        echo "<script>alert('Error: Format file tidak diizinkan (Gunakan PDF, JPG, atau PNG).'); window.history.back();</script>";
                        exit;
                    }

                    if ($file_info["size"] > 5 * 1024 * 1024) {
                        echo "<script>alert('Error: Ukuran file terlalu besar (maks 5MB).'); window.history.back();</script>";
                        exit;
                    }

                    // Penamaan unik: dinkes_TIMESTAMP_RAND.ekstensi
                    $new_file_name = "dinkes_" . time() . "_" . rand(100, 999) . "." . $file_extension;
                    $target_file = $target_dir . $new_file_name;

                    if (move_uploaded_file($file_info["tmp_name"], $target_file)) {
                        $file_name_db = $new_file_name;
                    } else {
                        echo "<script>alert('Error: Gagal memindahkan file ke folder server.'); window.history.back();</script>";
                        exit;
                    }
                } else {
                    echo "<script>alert('Error: File laporan wajib diupload.'); window.history.back();</script>";
                    exit;
                }

                // --- 2. SIMPAN KE DATABASE ---
                $sql = "INSERT INTO dokumen_resmi (tgl_laporan, judul_dokumen, path_file, id_user) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $tgl_laporan, $judul_dokumen, $file_name_db, $id_user);

                if ($stmt->execute()) {
                    echo "<script>
                        alert('BERHASIL: Data Pengujian Dinkes berhasil disimpan.');
                        window.location.href = 'index.php?menu=arsip_dinkes';
                      </script>";
                } else {
                    // Hapus file fisik jika database gagal
                    if ($file_name_db && file_exists($target_dir . $file_name_db)) {
                        unlink($target_dir . $file_name_db);
                    }
                    error_log("Gagal simpan DB: " . $stmt->error);
                    echo "<script>alert('Error: Gagal menyimpan data ke database.'); window.history.back();</script>";
                }
                $stmt->close();
                break;

            case 'hapus':
                $id_dokumen = isset($_GET['id_dokumen']) ? (int)$_GET['id_dokumen'] : 0;
                if ($id_dokumen <= 0) {
                    echo "<script>alert('Error: ID Dokumen tidak valid.'); window.history.back();</script>";
                    exit;
                }

                // 1. Ambil nama file dari DB
                $sql_select = "SELECT path_file FROM dokumen_resmi WHERE id_dokumen = ?";
                $stmt_select = $conn->prepare($sql_select);
                $stmt_select->bind_param("i", $id_dokumen);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $row = $result->fetch_assoc();
                $stmt_select->close();

                if ($row) {
                    $file_to_delete = $row['path_file'];

                    // 2. Hapus data dari database
                    $sql_delete = "DELETE FROM dokumen_resmi WHERE id_dokumen = ?";
                    $stmt_delete = $conn->prepare($sql_delete);
                    $stmt_delete->bind_param("i", $id_dokumen);

                    if ($stmt_delete->execute()) {
                        // 3. Hapus file fisik dari server jika ada
                        if (!empty($file_to_delete)) {
                            $full_path = $target_dir . $file_to_delete;
                            if (file_exists($full_path)) {
                                unlink($full_path);
                            }
                        }
                        echo "<script>
                            alert('BERHASIL: Data Arsip Dinkes berhasil dihapus.');
                            window.location.href = 'index.php?menu=arsip_dinkes';
                          </script>";
                    } else {
                        echo "<script>alert('Error: Gagal menghapus data dari database.'); window.history.back();</script>";
                    }
                    $stmt_delete->close();
                } else {
                    echo "<script>alert('Error: Data tidak ditemukan.'); window.history.back();</script>";
                }
                break;
        }
        break;

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
