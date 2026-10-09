<?php
$user_id_saat_ini = $_SESSION['id_user'];
$username_saat_ini = $_SESSION['user_login'];
$level_saat_ini = $_SESSION['level'];

// Nama menu untuk URL (jika form submit ke data.php)
$menuParam = 'pengaturan_akun';
?>

<style>
/* Anda bisa salin CSS modern dari index.php jika file ini tidak di-include
   oleh index.php, atau pastikan CSS global sudah mencakup styling form ini */
.form-section { padding: 25px; margin-bottom: 30px; background: #fdfdfd; border: 1px solid #e7eaf3; border-radius: 8px; max-width: 600px; margin-left: auto; margin-right: auto; }
.form-section h2 { text-align: center; font-size: 1.5em; margin-bottom: 25px; color: #34495e; font-weight: 600; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.95em; }
.form-group input[type="text"], .form-group input[type="password"] {
    width: 100%; padding: 12px 15px; border: 1px solid #dcdcdc; border-radius: 6px; box-sizing: border-box;
    font-size: 1em; transition: border-color 0.3s ease; background-color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.form-group input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2); }
.form-actions { margin-top: 30px; text-align: right; }
.form-actions .btn { margin-left: 10px; }
.user-info p { margin-bottom: 5px; font-size: 1.1em;}
.user-info strong { display: inline-block; width: 100px;} /* Rapikan label info */
.alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
.alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
.alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-10">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Pengaturan Akun</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Pengaturan Akun</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class='col-12'>
    <br>
    <div class="form-section">
        <h2>Ubah Password</h2>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert <?php echo $_SESSION['flash_type']; ?>" role="alert">
                <?php echo $_SESSION['flash_message']; ?>
            </div>
            <?php
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            ?>
        <?php endif; ?>

        <div class="user-info mb-4">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($username_saat_ini); ?></p>
            <p><strong>Level:</strong> <?php echo htmlspecialchars(ucfirst($level_saat_ini)); ?></p>
        </div>

        <form method="POST" action="./data.php?menu=<?php echo $menuParam; ?>&act=update_password" id="form-ubah-password">
             <input type="hidden" name="id_user" value="<?php echo $user_id_saat_ini; ?>">

            <div class="form-group">
                <label for="password_lama">Password Lama</label>
                <input type="password" id="password_lama" name="password_lama" required>
            </div>
            <div class="form-group">
                <label for="password_baru">Password Baru</label>
                <input type="password" id="password_baru" name="password_baru" required minlength="6">
                 <small>Minimal 6 karakter.</small>
            </div>
            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                <input type="password" id="konfirmasi_password" name="konfirmasi_password" required>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Kembali</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan Password</button>
            </div>
        </form>
    </div>
    <br><br>

    <script>
        document.getElementById('form-ubah-password').addEventListener('submit', function(event) {
            const passwordBaru = document.getElementById('password_baru').value;
            const konfirmasiPassword = document.getElementById('konfirmasi_password').value;

            if (passwordBaru !== konfirmasiPassword) {
                alert('Konfirmasi password baru tidak cocok!');
                event.preventDefault(); // Mencegah form dikirim
            }
            // Anda bisa tambahkan validasi panjang password di sini juga jika mau
             else if (passwordBaru.length < 6) {
                 alert('Password baru minimal harus 6 karakter!');
                 event.preventDefault();
             }
        });
    </script>

</div>