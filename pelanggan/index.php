<?php
// Tampilkan error saat pengembangan, nonaktifkan saat produksi
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Jika sesi login atau level tidak ada, paksa kembali ke halaman login
if (!isset($_SESSION['username_login']) || !isset($_SESSION['level'])) {
    header("Location: ../login.php");
    exit();
}

// Sertakan file koneksi
include "../config/koneksi.php";
// Membuat koneksi
$conn = mysqli_connect($servername, $username, $password, $database);
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Ambil variabel sesi
$username_login = $_SESSION['username_login'];
$level = $_SESSION['level'];

// Ambil variabel GET
$act = isset($_GET['act']) ? htmlspecialchars($_GET['act']) : '';
$menu = isset($_GET['menu']) ? htmlspecialchars($_GET['menu']) : '';

// --- LOGIKA HALAMAN DEFAULT ---
if (empty($menu)) {
    $level_lowercase = strtolower(trim($level));
    if ($level_lowercase == 'pelanggan') {
        $menu = 'dashboard_pelanggan';
    }
    // Jika tidak ada yang cocok, biarkan kosong -> home.php
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sipamis</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "include/link.php"; ?>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        #loading-status {
            transition: color 0.5s ease; /* Transisi warna halus */
        }
        #loading-status.loading {
            color: #dc3545; /* Merah */
        }
        #loading-status.loaded {
            color: #28a745; /* Hijau */
        }
        #loading-status .fa-spin { /* Animasi putar jika mau */
             animation: fa-spin 2s infinite linear;
        }
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    </head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item">
            <span id="loading-status" class="nav-link loading">
                <i class="fas fa-sync-alt fa-spin"></i>
                <span class="status-text d-none d-sm-inline"> Memuat...</span>
            </span>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" style="white-space: nowrap;">
                <i class="far fa-user-circle"></i>
                <span>&nbsp;<?= htmlspecialchars($_SESSION['nama_plng'] ?? 'Pengguna') ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">
                    Level: <?= htmlspecialchars(ucfirst($_SESSION['level'] ?? '')) ?>
                </span>
                <div class="dropdown-divider"></div>
                <a href="index.php?menu=pengaturan_akun" class="dropdown-item">
                    <i class="fas fa-user-cog mr-2"></i> Pengaturan Akun
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item dropdown-footer">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <img src="dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">
                <?php echo htmlspecialchars(ucfirst($level)); ?>
            </span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <?php
                $level_lowercase = strtolower(trim($level));

                // --- MENU Pelanggan ---
                if ($level_lowercase == 'pelanggan') {
                ?>
                    <li class="nav-item">
                        <a href="index.php?menu=dashboard_pelanggan" class="nav-link <?php echo ($menu == 'dashboard_pelanggan' || $menu == '') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?menu=pesanan_saya" class="nav-link <?php echo ($menu == 'pesanan_saya') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-users"></i><p>Pesanan</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?menu=pembayaran" class="nav-link <?php echo ($menu == 'pembayaran') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-shopping-cart"></i><p>Pembayaran</p>
                        </a>
                    </li>
                <?php
                } ?>

                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid pt-3"> <?php
                // Perutean Halaman (Routing)
                $file_to_include = null;

                if (empty($menu) || $menu == 'home') { $file_to_include = "include/home.php"; }
                // Halaman Kasir
                elseif ($menu == 'dashboard_pelanggan' && $level_lowercase == 'pelanggan') { $file_to_include = "data/dashboard_pelanggan.php"; }
                elseif ($menu == 'pesanan_saya' && $level_lowercase == 'pelanggan') { $file_to_include = "data/pesanan_saya.php"; }
                elseif ($menu == 'buat_pesanan' && $level_lowercase == 'pelanggan') { $file_to_include = "data/buat_pesanan.php"; }
                elseif ($menu == 'pembayaran' && $level_lowercase == 'pelanggan') { $file_to_include = "data/pembayaran_pelanggan.php"; }
                
                // === ROUTE BARU UNTUK PENGATURAN AKUN (SEMUA LEVEL BISA) ===
                elseif ($menu == 'pengaturan_akun') {
                    // Buat file ini: /admin/pengaturan_akun.php
                    // Isi dengan form ganti password, dll.
                    $file_to_include = "pengaturan_akun.php";
                }
                // === AKHIR ROUTE BARU ===

                // Cek file dan include, atau tampilkan error
                if ($file_to_include && file_exists($file_to_include)) {
                    include $file_to_include;
                } elseif (!empty($menu)) { // Hanya tampilkan error jika menu dipilih tapi tidak valid/diizinkan
                     echo "<div class='alert alert-danger'><h2>Akses Ditolak</h2><p>Anda tidak memiliki izin untuk mengakses halaman '{$menu}' atau halaman tersebut tidak ditemukan.</p></div>";
                } else {
                     include "include/home.php"; // Fallback ke home jika tidak ada file cocok
                }
                ?>
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>Copyright &copy; Sipamis 2025</strong> All rights reserved.
    </footer>

</div>
<?php include "include/link2.php"; ?>

<script>
window.addEventListener('load', function() {
    const statusIndicator = document.getElementById('loading-status');
    const statusText = statusIndicator ? statusIndicator.querySelector('.status-text') : null;
    const statusIcon = statusIndicator ? statusIndicator.querySelector('i') : null;

    if (statusIndicator && statusText && statusIcon) {
        statusIndicator.classList.remove('loading');
        statusIndicator.classList.add('loaded');
        statusIcon.classList.remove('fa-sync-alt', 'fa-spin'); // Hapus ikon loading
        statusIcon.classList.add('fa-check-circle');        // Tambah ikon centang
        statusText.textContent = ' Siap'; // Ubah teks
    }
});
</script>
</body>
</html>