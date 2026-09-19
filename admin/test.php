<?php
include "../config/koneksi.php";

// AMBIL VALUE TERBARU DARI BROWSER (F12 > Application > Cookies > __test)
$cookie_val = "021a8a0455c4506d881d87e4b06bdfed";

$url = "http://sipamis.fwh.is/admin/test.php?key=masuk_sipamis_2024";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Set Header agar benar-benar menyerupai Browser
$headers = [
    "Cookie: __test=" . $cookie_val,
    "Accept: application/json",
    "Connection: keep-alive",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$datatrainer = curl_exec($ch);
curl_close($ch);

// BERSIHKAN DATA DARI KARAKTER NON-JSON
// Kadang hosting menyelipkan spasi atau karakter aneh di awal/akhir
$datatrainer = trim($datatrainer);
$start_pos = strpos($datatrainer, '[');
$end_pos = strrpos($datatrainer, ']');

if ($start_pos !== false && $end_pos !== false) {
    $datatrainer = substr($datatrainer, $start_pos, ($end_pos - $start_pos) + 1);
}

$data = json_decode($datatrainer, true);

if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
    $berhasil = 0;
    foreach ($data as $d) {
        $id = $d['id_pesanan'];

        // 1. SINKRON PESANAN
        $sql_p = "INSERT INTO pesanan (id_pesanan, id_plng, nama_plng, no_telp, id_produk, nama_produk, galon, tgl_pesan) 
                  VALUES ('$id', '{$d['id_plng']}', '{$d['nama_plng']}', '{$d['no_telp']}', '{$d['id_produk']}', '{$d['nama_produk']}', '{$d['galon']}', '{$d['tgl_pesan']}')
                  ON DUPLICATE KEY UPDATE nama_plng='{$d['nama_plng']}', no_telp='{$d['no_telp']}'";
        mysqli_query($conn, $sql_p);

        // 2. SINKRON PEMBAYARAN
        $sql_b = "INSERT INTO pembayaran (id_pembayaran, id_pesanan, jumlah_pembayaran, metode_pembayaran, status_pembayaran, tgl_pembayaran, bukti_pembayaran) 
                  VALUES ('{$d['id_pembayaran']}', '$id', '{$d['jumlah_pembayaran']}', '{$d['metode_pembayaran']}', '{$d['status_pembayaran']}', '{$d['tgl_pembayaran']}', '{$d['bukti_pembayaran']}')
                  ON DUPLICATE KEY UPDATE status_pembayaran='{$d['status_pembayaran']}', jumlah_pembayaran='{$d['jumlah_pembayaran']}'";
        mysqli_query($conn, $sql_b);

        // 3. SINKRON PENGANTARAN
        $sql_a = "INSERT INTO pengantaran (id_pengantaran, id_pesanan, id_user, nama_pegawai, status_pengantaran, waktu_ambil, waktu_selesai, tracking_token, kurir_lat, kurir_lng) 
                  VALUES ('{$d['id_pengantaran']}', '$id', '{$d['id_user']}', '{$d['nama_pegawai']}', '{$d['status_pengantaran']}', '{$d['waktu_ambil']}', '{$d['waktu_selesai']}', '{$d['tracking_token']}', '{$d['kurir_lat']}', '{$d['kurir_lng']}')
                  ON DUPLICATE KEY UPDATE status_pengantaran='{$d['status_pengantaran']}', nama_pegawai='{$d['nama_pegawai']}'";
        mysqli_query($conn, $sql_a);

        $berhasil++;
    }
    echo "<div style='color:green; font-weight:bold;'>Sinkronisasi Berhasil! $berhasil data diproses.</div>";
} else {
    // TAMPILKAN HASIL MENTAH UNTUK DEBUG JIKA GAGAL
    echo "<div style='color:red; font-weight:bold;'>Format JSON Salah.</div>";
    echo "Isi yang diterima: <br><textarea style='width:100%; height:200px;'>" . htmlspecialchars($datatrainer) . "</textarea>";
}
