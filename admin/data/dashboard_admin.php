<?php
// PASTIKAN FILE INI DIAWALI DENGAN KONEKSI DATABASE ($conn)
// Asumsi: Nama admin diambil dari kolom 'nama_lengkap' atau 'user_login' di sesi.

// Ambil nama pengguna dari sesi
$current_admin_name = $_SESSION['nama_lengkap'] ?? $_SESSION['user_login'] ?? 'Admin';

// --- 1. MENGAMBIL DATA UNTUK KARTU ---

// Query 1: Total Pengguna Tipe Pengelola (Mengambil dari tabel 'user' dengan level staff)
// Asumsi: Level 'admin', 'pegawai', 'kasir' adalah Pengelola.
$query_pengelola = "SELECT COUNT(id_user) AS total_pengelola FROM user";
$result_pengelola = mysqli_query($conn, $query_pengelola);
$data_pengelola = mysqli_fetch_assoc($result_pengelola);
$total_pengelola = $data_pengelola['total_pengelola'] ?? 0;

// Query 2: Total Pengguna Tipe Pelanggan (MENGAMBIL DARI TABEL 'data_plng')
$query_pelanggan = "SELECT COUNT(id_plng) AS total_pelanggan FROM data_plng";
$result_pelanggan = mysqli_query($conn, $query_pelanggan);
$data_pelanggan = mysqli_fetch_assoc($result_pelanggan);
$total_pelanggan = $data_pelanggan['total_pelanggan'] ?? 0;

// Query 3: Total Produk Saat Ini 
$query_total_produk = "SELECT COUNT(id_produk) AS total_produk FROM produk";
$result_total_produk = mysqli_query($conn, $query_total_produk);
$data_total_produk = mysqli_fetch_assoc($result_total_produk);
$total_produk = $data_total_produk['total_produk'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengelola SIPAMIS</title>
    <style>
        .dashboard-card {
            text-align: center;
            border: 1px solid #ddd;
            padding: 30px;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            background-color: #fff;
            margin-bottom: 20px;
        }

        .dashboard-card h2 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .dashboard-card p {
            font-size: 1rem;
            margin-bottom: 25px;
            color: #666;
        }

        .btn-buka {
            display: inline-block;
            padding: 10px 20px;
            border: 1px solid #ccc;
            background-color: #f7f7f7;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0 text-dark" style="font-size: 1.5rem;">Selamat Datang, <?= htmlspecialchars($current_admin_name) ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="dashboard-card">
                        <h2>Kelola Pengguna</h2>
                        <p>Tipe Akun: Pengelola (<?= $total_pengelola ?>)</p>
                        <a href="index.php?menu=pengelola" class="btn-buka">Buka</a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="dashboard-card">
                        <h2>Kelola Pengguna</h2>
                        <p>Tipe Akun: Pelanggan (<?= $total_pelanggan ?>)</p>
                        <a href="index.php?menu=pelanggan" class="btn-buka">Buka</a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="dashboard-card">
                        <h2>Kelola Produk</h2>
                        <p>Produk Saat Ini: <?= $total_produk ?></p>
                        <a href="index.php?menu=produk" class="btn-buka">Buka</a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="dashboard-card">
                        <h2>Webhook</h2>
                        <p>Integrasi Notifikasi</p>
                        <a href="index.php?menu=pengaturan_admin" class="btn-buka">Buka</a>
                    </div>
                </div>

            </div>
        </div>
    </section>
</body>

</html>