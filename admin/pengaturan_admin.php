<?php
// Pastikan sesi dimulai dan hanya admin yang bisa akses
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['level']) || strtolower($_SESSION['level']) !== 'admin') { // Sesuaikan 'admin' jika level Anda berbeda
    die("Akses ditolak. Hanya Admin.");
}
$menuParam = 'pengaturan_admin'; // Untuk form action

// --- START MODIFIKASI DB: Mengganti File Config dengan DB (Key-Value) ---
// Asumsi: File koneksi database (misalnya '../config/koneksi.php') di-include di sini
include '../config/koneksi.php'; // SESUAIKAN PATH INI

// 1. Inisialisasi variabel
$verify_token_current = '';
$access_token_current = '';
$phone_number_id_current = '';
$db_error = '';
$config_wa = []; // Array untuk menyimpan semua konfigurasi key-value

// 2. Ambil data dari tabel settings (Skema Key-Value)
if (isset($conn) && $conn) {
    // Ambil data WhatsApp config dari tabel settings
    $sql = "SELECT name, value FROM settings";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            // Loop untuk mengisi array $config_wa[name] = value
            while ($row = mysqli_fetch_assoc($result)) {
                $config_wa[$row['name']] = $row['value'];
            }

            // Ekstrak nilai spesifik yang dibutuhkan dari array $config_wa
            $verify_token_current = $config_wa['wa_verify_token'] ?? '';
            $access_token_current = $config_wa['wa_access_token'] ?? '';
            $phone_number_id_current = $config_wa['wa_phone_number_id'] ?? '';
        } else {
            $db_error = "Tabel settings kosong. Harap simpan konfigurasi untuk mengisi data awal.";
        }
    } else {
        $db_error = "Error saat query database: " . mysqli_error($conn);
    }
} else {
    $db_error = "Koneksi database gagal atau tidak tersedia.";
}

// Konfigurasi sekarang selalu dianggap writable (karena disimpan di DB)
$config_writable = true;
$error_read = $db_error; // Gunakan error DB di sini

// --- END MODIFIKASI DB ---

// --- Path ke file webhook (HANYA untuk menampilkan URL & cek fungsi tes) ---
$webhook_file_path = __DIR__ . '/../config/webhook.php'; // Path ke file webhook di /config/

// URL Webhook (ditentukan oleh lokasi file webhook)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'sipamis.fwh.is'; // Fallback domain
$webhook_url_display = $protocol . $domainName . '/config/webhook.php'; // Default URL relatif

// --- START PERBAIKAN LOGIKA URL LOKAL ---

// Dapatkan path relatif dari root web
if (file_exists($webhook_file_path) && isset($_SERVER['DOCUMENT_ROOT'])) {
    // 1. Dapatkan path absolut file webhook dan pastikan menggunakan forward slash (/)
    $absPath = str_replace('\\', '/', realpath($webhook_file_path));
    // 2. Dapatkan path absolut Document Root dan pastikan menggunakan forward slash (/)
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

    // 3. Hitung path relatif web
    if (strpos($absPath, $docRoot) === 0) {
        $scriptPath = substr($absPath, strlen($docRoot));
        // Pastikan path relatif dimulai dengan /
        if (substr($scriptPath, 0, 1) !== '/') $scriptPath = '/' . $scriptPath;

        $webhook_url_display = $protocol . $domainName . $scriptPath;
    }
    // Jika tidak di root, gunakan path default relatif '/config/webhook.php'
}

// --- END PERBAIKAN LOGIKA URL LOKAL ---
// Mask Access Token (Logika tetap sama)
$access_token_masked = !empty($access_token_current) ? substr($access_token_current, 0, 10) . str_repeat('*', max(0, strlen($access_token_current) - 15)) . substr($access_token_current, -5) : '';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Admin - Sipamis</title>
    <style>
        /* CSS di sini TIDAK BERUBAH */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
        }

        .settings-container {
            padding: 15px;
            max-width: 900px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .settings-section {
            padding: 25px;
            margin-bottom: 30px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
        }

        .settings-section h2 {
            text-align: left;
            font-size: 1.5em;
            margin-bottom: 25px;
            color: #34495e;
            font-weight: 600;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }

        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="tel"] {
            /* Tambah tel */
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
            background-color: #fff;
            font-family: monospace;
            /* Monospace for tokens */
        }

        /* Kembalikan font normal untuk non-token */
        .form-group input[type="tel"] {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            font-weight: 500;
            text-align: center;
            line-height: 1.5;
            margin-bottom: 5px;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-info {
            background-color: #3498db;
            color: white;
        }

        .btn-info:hover {
            background-color: #2980b9;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }

        /* Ukuran tombol kecil */
        .btn:disabled {
            background-color: #bdc3c7;
            color: #7f8c8d;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .setting-item {
            margin-bottom: 15px;
        }

        .setting-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }

        .setting-item .value {
            font-size: 1em;
            color: #333;
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #dcdcdc;
            word-wrap: break-word;
            font-family: monospace;
        }

        .setting-item small {
            color: #777;
            font-size: 0.85em;
            display: block;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }

        /* Responsif */
        @media (max-width: 768px) {
            .content-header h1 {
                font-size: 1.4em;
            }

            .settings-container {
                padding: 10px;
                margin: 10px;
            }

            .settings-section {
                padding: 15px;
            }

            .form-actions {
                justify-content: center;
            }

            /* Pusatkan tombol di mobile */
        }
    </style>
</head>

<body>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-10">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Pengaturan Admin</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Pengaturan Admin</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class='col-12'>
        <br>
        <div class="settings-container">

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert <?php echo htmlspecialchars($_SESSION['flash_type']); ?>" role="alert">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <?php if (!empty($error_read)): ?>
                <div class="alert alert-warning" role="alert">
                    <strong>Info Membaca Konfigurasi:</strong> <?php echo $error_read; ?>
                </div>
            <?php endif; ?>

            <?php
            // Blok pengecekan izin tulis file config_wa.php lama DIHILANGKAN karena sekarang menggunakan DB.
            ?>


            <div class="settings-section">
                <h2>Update Konfigurasi WhatsApp API</h2>
                <p style="font-size: 0.9em; margin-bottom: 20px;">Masukkan nilai baru hanya pada field yang ingin Anda ubah. Access Token harus digenerate manual dari Meta Business Suite. Pengaturan ini **disimpan di database Anda**.</p>
                <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menuParam); ?>&act=simpan_konfigurasi">

                    <div class="form-group">
                        <label for="access_token">Access Token Baru (Opsional)</label>
                        <input type="password" id="access_token" name="access_token" placeholder="Masukkan Access Token BARU jika ingin mengubah" value="">
                        <small>Token saat ini (sebagian): <code><?php echo htmlspecialchars($access_token_masked ?: 'Belum diatur'); ?></code>. Kosongkan input ini jika tidak ingin mengubah token.</small>
                    </div>

                    <div class="form-group">
                        <label for="phone_number_id">Phone Number ID</label>
                        <input type="text" id="phone_number_id" name="phone_number_id" value="<?php echo htmlspecialchars($phone_number_id_current); ?>" required>
                        <small>ID nomor telepon yang terhubung dengan WABA Anda.</small>
                    </div>

                    <div class="form-group">
                        <label for="webhook_verify_token">Webhook Verify Token</label>
                        <input type="text" id="webhook_verify_token" name="webhook_verify_token" value="<?php echo htmlspecialchars($verify_token_current); ?>" required>
                        <small>Token rahasia ini dimasukkan di Meta Developer App untuk verifikasi webhook.</small>
                        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="generateVerifyToken()">Generate Token Acak Baru</button>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>


        </div>
        <br><br>
    </div>

    <script>
        // Fungsi generate verify token (tidak berubah)
        function generateVerifyToken() {
            const randomToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
            const tokenInput = document.getElementById('webhook_verify_token');
            if (tokenInput) {
                tokenInput.value = randomToken;
            }
        }
    </script>

</body>

</html>