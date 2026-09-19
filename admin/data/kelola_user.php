<?php
// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = $act ?? '';
$menu = $menu ?? 'kelola_user'; // Pastikan $menu tidak kosong

// Ambil ID user yang sedang login dari session
$current_user_id = $_SESSION['id_user'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna</title>
    <style>
/* Reset dasar & Font */
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f7fc; }
.user-container { padding: 15px; max-width: 1200px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
/* Header Halaman */
.content-header { padding-bottom: 10px; margin-bottom: 20px; border-bottom: 1px solid #e0e0e0; }
.content-header h1 { font-size: 1.6em; font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
.breadcrumb { background: none; padding: 0; margin-bottom: 0; font-size: 0.9em; }
/* Tombol */
.btn { display: inline-block; padding: 10px 18px; border: none; border-radius: 6px; font-size: 0.95em; cursor: pointer; text-decoration: none; transition: background-color 0.3s ease, box-shadow 0.3s ease; font-weight: 500; text-align: center; line-height: 1.5; margin-bottom: 5px; }
.btn-primary { background-color: #3498db; color: white; }
.btn-primary:hover { background-color: #2980b9; }
.btn-secondary { background-color: #95a5a6; color: white; }
.btn-secondary:hover { background-color: #7f8c8d; }
.btn-danger { background-color: #e74c3c; color: white; }
.btn-danger:hover { background-color: #c0392b; }
.btn-success { background-color: #2ecc71; color: white; }
.btn-success:hover { background-color: #27ae60; }
.btn-xs { padding: 4px 8px; font-size: 0.75em; }
.btn-reset { background-color: #7f8c8d; text-decoration: none; color: white; }
.btn-reset:hover { background-color: #6c757d; color: white; }
.btn:disabled, .btn-disabled { background-color: #bdc3c7; color: #7f8c8d; cursor: not-allowed; opacity: 0.7; }
.add-button-container { text-align: right; margin-bottom: 20px; }
/* Filter & Search Section */
.controls-section { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; padding: 15px; background: #fdfdfd; border: 1px solid #e7eaf3; border-radius: 8px; gap: 15px; }
.filter-search-form { display: contents; }
.search-group { flex: 1 1 300px; }
.search-group label, .limit-group label { display: block; font-size: 0.85em; margin-bottom: 5px; font-weight: 500; color: #555; }
.search-group input[type="text"], .limit-group select { width: 100%; padding: 8px 10px; border: 1px solid #dcdcdc; border-radius: 6px; font-size: 0.9em; box-sizing: border-box; height: 38px; }
.limit-group { flex: 0 1 150px; }
.filter-buttons { display: flex; gap: 8px; align-self: flex-end; }
.filter-buttons .btn { height: 38px; line-height: 1.5; padding: 8px 15px; }

/* Styling Tabel */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #e0e4f1; border-radius: 8px; background-color: #fff; margin-bottom: 20px; }
.user-table { width: 100%; border-collapse: collapse; }
.user-table th, .user-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e4f1; font-size: 0.85em; vertical-align: middle; }
.user-table th { background-color: #f8f9fc; font-weight: 600; color: #5a6a85; white-space: nowrap; }
.user-table th a { color: inherit; text-decoration: none; }
.user-table th a:hover { color: #34495e; }
.user-table th .fas { margin-left: 5px; font-size: 0.9em; }
.user-table tbody tr:last-child td { border-bottom: none; }
.user-table tbody tr:hover { background-color: #f1f5ff; }
.user-table .actions { text-align: center; white-space: nowrap; }
.user-table .actions a, .user-table .actions button { margin: 0 3px; }

/* Pagination Styling */
.pagination-container { text-align: center; margin-top: 25px; margin-bottom: 10px; }
.pagination { display: inline-flex; list-style: none; padding: 0; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.pagination li { margin: 0; }
.pagination li a, .pagination li span { color: #3498db; padding: 8px 14px; text-decoration: none; border: 1px solid #ddd; border-left-width: 0; transition: background-color .3s; display: block; background-color: #fff; }
.pagination li:first-child a, .pagination li:first-child span { border-left-width: 1px; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
.pagination li:last-child a, .pagination li:last-child span { border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
.pagination li a:hover { background-color: #f1f5ff; }
.pagination li.active span { background-color: #3498db; color: white; border-color: #3498db; cursor: default; }
.pagination li.disabled span { color: #ccc; background-color: #f9f9f9; cursor: not-allowed; border-color: #ddd; }

/* Styling Form (Tambah & Edit) */
.form-section { padding: 25px; margin-bottom: 30px; background: #fdfdfd; border: 1px solid #e7eaf3; border-radius: 8px; max-width: 600px; margin: 20px auto; }
.form-section h2 { text-align: center; font-size: 1.5em; margin-bottom: 25px; color: #34495e; font-weight: 600; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.95em; }
.form-group input[type="text"], .form-group input[type="password"], .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid #dcdcdc; border-radius: 6px; box-sizing: border-box;
    font-size: 1em; transition: border-color 0.3s ease; background-color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
.form-group input:focus, .form-group select:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2); }
.form-actions { margin-top: 30px; text-align: right; }
.form-actions .btn { margin-left: 10px; }

/* Responsive Table */
@media screen and (max-width: 768px) {
    .content-header h1 { font-size: 1.4em; }
    .pelanggan-container { padding: 10px; margin: 10px; }
    .form-section { padding: 15px; }
    .controls-section { flex-direction: column; align-items: stretch; }
    .search-group, .limit-group { flex-basis: auto; width: 100%; }
    .limit-group select { max-width: 150px; }
    .filter-buttons { width: 100%; margin-left: 0; margin-top: 10px; align-self: stretch; }
    .filter-buttons .btn { flex: 1 1 50%; }

    .user-table thead { display: none; }
    .user-table tr { display: block; margin-bottom: 15px; border: 1px solid #e0e4f1; border-radius: 6px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .user-table td { display: block; text-align: right; border-bottom: 1px dotted #ccc; position: relative; padding-left: 45%; padding-top: 10px; padding-bottom: 10px; white-space: normal; min-height: 25px; }
    .user-table td:last-child { border-bottom: none; }
    .user-table td::before { content: attr(data-label); position: absolute; left: 10px; width: calc(45% - 15px); padding-right: 5px; white-space: nowrap; text-align: left; font-weight: bold; color: #5a6a85; font-size: 0.9em; }
    .user-table .actions { text-align: center; padding-left: 10px; }
    .user-table .actions::before { content: ""; }
    .user-table .actions a { margin-bottom: 5px; }
    .pagination li a, .pagination li span { padding: 6px 10px; font-size: 0.9em;}
}
</style>
<div class="pelanggan-container"> <div class="content-header">
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

<?php
switch($act) {
    default:
        // --- PAGINATION & FILTER LOGIC ---
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_asc'; // Ganti ke user_login_asc?

        // Base query
        // Asumsi nama tabel adalah 'user'
        $sql_data = "SELECT id_user, user_login, nama_lengkap, level FROM user WHERE 1=1";
        $sql_total = "SELECT COUNT(*) as total FROM user WHERE 1=1";
        $params = []; $types = '';

        // Kondisi pencarian (user_login ATAU nama_lengkap)
        if (!empty($search)) {
            $sql_data .= " AND (user_login LIKE ? OR nama_lengkap LIKE ?)";
            $sql_total .= " AND (user_login LIKE ? OR nama_lengkap LIKE ?)";
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
        $total_pages = $limit > 0 ? ceil($total_records / $limit) : 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        // Kondisi sorting
        switch ($sort) {
            case 'user_login_asc': $sql_data .= " ORDER BY user_login ASC"; break;
            case 'user_login_desc': $sql_data .= " ORDER BY user_login DESC"; break;
            case 'level_asc': $sql_data .= " ORDER BY level ASC, user_login ASC"; break;
            case 'level_desc': $sql_data .= " ORDER BY level DESC, user_login ASC"; break;
            default: $sql_data .= " ORDER BY id_user ASC";
        }

        // Tambah LIMIT & OFFSET
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
        } else { die("Query data failed: " . mysqli_error($conn)); }

        // Persiapan URL
        $base_url_params = ['menu' => $menu, 'search' => $search, 'limit' => $limit];
        $page_params = $base_url_params + ['sort' => $sort];
        // Link sort untuk 'user_login'
        $sort_login_link = 'index.php?' . http_build_query($base_url_params + ['sort' => ($sort == 'user_login_asc' ? 'user_login_desc' : 'user_login_asc')]);
        $sort_login_icon = ($sort == 'user_login_asc') ? '<i class="fas fa-sort-alpha-down"></i>' : (($sort == 'user_login_desc') ? '<i class="fas fa-sort-alpha-up"></i>' : '<i class="fas fa-sort text-muted"></i>');
        // Link sort untuk 'level'
        $sort_level_link = 'index.php?' . http_build_query($base_url_params + ['sort' => ($sort == 'level_asc' ? 'level_desc' : 'level_asc')]);
        $sort_level_icon = ($sort == 'level_asc') ? '<i class="fas fa-sort-alpha-down"></i>' : (($sort == 'level_desc') ? '<i class="fas fa-sort-alpha-up"></i>' : '<i class="fas fa-sort text-muted"></i>');
        ?>

        <div class="add-button-container">
            <a href="index.php?menu=<?php echo htmlspecialchars($menu); ?>&act=tambah" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Pengguna Baru
            </a>
        </div>

        <div class="controls-section">
            <form method="GET" action="index.php" id="filterForm" class="filter-search-form">
                <input type="hidden" name="menu" value="<?php echo htmlspecialchars($menu); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                
                <div class="search-group">
                    <label for="searchInput">Cari Username / Nama Lengkap</label>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Ketik..." value="<?php echo htmlspecialchars($search); ?>">
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

        <h2>Daftar Pengguna</h2>
        <div class="table-responsive">
            <table class="user-table" id="tabel-user">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><a href="<?php echo $sort_login_link; ?>"><?= $sort_login_icon ?> Username</a></th>
                        <th>Nama Lengkap</th>
                        <th><a href="<?php echo $sort_level_link; ?>"><?= $sort_level_icon ?> Level</a></th>
                        <th class="actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($tampil && mysqli_num_rows($tampil) > 0) {
                        while($r = mysqli_fetch_assoc($tampil)) {
                            $user_id = htmlspecialchars($r['id_user']);
                            $user_login_list = htmlspecialchars($r['user_login']);
                            $is_current_user = ($user_id == $current_user_id); // Cek apakah ini user yg sedang login
                            
                            $link_edit = "index.php?menu=".htmlspecialchars($menu)."&act=edit&id_user=".$user_id;
                            $link_hapus = "./data.php?menu=".htmlspecialchars($menu)."&act=batal&id_user=".$user_id;

                            echo "<tr>";
                            echo "<td data-label='ID'>".$user_id."</td>";
                            echo "<td data-label='Username'>".$user_login_list."</td>";
                            echo "<td data-label='Nama Lengkap'>".htmlspecialchars($r['nama_lengkap'])."</td>";
                            echo "<td data-label='Level'>".htmlspecialchars(ucfirst($r['level']))."</td>";
                            echo "<td class='actions'>";
                            echo "<a href='".$link_edit."' class='btn btn-xs btn-success' title='Edit'><i class='fas fa-edit'></i></a> ";
                            
                            // Jangan biarkan admin menghapus dirinya sendiri
                            if ($is_current_user) {
                                echo "<button class='btn btn-xs btn-danger' title='Tidak bisa hapus diri sendiri' disabled><i class='fas fa-trash'></i></button>";
                            } else {
                                echo "<a href='".$link_hapus."' onClick=\"return confirm('Yakin ingin menghapus user: ".$user_login_list."?')\" class='btn btn-xs btn-danger' title='Hapus'><i class='fas fa-trash'></i></a>";
                            }
                            
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo '<tr><td colspan="5" style="text-align:center;">Data pengguna tidak ditemukan.</td></tr>';
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
                $num_links = 2; $start = max(1, $page - $num_links); $end = min($total_pages, $page + $num_links);
                if ($start > 1) echo '<li><a href="index.php?'.http_build_query(array_merge($page_params, ['page' => 1])).'">1</a></li><li class="disabled"><span>...</span></li>';
                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="<?php if ($i == $page) echo 'active'; ?>">
                        <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                        <?php else: ?><a href="index.php?<?php echo http_build_query(array_merge($page_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor;
                if ($end < $total_pages) echo '<li class="disabled"><span>...</span></li><li><a href="index.php?'.http_build_query(array_merge($page_params, ['page' => $total_pages])).'">'.$total_pages.'</a></li>';
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

    case "tambah":
        ?>
        <div class="form-section">
            <h2>Tambah Pengguna Baru</h2>
            <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menu); ?>&act=input" id="form-tambah-user">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type='text' id="nama_lengkap" name='nama_lengkap' autocomplete='off' required/>
                </div>
                <div class="form-group">
                    <label for="user_login">Username</label>
                    <input type='text' id="user_login" name='user_login' autocomplete='off' required/>
                </div>
                 <div class="form-group">
                    <label for="level">Level Pengguna</label>
                    <select id="level" name="level" required>
                         <option value="" disabled selected>-- Pilih Level --</option>
                         <option value="admin">Admin</option>
                         <option value="pimpinan">Pimpinan</option>
                         <option value="kasir">Kasir</option>
                         <option value="anggota">Anggota (Kurir)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password_baru">Password</label>
                    <input type='password' id="password_baru" name='password_baru' required minlength="6"/>
                    <small>Minimal 6 karakter. Password akan di-hash.</small>
                </div>
                 <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <input type='password' id="konfirmasi_password" name='konfirmasi_password' required minlength="6"/>
                </div>

                <div class="form-actions">
                    <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo htmlspecialchars($menu); ?>"'>Batal</button>
                    <button type='submit' class="btn btn-primary">Simpan Pengguna</button>
                </div>
            </form>
        </div>
        <br><br>
        <?php
        break; // Akhir case tambah

    case "edit":
        $id_edit = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0; // Ambil id_user
        if ($id_edit <= 0) { echo "<p style='color:red;'>ID Pengguna tidak valid.</p>"; break; }

        // Ganti nama tabel/kolom jika berbeda
        $edit_query = mysqli_query($conn, "SELECT id_user, user_login, nama_lengkap, level FROM user WHERE id_user='$id_edit'");
        $r = mysqli_fetch_array($edit_query);

        if (!$r) { echo "<p style='color:red;'>Data pengguna tidak ditemukan.</p>"; break; }
        ?>
         <div class="form-section">
            <h2>Edit Pengguna: <?php echo htmlspecialchars($r['user_login']); ?></h2>
            <form method="POST" action="./data.php?menu=<?php echo htmlspecialchars($menu); ?>&act=update">
                <input type="hidden" name="id_user" value="<?php echo htmlspecialchars($r['id_user']); ?>">
                
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type='text' id="nama_lengkap" name='nama_lengkap' value='<?php echo htmlspecialchars($r['nama_lengkap']); ?>' required/>
                </div>
                <div class="form-group">
                    <label for="user_login">Username</label>
                    <input type='text' id="user_login" name='user_login' value='<?php echo htmlspecialchars($r['user_login']); ?>' required/>
                </div>
                 <div class="form-group">
                    <label for="level">Level Pengguna</label>
                    <select id="level" name="level" required <?php if ($r['id_user'] == $current_user_id) echo 'disabled'; // Admin tidak bisa ubah level sendiri ?>>
                         <option value="admin" <?php if($r['level'] == 'admin') echo 'selected'; ?>>Admin</option>
                         <option value="pimpinan" <?php if($r['level'] == 'pimpinan') echo 'selected'; ?>>Pimpinan</option>
                         <option value="kasir" <?php if($r['level'] == 'kasir') echo 'selected'; ?>>Kasir</option>
                         <option value="anggota" <?php if($r['level'] == 'anggota') echo 'selected'; ?>>Anggota (Kurir)</option>
                    </select>
                     <?php if ($r['id_user'] == $current_user_id): ?>
                        <small>Anda tidak dapat mengubah level akun Anda sendiri.</small>
                        <input type="hidden" name="level" value="<?php echo htmlspecialchars($r['level']); ?>" /> <?php endif; ?>
                </div>
                
                <hr style="margin-top: 30px; margin-bottom: 20px;">
                <p style="text-align: center; color: #555;"><b>Ubah Password (Opsional)</b><br><small>Kosongkan jika tidak ingin mengubah password.</small></p>

                <div class="form-group">
                    <label for="password_baru">Password Baru</label>
                    <input type='password' id="password_baru" name='password_baru' minlength="6" placeholder="Minimal 6 karakter"/>
                </div>
                 <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                    <input type='password' id="konfirmasi_password" name='konfirmasi_password' minlength="6"/>
                </div>

                <div class="form-actions">
                    <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo htmlspecialchars($menu); ?>"'>Batal</button>
                    <button type='submit' class="btn btn-primary">Update Pengguna</button>
                </div>
            </form>
        </div>
        <br><br>
        <?php
        break; // Akhir case edit
} // Akhir switch($act)
?>
</div> <?php
// --- JavaScript untuk Validasi Password (Hanya di 'tambah' dan 'edit') ---
if ($act == 'tambah' || $act == 'edit') {
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-tambah-user') || document.getElementById('form-edit-user'); // Cari form yang sesuai
    if (form) {
        form.addEventListener('submit', function(event) {
            const passwordBaru = document.getElementById('password_baru');
            const konfirmasiPassword = document.getElementById('konfirmasi_password');

            // Cek jika kedua field password diisi (untuk 'edit') atau wajib diisi (untuk 'tambah')
            if (passwordBaru.value || konfirmasiPassword.value) {
                if (passwordBaru.value.length < 6) {
                     alert('Password baru minimal harus 6 karakter!');
                     event.preventDefault(); // Mencegah form dikirim
                     passwordBaru.focus();
                     return;
                }
                if (passwordBaru.value !== konfirmasiPassword.value) {
                    alert('Konfirmasi password baru tidak cocok!');
                    event.preventDefault(); // Mencegah form dikirim
                    konfirmasiPassword.focus();
                    return;
                }
            }
        });
    }
});
</script>
<?php
} // Akhir if ($act == 'tambah' || $act == 'edit')
?>

<?php
// --- JavaScript untuk Search Debounce (Hanya di 'default') ---
if ($act == '') {
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    let searchTimeout;
    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { filterForm.submit(); }, 700);
        });
    }
});
</script>
<?php
} // Akhir if ($act == 'default')
?>