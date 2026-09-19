<?php

$current_admin_name = $_SESSION['nama'] ?? 'Pegawai';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark" style="font-size: 1.5rem;">Selamat Datang, <?= $_SESSION['user_login'] ?></h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">

            <div class="col-lg-3 col-6">
                <div class="card" style="text-align: center; border: 1px solid #ddd; padding: 20px;">
                    <h2 style="font-size: 1.25rem; margin-bottom: 20px;">Pesanan Tersedia</h2>
                    <div style="margin-top: 10px;">
                        <a href="index.php?menu=pengantaran_anggota" class="btn btn-default" style="border: 1px solid #ccc; background-color: #f7f7f7;">Buka</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="card" style="text-align: center; border: 1px solid #ddd; padding: 20px;">
                    <h2 style="font-size: 1.25rem; margin-bottom: 20px;">Pengantaran Saya</h2>
                    <div style="margin-top: 10px;">
                        <a href="index.php?menu=pengantaran_saya" class="btn btn-default" style="border: 1px solid #ccc; background-color: #f7f7f7;">Buka</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>