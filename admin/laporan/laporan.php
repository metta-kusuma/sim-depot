<?php
// Pastikan koneksi $conn sudah tersedia di sini

// --- 1. AMBIL DATA BULAN-TAHUN UNIK ---
// Ambil semua bulan-tahun unik dari 3 tabel 
$sql_penjualan = "SELECT DISTINCT DATE_FORMAT(tgl_pesan, '%Y-%m') as bulan_tahun FROM pesanan WHERE tgl_pesan IS NOT NULL ORDER BY bulan_tahun DESC";
$query_penjualan = mysqli_query($conn, $sql_penjualan);
$bulan_tahun_penjualan = [];
if ($query_penjualan) while ($row = mysqli_fetch_assoc($query_penjualan)) $bulan_tahun_penjualan[] = $row['bulan_tahun'];

$sql_kualitas = "SELECT DISTINCT DATE_FORMAT(tgl_aktivitas, '%Y-%m') as bulan_tahun FROM data_aktivitas WHERE tgl_aktivitas IS NOT NULL AND (jenis_pengecekan = 'Tes pH' OR jenis_pengecekan = 'TDS') ORDER BY bulan_tahun DESC";
$query_kualitas = mysqli_query($conn, $sql_kualitas);
$bulan_tahun_kualitas = [];
if ($query_kualitas) while ($row = mysqli_fetch_assoc($query_kualitas)) $bulan_tahun_kualitas[] = $row['bulan_tahun'];

$sql_histori = "SELECT DISTINCT DATE_FORMAT(waktu_update, '%Y-%m') as bulan_tahun FROM histori_stok WHERE waktu_update IS NOT NULL ORDER BY bulan_tahun DESC";
$query_histori = mysqli_query($conn, $sql_histori);
$bulan_tahun_histori = [];
if ($query_histori) while ($row = mysqli_fetch_assoc($query_histori)) $bulan_tahun_histori[] = $row['bulan_tahun'];

// --- 2. AMBIL DATA TAHUN UNIK ---
$all_bulan_tahun = array_unique(array_merge($bulan_tahun_penjualan, $bulan_tahun_kualitas, $bulan_tahun_histori));
$tahun_unik = [];
foreach ($all_bulan_tahun as $bt) {
    $tahun = substr($bt, 0, 4);
    if (!empty($tahun)) $tahun_unik[] = $tahun;
}
$tahun_unik = array_unique($tahun_unik);
rsort($tahun_unik); // Urutkan dari tahun terbaru

// --- 3. AMBIL DATA PRODUK UNTUK FILTER HISTORI STOK ---
$sql_produk = "SELECT id_produk, nama_produk FROM produk ORDER BY nama_produk ASC";
$query_produk = mysqli_query($conn, $sql_produk);
$daftar_produk = [];
if ($query_produk) {
    while ($row = mysqli_fetch_assoc($query_produk)) {
        $daftar_produk[] = $row;
    }
}


// --- Fungsi helper (format bulan-tahun) ---
function format_bulan_tahun_indo($yyyy_mm)
{
    if (empty($yyyy_mm) || strpos($yyyy_mm, '-') === false) return $yyyy_mm;
    $timestamp = strtotime($yyyy_mm . '-01');
    if (!$timestamp) return $yyyy_mm;
    $bulan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    return ($bulan_map[date('n', $timestamp)] ?? '') . ' ' . date('Y', $timestamp);
}
?>
<style>
    /* CSS Anda */
    td {
        font-size: 12px;
        border: 1px #a9c6c9 solid;
        color: black;
    }

    th {
        font-size: 12px;
        border: 1px #a9c6c9 solid;
        color: black;
    }

    input[type="text"],
    input[type="date"],
    input[type="number"],
    input[type="password"],
    select,
    textarea {
        font-family: Tahoma, Geneva, sans-serif;
        font-size: 11px;
        padding: 5px;
        height: 30px;
        border: 1px solid #CCC;
    }

    .laporan-content {
        padding-top: 20px;
        padding-left: 10px;
        max-width: 450px;
    }

    .laporan-content h2 {
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 25px;
        color: #333;
    }

    .laporan-form-group {
        margin-bottom: 20px;
    }

    .laporan-form-group label {
        display: block;
        font-size: 16px;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }

    .laporan-form-group select,
    .laporan-form-group input[type="date"] {
        width: 100%;
        height: 45px;
        padding: 0 10px;
        font-size: 14px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-family: Tahoma, Geneva, sans-serif;
    }

    .btn-download {
        padding: 10px 20px;
        font-size: 14px;
        background-color: #f0f0f0;
        border: 1px solid #aaa;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
    }

    .btn-download:hover {
        background-color: #e0e0e0;
    }

    .btn-download:disabled {
        background-color: #e9e9e9;
        border-color: #ccc;
        color: #999;
        cursor: not-allowed;
    }

    .btn-download:disabled:hover {
        background-color: #e9e9e9;
    }

    .date-range-group {
        display: flex;
        gap: 15px;
    }

    .date-range-group .form-group {
        flex: 1;
        margin-bottom: 0;
    }

    @media (max-width: 500px) {
        .date-range-group {
            flex-direction: column;
            gap: 0;
        }

        .date-range-group .form-group {
            margin-bottom: 15px;
        }
    }

    .hidden-filter {
        display: none !important;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-10">
            <div class="col-sm-2">
                <h1 class="m-0 text-dark">LAPORAN</h1>
            </div>
            <div class="col-sm-10">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active"><a href='index.php?menu=laporan'>Laporan</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class='col-12'>
    <div class="laporan-content">
        <h2>Laporan</h2>

        <form action="./data.php?menu=laporan&act=hasil" method="POST" target="_blank" id="laporanForm">
            <div class="laporan-form-group">
                <label for="jenis_laporan">Jenis Laporan</label>
                <select id="jenis_laporan" name="jenis_laporan">
                    <option value="penjualan">Laporan Penjualan Harian</option>
                    <option value="kualitas_air">Laporan Kualitas Air Harian</option>
                    <option value="histori_stok">Laporan Histori Stok</option>
                </select>
            </div>

            <div class="laporan-form-group">
                <label for="tipe_filter">Pilih Tipe Periode</label>
                <select id="tipe_filter" name="tipe_filter">
                    <option value="bulan">Bulan & Tahun</option>
                    <option value="rentang">Rentang Tanggal Khusus</option>
                </select>
            </div>

            <div class="laporan-form-group" id="filter-bulan-tahun">
                <label for="bulan_tahun_laporan">Pilih Periode (Bulan-Tahun)</label>
                <select id="bulan_tahun_laporan" name="filter_value" required>
                </select>
            </div>

            <div class="laporan-form-group hidden-filter" id="filter-tahun">
                <label for="tahun_laporan">Pilih Tahun</label>
                <select id="tahun_laporan" name="filter_value" required>
                    <option value="">-- Pilih Tahun --</option>
                    <?php
                    foreach ($tahun_unik as $tahun) {
                        echo "<option value='{$tahun}'>{$tahun}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="laporan-form-group hidden-filter" id="filter-rentang-tanggal">
                <label>Pilih Rentang Tanggal</label>
                <div class="date-range-group">
                    <div class="form-group">
                        <label for="tanggal_awal" style="font-size: 0.9em; font-weight: normal;">Dari Tanggal</label>
                        <input type="date" id="tanggal_awal" name="tanggal_awal" value="<?php echo date('Y-m-01'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_akhir" style="font-size: 0.9em; font-weight: normal;">Sampai Tanggal</label>
                        <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo date('Y-m-d'); ?>" disabled>
                    </div>
                </div>
            </div>

            <div class="laporan-form-group hidden-filter" id="filter-produk">
                <label for="id_produk">Filter Berdasarkan Produk</label>
                <select id="id_produk" name="id_produk">
                    <option value="">-- Semua Produk --</option>
                    <?php
                    foreach ($daftar_produk as $produk) {
                        $is_galon_berisi = ($produk['id_produk'] == 4) ? ' (Galon Berisi)' : '';
                        echo "<option value='{$produk['id_produk']}'>" . htmlspecialchars($produk['nama_produk']) . $is_galon_berisi . "</option>";
                    }
                    ?>
                </select>
                <small style="display: block; margin-top: 5px; color: #777;">Kosongkan jika ingin melihat semua produk.</small>
            </div>

            <input type="hidden" name="report_title" id="report_title" value="Laporan Penjualan Harian">

            <button type="submit" class="btn-download" id="btnDownload" disabled>Download</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const dataBulanTahun = {
            penjualan: <?php echo json_encode($bulan_tahun_penjualan); ?>,
            kualitas_air: <?php echo json_encode($bulan_tahun_kualitas); ?>,
            histori_stok: <?php echo json_encode($bulan_tahun_histori); ?>,
        };

        const bulanIndo = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

        function formatBulanTahunJS(yyyy_mm) {
            if (!yyyy_mm || yyyy_mm.indexOf('-') === -1) return yyyy_mm;
            const parts = yyyy_mm.split('-');
            const tahun = parts[0];
            const bulanAngka = parseInt(parts[1], 10);
            if (isNaN(bulanAngka) || bulanAngka < 1 || bulanAngka > 12) return yyyy_mm;
            return bulanIndo[bulanAngka] + ' ' + tahun;
        }

        const selectLaporan = document.getElementById('jenis_laporan');
        const selectTipeFilter = document.getElementById('tipe_filter');

        // Elemen Filter Periode
        const filterBulanTahunDiv = document.getElementById('filter-bulan-tahun');
        const selectPeriodeBulan = document.getElementById('bulan_tahun_laporan');
        const filterTahunDiv = document.getElementById('filter-tahun');
        const selectPeriodeTahun = document.getElementById('tahun_laporan');
        const filterRentangTanggalDiv = document.getElementById('filter-rentang-tanggal');
        const inputTanggalAwal = document.getElementById('tanggal_awal');
        const inputTanggalAkhir = document.getElementById('tanggal_akhir');

        // Elemen Filter Produk (BARU)
        const filterProdukDiv = document.getElementById('filter-produk');
        const selectIdProduk = document.getElementById('id_produk');

        const btnDownload = document.getElementById('btnDownload');

        // --- FUNGSI UTAMA UNTUK MENGUBAH FILTER YANG TAMPIL ---
        function toggleFilters() {
            const tipeFilter = selectTipeFilter.value;
            const jenisLaporan = selectLaporan.value;

            // 1. Reset required dan disabled untuk semua input/select filter
            selectPeriodeBulan.required = false;
            selectPeriodeBulan.disabled = false;
            selectPeriodeTahun.required = false;
            selectPeriodeTahun.disabled = false;
            inputTanggalAwal.required = false;
            inputTanggalAwal.disabled = true;
            inputTanggalAkhir.required = false;
            inputTanggalAkhir.disabled = true;
            selectIdProduk.required = false; // Reset produk

            // 2. Sembunyikan semua filter terlebih dahulu
            filterBulanTahunDiv.classList.add('hidden-filter');
            filterTahunDiv.classList.add('hidden-filter');
            filterRentangTanggalDiv.classList.add('hidden-filter');
            filterProdukDiv.classList.add('hidden-filter'); // Sembunyikan filter produk

            // 3. Tampilkan filter periode yang sesuai dan atur required/disabled
            if (tipeFilter === 'bulan') {
                filterBulanTahunDiv.classList.remove('hidden-filter');
                selectPeriodeBulan.required = true;
                selectPeriodeBulan.name = 'filter_value';
                inputTanggalAwal.name = 'tanggal_awal_dummy';
                inputTanggalAkhir.name = 'tanggal_akhir_dummy';
                selectPeriodeTahun.name = 'tahun_laporan_dummy';

            } else if (tipeFilter === 'tahun') {
                filterTahunDiv.classList.remove('hidden-filter');
                selectPeriodeTahun.required = true;
                selectPeriodeTahun.name = 'filter_value';
                inputTanggalAwal.name = 'tanggal_awal_dummy';
                inputTanggalAkhir.name = 'tanggal_akhir_dummy';
                selectPeriodeBulan.name = 'bulan_tahun_laporan_dummy';

            } else if (tipeFilter === 'rentang') {
                filterRentangTanggalDiv.classList.remove('hidden-filter');
                inputTanggalAwal.required = true;
                inputTanggalAkhir.required = true;
                inputTanggalAwal.disabled = false;
                inputTanggalAkhir.disabled = false;
                inputTanggalAwal.name = 'tanggal_awal';
                inputTanggalAkhir.name = 'tanggal_akhir';
                selectPeriodeBulan.name = 'bulan_tahun_laporan_dummy';
                selectPeriodeTahun.name = 'tahun_laporan_dummy';
            }

            // 4. Tampilkan filter PRODUK hanya jika jenis laporan = histori_stok
            if (jenisLaporan === 'histori_stok') {
                filterProdukDiv.classList.remove('hidden-filter');
                // Name di selectIdProduk sudah 'id_produk' by default
            } else {
                // Pastikan input produk tidak terkirim jika bukan histori stok
                selectIdProduk.name = 'id_produk';
            }


            // 5. Update Judul Laporan & Update dropdown bulan-tahun
            const selectedText = selectLaporan.options[selectLaporan.selectedIndex].text;
            document.getElementById('report_title').value = selectedText;

            if (tipeFilter === 'bulan') {
                updatePeriodeDropdownBulan(jenisLaporan);
            }

            // 6. Cek status tombol download
            checkDownloadButtonState();
        }

        function updatePeriodeDropdownBulan(jenisLaporan) {
            // ... (fungsi yang sudah ada) ...
            if (selectTipeFilter.value !== 'bulan' || !dataBulanTahun.hasOwnProperty(jenisLaporan)) {
                selectPeriodeBulan.innerHTML = '<option value="">N/A</option>';
                return;
            }

            const periodeArray = dataBulanTahun[jenisLaporan];
            selectPeriodeBulan.innerHTML = '';

            if (!periodeArray || periodeArray.length === 0) {
                selectPeriodeBulan.innerHTML = '<option value="">-- Tidak ada data --</option>';
            } else {
                let optionsHtml = '<option value="">-- Pilih Periode --</option>';
                periodeArray.forEach(function(bt) {
                    const text = formatBulanTahunJS(bt);
                    optionsHtml += `<option value="${bt}">${text}</option>`;
                });
                selectPeriodeBulan.innerHTML = optionsHtml;
            }
        }

        function checkDownloadButtonState() {
            const tipeFilter = selectTipeFilter.value;
            let isFilterValid = false;

            if (tipeFilter === 'rentang') {
                isFilterValid = inputTanggalAwal.value !== '' && inputTanggalAkhir.value !== '';
                if (isFilterValid && inputTanggalAkhir.value < inputTanggalAwal.value) {
                    isFilterValid = false;
                }
            } else if (tipeFilter === 'tahun') {
                isFilterValid = selectPeriodeTahun.value !== '';
            } else { // tipeFilter === 'bulan'
                const selectedText = selectPeriodeBulan.options[selectPeriodeBulan.selectedIndex]?.text;
                isFilterValid = selectPeriodeBulan.value !== '' && selectedText !== '-- Tidak ada data --' && selectedText !== 'N/A';
            }

            btnDownload.disabled = !isFilterValid;
        }

        // Event listener untuk jenis laporan DAN tipe filter
        selectLaporan.addEventListener('change', toggleFilters);
        selectTipeFilter.addEventListener('change', toggleFilters);

        // Event listener untuk elemen filter
        selectPeriodeBulan.addEventListener('change', checkDownloadButtonState);
        selectPeriodeTahun.addEventListener('change', checkDownloadButtonState);
        inputTanggalAwal.addEventListener('change', checkDownloadButtonState);
        inputTanggalAkhir.addEventListener('change', checkDownloadButtonState);
        // Tambah listener untuk filter produk (meskipun tidak memengaruhi validasi, ini bagus jika nanti perlu)
        selectIdProduk.addEventListener('change', checkDownloadButtonState);

        // Panggil sekali saat halaman dimuat
        toggleFilters();

    });
</script>