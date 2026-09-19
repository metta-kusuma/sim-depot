<?php
// --- 1. KEAMANAN & PENGATURAN SESI PELANGGAN ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya level 'pelanggan' yang bisa mengakses
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'pelanggan') {
    // Jika bukan pelanggan, redirect ke halaman login
    header("Location: ../login_pelanggan.php");
    exit;
}

// Ambil data pelanggan dari Sesi
$id_pelanggan_login = $_SESSION['id_plng'] ?? 0;
$nama_plng = $_SESSION['nama_plng'] ?? 'Pelanggan';
$no_telp = $_SESSION['no_telp'] ?? '';
// --- AKHIR KEAMANAN ---


// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = $act ?? $_GET['act'] ?? 'default';
$menu = $menu ?? $_GET['menu'] ?? 'pesanan_saya';
$menuParam = $menu; // Gunakan $menuParam agar konsisten

// Ambil data produk yang relevan untuk form tambah
$produk_options = [];
// Ambil SEMUA data produk yang stoknya ada, utamakan Galon (ID 4)
$sql_produk = "SELECT id_produk, nama_produk, harga, stock 
               FROM produk 
               WHERE stock > 0 and id_produk in (1,3,4,6)
               ORDER BY FIELD(id_produk, 4) DESC, nama_produk ASC";
$query_produk = mysqli_query($conn, $sql_produk);
if ($query_produk) {
    while ($row_produk = mysqli_fetch_assoc($query_produk)) {
        // Simpan semua data produk untuk JavaScript
        $produk_options[] = $row_produk;
    }
} else {
    error_log("Gagal mengambil data produk: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pesanan Saya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* (SEMUA CSS BARU ANDA DARI FILE ANDA) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .pesanan-container {
            padding: 15px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .content-header {
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .content-header h1 {
            font-size: 1.6em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 0;
            font-size: 0.9em;
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        .add-button-container {
            text-align: right;
            margin-bottom: 20px;
        }

        .filter-form-container {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #e7eaf3;
            background: #fdfdfd;
            border-radius: 8px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 180px;
        }

        .filter-group.limit-group {
            flex: 0 1 120px;
        }

        .filter-group label {
            font-size: 0.85em;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .filter-group input[type="date"],
        .filter-group input[type="text"],
        .filter-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            font-size: 0.9em;
            box-sizing: border-box;
            height: 38px;
        }

        .filter-buttons {
            margin-left: 10px;
            display: flex;
            gap: 8px;
            align-items: flex-end;
            padding-bottom: 1px;
        }

        .filter-buttons .btn {
            height: 38px;
            padding: 8px 15px;
        }

        .filter-buttons .btn-reset {
            background-color: #7f8c8d;
            color: white;
            text-decoration: none;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e4f1;
            border-radius: 8px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .pesanan-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pesanan-table th,
        .pesanan-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e4f1;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .pesanan-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: #5a6a85;
            white-space: nowrap;
        }

        .pesanan-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pesanan-table tbody tr:hover {
            background-color: #f1f5ff;
        }

        .pesanan-table .actions {
            text-align: center;
            white-space: nowrap;
        }

        .pagination-container {
            text-align: center;
            margin-top: 25px;
            margin-bottom: 10px;
        }

        .pagination {
            display: inline-flex;
            list-style: none;
            padding: 0;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .pagination li {
            margin: 0;
        }

        .pagination li a,
        .pagination li span {
            color: #3498db;
            padding: 8px 14px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-left-width: 0;
            transition: background-color .3s;
            display: block;
            background-color: #fff;
        }

        .pagination li:first-child a,
        .pagination li:first-child span {
            border-left-width: 1px;
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }

        .pagination li:last-child a,
        .pagination li:last-child span {
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
        }

        .pagination li a:hover {
            background-color: #f1f5ff;
        }

        .pagination li.active span {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
            cursor: default;
        }

        .pagination li.disabled span {
            color: #ccc;
            background-color: #f9f9f9;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .form-section {
            padding: 25px;
            margin-bottom: 30px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
        }

        .form-section h2 {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 25px;
            color: #34495e;
            font-weight: 600;
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
        .form-group input[type="datetime-local"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
            background-color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            margin-top: 30px;
            text-align: right;
        }

        .form-actions .btn {
            margin-left: 10px;
        }

        .total-display {
            font-size: 1.5em;
            font-weight: 700;
            color: #3498db;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }

        /* Kartu Pesanan Kustom untuk Pelanggan */
        .order-card {
            background-color: white;
            border: 1px solid #dee2e6;
            border-left: 5px solid #007bff;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            transition: all 0.2s ease-in-out;
        }

        .order-card.status-diproses {
            border-left-color: #007bff;
        }

        /* Biru */
        .order-card.status-perjalanan {
            border-left-color: #ffc107;
        }

        /* Kuning */
        .order-card.status-selesai {
            border-left-color: #28a745;
        }

        /* Hijau */
        .order-card.status-batal {
            border-left-color: #dc3545;
        }

        /* Merah */
        .order-card .info {
            flex-grow: 1;
        }

        .order-card .info .id-pesanan {
            font-size: 0.8em;
            color: #6c757d;
            font-weight: bold;
        }

        .order-card .info .tgl-pesan {
            font-size: 1.2em;
            font-weight: 500;
            color: #333;
            margin: 5px 0;
        }

        .order-card .info .details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.9em;
            color: #444;
        }

        .details span {
            display: flex;
            align-items: center;
        }

        .details i {
            margin-right: 8px;
            width: 15px;
            text-align: center;
        }

        .order-card .action {
            margin-left: auto;
            padding-left: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge.lunas {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.belum-lunas {
            background-color: #f8d7da;
            color: #842029;
        }

        /* Responsive CSS */
        @media screen and (max-width: 768px) {
            .content-header h1 {
                font-size: 1.4em;
            }

            .pesanan-container {
                padding: 10px;
                margin: 10px;
            }

            .form-section {
                padding: 15px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex: 1 1 100%;
            }

            .filter-group.limit-group {
                flex: 1 1 100%;
            }

            .filter-buttons {
                margin-left: 0;
                margin-top: 10px;
                align-self: stretch;
            }

            .filter-buttons .btn {
                flex: 1 1 50%;
            }

            .pesanan-table thead {
                display: none;
            }

            .pesanan-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e0e4f1;
                border-radius: 6px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .pesanan-table td {
                display: block;
                text-align: right;
                border-bottom: 1px dotted #ccc;
                position: relative;
                padding-left: 45%;
                padding-top: 10px;
                padding-bottom: 10px;
                white-space: normal;
                min-height: 25px;
            }

            .pesanan-table td:last-child {
                border-bottom: none;
            }

            .pesanan-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: calc(45% - 15px);
                padding-right: 5px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: #5a6a85;
                font-size: 0.9em;
            }

            .pesanan-table .actions {
                text-align: center;
                padding-left: 10px;
            }

            .pesanan-table .actions::before {
                content: "";
            }

            .pagination li a,
            .pagination li span {
                padding: 6px 10px;
                font-size: 0.9em;
            }

            /* Card List Responsive */
            .order-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-card .action {
                width: 100%;
                margin-left: 0;
                margin-top: 15px;
                padding-left: 0;
            }

            .order-card .action .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="pesanan-container">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Pesanan Saya</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href='index.php?menu=<?php echo $menuParam; ?>'>Pesanan Saya</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Array parameter yang akan dibawa di URL
        $base_url_params = ['menu' => $menuParam];

        switch ($act) {

            // --- KASUS DEFAULT: MENAMPILKAN RIWAYAT PESANAN ---
            default:
        ?>
                <div class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6">
                                <h1 class="m-0 text-dark">Riwayat Pesanan</h1>
                            </div>
                            <div class="col-sm-6">
                                <a href="index.php?menu=<?php echo $menuParam; ?>&act=tambah" class="btn btn-primary float-sm-right">
                                    <i class="fas fa-plus"></i> Buat Pesanan Baru
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='col-12 card-list-container'>
                    <?php
                    // Query untuk mengambil data pesanan HANYA untuk pelanggan ini
                    $sql = "SELECT p.id_pesanan, p.tgl_pesan, p.galon, p.nama_produk,
                                   COALESCE(peng.status_pengantaran, 'Belum Diproses') as status_pengantaran,
                                   COALESCE(pem.status_pembayaran, 'Belum Lunas') as status_pembayaran
                            FROM pesanan p
                            LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                            LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                            WHERE p.id_plng = ?
                            ORDER BY p.tgl_pesan DESC
                            LIMIT 50"; // Batasi 50 pesanan terakhir

                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $id_pelanggan_login);
                    mysqli_stmt_execute($stmt);
                    $tampil = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($tampil) > 0) {
                        while ($r = mysqli_fetch_assoc($tampil)) {
                            // Tentukan style kartu berdasarkan status
                            $status_class = 'status-diproses'; // Default
                            if ($r['status_pengantaran'] == 'Dalam Perjalanan') {
                                $status_class = 'status-perjalanan';
                            } elseif ($r['status_pengantaran'] == 'Selesai') {
                                $status_class = 'status-selesai';
                            } elseif ($r['status_pengantaran'] == 'Batal') {
                                $status_class = 'status-batal';
                            }
                            $status_bayar_text = $r['status_pembayaran'];
                            $status_bayar_class = ($status_bayar_text == 'Lunas') ? 'lunas' : 'belum-lunas';
                    ?>

                            <div class="order-card <?= $status_class ?>">
                                <div class="info">
                                    <div class="id-pesanan">ID PESANAN: <?= htmlspecialchars($r['id_pesanan']) ?></div>
                                    <div class="tgl-pesan"><?= date('d F Y, H:i', strtotime($r['tgl_pesan'])) ?></div>
                                    <div class="details">
                                        <span><i class="fa-solid fa-bottle-water"></i> <?= htmlspecialchars($r['galon']) ?> Galon (<?= htmlspecialchars($r['nama_produk']) ?>)</span>
                                        <span><i class="fa-solid fa-truck"></i> <?= htmlspecialchars($r['status_pengantaran']) ?></span>
                                        <span><span class="status-badge <?= $status_bayar_class ?>"><?= htmlspecialchars($status_bayar_text) ?></span></span>
                                    </div>
                                </div>
                                <div class="action">
                                    <a href="index.php?menu=pesanan_saya&act=detail&id_pesanan=<?= htmlspecialchars($r['id_pesanan']) ?>" class="btn btn-sm btn-info">
                                        Lihat Detail
                                    </a>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        // Tampilkan pesan jika tidak ada riwayat
                        echo "<div class='form-section' style='text-align: center;'>Anda belum memiliki riwayat pesanan.</div>";
                    }
                    mysqli_stmt_close($stmt);
                    ?>
                </div>
            <?php
                break; // End case default

            // --- KASUS TAMBAH: FORMULIR BUAT PESANAN BARU ---
            case "tambah":
                $link_kembali = "index.php?menu=" . $menuParam;
            ?>
                <div class="form-section">
                    <h2>Tambah Pesanan Baru</h2>

                    <form method='POST' action='../pelanggan/data.php' id="form-tambah-pesanan">
                        <input type="hidden" name="menu" value="pesanan">
                        <input type="hidden" name="act" value="input">

                        <div class="form-group">
                            <label>Nama Pelanggan</label>
                            <input type="text" value="<?php echo $nama_plng; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Nomor Telepon</label>
                            <input type="text" value="<?php echo $no_telp; ?>" readonly>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label for="id_produk">Produk</label>
                            <select id="id_produk" name="id_produk" required>
                                <option value="" data-harga="0" data-nama="" data-stok="0">-- Pilih Produk --</option>
                                <?php
                                // Loop dari data produk yang sudah diambil di atas
                                foreach ($produk_options as $p) {
                                    echo sprintf(
                                        '<option value="%d" data-harga="%f" data-nama="%s" data-stok="%d">%s - Rp %s (Stok: %d)</option>',
                                        $p['id_produk'], // Gunakan 'id'
                                        $p['harga'],
                                        htmlspecialchars($p['nama_produk']),
                                        $p['stock'],
                                        htmlspecialchars($p['nama_produk']),
                                        number_format($p['harga'], 0, ',', '.'),
                                        $p['stock']
                                    );
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="galon">Jumlah</label>
                            <input type='number' id="jumlah_galon" name='galon' min='1' value='1' autocomplete='off' required />
                            <small id="stok-warning" style="color: red; display: none; margin-top: 5px;">Stok tidak mencukupi!</small>
                        </div>

                        <input type="hidden" name="nama_produk" id="nama_produk_hidden">

                        <div class="total-display" id="total-harga-display">
                            Total: Rp 0
                        </div>

                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="<?php echo $link_kembali; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary" id="btn-submit-pesan"
                                onclick="return validasiPesananSebelumSubmit();">
                                Pesan Sekarang
                            </button>
                        </div>
                    </form>
                </div>
                <br><br>
            <?php
                break; // End case tambah

            // --- KASUS DETAIL: MELIHAT DETAIL PESANAN (READ-ONLY) ---
            case "detail":
                $id_pesanan = (int)$_GET['id_pesanan'];

                $sql_detail = "SELECT p.*, 
                                    peng.status_pengantaran, peng.nama_pegawai, peng.tracking_token,
                                    pem.status_pembayaran, pem.metode_pembayaran, pem.jumlah_pembayaran, pem.tgl_pembayaran
                               FROM pesanan p
                               LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                               LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                               WHERE p.id_pesanan = ? AND p.id_plng = ?";

                $stmt_detail = mysqli_prepare($conn, $sql_detail);
                mysqli_stmt_bind_param($stmt_detail, "ii", $id_pesanan, $id_pelanggan_login);
                mysqli_stmt_execute($stmt_detail);
                $result_detail = mysqli_stmt_get_result($stmt_detail);
                $d = mysqli_fetch_assoc($result_detail);

                if (!$d) {
                    echo "<div class='form-section' style='text-align: center; border-color: #e74c3c;'>Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.</div>";
                    break;
                }

                // Format data untuk tampilan
                $tgl_pesan_formatted = date('d F Y, H:i', strtotime($d['tgl_pesan']));
                $total_tagihan_formatted = "Rp " . number_format($d['jumlah_pembayaran'] ?? 0, 0, ',', '.');
                $status_antar_text = $d['status_pengantaran'] ?? 'Belum Diproses';
                $status_bayar_text = $d['status_pembayaran'] ?? 'Belum Lunas';
                $metode_bayar_text = $d['metode_pembayaran'] ?? '-';
                $nama_kurir_text = $d['nama_pegawai'] ?? '-';

                $show_track_button = ($status_antar_text == 'Dalam Perjalanan' && !empty($d['tracking_token']));
                $show_BL_bayar = ($status_bayar_text == 'Belum Lunas' && !empty($d['status_pembayaran']));
                // Link pelacakan (keluar dari folder admin, ke root)
                $tracking_link = "../lacak.php?token=" . htmlspecialchars($d['tracking_token'] ?? '');
            ?>

                <div class="form-section">
                    <h2>Detail Pesanan #<?= htmlspecialchars($d['id_pesanan']) ?></h2>

                    <div class="form-group"><label>Tanggal Pesan</label><input type="text" class="form-control" value="<?= $tgl_pesan_formatted ?>" readonly></div>
                    <div class="form-group"><label>Produk</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['nama_produk'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Jumlah</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['galon'] ?? '') ?> Galon" readonly></div>
                    <!--<div class="form-group"><label>Total Tagihan</label><input type="text" class="form-control" value="<?= $total_tagihan_formatted ?>" readonly></div> -->
                    <hr>
                    <div class="form-group"><label>Status Pengantaran</label><input type="text" class="form-control" value="<?= $status_antar_text ?>" readonly></div>
                    <!--<div class="form-group"><label>Nama Kurir</label><input type="text" class="form-control" value="<?= $nama_kurir_text ?>" readonly></div> -->
                    <hr>
                    <div class="form-group"><label>Status Pembayaran</label><input type="text" class="form-control" value="<?= $status_bayar_text ?>" readonly></div>
                    <!--<div class="form-group"><label>Metode Pembayaran</label><input type="text" class="form-control" value="<?= $metode_bayar_text ?>" readonly></div>-->

                    <div class="form-actions">
                        <a href="index.php?menu=pesanan_saya" class="btn btn-secondary">Kembali ke Riwayat</a>
                        <?php if ($show_BL_bayar): ?>
                            <a href="index.php?menu=pembayaran&act=bayar&id_pesanan=<?= htmlspecialchars($d['id_pesanan']) ?>" class="btn btn-primary"> <i class="fas fa-money-bill-wave"></i> Pembayaran</a>
                        <?php endif; ?>
                        <?php if ($show_track_button): ?>
                            <a href="<?= $tracking_link ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-map-marker-alt"></i> Lacak Pesanan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
        <?php
                break; // End case detail

        } // End switch($act)
        ?>
    </div>

    <?php
    // --- JavaScript untuk Halaman "Tambah" (Kalkulator Harga) ---
    if ($act == 'tambah') {
    ?>
        <script>
            /**
             * FUNGSI 1: VALIDASI FORM (Dipanggil oleh tombol 'onclick')
             */
            function validasiPesananSebelumSubmit() {
                try {
                    const produkSelect = document.getElementById('id_produk');

                    // Cek jika produk belum dipilih
                    if (!produkSelect || produkSelect.value === "") {
                        alert("Anda harus memilih produk terlebih dahulu.");
                        produkSelect.focus();
                        return false; // <-- PENTING: Membatalkan pengiriman form
                    }
                } catch (e) {
                    // Jika ada error JS lain, tetap batalkan submit demi keamanan
                    console.error("Error validasi:", e);
                    alert("Terjadi error, coba refresh halaman.");
                    return false;
                }

                // Jika sudah dipilih, lanjutkan pengiriman
                return true;
            }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- TAMBAHAN BARU (Ambil Form) ---
                const form = document.getElementById('form-tambah-pesanan');
                // --- AKHIR TAMBAHAN ---

                const produkSelect = document.getElementById('id_produk');
                const jumlahInput = document.getElementById('jumlah_galon');
                const totalDisplay = document.getElementById('total-harga-display');
                const namaProdukHidden = document.getElementById('nama_produk_hidden');
                const stokWarning = document.getElementById('stok-warning');
                const submitButton = document.getElementById('btn-submit-pesan');

                let hargaSatuan = 0;
                let stokTersedia = 0;

                // --- TAMBAHAN BARU: VALIDASI SEBELUM SUBMIT ---
                if (form) {
                    form.addEventListener('submit', function(event) {
                        // Cek apakah dropdown masih di pilihan default
                        if (produkSelect.value === "") {
                            event.preventDefault(); // HENTIKAN PENGIRIMAN FORM
                            alert("Anda harus memilih produk terlebih dahulu.");
                            produkSelect.focus(); // Fokuskan ke dropdown
                        }
                    });
                }
                // --- AKHIR TAMBAHAN ---

                function hitungTotal() {
                    const jumlah = parseInt(jumlahInput.value) || 0;

                    if (jumlah > stokTersedia && stokTersedia > 0) {
                        stokWarning.innerHTML = 'Stok hanya tersisa ' + stokTersedia + '!';
                        stokWarning.style.display = 'block';
                        submitButton.disabled = true;
                    } else {
                        stokWarning.style.display = 'none';
                        submitButton.disabled = false;
                    }

                    const total = hargaSatuan * jumlah;

                    const formatter = new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0
                    });

                    totalDisplay.textContent = 'Total: ' + formatter.format(total);
                }

                produkSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    hargaSatuan = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
                    stokTersedia = parseInt(selectedOption.getAttribute('data-stok')) || 0;
                    namaProdukHidden.value = selectedOption.getAttribute('data-nama') || '';

                    jumlahInput.max = stokTersedia;

                    if (parseInt(jumlahInput.value) > stokTersedia && stokTersedia > 0) {
                        jumlahInput.value = stokTersedia;
                    } else if (stokTersedia === 0 && selectedOption.value !== "") {
                        jumlahInput.value = 0;
                    }

                    hitungTotal();
                });

                jumlahInput.addEventListener('input', hitungTotal);
            });
        </script>

    <?php

    } // End if ($act == 'tambah')
    ?>
</body>

</html>