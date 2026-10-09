<?php

date_default_timezone_set('Asia/Jakarta');

// --- 0. PENJAGA & DATA SESSION PELANGGAN ---
// Asumsi ID Pelanggan sudah ada di session
if (!isset($_SESSION['id_plng'])) {
    die("Sesi pelanggan tidak ditemukan.");
}
$id_plng = (int)$_SESSION['id_plng'];

// --- 1. DATA INFORMASI HEADER ---
$today_date = date('Y-m-d');

$nama_hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$nama_bulan = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];

$hari_ini = date('w');
$tanggal_ini = date('d');
$bulan_ini = date('n');
$tahun_ini = date('Y');
$tanggal_lengkap = $nama_hari[$hari_ini] . ", " . $tanggal_ini . " " . $nama_bulan[$bulan_ini] . " " . $tahun_ini;


$query_galon_hari_ini = "SELECT SUM(p.galon) AS total_galon
                         FROM pesanan p
                         LEFT JOIN pengantaran peng 
                           ON p.id_pesanan = peng.id_pesanan
                         WHERE p.id_plng = ? 
                           AND DATE(p.tgl_pesan) = ?
                           AND COALESCE(peng.status_pengantaran, 'Belum Diproses') <> 'Batal'"; // Menambahkan filter Batal

$stmt_galon_hari_ini = $conn->prepare($query_galon_hari_ini);

$stmt_galon_hari_ini->bind_param("is", $id_plng, $today_date);
$stmt_galon_hari_ini->execute();
$data_galon_hari_ini = $stmt_galon_hari_ini->get_result()->fetch_assoc();
$total_galon_hari_ini_plng = $data_galon_hari_ini['total_galon'] ?? 0;
$stmt_galon_hari_ini->close();


// Query 2: Total Pesanan Diproses/Dalam Perjalanan (Status AKTIF)
$query_pesanan_aktif = "SELECT COUNT(p.id_pesanan) AS total_aktif
                        FROM pesanan p
                        JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                        WHERE p.id_plng = ? 
                        AND peng.status_pengantaran IN ('Diproses', 'Dalam Perjalanan')";
$stmt_pesanan_aktif = $conn->prepare($query_pesanan_aktif);
$stmt_pesanan_aktif->bind_param("i", $id_plng);
$stmt_pesanan_aktif->execute();
$data_pesanan_aktif = $stmt_pesanan_aktif->get_result()->fetch_assoc();
$total_pesanan_aktif = $data_pesanan_aktif['total_aktif'] ?? 0;
$stmt_pesanan_aktif->close();

// Query 3: Total Galon yang Sudah Diterima/Selesai (Sepanjang Waktu)
$query_total_diterima = "SELECT SUM(p.galon) AS total_diterima
                         FROM pesanan p
                         JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                         WHERE p.id_plng = ? AND peng.status_pengantaran = 'Selesai'";
$stmt_total_diterima = $conn->prepare($query_total_diterima);
$stmt_total_diterima->bind_param("i", $id_plng);
$stmt_total_diterima->execute();
$data_total_diterima = $stmt_total_diterima->get_result()->fetch_assoc();
$total_galon_diterima = $data_total_diterima['total_diterima'] ?? 0;
$stmt_total_diterima->close();


// Query 4: Total Tagihan Belum Lunas
$query_tagihan_belum_lunas = "SELECT SUM(pem.jumlah_pembayaran) AS total_belum_lunas
                              FROM pembayaran pem
                              JOIN pesanan p ON pem.id_pesanan = p.id_pesanan
                              WHERE p.id_plng = ? AND pem.status_pembayaran = 'Belum Lunas'";
$stmt_tagihan_belum_lunas = $conn->prepare($query_tagihan_belum_lunas);
$stmt_tagihan_belum_lunas->bind_param("i", $id_plng);
$stmt_tagihan_belum_lunas->execute();
$data_tagihan_belum_lunas = $stmt_tagihan_belum_lunas->get_result()->fetch_assoc();
$tagihan_belum_lunas = $data_tagihan_belum_lunas['total_belum_lunas'] ?? 0;
$tagihan_belum_lunas_formatted = "Rp " . number_format($tagihan_belum_lunas, 0, ',', '.');
$stmt_tagihan_belum_lunas->close();


// --- 3. MENGAMBIL DATA UNTUK TABEL "Pesanan Terbaru" ---
$query_terbaru = "SELECT p.id_pesanan, p.tgl_pesan, p.galon, pem.status_pembayaran, peng.status_pengantaran
                  FROM pesanan p
                  JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                  JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                  WHERE p.id_plng = ? 
                  ORDER BY p.tgl_pesan DESC 
                  LIMIT 5";

$stmt_terbaru = $conn->prepare($query_terbaru);
$stmt_terbaru->bind_param("i", $id_plng);
$stmt_terbaru->execute();
$result_terbaru = $stmt_terbaru->get_result();


// --- 4. MENGAMBIL DATA UNTUK GRAFIK "Aktivitas Pesanan Mingguan" ---
$tanggal_akhir = date('Y-m-d');
$tanggal_mulai = date('Y-m-d', strtotime('-6 days')); // 7 hari total

$query_chart = "SELECT 
                    DATE(tgl_pesan) AS tanggal, 
                    COUNT(id_pesanan) AS total_pesanan 
                FROM pesanan 
                WHERE id_plng = ? AND tgl_pesan >= ? AND tgl_pesan < ? + INTERVAL 1 DAY
                GROUP BY DATE(tgl_pesan)
                ORDER BY tanggal ASC";

$stmt_chart = $conn->prepare($query_chart);
$stmt_chart->bind_param("iss", $id_plng, $tanggal_mulai, $tanggal_akhir);
$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();

$orders_by_date = [];
while ($row = $result_chart->fetch_assoc()) {
    $orders_by_date[$row['tanggal']] = $row['total_pesanan'];
}

// Isi data chart 7 hari terakhir
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($tanggal));
    $chart_data[] = $orders_by_date[$tanggal] ?? 0;
}
$stmt_chart->close();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_plng'] ?? 'Pelanggan') ?>!</h1>
            </div>
            <div class="col-sm-6">
                <h5 class="m-0 text-dark float-sm-right">
                    <i class="far fa-calendar-alt"></i>&nbsp;
                    <?= $tanggal_lengkap ?>
                </h5>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= htmlspecialchars($total_pesanan_aktif) ?></h3>
                        <p>Pesanan Aktif (Diproses/Kirim)</p>
                    </div>
                    <div class="icon"><i class="fas fa-truck-fast"></i></div>
                    <a href="index.php?menu=pesanan_saya" class="small-box-footer">
                        Lihat Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= htmlspecialchars($total_galon_hari_ini_plng) ?></h3>
                        <p>Galon Dipesan Hari Ini</p>
                    </div>
                    <div class="icon"><i class="fas fa-bottle-water"></i></div>
                    <a href="index.php?menu=pesanan_saya&act=tambah" class="small-box-footer">
                        Buat Pesanan Baru <i class="fas fa-plus-circle"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= htmlspecialchars($total_galon_diterima) ?></h3>
                        <p>Total Galon Diterima</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <a href="index.php?menu=pesanan_saya" class="small-box-footer">
                        Riwayat Transaksi <i class="fas fa-history"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3 style="font-size: 1.75rem; word-wrap: break-word;">
                            <?= htmlspecialchars($tagihan_belum_lunas_formatted) ?>
                        </h3>
                        <p>Tagihan Belum Lunas</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <a href="index.php?menu=pembayaran" class="small-box-footer">
                        Bayar Sekarang <i class="fas fa-credit-card"></i>
                    </a>
                </div>
            </div>
        </div>
        <hr>

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="card-title">Aktivitas Pesanan Mingguan (Jumlah Order)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyOrdersChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="card-title">5 Pesanan Terbaru Anda</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-valign-middle">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Galon</th>
                                    <th>Bayar</th>
                                    <th>Kirim</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result_terbaru && $result_terbaru->num_rows > 0) {
                                    while ($r = $result_terbaru->fetch_assoc()) {
                                        // Helper untuk Badge status
                                        $badge_bayar = match ($r['status_pembayaran']) {
                                            'Lunas' => '<span class="badge bg-success">Lunas</span>',
                                            'Menunggu Konfirmasi' => '<span class="badge bg-warning">Menunggu</span>',
                                            default => '<span class="badge bg-danger">Belum Lunas</span>',
                                        };
                                        $badge_kirim = match ($r['status_pengantaran']) {
                                            'Selesai' => '<span class="badge bg-success">Selesai</span>',
                                            'Dalam Perjalanan' => '<span class="badge bg-info">Dikirim</span>',
                                            default => '<span class="badge bg-warning">Diproses</span>',
                                        };

                                        echo "<tr>";
                                        echo "<td>#{$r['id_pesanan']} <small class='text-muted d-block'>" . date('d/m H:i', strtotime($r['tgl_pesan'])) . "</small></td>";
                                        echo "<td>" . htmlspecialchars($r['galon']) . "</td>";
                                        echo "<td>{$badge_bayar}</td>";
                                        echo "<td>{$badge_kirim}</td>";
                                        echo "<td><a href='index.php?menu=pesanan_saya&act=detail&id_pesanan=" . htmlspecialchars($r['id_pesanan']) . "' class='btn btn-sm btn-outline-primary'>Detail</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center'>Anda belum memiliki pesanan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Chart Aktivitas Pesanan Mingguan ---
        const ctxWeekly = document.getElementById('weeklyOrdersChart').getContext('2d');

        const chartLabels = <?= json_encode($chart_labels) ?>;
        const chartData = <?= json_encode($chart_data) ?>;

        new Chart(ctxWeekly, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Pesanan',
                    data: chartData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)', // Biru
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            },
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
</script>