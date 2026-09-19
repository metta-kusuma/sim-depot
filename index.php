
<?php
// Include database connection
include 'config/koneksi.php';

// Function to get test results
function getTestResults($conn, $jenis_pengecekan) {
    $sql = "SELECT * FROM data_aktivitas WHERE jenis_pengecekan = ? ORDER BY tgl_aktivitas DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $jenis_pengecekan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get pH and TDS test results
$phResults = getTestResults($conn, 'Tes PH');
$tdsResults = getTestResults($conn, 'TDS');

// Get Galon products from database (exclude id_produk = 7)
$sqlProduk = "SELECT id_produk, nama_produk, stock, harga FROM produk WHERE jenis_produk = 'Galon' AND id_produk != 7 ORDER BY harga ASC";
$resultProduk = $conn->query($sqlProduk);
$produkGalon = [];
if ($resultProduk->num_rows > 0) {
    while($row = $resultProduk->fetch_assoc()) {
        $produkGalon[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You Water - Layanan Air Bersih Berkualitas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        .water-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .wave-animation {
            animation: wave 3s ease-in-out infinite;
        }

        @keyframes wave {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .test-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .ph-gradient {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .tds-gradient {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="water-gradient text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold">Thank You Water</h1>
                </div>
                <nav class="hidden md:flex space-x-6 items-center">
                    <a href="#home" class="hover:text-blue-200 transition duration-300">Beranda</a>
                    <a href="#services" class="hover:text-blue-200 transition duration-300">Layanan</a>
                    <a href="#testing" class="hover:text-blue-200 transition duration-300">Hasil Pengujian</a>
                    <a href="#pricing" class="hover:text-blue-200 transition duration-300">Harga</a>
                    <a href="#contact" class="hover:text-blue-200 transition duration-300">Kontak</a>
                    <a href="login.php" class="bg-white text-blue-600 px-4 py-2 rounded-full font-semibold hover:bg-blue-50 transition duration-300 shadow-lg">
                        Login
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="water-gradient text-white py-20">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-5xl font-bold mb-6">Air Bersih Berkualitas untuk Keluarga Anda</h2>
            <p class="text-xl mb-8 max-w-2xl mx-auto">Menyediakan air galon berkualitas tinggi dengan pelayanan terpercaya dan harga terjangkau untuk kebutuhan sehari-hari Anda</p>
            <button onclick="scrollToSection('contact')" class="bg-white text-blue-600 px-8 py-3 rounded-full font-semibold hover:bg-blue-50 transition duration-300 shadow-lg">
                Pesan Sekarang
            </button>
            <p class="text-lg mt-8 mb-4">Sudah Punya Akun?</p>
            <a href="login.php" class="inline-block bg-white text-blue-600 px-6 py-2 rounded-full font-semibold hover:bg-blue-50 transition duration-300 shadow-lg">
                Login
            </a>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Layanan Kami</h3>
                <p class="text-gray-600 text-lg">Komitmen kami untuk memberikan yang terbaik</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition duration-300 bg-white">
                    <div class="text-4xl mb-4">🚚</div>
                    <h4 class="text-xl font-semibold text-gray-800 mb-3">Pengiriman Cepat</h4>
                    <p class="text-gray-600">Pengiriman air galon langsung ke rumah Anda dengan layanan yang cepat dan tepat waktu</p>
                </div>

                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition duration-300 bg-white">
                    <div class="text-4xl mb-4">✨</div>
                    <h4 class="text-xl font-semibold text-gray-800 mb-3">Kualitas Terjamin</h4>
                    <p class="text-gray-600">Air yang telah melalui proses penyaringan dan sterilisasi dengan standar kesehatan tinggi</p>
                </div>

                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition duration-300 bg-white">
                    <div class="text-4xl mb-4">💰</div>
                    <h4 class="text-xl font-semibold text-gray-800 mb-3">Harga Terjangkau</h4>
                    <p class="text-gray-600">Harga kompetitif dengan berbagai pilihan paket yang sesuai dengan kebutuhan Anda</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testing Results Section -->
    <section id="testing" class="py-16 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Hasil Pengujian Kualitas Air</h3>
                <p class="text-gray-600 text-lg">Kami melakukan pengujian rutin untuk memastikan kualitas air terbaik</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <!-- pH Test Results -->
                <div class="ph-gradient rounded-xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-2xl font-bold mb-2">Pengujian pH</h4>
                                <p class="text-blue-100">Tingkat Keasaman Air</p>
                            </div>
                            <div class="text-5xl">🧪</div>
                        </div>
                    </div>
                    <div class="p-6 bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">tgl_aktivitas</th>
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">hasil pH</th>
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="phTestResults">
                                    <?php if (empty($phResults)): ?>
                                        <tr class="border-b border-gray-100">
                                            <td colspan="3" class="text-center py-8 text-gray-500">
                                                <div class="text-4xl mb-2">📋</div>
                                                Belum ada data pengujian
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($phResults as $row): 
                                            $hasil = $row['hasil'];
                                            $status = ($hasil >= 6.5 && $hasil <= 8.5) ? 'Baik' : 'Tidak Baik';
                                            $statusClass = ($status === 'Baik') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                        ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                <td class="py-3 px-4 text-gray-700"><?php echo date('d/m/Y', strtotime($row['tgl_aktivitas'])); ?></td>
                                                <td class="py-3 px-4 text-gray-700 font-semibold"><?php echo $hasil; ?></td>
                                                <td class="py-3 px-4">
                                                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $statusClass; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TDS Test Results -->
                <div class="tds-gradient rounded-xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-500 to-pink-600 text-white p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-2xl font-bold mb-2">Pengujian TDS</h4>
                                <p class="text-orange-100">Total Dissolved Solids</p>
                            </div>
                            <div class="text-5xl">🔬</div>
                        </div>
                    </div>
                    <div class="p-6 bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b-2 border-gray-200">
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">tgl_aktivitas</th>
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">hasil TDS</th>
                                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="tdsTestResults">
                                    <?php if (empty($tdsResults)): ?>
                                        <tr class="border-b border-gray-100">
                                            <td colspan="3" class="text-center py-8 text-gray-500">
                                                <div class="text-4xl mb-2">📋</div>
                                                Belum ada data pengujian
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tdsResults as $row): 
                                            $hasil = $row['hasil'];
                                            if ($hasil <= 500) {
                                                $status = 'Sangat Baik';
                                                $statusClass = 'bg-green-100 text-green-700';
                                            } elseif ($hasil <= 1000) {
                                                $status = 'Baik';
                                                $statusClass = 'bg-blue-100 text-blue-700';
                                            } else {
                                                $status = 'Tidak Baik';
                                                $statusClass = 'bg-red-100 text-red-700';
                                            }
                                        ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                <td class="py-3 px-4 text-gray-700"><?php echo date('d/m/Y', strtotime($row['tgl_aktivitas'])); ?></td>
                                                <td class="py-3 px-4 text-gray-700 font-semibold"><?php echo $hasil; ?> ppm</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $statusClass; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <div class="inline-block bg-blue-50 rounded-lg p-6 max-w-2xl">
                    <p class="text-sm text-gray-600">
                        <strong>Standar pH:</strong> 6.5 - 8.5 (SNI) | 
                        <strong>Standar TDS:</strong> 0 - 500 ppm (Sangat Baik)
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Harga Terjangkau</h3>
                <p class="text-gray-600 text-lg">Pilih paket yang sesuai dengan kebutuhan Anda</p>
            </div>

            <?php if (empty($produkGalon)): ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">💧</div>
                    <p class="text-gray-600 text-lg">Produk galon belum tersedia</p>
                </div>
             <?php else: ?>
                <div class="grid md:grid-cols-<?php echo min(count($produkGalon), 3); ?> gap-8 max-w-5xl mx-auto">
                    <?php 
                    $index = 0;
                    foreach ($produkGalon as $produk): 
                        $isPopular = ($index == 0); // Produk pertama (harga terendah) jadi populer
                        $borderClass = $isPopular ? 'border-2 border-blue-600 shadow-xl transform scale-105' : 'border-2 border-gray-200 hover:border-blue-500 transition duration-300';
                    ?>
                        <div class="<?php echo $borderClass; ?> rounded-lg p-8">
                            <?php if ($isPopular): ?>
                                <div class="bg-blue-600 text-white text-sm font-semibold px-3 py-1 rounded-full inline-block mb-4">Populer</div>
                            <?php endif; ?>
                            
                            <h4 class="text-2xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($produk['nama_produk']); ?></h4>
                            <div class="text-4xl font-bold text-blue-600 mb-6">
                                Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
                                <span class="text-lg text-gray-600">/galon</span>
                            </div>
                            
                            <ul class="space-y-3 mb-8">
                                <li class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600">Air berkualitas premium</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600">Pengiriman gratis</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600">Stock tersedia: <?php echo $produk['stock']; ?> galon</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600">Barang kualitas terjamin</span>
                                </li>
                            </ul>
                            
                            <?php if ($produk['stock'] >0): ?>
                                <button onclick="scrollToSection('contact')" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-300">
                                    Pilih Paket
                                </button>
                            <?php else: ?>
                                <button disabled class="w-full bg-gray-400 text-white py-3 rounded-lg font-semibold cursor-not-allowed">
                                    Stock Habis
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php 
                        $index++;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-12">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Hubungi Kami</h3>
                <p class="text-gray-600 text-lg">Siap melayani kebutuhan air bersih Anda</p>
            </div>

            <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-xl p-8">
                <form id="contactForm">
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2" for="name">Nama Lengkap</label>
                        <input type="text" id="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" placeholder="Masukkan nama Anda" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2" for="phone">Nomor Telepon</label>
                        <input type="tel" id="phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" placeholder="08xxxxxxxxxx" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2" for="address">Alamat Lengkap</label>
                        <textarea id="address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" placeholder="Masukkan alamat lengkap Anda" required></textarea>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2" for="quantity">Jumlah Galon</label>
                        <input type="number" id="quantity" min="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" placeholder="Jumlah galon" required>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-300">
                        Kirim Pesanan
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="water-gradient text-white py-8">
        <div class="container mx-auto px-6 text-center">
            <div class="text-4xl mb-4">💧</div>
            <h4 class="text-2xl font-bold mb-2">Thank You Water</h4>
            <p class="mb-4">Menyediakan air bersih berkualitas untuk keluarga Indonesia</p>
            <p class="text-blue-200 text-sm">&copy; 2024 Thank You Water. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function scrollToSection(sectionId) {
            document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
        }

        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Tampilkan pesan sukses dengan modal sederhana
            const messageDiv = document.createElement('div');
            messageDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-xl z-50';
            messageDiv.innerHTML = '<strong>Terima kasih!</strong> Pesanan Anda akan segera kami proses.';
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 3000);
            
            this.reset();
        });
    </script>
</body>

</html>