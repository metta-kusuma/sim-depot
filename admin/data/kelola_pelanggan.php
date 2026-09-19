<?php
// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = $act ?? '';
// Nama menu disetel ke 'kelola_pengguna' agar konsisten dengan sidebar
$menu = $menu ?? 'pelanggan';

// Ambil ID user yang sedang login dari session
$current_user_id = $_SESSION['id_user'] ?? 0;
// Ambil level user yang sedang login
$current_user_level = strtolower($_SESSION['level'] ?? '');

// --- NEW: AMBIL STATUS WEBHOOK DARI DATABASE ---
$webhook_status_db = 'nonaktif';
$webhook_url_db = '';
// Asumsi: Query ini dijalankan di index.php atau file yang di-include sebelum switch($act)
// Jika tidak, Anda perlu memindahkan koneksi/query ini ke awal file
$query_settings = mysqli_query($conn, "SELECT name, value FROM settings WHERE name IN ('webhook_status', 'webhook_api_url')");
if ($query_settings) {
    while ($row = mysqli_fetch_assoc($query_settings)) {
        if ($row['name'] == 'webhook_status') {
            $webhook_status_db = htmlspecialchars($row['value']);
        }
        if ($row['name'] == 'webhook_api_url') {
            $webhook_url_db = htmlspecialchars($row['value']);
        }
    }
}
$is_webhook_active = ($webhook_status_db == 'aktif');
// ---------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelanggan</title>
    <style>
        /* CSS Anda (dasar) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
        }

        .user-container {
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

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e4f1;
            border-radius: 8px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th,
        .user-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e4f1;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .user-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: #5a6a85;
            white-space: nowrap;
        }

        .user-table th a {
            color: inherit;
            text-decoration: none;
        }

        .user-table th a:hover {
            color: #34495e;
        }

        .user-table th .fas {
            margin-left: 5px;
            font-size: 0.9em;
        }

        .user-table tbody tr:last-child td {
            border-bottom: none;
        }

        .user-table tbody tr:hover {
            background-color: #f1f5ff;
        }

        .user-table .actions {
            text-align: center;
            white-space: nowrap;
        }

        .user-table .actions a,
        .user-table .actions button {
            margin: 0 3px;
        }

        .pagination li a,
        .pagination li span {
            padding: 6px 10px;
            font-size: 0.9em;
        }

        .user-table .btn-buat-akun {
            background-color: #2ecc71;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
        }

        .user-table .btn-buat-akun:hover {
            background-color: #27ae60;
        }

        .badge-tersedia {
            color: green;
            font-weight: bold;
        }

        .badge-tidak-tersedia {
            color: red;
            font-weight: bold;
        }

        .user-table .actions-btn {
            margin-bottom: 5px;
        }

        /* WEBHOOK TOGGLE STYLE */
        .webhook-info-bar {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95em;
        }

        .webhook-info-bar.active {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #c3e6cb;
        }

        .webhook-info-bar.inactive {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .btn-toggle-webhook {
            padding: 5px 10px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
    </style>
    <div class="user-container">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Kelola Pengguna</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href='index.php?menu=<?php echo htmlspecialchars($menu); ?>'>Kelola Pengguna</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <h2 style="font-size: 1.5em; font-weight: 600; color: #34495e; margin-bottom: 20px;">Kelola Pelanggan</h2>

        <?php if ($current_user_level == 'admin'): ?>
            <div class="webhook-info-bar <?php echo $is_webhook_active ? 'active' : 'inactive'; ?>">
                <span>
                    Status WA Webhook:
                    <b><?php echo $is_webhook_active ? 'AKTIF' : 'NONAKTIF'; ?></b>
                    (Pesan akun otomatis terkirim saat buat/reset)
                </span>
                <button id="toggleWebhook" data-status="<?php echo $webhook_status_db; ?>" class="btn-toggle-webhook <?php echo $is_webhook_active ? 'btn-danger' : 'btn-success'; ?>">
                    <?php echo $is_webhook_active ? 'Nonaktifkan' : 'Aktifkan'; ?> Webhook
                </button>
            </div>
            
        <?php endif; ?>
        <?php
        switch ($act) {
            default:
                // --- PAGINATION & FILTER LOGIC ---
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
                $filter = 'pelanggan';

                // Base query: Ambil data pelanggan
                $sql_data = "SELECT id_plng, nama_plng, no_telp, username_login FROM data_plng WHERE 1=1";
                $sql_total = "SELECT COUNT(*) as total FROM data_plng WHERE 1=1";
                $params = [];
                $types = '';

                // Kondisi pencarian (nama_plng ATAU no_telp)
                if (!empty($search)) {
                    $sql_data .= " AND (nama_plng LIKE ? OR no_telp LIKE ?)";
                    $sql_total .= " AND (nama_plng LIKE ? OR no_telp LIKE ?)";
                    $types .= 'ss';
                    $params[] = "%" . $search . "%";
                    $params[] = "%" . $search . "%";
                }

                // Hitung total data
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
                $page = max(1, min($page, $total_pages));
                $offset = ($page - 1) * $limit;

                // Sorting default (berdasarkan ID)
                $sql_data .= " ORDER BY id_plng ASC LIMIT ? OFFSET ?";
                $types_data = $types . 'ii';
                $params_data = $params;
                $params_data[] = $limit;
                $params_data[] = $offset;

                $stmt_data = mysqli_prepare($conn, $sql_data);
                $tampil = null;
                if ($stmt_data) {
                    if (!empty($types_data)) mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
                    mysqli_stmt_execute($stmt_data);
                    $tampil = mysqli_stmt_get_result($stmt_data);
                } else {
                    error_log("Query data pelanggan failed: " . mysqli_error($conn));
                }

                // Persiapan URL Pagination (DENGAN SEMUA FILTER)
                $page_params = [
                    'menu' => $menu,
                    'filter' => $filter,
                    'search' => $search,
                    'limit' => $limit
                ];
        }
        ?>

        <div class="controls-section">
            <form method="GET" action="index.php" id="filterForm" class="filter-search-form">
                <input type="hidden" name="menu" value="<?php echo htmlspecialchars($menu); ?>">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort ?? 'id_asc'); ?>">

                <div class="search-group">
                    <label for="searchInput">Cari Nama / Nomor Telepon</label>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Ketik nama atau no.telp..." value="<?php echo htmlspecialchars($search); ?>">
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
                    <a href="index.php?menu=<?php echo htmlspecialchars($menu); ?>&filter=pelanggan" class="btn btn-sm btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="user-table" id="tabel-pelanggan">
                <thead>
                    <tr>
                        <th style="width:10%;">ID Pelanggan</th>
                        <th style="width:25%;">Nama Pelanggan</th>
                        <th style="width:15%;">Akun</th>
                        <th style="width:20%;">Nomor Telepon</th>
                        <th style="width:30%;" class="actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($tampil && mysqli_num_rows($tampil) > 0) {
                        while ($r = mysqli_fetch_assoc($tampil)) {
                            $id_plng = htmlspecialchars($r['id_plng']);
                            $nama_plng = htmlspecialchars($r['nama_plng']);
                            $no_telp = htmlspecialchars($r['no_telp']);

                            $has_account = !empty($r['username_login']);

                            $status_akun_text = $has_account ? 'Tersedia' : 'Tidak Tersedia';
                            $status_akun_class = $has_account ? 'badge-tersedia' : 'badge-tidak-tersedia';

                            $button_text = $has_account ? 'Reset Akun' : 'Buat Akun';
                            $action_type = $has_account ? 'reset_akun' : 'buat_akun';

                            $action_link = "href='./data.php?menu={$menu}&act={$action_type}&id_plng={$id_plng}'";
                            $confirm_message = "onclick=\"return confirm('Yakin ingin {$button_text} untuk {$nama_plng}? Username akan disetel ke No. Telepon dan sandi baru akan di-generate.')\"";

                            echo "<tr>";
                            echo "<td data-label='ID Pelanggan'>" . sprintf('%03d', $id_plng) . "</td>"; // Format 001, 002, dst.
                            echo "<td data-label='Nama Pelanggan'>" . $nama_plng . "</td>";
                            echo "<td data-label='Akun' class='{$status_akun_class}'>" . $status_akun_text . "</td>";
                            echo "<td data-label='Nomor telepon'>" . $no_telp . "</td>";

                            // Kolom Action
                            echo "<td data-label='Action' class='actions'>";

                            // Logika Admin: Tombol Aksi
                            if ($current_user_level == 'admin') {
                                // Tombol Buat/Reset Akun
                                echo "<a {$confirm_message} {$action_link} class='btn btn-xs btn-primary actions-btn'>" . $button_text . "</a>";
                            } else {
                                // Non-admin: Text statis
                                echo "-";
                            }

                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center;'>Data pelanggan tidak ditemukan.</td></tr>";
                    }
                    if ($stmt_data) mysqli_stmt_close($stmt_data);
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-container">
            <ul class="pagination">
                <?php
                // Logika Pagination Disesuaikan
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

    </div>

    <?php if ($current_user_level == 'admin'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toggleButton = document.getElementById('toggleWebhook');
                const webhookInfoBar = document.querySelector('.webhook-info-bar');

                if (toggleButton) {
                    toggleButton.addEventListener('click', function() {
                        const currentStatus = toggleButton.getAttribute('data-status');
                        const newStatus = (currentStatus === 'aktif') ? 'nonaktif' : 'aktif';
                        const confirmMsg = `Anda yakin ingin ${newStatus.toUpperCase()}KAN pengiriman notifikasi WhatsApp otomatis?`;

                        if (confirm(confirmMsg)) {
                            // Kirim request ke data.php untuk mengubah status
                            window.location.href = `./data.php?menu=pelanggan&act=toggle_webhook&status=${newStatus}`;
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>

    </body>

</html>