<?php
ob_start();
// Tampilkan error saat pengembangan, nonaktifkan saat produksi
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
$isTipeAkunOpen = false;

// Jika sesi login atau level tidak ada, paksa kembali ke halaman login
if (!isset($_SESSION['user_login']) || !isset($_SESSION['level'])) {
    header("Location: login.php");
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
$user_login = $_SESSION['user_login'];
$level = $_SESSION['level'];

// Ambil variabel GET
$act = isset($_GET['act']) ? htmlspecialchars($_GET['act']) : '';
$menu = isset($_GET['menu']) ? htmlspecialchars($_GET['menu']) : '';

// --- LOGIKA HALAMAN DEFAULT ---
if (empty($menu)) {
    $level_lowercase = strtolower(trim($level));
    if ($level_lowercase == 'anggota') {
        $menu = 'dashboard_pegawai';
    } else if ($level_lowercase == 'kasir') {
        $menu = 'dashboard';
    } else if ($level_lowercase == 'admin') {
        $menu = 'dashboard_admin';
    } else if ($level_lowercase == 'pimpinan') {
        $menu = 'dashboard_pimpinan';
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
            transition: color 0.5s ease;
            /* Transisi warna halus */
        }

        #loading-status.loading {
            color: #dc3545;
            /* Merah */
        }

        #loading-status.loaded {
            color: #28a745;
            /* Hijau */
        }

        #loading-status .fa-spin {
            /* Animasi putar jika mau */
            animation: fa-spin 2s infinite linear;
        }

        @keyframes fa-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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
                        <span>&nbsp;<?= htmlspecialchars($_SESSION['user_login'] ?? 'Pengguna') ?></span>
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

                        // --- MENU KASIR ---
                        if ($level_lowercase == 'kasir') {
                        ?>
                            <li class="nav-item">
                                <a href="index.php?menu=dashboard" class="nav-link <?php echo ($menu == 'dashboard' || $menu == '') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                    <p>Dashboard</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=member" class="nav-link <?php echo ($menu == 'member') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-users"></i>
                                    <p>Data Pelanggan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=pemesanan" class="nav-link <?php echo ($menu == 'pemesanan') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-shopping-cart"></i>
                                    <p>Pesanan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=produk" class="nav-link <?php echo ($menu == 'produk') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-box"></i>
                                    <p>Produk</p>
                                </a>
                            </li>
                        <?php
                        }

                        // --- MENU PIMPINAN ---
                        if ($level_lowercase == 'pimpinan') {
                            $isLaporanOpen = ($menu == 'laporan');
                            $isKualitasOpen = ($menu == 'aktivitasdata' || $menu == 'arsip_dinkes');
                            $isDashboardOpen = ($menu == 'dashboard_pimpinan');
                        ?>
                            <li class="nav-item">
                                <a href="index.php?menu=dashboard_pimpinan" class="nav-link <?php echo $isDashboardOpen ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                    <p>Dashboard</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=laporan" class="nav-link <?php echo $isLaporanOpen ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-chart-bar"></i>
                                    <p>Laporan</p>
                                </a>
                            </li>
                            <li class="nav-item <?php echo $isKualitasOpen ? 'menu-open' : ''; ?>">
                                <a href="#" class="nav-link <?php echo $isKualitasOpen ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-tint"></i>
                                    <p>Kualitas Air <i class="fas fa-angle-left right"></i></p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item"><a href="index.php?menu=aktivitasdata" class="nav-link <?php echo ($menu == 'aktivitasdata') ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i>
                                            <p>Cek Kualitas Air</p>
                                        </a></li>
                                    <li class="nav-item"><a href="index.php?menu=arsip_dinkes" class="nav-link <?php echo ($menu == 'arsip_dinkes') ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i>
                                            <p>Laporan Dinkes</p>
                                        </a></li>
                                </ul>
                            </li>
                        <?php
                        }

                        // --- MENU ANGGOTA ---
                        if ($level_lowercase == 'anggota') {
                        ?>
                            <li class="nav-item">
                                <a href="index.php?menu=dashboard_pegawai" class="nav-link <?php echo ($menu == 'dashboard_pegawai' || $menu == '') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                    <p>Dashboard</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=pengantaran_anggota" class="nav-link <?php echo ($menu == 'pengantaran_anggota' || $menu == '') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-truck"></i>
                                    <p>Pengantaran Tersedia</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=pengantaran_saya" class="nav-link <?php echo ($menu == 'pengantaran_saya') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-map-marked-alt"></i>
                                    <p>Pengantaran Saya</p>
                                </a>
                            </li>
                        <?php
                        }
                        // MENU ADMIN
                        if ($level_lowercase == 'admin') { // Pastikan ini sesuai level Anda
                        ?>
                            <li class="nav-item">
                                <a href="index.php?menu=dashboard_admin" class="nav-link <?php echo ($menu == 'dashboard_admin') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                    <p>Dashboard Admin</p>
                                </a>
                            </li>
                            <li class="nav-item <?php echo $isTipeAkunOpen ? 'menu-open' : ''; ?>">
                                <a href="#" class="nav-link <?php echo $isTipeAkunOpen ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-users-cog"></i>
                                    <p>Kelola Akun <i class="fas fa-angle-left right"></i></p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item"><a href="index.php?menu=pengelola" class="nav-link <?php echo ($menu == 'pengelola') ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i>
                                            <p>Kelola Pengelola</p>
                                        </a></li>
                                    <li class="nav-item"><a href="index.php?menu=pelanggan" class="nav-link <?php echo ($menu == 'pelanggan') ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i>
                                            <p>Kelola Pelanggan</p>
                                        </a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=produk" class="nav-link <?php echo ($menu == 'produk') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-users-cog"></i>
                                    <p>Kelola Produk</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="index.php?menu=pengaturan_admin" class="nav-link <?php echo ($menu == 'pengaturan_admin') ? 'active' : ''; ?>">
                                    <i class="nav-icon fas fa-cogs"></i>
                                    <p>Pengaturan Webhook</p>
                                </a>
                            </li>
                        <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper">
            <section class="content">
                <div class="container-fluid pt-3"> <?php
                                                    // Perutean Halaman (Routing)
                                                    $file_to_include = null;

                                                    if (empty($menu) || $menu == 'home') {
                                                        $file_to_include = "include/home.php";
                                                    }
                                                    // Halaman Kasir
                                                    elseif ($menu == 'dashboard' && $level_lowercase == 'kasir') {
                                                        $file_to_include = "data/dashboard_kasir.php";
                                                    } elseif ($menu == 'produk' && $level_lowercase == 'kasir') {
                                                        $file_to_include = "data/produk.php";
                                                    } elseif ($menu == 'member' && $level_lowercase == 'kasir') {
                                                        $file_to_include = "data/member.php";
                                                    } elseif ($menu == 'pemesanan' && $level_lowercase == 'kasir') {
                                                        $file_to_include = "data/pemesanan.php";
                                                    }
                                                    // Halaman Pimpinan
                                                    elseif ($menu == 'aktivitasdata' && $level_lowercase == 'pimpinan') {
                                                        $file_to_include = "data/aktivitasfilter.php";
                                                    } elseif ($menu == 'arsip_dinkes' && $level_lowercase == 'pimpinan') {
                                                        $file_to_include = "laporan/arsip_dinkes.php";
                                                    } elseif ($menu == 'laporan' && $level_lowercase == 'pimpinan') {
                                                        $file_to_include = "laporan/laporan.php";
                                                    } elseif ($menu == 'dashboard_pimpinan' && $level_lowercase == 'pimpinan') {
                                                        $file_to_include = "data/dashboard_pimpinan.php";
                                                    }
                                                    // Halaman Anggota
                                                    elseif ($menu == 'pengantaran_anggota' && $level_lowercase == 'anggota') {
                                                        $file_to_include = "data/pengantaran_anggota.php";
                                                    } elseif ($menu == 'pengantaran_saya' && $level_lowercase == 'anggota') {
                                                        $file_to_include = "data/pengantaran_saya.php";
                                                    } elseif ($menu == 'dashboard_pegawai' && $level_lowercase == 'anggota') {
                                                        $file_to_include = "data/dashboard_pegawai.php";
                                                    } elseif ($menu == 'dashboard_admin' && $level_lowercase == 'admin') { // Sesuaikan level jika perlu
                                                        $file_to_include = "data/dashboard_admin.php";
                                                    } elseif ($menu == 'pengaturan_admin' && $level_lowercase == 'admin') { // Sesuaikan level jika perlu
                                                        $file_to_include = "pengaturan_admin.php";
                                                    } elseif ($menu == 'pengelola' && $level_lowercase == 'admin') {
                                                        $file_to_include = "data/kelola_user.php";
                                                    } elseif ($menu == 'pelanggan' && $level_lowercase == 'admin') {
                                                        $file_to_include = "data/kelola_pelanggan.php";
                                                    } elseif ($menu == 'produk' && $level_lowercase == 'admin') {
                                                        $file_to_include = "data/produk.php";
                                                    }

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
                statusIcon.classList.add('fa-check-circle'); // Tambah ikon centang
                statusText.textContent = ' Siap'; // Ubah teks
            }
        });
    </script>
</body>

</html>