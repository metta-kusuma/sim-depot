<?php
$menuParam = 'member';

$act = isset($_GET['act']) ? $_GET['act'] : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan</title>
    <style>
        /* Reset dasar & Font */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .pelanggan-container {
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

        .add-button-container {
            text-align: right;
            margin-bottom: 20px;
        }

        /* Filter & Search Section */
        .controls-section {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
            gap: 15px;
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
            /* Lebar dropdown limit */
        }

        /* Styling Tabel Daftar Pelanggan */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e4f1;
            border-radius: 8px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .pelanggan-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pelanggan-table th,
        .pelanggan-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e4f1;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .pelanggan-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: #5a6a85;
            white-space: nowrap;
        }

        .pelanggan-table th a {
            color: inherit;
            text-decoration: none;
        }

        .pelanggan-table th a:hover {
            color: #34495e;
        }

        .pelanggan-table th .fas {
            margin-left: 5px;
            font-size: 0.9em;
        }

        .pelanggan-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pelanggan-table tbody tr:hover {
            background-color: #f1f5ff;
        }

        .pelanggan-table .actions {
            text-align: center;
            white-space: nowrap;
        }

        .pelanggan-table .actions a {
            margin: 0 3px;
        }

        /* Pagination Styling */
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

        /* Styling Form (Tambah & Edit) */
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
        .form-group input[type="tel"],
        .form-group textarea {
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

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .map-container {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #dcdcdc;
            margin-bottom: 5px;
        }

        .map-instruction {
            font-size: 0.85em;
            color: #777;
        }

        .form-actions {
            margin-top: 30px;
            text-align: right;
        }

        .form-actions .btn {
            margin-left: 10px;
        }

        /* Responsive Table */
        @media screen and (max-width: 768px) {
            .content-header h1 {
                font-size: 1.4em;
            }

            .pelanggan-container {
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

            .search-group,
            .limit-group {
                flex-basis: auto;
                width: 100%;
            }

            .limit-group select {
                max-width: 150px;
            }

            /* Batasi lebar dropdown limit */

            .pelanggan-table thead {
                display: none;
            }

            .pelanggan-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e0e4f1;
                border-radius: 6px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .pelanggan-table td {
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

            .pelanggan-table td:last-child {
                border-bottom: none;
            }

            .pelanggan-table td::before {
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

            .pelanggan-table .actions {
                text-align: center;
                padding-left: 10px;
            }

            .pelanggan-table .actions::before {
                content: "";
            }

            .pelanggan-table .actions a {
                margin-bottom: 5px;
            }

            /* Tombol aksi bisa stack */
            .pagination li a,
            .pagination li span {
                padding: 6px 10px;
                font-size: 0.9em;
            }

            /* Perkecil tombol pagination */
        }
    </style>
</head>

<body>

    <div class="pelanggan-container">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Data Pelanggan</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href='index.php?menu=<?php echo $menuParam; ?>'>Pelanggan</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        switch ($act) {
            default:
                // --- PAGINATION & FILTER LOGIC ---
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 per halaman
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $limit;

                $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
                $sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_asc';

                // Base query untuk data
                $sql_data = "SELECT * FROM data_plng WHERE 1=1";
                // Base query untuk menghitung total (tanpa limit/offset)
                $sql_total = "SELECT COUNT(*) as total FROM data_plng WHERE 1=1";

                $params = [];
                $types = '';

                // Kondisi pencarian (tambahkan ke kedua query)
                if (!empty($search)) {
                    $sql_data .= " AND nama_plng LIKE ?";
                    $sql_total .= " AND nama_plng LIKE ?";
                    $types .= 's';
                    $params[] = "%" . $search . "%";
                }

                // Hitung total data
                $total_records = 0;
                $stmt_total = mysqli_prepare($conn, $sql_total);
                if ($stmt_total) {
                    if (!empty($types)) {
                        mysqli_stmt_bind_param($stmt_total, $types, ...$params);
                    }
                    mysqli_stmt_execute($stmt_total);
                    $result_total = mysqli_stmt_get_result($stmt_total);
                    $total_records = mysqli_fetch_assoc($result_total)['total'] ?? 0;
                    mysqli_stmt_close($stmt_total);
                }
                $total_pages = ceil($total_records / $limit);
                if ($page > $total_pages && $total_pages > 0) $page = $total_pages; // Koreksi jika page > max
                if ($page < 1) $page = 1; // Koreksi jika page < 1
                $offset = ($page - 1) * $limit; // Hitung ulang offset

                // Kondisi sorting (hanya untuk query data)
                switch ($sort) {
                    case 'nama_asc':
                        $sql_data .= " ORDER BY nama_plng ASC";
                        break;
                    case 'nama_desc':
                        $sql_data .= " ORDER BY nama_plng DESC";
                        break;
                    default:
                        $sql_data .= " ORDER BY id_plng ASC";
                }

                // Tambahkan LIMIT dan OFFSET ke query data
                $sql_data .= " LIMIT ? OFFSET ?";
                $types .= 'ii'; // Tambahkan tipe integer untuk limit dan offset
                $params[] = $limit;
                $params[] = $offset;

                // Eksekusi query data
                $stmt_data = mysqli_prepare($conn, $sql_data);
                if ($stmt_data) {
                    if (!empty($types)) {
                        mysqli_stmt_bind_param($stmt_data, $types, ...$params);
                    }
                    mysqli_stmt_execute($stmt_data);
                    $tampil = mysqli_stmt_get_result($stmt_data);
                } else {
                    die("Query data failed: " . mysqli_error($conn));
                }

                // Persiapan untuk link sorting & pagination
                $base_url_params = ['menu' => $menuParam, 'search' => $search, 'limit' => $limit];
                $sort_params = $base_url_params; // Untuk link sorting
                $page_params = $base_url_params + ['sort' => $sort]; // Untuk link pagination

                $nextSort = 'nama_asc';
                $sortIcon = '<i class="fas fa-sort text-muted"></i>';
                if ($sort == 'nama_asc') {
                    $nextSort = 'nama_desc';
                    $sortIcon = '<i class="fas fa-sort-alpha-down"></i>';
                } elseif ($sort == 'nama_desc') {
                    $nextSort = 'id_asc';
                    $sortIcon = '<i class="fas fa-sort-alpha-up"></i>';
                } // Kembali ke default ID setelah Z-A
                $sort_params['sort'] = $nextSort;
                $sort_link = 'index.php?' . http_build_query($sort_params);
        ?>

                <div class="add-button-container">
                    <a href="index.php?menu=<?php echo $menuParam; ?>&act=tambah" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Pelanggan
                    </a>
                </div>

                <div class="controls-section">
                    <form method="GET" action="index.php" id="filterForm" class="filter-search-form">
                        <input type="hidden" name="menu" value="<?php echo $menuParam; ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <div class="search-group">
                            <label for="searchInput">Cari Nama Pelanggan</label>
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
                        <button type="submit" class="btn btn-secondary btn-sm" style="height: 38px;">Cari</button>
                        <a href="index.php?menu=<?php echo $menuParam; ?>" class="btn btn-secondary btn-sm" style="height: 38px; line-height: 22px; background-color: #7f8c8d; text-decoration: none;">Reset</a>
                    </form>
                </div>

                <h2>Daftar Pelanggan</h2>
                <div class="table-responsive">
                    <table class="pelanggan-table" id="tabel-pelanggan">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><a href="<?php echo $sort_link; ?>"><?= $sortIcon ?> Nama Pelanggan</a></th>
                                <th>Alamat</th>
                                <th>No. Telepon</th>
                                <th class="actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($tampil && mysqli_num_rows($tampil) > 0) {
                                while ($r = mysqli_fetch_assoc($tampil)) {
                                    $id_plng = htmlspecialchars($r['id_plng']);
                                    $nama_plng = htmlspecialchars($r['nama_plng']);
                                    $link_edit = "index.php?menu=" . $menuParam . "&act=edit&id_plng=" . $id_plng;
                                    $link_hapus = "./data.php?menu=" . $menuParam . "&act=batal&id_plng=" . $id_plng;

                                    echo "<tr>";
                                    echo "<td data-label='ID'>" . $id_plng . "</td>";
                                    echo "<td data-label='Nama'>" . $nama_plng . "</td>";
                                    echo "<td data-label='Alamat'>" . htmlspecialchars($r['alamat']) . "</td>";
                                    echo "<td data-label='No. Telepon'>" . htmlspecialchars($r['no_telp']) . "</td>";
                                    echo "<td class='actions'>";
                                    echo "<a href='" . $link_edit . "' class='btn btn-xs btn-success' title='Edit'><i class='fas fa-edit'></i></a> ";
                                    echo "<a href='" . $link_hapus . "' onClick=\"return confirm('Yakin ingin menghapus: " . $nama_plng . "?')\" class='btn btn-xs btn-danger' title='Hapus'><i class='fas fa-trash'></i></a>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align:center;">Data tidak ditemukan.</td></tr>';
                            }
                            if ($stmt_data) mysqli_stmt_close($stmt_data);
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <ul class="pagination">
                        <?php
                        if (!isset($page_params['menu'])) {
                            $page_params['menu'] = $menuParam; // Tambahkan jika hilang
                        }
                        ?>
                        <?php if ($page > 1): ?>
                            <li><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $page - 1])); ?>">&laquo; Prev</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>&laquo; Prev</span></li>
                        <?php endif; ?>

                        <?php

                        $num_links = 2; // Number of links before and after current page
                        $start = max(1, $page - $num_links);
                        $end = min($total_pages, $page + $num_links);

                        if ($start > 1) echo '<li><a href="index.php?' . http_build_query(array_merge($page_params, ['page' => 1])) . '">1</a></li><li class="disabled"><span>...</span></li>';

                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="<?php if ($i == $page) echo 'active'; ?>">
                                <?php if ($i == $page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor;

                        if ($end < $total_pages) echo '<li class="disabled"><span>...</span></li><li><a href="index.php?' . http_build_query(array_merge($page_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $page + 1])); ?>">Next &raquo;</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Next &raquo;</span></li>
                        <?php endif; ?>
                    </ul>
                    <div style="margin-top: 10px; font-size: 0.9em; color: #555;">
                        Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_records; ?> data)
                    </div>
                </div>
                <br>

            <?php
                break;

            case "tambah":
                $lat_awal = 0.489891;
                $lng_awal = 101.438708; // Default Pekanbaru
            ?>
                <div class="form-section">
                    <h2>Tambah Pelanggan Baru</h2>
                    <form method="POST" action="./data.php?menu=<?php echo $menuParam; ?>&act=input">
                        <div class="form-group">
                            <label for="nama_plng">Nama Pelanggan</label>
                            <input type='text' id="nama_plng" name='nama_plng' autocomplete='off' required />
                        </div>
                        <div class="form-group">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name='alamat' rows='3' required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="no_telp">Nomor Telepon</label>
                            <input type='tel' id="no_telp" name='no_telp' autocomplete='off' value='62' />
                            <small>Awali dengan 62 (contoh: 62812xxxx).</small>
                        </div>
                        <div class="form-group">
                            <label for="lat">Latitude</label>
                            <input type='text' name='lat' id='lat' value="<?php echo $lat_awal; ?>" required readonly />
                        </div>
                        <div class="form-group">
                            <label for="lng">Longitude</label>
                            <input type='text' name='lng' id='lng' value="<?php echo $lng_awal; ?>" required readonly />
                        </div>
                        <div class="form-group">
                            <label>Pilih Lokasi di Peta</label>
                            <div id="map" class="map-container"></div>
                            <small class="map-instruction">Klik pada peta untuk menempatkan pin atau geser pin yang sudah ada.</small>
                        </div>
                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo $menuParam; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary">Simpan Pelanggan</button>
                        </div>
                    </form>
                </div>
                <br><br>
            <?php
                break; // Akhir case tambah

            case "edit":
                $id_edit = isset($_GET['id_plng']) ? mysqli_real_escape_string($conn, $_GET['id_plng']) : 0;
                if ($id_edit <= 0) {
                    echo "<p style='color:red;'>ID Pelanggan tidak valid.</p>";
                    break;
                }

                $edit_query = mysqli_query($conn, "SELECT * FROM data_plng WHERE id_plng='$id_edit'");
                $r = mysqli_fetch_array($edit_query);

                if (!$r) {
                    echo "<p style='color:red;'>Data pelanggan tidak ditemukan.</p>";
                    break;
                }

                $lat_awal = $r['lat'] ?? 0.489891;
                $lng_awal = $r['lng'] ?? 101.438708;
            ?>
                <div class="form-section">
                    <h2>Edit Pelanggan: <?php echo htmlspecialchars($r['nama_plng']); ?></h2>
                    <form method="POST" action="./data.php?menu=<?php echo $menuParam; ?>&act=update">
                        <input type="hidden" name="id_plng" value="<?php echo htmlspecialchars($r['id_plng']); ?>">
                        <div class="form-group">
                            <label for="nama_plng">Nama Pelanggan</label>
                            <input type='text' id="nama_plng" name='nama_plng' value='<?php echo htmlspecialchars($r['nama_plng']); ?>' required />
                        </div>
                        <div class="form-group">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name='alamat' rows='3'><?php echo htmlspecialchars($r['alamat']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="no_telp">Nomor Telepon</label>
                            <input type='tel' id="no_telp" name='no_telp' value='<?php echo htmlspecialchars($r['no_telp']); ?>' />
                            <small>Awali dengan 62.</small>
                        </div>
                        <div class="form-group">
                            <label for="lat">Latitude</label>
                            <input type='text' name='lat' id='lat' value='<?php echo htmlspecialchars($r['lat'] ?? ''); ?>' required readonly />
                        </div>
                        <div class="form-group">
                            <label for="lng">Longitude</label>
                            <input type='text' name='lng' id='lng' value='<?php echo htmlspecialchars($r['lng'] ?? ''); ?>' required readonly />
                        </div>
                        <div class="form-group">
                            <label>Pilih Lokasi di Peta</label>
                            <div id="map" class="map-container"></div>
                            <small class="map-instruction">Klik pada peta untuk menempatkan pin atau geser pin yang sudah ada.</small>
                        </div>
                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo $menuParam; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary">Update Pelanggan</button>
                        </div>
                    </form>
                </div>
                <br><br>
        <?php
                break; // Akhir case edit
        } // Akhir switch($act)
        ?>
    </div> <?php
            // --- JavaScript untuk Peta Leaflet (Hanya di 'tambah' dan 'edit') ---
            if ($act == 'tambah' || $act == 'edit') {
                // Pastikan $lat_awal dan $lng_awal sudah didefinisikan di atas
                $lat_js = $lat_awal ?? 0.489891;
                $lng_js = $lng_awal ?? 101.438708;
            ?>
        <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Cek jika elemen map ada
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    const latInput = document.getElementById('lat');
                    const lngInput = document.getElementById('lng');
                    const initialLat = parseFloat(latInput.value) || <?php echo $lat_js; ?>;
                    const initialLng = parseFloat(lngInput.value) || <?php echo $lng_js; ?>;
                    const mapZoom = (initialLat === <?php echo $lat_js; ?> && initialLng === <?php echo $lng_js; ?>) ? 13 : 16; // Zoom lebih dekat jika sudah ada koordinat

                    const map = L.map('map').setView([initialLat, initialLng], mapZoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);

                    const marker = L.marker([initialLat, initialLng], {
                        draggable: true
                    }).addTo(map);

                    function updateInputsFromMap() {
                        const latlng = marker.getLatLng();
                        if (latInput) latInput.value = latlng.lat.toFixed(8);
                        if (lngInput) lngInput.value = latlng.lng.toFixed(8);
                    }

                    function moveMarkerOnClick(e) {
                        marker.setLatLng(e.latlng);
                        updateInputsFromMap();
                    }

                    marker.on('dragend', updateInputsFromMap);
                    map.on('click', moveMarkerOnClick);

                    // Panggil sekali untuk memastikan value awal benar
                    updateInputsFromMap();
                } else {
                    console.error("Elemen 'map' tidak ditemukan.");
                }
            });
        </script>
    <?php
            } // Akhir if ($act == 'tambah' || $act == 'edit')
    ?>

    <?php
    // --- JavaScript untuk Search Debounce (Hanya di 'default') ---
    if ($act == '') { // Atau default
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const filterForm = document.getElementById('filterForm'); // Gunakan ID form filter
                let searchTimeout;

                if (searchInput && filterForm) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            // Submit form filter saat user berhenti mengetik
                            filterForm.submit();
                        }, 700); // Tunggu 700ms setelah user berhenti mengetik
                    });
                }
            });
        </script>
    <?php
    } // Akhir if ($act == 'default')
    ?>

</body>

</html>