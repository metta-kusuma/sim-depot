<?php
// Password yang diketik pengguna (misal dari form registrasi)
$password_dari_form = '123'; 

// Membuat hash password
// PASSWORD_DEFAULT adalah pilihan terbaik, PHP akan otomatis memilih algoritma terkuat
$hash_untuk_database = password_hash($password_dari_form, PASSWORD_DEFAULT);

// Outputnya akan seperti ini (setiap kali dijalankan akan berbeda, tapi tetap valid):
// $2y$10$abcdefghijklmnopqrstuvwx/abcdefghijklmnopqrstuvwxy.abcdefghijk
echo $hash_untuk_database; 

// --- Simpan $hash_untuk_database ini ke kolom password di database Anda ---
// Pastikan kolom password di database Anda cukup panjang,
// VARCHAR(255) biasanya sudah lebih dari cukup.
?>