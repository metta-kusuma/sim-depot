<?php
// Pastikan $act dan $menu (dari index.php) ada nilainya
$act = isset($_GET['act']) ? $_GET['act'] : '';
$menuParam = 'aktivitasdata'; // Nama menu untuk URL
// ASUMSI: $conn sudah tersedia
// ASUMSI: Untuk Cloudflare, URL API dan Token/Key akan dihandle di data.php

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kualitas Air & Aktivitas</title>
    <style>
        /* CSS Anda (ditambahkan styling untuk input type="file" dan link bukti) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        .aktivitas-container {
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

        .add-button-container {
            text-align: center;
            margin-bottom: 25px;
        }

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
            line-height: 1.5;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        .action-buttons a {
            margin: 0 3px;
        }

        /* Styling Form (Tambah & Edit) */
        .form-section {
            padding: 20px;
            margin-bottom: 30px;
            background: #fdfdfd;
            border: 1px solid #e7eaf3;
            border-radius: 8px;
        }

        .form-section h2 {
            text-align: center;
            font-size: 1.4em;
            margin-bottom: 25px;
            color: #34495e;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
            font-size: 0.9em;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea,
        .form-group input[type="file"] {
            /* Tambahkan type file */
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            transition: border-color 0.3s ease;
            background-color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input[type="file"] {
            /* Style khusus untuk file input */
            padding: 8px 12px;
            height: auto;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            margin-top: 25px;
            text-align: right;
        }

        .form-actions .btn {
            margin-left: 10px;
        }

        /* Styling Tabel Riwayat */
        .riwayat-section h2 {
            text-align: center;
            font-size: 1.4em;
            margin-bottom: 20px;
            color: #34495e;
            font-weight: 600;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e4f1;
            border-radius: 8px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .riwayat-table {
            width: 100%;
            border-collapse: collapse;
        }

        .riwayat-table th,
        .riwayat-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e4f1;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .riwayat-table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: #5a6a85;
            white-space: nowrap;
        }

        .riwayat-table tbody tr:last-child td {
            border-bottom: none;
        }

        .riwayat-table tbody tr:hover {
            background-color: #f1f5ff;
        }

        .riwayat-table .actions {
            text-align: center;
            white-space: nowrap;
        }

        .bukti-link {
            display: inline-block;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .bukti-link:hover {
            text-decoration: underline;
        }

        /* Responsive Table for Mobile */
        @media screen and (max-width: 768px) {
            .content-header h1 {
                font-size: 1.4em;
            }

            .aktivitas-container {
                padding: 10px;
                margin: 10px;
            }

            .form-section,
            .upload-form-section {
                padding: 15px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 0.9em;
                padding: 8px 10px;
            }

            .riwayat-table thead {
                display: none;
            }

            .riwayat-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e0e4f1;
                border-radius: 6px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .riwayat-table td {
                display: block;
                text-align: right;
                border-bottom: 1px dotted #ccc;
                position: relative;
                padding-left: 45%;
                padding-top: 8px;
                padding-bottom: 8px;
                white-space: normal;
                min-height: 20px;
            }

            .riwayat-table td:last-child {
                border-bottom: none;
            }

            .riwayat-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: calc(45% - 15px);
                padding-right: 5px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: #5a6a85;
                font-size: 0.9em;
            }

            .riwayat-table .actions {
                text-align: center;
                padding-left: 10px;
            }

            .riwayat-table .actions::before {
                content: "";
            }

            .action-buttons .btn {
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>

    <div class="aktivitas-container">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-10">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Data Kualitas Air & Aktivitas</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><a href="index.php?menu=aktivitasdata">Aktivitas</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php
        switch ($act) {
            default:
        ?>
                <div class="add-button-container">
                    <a href="?menu=<?php echo $menuParam; ?>&act=tambah" class="btn btn-primary">Tambah Data Aktivitas</a>
                </div>

                <div class="riwayat-section">
                    <h2>Daftar Aktivitas</h2>
                    <div class="table-responsive">
                        <table class='riwayat-table' id='example1'>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tanggal & Waktu</th>
                                    <th>Jenis Pengecekan</th>
                                    <th>Hasil</th>
                                    <th>Keterangan</th>
                                    <th>Bukti</th>
                                    <th>Petugas</th>
                                    <th class="actions">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT da.*, u.user_login
                                    FROM data_aktivitas da
                                    LEFT JOIN user u ON da.id_user = u.id_user
                                    ORDER BY da.id_aktivitas DESC";
                                $tampil = mysqli_query($conn, $sql);

                                if (mysqli_num_rows($tampil) > 0) {
                                    $total_cols = 8; // Total kolom setelah ditambah kolom Bukti
                                    while ($r = mysqli_fetch_array($tampil)) {
                                        $id_edit = htmlspecialchars($r['id_aktivitas']);
                                        $id_hapus = htmlspecialchars($r['id_aktivitas']);
                                        $tgl_terformat = date('d M Y, H:i', strtotime($r['tgl_aktivitas']));
                                        $bukti_url = htmlspecialchars($r['bukti_url'] ?? ''); // Ambil URL bukti

                                        echo "<tr>";
                                        echo "<td data-label='ID'>" . htmlspecialchars($r['id_aktivitas']) . "</td>";
                                        echo "<td data-label='Tanggal'>" . $tgl_terformat . "</td>";
                                        echo "<td data-label='Jenis'>" . htmlspecialchars($r['jenis_pengecekan']) . "</td>";
                                        echo "<td data-label='Hasil'>" . htmlspecialchars($r['hasil']) . "</td>";
                                        echo "<td data-label='Keterangan'>" . htmlspecialchars($r['keterangan']) . "</td>";

                                        // Kolom Bukti
                                        echo "<td data-label='Bukti'>";
                                        if (!empty($bukti_url)) {
                                            // Sesuaikan path ini dengan struktur folder Anda. 
                                            // Jika posisi file ini ada di folder admin, gunakan '../' untuk kembali ke root
                                            $path_bukti = "../assets/aktivitas_bukti/" . $bukti_url;

                                            echo "<a href='" . $path_bukti . "' target='_blank' class='bukti-link'>
             Lihat Bukti
          </a>";
                                        } else {
                                            echo "<span style='color: #999; font-style: italic;'>Belum Ada</span>";
                                        }
                                        echo "</td>";

                                        echo "<td data-label='Petugas'>" . htmlspecialchars($r['user_login'] ?? 'N/A') . "</td>";
                                        echo "<td class='actions'>";
                                        echo "<div class='action-buttons'>";
                                        echo "<a href='?menu=" . $menuParam . "&act=edit&id_aktivitas=" . $id_edit . "' class='btn btn-secondary btn-sm'>Edit</a>";
                                        echo "<a href=\"./data.php?menu=aktivitasdata&act=batal&id_aktivitas=" . $id_hapus . "\" class='btn btn-danger btn-sm' onClick=\"return confirm('Apakah data ini akan dihapus?')\">Hapus</a>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' style='text-align:center;'>Belum ada data aktivitas.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <br><br>
            <?php
                break; // Akhir case default

            case "tambah":
                $id_petugas = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : 0;
                $nama_petugas = isset($_SESSION['user_login']) ? htmlspecialchars($_SESSION['user_login']) : 'Admin';
            ?>
                <div class="form-section">
                    <h2>Tambah Data Aktivitas Baru</h2>
                    <form method='POST' action='./data.php?menu=aktivitasdata&act=input' enctype="multipart/form-data">

                        <div class="form-group">
                            <label for="jenis_pengecekan">Jenis Pengecekan/Aktivitas</label>
                            <select id="jenis_pengecekan" name='jenis_pengecekan' required>
                                <option value='' disabled selected>-- Pilih Jenis --</option>
                                <option value='Tes pH'>Tes pH</option>
                                <option value='TDS'>Tes TDS</option>
                                <option value='Pembersihan Filter'>Pembersihan Filter</option>
                                <option value='Pergantian Filter'>Pergantian Filter</option>
                                <option value='Lainnya'>Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="hasil">Hasil</label>
                            <input type='text' id="hasil" name='hasil' placeholder='Misal: 7.2 atau 15 ppm (jika ada)' autocomplete='off' />
                        </div>

                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea id="keterangan" name='keterangan'></textarea>
                        </div>

                        <div class="form-group">
                            <label for="bukti_bayar">Upload Bukti (Max 5MB)</label>
                            <input type='file' id="bukti_bayar" name='bukti_bayar' accept="image/*, application/pdf" />
                            <small>Format: Gambar (JPG/PNG) atau PDF.</small>
                        </div>

                        <div class="form-group">
                            <label>Petugas</label>
                            <input type='text' value='<?php echo $nama_petugas; ?>' readonly />
                        </div>
                        <input type='hidden' name='id_user' value='<?php echo $id_petugas; ?>'>

                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo $menuParam; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary">Simpan Data</button>
                        </div>
                    </form>
                </div>
                <br><br>
            <?php
                break; // Akhir case tambah

            case "edit":
                $id_aktivitas = isset($_GET['id_aktivitas']) ? mysqli_real_escape_string($conn, $_GET['id_aktivitas']) : 0;

                if ($id_aktivitas <= 0) {
                    echo "<p style='color:red; text-align:center;'>ID Aktivitas tidak valid.</p>";
                    break;
                }

                $sql_edit = "SELECT da.*, u.user_login
                          FROM data_aktivitas da
                          LEFT JOIN user u ON da.id_user = u.id_user
                          WHERE da.id_aktivitas='$id_aktivitas'";
                $edit_query = mysqli_query($conn, $sql_edit);
                $r = mysqli_fetch_array($edit_query);

                if (!$r) {
                    echo "<p style='color:red; text-align:center;'>Data aktivitas dengan ID " . $id_aktivitas . " tidak ditemukan.</p>";
                    break;
                }

                $tgl_edit_terformat = date('d M Y, H:i', strtotime($r['tgl_aktivitas']));
                $nama_petugas_edit = htmlspecialchars($r['user_login'] ?? 'N/A');
                $bukti_url_lama = htmlspecialchars($r['bukti_url'] ?? ''); // Ambil URL lama
            ?>
                <div class="form-section">
                    <h2>Edit Data Aktivitas #<?php echo htmlspecialchars($r['id_aktivitas']); ?></h2>
                    <form method='POST' action='./data.php?menu=aktivitasdata&act=update' enctype="multipart/form-data">
                        <input type='hidden' name='id_aktivitas' value='<?php echo htmlspecialchars($r['id_aktivitas']); ?>'>
                        <input type='hidden' name='bukti_url_lama' value='<?php echo $bukti_url_lama; ?>'>
                        <div class="form-group">
                            <label>Tanggal Aktivitas (Otomatis)</label>
                            <input type='text' value='<?php echo $tgl_edit_terformat; ?>' readonly />
                        </div>

                        <div class="form-group">
                            <label for="jenis_pengecekan">Jenis Pengecekan/Aktivitas</label>
                            <select id="jenis_pengecekan" name='jenis_pengecekan' required>
                                <option value='' disabled>-- Pilih Jenis --</option>
                                <?php
                                $jenis_array = ['Tes pH', 'TDS', 'Pembersihan Filter', 'Pergantian Filter', 'Lainnya'];
                                foreach ($jenis_array as $jenis) {
                                    $jenis_html = htmlspecialchars($jenis);
                                    $selected = ($r['jenis_pengecekan'] == $jenis) ? 'selected' : '';
                                    echo "<option value='" . $jenis_html . "' " . $selected . ">" . $jenis_html . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="hasil">Hasil</label>
                            <input type='text' id="hasil" name='hasil' value='<?php echo htmlspecialchars($r['hasil']); ?>' placeholder='Misal: 7.2 atau 15 ppm (jika ada)' autocomplete='off' />
                        </div>

                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea id="keterangan" name='keterangan'><?php echo htmlspecialchars($r['keterangan']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="bukti_bayar_edit">Ganti Bukti (Max 5MB)</label>
                            <?php if (!empty($bukti_url_lama)): ?>
                                <?php
                                // Sesuaikan path ke folder lokal Anda
                                $path_bukti_lama = "../assets/aktivitas_bukti/" . $bukti_url_lama;
                                ?>
                                <p>Bukti saat ini:
                                    <a href="<?php echo $path_bukti_lama; ?>" target="_blank" class="bukti-link">
                                        Lihat Bukti Lama
                                    </a>
                                </p>
                            <?php else: ?>
                                <p>Bukti saat ini: <span style="color: #999;">N/A (Belum ada file)</span></p>
                            <?php endif; ?>

                            <input type='file' id="bukti_bayar_edit" name='bukti_bayar_edit' accept="image/*, application/pdf" class="form-control" />
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti bukti. File baru akan otomatis menghapus file lama di server.</small>
                        </div>

                        <div class="form-group">
                            <label>Petugas</label>
                            <input type='text' value='<?php echo $nama_petugas_edit; ?>' readonly />
                        </div>

                        <div class="form-actions">
                            <button type='button' class="btn btn-secondary" onclick='window.location.href="index.php?menu=<?php echo $menuParam; ?>"'>Batal</button>
                            <button type='submit' class="btn btn-primary">Update Data</button>
                        </div>
                    </form>
                </div>
                <br><br>
        <?php
                break; // Akhir case edit
        } // Akhir switch($act)
        ?>
    </div>
</body>