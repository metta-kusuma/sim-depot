<?php
// Tampilkan error saat pengembangan, nonaktifkan saat produksi
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Sertakan file koneksi yang berisi variabel $servername, dsb.
include "../config/koneksi.php";
// Membuat koneksi ke database. Anda mempertahankan baris ini.
$conn = mysqli_connect($servername, $username, $password, $database);
// Periksa apakah koneksi berhasil dibuat
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Ambil variabel sesi
$user_login = isset($_SESSION['user_login']) ? $_SESSION['user_login'] : '';
$level = isset($_SESSION['level']) ? $_SESSION['level'] : '';

// Ambil variabel GET dengan aman
$act = isset($_GET['act']) ? $_GET['act'] : '';
$menu = isset($_GET['menu']) ? $_GET['menu'] : '';

if (empty($user_login)) {
    include "login.php";
} else {
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Sipamis</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php
  include "include/link.php";
  ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="index.php" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="logout.php" class="nav-link">Logout</a>
      </li>
    </ul>
  </nav>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link">
      <img src="dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
           style="opacity: .8">
      <span class="brand-text font-weight-light">
    <?php echo htmlspecialchars($level); ?></span>
    </a>

    <div class="sidebar">
      <br><br>
<?php
if ($level == 'admin' || $level == 'direktur' || $level == 'Admin') {
?>
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item has-treeview menu-open">
            <a href="#" class="nav-link active">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>


            <ul class="nav nav-treeview">
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="nav-icon fas fa-copy"></i>
                  <p>
                    Produk
                    <i class="fas fa-angle-left right"></i>
                    <span class="badge badge-info right">5</span>
                  </p>
                </a>

                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="index.php?menu=kategori" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Jenis Produk</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=menu" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Produk</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=ongkir" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Ongkir</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=member" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Konsumen</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=admin" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>User</p>
                    </a>
                  </li>

                </ul>
              </li>


              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="nav-icon fas fa-copy"></i>
                  <p>
                    Air
                    <i class="fas fa-angle-left right"></i>
                    <span class="badge badge-info right">3</span>
                  </p>
                </a>

                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="index.php?menu=datafilter" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Data Filter</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=aktivitas_filter" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Aktivitas Filter</p>
                    </a>
                  </li>

                  <li class="nav-item">
                    <a href="index.php?menu=qttair" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Kualitas Air</p>
                    </a>
                  </li>

                </ul>
              </li>

              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="nav-icon fas fa-copy"></i>
                  <p>
                    Transaksi
                    <i class="fas fa-angle-left right"></i>
                    <span class="badge badge-info right">4</span>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="index.php?menu=info" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Info</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="index.php?menu=pemesanan" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Order</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="index.php?menu=pembayaran" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Pembayaran</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="index.php?menu=pengiriman" class="nav-link">
                      <i class="far fa-circle nav-icon"></i>
                      <p>Pengiriman</p>
                    </a>
                  </li>
                </ul>
              </li>

              
            </ul>
          </li>
        </ul>
      </nav>
      </div>
    </aside>

  <?php
  } else {
  ?>
  
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item has-treeview menu-open">
          <a href="#" class="nav-link active">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>
              Dashboard
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item has-treeview">
              <a href="index.php?menu=pengiriman" class="nav-link">
                <i class="far fa-circle nav-icon"></i>
                <p>Pengiriman</p>
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </nav>
    </div>
  </aside>

  <?php
  }
  ?>

  <div class="content-wrapper">
    <section class="content">
      <div class="container-fluid">
        <?php
        // Periksa apakah variabel $menu sudah disanitasi
        if (empty($menu)) {
            include "include/home.php";
            } elseif ($menu == 'datafilter') {
            include "data/datafilter.php";
            } elseif ($menu == 'aktivitasfilter') {
            include "data/aktivitasfilter.php";
            } elseif ($menu == 'qttair') {
            include "data/admin.php";

        } elseif ($menu == 'kategori') {
            include "data/kategori.php";
        } elseif ($menu == 'menu') {
            include "data/menu.php";
        } elseif ($menu == 'ongkir') {
            include "data/ongkir.php";
        } elseif ($menu == 'member') {
            include "data/member.php";
        } elseif ($menu == 'info') {
            include "data/info.php";
        } elseif ($menu == 'admin') {
            include "data/admin.php";
        } elseif ($menu == 'pemesanan') {
            include "transaksi/faktur_pesan.php";
        } elseif ($menu == 'isi_detail') {
            include "transaksi/isi_detail.php";
        } elseif ($menu == 'pembayaran') {
            include "transaksi/isi_bayar.php";
        } elseif ($menu == 'pengiriman') {
            include "transaksi/isi_kirim.php";
        } elseif ($menu == 'l_barang') {
            include "laporan/l_barang.php";
        } elseif ($menu == 'l_pesan') {
            include "laporan/l_pesan.php";
        } elseif ($menu == 'l_bayar') {
            include "laporan/l_bayar.php";
        } elseif ($menu == 'l_kirim') {
            include "laporan/l_kirim.php";
        } else {
            // Halaman tidak ditemukan
            echo "<p>Halaman tidak ditemukan.</p>";
        }
        ?>
      </div>
    </section>
  </div>
  <footer class="main-footer">
    <strong>Copyright &copy; 2021</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
    </div>
  </footer>

  <aside class="control-sidebar control-sidebar-dark">
    </aside>
  </div>
<?php
include "include/link2.php";
}
?>

</body>
</html>