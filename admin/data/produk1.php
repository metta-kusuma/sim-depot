<?php
// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = $act ?? '';
$menu = $menu ?? 'produk'; // Pastikan $menu tidak kosong
// Ambil level dari session
$level_lowercase = strtolower($_SESSION['level'] ?? '');
$current_user_id = $_SESSION['id_user'] ?? 0;
// *** ASUMSI: Objek koneksi database ($conn) sudah tersedia dan valid ***
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk</title>
    <style>
        /* Reset dasar & Font */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
        }

        .produk-container {
            padding: 15px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Header Halaman */
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

        /* Tombol */
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

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-xs {
            padding: 4px 8px;
            font-size: 0.75em;
        }

        .btn-reset {
            background-color: #7f8c8d;
            text-decoration: none;
            color: white;
        }

        .btn-reset:hover {
            background-color: #6c757d;
            color: white;
        }

        .btn:disabled,
        .btn-disabled {
            background-color: #bdc3c7;
            color: #7f8c8d;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .add-button-container {
            text-align: right;
            margin-bottom: 20px;
        }

        /* Filter & Search Section */
        .controls-section {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            padding: 15px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
            gap: 15px;
        }

        .filter-search-form {
            display: contents;
        }

        .search-group {
            flex: 1 1 300px;
        }

        .search-group label,
        .limit-group label {
            display: block;
            font-size: 0.85em;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .search-group input[type="text"],
        .limit-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            font-size: 0.9em;
            box-sizing: border-box;
            height: 38px;
        }

        .limit-group {
            flex: 0 1 150px;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            align-self: flex-end;
        }

        .filter-buttons .btn {
            height: 38px;
            line-height: 1.5;
            padding: 8px 15px;
        }

        /* Styling Tabel */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e4f1;
            border-radius: 8px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .produk-table {
            width: 100%;
            border-collapse: collapse;
        }

        .produk-table th,
        .produk-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e4f1;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .produk-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: #5a6a85;
            white-space: nowrap;
        }

        .produk-table th a {
            color: inherit;
            text-decoration: none;
        }

        .produk-table th a:hover {
            color: #34495e;
        }

        .produk-table th .fas {
            margin-left: 5px;
            font-size: 0.9em;
        }

        .produk-table tbody tr:last-child td {
            border-bottom: none;
        }

        .produk-table tbody tr:hover {
            background-color: #f1f5ff;
        }

        .produk-table .actions {
            text-align: center;
            white-space: nowrap;
        }

        .produk-table .actions a,
        .produk-table .actions button {
            margin: 0 3px;
        }

        /* Form Tambah Stok di Tabel (button ke form terpisah) */
        .produk-table .stock-input {
            width: 70px;
            padding: 6px 8px;
            font-size: 0.9em;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 5px;
            box-sizing: border-box;
            height: 32px;
        }

        .produk-table .stock-input:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .produk-table .add-stock-form {
            display: flex;
            align-items: center;
        }

        .produk-table .info-text {
            font-size: 0.8em;
            color: #7f8c8d;
            display: block;
            margin-top: 2px;
        }

        /* Pagination */
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

        /* Form (Tambah & Edit) */
        .form-section {
            padding: 25px;
            margin-bottom: 30px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
            max-width: 600px;
            margin: 20px auto;
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

        /* NEW: Form Tambah Stok spesifik */
        .form-group-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group-row label {
            flex: 0 0 150px;
            margin-bottom: 0;
            font-weight: 600;
            color: #555;
        }

        .form-group-row .input-wrapper {
            flex: 1;
        }

        .form-group-row .input-wrapper input,
        .form-group-row .input-wrapper span {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            background-color: #fff;
            display: block;
        }

        .form-group-row .input-wrapper span {
            background-color: #f0f0f0;
            font-weight: 500;
        }

        .form-group-row .input-wrapper small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 0.85em;
        }

        /* END NEW CSS FOR TAMBAH STOK FORM */

        /* Responsive Table */
        @media screen and (max-width: 768px) {
            .content-header h1 {
                font-size: 1.4em;
            }

            .produk-container {
                padding: 10px;
                margin: 10px;
            }

            .form-section {
                padding: 15px;
            }

            .controls-section {
                flex-direction: column;
                align-items: stretch;
            }

            /* Responsive Form Tambah Stok */
            .form-group-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-group-row label {
                flex-basis: auto;
                margin-bottom: 5px;
            }

            .search-group,
            .limit-group {
                flex-basis: auto;
                width: 100%;
            }

            .limit-group select {
                max-width: 150px;
            }

            .filter-buttons {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
                align-self: stretch;
            }

            .filter-buttons .btn {
                flex: 1 1 50%;
            }

            .produk-table thead {
                display: none;
            }

            .produk-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e0e4f1;
                border-radius: 6px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .produk-table td {
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

            .produk-table td:last-child {
                border-bottom: none;
            }

            .produk-table td::before {
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

            .produk-table .actions {
                text-align: center;
                padding-left: 10px;
            }

            .produk-table .actions::before {
                content: "Aksi";
            }

            .produk-table .add-stock-cell {
                padding-left: 10px;
                text-align: right;
            }

            .produk-table .add-stock-cell::before {
                content: "Tambah Stok";
            }

            .produk-table .add-stock-form {
                justify-content: flex-end;
            }

            /* Ratakan kanan di mobile */
            .pagination li a,
            .pagination li span {
                padding: 6px 10px;
                font-size: 0.9em;
            }
        }
    </style>
    <div class="produk-container">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Manajemen Stok Produk</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href='index.php?menu=<?php echo htmlspecialchars($menu); ?>'>Produk</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        switch ($act) {
            default:
                // --- PAGINATION & FILTER LOGIC ---
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
                $sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_asc';

                $sql_data = "SELECT id_produk, nama_produk, jenis_produk, stock FROM produk WHERE 1=1";
                $sql_total = "SELECT COUNT(*) as total FROM produk WHERE 1=1";
                $params = [];
                $types = '';

                if (!empty($search)) {
                    $sql_data .= " AND nama_produk LIKE ?";
                    $sql_total .= " AND nama_produk LIKE ?";
                    $types .= 's';
                    $params[] = "%" . $search . "%";
                }

                $total_records = 0;
                $stmt_total = mysqli_prepare($conn, $sql_total);
                if ($stmt_total) {
                    if (!empty($types)) mysqli_stmt_bind_param($stmt_total, $types, ...$params);
                    mysqli_stmt_execute($stmt_total);
                    $result_total = mysqli_stmt_get_result($stmt_total);
                    $total_records = mysqli_fetch_assoc($result_total)['total'] ?? 0;
                    mysqli_stmt_close($stmt_total);
                }
                $total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;
                if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
                if ($page < 1) $page = 1;
                $offset = ($page - 1) * $limit;

                switch ($sort) {
                    case 'nama_asc':
                        $sql_data .= " ORDER BY nama_produk ASC";
                        break;
                    case 'nama_desc':
                        $sql_data .= " ORDER BY nama_produk DESC";
                        break;
                    case 'stok_asc':
                        $sql_data .= " ORDER BY stock ASC";
                        break;
                    case 'stok_desc':
                        $sql_data .= " ORDER BY stock DESC";
                        break;
                    default:
                        $sql_data .= " ORDER BY id_produk ASC";
                }

                $sql_data .= " LIMIT ? OFFSET ?";
                $types_data = $types . 'ii';
                $params_data = $params;
                $params_data[] = $limit;
                $params_data[] = $offset;

                $stmt_data = mysqli_prepare($conn, $sql_data);
                if ($stmt_data) {
                    if (!empty($types_data)) mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
                    mysqli_stmt_execute($stmt_data);
                    $tampil = mysqli_stmt_get_result($stmt_data);
                } else {
                    die("Query data failed: " . mysqli_error($conn));
                }

                // Persiapan URL
                $base_url_params = ['menu' => $menu, 'search' => $search, 'limit' => $limit];
                $page_params = $base_url_params + ['sort' => $sort];
                // Link sort untuk 'nama'
                $sort_nama_link = 'index.php?' . http_build_query($base_url_params + ['sort' => ($sort == 'nama_asc' ? 'nama_desc' : 'nama_asc')]);
                $sort_nama_icon = ($sort == 'nama_asc') ? '<i class="fas fa-sort-alpha-down"></i>' : (($sort == 'nama_desc') ? '<i class="fas fa-sort-alpha-up"></i>' : '<i class="fas fa-sort text-muted"></i>');
                // Link sort untuk 'stok'
                $sort_stok_link = 'index.php?' . http_build_query($base_url_params + ['sort' => ($sort == 'stok_asc' ? 'stok_desc' : 'stok_asc')]);
                $sort_stok_icon = ($sort == 'stok_asc') ? '<i class="fas fa-sort-numeric-down"></i>' : (($sort == 'stok_desc') ? '<i class="fas fa-sort-numeric-up"></i>' : '<i class="fas fa-sort text-muted"></i>');
        ?>

                <?php if ($level_lowercase == 'admin'): ?>
                    <div class="add-button-container">
                        <a href="index.php?menu=<?php echo htmlspecialchars($menu); ?>&act=tambah" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Produk Baru
                        </a>
                    </div>
                <?php endif; ?>

                <div class="controls-section">
                    <form method="GET" action="index.php" id="filterForm" class="filter-search-form">
                        <input type="hidden" name="menu" value="<?php echo htmlspecialchars($menu); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                        <div class="search-group">
                            <label for="searchInput">Cari Nama Produk</label>
                            <input type="text" name="search" id="searchInput" class="form-control" placeholder="Ketik nama..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="limit-group">
                            <label for="limitSelect">Tampilkan per Halaman</label>
                            <select name="limit" id="limitSelect" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                <option value="10" <?php if ($limit == 10) echo 'selected'; ?>>10</option>
                                <option value="25" <?php if ($limit == 25) echo 'selected'; ?>>25</option>
                                <option value="50" <?php if ($limit == 50) echo 'selected'; ?>>50</option>
                                <option value="100" <?php if ($limit == 100) echo 'selected'; ?>>100</option>
                            </select>
                        </div>

                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
                            <a href="index.php?menu=<?php echo htmlspecialchars($menu); ?>" class="btn btn-sm btn-reset">Reset</a>
                        </div>
                    </form>
                </div>

                <h2>Daftar Produk</h2>
                <div class="table-responsive">
                    <table class="produk-table" id="tabel-produk">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><a href="<?php echo $sort_nama_link; ?>"><?= $sort_nama_icon ?> Nama Produk</a></th>
                                <th>Jenis</th>
                                <th><a href="<?php echo $sort_stok_link; ?>"><?= $sort_stok_icon ?> Stok</a></th>

                                <?php if ($level_lowercase == 'kasir'): ?>
                                    <th style="width: 150px;">Tambah Stok</th>
                                <?php endif; ?>

                                <?php if ($level_lowercase == 'admin'): ?>
                                    <th class="actions">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($tampil && mysqli_num_rows($tampil) > 0) {
                                while ($r = mysqli_fetch_assoc($tampil)) {
                                    $id_produk = htmlspecialchars($r['id_produk']);
                                    $nama_produk = htmlspecialchars($r['nama_produk']);
                                    $is_disabled = ($id_produk == 4); // Cek apakah ID Galon (4)

                                    $link_edit = "index.php?menu=" . htmlspecialchars($menu) . "&act=edit&id_produk=" . $id_produk;
                                    $link_hapus = "./data.php?menu=" . htmlspecialchars($menu) . "&act=batal&id_produk=" . $id_produk;
                                    $link_tambah_stok = "index.php?menu=" . htmlspecialchars($menu) . "&act=tambah_stok_form&id_produk=" . $id_produk;

                                    echo "<tr>";
                                    echo "<td data-label='ID'>" . $id_produk . "</td>";
                                    echo "<td data-label='Nama'>" . $nama_produk . "</td>";
                                    echo "<td data-label='Jenis'>" . htmlspecialchars($r['jenis_produk']) . "</td>";
                                    echo "<td data-label='Stok'>" . htmlspecialchars($r['stock']) . "</td>";

                                    // Kolom Tambah Stok (Hanya untuk Kasir dan Admin)
                                    if ($level_lowercase == 'kasir') {
                                        echo "<td data-label='Tambah Stok' class='add-stock-cell' style='text-align: center;'>";
                                        if ($is_disabled) {
                                            // Jika ID 4 (Galon), nonaktifkan
                                            echo "<button class='btn btn-xs btn-disabled' disabled><i class='fas fa-ban'></i> Otomatis</button>";
                                            echo "<span class='info-text'>(Stok diupdate via pesanan)</span>";
                                        } else {
                                            // Tombol ke form terpisah
                                            echo "<a href='" . $link_tambah_stok . "' class='btn btn-xs btn-success' title='Tambah Stok'><i class='fas fa-box-open'></i> Tambah Stok</a>";
                                        }
                                        echo "</td>";
                                    }

                                    // --- KOLOM AKSI (HANYA ADMIN) ---
                                    if ($level_lowercase == 'admin') {
                                        echo "<td class='actions'>";
                                        if ($is_disabled) {
                                            // Nonaktifkan Edit/Hapus untuk ID 4 (Galon)
                                            echo "<button class='btn btn-xs btn-success' title='(Otomatis)' disabled><i class='fas fa-edit'></i></button> ";
                                            echo "<button class='btn btn-xs btn-danger' title='(Tidak bisa dihapus)' disabled><i class='fas fa-trash'></i></button>";
                                        } else {
                                            echo "<a href='" . $link_edit . "' class='btn btn-xs btn-success' title='Edit'><i class='fas fa-edit'></i></a> ";
                                            echo "<a href='" . $link_hapus . "' onClick=\"return confirm('Yakin ingin menghapus: " . $nama_produk . "?')\" class='btn btn-xs btn-danger' title='Hapus'><i class='fas fa-trash'></i></a>";
                                        }
                                        echo "</td>";
                                    }
                                    echo "</tr>";
                                }
                            } else {
                                $colspan = ($level_lowercase == 'admin') ? 6 : 5;
                                echo '<tr><td colspan="' . $colspan . '" style="text-align:center;">Data produk tidak ditemukan.</td></tr>';
                            }
                            if ($stmt_data) mysqli_stmt_close($stmt_data);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <ul class="pagination">
                        <?php
                        if (!isset($page_params['menu']) || empty($page_params['menu'])) $page_params['menu'] = $menu;
                        if ($page > 1): ?>
                            <li><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $page - 1])); ?>">&laquo; Prev</a></li>
                        <?php else: ?><li class="disabled"><span>&laquo; Prev</span></li><?php endif; ?>
                        <?php
                        $num_links = 2;
                        $start = max(1, $page - $num_links);
                        $end = min($total_pages, $page + $num_links);
                        if ($start > 1) echo '<li><a href="index.php?' . http_build_query(array_merge($page_params, ['page' => 1])) . '">1</a></li><li class="disabled"><span>...</span></li>';
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="<?php if ($i == $page) echo 'active'; ?>">
                                <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                <?php else: ?><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor;
                        if ($end < $total_pages) echo '<li class="disabled"><span>...</span></li><li><a href="index.php?' . http_build_query(array_merge($page_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                        if ($page < $total_pages): ?>
                            <li><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $page + 1])); ?>">Next &raquo;</a></li>
                        <?php else: ?><li class="disabled"><span>Next &raquo;</span></li><?php endif; ?>
                    </ul>
                    <div style="margin-top: 10px; font-size: 0.9em; color: #555;">
                        Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_records; ?> data)
                    </div>
                </div>
                <br>
            <?php
                break; // Akhir case default

            // =======================================================
            // KASUS BARU: FORMULIR TAMBAH STOK (Akses hanya Kasir/Admin)
            // =======================================================
            case "tambah_stok_form":
                // Hanya Admin atau Kasir yang bisa mengakses form ini
                if ($level_lowercase != 'admin' && $level_lowercase != 'kasir') {
                    echo "<div class='alert alert-danger'>Akses ditolak. Anda tidak memiliki izin menambah stok.</div>";
                    break;
                }

                $id_produk_stok = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
                if ($id_produk_stok <= 0) {
                    echo "<p style='color:red;'>ID Produk tidak valid.</p>";
                    break;
                }

                // Ambil data produk
                $stok_query = mysqli_query($conn, "SELECT nama_produk, stock FROM produk WHERE id_produk='$id_produk_stok'");
                $r_stok = mysqli_fetch_array($stok_query);

                if (!$r_stok) {
                    echo "<p style='color:red;'>Data produk tidak ditemukan.</p>";
                    break;
                }

                $nama_produk_stok = htmlspecialchars($r_stok['nama_produk']);
                $stok_saat_ini = htmlspecialchars($r_stok['stock']);
            ?>
                <div class="form-section">
                    <h2>Tambah Stok Produk: <?php echo $nama_produk_stok; ?></h2>
                    <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menu); ?>&act=tambah_stok">
                        <input type="hidden" name="id_produk" value="<?php echo $id_produk_stok; ?>">
                        <input type="hidden" name="stok_awal" value="<?php echo $stok_saat_ini; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $current_user_id; ?>">


                        <div class="form-group-row">
                            <label>Nama Produk</label>
                            <div class="input-wrapper">
                                <span><?php echo $nama_produk_stok; ?></span>
                            </div>
                        </div>
                        <div class="form-group-row">
                            <label>Stok Saat Ini</label>
                            <div class="input-wrapper">
                                <span><?php echo $stok_saat_ini; ?></span>
                            </div>
                        </div>
                        <div class="form-group-row">
                            <label for="jumlah_tambah">Tambah</label>
                            <div class="input-wrapper">
                                <input type='number' id="jumlah_tambah" name='jumlah_tambah' value="" min="1" placeholder="Masukkan jumlah stok baru" autocomplete='off' required />
                            </div>
                        </div>
                        <div class="form-group-row">
                            <label for="harga_pembelian">Harga Pembelian</label>
                            <div class="input-wrapper">
                                <input type='number' id="harga_pembelian" name='harga_pembelian' value="0" min="0" autocomplete='off' required />
                                <small>Harga pembelian per unit untuk pencatatan HPP/laporan.</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="jenis_transaksi">Jenis Transaksi</label>
                            <select id="jenis_transaksi" name='jenis_transaksi' required>
                                <option value="" disabled selected>-- Pilih Jenis Transaksi --</option>
                                <option value='Pembelian'>Pembelian</option>
                                <option value='Restock'>Restock</option>
                            </select>
                        </div>
                        <div class="form-actions" style="text-align: right;">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo htmlspecialchars($menu); ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary" onclick="return confirm('Yakin ingin menambahkan stok produk ini?');">Simpan</button>
                        </div>
                    </form>
                </div>
                <br><br>
            <?php
                break; // Akhir case tambah_stok_form

            case "tambah":
                // Hanya Admin yang bisa tambah
                if ($level_lowercase != 'admin') {
                    echo "<div class='alert alert-danger'>Akses ditolak. Hanya Admin yang dapat menambah produk baru.</div>";
                    break;
                }
            ?>
                <div class="form-section">
                    <h2>Tambah Produk Baru</h2>
                    <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menu); ?>&act=input">
                        <div class="form-group">
                            <label for="nama_produk">Nama Produk</label>
                            <input type='text' id="nama_produk" name='nama_produk' autocomplete='off' required />
                        </div>
                        <div class="form-group">
                            <label for="jenis_produk">Jenis Produk</label>
                            <select id="jenis_produk" name='jenis_produk' required>
                                <option value="" disabled selected>-- Pilih Jenis --</option>
                                <option value='Galon'>Galon</option>
                                <option value='Kebutuhan Depot'>Kebutuhan Depot</option>
                                <option value='Lainnya'>Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga</label>
                            <input type='number' id="harga" name='harga' value="0" min="0" autocomplete='off' required />
                            <small>Masukkan harga jual produk.</small>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stok Awal</label>
                            <input type='number' id="stock" name='stock' value="0" min="0" autocomplete='off' required />
                            <small>Stok awal akan dicatat di histori.</small>
                        </div>
                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo htmlspecialchars($menu); ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary" onclick="return confirm('Apakah data produk yang Anda masukkan sudah benar dan yakin ingin disimpan?');">Simpan Produk</button>
                        </div>
                    </form>
                </div>
                <br><br>
            <?php
                break; // Akhir case tambah

            case "edit":
                // Hanya Admin yang bisa edit
                if ($level_lowercase != 'admin') {
                    echo "<div class='alert alert-danger'>Akses ditolak. Hanya Admin yang dapat mengedit produk.</div>";
                    break;
                }

                $id_edit = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0; // Ambil id_produk
                if ($id_edit <= 0) {
                    echo "<p style='color:red;'>ID Produk tidak valid.</p>";
                    break;
                }

                // Cek jika ID 4 (Galon), tidak boleh diedit
                if ($id_edit == 4) {
                    echo "<div class='alert alert-warning' style='text-align:center; max-width: 600px; margin: 20px auto;'>";
                    echo "<h4>Aksi Dibatasi</h4>";
                    echo "<p>Produk 'Galon Isi Ulang' (ID 4) adalah produk inti sistem dan tidak dapat diedit atau dihapus secara manual.</p>";
                    echo "<br><button type='button' class='btn btn-secondary' onclick='window.location.href=\"index.php?menu=" . htmlspecialchars($menu) . "\"'>Kembali ke Daftar Produk</button>";
                    echo "</div>";
                    break;
                }

                $edit_query = mysqli_query($conn, "SELECT id_produk, nama_produk, jenis_produk, harga, stock FROM produk WHERE id_produk='$id_edit'");
                $r = mysqli_fetch_array($edit_query);

                if (!$r) {
                    echo "<p style='color:red;'>Data produk tidak ditemukan.</p>";
                    break;
                }
            ?>
                <div class="form-section">
                    <h2>Edit Produk: <?php echo htmlspecialchars($r['nama_produk']); ?></h2>
                    <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menu); ?>&act=update">
                        <input type="hidden" name="id_produk" value="<?php echo htmlspecialchars($r['id_produk']); ?>">

                        <div class="form-group">
                            <label for="nama_produk">Nama Produk</label>
                            <input type='text' id="nama_produk" name='nama_produk' value='<?php echo htmlspecialchars($r['nama_produk']); ?>' required />
                        </div>
                        <div class="form-group">
                            <label for="jenis_produk">Jenis Produk</label>
                            <select id="jenis_produk" name='jenis_produk' required>
                                <option value="" disabled>-- Pilih Jenis --</option>
                                <?php
                                $opsi_jenis = ['Galon', 'Kebutuhan Depot', 'Lainnya'];
                                foreach ($opsi_jenis as $opsi) {
                                    $selected = ($r['jenis_produk'] == $opsi) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($opsi) . "' $selected>" . htmlspecialchars($opsi) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga</label>
                            <input type='number' id="harga" name='harga' value="<?php echo htmlspecialchars($r['harga']); ?>" min="0" autocomplete='off' required />
                        </div>
                        <div class="form-group">
                            <label for="stock">Stok Saat Ini</label>
                            <input type='number' id="stock" name='stock' value='<?php echo htmlspecialchars($r['stock']); ?>' min="0" autocomplete='off' required />
                            <small>Mengubah stok di sini akan tercatat di histori.</small>
                        </div>

                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo htmlspecialchars($menu); ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary" onclick="return confirm('Apakah perubahan data produk ini sudah benar dan yakin ingin diupdate?');">Update Produk</button>
                        </div>
                    </form>
                </div>
                <br><br>
        <?php
                break; // Akhir case edit
        } // Akhir switch($act)
        ?>
    </div>
    <?php
    // --- JavaScript untuk Validasi Form & Search ---
    if ($act == 'tambah' || $act == 'edit' || $act == '' || $act == 'tambah_stok_form') {
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Validasi form (jika ada)
                const form = document.querySelector('.form-section form');
                if (form) {
                    form.addEventListener('submit', function(event) {
                        const stockInput = document.getElementById('stock');
                        const hargaInput = document.getElementById('harga');
                        const jumlahTambahInput = document.getElementById('jumlah_tambah');
                        const hargaPembelianInput = document.getElementById('harga_pembelian');

                        // Validasi Stok (untuk Tambah/Edit Produk)
                        if (stockInput && parseInt(stockInput.value, 10) < 0) {
                            alert('Stok tidak boleh negatif.');
                            event.preventDefault();
                            stockInput.focus();
                            return;
                        }

                        // Validasi Harga Jual (untuk Tambah/Edit Produk)
                        if (hargaInput && parseFloat(hargaInput.value) < 0) {
                            alert('Harga tidak boleh negatif.');
                            event.preventDefault();
                            hargaInput.focus();
                            return;
                        }

                        // Validasi Jumlah Tambah (untuk Form Tambah Stok)
                        if (jumlahTambahInput && parseInt(jumlahTambahInput.value, 10) < 1) {
                            alert('Jumlah tambah stok harus minimal 1.');
                            event.preventDefault();
                            jumlahTambahInput.focus();
                            return;
                        }

                        // Validasi Harga Pembelian (untuk Form Tambah Stok)
                        if (hargaPembelianInput && parseFloat(hargaPembelianInput.value) < 0) {
                            alert('Harga Pembelian tidak boleh negatif.');
                            event.preventDefault();
                            hargaPembelianInput.focus();
                            return;
                        }
                    });
                }

                // Debounce search (hanya di halaman default)
                if (document.getElementById('filterForm')) {
                    const searchInput = document.getElementById('searchInput');
                    const filterForm = document.getElementById('filterForm');
                    let searchTimeout;
                    if (searchInput && filterForm) {
                        searchInput.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(() => {
                                filterForm.submit();
                            }, 700);
                        });
                    }
                }
            });
        </script>
    <?php
    } // Akhir if
    ?>