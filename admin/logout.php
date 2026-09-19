<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html>

<head>
	<title>Logout</title>
</head>
<style>
	.logout-container {
		text-align: center;
		margin-top: 50px;
		font-family: sans-serif;
	}

	.spinner {
		border: 8px solid #f3f3f3;
		border-top: 8px solid #3498db;
		border-radius: 50%;
		width: 50px;
		height: 50px;
		animation: spin 1s linear infinite;
		margin: 20px auto;
	}

	@keyframes spin {
		0% {
			transform: rotate(0deg);
		}

		100% {
			transform: rotate(360deg);
		}
	}
</style>

<body>
	<div class="logout-container">
		<h1>Anda telah berhasil logout.</h1>
		<p>Mengalihkan ke halaman login...</p>
		<div class="spinner"></div>
	</div>
</body>

</html>

<script>
	setTimeout(function() {
		window.location.href = 'login.php';
	}, 2000); // Mengalihkan setelah 2 detik
</script>