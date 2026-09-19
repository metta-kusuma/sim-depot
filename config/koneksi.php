
<?php
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database
$servername = "localhost";
$username = "root";
$password = "";
$database = "depot";

// Membuat koneksi yang akan digunakan oleh file lain (seperti index.php)
$conn = mysqli_connect($servername, $username, $password, $database);

// Cek koneksi
if (!$conn) {
    // Tampilkan error jika koneksi gagal
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Set charset untuk koneksi
mysqli_set_charset($conn, "utf8mb4");


// ==========================================================
// SCRIPT AUTO-RESET STOK HARIAN (DENGAN PENCATATAN HISTORI)
// ==========================================================

// Pastikan koneksi $conn sudah ada
if (isset($conn) && $conn) {

    $tanggal_hari_ini = date('Y-m-d'); // Format: 2025-11-01
    $file_pencatat = __DIR__ . '/last_run.txt';
    $last_run_date = null;

    // 1. Cek file pencatat
    if (file_exists($file_pencatat)) {
        $last_run_date = trim(file_get_contents($file_pencatat));
    }

    // 2. JIKA BELUM DIJALANKAN HARI INI...
    if ($last_run_date != $tanggal_hari_ini) {

        $conn->begin_transaction();
        try {

            $stok_sesudah_baru = 200; // Target stok baru HARIAN (misal: stok awal galon)
            $stok_sebelum = 0;

            // --- BLOK AMBIL STOK LAMA (Menggunakan FOR UPDATE) ---
            $sql_get = "SELECT stock FROM produk WHERE id_produk = 4 FOR UPDATE";
            $result_get = $conn->query($sql_get);

            if (!$result_get) throw new Exception("Query ambil stok gagal: " . $conn->error);

            if ($result_get->num_rows > 0) {
                $stok_sebelum = (int)$result_get->fetch_assoc()['stock'];
            } else {
                // Jika produk ID 4 tidak ditemukan, batalkan transaksi
                throw new Exception("Produk dengan ID 4 tidak ditemukan.");
            }
            // --- AKHIR BLOK AMBIL STOK LAMA ---


            // =======================================================
            // 3. PENGECEKAN KRITIS (Tambahan dari perbaikan)
            // =======================================================
            if ($stok_sebelum == $stok_sesudah_baru) {
                // Jika stok saat ini sudah SAMA dengan target (200), JANGAN LAKUKAN APA-APA.
                // Ini mencegah entri histori ganda atau update yang tidak perlu.

                // HANYA update file pencatat agar skrip tidak jalan lagi hari ini.
                if (file_put_contents($file_pencatat, $tanggal_hari_ini) === false) {
                    throw new Exception("Gagal menulis ke file last_run.txt.");
                }

                $conn->commit();
                // Keluar dari blok try
                return; // Atau menggunakan 'break 2' jika di dalam switch/loop yang lebih dalam.
            }

            // =======================================================
            // LANJUT JIKA STOK HARUS DI-UPDATE
            // =======================================================

            $selisih_stok = $stok_sesudah_baru - $stok_sebelum;
            $id_user = 1; // ID User untuk proses otomatis/sistem
            $waktu_sekarang_jakarta = date('Y-m-d H:i:s');


            // 4. JALANKAN UPDATE STOK (ID=4 ke 200)
            $sql_update_stok = "UPDATE produk SET stock = ? WHERE id_produk = 4";
            $stmt_update_stok = $conn->prepare($sql_update_stok);
            if (!$stmt_update_stok) throw new Exception("Prepare update stok gagal: " . $conn->error);
            $stmt_update_stok->bind_param("i", $stok_sesudah_baru);
            if (!$stmt_update_stok->execute()) throw new Exception("Execute update stok gagal: " . $stmt_update_stok->error);
            $stmt_update_stok->close();

            // 5. MASUKKAN KE HISTORI STOK
            $sql_histori = "INSERT INTO histori_stok 
                                (id_produk, jumlah_tambah, stok_sebelum, stok_sesudah, id_user, waktu_update, jenis_transaksi, harga_pembelian_unit)
                            VALUES (4, ?, ?, ?, ?, ?, 'Restock', 0)"; // id_produk = 4

            $stmt_histori = $conn->prepare($sql_histori);
            if (!$stmt_histori) throw new Exception("Prepare insert histori gagal: " . $conn->error);

            $stmt_histori->bind_param("iiiis", $selisih_stok, $stok_sebelum, $stok_sesudah_baru, $id_user, $waktu_sekarang_jakarta);
            if (!$stmt_histori->execute()) throw new Exception("Execute insert histori gagal: " . $stmt_histori->error);
            $stmt_histori->close();

            // 6. TULIS TANGGAL HARI INI ke file pencatat
            if (file_put_contents($file_pencatat, $tanggal_hari_ini) === false) {
                throw new Exception("Gagal menulis ke file last_run.txt. Cek folder permission.");
            }

            // Jika semua berhasil
            $conn->commit();
        } catch (Exception $e) {
            // Jika ada kegagalan, batalkan semua
            if (isset($conn)) {
                $conn->rollback();
            }
            error_log("CRON HARIAN GAGAL (reset_stock_galon / file): " . $e->getMessage());
        }
    }
}
// ==========================================================
// AKHIR SCRIPT AUTO-RESET
// ==========================================================

?>