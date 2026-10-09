<?php
// Pastikan hanya level 'anggota' yang bisa mengakses halaman ini
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'anggota') {
    die("Akses ditolak. Halaman ini hanya untuk Anggota.");
}

// Ambil ID user yang sedang login dari session
$id_user_login = (int)$_SESSION['id_user'];
$act = isset($_GET['act']) ? $_GET['act'] : 'default';

// Sertakan koneksi (pastikan path ini benar)
// include "../config/koneksi.php"; 
?>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
</head>
<style>
    /* CSS untuk nomor urut */
    .order-card { position: relative; }
    .selection-order {
        position: absolute; top: -10px; left: -10px;
        background-color: #28a745; color: white;
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; justify-content: center; align-items: center;
        font-weight: bold; font-size: 14px; border: 2px solid white;
        opacity: 0; transform: scale(0.5); transition: all 0.2s ease-in-out;
    }
    .order-card.selected .selection-order { opacity: 1; transform: scale(1); }
    
    /* Sisa CSS Anda yang lain (tidak berubah) */
    .card-list-container { padding: 10px; padding-bottom: 100px; }
    .order-card { background-color: white; border: 1px solid #dee2e6; border-left: 5px solid #007bff; border-radius: 8px; margin-bottom: 15px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; align-items: center; cursor: pointer; transition: all 0.2s ease-in-out; }
    .order-card.selected { border-left-color: #28a745; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2); transform: translateY(-2px); }
    .order-card .info { flex-grow: 1; }
    .order-card .info .id-pesanan { font-size: 0.8em; color: #6c757d; font-weight: bold; }
    .order-card .info .nama-pelanggan { font-size: 1.2em; font-weight: 500; color: #333; margin: 5px 0; }
    .order-card .info .details { display: flex; flex-direction: column; gap: 8px; font-size: 0.9em; color: #444; }
    .details span { display: flex; align-items: center; }
    .details i { margin-right: 8px; width: 15px; text-align: center; }
    .order-card .action { margin-left: auto; padding-left: 15px; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
    .status-badge.lunas { background-color: #d1e7dd; color: #0f5132; }
    .status-badge.belum-lunas { background-color: #f8d7da; color: #842029; }
    .action-buttons .btn { margin-bottom: 10px; }
    .bulk-action-footer { position: fixed; bottom: -100px; left: 0; width: 100%; background-color: #ffffff; padding: 15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.15); z-index: 100; transition: bottom 0.3s ease-in-out; }
    .bulk-action-footer.show { bottom: 0; }
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
        $id_pengantaran = (int)$_GET['id_pengantaran'];
        $selected_ids_str = $_GET['selected_ids'] ?? '';

        // --- BLOK BARU: TAMPILKAN DAFTAR PESANAN YANG DIPILIH ---
        $selected_ids = [];
        if (!empty($selected_ids_str)) {
            $id_array = explode(',', $selected_ids_str);
            foreach ($id_array as $id) {
                $selected_ids[] = (int)$id; // Sanitasi
            }
        }
        
        if (!empty($selected_ids)) {
            $in_clause = implode(',', $selected_ids);
            
            // Query untuk mengambil info singkat dari pesanan yang dipilih
            $sql_selected = "SELECT p.id_pesanan, plg.nama_plng, peng.id_pengantaran
                             FROM pengantaran peng
                             JOIN pesanan p ON peng.id_pesanan = p.id_pesanan
                             JOIN data_plng plg ON p.id_plng = plg.id_plng
                             WHERE peng.id_pengantaran IN ($in_clause) AND peng.id_user = $id_user_login
                             ORDER BY FIELD(peng.id_pengantaran, $in_clause)"; // Menjaga urutan pemilihan
            
            $result_selected = mysqli_query($conn, $sql_selected);
            
            if ($result_selected && mysqli_num_rows($result_selected) > 0) {
        ?>
            <div class="col-12" style="padding: 20px 20px 0 20px;">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-list-ol"></i> Pesanan yang Dipilih (<?php echo mysqli_num_rows($result_selected); ?>)
                  </h3>
                </div>
                <div class="card-body p-0">
                  <ul class="nav nav-pills flex-column">
                    <?php
                    while ($s = mysqli_fetch_assoc($result_selected)) {
                        // Tandai link yang sedang aktif (yang sedang dilihat detailnya)
                        $is_active = ($s['id_pengantaran'] == $id_pengantaran) ? 'active' : '';
                    ?>
                    <li class="nav-item">
                      <a href="index.php?menu=pengantaran_saya&act=detail&id_pengantaran=<?php echo $s['id_pengantaran']; ?>&selected_ids=<?php echo htmlspecialchars($selected_ids_str); ?>" class="nav-link <?php echo $is_active; ?>">
                        <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($s['nama_plng']); ?>
                        <span class="float-right badge bg-primary">#<?php echo htmlspecialchars($s['id_pesanan']); ?></span>
                      </a>
                    </li>
                    <?php } ?>
                  </ul>
                </div>
              </div>
            </div>
        <?php
            }
        }
        // --- AKHIR BLOK BARU ---


        // Query untuk detail pesanan yang sedang aktif
        $sql_detail = "SELECT p.*, plg.*, peng.id_pengantaran, pem.status_pembayaran
                       FROM pengantaran peng
                       JOIN pesanan p ON peng.id_pesanan = p.id_pesanan
                       JOIN data_plng plg ON p.id_plng = plg.id_plng
                       LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                       WHERE peng.id_pengantaran = ? AND peng.id_user = ?";

        $stmt_detail = mysqli_prepare($conn, $sql_detail);
        mysqli_stmt_bind_param($stmt_detail, "ii", $id_pengantaran, $id_user_login);
        mysqli_stmt_execute($stmt_detail);
        $result_detail = mysqli_stmt_get_result($stmt_detail);
        $d = mysqli_fetch_assoc($result_detail);
        ?>

        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0 text-dark">Detail Pesanan</h1>
            </div>
        </div>

        <div class="col-12" style="padding: 20px;">
            <div class="card">
                <div class="card-body">
                    <div class="form-group"><label>Nama Pelanggan</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['nama_plng'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Nomor Telepon</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['no_telp'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Jumlah Galon</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['galon'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Status Bayar</label><input type="text" class="form-control" value="<?= htmlspecialchars($d['status_pembayaran'] ?? 'Belum Lunas') ?>" readonly></div>
                    <div class="form-group"><label>Alamat</label><textarea class="form-control" readonly><?= htmlspecialchars($d['alamat'] ?? '') ?></textarea></div>
                    <hr>

                    <div class="row action-buttons">
                        <div class="col-12 col-md-4">
                            <a href="index.php?menu=pengantaran_saya" class="btn btn-secondary btn-block">Kembali ke Daftar</a>
                        </div>
                        <div class="col-12 col-md-4">
                            <a href="https://wa.me/<?= htmlspecialchars($d['no_telp'] ?? '') ?>" target="_blank" class="btn btn-success btn-block">Chat via WhatsApp</a>
                        </div>
                        <div class="col-12 col-md-4">
                            <a href="./data.php?menu=pengantaran&act=selesai&id_pengantaran=<?= htmlspecialchars($d['id_pengantaran']) ?>" class="btn btn-primary btn-block" onclick="return confirm('Anda yakin ingin menyelesaikan pesanan ini?')">Selesaikan Pesanan</a>
                        </div>
                    </div>

                    <div id="gps_status" style="margin-top: 20px; padding: 10px; background-color: #f0f0f0; border-radius: 5px; text-align: center;">
                        Mengaktifkan GPS...
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Script GPS untuk halaman detail (ini sudah benar, tidak diubah)
            const statusDiv = document.getElementById('gps_status');
            function kirimLokasi(posisi) {
                const lat = posisi.coords.latitude;
                const lng = posisi.coords.longitude;
                statusDiv.innerHTML = `<span style="color: green;">GPS Aktif! Lokasi berhasil dikirim.</span>`;
                fetch('../simpan_lokasi.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ lat: lat, lng: lng })
                }).catch(error => {
                    statusDiv.innerHTML = `<span style="color: red;">Gagal mengirim lokasi ke server.</span>`;
                });
            }
            function handleError(error) {
                statusDiv.innerHTML = `<span style="color: red;">Error GPS: ${error.message}</span>`;
            }
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(kirimLokasi, handleError, { enableHighAccuracy: true });
            } else {
                statusDiv.innerHTML = `<span style="color: red;">Browser Anda tidak mendukung Geolocation.</span>`;
            }
        </script>
        <?php
        break;

    default:
        ?>
        <div class="content-header">
            <div class="container-fluid"><h1 class="m-0 text-dark">Pesanan Dalam Perjalanan</h1></div>
        </div>
        
        <div class="col-12" style="padding: 10px 20px;">
            <div id="gps_status" style="padding: 10px; background-color: #e9ecef; border-radius: 5px; text-align: center; border: 1px solid #ced4da;">
                GPS tidak aktif. Pilih pesanan dan mulai pengantaran untuk mengaktifkan.
            </div>
        </div>

        <div class='col-12 card-list-container'>
            <p class="text-muted"><small>Ketuk pesanan sesuai urutan pengantaran yang Anda inginkan.</small></p>
            <?php
            // Query untuk mengambil data pesanan
            $sql = "SELECT p.id_pesanan, p.galon, plg.nama_plng, plg.lat, plg.lng, peng.id_pengantaran, pem.status_pembayaran
                    FROM pengantaran peng
                    JOIN pesanan p ON peng.id_pesanan = p.id_pesanan
                    JOIN data_plng plg ON p.id_plng = plg.id_plng
                    LEFT JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                    WHERE peng.status_pengantaran = 'Dalam Perjalanan' AND peng.id_user = ?
                    ORDER BY p.tgl_pesan ASC";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id_user_login);
            mysqli_stmt_execute($stmt);
            $tampil = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($tampil) > 0) {
                while ($r = mysqli_fetch_assoc($tampil)) {
                    
                    $status_bayar_text = $r['status_pembayaran'] ?? 'Belum Lunas';
                    $status_bayar_class = ($status_bayar_text == 'Lunas') ? 'lunas' : 'belum-lunas';
                    ?>
                    <div class="order-card" 
                         data-lat="<?= htmlspecialchars($r['lat']) ?>" 
                         data-lng="<?= htmlspecialchars($r['lng']) ?>"
                         data-id-pengantaran="<?= htmlspecialchars($r['id_pengantaran']) ?>">
                         
                        <div class="selection-order"></div>
                        <div class="info">
                            <div class="id-pesanan">ID PESANAN: <?= htmlspecialchars($r['id_pesanan']) ?></div>
                            <div class="nama-pelanggan"><?= htmlspecialchars($r['nama_plng']) ?></div>
                            <div class="details">
                                <span><i class="fa-solid fa-bottle-water"></i> <?= htmlspecialchars($r['galon']) ?> Galon</span>
                                <span><span class="status-badge <?= $status_bayar_class ?>"><?= htmlspecialchars($status_bayar_text) ?></span></span>
                            </div>
                        </div>
                        <div class="action">
                            <a href="index.php?menu=pengantaran_saya&act=detail&id_pengantaran=<?= htmlspecialchars($r['id_pengantaran']) ?>" class="btn btn-info">Detail & Selesaikan</a>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='alert alert-info text-center'>Tidak ada data pengantaran yang sedang berjalan.</div>";
            }
            ?>
        </div>
        
        <div class="bulk-action-footer" id="bulk-action-menu">
            <button type="button" id="start-delivery-button" class="btn btn-primary btn-lg btn-block">
                <i class="fa-solid fa-location-crosshairs"></i>
                Mulai Pengantaran & Aktifkan Pelacakan (<span id="selected-count">0</span>)
            </button>
        </div>

        <script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.order-card');
    const bulkMenu = document.getElementById('bulk-action-menu');
    const selectedCountSpan = document.getElementById('selected-count');
    const startButton = document.getElementById('start-delivery-button');
    const statusDiv = document.getElementById('gps_status');
    const detailLinks = document.querySelectorAll('.order-card .action a');

    let selectionOrder = []; // Sekarang akan berisi ID Pengantaran
    let gpsIsActive = false;
    let gpsWatcherId = null; // Untuk menyimpan ID proses watchPosition

    // Fungsi untuk mengaktifkan GPS Tracking (watchPosition)
    // Fungsi ini tidak diubah, sudah benar.
    function startGpsTracking() {
        if (gpsIsActive) return; 

        statusDiv.innerHTML = 'Mengaktifkan GPS...';
        if (navigator.geolocation) {
            gpsWatcherId = navigator.geolocation.watchPosition(
                (posisi) => {
                    const lat = posisi.coords.latitude;
                    const lng = posisi.coords.longitude;
                    statusDiv.innerHTML = `<span style="color: green;">GPS Aktif! Lokasi Anda sedang dikirim.</span>`;
                    fetch('../simpan_lokasi.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ lat: lat, lng: lng })
                    });
                    gpsIsActive = true;
                    // Nonaktifkan tombol setelah GPS berhasil aktif
                    startButton.innerHTML = '<i class="fa-solid fa-check"></i> Pelacakan Aktif';
                    startButton.disabled = true;
                }, 
                (error) => { 
                    statusDiv.innerHTML = `<span style="color: red;">Error GPS: ${error.message}. Pastikan izin lokasi diberikan.</span>`; 
                    gpsIsActive = false;
                },
                { enableHighAccuracy: true }
            );
        } else {
            statusDiv.innerHTML = `<span style="color: red;">Browser tidak mendukung Geolocation.</span>`;
        }
    }
    
    // Fungsi untuk mengupdate UI (menu bawah dan nomor urut)
    function updateUI() {
        const count = selectionOrder.length;
        selectedCountSpan.textContent = count;
        bulkMenu.classList.toggle('show', count > 0);

        cards.forEach(card => {
            const orderNumberDiv = card.querySelector('.selection-order');
            // DIUBAH: Cek berdasarkan data-id-pengantaran
            const id_pengantaran = card.getAttribute('data-id-pengantaran');
            const index = selectionOrder.indexOf(id_pengantaran);
            
            if (index > -1) {
                card.classList.add('selected');
                orderNumberDiv.textContent = index + 1;
            } else {
                card.classList.remove('selected');
                orderNumberDiv.textContent = '';
            }
        });
    }

    // BAGIAN PENTING 1: Membuat kartu bisa diklik untuk dipilih
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('a')) return;
            
            // DIUBAH: Simpan ID, bukan elemen
            const id_pengantaran = this.getAttribute('data-id-pengantaran');
            const index = selectionOrder.indexOf(id_pengantaran);
            
            if (index > -1) {
                selectionOrder.splice(index, 1);
            } else {
                selectionOrder.push(id_pengantaran);
            }
            updateUI();
        });
    });

    // BAGIAN PENTING 2: Tombol "Mulai Pengantaran" (FUNGSI GOOGLE MAPS DIHILANGKAN)
    startButton.addEventListener('click', function() {
        if (selectionOrder.length === 0) {
            alert('Pilih minimal satu pesanan untuk memulai.');
            return;
        }

        if (gpsIsActive) {
            alert('Pelacakan sudah aktif.');
            return;
        }

        // Langsung aktifkan pelacakan (tracking)
        startGpsTracking();

        // Beri feedback ke kurir
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengaktifkan...';
        
        // Sembunyikan menu setelah beberapa detik
        setTimeout(() => {
            bulkMenu.classList.remove('show');
        }, 3000);
    });

    // BAGIAN PENTING 3 (BARU): Intercept link "Detail" untuk menambah selected_ids
    detailLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Hentikan navigasi standar
            e.preventDefault(); 
            
            let currentUrl = this.href;
            let ids_to_pass = '';

            if (selectionOrder.length > 0) {
                // Jika user SUDAH memilih, gunakan daftar pilihan
                ids_to_pass = selectionOrder.join(',');
            } else {
                // Jika user TIDAK memilih apa-apa (langsung klik detail),
                // anggap saja dia 'memilih' 1 item yang dia klik itu.
                const card = this.closest('.order-card');
                const id_pengantaran = card.getAttribute('data-id-pengantaran');
                ids_to_pass = id_pengantaran;
            }

            // Tambahkan parameter selected_ids ke URL
            currentUrl += '&selected_ids=' + ids_to_pass;
            
            // Lanjutkan navigasi ke URL yang sudah dimodifikasi
            window.location.href = currentUrl;
        });
    });

});
</script>
        <?php
        break;
}
?>