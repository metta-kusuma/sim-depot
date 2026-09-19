<?php
    
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');
// Sertakan file koneksi Anda
include "config/koneksi.php";

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['error' => 'Token tidak valid']);
    exit();
}

$sql = "SELECT kurir_lat, kurir_lng FROM pengantaran WHERE tracking_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$conn->close();

if ($data) {
    echo json_encode(['lat' => $data['kurir_lat'], 'lng' => $data['kurir_lng']]);
} else {
    echo json_encode(['error' => 'Data tidak ditemukan']);
}
?>