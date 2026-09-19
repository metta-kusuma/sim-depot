<?php
// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = $act ?? '';
$menu = $menu ?? 'pemesanan';
// Ambil level dari session
$level_lowercase = strtolower($_SESSION['level'] ?? '');
$current_user_id = $_SESSION['id_user'] ?? 0;

// Ambil data produk yang relevan untuk form tambah/edit (seperti di kode asli)
$produk_options = [];
// Order by FIELD to put ID 4 (Galon Isi Ulang) first in the dropdown
$sql_produk = "SELECT id_produk, nama_produk FROM produk WHERE id_produk IN (1, 3, 4) ORDER BY FIELD(id_produk, 4, 1, 3)";
$query_produk = mysqli_query($conn, $sql_produk);
if ($query_produk) {
    while ($row_produk = mysqli_fetch_assoc($query_produk)) {
        // --- PERBAIKAN: Gunakan id_produk sebagai key ---
        $produk_options[$row_produk['id_produk']] = $row_produk['nama_produk'];
    }
} else {
    // Basic error handling if product fetch fails
    error_log("Failed to fetch products: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pemesanan</title>
    <style>
        /* (CSS Anda tidak berubah) */
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

        /* --- PERUBAHAN: Tambah style untuk tombol verifikasi --- */
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        /* Badge Batal */
        .badge-secondary {
            background-color: #6c757d;
        }

        /* --- AKHIR PERUBAHAN --- */
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
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

        /* --- PERUBAHAN: Style untuk bukti bayar --- */
        .bukti-bayar-container {
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 8px;
        }

        .bukti-bayar-container img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .bukti-bayar-container a {
            display: block;
        }

        .badge {
            display: inline-block;
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .25rem;
        }

        .badge-success {
            background-color: #28a745;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background-color: #dc3545;
        }

        /* Badge Info/Default */
        .badge-info {
            background-color: #17a2b8;
        }

        /* --- AKHIR PERUBAHAN --- */

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
        }
    </style>
</head>

<body>

    <div class="pesanan-container">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Data Pemesanan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href='index.php?menu=pemesanan'>Pemesanan</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // $act = isset($_GET['act']) ? $_GET['act'] : ''; // (Sudah didefinisikan di atas)
        $menuParam = 'pemesanan'; // Menu untuk Admin/Kasir

        // --- LOGIC PAGINATION & FILTER BARU ---
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if (!in_array($limit, [10, 25, 50, 100])) $limit = 10;

        $filter_tanggal = $_GET['filter_tanggal'] ?? date('Y-m-d');
        $filter_nama = $_GET['filter_nama'] ?? '';
        $filter_antar = $_GET['filter_antar'] ?? '';
        $filter_bayar = $_GET['filter_bayar'] ?? '';

        $base_url_params = ['menu' => $menuParam, 'limit' => $limit];
        if (!empty($filter_tanggal)) $base_url_params['filter_tanggal'] = $filter_tanggal;
        if (!empty($filter_nama)) $base_url_params['filter_nama'] = $filter_nama;
        if (!empty($filter_antar) && $filter_antar != 'Semua') $base_url_params['filter_antar'] = $filter_antar;
        if (!empty($filter_bayar) && $filter_bayar != 'Semua') $base_url_params['filter_bayar'] = $filter_bayar;
        $filter_url_string = http_build_query($base_url_params);
        $filter_url_string_page = $filter_url_string;

        if (!empty($filter_url_string)) $filter_url_string = '&' . $filter_url_string;
        // --- AKHIR LOGIC PAGINATION & FILTER BARU ---

        switch ($act) {
            default: // Display Order List

                // 1. QUERY UNTUK MENGHITUNG TOTAL RECORD
                $sql_total = "SELECT COUNT(p.id_pesanan) as total
                                FROM pesanan p
                                LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                                LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                                WHERE 1=1";

                // --- REVISI QUERY UTAMA UNTUK MENGATASI BUG HILANGNYA PEMBAYARAN ---
                $sql_data = "SELECT p.*,
                                     COALESCE(peng.status_pengantaran, 'Belum Diproses') as status_pengantaran_fix,
                                     -- Jika pembayaran NULL, cek apakah pengantaran sudah Batal. Jika Batal, status bayar juga Batal.
                                     COALESCE(pem.status_pembayaran, IF(peng.status_pengantaran = 'Batal', 'Batal', 'Belum Lunas')) as status_pembayaran_fix
                                 FROM pesanan p
                                 LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                                 LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                                 WHERE 1=1";
                // --- AKHIR REVISI QUERY UTAMA ---

                $params = [];
                $types = "";
                if (!empty($filter_tanggal)) {
                    $sql_data .= " AND DATE(p.tgl_pesan) = ?";
                    $sql_total .= " AND DATE(p.tgl_pesan) = ?";
                    $params[] = $filter_tanggal;
                    $types .= "s";
                }
                if (!empty($filter_nama)) {
                    $sql_data .= " AND p.nama_plng LIKE ?";
                    $sql_total .= " AND p.nama_plng LIKE ?";
                    $params[] = "%" . $filter_nama . "%";
                    $types .= "s";
                }
                if (!empty($filter_antar) && $filter_antar != 'Semua') {
                    $sql_data .= " AND COALESCE(peng.status_pengantaran, 'Belum Diproses') = ?";
                    $sql_total .= " AND COALESCE(peng.status_pengantaran, 'Belum Diproses') = ?";
                    $params[] = $filter_antar;
                    $types .= "s";
                }
                if (!empty($filter_bayar) && $filter_bayar != 'Semua') {
                    // --- Penanganan filter Bayar
                    if ($filter_bayar == 'Belum Lunas') {
                        // Cari yang Belum Lunas ATAU yang datanya NULL tapi belum Batal di Pengantaran
                        $sql_data .= " AND (pem.status_pembayaran = 'Belum Lunas' OR (pem.status_pembayaran IS NULL AND peng.status_pengantaran <> 'Batal'))";
                        $sql_total .= " AND (pem.status_pembayaran = 'Belum Lunas' OR (pem.status_pembayaran IS NULL AND peng.status_pengantaran <> 'Batal'))";
                    } else if ($filter_bayar == 'Batal') {
                        // Cari yang datanya NULL TAPI pengantaran Batal
                        $sql_data .= " AND (pem.status_pembayaran IS NULL AND peng.status_pengantaran = 'Batal')";
                        $sql_total .= " AND (pem.status_pembayaran IS NULL AND peng.status_pengantaran = 'Batal')";
                    } else {
                        $sql_data .= " AND pem.status_pembayaran = ?";
                        $sql_total .= " AND pem.status_pembayaran = ?";
                        $params[] = $filter_bayar;
                        $types .= "s";
                    }
                }

                // Hitung total records
                $total_records = 0;
                $stmt_total = mysqli_prepare($conn, $sql_total);
                if ($stmt_total) {
                    if (!empty($types)) mysqli_stmt_bind_param($stmt_total, $types, ...$params);
                    mysqli_stmt_execute($stmt_total);
                    $result_total = mysqli_stmt_get_result($stmt_total);
                    $total_records = mysqli_fetch_assoc($result_total)['total'] ?? 0;
                    mysqli_stmt_close($stmt_total);
                }

                // Hitung total halaman dan offset
                $total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;
                if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
                if ($page < 1) $page = 1;
                $offset = ($page - 1) * $limit;

                // 2. QUERY UNTUK MENGAMBIL DATA
                $sql_data .= " ORDER BY p.id_pesanan DESC LIMIT ? OFFSET ?";

                $types_data = $types . 'ii';
                $params_data = $params;
                $params_data[] = $limit;
                $params_data[] = $offset;

                $stmt = mysqli_prepare($conn, $sql_data);
                if (!$stmt) {
                    echo "<tr><td colspan='9' style='color:red; text-align:center;'>Error preparing statement: " . mysqli_error($conn) . "</td></tr>";
                } else {
                    if (!empty($types_data)) mysqli_stmt_bind_param($stmt, $types_data, ...$params_data);
                    mysqli_stmt_execute($stmt);
                    $tampil = mysqli_stmt_get_result($stmt);
                }
        ?>

                <div class='filter-form-container'>
                    <form method='GET' action='index.php' class='filter-form' id="filterForm">
                        <input type='hidden' name='menu' value='<?php echo $menuParam; ?>'>
                        <input type='hidden' name='page' value='1'>
                        <div class="filter-group">
                            <label for='filter_tanggal'>Tanggal Pesan:</label>
                            <input type='date' name='filter_tanggal' id='filter_tanggal' value='<?php echo htmlspecialchars($filter_tanggal); ?>'>
                        </div>
                        <div class="filter-group">
                            <label for='filter_nama'>Nama Pelanggan:</label>
                            <input type='text' name='filter_nama' id='filter_nama' value='<?php echo htmlspecialchars($filter_nama); ?>' placeholder='Cari Nama...'>
                        </div>
                        <div class="filter-group">
                            <label for='filter_antar'>Status Antar:</label>
                            <select name='filter_antar' id='filter_antar'>
                                <?php $opsi_antar = ['Semua', 'Belum Diproses', 'Diproses', 'Dalam Perjalanan', 'Selesai', 'Batal'];
                                foreach ($opsi_antar as $opsi) echo "<option value='" . htmlspecialchars($opsi) . "' " . ($filter_antar == $opsi ? 'selected' : '') . ">" . htmlspecialchars($opsi) . "</option>"; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for='filter_bayar'>Status Bayar:</label>
                            <select name='filter_bayar' id='filter_bayar'>
                                <?php $opsi_bayar = ['Semua', 'Belum Lunas', 'Menunggu Konfirmasi', 'Lunas', 'Batal']; // Tambah opsi Batal untuk Filter
                                foreach ($opsi_bayar as $opsi) echo "<option value='" . htmlspecialchars($opsi) . "' " . ($filter_bayar == $opsi ? 'selected' : '') . ">" . htmlspecialchars($opsi) . "</option>"; ?>
                            </select>
                        </div>

                        <div class="filter-group limit-group">
                            <label for="limitSelect">Tampilkan:</label>
                            <select name="limit" id="limitSelect" onchange="document.getElementById('filterForm').submit()">
                                <?php
                                $limit_options = [10, 25, 50, 100];
                                foreach ($limit_options as $opt) {
                                    $selected = ($limit == $opt) ? 'selected' : '';
                                    echo "<option value='{$opt}' {$selected}>{$opt} baris</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class='filter-buttons'>
                            <button type='submit' class="btn btn-primary btn-sm">Filter</button>
                            <a href='index.php?menu=<?php echo $menuParam; ?>' class='btn btn-reset btn-sm'>Reset</a>
                        </div>
                    </form>
                </div>



                <h2>Daftar Pesanan</h2>
                <div class="table-responsive">
                    <table class='pesanan-table' id='example1'>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>No. Telp</th>
                                <th>Tgl Pesan</th>
                                <th>Produk</th>
                                <th>Jumlah</th>
                                <th>Status Antar</th>
                                <th>Status Bayar</th>
                                <th class="actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (isset($tampil) && mysqli_num_rows($tampil) > 0) {
                                while ($r = mysqli_fetch_array($tampil)) {
                                    $id_pesanan = htmlspecialchars($r['id_pesanan']);
                                    $nama_pelanggan = htmlspecialchars($r['nama_plng']);
                                    $nama_produk_pesanan = htmlspecialchars($r['nama_produk'] ?? 'N/A');
                                    $jumlah_pesanan = htmlspecialchars($r['galon'] ?? '0');

                                    // --- Ambil status yang sudah diperbaiki dari query ---
                                    $status_antar = $r['status_pengantaran_fix'];
                                    $status_bayar = $r['status_pembayaran_fix'];


                                    // Link Aksi
                                    $link_edit = "index.php?menu=" . $menuParam . "&act=edit&id_pesanan=" . $id_pesanan . $filter_url_string;
                                    $link_hapus = "./data.php?menu=" . $menuParam . "&act=batal&id_pesanan=" . $id_pesanan . $filter_url_string;
                                    $link_bayar = "index.php?menu=" . $menuParam . "&act=bayar&id_pesanan=" . $id_pesanan . $filter_url_string;
                                    $link_detail_antar = "index.php?menu=" . $menuParam . "&act=detail_pengantaran&id_pesanan=" . $id_pesanan;
                                    $link_detail_bayar = "index.php?menu=" . $menuParam . "&act=detail_pembayaran&id_pesanan=" . $id_pesanan;

                                    // NEW: Link untuk mengubah status menjadi Diproses
                                    $link_proses = "./data.php?menu=" . $menuParam . "&act=proses_pesanan&id_pesanan=" . $id_pesanan . $filter_url_string;

                                    echo "<tr>";
                                    echo "<td data-label='ID'>" . $id_pesanan . "</td>";
                                    echo "<td data-label='Pelanggan'>" . $nama_pelanggan . "</td>";
                                    echo "<td data-label='No. Telp'>" . htmlspecialchars($r['no_telp']) . "</td>";
                                    echo "<td data-label='Tgl Pesan'>" . date('d M Y, H:i', strtotime($r['tgl_pesan'])) . "</td>";
                                    echo "<td data-label='Produk'>" . $nama_produk_pesanan . "</td>";
                                    echo "<td data-label='Jumlah'>" . $jumlah_pesanan . "</td>";

                                    // Status Antar
                                    echo "<td data-label='Status Antar'>";
                                    $badge_antar_class = 'badge-info';
                                    if ($status_antar == 'Selesai') $badge_antar_class = 'badge-success';
                                    else if ($status_antar == 'Batal') $badge_antar_class = 'badge-secondary';
                                    else if ($status_antar == 'Dalam Perjalanan') $badge_antar_class = 'badge-warning';
                                    else if ($status_antar == 'Belum Diproses') $badge_antar_class = 'badge-danger';

                                    if ($status_antar == 'Selesai') {
                                        echo "<a href='" . $link_detail_antar . "'><span class='badge {$badge_antar_class}'>" . htmlspecialchars($status_antar) . "</span></a>";
                                    } else {
                                        echo "<span class='badge {$badge_antar_class}'>" . htmlspecialchars($status_antar) . "</span>";
                                    }
                                    echo "</td>";

                                    // Status Bayar
                                    echo "<td data-label='Status Bayar'>";
                                    $badge_bayar_class = 'badge-danger';
                                    if ($status_bayar == 'Lunas') $badge_bayar_class = 'badge-success';
                                    else if ($status_bayar == 'Menunggu Konfirmasi') $badge_bayar_class = 'badge-warning';
                                    else if ($status_bayar == 'Batal') $badge_bayar_class = 'badge-secondary';

                                    if ($status_bayar == 'Lunas') {
                                        echo "<a href='" . $link_detail_bayar . "'><span class='badge {$badge_bayar_class}'>" . htmlspecialchars($status_bayar) . "</span></a>";
                                    } else if ($status_bayar == 'Batal' || $status_antar == 'Belum Diproses') {
                                        // Tidak bisa diklik jika Batal
                                        echo "<span class='badge {$badge_bayar_class}'>" . htmlspecialchars($status_bayar) . "</span>";
                                    } else {
                                        // Belum Lunas atau Menunggu Konfirmasi (Bisa diklik)
                                        echo "<a href='" . $link_bayar . "'><span class='badge {$badge_bayar_class}'>" . htmlspecialchars($status_bayar) . "</span></a>";
                                    }
                                    echo "</td>";

                                    // Kolom Aksi
                                    echo "<td class='actions'>";
                                    echo "<div class='action-buttons'>";

                                    // =========================================================
                                    // --- NEW LOGIC: JADIKAN DIPROSES (Priority Action) ---
                                    if ($status_antar == 'Belum Diproses') {
                                        // REQUIREMENT: Jika Belum Diproses, hanya tampilkan tombol "Proses Pesanan"
                                        echo "<a href='" . $link_proses . "' class='btn btn-primary btn-sm' onClick=\"return confirm('Yakin ingin mengubah status pesanan #" . $id_pesanan . " menjadi DIPROSES?')\">Proses Pesanan</a>";
                                        echo "<a href='" . $link_hapus . "' class='btn btn-danger btn-sm' onClick=\"return confirm('Batalkan pesanan #" . $id_pesanan . " untuk " . $nama_pelanggan . "?')\"><i class='fas fa-trash'></i></a>";
                                    } else {
                                        // Aksi Pembayaran/Verifikasi (Hanya jika TIDAK Belum Diproses)
                                        if ($status_bayar == 'Lunas') {
                                        } else if ($status_bayar == 'Menunggu Konfirmasi') {
                                            echo "<a href='" . $link_bayar . "' class='btn btn-warning btn-sm'>Verifikasi</a>";
                                        } else if ($status_bayar == 'Belum Lunas') {
                                            echo "<a href='" . $link_bayar . "' class='btn btn-success btn-sm'>Bayar</a>";
                                        }

                                        // Tombol Edit (Hanya tampil jika belum selesai dan belum batal)
                                        if ($status_antar != 'Selesai' && $status_antar != 'Batal') {
                                            echo "<a href='" . $link_edit . "' class='btn btn-info btn-sm'>Edit</a>";
                                        }

                                        // Tombol Batal/Hapus
                                        if ($status_antar != 'Selesai' && $status_antar != 'Batal') {
                                            echo "<a href='" . $link_hapus . "' class='btn btn-danger btn-sm' onClick=\"return confirm('Batalkan pesanan #" . $id_pesanan . " untuk " . $nama_pelanggan . "?')\"><i class='fas fa-trash'></i></a>";
                                        } else if ($status_antar == 'Batal') {
                                            echo "<span class='btn btn-secondary btn-sm disabled'>Batal</span>";
                                        }
                                    }
                                    // =========================================================

                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align:center;'>Tidak ada data pesanan yang sesuai filter.</td></tr>";
                            }
                            if (isset($stmt)) mysqli_stmt_close($stmt);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li><a href="index.php?<?php echo $filter_url_string_page . '&page=' . ($page - 1); ?>">&laquo; Prev</a></li>
                        <?php else: ?><li class="disabled"><span>&laquo; Prev</span></li><?php endif; ?>
                        <?php
                        $num_links = 2;
                        $start = max(1, $page - $num_links);
                        $end = min($total_pages, $page + $num_links);
                        if ($start > 1) echo '<li><a href="index.php?' . $filter_url_string_page . '&page=1">1</a></li><li class="disabled"><span>...</span></li>';
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="<?php if ($i == $page) echo 'active'; ?>">
                                <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                <?php else: ?><a href="index.php?<?php echo $filter_url_string_page . '&page=' . $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor;
                        if ($end < $total_pages) echo '<li class="disabled"><span>...</span></li><li><a href="index.php?' . $filter_url_string_page . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        if ($page < $total_pages): ?>
                            <li><a href="index.php?<?php echo $filter_url_string_page . '&page=' . ($page + 1); ?>">Next &raquo;</a></li>
                        <?php else: ?><li class="disabled"><span>Next &raquo;</span></li><?php endif; ?>
                    </ul>
                    <div style="margin-top: 10px; font-size: 0.9em; color: #555;">
                        Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_records; ?> data)
                    </div>
                </div>
                <br>
            <?php
                break; // End case default



            case "edit":
                // (Kode case 'edit' Anda tidak berubah, masih relevan)
                $id_pesanan_edit = isset($_GET['id_pesanan']) ? mysqli_real_escape_string($conn, $_GET['id_pesanan']) : 0;
                if ($id_pesanan_edit <= 0) {
                    echo "<p style='color:red;'>ID Pesanan tidak valid.</p>";
                    break;
                }
                $hidden_inputs = '';
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar', 'limit'];
                foreach ($filters_to_check as $filter_key) if (isset($_GET[$filter_key]) && !empty($_GET[$filter_key])) $hidden_inputs .= "<input type='hidden' name='{$filter_key}' value='" . htmlspecialchars($_GET[$filter_key]) . "'>\n";
                $link_kembali = "index.php?" . http_build_query($base_url_params);

                // Menggunakan status pengantaran di pengantaran untuk status fix
                $sql_edit = "SELECT p.*, COALESCE(peng.status_pengantaran, 'Belum Diproses') as status_pengantaran_fix
                 FROM pesanan p
                 LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                 WHERE p.id_pesanan = '$id_pesanan_edit'";
                $edit_query = mysqli_query($conn, $sql_edit);
                $r = mysqli_fetch_array($edit_query);
                if (!$r) {
                    echo "<p style='color:red;'>Data pesanan tidak ditemukan.</p>";
                    break;
                }
            ?>
                <div class="form-section">
                    <h2>Edit Pesanan #<?php echo htmlspecialchars($r['id_pesanan']); ?></h2>
                    <form method='POST' action='./data.php?menu=<?php echo $menuParam; ?>&act=update'>
                        <input type='hidden' name='id_pesanan' value='<?php echo htmlspecialchars($r['id_pesanan']); ?>'>
                        <?php echo $hidden_inputs; ?>

                        <?php
                        // --- Asumsi data pesanan sudah dimuat ke array $r ---
                        $nama_pelanggan_saat_ini = htmlspecialchars($r['nama_plng'] ?? 'N/A');
                        $no_telp_saat_ini = htmlspecialchars($r['no_telp'] ?? 'N/A');
                        $id_plng_saat_ini = htmlspecialchars($r['id_plng'] ?? '');
                        ?>
                        <div class="form-group">
                            <label>Pelanggan (Nama - No. Telepon)</label>
                            <input type='text' class='form-control'
                                value='<?php echo "{$nama_pelanggan_saat_ini} - {$no_telp_saat_ini}"; ?>'
                                readonly />

                            <input type='hidden' name='id_plng' value='<?php echo $id_plng_saat_ini; ?>' />
                            <input type='hidden' name='no_telp' value='<?php echo $no_telp_saat_ini; ?>' />
                            <input type='hidden' name='nama_plng' value='<?php echo $nama_pelanggan_saat_ini; ?>' />

                            <?php
                            // --- Asumsi data pesanan sudah dimuat ke array $r ---
                            $tgl_pesan_db = $r['tgl_pesan'] ?? date('Y-m-d H:i:s');
                            $tgl_pesan_value = date('Y-m-d\TH:i', strtotime($tgl_pesan_db));
                            $tgl_pesan_display = date('d/m/Y H:i', strtotime($tgl_pesan_db)); // Format yang lebih mudah dibaca
                            ?>
                            <div class="form-group">
                                <label for="tgl_pesan_display">Tanggal & Waktu Pesan</label>
                                <input type='text' id="tgl_pesan_display" class='form-control'
                                    value='<?php echo $tgl_pesan_display; ?>'
                                    readonly />

                                <input type='hidden' name='tgl_pesan' value='<?php echo $tgl_pesan_value; ?>' />
                            </div>
                            <input type='hidden' name='id_plng' id='id_plng' value='<?php echo htmlspecialchars($r['id_plng']); ?>'>
                            <input type='hidden' name='nama_plng' id='nama_plng' value='<?php echo htmlspecialchars($r['nama_plng']); ?>'>
                        </div>
                        <div class="form-group">
                            <label for="id_produk">Produk</label>
                            <select id="id_produk" name="id_produk" required>
                                <option value="" disabled>-- Pilih Produk --</option>
                                <?php
                                // --- PERBAIKAN: Gunakan id_produk dari $produk_options ---
                                foreach ($produk_options as $id => $nama) {
                                    $selected = ($id == $r['id_produk']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($id) . "' data-nama='" . htmlspecialchars($nama) . "' " . $selected . ">" . htmlspecialchars($nama) . "</option>";
                                }
                                ?>
                            </select>
                            <input type="hidden" name="nama_produk" id="nama_produk_hidden" value="<?php echo htmlspecialchars($r['nama_produk']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="galon">Jumlah</label>
                            <input type='number' id="galon" name='galon' min='1' value='<?php echo htmlspecialchars($r['galon']); ?>' autocomplete='off' required />
                        </div>
                        <div class="form-group">
                            <label for="status_antar">Status Pengantaran</label>
                            <select id="status_antar" name='status_antar'>
                                <?php
                                $current_status_fix = $r['status_pengantaran_fix'];
                                $opsi_wajib = [$current_status_fix, 'Batal'];
                                $displayed_options = array_unique($opsi_wajib);
                                foreach ($displayed_options as $value) {
                                    $selected = ($current_status_fix == $value) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($value) . "' $selected>" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="<?php echo $link_kembali; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary">Update Pesanan</button>
                        </div>
                    </form>
                </div>
                <br><br>
                <?php
                break; // End case edit

            case "bayar":
                // 1. Persiapan Variabel dan Sanitasi
                $hidden_inputs = '';
                $filters_to_check = ['filter_tanggal', 'filter_nama', 'filter_antar', 'filter_bayar', 'limit'];
                foreach ($filters_to_check as $filter_key) if (isset($_GET[$filter_key]) && !empty($_GET[$filter_key])) $hidden_inputs .= "<input type='hidden' name='{$filter_key}' value='" . htmlspecialchars($_GET[$filter_key]) . "'>\n";

                // Tentukan link kembali
                // Asumsi $base_url_params sudah didefinisikan sebelumnya, jika tidak, ganti dengan 'index.php?menu=pesanan'
                $link_kembali = "index.php?" . (isset($base_url_params) ? http_build_query($base_url_params) : 'menu=pesanan');

                $id_pesanan_bayar = isset($_GET['id_pesanan']) ? mysqli_real_escape_string($conn, $_GET['id_pesanan']) : 0;

                if ($id_pesanan_bayar <= 0) {
                    echo "<p class='text-danger'>ID Pesanan tidak valid.</p>";
                    break;
                }

                // 2. Ambil Data Pesanan Utama (untuk form bayar)
                // Ambil juga id_plng untuk query tambahan
                $sql_bayar = "SELECT pem.*, pes.nama_plng, pes.id_plng, pes.no_telp 
                  FROM pembayaran pem 
                  JOIN pesanan pes ON pem.id_pesanan = pes.id_pesanan 
                  WHERE pem.id_pesanan = '$id_pesanan_bayar'";
                $query_bayar = mysqli_query($conn, $sql_bayar);
                $d = mysqli_fetch_assoc($query_bayar);

                if (!$d) {
                    echo "<p class='text-danger'>Data pembayaran tidak ditemukan.</p>";
                    break;
                }

                $current_status = $d['status_pembayaran'];
                $bukti_bayar_url = $d['bukti_pembayaran'] ?? null;
                $id_plng = $d['id_plng'];

                // --- 3. Query Pesanan Belum Lunas Pelanggan yang Sama (Untuk List Atas) ---
                $pesanan_belum_lunas = [];
                $sql_belum_lunas = "SELECT 
                            pem.id_pesanan, 
                            pem.jumlah_pembayaran 
                        FROM pembayaran pem
                        JOIN pesanan pes ON pem.id_pesanan = pes.id_pesanan
                        WHERE pes.id_plng = '$id_plng' 
                        AND pem.status_pembayaran IN ('Belum Lunas', 'Menunggu Konfirmasi')
                        ORDER BY pem.id_pesanan DESC";
                $query_belum_lunas = mysqli_query($conn, $sql_belum_lunas);
                if ($query_belum_lunas) {
                    while ($row = mysqli_fetch_assoc($query_belum_lunas)) {
                        $pesanan_belum_lunas[] = $row;
                    }
                }
                // ---------------------------------------------------------------------------------

                // Tampilkan form berdasarkan status
                if ($current_status == 'Menunggu Konfirmasi') :
                ?>
                    <div class="card card-warning card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Verifikasi Pembayaran Pesanan #<?php echo htmlspecialchars($d['id_pesanan']); ?></h3>
                        </div>
                        <div class="card-body">
                            <form>

                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Pesanan yang Belum Lunas</label>
                                    <div class="col-sm-9">
                                        <div class="card bg-light p-2 mb-2">
                                            <ul class="list-unstyled mb-0">
                                                <?php if (empty($pesanan_belum_lunas)): ?>
                                                    <small class="text-success">Tidak ada tagihan lain.</small>
                                                <?php else: ?>
                                                    <?php foreach ($pesanan_belum_lunas as $pb): ?>
                                                        <?php
                                                        // Logika menyorot pesanan yang sedang aktif
                                                        $class = ($pb['id_pesanan'] == $id_pesanan_bayar) ? 'bg-primary text-white p-1' : 'p-1';
                                                        $link = "index.php?menu=" . $menuParam . "&act=bayar&id_pesanan=" . $pb['id_pesanan'];
                                                        ?>
                                                        <li class='<?= $class ?>'>
                                                            <a href='<?= $link ?>' class='d-flex justify-content-between text-decoration-none <?= ($pb['id_pesanan'] == $id_pesanan_bayar) ? 'text-white' : 'text-dark' ?>'>
                                                                <span>#<?= htmlspecialchars($pb['id_pesanan']) ?></span>
                                                                <span>Rp <?= number_format($pb['jumlah_pembayaran'], 0, ',', '.') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row"><label class="col-sm-3 col-form-label">Nama Pelanggan:</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo htmlspecialchars($d['nama_plng']); ?>" readonly></div>
                                </div>
                                <div class="form-group row"><label class="col-sm-3 col-form-label">Jumlah Tagihan:</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo "Rp " . number_format($d['jumlah_pembayaran'], 0, ',', '.'); ?>" readonly></div>
                                </div>
                                <div class="form-group row"><label class="col-sm-3 col-form-label">Metode Pembayaran:</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo htmlspecialchars($d['metode_pembayaran'] ?? 'Transfer / QRIS'); ?>" readonly></div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Bukti Pembayaran:</label>
                                    <div class="col-sm-9">
                                        <?php
                                        // Pastikan variabel $bukti_bayar_url hanya berisi nama file dari database
                                        if (!empty($bukti_bayar_url)) :
                                            // Gabungkan dengan path folder lokal
                                            $path_lokal = "../assets/bukti_pembayaran/" . htmlspecialchars($bukti_bayar_url);
                                        ?>
                                            <div class="d-flex align-items-center">
                                                <a href="<?php echo $path_lokal; ?>" target="_blank" class="btn btn-sm btn-info mr-2">
                                                    <i class="fa-solid fa-up-right-from-square"></i> Lihat Fullscreen
                                                </a>

                                                <?php
                                                // Opsional: Tampilkan preview kecil jika filenya adalah gambar
                                                $ext = strtolower(pathinfo($bukti_bayar_url, PATHINFO_EXTENSION));
                                                if (in_array($ext, ['jpg', 'jpeg', 'png'])) :
                                                ?>
                                                    <img src="<?php echo $path_lokal; ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <p class="text-danger mb-0"><i class="fa-solid fa-circle-xmark"></i> Bukti pembayaran belum terlampir.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer">
                            <div class="float-right">
                                <a href="./data.php?menu=<?php echo $menuParam; ?>&act=tolak_bukti&id_pesanan=<?php echo $d['id_pesanan']; ?><?php echo $filter_url_string; ?>"
                                    class="btn btn-danger" onclick="return confirm('YAKIN TOLAK BUKTI INI?\nStatus akan kembali ke Belum Lunas dan bukti akan dihapus.')">
                                    <i class="fas fa-times"></i> Tolak Bukti
                                </a>
                                <a href="./data.php?menu=<?php echo $menuParam; ?>&act=verifikasi_lunas&id_pesanan=<?php echo $d['id_pesanan']; ?><?php echo $filter_url_string; ?>"
                                    class="btn btn-success" onclick="return confirm('YAKIN KONFIRMASI PEMBAYARAN INI?\nStatus akan diubah menjadi LUNAS.')">
                                    <i class="fas fa-check"></i> Konfirmasi & Lunas
                                </a>
                            </div>
                            <button type='button' class="btn btn-default" onclick='window.location.href="<?php echo $link_kembali; ?>"'>Batal</button>
                        </div>
                    </div>

                <?php
                else : // Status selain 'Menunggu Konfirmasi' (yaitu 'Belum Lunas' atau 'Lunas')
                    // Tentukan nilai default untuk form
                    // Default metode: Transfer jika ada bukti URL, Tunai jika tidak ada.
                    $default_metode = ($bukti_bayar_url) ? 'Transfer Bank' : 'Tunai';
                    $metode_terpilih = $d['metode_pembayaran'] ?? $default_metode;
                ?>
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Form Pelunasan Pesanan #<?php echo htmlspecialchars($d['id_pesanan']); ?></h3>
                        </div>
                        <div class="card-body">
                            <form method='POST' enctype='multipart/form-data' action='./data.php?menu=<?php echo $menuParam; ?>&act=update_pembayaran'>
                                <input type='hidden' name='id_pembayaran' value='<?php echo htmlspecialchars($d['id_pembayaran']); ?>'>
                                <input type='hidden' name='id_pesanan' value='<?php echo htmlspecialchars($d['id_pesanan']); ?>'>
                                <?php echo $hidden_inputs; ?>

                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Pesanan yang Belum Lunas</label>
                                    <div class="col-sm-9">
                                        <div class="card bg-light p-2 mb-2">
                                            <ul class="list-unstyled mb-0">
                                                <?php if (empty($pesanan_belum_lunas)): ?>
                                                    <small class="text-success">Tidak ada tagihan lain.</small>
                                                <?php else: ?>
                                                    <?php foreach ($pesanan_belum_lunas as $pb): ?>
                                                        <?php
                                                        $class = ($pb['id_pesanan'] == $id_pesanan_bayar) ? 'bg-primary text-white p-1' : 'p-1';
                                                        $link = "index.php?menu=" . $menuParam . "&act=bayar&id_pesanan=" . $pb['id_pesanan'];
                                                        ?>
                                                        <li class='<?= $class ?>'>
                                                            <a href='<?= $link ?>' class='d-flex justify-content-between text-decoration-none <?= ($pb['id_pesanan'] == $id_pesanan_bayar) ? 'text-white' : 'text-dark' ?>'>
                                                                <span>#<?= htmlspecialchars($pb['id_pesanan']) ?></span>
                                                                <span>Rp <?= number_format($pb['jumlah_pembayaran'], 0, ',', '.') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row"><label class="col-sm-3 col-form-label">Nama Pelanggan</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo htmlspecialchars($d['nama_plng']); ?>" readonly></div>
                                </div>
                                <div class="form-group row"><label class="col-sm-3 col-form-label">Jumlah Tagihan</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo "Rp " . number_format($d['jumlah_pembayaran'], 0, ',', '.'); ?>" readonly></div>
                                </div>
                                <div class="form-group row"><label class="col-sm-3 col-form-label">Status Saat Ini</label>
                                    <div class="col-sm-9"><input type="text" class="form-control" value="<?php echo htmlspecialchars($d['status_pembayaran']); ?>" readonly></div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label" for="metode_pembayaran">Metode Pembayaran</label>
                                    <div class="col-sm-9">
                                        <select id="metode_pembayaran" name='metode_pembayaran' class="form-control" style="line-height: normal; height: auto;" required>
                                            <option value='Tunai' <?php if ($metode_terpilih == 'Tunai') echo 'selected'; ?>>Tunai</option>
                                        </select>
                                    </div>
                                </div>




                        </div>
                        <div class="card-footer">
                            <div class="float-right">
                                <?php if ($d['status_pembayaran'] != 'Lunas'): ?>
                                    <button type='submit' class="btn btn-primary">Update Pembayaran & Lunas</button>
                                <?php endif; ?>
                            </div>
                            <button type='button' class="btn btn-default" onclick='window.location.href="<?php echo $link_kembali; ?>"'>Batal</button>
                            </form>
                        </div>
                    </div>

        <?php
                endif; // Akhir dari if($current_status)
                break; // Akhir case 'bayar'

            // (Kode case 'detail_pengantaran' dan 'detail_pembayaran' Anda tidak berubah)
            case "detail_pengantaran":
                $id_pesanan_detail = isset($_GET['id_pesanan']) ? mysqli_real_escape_string($conn, $_GET['id_pesanan']) : 0;
                if ($id_pesanan_detail <= 0) {
                    echo "<p style='color:red;'>ID Pesanan tidak valid.</p>";
                    break;
                }
                $sql_detail_p = "SELECT peng.*, pes.nama_plng FROM pengantaran peng JOIN pesanan pes ON peng.id_pesanan = pes.id_pesanan WHERE peng.id_pesanan = '$id_pesanan_detail'";
                $query_detail_p = mysqli_query($conn, $sql_detail_p);
                $detail = mysqli_fetch_assoc($query_detail_p);
                if ($detail) {
                    echo "<div class='form-section'><h2>Detail Pengantaran Pesanan #" . $id_pesanan_detail . "</h2>";
                    echo "<p><strong>Nama Pelanggan:</strong> " . htmlspecialchars($detail['nama_plng']) . "</p>";
                    echo "<p><strong>ID Pengantaran:</strong> " . htmlspecialchars($detail['id_pengantaran']) . "</p>";
                    echo "<p><strong>Nama Kurir:</strong> " . htmlspecialchars($detail['nama_pegawai'] ?? 'Belum Ditugaskan') . "</p>";
                    echo "<p><strong>Status:</strong> <strong>" . htmlspecialchars($detail['status_pengantaran']) . "</strong></p>";
                    echo "<p><strong>Waktu Diambil:</strong> " . ($detail['waktu_ambil'] ? date('d M Y, H:i', strtotime($detail['waktu_ambil'])) : '-') . "</p>";
                    echo "<p><strong>Waktu Selesai:</strong> " . ($detail['waktu_selesai'] ? date('d M Y, H:i', strtotime($detail['waktu_selesai'])) : '-') . "</p>";
                    echo "<br><button type='button' class='btn btn-secondary' onclick='history.back()'>Kembali</button></div>";
                } else {
                    echo "<br><center><b>Data pengantaran #" . $id_pesanan_detail . " tidak ditemukan.</b><br><br><button type='button' class='btn btn-secondary' onclick='history.back()'>Kembali</button></center>";
                }
                break;

            case "detail_pembayaran":
                $id_pesanan_detail_b = isset($_GET['id_pesanan']) ? (int)$_GET['id_pesanan'] : 0;
                if ($id_pesanan_detail_b <= 0) {
                    echo "<p style='color:red;'>ID Pesanan tidak valid.</p>";
                    break;
                }
                $sql_detail_b = "SELECT pem.*, pes.nama_plng
                                 FROM pembayaran pem
                                 JOIN pesanan pes ON pem.id_pesanan = pes.id_pesanan
                                 WHERE pem.id_pesanan = ?";
                $stmt_detail_b = $conn->prepare($sql_detail_b);
                $stmt_detail_b->bind_param("i", $id_pesanan_detail_b);
                $stmt_detail_b->execute();
                $result_detail_b = $stmt_detail_b->get_result();
                $detail_b = $result_detail_b->fetch_assoc();
                $stmt_detail_b->close();
                if ($detail_b) {
                    echo "<div class='form-section'><h2>Detail Pembayaran Pesanan #" . htmlspecialchars($id_pesanan_detail_b) . "</h2>";
                    echo "<p><strong>Nama Pelanggan:</strong> " . htmlspecialchars($detail_b['nama_plng']) . "</p>";
                    echo "<p><strong>ID Pembayaran:</strong> " . htmlspecialchars($detail_b['id_pembayaran']) . "</p>";
                    echo "<p><strong>Jumlah Tagihan:</strong> Rp " . number_format($detail_b['jumlah_pembayaran'], 0, ',', '.') . "</p>";
                    echo "<p><strong>Metode Pembayaran:</strong> " . htmlspecialchars($detail_b['metode_pembayaran'] ?? '-') . "</p>";
                    echo "<p><strong>Status:</strong> <strong style='color:" . ($detail_b['status_pembayaran'] == 'Lunas' ? 'green' : ($detail_b['status_pembayaran'] == 'Menunggu Konfirmasi' ? '#f39c12' : 'red')) . ";'>" . htmlspecialchars($detail_b['status_pembayaran']) . "</strong></p>";
                    echo "<p><strong>Tanggal Lunas:</strong> " . (!empty($detail_b['tgl_pembayaran']) ? date('d M Y, H:i', strtotime($detail_b['tgl_pembayaran'])) : '-') . "</p>";
                    echo "<p><strong>Bukti Bayar:</strong> ";
                    if (!empty($detail_b['bukti_pembayaran'])) {
                        echo "<a href='" . htmlspecialchars($detail_b['bukti_pembayaran']) . "' target='_blank' class='btn btn-info btn-sm' rel='noopener noreferrer'><i class='fas fa-external-link-alt'></i> Lihat Bukti</a>";
                    } else {
                        echo "Tidak Ada";
                    }
                    echo "</p>";
                    echo "<br><button type'button' class='btn btn-secondary' onclick='history.back()'>Kembali</button></div>";
                } else {
                    echo "<br><center><b>Data pembayaran #" . htmlspecialchars($id_pesanan_detail_b) . " tidak ditemukan.</b><br><br><button type='button' class='btn btn-secondary' onclick='history.back()'>Kembali</button></center>";
                }
                break;
        } // End switch($act)
        ?>
    </div>
    <?php
    // --- JavaScript for Add/Edit Forms (Auto-fill Pelanggan & Nama Produk) ---
    if ($act == 'edit') {
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectPelanggan = document.getElementById('no_telp_select');
                const inputNamaPlng = document.getElementById('nama_plng');
                const inputIdPlng = document.getElementById('id_plng');
                const selectProduk = document.getElementById('id_produk');
                const inputNamaProduk = document.getElementById('nama_produk_hidden');

                function updatePelangganFields() {
                    if (!selectPelanggan) return;
                    const selectedOption = selectPelanggan.options[selectPelanggan.selectedIndex];
                    if (selectedOption && selectedOption.value !== "") {
                        if (inputNamaPlng) inputNamaPlng.value = selectedOption.getAttribute('data-nama') || '';
                        if (inputIdPlng) inputIdPlng.value = selectedOption.getAttribute('data-id') || '';
                    } else {
                        if (inputNamaPlng) inputNamaPlng.value = '';
                        if (inputIdPlng) inputIdPlng.value = '';
                    }
                }

                function updateNamaProdukField() {
                    if (!selectProduk || !inputNamaProduk) return;
                    const selectedOption = selectProduk.options[selectProduk.selectedIndex];
                    if (selectedOption && selectedOption.value !== "") {
                        inputNamaProduk.value = selectedOption.getAttribute('data-nama') || '';
                    } else {
                        inputNamaProduk.value = '';
                    }
                }

                if (selectPelanggan) selectPelanggan.addEventListener('change', updatePelangganFields);
                if (selectProduk) selectProduk.addEventListener('change', updateNamaProdukField);

                updatePelangganFields();
                updateNamaProdukField();
            });
        </script>
    <?php
    }
    ?>
</body>

</html>