<?php
// Pastikan hanya level 'pelanggan' yang bisa mengakses halaman ini
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'pelanggan') {
    die("Akses ditolak. Halaman ini hanya untuk Pelanggan.");
}

// Ambil ID PELANGGAN yang sedang login dari session
$id_pelanggan_login = (int)$_SESSION['id_plng'];
$act = isset($_GET['act']) ? $_GET['act'] : 'default';

// Sertakan koneksi (diasumsikan sudah ada di file pemanggil, misal index.php)
// include "../config/koneksi.php"; 
?>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
</head>
<style>
    /* CSS dasar dari file Anda */
    .card-list-container { padding: 10px; padding-bottom: 100px; }
    .order-card { background-color: white; border: 1px solid #dee2e6; border-left: 5px solid #007bff; border-radius: 8px; margin-bottom: 15px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; align-items: center; transition: all 0.2s ease-in-out; }

    /* --- Style Tambahan untuk Status Pesanan Pelanggan --- */
    .order-card.status-diproses { border-left-color: #007bff; } /* Biru */
    .order-card.status-perjalanan { border-left-color: #ffc107; } /* Kuning */
    .order-card.status-selesai { border-left-color: #28a745; } /* Hijau */
    .order-card.status-batal { border-left-color: #dc3545; } /* Merah */
    /* --- Akhir Style Tambahan --- */

    .order-card .info { flex-grow: 1; }
    .order-card .info .id-pesanan { font-size: 0.8em; color: #6c757d; font-weight: bold; }
    /* Nama pelanggan diubah jadi Tanggal Pesan */
    .order-card .info .tgl-pesan { font-size: 1.2em; font-weight: 500; color: #333; margin: 5px 0; }
    .order-card .info .details { display: flex; flex-direction: column; gap: 8px; font-size: 0.9em; color: #444; }
    .details span { display: flex; align-items: center; }
    .details i { margin-right: 8px; width: 15px; text-align: center; }
    .order-card .action { margin-left: auto; padding-left: 15px; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
    .status-badge.lunas { background-color: #d1e7dd; color: #0f5132; }
    .status-badge.belum-lunas { background-color: #f8d7da; color: #842029; }
    .action-buttons .btn { margin-bottom: 10px; }

    @media (min-width: 768px) { .action-buttons .btn { margin-bottom: 0; } }
    @media (max-width: 767px) {
        .order-card { flex-direction: column; align-items: flex-start; }
        .order-card .action { width: 100%; margin-left: 0; margin-top: 15px; padding-left: 0; }
        .order-card .action .btn { width: 100%; }
    }
</style>

<?php
switch ($act) {
    case 'detail':
        // Ambil ID Pesanan (BUKAN id_pengantaran)
        $id_pesanan = (int)$_GET['id_pesanan'];

        // Query untuk detail pesanan yang sedang aktif
        // KITA CEK id_pesanan DAN id_plng
        $sql_detail = "SELECT p.*, 
                            peng.status_pengantaran, peng.nama_pegawai, peng.tracking_token,
                            pem.status_pembayaran, pem.metode_pembayaran, pem.jumlah_pembayaran, pem.tgl_pembayaran
                       FROM pesanan p
                       LEFT JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan
                       LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                       WHERE p.id_pesanan = ? AND p.id_plng = ?";

        $stmt_detail = mysqli_prepare($conn, $sql_detail);
        // Bind id_pesanan dan id_pelanggan_login
        mysqli_stmt_bind_param($stmt_detail, "ii", $id_pesanan, $id_pelanggan_login);
        mysqli_stmt_execute($stmt_detail);
        $result_detail = mysqli_stmt_get_result($stmt_detail);
        $d = mysqli_fetch_assoc($result_detail);
        
        // Jika data tidak ditemukan (mungkin mencoba akses pesanan orang lain)
        if (!$d) {
            echo "<div class='alert alert-danger'>Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.</div>";
            break; // Hentikan case detail
        }
        
        // Format data untuk tampilan read-only
        $tgl_pesan_formatted = date('d F Y, H:i', strtotime($d['tgl_pesan']));
        $total_tagihan_formatted = "Rp " . number_format($d['jumlah_pembayaran'] ?? 0, 0, ',', '.');
        $status_antar_text = $d['status_pengantaran'] ?? 'Belum Diproses';
        $status_bayar_text = $d['status_pembayaran'] ?? 'Belum Lunas';
        $metode_bayar_text = $d['metode_pembayaran'] ?? '-';
        $nama_kurir_text = $d['nama_pegawai'] ?? '-';
        
        // Cek apakah tombol lacak bisa ditampilkan
        $show_track_button = ($status_antar_text == 'Dalam Perjalanan' && !empty($d['tracking_token']));
        // Tentukan link pelacakan (keluar dari folder admin, ke root)
        $tracking_link = "../lacak.php?token=" . htmlspecialchars($d['tracking_token'] ?? '');
        
        ?>

        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0 text-dark">Detail Pesanan #<?= htmlspecialchars($d['id_pesanan']) ?></h1>
            </div>
        </div>

        <div class="col-12" style="padding: 20px;">
            <div class="card">
                <div class="card-body">
                    <div class="form-group"><label>Tanggal Pesan</label><input type="text" class="form-control" value="<?= $tgl_pesan_formatted ?>" readonly></div>
                    <div class="form-group"><label>Produk</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['nama_produk'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Jumlah</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['galon'] ?? '') ?> Galon" readonly></div>
                    <div class="form-group"><label>Total Tagihan</label><input type="text" class="form-control" value="<?= $total_tagihan_formatted ?>" readonly></div>
                    <hr>
                    <div class="form-group"><label>Status Pengantaran</label><input type="text" class="form-control" value="<?= $status_antar_text ?>" readonly></div>
                    <div class="form-group"><label>Nama Kurir</label><input type="text" class="form-control" value="<?= $nama_kurir_text ?>" readonly></div>
                    <hr>
                    <div class="form-group"><label>Status Pembayaran</label><input type="text" class="form-control" value="<?= $status_bayar_text ?>" readonly></div>
                    <div class="form-group"><label>Metode Pembayaran</label><input type="text" class="form-control" value="<?= $metode_bayar_text ?>" readonly></div>

                    <hr>

                    <div class="row action-buttons">
                        <div class="col-12 col-md-6">
                            <a href="index.php?menu=pesanan_saya" class="btn btn-secondary btn-block">Kembali ke Riwayat</a>
                        </div>
                        
                        <?php if ($show_track_button): ?>
                        <div class="col-12 col-md-6">
                            <a href="<?= $tracking_link ?>" target="_blank" class="btn btn-primary btn-block">
                                <i class="fas fa-map-marker-alt"></i> Lacak Pesanan
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php
        break; // Akhir case 'detail'

    default: // Halaman daftar (default)
        ?>
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Riwayat Pesanan Saya</h1>
                    </div>
                    <div class="col-sm-6">
                        <a href="index.php?menu=buat_pesanan" class="btn btn-primary float-sm-right">
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
                    
                    // Tentukan style badge pembayaran
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
                            <a href="index.php?menu=pesanan_saya&act=detail&id_pesanan=<?= htmlspecialchars($r['id_pesanan']) ?>" class="btn btn-info">
                                Lihat Detail
                            </a>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='alert alert-info text-center'>Anda belum memiliki riwayat pesanan.</div>";
            }
            mysqli_stmt_close($stmt);
            ?>
        </div>
        
        <?php
        break; // Akhir case 'default'
}
?>