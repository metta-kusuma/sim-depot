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
        WHERE peng.tracking_token = ?";

$data = null;
if (isset($conn)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
    } else {
         error_log("Error prepare lacak.php: " . $conn->error);
    }
    // Jangan tutup koneksi di sini
} else {
     die("Koneksi database gagal.");
}

if (!$data) { die("Pesanan tidak ditemukan atau token salah."); }

$status_pengantaran = $data['status_pengantaran'] ?? '';
// Ambil koordinat awal
// PENTING: Biarkan $kurirLatAwal dan $kurirLngAwal apa adanya (bisa null)
$kurirLatAwal = $data['kurir_lat'] ?? null;
$kurirLngAwal = $data['kurir_lng'] ?? null;
$pelangganLat = $data['pelanggan_lat'] ?? null;
$pelangganLng = $data['pelanggan_lng'] ?? null;

// Tentukan apakah LOKASI TUJUAN valid (Lokasi kurir dicek JS)
$lokasiTujuanValid = ($pelangganLat !== null && $pelangganLng !== null);

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
        .info-panel .address-info { padding-left: 28px; font-size: 0.9em; color: #666; }
        .info-panel hr { border: 0; border-top: 1px solid var(--border-color); margin: 15px 0; }
        
        /* Map container (awalnya disembunyikan oleh JS jika GPS belum ada) */
        #map { flex-grow: 1; width: 100%; height: 100%; }
        .leaflet-routing-container { display: none; }

        /* --- Overlay (Selesai & Loading GPS) --- */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            z-index: 5000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column; text-align: center;
            opacity: 0; visibility: hidden;
        }
        /* Style untuk 'Selesai' */
        .selesai-overlay {
            transition: opacity 0.5s ease, visibility 0s linear 0.5s;
        }
        .selesai-overlay.show {
            opacity: 1; visibility: visible; transition: opacity 0.5s ease, visibility 0s linear 0s;
        }
        .selesai-overlay i { font-size: 60px; color: var(--success-color); margin-bottom: 15px;}
        .selesai-overlay h2 { color: var(--success-color); margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
        .selesai-overlay p { font-size: 1.1em; color: #555; }

        /* BARU: Style untuk 'Loading GPS' */
        .loading-gps-overlay {
            opacity: 1; /* Selalu terlihat saat aktif */
            visibility: visible;
        }
        .loading-gps-overlay i {
            font-size: 50px;
            color: var(--primary-color);
            margin-bottom: 20px;
            /* Tambahkan animasi berputar */
            animation: fa-spin 2s infinite linear;
        }
        .loading-gps-overlay h2 { color: #555; font-size: 1.5em; }
        
        @keyframes fa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Sembunyikan map jika .map-hidden ada di wrapper */
        .wrapper.map-hidden #map {
             display: none;
        }
        
        /* Tampilkan loading GPS jika .map-hidden ada di wrapper */
        .wrapper:not(.map-hidden) .loading-gps-overlay {
            display: none; /* Sembunyikan loading jika map tampil */
        }
        /* --- Akhir Overlay --- */


        /* Responsive Layout */
        @media (min-width: 768px) {
            .wrapper { flex-direction: row; }
            .info-panel { flex-basis: 350px; flex-shrink: 0; height: 100vh; overflow-y: auto; border-bottom: none; border-right: 1px solid var(--border-color); box-shadow: -2px 0 8px rgba(0,0,0,0.05); }
            .info-panel h4 { font-size: 1.3em; }
            .info-panel p { font-size: 1em; }
            .selesai-overlay i { font-size: 80px; } .selesai-overlay h2 { font-size: 2.2em; }
            .loading-gps-overlay i { font-size: 60px; } .loading-gps-overlay h2 { font-size: 1.8em; }
        }
    </style>
</head>
<body>

<div class="overlay selesai-overlay" id="selesai-container">
    <i class="fa-solid fa-circle-check"></i>
    <h2>Pesanan Sudah Diantar!</h2>
    <p>Terima kasih telah menggunakan layanan kami.</p>
</div>

<div class="overlay loading-gps-overlay" id="loading-gps-overlay">
    <i class="fa-solid fa-satellite-dish"></i>
    <h2>Menunggu sinyal GPS kurir...</h2>
    <p>Peta akan muncul otomatis saat kurir online.</p>
</div>


<div class="wrapper" id="main-wrapper">
    <div class="info-panel">
        <h4><i class="fa-solid fa-box-open"></i> Lacak Pesanan #<?= htmlspecialchars($data['id_pesanan']) ?></h4>
        <p><i class="fa-solid fa-user"></i><strong>Pelanggan:</strong> <?= htmlspecialchars($data['nama_plng'] ?? '') ?></p>
        <p><i class="fa-solid fa-truck"></i><strong>Status:</strong> <span id="status-display"><?= htmlspecialchars($status_pengantaran) ?></span></p>
        <p><i class="fa-solid fa-id-card"></i><strong>Kurir:</strong> <?= htmlspecialchars($data['nama_pegawai'] ?? 'Belum Ditugaskan') ?></p>
        <p><i class="fa-solid fa-clock"></i><strong>Estimasi Tiba:</strong> <span id="eta"><?= $lokasiTujuanValid ? 'Menunggu GPS...' : 'Lokasi Tujuan Tidak Valid' ?></span></p>
        <hr>
        <p><i class="fa-solid fa-map-marker-alt"></i><strong>Alamat Tujuan:</strong></p>
        <div class="address-info"><?= nl2br(htmlspecialchars($data['alamat'] ?? '')) ?></div>
    </div>
    <div id="map"></div>
</div>

<script>
    // === Data Awal dari PHP ===
    // $kurirLatAwal dan $kurirLngAwal bisa jadi NULL
    let kurirLatAwal = <?= json_encode($kurirLatAwal) ?>;
    let kurirLngAwal = <?= json_encode($kurirLngAwal) ?>;
    const pelangganLat = <?= json_encode($pelangganLat) ?>;
    const pelangganLng = <?= json_encode($pelangganLng) ?>;
    const token = <?= json_encode($token) ?>;
    let initialStatus = <?= json_encode($status_pengantaran) ?>;
    const lokasiTujuanValid = <?= json_encode($lokasiTujuanValid) ?>;

    // === Variabel Global ===
    let map;
    let routingControl;
    let kurirMarker;
    let updateInterval; // Interval update lokasi
    let statusInterval; // Interval cek status
    let findGpsInterval; // Interval cari GPS awal

    // === Elemen DOM ===
    const wrapper = document.getElementById('main-wrapper');
    const mapElement = document.getElementById('map');
    const loadingGpsOverlay = document.getElementById('loading-gps-overlay');
    const selesaiOverlay = document.getElementById('selesai-container');
    const etaElement = document.getElementById('eta');
    const statusDisplay = document.getElementById('status-display');

    // === Ikon Marker ===
    const motorIcon = L.icon({ iconUrl: 'gambar/motor.png', iconSize: [35, 35], iconAnchor: [17, 35] });
    const rumahIcon = L.icon({ iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png', iconSize: [25, 41], iconAnchor: [12, 41], shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png', shadowSize: [41, 41]});

    // === Fungsi Format Waktu ===
    function formatWaktu(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds < 0) return 'N/A';
        const minutes = Math.round(totalSeconds / 60);
        if (minutes < 1) return '< 1 menit';
        return `~${minutes} menit`;
    }

     // === Fungsi Inisialisasi Peta (DIPANGGIL SAAT GPS DITEMUKAN) ===
     function initMap(kurirLat, kurirLng) {
         if (!lokasiTujuanValid) return; // Jangan inisialisasi jika tujuan tidak ada

         map = L.map('map');
         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
         }).addTo(map);
         L.control.fullscreen().addTo(map);

         routingControl = L.Routing.control({
             waypoints: [
                 L.latLng(kurirLat, kurirLng), // Titik Awal Kurir (dari GPS pertama)
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
             show: false, addWaypoints: false, routeWhileDragging: false,
             fitSelectedRoutes: true
         }).addTo(map);

         // Tangkap ETA awal saat rute ditemukan
         routingControl.on('routesfound', function(e) {
             const routes = e.routes;
             if (routes.length > 0) {
                 const summary = routes[0].summary;
                 if (etaElement && summary && summary.totalTime) {
                     etaElement.textContent = formatWaktu(summary.totalTime);
                 } else if (etaElement) {
                      etaElement.textContent = 'Gagal menghitung';
                 }
             }
         });
     }

    // === Fungsi Update Posisi Kurir (Hanya Marker) ===
    function updatePosisiKurir() {
         if (!kurirMarker || !token) return;

        fetch(`get_lokasi.php?token=${token}`, {
            method: 'POST', // <-- Ubah ke POST
            cache: 'no-cache' // <-- Tambahkan ini
        })
            .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok'))
            .then(posisi => {
                if (posisi && typeof posisi.lat === 'number' && typeof posisi.lng === 'number') {
                    const newLatLng = L.latLng(posisi.lat, posisi.lng);
                    kurirMarker.setLatLng(newLatLng); // Pindahkan marker
                } else {
                     console.warn('Data lokasi kurir tidak valid:', posisi);
                }
            })
            .catch(error => console.error('Gagal mengambil lokasi kurir:', error));
    }

    // === Fungsi Cek Status Pengantaran ===
    function checkStatus() {
         if (!token) return;

         fetch(`get_status.php?token=${token}`, {
            method: 'POST', // <-- Ubah ke POST
            cache: 'no-cache' // <-- Tambahkan ini
        })
             .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok'))
             .then(data => {
                 if (data && data.status === 'success' && data.delivery_status) {
                     const newStatus = data.delivery_status;
                     if (statusDisplay) statusDisplay.textContent = newStatus;

                     if (newStatus === 'Selesai' || newStatus === 'Terkirim') {
                         console.log("Status pengantaran selesai.");
                         showSelesaiOverlay();
                         // Hentikan semua interval
                         if (findGpsInterval) clearInterval(findGpsInterval);
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
         if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'none'; // Sembunyikan GPS overlay
         if (wrapper) wrapper.style.display = 'none'; // Sembunyikan map & info panel
         if (selesaiOverlay) selesaiOverlay.classList.add('show'); // Tampilkan overlay selesai
     }

     // === Fungsi Sembunyikan Overlay GPS & Tampilkan Peta ===
     function showMap() {
         if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'none';
         if (mapElement) mapElement.style.visibility = 'visible';
     }

     // === Fungsi Sembunyikan Peta & Tampilkan Overlay GPS ===
     function hideMap() {
         if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'flex'; // Tampilkan overlay GPS
         if (mapElement) mapElement.style.visibility = 'hidden'; // Sembunyikan peta
     }

     // === Fungsi untuk mencari lokasi awal kurir ===
    function checkInitialLocation() {
         if (!token) return;

         console.log("Mencari sinyal GPS kurir...");
         fetch(`get_lokasi.php?token=${token}`, {
            method: 'POST', // <-- Ubah ke POST
            cache: 'no-cache' // <-- Tambahkan ini
        })
            .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok'))
            .then(posisi => {
                 // --- PERBAIKAN PENGECEKAN ---
                 // Cek apakah 'lat' dan 'lng' ada, BUKAN null, dan merupakan angka (atau string angka)
                 const latValid = (posisi && posisi.lat !== null && !isNaN(parseFloat(posisi.lat)));
                 const lngValid = (posisi && posisi.lng !== null && !isNaN(parseFloat(posisi.lng)));
                 // Kita anggap 0,0 juga tidak valid untuk lokasi awal
                 const isNotZero = (latValid && lngValid && (parseFloat(posisi.lat) !== 0 || parseFloat(posisi.lng) !== 0));

                 if (latValid && lngValid && isNotZero) {
                 // --- AKHIR PERBAIKAN ---

                    console.log("Sinyal GPS ditemukan!", posisi);
                    if (findGpsInterval) clearInterval(findGpsInterval); // Hentikan interval pencarian GPS ini

                    showMap(); // Sembunyikan overlay GPS dan tampilkan peta
                    
                    // Inisialisasi peta dengan lokasi yang baru ditemukan
                    initMap(parseFloat(posisi.lat), parseFloat(posisi.lng)); // Pastikan kirim sebagai angka
                    
                    // Mulai interval untuk update posisi marker
                    updateInterval = setInterval(updatePosisiKurir, 15000);

                } else {
                     // Jika masih null atau 0,0
                     console.log("Sinyal GPS belum ada (masih null atau 0,0). Mencoba lagi...");
                     if (etaElement) etaElement.textContent = 'Menunggu GPS Kurir...';
                }
            })
            .catch(error => {
                console.error('Gagal mengambil lokasi awal kurir:', error);
                 if (etaElement) etaElement.textContent = 'Gagal Cek GPS';
            });
     }


    // === INISIALISASI SAAT HALAMAN DIMUAT ===
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Cek status awal dulu
        if (initialStatus === 'Selesai' || initialStatus === 'Terkirim') {
            showSelesaiOverlay();
        } 
        // 2. Cek apakah lokasi tujuan valid
        else if (!lokasiTujuanValid) {
             hideMap(); // Sembunyikan peta
             if (etaElement) etaElement.textContent = 'Lokasi Tujuan Tdk Valid';
             // Ubah teks di overlay loading
             if (loadingGpsOverlay) {
                 loadingGpsOverlay.querySelector('h2').textContent = 'Error';
                 loadingGpsOverlay.querySelector('p').textContent = 'Alamat pelanggan tidak memiliki koordinat (Lat/Lng).';
                 loadingGpsOverlay.querySelector('i').className = 'fa-solid fa-map-location-dot'; // Ganti ikon
             }
        }
        // 3. Jika belum selesai DAN tujuan valid, mulai cek status & GPS
        else {
            // Sembunyikan peta dan tampilkan loading GPS overlay
            hideMap();

            // Mulai cek status (auto-refresh jika selesai)
            statusInterval = setInterval(checkStatus, 30000); // Cek status tiap 30 detik
            checkStatus(); // Cek status sekali di awal

            // Mulai cek lokasi awal kurir
            // Jika lokasi awal dari PHP sudah ada, langsung gunakan
            if (kurirLatAwal && kurirLngAwal) {
                 console.log("Lokasi awal kurir sudah ada dari PHP.");
                 showMap();
                 initMap(kurirLatAwal, kurirLngAwal);
                 updateInterval = setInterval(updatePosisiKurir, 15000); // Mulai update posisi
            } else {
                // Jika lokasi awal null, cari tiap 10 detik
                 console.log("Lokasi awal kurir NULL, memulai pencarian...");
                 findGpsInterval = setInterval(checkInitialLocation, 10000); // Cek GPS tiap 10 detik
                 checkInitialLocation(); // Cek sekali di awal
            }
        }
    });

</script>

</body>
</html>