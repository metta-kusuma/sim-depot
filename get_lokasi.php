<?php
// Header anti-cache (Tetap ada)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Sertakan file koneksi Anda
include "config/koneksi.php"; // Pastikan path ini benar

// --- PERUBAHAN: Baca dari $_POST ---
$token = $_POST['token'] ?? ''; // Ambil token dari body POST

if (empty($token) || !isset($conn)) {
    echo json_encode(['error' => 'Token tidak valid atau koneksi DB gagal']);
    exit();
}

$sql = "SELECT kurir_lat, kurir_lng FROM pengantaran WHERE tracking_token = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data) {
        echo json_encode([
            'lat' => $data['kurir_lat'] ? (float)$data['kurir_lat'] : null,
            'lng' => $data['kurir_lng'] ? (float)$data['kurir_lng'] : null
        ]);
    } else {
        echo json_encode(['error' => 'Data tidak ditemukan']);
    }
} else {
    error_log("Error prepare get_lokasi: " . $conn->error);
    echo json_encode(['error' => 'Query server error']);
}

if (isset($conn)) $conn->close();
exit;
?>
