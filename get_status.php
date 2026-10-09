<?php
// Header anti-cache (Tetap ada)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

include "config/koneksi.php"; // Pastikan path ini benar

// --- PERUBAHAN: Baca dari $_POST ---
$token = $_POST['token'] ?? ''; // Ambil token dari body POST
$response = ['status' => 'error', 'message' => 'Token tidak valid'];

if (!empty($token) && isset($conn)) {
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
         error_log("Error prepare get_status: " . $conn->error);
    }
     if (isset($conn)) $conn->close();
}

echo json_encode($response);
exit;
?>
