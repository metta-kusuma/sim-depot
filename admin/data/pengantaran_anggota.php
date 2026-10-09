<?php
// Pastikan hanya level 'anggota' yang bisa mengakses halaman ini
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'anggota') {
    die("Akses ditolak. Halaman ini hanya untuk Anggota.");
}

?>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<style>
    .card-list-container { 
        padding: 10px; 
        /* Beri ruang di bawah untuk tombol aksi yang melayang */
        padding-bottom: 100px; 
    }
    .order-card {
        background-color: white;
        border: 1px solid #dee2e6;
        border-left: 5px solid #fd7e14; /* Warna oranye untuk tugas baru */
        border-radius: 8px;
        margin-bottom: 15px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        transition: box-shadow 0.2s ease-in-out;
    }
    .order-card:hover { 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
    }
    .order-card .selection {
        padding-right: 20px;
    }
    .order-card .selection input[type="checkbox"] {
        transform: scale(1.5); /* Membuat checkbox lebih besar dan mudah disentuh */
        cursor: pointer;
    }
    .order-card .info { 
        flex-grow: 1; 
    }
    .order-card .info .id-pesanan { 
        font-size: 0.8em; 
        color: #6c757d; 
        font-weight: bold; 
    }
    .order-card .info .nama-pelanggan { 
        font-size: 1.2em; 
        font-weight: 500; 
        color: #333; 
        margin: 5px 0; 
    }
    .order-card .info .details { 
        display: flex; 
        flex-direction: column; /* Susun detail ke bawah */
        gap: 8px; /* Jarak antar detail */
        font-size: 0.9em; 
        color: #444; 
    }
    .details span {
        display: flex;
        align-items: center;
    }
    .details i {
        margin-right: 8px;
        width: 15px;
        text-align: center;
    }
    .bulk-action-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: #ffffff;
        padding: 15px;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
        z-index: 100;
    }
    /* Sembunyikan footer jika tidak ada javascript (opsional) */
    .no-js .bulk-action-footer {
        display: none;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <h1 class="m-0 text-dark">Daftar Pengantaran Tersedia</h1>
    </div>
</div>

<form method="POST" action="./data.php?menu=pengantaran&act=ambil_multi" id="form-pengantaran">
    <div class='col-12 card-list-container'>
        <?php
        // Query untuk mengambil pesanan yang siap diantar (status 'Diproses') dan belum ada yang mengambil
        $sql = "SELECT p.id_pesanan, p.galon, plg.nama_plng, plg.alamat, peng.id_pengantaran
                FROM pengantaran peng
                JOIN pesanan p ON peng.id_pesanan = p.id_pesanan
                JOIN data_plng plg ON p.id_plng = plg.id_plng
                WHERE peng.status_pengantaran = 'Diproses' AND peng.id_user IS NULL and peng.status_pengantaran <>'Belum Diproses'
                ORDER BY p.tgl_pesan ASC";
        
        $tampil = mysqli_query($conn, $sql);

        if (mysqli_num_rows($tampil) > 0) {
            while ($r = mysqli_fetch_array($tampil)) {
                ?>
                <div class="order-card">
                    <div class="selection">
                        <input type="checkbox" name="id_pengantaran[]" value="<?= htmlspecialchars($r['id_pengantaran']) ?>" class="pengantaran-checkbox">
                    </div>

                    <div class="info">
                        <div class="id-pesanan">ID PESANAN: <?= htmlspecialchars($r['id_pesanan']) ?></div>
                        <div class="nama-pelanggan"><?= htmlspecialchars($r['nama_plng']) ?></div>
                        <div class="details">
                            <span><i class="fa-solid fa-bottle-water"></i> <?= htmlspecialchars($r['galon']) ?> Galon</span>
                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($r['alamat']) ?></span>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<div class='alert alert-success text-center'>Kerja bagus! Tidak ada pengantaran baru yang tersedia saat ini.</div>";
        }
        ?>
    </div>

    <?php if (mysqli_num_rows($tampil) > 0): ?>
    <div class="bulk-action-footer">
        <button type="submit" id="submit-button" class="btn btn-success btn-lg btn-block" disabled>
            Ambil Pesanan Terpilih (<span id="selected-count">0</span>)
        </button>
    </div>
    <?php endif; ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hilangkan class no-js untuk menampilkan footer jika JS aktif
    document.body.classList.remove('no-js');

    const checkboxes = document.querySelectorAll('.pengantaran-checkbox');
    const submitButton = document.getElementById('submit-button');
    const selectedCountSpan = document.getElementById('selected-count');
    const form = document.getElementById('form-pengantaran');

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.pengantaran-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount;

        if (checkedCount > 0) {
            submitButton.disabled = false;
        } else {
            submitButton.disabled = true;
        }
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateButtonState);
    });
    
    form.addEventListener('submit', function(e) {
        if (document.querySelectorAll('.pengantaran-checkbox:checked').length === 0) {
            e.preventDefault(); // Mencegah form disubmit jika tidak ada yang dipilih
            alert('Silakan pilih minimal satu pesanan untuk diambil.');
        } else {
            // Konfirmasi sebelum submit
            if (!confirm('Anda yakin akan mengambil ' + selectedCountSpan.textContent + ' pesanan yang dipilih?')) {
                e.preventDefault();
            }
        }
    });

    // Panggil sekali di awal untuk inisialisasi
    updateButtonState();
});

// Tambahkan class no-js ke body di awal, akan dihapus oleh JS
document.body.classList.add('no-js');
</script>