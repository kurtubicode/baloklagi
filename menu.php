<?php
$page_title = "Menu";
include 'backend/koneksi.php'; // Pastikan path ini benar

// 1. Ambil data Paket yang aktif beserta rinciannya
$query_paket = "SELECT * FROM paket WHERE status_paket = 'aktif' ORDER BY id_paket ASC";
$result_paket = mysqli_query($koneksi, $query_paket);
$pakets = [];
if ($result_paket) {
    while ($row = mysqli_fetch_assoc($result_paket)) {
        $id_paket = $row['id_paket'];
        $query_detail = "SELECT dp.jumlah, p.nama_produk FROM detail_paket dp JOIN produk p ON dp.id_produk = p.id_produk WHERE dp.id_paket = '$id_paket'";
        $result_detail = mysqli_query($koneksi, $query_detail);
        $isi_paket = [];
        while ($item = mysqli_fetch_assoc($result_detail)) {
            $isi_paket[] = $item['jumlah'] . 'x ' . $item['nama_produk'];
        }
        $row['rincian_teks'] = implode(', ', $isi_paket);
        $pakets[] = $row;
    }
}


// 2. Ambil data Produk aktif dan kelompokkan berdasarkan kategori
$query_produk = "SELECT * FROM produk WHERE status_produk = 'aktif' ORDER BY kategori, nama_produk";
$result_produk = mysqli_query($koneksi, $query_produk);
$produks_by_kategori = [];
if ($result_produk) {
    while ($row = mysqli_fetch_assoc($result_produk)) {
        $produks_by_kategori[$row['kategori']][] = $row;
    }
}
?>

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="backend/assets/img/logo-kuebalok.png">

    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Kue Balok Mang Wiro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,300;0,400;0,700;1,700&display=swap"
        rel="stylesheet" />

    <script src="https://unpkg.com/feather-icons"></script>


    <link rel="stylesheet" href="assets/css/style1.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Import Font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

        /* Reset & Pengaturan Dasar */
        :root {
            --primary: #2e4358;
            --bg: #f4f7f6;
            --text-dark: #333;
            --text-light: #666;
            --accent: #27ae60;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            text-decoration: none;
            border: none;
            outline: none;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--text-dark);
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Beri jarak atas agar konten tidak tertutup navbar */
        .main-content-page {
            padding-top: 8rem;
            padding-bottom: 4rem;
        }

        /* Judul Section (Paket & Menu Satuan) */
        .section-title {
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
            display: inline-block;
        }

        .category-section {
            margin-bottom: 4rem;
            text-align: center;
            /* Agar judul section bisa di tengah */
        }

        /* Judul Kategori (Makanan, Minuman) */
        .menu-category h3 {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            text-align: left;
        }

        /* Info Promo Porsi */
        .promo-porsi-info {
            background-color: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1rem;
            color: #0050b3;
        }

        /* Grid untuk Kartu Menu/Paket */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        /* Desain Kartu (berlaku untuk produk dan paket) */
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-align: left;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .card-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .card-content h3,
        .card-content h4 {
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-content .paket-isi {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 15px;
            flex-grow: 1;
            /* Mendorong harga dan tombol ke bawah */
        }

        .price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .add-to-cart-btn {
            margin-top: auto;
            /* Selalu di bagian bawah kartu */
        }

        .add-to-cart-btn .btn {
            width: 100%;
            padding: 0.8rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            background-color: transparent;
            border: 2px solid var(--primary);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-to-cart-btn .btn:hover {
            background-color: var(--primary);
            color: white;
        }

        .add-to-cart-btn .btn i {
            margin-right: 8px;
        }

        /* Menambahkan sisa style dari file Anda */
        /* Pastikan ini tidak bertentangan dengan style baru di atas */
        <?php include 'style1.css'; ?>
    </style>
</head>

<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-logo">
            <img src="assets/img/logo.png" alt="LOGO KUE BALOK MANG WIRO" style="width: 50px;" />
        </a>
        <div class="navbar-nav">
            <a href="index.php#home">Beranda</a>
            <a href="index.php#about">Tentang Kami</a>
            <a href="menu.php">Menu</a>
            <a href="lacak.php">Lacak Pesanan</a>
            <a href="index.php#faq">FAQ</a>
            <a href="index.php#contact">Kontak</a>
        </div>

        <div class="navbar-extra">
            <a href="keranjang.php" id="shopping-cart-button">
                <i data-feather="shopping-cart"></i>
                <span class="cart-item-count" style="display:none;">0</span>
            </a>
            <a href="#" id="hamburger-menu"><i data-feather="menu"></i></a>
        </div>

        <div class="search-form">
            <input type="search" id="search-box" placeholder="Cari menu...">
            <label for="search-box"><i data-feather="search"></i></label>
        </div>
    </nav>
    <main class="main-content-page">
        <div class="container">

            <?php if (!empty($pakets)): ?>
                <section class="category-section">
                    <h2 class="section-title">Paket Spesial</h2>
                    <div class="menu-grid">
                        <?php foreach ($pakets as $paket): ?>
                            <div class="card paket-card">
                                <img src="backend/assets/img/paket/<?= htmlspecialchars($paket['poto_paket']) ?>" alt="<?= htmlspecialchars($paket['nama_paket']) ?>">
                                <div class="card-content">
                                    <h3><?= htmlspecialchars($paket['nama_paket']) ?></h3>
                                    <p class="paket-isi">Isi: <?= htmlspecialchars($paket['rincian_teks']) ?>.</p>
                                    <div class="price">Rp <?= number_format($paket['harga_paket'], 0, ',', '.') ?></div>
                                    <div class="add-to-cart-btn">
                                        <button class="btn add-paket-to-cart" data-id="<?= htmlspecialchars($paket['id_paket']) ?>">
                                            <i class="fas fa-shopping-cart"></i> Tambah
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="category-section">
                <h2 class="section-title">Menu Satuan</h2>
                <?php foreach ($produks_by_kategori as $kategori => $produks): ?>
                    <div class="menu-category">
                        <h3><?= htmlspecialchars(ucfirst($kategori)) ?></h3>

                        <?php if ($kategori === 'makanan' && !empty(array_filter($produks, function ($p) {
                            return strpos(strtolower($p['nama_produk']), 'kue balok') !== false;
                        }))): ?>
                            <div class="promo-porsi-info">
                                <p>✨ **Promo Spesial Kue Balok:** Beli <strong>5 pcs</strong> dapat diskon Rp 2.000, beli <strong>10 pcs</strong> dapat diskon Rp 5.000! (Berlaku kelipatan untuk 10 pcs)</p>
                            </div>
                        <?php endif; ?>

                        <div class="menu-grid">
                            <?php foreach ($produks as $produk): ?>
                                <div class="card product-card">
                                    <img src="backend/assets/img/produk/<?= htmlspecialchars($produk['poto_produk']) ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
                                    <div class="card-content">
                                        <h4><?= htmlspecialchars($produk['nama_produk']) ?></h4>
                                        <div class="price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></div>
                                        <div class="add-to-cart-btn">
                                            <button class="btn add-product-to-cart" data-id="<?= htmlspecialchars($produk['id_produk']) ?>">
                                                <i class="fas fa-shopping-cart"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </main>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter kategori
            const filterBtns = document.querySelectorAll('.filter-btn');
            const menuCards = document.querySelectorAll('.apple-card');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => b.style.background = '#fff', b => b.style.color = 'var(--primary)');
                    this.style.background = 'var(--primary)';
                    this.style.color = '#fff';
                    const filter = this.dataset.filter;
                    menuCards.forEach(card => {
                        if (filter === 'all' || card.dataset.kategori === filter) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            // Search bar
            const searchBar = document.getElementById('menu-search-bar');
            searchBar.addEventListener('input', function() {
                const keyword = this.value.trim().toLowerCase();
                menuCards.forEach(card => {
                    const nama = card.dataset.nama;
                    if (nama.includes(keyword)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            if (document.querySelector('.apple-card')) {
                const addToCartButtons = document.querySelectorAll('.apple-cart-btn');
                addToCartButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const product = {
                            id: this.dataset.id,
                            nama: this.dataset.nama,
                            harga: parseInt(this.dataset.harga)
                        };
                        addToCart(product);
                    });
                });
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>