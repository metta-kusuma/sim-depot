<?php

$current_admin_name = $_SESSION['user_login'] ?? 'Pegawai';
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
                    <h2 style="font-size: 1.25rem; margin-bottom: 20px;">Laporan</h2>
                    <div style="margin-top: 10px;">
                        <a href="index.php?menu=laporan" class="btn btn-default" style="border: 1px solid #ccc; background-color: #f7f7f7;">Buka</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="card" style="text-align: center; border: 1px solid #ddd; padding: 20px;">
                    <h2 style="font-size: 1.25rem; margin-bottom: 20px;">Kualitas Air</h2>
                    <div style="margin-top: 10px;">
                        <a href="index.php?menu=aktivitasdata" class="btn btn-default" style="border: 1px solid #ccc; background-color: #f7f7f7;">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card" style="text-align: center; border: 1px solid #ddd; padding: 20px;">
                    <h2 style="font-size: 1.25rem; margin-bottom: 20px;">Laporan Dinkes</h2>
                    <div style="margin-top: 10px;">
                        <a href="index.php?menu=arsip_dinkes" class="btn btn-default" style="border: 1px solid #ccc; background-color: #f7f7f7;">Buka</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>