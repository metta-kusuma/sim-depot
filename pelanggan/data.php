<?php

session_start();
date_default_timezone_set('Asia/Jakarta');

// --- 1. PENJAGA PELANGGAN ---
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'pelanggan') {
    http_response_code(403);
    die("Akses ditolak. Silakan login sebagai pelanggan.");
}


include "../config/koneksi.php";


$menu = $_GET['menu'] ?? $_POST['menu'] ?? '';
$act = $_GET['act'] ?? $_POST['act'] ?? '';


switch ($menu) {

    case 'pesanan':
        switch ($act) {

            case 'input':
                try {
                    $id_plng = (int)$_SESSION['id_plng'];
                    $nama_plng = $_SESSION['nama_plng'];
                    $no_telp = $_SESSION['no_telp'];

                    $id_produk = (int)$_POST['id_produk'];
                    $nama_produk = $_POST['nama_produk'];
                    $jumlah = (int)$_POST['galon'];
                    $tgl_pesan = date('Y-m-d H:i:s');

                    if ($jumlah <= 0 || $id_produk <= 0) {
                        throw new Exception("Jumlah atau produk tidak valid.");
                    }
                } catch (Exception $e) {
                    echo "<h1>Error Data Tidak Lengkap</h1>";
                    echo "<p>Terjadi kesalahan: " . $e->getMessage() . "</p>";
                    echo "<a href='../pelanggan/index.php?menu=pesanan_saya&act=tambah'>Kembali ke formulir pesanan</a>";
                    exit;
                }

                // MULAI TRANSAKSI
                $conn->begin_transaction();
                try {

                    $sql_produk_info = "SELECT harga, stock FROM produk WHERE id_produk = ? FOR UPDATE";
                    $stmt_produk_info = $conn->prepare($sql_produk_info);
                    $stmt_produk_info->bind_param("i", $id_produk);
                    $stmt_produk_info->execute();
                    $result_produk_info = $stmt_produk_info->get_result();

                    if ($result_produk_info->num_rows === 0) {
                        throw new Exception("Produk tidak ditemukan (ID: $id_produk).");
                    }

                    $row_produk = $result_produk_info->fetch_assoc();
                    $harga_produk = (float)$row_produk['harga'];
                    $stok_sekarang = (int)$row_produk['stock'];
                    $stmt_produk_info->close();

                    if ($jumlah > $stok_sekarang) {
                        throw new Exception("Stok produk '" . htmlspecialchars($nama_produk) . "' tidak mencukupi ({$stok_sekarang} tersedia).");
                    }
                    if ($id_produk == 4) {
                        $sql_cek_tutup = "SELECT stock FROM produk WHERE id_produk = 5 FOR UPDATE";
                        $result_tutup = $conn->query($sql_cek_tutup);
                        if (!$result_tutup) throw new Exception("Gagal cek stok tutup: " . $conn->error);

                        $stok_tutup = $result_tutup->fetch_assoc()['stock'] ?? 0;
                        if ($jumlah > $stok_tutup) {
                            throw new Exception("Stok Tutup Galon (ID 5) tidak mencukupi ({$stok_tutup} tersedia).");
                        }
                    }

                    $total_tagihan = $jumlah * $harga_produk;

                    // (A) Insert ke tabel 'pesanan'
                    $sql_pesanan = "INSERT INTO pesanan(id_plng, nama_plng, no_telp, id_produk, nama_produk, galon, tgl_pesan) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_pesanan = $conn->prepare($sql_pesanan);
                    if (!$stmt_pesanan) throw new Exception("Prepare insert pesanan gagal: " . $conn->error);
                    $stmt_pesanan->bind_param("ississs", $id_plng, $nama_plng, $no_telp, $id_produk, $nama_produk, $jumlah, $tgl_pesan);
                    if (!$stmt_pesanan->execute()) throw new Exception("Execute insert pesanan gagal: " . $stmt_pesanan->error);
                    $id_pesanan_baru = $conn->insert_id;
                    $stmt_pesanan->close();

                    // (B) Kurangi Stok Produk Utama
                    $sql_update_stok = "UPDATE produk SET stock = stock - ? WHERE id_produk = ?";
                    $stmt_stok = $conn->prepare($sql_update_stok);
                    if (!$stmt_stok) throw new Exception("Prepare update stok produk gagal: " . $conn->error);
                    $stmt_stok->bind_param("ii", $jumlah, $id_produk);
                    if (!$stmt_stok->execute()) throw new Exception("Execute update stok produk gagal: " . $stmt_stok->error);
                    $stmt_stok->close();

                    // (C) Kurangi Stok Tutup jika perlu
                    if ($id_produk == 4) {
                        $sql_update_tutup = "UPDATE produk SET stock = stock - ? WHERE id_produk = 5";
                        $stmt_tutup = $conn->prepare($sql_update_tutup);
                        if (!$stmt_tutup) throw new Exception("Prepare update stok tutup gagal: " . $conn->error);
                        $stmt_tutup->bind_param("i", $jumlah);
                        if (!$stmt_tutup->execute()) throw new Exception("Execute update stok tutup gagal: " . $stmt_tutup->error);
                        $stmt_tutup->close();
                    }

                    // (D) Insert ke tabel 'pengantaran'
                    $status_antar_default = 'Belum Diproses';
                    $sql_pengantaran = "INSERT INTO pengantaran (id_pesanan, status_pengantaran) VALUES (?, ?)";
                    $stmt_pengantaran = $conn->prepare($sql_pengantaran);
                    if (!$stmt_pengantaran) throw new Exception("Prepare insert pengantaran gagal: " . $conn->error);
                    $stmt_pengantaran->bind_param("is", $id_pesanan_baru, $status_antar_default);
                    if (!$stmt_pengantaran->execute()) throw new Exception("Execute insert pengantaran gagal: " . $stmt_pengantaran->error);
                    $stmt_pengantaran->close();

                    // (E) Insert ke tabel 'pembayaran'
                    $status_bayar_default = 'Belum Lunas';
                    $sql_pembayaran = "INSERT INTO pembayaran (id_pesanan, jumlah_pembayaran, status_pembayaran) VALUES (?, ?, ?)";
                    $stmt_pembayaran = $conn->prepare($sql_pembayaran);
                    if (!$stmt_pembayaran) throw new Exception("Prepare insert pembayaran gagal: " . $conn->error);
                    $stmt_pembayaran->bind_param("ids", $id_pesanan_baru, $total_tagihan, $status_bayar_default);
                    if (!$stmt_pembayaran->execute()) throw new Exception("Execute insert pembayaran gagal: " . $stmt_pembayaran->error);
                    $stmt_pembayaran->close();

                    $conn->commit();
                    header("Location: ../pelanggan/index.php?menu=pesanan_saya&act=detail&id_pesanan=" . $id_pesanan_baru);
                    exit();
                } catch (Exception $e) {
                    // Jika terjadi error, BATALKAN SEMUA
                    $conn->rollback();
                    error_log("Gagal input pesanan oleh pelanggan ID {$id_plng}: " . $e->getMessage());

                    // Tampilkan pesan error
                    echo "<h1>Pesanan Gagal Dibuat!</h1>";
                    echo "<p>Terjadi kesalahan: " . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<a href='../pelanggan/index.php?menu=pesanan_saya&act=tambah'>Kembali ke formulir pesanan</a>";
                }

                break; // Akhir case 'input'
        }
        break;

    case 'pembayaran':
        switch ($act) {
            case 'bayar':
                $id_pesanan = (int)$_POST['id_pesanan'];
                $id_plng = (int)$_SESSION['id_plng'];
                $file_name_db = null;

                $target_dir = __DIR__ . '/../assets/bukti_pembayaran/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                $conn->begin_transaction();
                try {
                    // 1. Validasi File Upload
                    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] != UPLOAD_ERR_OK) {
                        $errorCode = $_FILES['bukti_pembayaran']['error'] ?? 'No File';
                        throw new Exception("File bukti pembayaran tidak terkirim atau rusak (Error: $errorCode).");
                    }

                    $file_info = $_FILES['bukti_pembayaran'];
                    $file_extension = strtolower(pathinfo($file_info["name"], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png'];

                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception("Format tidak didukung! Gunakan JPG atau PNG.");
                    }

                    if ($file_info["size"] > 5000000) { // 5MB
                        throw new Exception("Ukuran file terlalu besar! Maksimal adalah 5MB.");
                    }

                    // 2. Proses Simpan File ke Lokal
                    $new_file_name = "bukti_" . $id_pesanan . "_" . time() . "." . $file_extension;
                    $target_file = $target_dir . $new_file_name;

                    if (!move_uploaded_file($file_info["tmp_name"], $target_file)) {
                        throw new Exception("Gagal menyimpan file ke server. Cek izin folder assets.");
                    }

                    $file_name_db = $new_file_name;

                    // 3. Update Database
                    $new_status = 'Menunggu Konfirmasi';
                    $metode = 'Transfer Bank';
                    $tgl_bayar = date('Y-m-d H:i:s');

                    $sql_update = "UPDATE pembayaran pem
                               JOIN pesanan p ON pem.id_pesanan = p.id_pesanan 
                               SET 
                                   pem.status_pembayaran = ?, 
                                   pem.metode_pembayaran = ?, 
                                   pem.tgl_pembayaran = ?, 
                                   pem.bukti_pembayaran = ? 
                               WHERE 
                                   pem.id_pesanan = ? 
                                   AND p.id_plng = ? 
                                   AND pem.status_pembayaran = 'Belum Lunas'";

                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Database Error: Gagal menyiapkan perintah update.");

                    $stmt_update->bind_param("ssssii", $new_status, $metode, $tgl_bayar, $file_name_db, $id_pesanan, $id_plng);

                    if (!$stmt_update->execute()) throw new Exception("Database Error: Gagal menjalankan update.");

                    if ($stmt_update->affected_rows === 0) {
                        throw new Exception("Gagal! Pesanan mungkin sudah lunas atau tidak ditemukan.");
                    }
                    $stmt_update->close();

                    // 4. Sukses - Commit Transaksi
                    $conn->commit();

                    echo "<script>
                        alert('Upload berhasil! Mohon tunggu verifikasi admin.');
                        window.location.href = '../pelanggan/index.php?menu=pesanan_saya&act=detail&id_pesanan=$id_pesanan';
                      </script>";
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();

                    // Hapus file jika database gagal update tapi file terlanjur masuk
                    if ($file_name_db && file_exists($target_dir . $file_name_db)) {
                        unlink($target_dir . $file_name_db);
                    }

                    // Log error untuk debug admin
                    error_log("Payment Error ID {$id_plng}: " . $e->getMessage());

                    // Tampilkan Pop-up Error dan kembali ke halaman sebelumnya
                    $pesanError = $e->getMessage();
                    echo "<script>
                        alert('ERROR: $pesanError');
                        window.history.back();
                      </script>";
                    exit();
                }
                break;
        }
        break;

    case 'pengaturan_akun':
        switch ($act) {
            case 'update_password': // Aksi untuk user mengganti passwordnya sendiri
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                } // Pastikan sesi ada

                // Ambil data dari form
                $id_user = isset($_POST['id_plng']) ? (int)$_POST['id_plng'] : 0;
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
                $sql_get_pass = "SELECT password FROM data_plng WHERE id_plng = ?";
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
                    $sql_update_pass = "UPDATE data_plng SET password = ? WHERE id_plng = ?";
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
        break;

    default:
        echo "Aksi tidak dikenal.";
        break;
} // Akhir switch $menu

$conn->close();
