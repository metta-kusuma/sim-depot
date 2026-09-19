<?php
// Set Timezone (WIB - Western Indonesian Time)
date_default_timezone_set('Asia/Jakarta');

// --- PENGAMBILAN KONFIGURASI DARI DATABASE ---
// Asumsi: File koneksi database di-include di sini dan menyediakan variabel koneksi $conn
// Kita akan menggunakan $conn untuk membaca konfigurasi dan untuk logic pesanan.
include 'koneksi.php'; // SESUAIKAN PATH INI

$verifyToken = 'ERROR';
$accessToken = '';
$phoneNumberId = '888054157724456';

$config_wa = [];
if (isset($conn) && $conn) {
    // Ambil konfigurasi WhatsApp dari tabel settings (Key-Value)
    $sql_config = "SELECT name, value FROM settings WHERE name IN ('wa_verify_token', 'wa_access_token', 'wa_phone_number_id')";
    $result_config = mysqli_query($conn, $sql_config);

    if ($result_config) {
        while ($row = mysqli_fetch_assoc($result_config)) {
            $config_wa[$row['name']] = $row['value'];
        }
        $verifyToken = $config_wa['wa_verify_token'] ?? 'ERROR';
        $accessToken = $config_wa['wa_access_token'] ?? '';
        $phoneNumberId = $config_wa['wa_phone_number_id'] ?? '';
    } else {
        error_log("DB Error fetching config in webhook.php: " . mysqli_error($conn));
    }
} else {
    error_log("FATAL: Koneksi database global ($conn) tidak tersedia di webhook.php!");
}
// --- END PENGAMBILAN KONFIGURASI DB ---


// --- Fungsi untuk mencatat log ---
function writeLog($filename, $message)
{
    // Tambahkan pengecekan ukuran log file di produksi
    file_put_contents($filename, date('Y-m-d H-i-s') . " - " . $message . "\n", FILE_APPEND);
}


// --- Fungsi untuk mengirim pesan balasan WhatsApp (DIKOREKSI TOTAL UNTUK DEBUGGING) ---
function sendWhatsAppMessage($to_number, $message_text, $access_token, $phone_number_id)
{
    // 1. Validasi cURL
    if (!function_exists('curl_init')) {
        return "cURL library tidak aktif di server PHP.";
    }

    // Perbaikan #1: Pastikan nomor telepon berformat E.164 (+62...)
    // Asumsi input dari form adalah 628xxxx.
    if (substr($to_number, 0, 2) === '62' && substr($to_number, 0, 1) !== '+') {
        $to_number = '+' . $to_number;
    }

    // Perbaikan #2: Bersihkan pesan dari karakter non-UTF8
    $message_text = mb_convert_encoding($message_text, 'UTF-8', 'UTF-8');

    $url = "https://graph.facebook.com/v19.0/{$phone_number_id}/messages";

    // 2. Susun Payload
    $body = [
        "messaging_product" => "whatsapp",
        "to" => $to_number, // Nomor yang sudah dikoreksi
        "type" => "text",
        "text" => ["body" => $message_text]
    ];

    $json_payload = json_encode($body);
    if ($json_payload === false) {
        // Ini akan tertangkap di log dan dikembalikan sebagai error spesifik
        return "Gagal encode JSON: " . json_last_error_msg();
    }

    // 3. Set Header
    $headers = [
        "Authorization: Bearer {$access_token}",
        "Content-Type: application/json"
    ];

    // 4. Konfigurasi cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    writeLog('whatsapp_api_response.txt', "--- PAYLOAD DIKIRIM: {$json_payload}"); // Log payload yang dikirim

    // 5. Eksekusi
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    if ($curl_error) {
        writeLog('whatsapp_api_error.txt', "cURL Error: " . $curl_error);
        curl_close($ch);
        return "cURL Error: " . $curl_error;
    }

    curl_close($ch);
    writeLog('whatsapp_api_response.txt', "WhatsApp API Response ({$http_code}): " . $response);

    // 6. Analisis Respon
    if ($http_code == 200 || $http_code == 201) {
        $response_data = json_decode($response, true);
        if (isset($response_data['messages'][0]['id'])) {
            return true; // Sukses
        }
    }

    // Jika non-200/201, coba ekstrak error dari body respons Meta
    $response_data = json_decode($response, true);

    if (isset($response_data['error']['message'])) {
        // Mengembalikan pesan error dari Meta (e.g., "Invalid Access Token", "Bad Request: Parameter issue")
        $error_msg = $response_data['error']['message'];
    } elseif (json_last_error() !== JSON_ERROR_NONE) {
        // Error decoding respons Meta
        $error_msg = 'Respon HTTP ' . $http_code . ', tetapi body tidak dapat di-decode JSON. Body: ' . $response;
    } else {
        // Error tidak terdeteksi (sangat jarang)
        $error_msg = 'Respon tidak sukses (' . $http_code . ') tanpa pesan error yang jelas.';
    }

    return $error_msg; // Mengembalikan string error API/HTTP
}


// --- Lanjutan logika utama Webhook ---
if (!defined('ADMIN_CONTEXT')) {

    // --- Penanganan Verifikasi Webhook (GET Request) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';

        if ($mode === 'subscribe' && $token === $verifyToken) {
            http_response_code(200);
            echo $challenge;
            exit();
        } else {
            http_response_code(403);
            die('Forbidden');
        }
    }

    // --- Penanganan Pesan Masuk (POST Request) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        writeLog('whatsapp_webhook_log.txt', "Menerima POST request.");
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            writeLog('whatsapp_webhook_log.txt', "ERROR: Gagal decode JSON payload: " . json_last_error_msg());
            http_response_code(400);
            exit('Bad Request: Invalid JSON');
        }
        writeLog('whatsapp_webhook_log.txt', "POST Payload Diterima: " . $input);

        // --- Sisanya (Logika Pemrosesan Pesan) ---
        if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
            $contact = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? null;

            if ($message['type'] === 'text') {
                $no_telp_pengirim = $message['from'];
                $profile_name = $contact['profile']['name'] ?? 'Pelanggan';
                $message_body = trim(strtolower($message['text']['body']));
                writeLog('whatsapp_webhook_log.txt', "Pesan Teks dari {$profile_name} ({$no_telp_pengirim}): {$message_body}");

                $response_message_on_error = "Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi nanti atau hubungi admin.";
                $conn_webhook = $conn; // Gunakan koneksi database $conn untuk Webhook

                $galon = null;
                // Hanya deteksi '... galon' (ID 4)
                if (preg_match('/(\d+)\s*(galon|gallon|gl)/i', $message_body, $matches)) {
                    $galon = (int)$matches[1];
                }

                if ($galon !== null && $galon > 0) {
                    // --- Mulai Logika Pesanan Galon (ID 4) ---
                    try {
                        // Gunakan koneksi yang sudah ada dari scope global (dari 'koneksi.php')
                        if (!$conn_webhook || !$conn_webhook->ping()) throw new Exception("Koneksi DB Gagal.");
                        $conn_webhook->set_charset("utf8mb4");

                        // 1. Cari pelanggan
                        $sql_select = "SELECT id_plng, nama_plng FROM data_plng WHERE no_telp = ?";
                        $stmt_select = $conn_webhook->prepare($sql_select);
                        if (!$stmt_select) throw new Exception("Prepare select customer failed: " . $conn_webhook->error);
                        $stmt_select->bind_param("s", $no_telp_pengirim);
                        $stmt_select->execute();
                        $result = $stmt_select->get_result();
                        $customer_data = $result->fetch_assoc();
                        $stmt_select->close();

                        if ($customer_data) {
                            // --- Pelanggan Ditemukan ---
                            $id_plng = $customer_data['id_plng'];
                            $nama_plng_db = $customer_data['nama_plng'];

                            // 2. Cek Stok Produk ID 4 (Galon Isi Ulang)
                            $id_produk_pesan = 4; // Hardcode ID Produk Galon Isi Ulang
                            $nama_produk_pesan = 'Galon Berisi'; // Default
                            $harga_produk = 5000; // Default

                            $sql_cek_stok4 = "SELECT stock, nama_produk, harga FROM produk WHERE id_produk = ?"; // Diubah: id -> id_produk
                            $stmt_stok4 = $conn_webhook->prepare($sql_cek_stok4);
                            if (!$stmt_stok4) throw new Exception("Query cek stok galon gagal: " . $conn_webhook->error);
                            $stmt_stok4->bind_param("i", $id_produk_pesan);
                            $stmt_stok4->execute();
                            $result_stok4 = $stmt_stok4->get_result();
                            $data_stok4 = $result_stok4->fetch_assoc();
                            $stmt_stok4->close();

                            if (!$data_stok4) throw new Exception("Produk ID 4 (Galon Isi Ulang) tidak ditemukan di database.");

                            $stok_sekarang4 = $data_stok4['stock'] ?? 0;
                            $nama_produk_pesan = $data_stok4['nama_produk'] ?? $nama_produk_pesan;
                            $harga_produk = $data_stok4['harga'] ?? $harga_produk;

                            if ($galon > $stok_sekarang4) {
                                $response_message = "Hai {$nama_plng_db}, mohon maaf, stok {$nama_produk_pesan} kami saat ini ({$stok_sekarang4}) tidak mencukupi untuk pesanan Anda ({$galon}).";
                                sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                                throw new Exception("Stok galon (ID 4) tidak cukup.");
                            }

                            // 3. Cek Stok Produk ID 5 (Tutup Galon)
                            $sql_cek_stok5 = "SELECT stock FROM produk WHERE id_produk = 5"; // Diubah: id -> id_produk
                            $result_stok5 = $conn_webhook->query($sql_cek_stok5);
                            if (!$result_stok5) throw new Exception("Query cek stok tutup gagal: " . $conn_webhook->error);
                            $data_stok5 = $result_stok5->fetch_assoc();
                            $stok_sekarang5 = $data_stok5['stock'] ?? 0;

                            if ($galon > $stok_sekarang5) {
                                $response_message = "Hai {$nama_plng_db}, mohon maaf, stok tutup galon kami saat ini ({$stok_sekarang5}) tidak mencukupi untuk pesanan Anda ({$galon}).";
                                sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                                throw new Exception("Stok tutup galon (ID 5) tidak cukup.");
                            }

                            // 4. Cek Pesanan Aktif
                            $sql_cek_aktif = "SELECT p.id_pesanan FROM pesanan p JOIN pengantaran peng ON p.id_pesanan = peng.id_pesanan WHERE p.no_telp = ? AND peng.status_pengantaran IN ('Belum Diproses','Diproses', 'Dalam Perjalanan')";
                            $stmt_cek = $conn_webhook->prepare($sql_cek_aktif);
                            if (!$stmt_cek) throw new Exception("Prepare cek aktif failed: " . $conn_webhook->error);
                            $stmt_cek->bind_param("s", $no_telp_pengirim);
                            $stmt_cek->execute();
                            $result_cek = $stmt_cek->get_result();

                            if ($result_cek->num_rows > 0) {
                                $pesanan_aktif = $result_cek->fetch_assoc();
                                $id_pesanan_aktif = $pesanan_aktif['id_pesanan'];
                                $response_message = "Hai {$nama_plng_db}, Anda tidak bisa memesan lagi karena masih ada pesanan aktif (ID: #{$id_pesanan_aktif}) yang sedang diproses. Mohon tunggu.";
                                sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                                $stmt_cek->close();
                                throw new Exception("Pesanan aktif ditemukan, proses dibatalkan.");
                            }
                            $stmt_cek->close();

                            // --- Lanjutkan Proses Pemesanan (Transaksi) ---
                            $conn_webhook->begin_transaction();

                            $tgl_sekarang_wib = date('Y-m-d H:i:s');

                            // 5. Insert pesanan
                            $sql_pesanan = "INSERT INTO pesanan (id_plng, nama_plng, no_telp, id_produk, nama_produk, galon, tgl_pesan) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt_pesanan = $conn_webhook->prepare($sql_pesanan);
                            if (!$stmt_pesanan) throw new Exception("Prepare insert pesanan gagal: " . $conn_webhook->error);
                            $stmt_pesanan->bind_param("sssisss", $id_plng, $nama_plng_db, $no_telp_pengirim, $id_produk_pesan, $nama_produk_pesan, $galon, $tgl_sekarang_wib);
                            if (!$stmt_pesanan->execute()) throw new Exception("Execute insert pesanan gagal: " . $stmt_pesanan->error);
                            $id_pesanan_baru = $conn_webhook->insert_id;
                            $stmt_pesanan->close();

                            // 6. Kurangi stok ID 4 (Galon Isi Ulang)
                            $sql_update_stok4 = "UPDATE produk SET stock = stock - ? WHERE id_produk = 4"; // Diubah: id -> id_produk
                            $stmt_stok4 = $conn_webhook->prepare($sql_update_stok4);
                            if (!$stmt_stok4) throw new Exception("Prepare update stok 4 gagal: " . $conn_webhook->error);
                            $stmt_stok4->bind_param("i", $galon);
                            if (!$stmt_stok4->execute()) throw new Exception("Execute update stok 4 gagal: " . $stmt_stok4->error);
                            $stmt_stok4->close();

                            // 7. Kurangi stok ID 5 (Tutup Galon)
                            $sql_update_stok5 = "UPDATE produk SET stock = stock - ? WHERE id_produk = 5"; // Diubah: id -> id_produk
                            $stmt_stok5 = $conn_webhook->prepare($sql_update_stok5);
                            if (!$stmt_stok5) throw new Exception("Prepare update stok 5 gagal: " . $conn_webhook->error);
                            $stmt_stok5->bind_param("i", $galon);
                            if (!$stmt_stok5->execute()) throw new Exception("Execute update stok 5 gagal: " . $stmt_stok5->error);
                            $stmt_stok5->close();

                            // 8. Hitung total tagihan (Gunakan $harga_produk dari DB)
                            $total_tagihan = $galon * $harga_produk;

                            // 9. Insert pengantaran
                            $sql_pengantaran = "INSERT INTO pengantaran (id_pesanan, status_pengantaran) VALUES (?, 'Belum Diproses')";
                            $stmt_pengantaran = $conn_webhook->prepare($sql_pengantaran);
                            if (!$stmt_pengantaran) throw new Exception("Prepare insert pengantaran gagal: " . $conn_webhook->error);
                            $stmt_pengantaran->bind_param("i", $id_pesanan_baru);
                            if (!$stmt_pengantaran->execute()) throw new Exception("Execute insert pengantaran gagal: " . $stmt_pengantaran->error);
                            $stmt_pengantaran->close();

                            // 10. Insert pembayaran
                            $sql_pembayaran = "INSERT INTO pembayaran (id_pesanan, jumlah_pembayaran, status_pembayaran) VALUES (?, ?, 'Belum Lunas')";
                            $stmt_pembayaran = $conn_webhook->prepare($sql_pembayaran);
                            if (!$stmt_pembayaran) throw new Exception("Prepare insert pembayaran gagal: " . $conn_webhook->error);
                            $stmt_pembayaran->bind_param("id", $id_pesanan_baru, $total_tagihan);
                            if (!$stmt_pembayaran->execute()) throw new Exception("Execute insert pembayaran gagal: " . $stmt_pembayaran->error);
                            $stmt_pembayaran->close();

                            // 11. Commit
                            $conn_webhook->commit();
                            writeLog('whatsapp_webhook_log.txt', "Pesanan #{$id_pesanan_baru} (ID Produk: {$id_produk_pesan}) berhasil dibuat via WA.");

                            // 12. Kirim konfirmasi (Diedit untuk menambahkan link website)
$response_message = "Hai {$nama_plng_db}, pesanan Anda {$galon} {$nama_produk_pesan} (ID: #{$id_pesanan_baru}) telah kami terima & sedang dicek oleh kasir.\n\n" .
                    "Total tagihan: Rp " . number_format($total_tagihan, 0, ',', '.') . ".\n\n" .
                    "Cek status pesanan Anda secara real-time di sini:\n" .
                    "https://sipamis.fwh.is\n\n" .
                    "Terima kasih!";

sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                        } else {
                            // --- Pelanggan TIDAK Ditemukan ---
                            writeLog('whatsapp_webhook_log.txt', "Pelanggan dengan nomor {$no_telp_pengirim} tidak ditemukan saat pesan galon.");
                            $response_message = "Hai {$profile_name}, nomor Anda ({$no_telp_pengirim}) belum terdaftar di sistem kami. Mohon hubungi admin untuk pendaftaran.";
                            sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                        }
                    } catch (Exception $e) {
                        writeLog('whatsapp_webhook_log.txt', "ERROR proses pesanan WA: " . $e->getMessage());
                        if ($conn_webhook && $conn_webhook->ping()) {
                            $conn_webhook->rollback();
                        }
                        // Kirim pesan error umum HANYA jika bukan error stok/pesanan aktif
                        if (strpos($e->getMessage(), 'Stok') === false && strpos($e->getMessage(), 'Pesanan aktif') === false) {
                            sendWhatsAppMessage($no_telp_pengirim, $response_message_on_error, $accessToken, $phoneNumberId);
                        }
                    }
                    // Tidak perlu $conn_webhook->close() jika menggunakan koneksi global $conn

                } else {
                    writeLog('whatsapp_webhook_log.txt', "Pesan dari {$no_telp_pengirim} tidak dikenal sebagai pesanan galon: '{$message_body}'");
                    $response_message = "Hai {$profile_name}, format pesan Anda tidak dikenali. Untuk memesan Galon Isi Ulang, gunakan format: 'pesan [angka] galon' atau '[angka] galon'. Contoh: '5 galon'.";
                    sendWhatsAppMessage($no_telp_pengirim, $response_message, $accessToken, $phoneNumberId);
                }
            } else {
                writeLog('whatsapp_webhook_log.txt', "Menerima pesan BUKAN teks dari {$message['from']}. Tipe: " . ($message['type'] ?? 'unknown'));
            }
        } else {
            writeLog('whatsapp_webhook_log.txt', "Menerima payload POST tidak dikenal: " . $input);
        }

        http_response_code(200);
        echo 'OK';
        exit();
    } else {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}
