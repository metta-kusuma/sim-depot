<?php
// Pastikan hanya level 'pelanggan' yang bisa mengakses halaman ini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'pelanggan') {
    die("Akses ditolak. Halaman ini hanya untuk Pelanggan.");
}

// Ambil data pelanggan dari Sesi
$id_pelanggan_login = (int)$_SESSION['id_plng'];
$nama_pelanggan_login = htmlspecialchars($_SESSION['nama_plng']);
$notelp_pelanggan_login = htmlspecialchars($_SESSION['no_telp']);

// Ambil daftar produk yang tersedia (yang ada stoknya)
$sql_produk = "SELECT id_produk, nama_produk, harga, stock FROM produk WHERE stock > 0 ";
$result_produk = mysqli_query($conn, $sql_produk);
?>

<head>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
        }

        .pesanan-container {
            padding: 15px;
            max-width: 800px;
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
        }

        .form-section {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            background-color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .total-display {
            font-size: 1.5em;
            font-weight: 700;
            color: #3498db;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-weight: 500;
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
    </style>
</head>

<div class="pesanan-container">

    <div class="content-header">
        <h1>Buat Pesanan Baru</h1>
    </div>

    <div class="form-section">
        <form method="POST" action="../pelanggan/data.php?menu=pesanan&act=input" id="form-pesan">
            <div class="form-group">
                <label>Nama Pelanggan</label>
                <input type="text" value="<?php echo $nama_pelanggan_login; ?>" readonly>
            </div>
            <div class="form-group">
                <label>Nomor Telepon</label>
                <input type="text" value="<?php echo $notelp_pelanggan_login; ?>" readonly>
            </div>

            <hr>

            <div class="form-group">
                <label for="id_produk">Pilih Produk</label>
                <select id="id_produk" name="id_produk" required>
                    <option value="" data-harga="0" data-nama="">-- Pilih Produk --</option>
                    <?php
                    while ($p = mysqli_fetch_assoc($result_produk)) {
                        // Kita simpan harga dan nama di data-attributes
                        echo sprintf(
                            '<option value="%d" data-harga="%f" data-nama="%s" data-stok="%d">%s - Rp %s (Stok: %d)</option>',
                            $p['id'],
                            $p['harga'],
                            htmlspecialchars($p['nama_produk']),
                            $p['stock'],
                            htmlspecialchars($p['nama_produk']),
                            number_format($p['harga'], 0, ',', '.'),
                            $p['stock']
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="jumlah_galon">Jumlah Pesan</label>
                <input type="number" id="jumlah_galon" name="galon" min="1" value="1" required>
                <small id="stok-warning" style="color: red; display: none;">Stok tidak mencukupi!</small>
            </div>

            <div class="total-display" id="total-harga-display">
                Total: Rp 0
            </div>

            <input type="hidden" name="nama_produk" id="nama_produk_hidden">

            <div style="margin-top: 30px; text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="window.history.back();">Batal</button>
                <button type="submit" class="btn btn-primary" id="btn-submit-pesan">Pesan Sekarang</button>
            </div>

        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produkSelect = document.getElementById('id_produk');
        const jumlahInput = document.getElementById('jumlah_galon');
        const totalDisplay = document.getElementById('total-harga-display');
        const namaProdukHidden = document.getElementById('nama_produk_hidden');
        const stokWarning = document.getElementById('stok-warning');
        const submitButton = document.getElementById('btn-submit-pesan');

        let hargaSatuan = 0;
        let stokTersedia = 0;

        function hitungTotal() {
            const jumlah = parseInt(jumlahInput.value) || 0;

            // Cek Stok
            if (jumlah > stokTersedia) {
                stokWarning.style.display = 'block';
                submitButton.disabled = true; // Matikan tombol jika stok kurang
            } else {
                stokWarning.style.display = 'none';
                submitButton.disabled = false;
            }

            const total = hargaSatuan * jumlah;

            // Format ke Rupiah
            const formatter = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            });

            totalDisplay.textContent = 'Total: ' + formatter.format(total);
        }

        produkSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            hargaSatuan = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
            stokTersedia = parseInt(selectedOption.getAttribute('data-stok')) || 0;
            namaProdukHidden.value = selectedOption.getAttribute('data-nama') || '';

            // Set max di input jumlah sesuai stok
            jumlahInput.max = stokTersedia;

            hitungTotal();
        });

        jumlahInput.addEventListener('input', hitungTotal);
    });
</script>