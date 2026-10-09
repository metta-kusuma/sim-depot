<?php
// Pastikan hanya level 'anggota' yang bisa mengakses halaman ini
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'anggota') {
    die("Akses ditolak. Halaman ini hanya untuk Anggota.");
}

// Ambil ID user yang sedang login dari session
$id_user_login = (int)$_SESSION['id_user'];
$act = isset($_GET['act']) ? $_GET['act'] : 'default';
?>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
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
        $id_pengantaran = $_GET['id_pengantaran'];

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
                            <button class="btn btn-secondary btn-block" onclick="history.back()">Kembali</button>
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
                    <div class="order-card" data-lat="<?= htmlspecialchars($r['lat']) ?>" data-lng="<?= htmlspecialchars($r['lng']) ?>">
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
                <i class="fa-solid fa-route"></i> 
                Mulai Pengantaran & Lihat Rute (<span id="selected-count">0</span>)
            </button>
        </div>

        <script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.order-card');
    const bulkMenu = document.getElementById('bulk-action-menu');
    const selectedCountSpan = document.getElementById('selected-count');
    const startButton = document.getElementById('start-delivery-button');
    const statusDiv = document.getElementById('gps_status');

    let selectionOrder = [];
    let gpsIsActive = false;

    // Fungsi untuk mengaktifkan GPS Tracking
    function startGpsTracking() {
        if (gpsIsActive) return; 

        statusDiv.innerHTML = 'Mengaktifkan GPS...';
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
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
                }, 
                (error) => { statusDiv.innerHTML = `<span style="color: red;">Error GPS: ${error.message}</span>`; },
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
            const index = selectionOrder.indexOf(card);
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
    // Kode ini TIDAK akan berjalan jika Anda mengklik tombol link "Detail & Selesaikan"
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Jika yang diklik adalah link (<a>), abaikan fungsi pilih ini
            if (e.target.closest('a')) return;
            
            const index = selectionOrder.indexOf(this);
            if (index > -1) {
                selectionOrder.splice(index, 1);
            } else {
                selectionOrder.push(this);
            }
            updateUI();
        });
    });

    // BAGIAN PENTING 2: Tombol "Mulai Pengantaran" dengan URL Google Maps yang sudah diperbaiki
    startButton.addEventListener('click', function() {
        if (selectionOrder.length === 0) {
            alert('Pilih minimal satu pesanan untuk memulai.');
            return;
        }

        statusDiv.innerHTML = 'Mencari lokasi Anda untuk rute...';

        navigator.geolocation.getCurrentPosition(function(posisi) {
            const kurirLat = posisi.coords.latitude;
            const kurirLng = posisi.coords.longitude;
            statusDiv.innerHTML = '<span style="color: green;">Lokasi ditemukan! Membuka Google Maps...</span>';

            startGpsTracking();

            const waypoints = selectionOrder.map(card => {
                const lat = card.getAttribute('data-lat');
                const lng = card.getAttribute('data-lng');
                if (lat && lng && lat.trim() !== '') return `${lat},${lng}`;
                return null;
            }).filter(Boolean);
            
            if (waypoints.length > 0) {
                const origin = `${kurirLat},${kurirLng}`;
                const destination = waypoints.pop();
                
                let googleMapsUrl = `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}`;
                
                if (waypoints.length > 0) {
                    googleMapsUrl += `&waypoints=${waypoints.join('|')}`;
                }
                
                window.open(googleMapsUrl, '_blank');
            } else {
                alert('Tidak ada koordinat yang valid pada pesanan yang dipilih.');
                statusDiv.innerHTML = 'Gagal membuat rute. Koordinat tidak valid.';
            }

        }, function(error) {
            statusDiv.innerHTML = `<span style="color: red;">Gagal mendapatkan lokasi GPS Anda. Pastikan GPS aktif dan izin diberikan.</span>`;
            alert('Gagal mendapatkan lokasi GPS Anda. Pastikan layanan lokasi di HP Anda aktif dan Anda sudah memberikan izin ke browser.');
        }, { 
            enableHighAccuracy: true 
        });
    });
});
</script>
        <?php
        break;
}
?>