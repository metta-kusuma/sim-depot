<?php
// Sertakan file koneksi Anda
include "config/koneksi.php"; // Pastikan path ini benar

$token = $_GET['token'] ?? '';
if (empty($token)) { die("Token tidak valid."); }

// Ambil semua data awal
$sql = "SELECT p.*, plg.nama_plng, plg.alamat, plg.lat AS pelanggan_lat, plg.lng AS pelanggan_lng,
               peng.status_pengantaran, peng.kurir_lat, peng.kurir_lng, peng.nama_pegawai
        FROM pengantaran peng
        JOIN pesanan p ON peng.id_pesanan = p.id_pesanan
        JOIN data_plng plg ON p.id_plng = plg.id_plng
        WHERE peng.tracking_token = ?";a

$data = null; // Inisialisasi
if (isset($conn)) { // Cek koneksi
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
    } else {
         error_log("Error prepare lacak.php: " . $conn->error); // Log error
    }
    // JANGAN TUTUP KONEKSI DI SINI jika get_status.php pakai koneksi baru
    // if (isset($conn)) $conn->close();
} else {
     die("Koneksi database gagal.");
}


if (!$data) { die("Pesanan tidak ditemukan atau token salah."); }

$status_pengantaran = $data['status_pengantaran'] ?? '';
// Ambil koordinat awal
$kurirLatAwal = $data['kurir_lat'] ?? null; // Default null jika tidak ada
$kurirLngAwal = $data['kurir_lng'] ?? null;
$pelangganLat = $data['pelanggan_lat'] ?? null;
$pelangganLng = $data['pelanggan_lng'] ?? null;

// Tentukan apakah lokasi awal valid
$lokasiAwalValid = ($kurirLatAwal !== null && $kurirLngAwal !== null && $pelangganLat !== null && $pelangganLng !== null);

// Tetapkan lokasi default jika lokasi awal tidak valid (misal: tengah peta)
if (!$lokasiAwalValid) {
    // Anda bisa set ke lokasi depot atau pusat kota
    $kurirLatAwal = 0.489891; // Contoh: Pekanbaru
    $kurirLngAwal = 101.438708;
    $pelangganLat = 0.489891; // Set tujuan ke tempat yang sama agar peta tidak error
    $pelangganLng = 101.438708;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Lacak Pesanan #<?= htmlspecialchars($data['id_pesanan']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
    <style>
        :root { --primary-color: #3498db; --success-color: #2ecc71; --light-gray: #f4f7fc; --border-color: #e0e4f1; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--light-gray); }
        .wrapper { display: flex; flex-direction: column; height: 100vh; }
        .info-panel { padding: 15px 20px; background-color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 10; border-bottom: 1px solid var(--border-color); }
        .info-panel h4 { margin-top: 0; margin-bottom: 15px; color: var(--primary-color); font-size: 1.2em; font-weight: 600; display: flex; align-items: center;}
        .info-panel h4 i { margin-right: 10px; }
        .info-panel p { margin: 8px 0; font-size: 0.95em; display: flex; align-items: center; color: #555; }
        .info-panel i.fa-solid { color: var(--primary-color); margin-right: 10px; width: 18px; text-align: center; flex-shrink: 0; }
        .info-panel strong { font-weight: 600; color: #333; margin-right: 5px; }
        .info-panel .address-info { padding-left: 28px; font-size: 0.9em; color: #666; } /* Indentasi alamat */
        .info-panel hr { border: 0; border-top: 1px solid var(--border-color); margin: 15px 0; }
        #map { flex-grow: 1; width: 100%; height: 100%; /* Fix height issue */ } /* Penting: height 100% */
        .leaflet-routing-container { display: none; } /* Sembunyikan panel teks rute */

        /* Overlay Selesai */
        .selesai-overlay {
            position: fixed; /* Fixed agar menutupi seluruh layar */
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 5000; /* Pastikan di atas peta */
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; text-align: center;
            opacity: 0; /* Mulai transparan */
            visibility: hidden; /* Mulai tersembunyi */
            transition: opacity 0.5s ease, visibility 0s linear 0.5s; /* Transisi fade out */
        }
        .selesai-overlay.show {
            opacity: 1;
            visibility: visible;
            transition: opacity 0.5s ease, visibility 0s linear 0s; /* Transisi fade in */
        }
        .selesai-overlay i { font-size: 60px; color: var(--success-color); margin-bottom: 15px;}
        .selesai-overlay h2 { color: var(--success-color); margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
        .selesai-overlay p { font-size: 1.1em; color: #555; }

        /* Responsive Layout */
        @media (min-width: 768px) {
            .wrapper { flex-direction: row; }
            .info-panel {
                flex-basis: 350px; /* Lebar panel info di desktop */
                flex-shrink: 0;
                height: 100vh;
                overflow-y: auto; /* Scroll jika info panjang */
                border-bottom: none;
                border-right: 1px solid var(--border-color); /* Garis pemisah */
                box-shadow: -2px 0 8px rgba(0,0,0,0.05); /* Shadow di sisi kiri */
            }
             .info-panel h4 { font-size: 1.3em; }
             .info-panel p { font-size: 1em; }
             .selesai-overlay i { font-size: 80px; }
             .selesai-overlay h2 { font-size: 2.2em; }
        }
    </style>
</head>
<body>

<div class="selesai-overlay" id="selesai-container">
    <i class="fa-solid fa-circle-check"></i>
    <h2>Pesanan Sudah Diantar!</h2>
    <p>Terima kasih telah menggunakan layanan kami.</p>
</div>

<div class="wrapper">
    <div class="info-panel">
        <h4><i class="fa-solid fa-box-open"></i> Lacak Pesanan #<?= htmlspecialchars($data['id_pesanan']) ?></h4>
        <p><i class="fa-solid fa-user"></i><strong>Pelanggan:</strong> <?= htmlspecialchars($data['nama_plng'] ?? '') ?></p>
        <p><i class="fa-solid fa-truck"></i><strong>Status:</strong> <span id="status-display"><?= htmlspecialchars($status_pengantaran) ?></span></p>
        <p><i class="fa-solid fa-id-card"></i><strong>Kurir:</strong> <?= htmlspecialchars($data['nama_pegawai'] ?? 'Belum Ditugaskan') ?></p>
        <p><i class="fa-solid fa-clock"></i><strong>Estimasi Tiba:</strong> <span id="eta"><?= $lokasiAwalValid ? 'Menghitung...' : 'Lokasi Awal Tidak Valid' ?></span></p>
        <hr>
        <p><i class="fa-solid fa-map-marker-alt"></i><strong>Alamat Tujuan:</strong></p>
        <div class="address-info"><?= nl2br(htmlspecialchars($data['alamat'] ?? '')) ?></div>
    </div>
    <div id="map"></div>
</div>

<script>
    // === Data Awal dari PHP ===
    const kurirLat = <?= json_encode($kurirLatAwal) ?>; // Bisa null
    const kurirLng = <?= json_encode($kurirLngAwal) ?>; // Bisa null
    const pelangganLat = <?= json_encode($pelangganLat) ?>; // Bisa null
    const pelangganLng = <?= json_encode($pelangganLng) ?>;
    const token = <?= json_encode($token) ?>;
    let initialStatus = <?= json_encode($status_pengantaran) ?>; // Status awal

    // === Variabel Global ===
    let map;
    let routingControl;
    let kurirMarker;
    let updateInterval; // Untuk interval update lokasi
    let statusInterval; // Untuk interval cek status

    // === Ikon Marker ===
    const motorIcon = L.icon({ iconUrl: 'gambar/motor.png', iconSize: [35, 35], iconAnchor: [17, 35] }); // Sesuaikan path jika perlu
    const rumahIcon = L.icon({ iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png', // Ikon default Leaflet
                              iconSize: [25, 41], iconAnchor: [12, 41], shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png', shadowSize: [41, 41]});

    // === Fungsi Format Waktu (Detik ke Menit) ===
    function formatWaktu(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds < 0) return 'N/A';
        const minutes = Math.round(totalSeconds / 60);
        if (minutes < 1) return '< 1 menit';
        return `~${minutes} menit`;
    }

     // === Fungsi Inisialisasi Peta ===
     function initMap() {
         map = L.map('map');
         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { // OpenStreetMap standar
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
         }).addTo(map);
         L.control.fullscreen().addTo(map);

         // Tampilkan Peta awal meskipun lokasi belum valid
         const initialCenter = (pelangganLat && pelangganLng) ? [pelangganLat, pelangganLng] : [-0.52, 101.45]; // Default center jika tujuan tdk ada
         map.setView(initialCenter, 13); // Zoom awal

         // Hanya buat rute jika lokasi awal valid
         if (kurirLat && kurirLng && pelangganLat && pelangganLng) {
             routingControl = L.Routing.control({
                 waypoints: [
                     L.latLng(kurirLat, kurirLng), // Titik Awal Kurir
                     L.latLng(pelangganLat, pelangganLng) // Titik Tujuan Pelanggan
                 ],
                 createMarker: function(i, waypoint, n) {
                     const icon = (i === 0) ? motorIcon : rumahIcon;
                     const marker = L.marker(waypoint.latLng, { icon: icon, draggable: false });
                     if (i === 0) {
                         kurirMarker = marker; // Simpan referensi marker kurir
                     }
                     return marker;
                 },
                 lineOptions: { styles: [{ color: '#3498db', opacity: 0.8, weight: 6 }] },
                 show: false, // Sembunyikan instruksi rute
                 addWaypoints: false, // Jangan izinkan tambah waypoint
                 routeWhileDragging: false, // Jangan hitung ulang saat marker (jika bisa) digeser
                 fitSelectedRoutes: true // Auto zoom ke rute
             }).addTo(map);

             // Tangkap ETA awal saat rute ditemukan
             routingControl.on('routesfound', function(e) {
                 const routes = e.routes;
                 if (routes.length > 0) {
                     const summary = routes[0].summary; // Ambil summary rute pertama
                     const etaElement = document.getElementById('eta');
                     if (etaElement && summary && summary.totalTime) {
                         etaElement.textContent = formatWaktu(summary.totalTime);
                     } else if (etaElement) {
                          etaElement.textContent = 'Gagal menghitung';
                     }
                 }
             });
         } else {
              // Tampilkan pesan jika lokasi tidak valid
              const etaElement = document.getElementById('eta');
              if (etaElement) etaElement.textContent = 'Lokasi kurir/pelanggan tidak valid';
              // Tambahkan marker rumah saja jika tujuannya valid
              if (pelangganLat && pelangganLng) {
                   L.marker([pelangganLat, pelangganLng], { icon: rumahIcon }).addTo(map).bindPopup("Lokasi Tujuan");
              }
         }
     }

    // === Fungsi Update Posisi Kurir (Hanya Marker) ===
    function updatePosisiKurir() {
         if (!kurirMarker || !token) return; // Jangan lakukan jika marker belum ada atau token kosong

        fetch(`get_lokasi.php?token=${token}`) // Asumsi get_lokasi.php mengembalikan {lat: ..., lng: ...}
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(posisi => {
                if (posisi && typeof posisi.lat === 'number' && typeof posisi.lng === 'number') {
                    const newLatLng = L.latLng(posisi.lat, posisi.lng);
                    kurirMarker.setLatLng(newLatLng); // Pindahkan marker saja

                    // Opsional: Pusatkan peta ke kurir jika terlalu jauh?
                    // if (!map.getBounds().contains(newLatLng)) {
                    //    map.panTo(newLatLng);
                    // }
                } else {
                     console.warn('Data lokasi kurir tidak valid:', posisi);
                }
            })
            .catch(error => console.error('Gagal mengambil lokasi kurir:', error));
    }

    // === Fungsi Cek Status Pengantaran ===
    function checkStatus() {
         if (!token) return; // Jangan cek jika token kosong

         fetch(`get_status.php?token=${token}`)
             .then(response => {
                 if (!response.ok) throw new Error('Network response was not ok');
                 return response.json();
             })
             .then(data => {
                 if (data && data.status === 'success' && data.delivery_status) {
                     const newStatus = data.delivery_status;
                     const statusDisplay = document.getElementById('status-display');
                     if (statusDisplay) statusDisplay.textContent = newStatus; // Update teks status

                     // Periksa apakah sudah selesai
                     if (newStatus === 'Selesai' || newStatus === 'Terkirim') { // Sesuaikan dengan nilai di DB Anda
                         console.log("Status pengantaran selesai.");
                         showSelesaiOverlay();
                         // Hentikan interval
                         if (updateInterval) clearInterval(updateInterval);
                         if (statusInterval) clearInterval(statusInterval);
                     }
                 } else {
                      console.warn('Data status tidak valid:', data);
                 }
             })
             .catch(error => console.error('Gagal memeriksa status:', error));
    }

     // === Fungsi Tampilkan Overlay Selesai ===
     function showSelesaiOverlay() {
         const overlay = document.getElementById('selesai-container');
         if (overlay) {
             overlay.classList.add('show');
         }
         // Opsional: Sembunyikan wrapper peta dan info
         // const wrapper = document.querySelector('.wrapper');
         // if (wrapper) wrapper.style.display = 'none';
     }

    // === INISIALISASI ===
    document.addEventListener('DOMContentLoaded', function() {
        // Cek status awal sebelum memulai
        if (initialStatus === 'Selesai' || initialStatus === 'Terkirim') {
            showSelesaiOverlay();
        } else {
            initMap(); // Inisialisasi peta

            // Hanya jalankan interval jika lokasi awal valid dan status belum selesai
            if (lokasiAwalValid) {
                // Update posisi kurir setiap 15 detik
                updateInterval = setInterval(updatePosisiKurir, 15000);
                // Cek status pengantaran setiap 30 detik
                statusInterval = setInterval(checkStatus, 30000);

                // Panggil sekali di awal untuk data pertama
                 updatePosisiKurir();
                 checkStatus();
            } else {
                 console.warn("Lokasi awal tidak valid, update posisi/status tidak dimulai.");
                 // Tetap cek status jika hanya lokasi kurir yg tdk valid tapi tujuan ada
                 if (pelangganLat && pelangganLng) {
                      statusInterval = setInterval(checkStatus, 30000);
                      checkStatus(); // Cek status awal
                 }
            }
        }
    });

</script>

</body>
</html>