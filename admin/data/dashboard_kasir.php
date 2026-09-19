<?php
// Atur zona waktu untuk memastikan query tanggal berjalan benar
date_default_timezone_set('Asia/Jakarta');

// --- 1. MENGAMBIL DATA UNTUK KOTAK INFO ---
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

// Ambil komponen tanggal saat ini
$hari_ini = date('w');
$tanggal_ini = date('d');
$bulan_ini = date('n');
$tahun_ini = date('Y');

// Gabungkan menjadi format tanggal lengkap Bahasa Indonesia
$tanggal_lengkap = $nama_hari[$hari_ini] . ", " . $tanggal_ini . " " . $nama_bulan[$bulan_ini] . " " . $tahun_ini;

// Query untuk TOTAL GALON HARI INI
$query_total_galon = "SELECT SUM(p.galon) AS total_galon
                      FROM pesanan p
                      JOIN pengantaran peng 
                        ON p.id_pesanan = peng.id_pesanan
                      WHERE DATE(p.tgl_pesan) = '$today_date'
                        AND peng.status_pengantaran <> 'Batal'";
$result_total_galon = mysqli_query($conn, $query_total_galon);
$data_total_galon = mysqli_fetch_assoc($result_total_galon);
$total_galon_hari_ini = $data_total_galon['total_galon'] ?? 0;

// Query untuk TOTAL PESANAN HARI INI (berdasarkan jumlah order)
$query_total_pesanan = "SELECT COUNT(p.id_pesanan) AS total_order
                      FROM pesanan p
                      JOIN pengantaran peng 
                        ON p.id_pesanan = peng.id_pesanan
                      WHERE DATE(p.tgl_pesan) = '$today_date'
                        AND peng.status_pengantaran <> 'Batal'";
$result_total_pesanan = mysqli_query($conn, $query_total_pesanan);
$data_total_pesanan = mysqli_fetch_assoc($result_total_pesanan);
$total_pesanan_hari_ini = $data_total_pesanan['total_order'] ?? 0;

// Query untuk TOTAL PESANAN AKTIF (Diproses atau Dalam Perjalanan)
$query_pesanan_aktif = "SELECT COUNT(p.id_pesanan) AS total_aktif
                        FROM pesanan p
                        JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                        WHERE peng.status_pengantaran IN ('Diproses', 'Dalam Perjalanan')";
$result_pesanan_aktif = mysqli_query($conn, $query_pesanan_aktif);
$data_pesanan_aktif = mysqli_fetch_assoc($result_pesanan_aktif);
$total_pesanan_aktif = $data_pesanan_aktif['total_aktif'] ?? 0;

// Query untuk TOTAL PENDAPATAN HARI INI (yang sudah lunas hari ini)
$query_pendapatan = "SELECT SUM(jumlah_pembayaran) AS total_pendapatan
                   FROM pembayaran
                   WHERE status_pembayaran = 'Lunas' AND DATE(tgl_pembayaran) = '$today_date'";
$result_pendapatan = mysqli_query($conn, $query_pendapatan);
$data_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$pendapatan_hari_ini = $data_pendapatan['total_pendapatan'] ?? 0;
$pendapatan_hari_ini_formatted = "Rp " . number_format($pendapatan_hari_ini, 0, ',', '.');


// --- 2. MENGAMBIL DATA UNTUK GRAFIK "Statistik Penjualan Mingguan" ---
$tanggal_akhir = date('Y-m-d');
$tanggal_mulai = date('Y-m-d', strtotime('-7 days'));

$query_chart = "SELECT 
                    DATE(p.tgl_pesan) AS tanggal, 
                    SUM(p.galon) AS total_galon 
                FROM pesanan p
                LEFT JOIN pengantaran peng 
                  ON p.id_pesanan = peng.id_pesanan
                WHERE p.tgl_pesan >= ? AND p.tgl_pesan < ? + INTERVAL 1 DAY
                  AND COALESCE(peng.status_pengantaran, 'Belum Diproses') <> 'Batal'
                GROUP BY DATE(p.tgl_pesan)";

$stmt_chart = $conn->prepare($query_chart);
$stmt_chart->bind_param("ss", $tanggal_mulai, $tanggal_akhir);
$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();

$sales_by_date = [];
while ($row = $result_chart->fetch_assoc()) {
    $sales_by_date[$row['tanggal']] = $row['total_galon'];
}

$chart_labels = [];
$chart_data = [];
for ($i = 7; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($tanggal));
    $chart_data[] = $sales_by_date[$tanggal] ?? 0;
}


// --- 3. MENGAMBIL DATA UNTUK TABEL "Pesanan Terkini" ---
$query_terkini = "SELECT p.id_pesanan, p.tgl_pesan, p.nama_plng, p.galon 
                  FROM pesanan p
                  LEFT JOIN pengantaran peng 
                    ON p.id_pesanan = peng.id_pesanan
                  WHERE DATE(p.tgl_pesan) = '$today_date' 
                    AND COALESCE(peng.status_pengantaran, 'Belum Diproses') NOT IN ('Selesai', 'Batal')
                  ORDER BY p.tgl_pesan DESC 
                  LIMIT 5";
$result_terkini = mysqli_query($conn, $query_terkini);


// --- 4. DATA UNTUK GRAFIK PER JAM (STATISTIK 7 HARI) ---

// Tentukan rentang 7 hari (hari ini dan 6 hari ke belakang)
$tanggal_mulai_hourly = date('Y-m-d', strtotime('-6 days'));
$tanggal_akhir_hourly = date('Y-m-d');

// Query untuk mengambil data per jam selama 7 hari terakhir
$query_hourly = "SELECT
                     DATE(p.tgl_pesan) AS tanggal,
                     HOUR(p.tgl_pesan) AS jam,
                     SUM(p.galon) AS total_galon
                 FROM pesanan p
                 LEFT JOIN pengantaran peng 
                   ON p.id_pesanan = peng.id_pesanan
                 WHERE p.tgl_pesan >= ? AND p.tgl_pesan < ? + INTERVAL 1 DAY
                   AND COALESCE(peng.status_pengantaran, 'Belum Diproses') <> 'Batal'
                 GROUP BY tanggal, jam
                 ORDER BY tanggal ASC, jam ASC";

$stmt_hourly = $conn->prepare($query_hourly);
// Bind tanggal mulai dan tanggal akhir
$stmt_hourly->bind_param("ss", $tanggal_mulai_hourly, $tanggal_akhir_hourly);
$stmt_hourly->execute();
$result_hourly = $stmt_hourly->get_result();

// Olah data ke dalam array bersarang: [tanggal][jam] => total
$sales_by_day_hour = [];
while ($row = $result_hourly->fetch_assoc()) {
    $sales_by_day_hour[$row['tanggal']][(int)$row['jam']] = (int)$row['total_galon'];
}

// Siapkan label untuk sumbu-X (00:00 s/d 23:00)
$hourly_labels = [];
for ($i = 0; $i < 24; $i++) {
    $hourly_labels[] = sprintf('%02d:00', $i);
}

// Siapkan dataset untuk 7 hari
$hourly_datasets = [];
$nama_hari_simple = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];

// Palet warna (7 warna) - Disesuaikan
$color_palette = [
    'rgba(190, 190, 190, 1)', // 0. Abu-abu (6 hari lalu)
    'rgba(153, 102, 255, 1)', // 1. Ungu
    'rgba(75, 192, 192, 1)',  // 2. Teal
    'rgba(255, 206, 86, 1)', // 3. Kuning
    'rgba(255, 159, 64, 1)',  // 4. Oranye
    'rgba(54, 162, 235, 1)',  // 5. Biru
    'rgba(255, 99, 132, 1)',  // 6. Merah (Hari Ini)
];
$color_palette_bg = [
    'rgba(190, 190, 190, 0.2)', // 0. Abu-abu (BG)
    'rgba(153, 102, 255, 0.2)', // 1. Ungu (BG)
    'rgba(75, 192, 192, 0.2)', // 2. Teal (BG)
    'rgba(255, 206, 86, 0.2)', // 3. Kuning (BG)
    'rgba(255, 159, 64, 0.2)', // 4. Oranye (BG)
    'rgba(54, 162, 235, 0.2)', // 5. Biru (BG)
    'rgba(255, 99, 132, 0.2)', // 6. Merah (BG)
];
$color_index = 0;

// Loop dari 6 hari lalu (paling lama) NAIK ke 0 (hari ini)
// Ini membangun array dataset agar "Hari Ini" (dataset terakhir)
// ditampilkan di lapisan paling atas pada chart.
for ($i = 6; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-$i days"));
    $hari_index = date('w', strtotime($tanggal));

    // Buat label (e.g., "01/11 (Sb)")
    $label = date('d/m', strtotime($tanggal)) . ' (' . $nama_hari_simple[$hari_index] . ')';
    if ($i == 0) {
        $label = "Hari Ini (" . $nama_hari_simple[$hari_index] . ")";
    }

    // Ambil data untuk tanggal ini
    $data_for_this_day = $sales_by_day_hour[$tanggal] ?? [];

    // Buat array 24 jam untuk data chart
    $day_data_array = [];
    for ($h = 0; $h < 24; $h++) {
        $day_data_array[] = $data_for_this_day[$h] ?? 0;
    }

    // Tentukan warna
    $color = $color_palette[$color_index];
    $bgColor = $color_palette_bg[$color_index];
    $color_index++; // Naikkan index warna

    // Bedakan "Hari Ini"
    $borderWidth = ($i == 0) ? 3 : 1.5; // Garis tebal untuk hari ini
    $fill = ($i == 0); // Isi area hanya untuk hari ini

    $hourly_datasets[] = [
        'label' => $label,
        'data' => $day_data_array,
        'borderColor' => $color,
        'backgroundColor' => $bgColor,
        'borderWidth' => $borderWidth,
        'fill' => $fill,
        'tension' => 0.1
    ];
}
// --- AKHIR BAGIAN 4 ---

include "../config/koneksi.php";

$url = "http://sipamis.fwh.is/admin/datatabel1.json";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$datatrainer = curl_exec($ch);
curl_close($ch);

if ($datatrainer) {
    $data = json_decode(trim($datatrainer), true);

    if (!empty($data) && json_last_error() === JSON_ERROR_NONE) {
        foreach ($data as $d) {
            $id = $d['id_pesanan'];

            // 1. Sinkronisasi Tabel PESANAN
            $check_p = mysqli_query($conn, "SELECT id_pesanan FROM pesanan WHERE id_pesanan = '$id'");
            if (mysqli_num_rows($check_p) == 0) {
                $sql_p = "INSERT INTO pesanan (id_pesanan, id_plng, nama_plng, no_telp, id_produk, nama_produk, galon, tgl_pesan) 
                          VALUES ('$id', '{$d['id_plng']}', '{$d['nama_plng']}', '{$d['no_telp']}', '{$d['id_produk']}', '{$d['nama_produk']}', '{$d['galon']}', '{$d['tgl_pesan']}')";
                mysqli_query($conn, $sql_p);
            }

            // 2. Sinkronisasi Tabel PEMBAYARAN
            $check_b = mysqli_query($conn, "SELECT id_pesanan FROM pembayaran WHERE id_pesanan = '$id'");
            if (mysqli_num_rows($check_b) > 0) {
                $sql_b = "UPDATE pembayaran SET 
                          jumlah_pembayaran = '{$d['jumlah_pembayaran']}', 
                          metode_pembayaran = '{$d['metode_pembayaran']}', 
                          status_pembayaran = '{$d['status_pembayaran']}', 
                          tgl_pembayaran = '{$d['tgl_pembayaran']}' 
                          WHERE id_pesanan = '$id'";
            } else {
                $sql_b = "INSERT INTO pembayaran (id_pesanan, jumlah_pembayaran, metode_pembayaran, status_pembayaran, tgl_pembayaran) 
                          VALUES ('$id', '{$d['jumlah_pembayaran']}', '{$d['metode_pembayaran']}', '{$d['status_pembayaran']}', '{$d['tgl_pembayaran']}')";
            }
            mysqli_query($conn, $sql_b);

            // 3. Sinkronisasi Tabel PENGANTARAN
            $check_a = mysqli_query($conn, "SELECT id_pesanan FROM pengantaran WHERE id_pesanan = '$id'");
            if (mysqli_num_rows($check_a) > 0) {
                $sql_a = "UPDATE pengantaran SET 
                          status_pengantaran = '{$d['status_pengantaran']}', 
                          nama_pegawai = '{$d['nama_pegawai']}', 
                          waktu_ambil = '{$d['waktu_ambil']}', 
                          waktu_selesai = '{$d['waktu_selesai']}' 
                          WHERE id_pesanan = '$id'";
            } else {
                $sql_a = "INSERT INTO pengantaran (id_pesanan, status_pengantaran, nama_pegawai, waktu_ambil, waktu_selesai) 
                          VALUES ('$id', '{$d['status_pengantaran']}', '{$d['nama_pegawai']}', '{$d['waktu_ambil']}', '{$d['waktu_selesai']}')";
            }
            mysqli_query($conn, $sql_a);
        }
    }
}


?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Dashboard</h1>
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
                        <h3><?= htmlspecialchars($total_pesanan_hari_ini) ?></h3>
                        <p>Total Pesanan Hari Ini</p>
                    </div>
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= htmlspecialchars($total_galon_hari_ini) ?></h3>
                        <p>Total Galon Hari Ini</p>
                    </div>
                    <div class="icon"><i class="fas fa-bottle-water"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= htmlspecialchars($total_pesanan_aktif) ?></h3>
                        <p>Pesanan Aktif</p>
                    </div>
                    <div class="icon"><i class="fas fa-truck-fast"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3 style="font-size: 1.75rem; word-wrap: break-word;">
                            <?= htmlspecialchars($pendapatan_hari_ini_formatted) ?></h3>
                        <p>Pendapatan Lunas Hari Ini</p>
                    </div>
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="card-title">Statistik Penjualan Mingguan</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklySalesChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="card-title">Pesanan Terkini</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-valign-middle">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Nama Pelanggan</th>
                                    <th>Jumlah</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result_terkini && mysqli_num_rows($result_terkini) > 0) {
                                    while ($r = mysqli_fetch_assoc($result_terkini)) {
                                        echo "<tr>";
                                        echo "<td>" . date('H:i', strtotime($r['tgl_pesan'])) . "</td>";
                                        echo "<td>" . htmlspecialchars($r['nama_plng']) . "</td>";
                                        echo "<td>" . htmlspecialchars($r['galon']) . " Galon</td>";
                                        echo "<td><a href='index.php?menu=pemesanan&act=detail&id_pesanan=" . htmlspecialchars($r['id_pesanan']) . "' class='btn btn-sm btn-info'>Lihat</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center'>Belum ada pesanan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header border-0">
                        <h3 class="card-title">Statistik Penjualan Per Jam (7 Hari Terakhir)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlySalesChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Chart Mingguan (KODE ASLI ANDA) ---
        const ctx = document.getElementById('weeklySalesChart').getContext('2d');

        const chartLabels = <?= json_encode($chart_labels) ?>;
        const chartData = <?= json_encode($chart_data) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Galon',
                    data: chartData,
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                    borderColor: 'rgba(0, 123, 255, 1)',
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

        // --- KODE BARU: Chart Harian (Per Jam) untuk 7 HARI ---
        const ctxHourly = document.getElementById('hourlySalesChart').getContext('2d');

        // Ambil label X-Axis (00:00 - 23:00) dari PHP
        const hourlyLabels = <?= json_encode($hourly_labels) ?>;

        // Ambil 7 dataset (satu per hari) dari PHP
        const hourlyDatasets = <?= json_encode($hourly_datasets) ?>;

        new Chart(ctxHourly, {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: hourlyDatasets // Langsung gunakan array of objects dari PHP
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { // Pengaturan agar tooltip menampilkan semua data per jam
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true, // Tampilkan legenda (wajib untuk 7 warna)
                        position: 'top', // Posisi legenda di atas
                    }
                }
            }
        });
        // --- AKHIR KODE BARU ---
    });
</script>