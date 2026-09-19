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
} else {
     die("Koneksi database gagal.");
}

if (!$data) { die("Pesanan tidak ditemukan atau token salah."); }

$status_pengantaran = $data['status_pengantaran'] ?? '';
$kurirLatAwal = $data['kurir_lat'] ?? null;
$kurirLngAwal = $data['kurir_lng'] ?? null;
$pelangganLat = $data['pelanggan_lat'] ?? null;
$pelangganLng = $data['pelanggan_lng'] ?? null;

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
        /* ... (CSS Anda tidak berubah, saya salin dari file Anda) ... */
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
        #map { flex-grow: 1; width: 100%; height: 100%; }
        .leaflet-routing-container { display: none; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.95); z-index: 5000; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; opacity: 0; visibility: hidden; }
        .selesai-overlay { transition: opacity 0.5s ease, visibility 0s linear 0.5s; }
        .selesai-overlay.show { opacity: 1; visibility: visible; transition: opacity 0.5s ease, visibility 0s linear 0s; }
        .selesai-overlay i { font-size: 60px; color: var(--success-color); margin-bottom: 15px;}
        .selesai-overlay h2 { color: var(--success-color); margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
        .selesai-overlay p { font-size: 1.1em; color: #555; }
        .loading-gps-overlay { opacity: 1; visibility: visible; }
        .loading-gps-overlay i { font-size: 50px; color: var(--primary-color); margin-bottom: 20px; animation: fa-spin 2s infinite linear; }
        .loading-gps-overlay h2 { color: #555; font-size: 1.5em; }
        @keyframes fa-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .wrapper.map-hidden #map { display: none; }
        .wrapper:not(.map-hidden) .loading-gps-overlay { display: none; }
        @media (min-width: 768px) {
            .wrapper { flex-direction: row; }
            .info-panel { flex-basis: 350px; flex-shrink: 0; height: 100vh; overflow-y: auto; border-bottom: none; border-right: 1px solid var(--border-color); box-shadow: -2px 0 8px rgba(0,0,0,0.05); }
            .info-panel h4 { font-size: 1.3em; } .info-panel p { font-size: 1em; }
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
    let kurirLatAwal = <?= json_encode($kurirLatAwal) ?>;
    let kurirLngAwal = <?= json_encode($kurirLngAwal) ?>;
    const pelangganLat = <?= json_encode($pelangganLat) ?>;
    const pelangganLng = <?= json_encode($pelangganLng) ?>;
    const token = <?= json_encode($token) ?>;
    let initialStatus = <?= json_encode($status_pengantaran) ?>;
    const lokasiTujuanValid = <?= json_encode($lokasiTujuanValid) ?>;

    // === Variabel Kontrol Fitur Baru ===
    let hasShownArrivalPopup = false; // Mencegah pop-up muncul berulang kali

    // === Variabel Global ===
    let map;
    let routingControl;
    let kurirMarker; 
    let updateInterval;
    let statusInterval;
    let findGpsInterval;

    // === Elemen DOM ===
    const wrapper = document.getElementById('main-wrapper');
    const mapElement = document.getElementById('map');
    const loadingGpsOverlay = document.getElementById('loading-gps-overlay');
    const selesaiOverlay = document.getElementById('selesai-container');
    const etaElement = document.getElementById('eta');
    const statusDisplay = document.getElementById('status-display');

    // === Ikon Marker ===
    const motorIcon = L.icon({
        iconUrl: 'gambar/motor.png',
        iconSize: [35, 35],
        iconAnchor: [17, 35],
        popupAnchor: [0, -35]
    });
    const rumahIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        shadowSize: [41, 41]
    });

    function formatWaktu(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds < 0) return 'N/A';
        const minutes = Math.round(totalSeconds / 60);
        
        // Fitur Pop-up jika estimasi < 1 menit
        if (totalSeconds > 0 && totalSeconds < 60 && !hasShownArrivalPopup) {
            hasShownArrivalPopup = true; 
            alert("Kurir sudah hampir sampai! Mohon bersiap di lokasi penjemputan.");
        }

        if (minutes < 1) return '< 1 menit';
        return `~${minutes} menit`;
    }

    function initMap(kurirLat, kurirLng) {
        if (!lokasiTujuanValid) return;

        map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        L.control.fullscreen().addTo(map);

        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(kurirLat, kurirLng),
                L.latLng(pelangganLat, pelangganLng)
            ],
            createMarker: function(i, waypoint, n) {
                const icon = (i === 0) ? motorIcon : rumahIcon;
                const marker = L.marker(waypoint.latLng, { icon: icon });
                if (i === 0) kurirMarker = marker; 
                return marker;
            },
            lineOptions: { styles: [{ color: '#3498db', opacity: 0.8, weight: 6 }] },
            show: false,
            addWaypoints: false,
            routeWhileDragging: false,
            fitSelectedRoutes: true 
        }).addTo(map);

        routingControl.on('routesfound', function(e) {
            const routes = e.routes;
            if (routes.length > 0) {
                const summary = routes[0].summary;
                if (etaElement && summary && summary.totalTime) {
                    etaElement.textContent = formatWaktu(summary.totalTime);
                }
            }
        });
    }

    function updatePosisiKurir() {
        if (!routingControl || !token) return; 

        const formData = new FormData();
        formData.append('token', token);

        fetch(`get_lokasi.php?_=${new Date().getTime()}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(posisi => {
            if (posisi && typeof posisi.lat === 'number' && typeof posisi.lng === 'number') {
                const newLatLng = L.latLng(posisi.lat, posisi.lng);
                routingControl.setWaypoints([
                    newLatLng,
                    L.latLng(pelangganLat, pelangganLng)
                ]);
            }
        })
        .catch(error => console.error('Gagal mengambil lokasi:', error));
    }

    function checkStatus() {
        if (!token) return;
        const formData = new FormData();
        formData.append('token', token);
        fetch(`get_status.php?_=${new Date().getTime()}`, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data && data.status === 'success' && data.delivery_status) {
                    const newStatus = data.delivery_status;
                    if (statusDisplay) statusDisplay.textContent = newStatus;
                    
                    // Fitur Auto Selesai jika status database berubah jadi Selesai/Terkirim
                    if (newStatus.toLowerCase() === 'selesai' || newStatus.toLowerCase() === 'terkirim') {
                        showSelesaiOverlay();
                        clearIntervals();
                    }
                }
            })
            .catch(error => console.error('Gagal memeriksa status:', error));
    }

    function clearIntervals() {
        if (findGpsInterval) clearInterval(findGpsInterval);
        if (updateInterval) clearInterval(updateInterval);
        if (statusInterval) clearInterval(statusInterval);
    }

    function showSelesaiOverlay() {
        if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'none';
        if (wrapper) wrapper.style.display = 'none';
        if (selesaiOverlay) selesaiOverlay.classList.add('show');
    }

    function showMap() {
        if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'none';
        if (mapElement) mapElement.style.visibility = 'visible';
    }

    function hideMap() {
        if (loadingGpsOverlay) loadingGpsOverlay.style.display = 'flex';
        if (mapElement) mapElement.style.visibility = 'hidden';
    }

    function checkInitialLocation() {
        if (!token) return;
        const formData = new FormData();
        formData.append('token', token);
        fetch(`get_lokasi.php?_=${new Date().getTime()}`, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(posisi => {
            const latValid = (posisi && posisi.lat !== null && parseFloat(posisi.lat) !== 0);
            if (latValid) {
                clearInterval(findGpsInterval);
                showMap();
                initMap(parseFloat(posisi.lat), parseFloat(posisi.lng));
                updateInterval = setInterval(updatePosisiKurir, 15000);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (initialStatus === 'Selesai' || initialStatus === 'Terkirim') {
            showSelesaiOverlay();
        } 
        else if (!lokasiTujuanValid) {
            hideMap();
        }
        else {
            hideMap();
            statusInterval = setInterval(checkStatus, 10000); // Cek status tiap 10 detik
            checkStatus(); 
            if (kurirLatAwal && kurirLngAwal && parseFloat(kurirLatAwal) !== 0) {
                showMap();
                initMap(kurirLatAwal, kurirLngAwal);
                updateInterval = setInterval(updatePosisiKurir, 15000);
            } else {
                findGpsInterval = setInterval(checkInitialLocation, 10000);
                checkInitialLocation();
            }
        }
    });
</script>

</body>
</html>