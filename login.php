<?php
// Ambil username dari URL (jika ada)
// Contoh: login_pelanggan.php?user=pg0001
$username_dari_url = '';
if (isset($_GET['user'])) {
    // Kita amankan dengan htmlspecialchars
    $username_dari_url = htmlspecialchars($_GET['user']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You Water</title>
    <link rel="stylesheet" href="cssku/style1.css">
    <link rel="stylesheet" href="cssku/asset1.css">

</head>

<body>
    <div class="form-container">
        <div class="form-col">
            <div class="btn-box">
                <button class="btn btn-1" id="login">Masuk</button>
            </div>

            <form class="form-box login-form" action="proses/proseslogin.php" method="post">
                <div class="form-title">
                    <span>Masuk</span>
                </div>
                <div class="form-inputs">
                    <div class="input-box">
                        <input type="text" class="inputs input-field" name="username_login" placeholder="Id Pelanggan" value="<?php echo $username_dari_url; ?>" require>
                        <ion-icon name="person-outline" class="icon"></ion-icon>
                    </div>
                    <div class="input-box">
                        <input type="password" oninput="changeIcon(this.value)" name="password" id="logPassword" class="inputs input-field" placeholder="Password" require>
                        <ion-icon name="lock-closed-outline" class="icon" id="log-pass-icon" onclick="myLogPassword()"></ion-icon>
                    </div>
                    <div class="input-box">
                        <button class="inputs submit-btn">
                            <span>Masuk</span>
                            <ion-icon name="arrow-forward-outline"></ion-icon>
                        </button>
                    </div>
                </div>
            </form>


        </div>
    </div>
    <div class="versionutamatext"> V 0.0.1</div>
    <script src="js/switch.js"> </script>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js">
        </script>
</body>

</html>