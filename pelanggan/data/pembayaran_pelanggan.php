<?php
// File: admin/pembayaran_pelanggan.php
// --- 1. KEAMANAN & PENGATURAN SESI PELANGGAN ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'pelanggan') {
    header("Location: ../login_pelanggan.php");
    exit;
}
$id_pelanggan_login = $_SESSION['id_plng'] ?? 0;
// --- AKHIR KEAMANAN ---

$act = $act ?? $_GET['act'] ?? 'default';
$menu = $menu ?? $_GET['menu'] ?? 'pembayaran';
$menuParam = $menu;

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Saya</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .pesanan-container {
            padding: 15px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .content-header {
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .content-header h1 {
            font-size: 1.6em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 0;
            font-size: 0.9em;
        }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-weight: 500;
            text-align: center;
            line-height: 1.5;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        /* Kartu Pesanan */
        .order-card {
            background-color: white;
            border: 1px solid #dee2e6;
            border-left: 5px solid #e74c3c;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }

        .order-card .info {
            flex-grow: 1;
        }

        .order-card .info .id-pesanan {
            font-size: 0.8em;
            color: #6c757d;
            font-weight: bold;
        }

        .order-card .info .tgl-pesan {
            font-size: 1.2em;
            font-weight: 500;
            color: #333;
            margin: 5px 0;
        }

        .order-card .info .total-tagihan {
            font-size: 1.1em;
            font-weight: 600;
            color: #c0392b;
            margin-top: 5px;
        }

        .order-card .action {
            margin-left: auto;
            padding-left: 15px;
        }

        /* Form Pembayaran */
        .form-section {
            padding: 25px;
            margin-bottom: 30px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
        }

        .form-section h2 {
            text-align: center;
            font-size: 1.5em;
            margin-bottom: 25px;
            color: #34495e;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .payment-instructions {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e4f1;
        }

        .payment-instructions h4 {
            margin-top: 0;
        }

        .payment-instructions img.qris {
            max-width: 250px;
            height: auto;
            border: 1px solid #ccc;
        }

        .form-actions {
            margin-top: 30px;
            text-align: right;
        }

        .form-actions .btn {
            margin-left: 10px;
        }

        @media screen and (max-width: 768px) {
            .order-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-card .action {
                width: 100%;
                margin-left: 0;
                margin-top: 15px;
                padding-left: 0;
            }

            .order-card .action .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="pesanan-container">
        <div class="content-header">
            <h1>Pembayaran Saya</h1>
        </div>

        <?php
        switch ($act) {

            // --- HALAMAN FORM UPLOAD BUKTI BAYAR ---
            case 'bayar':
                $id_pesanan = (int)$_GET['id_pesanan'];

                // Ambil detail tagihan yang BELUM LUNAS
                $sql_detail = "SELECT p.id_pesanan, p.tgl_pesan, pem.jumlah_pembayaran 
                           FROM pesanan p
                           JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                           WHERE p.id_pesanan = ? AND p.id_plng = ? AND pem.status_pembayaran = 'Belum Lunas'";
                $stmt_detail = mysqli_prepare($conn, $sql_detail);
                mysqli_stmt_bind_param($stmt_detail, "ii", $id_pesanan, $id_pelanggan_login);
                mysqli_stmt_execute($stmt_detail);
                $result_detail = mysqli_stmt_get_result($stmt_detail);
                $d = mysqli_fetch_assoc($result_detail);

                if (!$d) {
                    echo "<div class='form-section' style='text-align: center; border-color: #e74c3c;'>Tagihan tidak ditemukan, sudah lunas, atau Anda tidak memiliki akses.</div>";
                    break;
                }

                $total_tagihan_formatted = "Rp " . number_format($d['jumlah_pembayaran'] ?? 0, 0, ',', '.');
        ?>

                <div class="form-section">
                    <h2>Konfirmasi Pembayaran Pesanan #<?php echo $d['id_pesanan']; ?></h2>

                    <div class="form-group">
                        <label>Total Tagihan</label>
                        <input type="text" value="<?php echo $total_tagihan_formatted; ?>" readonly>
                    </div>

                    <form method="POST" action="../pelanggan/data.php?menu=pembayaran&act=bayar" enctype="multipart/form-data">
                        <input type="hidden" name="menu" value="pembayaran">
                        <input type="hidden" name="act" value="upload_bukti">
                        <input type="hidden" name="id_pesanan" value="<?php echo $d['id_pesanan']; ?>">

                        <div class="payment-instructions">
                            <h4>Instruksi Pembayaran</h4>
                            <p>Silakan lakukan pembayaran sejumlah <strong><?php echo $total_tagihan_formatted; ?></strong> ke salah satu rekening berikut:</p>

                            <p><strong>Bank BCA:</strong> 8230504246 a/n Metta Kusuma</p>
                            <p><strong>Bank CIMB:</strong> 763169466800 a/n Metta Kusuma</p>

                            <p>Atau scan menggunakan QRIS di bawah ini:</p>
                            <img src="data/assets/qris.png" alt="QRIS" class="qris">

                        </div>

                        <hr>

                        <div class="form-group">
                            <label for="bukti_pembayaran">Upload Bukti Transfer / QRIS</label>
                            <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" required
                                accept="image/jpeg,image/png,image/jpg">
                            <small>Hanya file JPG, JPEG, atau PNG. Maksimal 5MB.</small>
                        </div>

                        <div class="form-actions">
                            <a href="index.php?menu=pembayaran" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Saya Sudah Bayar & Upload Bukti
                            </button>
                        </div>
                    </form>
                </div>

            <?php
                break; // Akhir case 'bayar'


            // --- HALAMAN DAFTAR TAGIHAN "BELUM LUNAS" ---
            default:
            ?>
                <div class='col-12 card-list-container'>
                    <?php
                    // Query untuk mengambil data pesanan HANYA yang 'Belum Lunas'
                    $sql = "SELECT p.id_pesanan, p.tgl_pesan, p.galon, p.nama_produk, pem.jumlah_pembayaran
                        FROM pesanan p
                        JOIN pembayaran pem ON p.id_pesanan = pem.id_pesanan
                        WHERE p.id_plng = ? AND pem.status_pembayaran = 'Belum Lunas'
                        ORDER BY p.tgl_pesan DESC";

                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $id_pelanggan_login);
                    mysqli_stmt_execute($stmt);
                    $tampil = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($tampil) > 0) {
                        while ($r = mysqli_fetch_assoc($tampil)) {
                            $total_tagihan_formatted = "Rp " . number_format($r['jumlah_pembayaran'] ?? 0, 0, ',', '.');
                    ?>

                            <div class="order-card">
                                <div class="info">
                                    <div class="id-pesanan">ID PESANAN: <?= htmlspecialchars($r['id_pesanan']) ?></div>
                                    <div class="tgl-pesan"><?= date('d F Y, H:i', strtotime($r['tgl_pesan'])) ?></div>
                                    <div class="total-tagihan"><?= $total_tagihan_formatted ?></div>
                                </div>
                                <div class="action">
                                    <a href="index.php?menu=pembayaran&act=bayar&id_pesanan=<?= htmlspecialchars($r['id_pesanan']) ?>" class="btn btn-success">
                                        <i class="fas fa-money-bill-wave"></i> Bayar Sekarang
                                    </a>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        echo "<div class='form-section' style='text-align: center;'>Tidak ada tagihan yang belum dibayar.</div>";
                    }
                    mysqli_stmt_close($stmt);
                    ?>
                </div>
        <?php
                break; // Akhir case 'default'
        } // Akhir switch
        ?>
    </div>
</body>

</html>