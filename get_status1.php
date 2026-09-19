<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Sertakan file koneksi Anda
include "config/koneksi.php"; // Pastikan path ini benar

$token = $_GET['token'] ?? '';
$response = ['status' => 'error', 'message' => 'Token tidak valid']; // Default response

if (!empty($token) && isset($conn)) { // Pastikan $conn ada
    $sql = "SELECT status_pengantaran FROM pengantaran WHERE tracking_token = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        if ($data) {
            $response = ['status' => 'success', 'delivery_status' => $data['status_pengantaran']];
        } else {
            $response['message'] = 'Data tidak ditemukan';
        }
    } else {
         $response['message'] = 'Query error: ' . $conn->error;
         error_log("Error prepare get_status: " . $conn->error); // Log error server
    }
     if (isset($conn)) $conn->close(); // Tutup koneksi
}

echo json_encode($response);
exit;
?>