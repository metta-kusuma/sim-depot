<style>
    /* Reset dasar & Font */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f4f7fc;
        /* Warna latar belakang lembut */
    }

    /* Container utama */
    .arsip-container {
        padding: 20px;
        max-width: 1200px;
        margin: 20px auto;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* Header Halaman */
    .content-header {
        padding-bottom: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .content-header h1 {
        font-size: 1.8em;
        font-weight: 600;
        color: #2c3e50;
        /* Warna judul lebih gelap */
    }

    .breadcrumb {
        background: none;
        padding: 0;
        margin-bottom: 0;
    }

    /* Styling Form Upload */
    .upload-form-section {
        padding: 25px;
        margin-bottom: 30px;
        background: #fdfdfd;
        /* Sedikit beda dari background utama */
        border: 1px solid #e7eaf3;
        border-radius: 8px;
    }

    .upload-form-section h2 {
        text-align: center;
        font-size: 1.5em;
        margin-bottom: 25px;
        color: #34495e;
        font-weight: 600;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
        font-size: 0.95em;
    }

    /* Input modern */
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="file"] {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #dcdcdc;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 1em;
        transition: border-color 0.3s ease;
        background-color: #fff;
    }

    .form-group input[type="text"]:focus,
    .form-group input[type="date"]:focus,
    .form-group input[type="file"]:focus {
        border-color: #3498db;
        /* Warna border saat fokus */
        outline: none;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    /* Styling khusus file input */
    .form-group input[type="file"] {
        padding: 8px;
        /* Padding berbeda untuk file input */
    }

    /* Tombol modern */
    .btn {
        display: inline-block;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 1em;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
        font-weight: 500;
        text-align: center;
    }

    .btn-primary {
        background-color: #3498db;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2980b9;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .btn-danger {
        background-color: #e74c3c;
        color: white;
    }

    .btn-danger:hover {
        background-color: #c0392b;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .btn-secondary {
        background-color: #95a5a6;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #7f8c8d;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.85em;
    }

    .action-buttons a {
        margin: 0 4px;
        /* Jarak antar tombol aksi */
    }

    /* Styling Tabel Riwayat */
    .riwayat-section h2 {
        text-align: center;
        font-size: 1.5em;
        margin-bottom: 20px;
        color: #34495e;
        font-weight: 600;
    }

    .table-responsive {
        overflow-x: auto;
        /* Scroll horizontal jika tabel terlalu lebar */
        -webkit-overflow-scrolling: touch;
        /* Scrolling halus di iOS */
        border: 1px solid #e0e4f1;
        border-radius: 8px;
        background-color: #fff;
        /* Latar belakang tabel */
    }

    .riwayat-table {
        width: 100%;
        border-collapse: collapse;
    }

    .riwayat-table th,
    .riwayat-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e0e4f1;
        /* Garis antar baris */
        font-size: 0.9em;
        vertical-align: middle;
    }

    .riwayat-table th {
        background-color: #f8f9fc;
        /* Warna header tabel */
        font-weight: 600;
        color: #5a6a85;
        white-space: nowrap;
        /* Cegah header wrap */
    }

    .riwayat-table tbody tr:last-child td {
        border-bottom: none;
        /* Hilangkan border di baris terakhir */
    }

    .riwayat-table tbody tr:hover {
        background-color: #f1f5ff;
        /* Warna hover */
    }

    .riwayat-table .actions {
        text-align: center;
        white-space: nowrap;
        /* Jaga tombol tetap sebaris */
    }

    /* === RESPONSIVE DESIGN UNTUK TABEL === */
    @media screen and (max-width: 768px) {
        .riwayat-table thead {
            display: none;
            /* Sembunyikan header asli di mobile */
        }

        .riwayat-table tr {
            display: block;
            /* Buat baris jadi blok */
            margin-bottom: 15px;
            border: 1px solid #e0e4f1;
            border-radius: 6px;
            background-color: #fff;
        }

        .riwayat-table td {
            display: block;
            /* Buat sel jadi blok */
            text-align: right;
            /* Ratakan teks ke kanan */
            border-bottom: 1px dotted #ccc;
            position: relative;
            padding-left: 50%;
            /* Ruang untuk label */
            white-space: normal;
            /* Biarkan teks wrap */
        }

        .riwayat-table td:last-child {
            border-bottom: none;
            /* Hilangkan border di sel terakhir */
        }

        /* Tambahkan label sebelum data sel */
        .riwayat-table td::before {
            content: attr(data-label);
            /* Ambil teks dari atribut data-label */
            position: absolute;
            left: 10px;
            width: calc(50% - 20px);
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: bold;
            color: #5a6a85;
        }

        .riwayat-table .actions {
            text-align: center;
            /* Tombol tetap di tengah */
            padding-left: 15px;
            /* Reset padding kiri */
        }

        .riwayat-table .actions::before {
            content: "";
            /* Kosongkan label untuk kolom aksi */
        }

        /* Sesuaikan lebar input form */
        .form-group input[type="text"],
        .form-group input[type="date"] {
            width: 100%;
        }
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-10">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Arsip Dokumen Dinkes</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active"><a href="index.php?menu=arsip_dinkes">Arsip Dinkes</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class='arsip-container'>

    <div class="upload-form-section">
        <h2>Upload Dokumen Baru</h2>

        <form method='POST' action='./data.php?menu=arsip_dinkes&act=upload' enctype="multipart/form-data">
            <div class="form-group">
                <label for="judul_dokumen">Judul Dokumen</label>
                <input type='text' id="judul_dokumen" name='judul_dokumen' placeholder="Misal: Hasil Uji Lab Q1 2025" required />
            </div>
            <div class="form-group">
                <label for="tgl_laporan">Tanggal Laporan (di surat)</label>
                <input type='date' id="tgl_laporan" name='tgl_laporan' required />
            </div>
            <div class="form-group">
                <label for="file_laporan">Pilih File (PDF/JPG/PNG, Maks 5MB)</label>
                <input type='file' id="file_laporan" name='file_laporan' accept=".pdf,.jpg,.jpeg,.png" required />
            </div>

            <input type='hidden' name='id_user' value='<?php echo isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : 0; ?>'>

            <div style="text-align: right;">
                <button type='submit' class="btn btn-primary">Upload Dokumen</button>
            </div>
        </form>
    </div>

    <div class="riwayat-section">
        <h2>Riwayat Arsip Dokumen</h2>
        <div class="table-responsive">
            <table class='riwayat-table'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Judul Dokumen</th>
                        <th>Tgl Laporan</th>
                        <th>Tgl Upload</th>
                        <th>Petugas</th>
                        <th class="actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT d.*, u.user_login
            FROM dokumen_resmi d
            LEFT JOIN user u ON d.id_user = u.id_user
            ORDER BY d.tgl_laporan DESC";
                    $tampil = mysqli_query($conn, $sql);

                    if (mysqli_num_rows($tampil) > 0) {
                        while ($r = mysqli_fetch_array($tampil)) {
                            $id_dokumen = htmlspecialchars($r['id_dokumen']);
                            $nama_file = $r['path_file']; // Ini adalah nama file yang tersimpan di DB

                            // Tentukan path lokal folder aset
                            $url_file = "../assets/dokumen_dinkes/" . htmlspecialchars($nama_file);

                            echo "<tr>";
                            echo "<td data-label='ID'>" . $id_dokumen . "</td>";
                            echo "<td data-label='Judul Dokumen'>" . htmlspecialchars($r['judul_dokumen']) . "</td>";
                            echo "<td data-label='Tgl Laporan'>" . date('d M Y', strtotime($r['tgl_laporan'])) . "</td>";
                            echo "<td data-label='Tgl Upload'>" . date('d M Y H:i', strtotime($r['tgl_upload'])) . "</td>";
                            echo "<td data-label='Petugas'>" . htmlspecialchars($r['user_login'] ?? 'N/A') . "</td>";
                            echo "<td class='actions'>";
                            echo "<div class='action-buttons'>";

                            // Tombol Lihat mengarah ke folder lokal
                            if (!empty($nama_file)) {
                                echo "<a href='" . $url_file . "' target='_blank' class='btn btn-secondary btn-sm'>
                         Lihat
                      </a>";
                            } else {
                                echo "<span class='badge badge-warning'>No File</span>";
                            }

                            echo "<a href=\"./data.php?menu=arsip_dinkes&act=hapus&id_dokumen=" . $id_dokumen . "\"
                    class='btn btn-danger btn-sm'
                    onClick=\"return confirm('Yakin ingin menghapus arsip ini? File di server juga akan dihapus permanen.')\">
                     Hapus
                  </a>";

                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>Belum ada dokumen yang diarsipkan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <br><br>
</div>