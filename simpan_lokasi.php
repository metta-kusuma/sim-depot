<?php
session_start();
// Sertakan file koneksi Anda
include "config/koneksi.php";

// Hanya proses jika yang mengirim adalah anggota yang sedang login
if (isset($_SESSION['level']) && $_SESSION['level'] == 'anggota') {
    $id_user = $_SESSION['id_user'];
    
    // Ambil data JSON yang dikirim dari browser kurir
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['lat']) && isset($data['lng'])) {
        $lat = $data['lat'];
        $lng = $data['lng'];
        
        // Update lokasi di baris pengantaran yang sedang dijalankan oleh kurir ini
        // Ini memastikan hanya 1 pesanan aktif per kurir yang diupdate
        $sql = "UPDATE pengantaran SET kurir_lat=?, kurir_lng=? WHERE id_user=? AND status_pengantaran='Dalam Perjalanan'";
        
        $stmt = $conn->prepare($sql);
        // 'ddi' -> double, double, integer
        $stmt->bind_param("ddi", $lat, $lng, $id_user);
        $stmt->execute();
        $conn->close();

        // Kirim respons OK (opsional)
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    }
} else {
    // Jika bukan anggota, kirim error
    http_response_code(403);
    echo json_encode(['status' => 'forbidden']);
}
?>