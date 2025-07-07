<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] !== 'kasir') {
    header('Location: ../../login.php');
    exit;
}

// =======================================================================
// LANGKAH 1: MENGHITUNG STOK TERKINI DARI LOG_STOK (LOGIKA ANDA)
// =======================================================================
$stok_terkini = [];
$query_stok = "
    SELECT id_produk, NULL as id_kategori_stok, SUM(jumlah_perubahan) as total FROM log_stok WHERE id_produk IS NOT NULL GROUP BY id_produk
    UNION ALL
    SELECT NULL as id_produk, id_kategori_stok, SUM(jumlah_perubahan) as total FROM log_stok WHERE id_kategori_stok IS NOT NULL GROUP BY id_kategori_stok
";
$result_stok = mysqli_query($koneksi, $query_stok);
while ($row_stok = mysqli_fetch_assoc($result_stok)) {
    if ($row_stok['id_produk']) {
        $stok_terkini['produk'][$row_stok['id_produk']] = (int)$row_stok['total'];
    } elseif ($row_stok['id_kategori_stok']) {
        $stok_terkini['kategori'][$row_stok['id_kategori_stok']] = (int)$row_stok['total'];
    }
}

// Mengambil data produk
$query_produk = "SELECT * FROM produk WHERE status_produk = 'aktif' ORDER BY FIELD(kategori, 'makanan', 'minuman'), nama_produk";
$result_produk = mysqli_query($koneksi, $query_produk);

// Mengelompokkan produk untuk Tampilan Tab
$produk_by_kategori = [];
$kategori_order = ['Kue Balok', 'Ketan Susu', 'Makanan Lain', 'Minuman'];
foreach ($kategori_order as $k) {
    $produk_by_kategori[$k] = [];
}

while ($row = mysqli_fetch_assoc($result_produk)) {
    $nama_lower = strtolower($row['nama_produk']);
    $kategori_produk_db = strtolower($row['kategori']);

    if (strpos($nama_lower, 'kue balok') !== false) {
        $produk_by_kategori['Kue Balok'][] = $row;
    } else if (strpos($nama_lower, 'ketan') !== false) {
        $produk_by_kategori['Ketan Susu'][] = $row;
    } else if ($kategori_produk_db == 'minuman') {
        $produk_by_kategori['Minuman'][] = $row;
    } else {
        $produk_by_kategori['Makanan Lain'][] = $row;
    }
}
$produk_by_kategori = array_filter($produk_by_kategori);

$pageTitle = "Input Pesanan Kasir";
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="../../css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .pos-product-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .pos-product-card:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .pos-product-card img {
            height: 100px;
            object-fit: cover;
        }

        /* Style untuk produk habis stok */
        .pos-product-card.out-of-stock {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pos-product-card.out-of-stock:hover {
            transform: none;
            box-shadow: none;
        }

        .cart-item-list {
            list-style: none;
            padding: 0;
            max-height: 35vh;
            overflow-y: auto;
        }

        .nav-tabs .nav-link {
            font-weight: bold;
        }
    </style>
</head>

<body class="sb-nav-fixed">
    <?php include "inc/navbar.php"; ?>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <?php include "inc/sidebar.php"; ?>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4 py-4">
                   
                    <?php
                    if (isset($_SESSION['notif'])) {
                        $notif = $_SESSION['notif'];
                        echo '<div class="alert alert-' . htmlspecialchars($notif['tipe']) . ' alert-dismissible fade show" role="alert">';
                        echo '<strong>' . htmlspecialchars(ucfirst($notif['tipe'])) . '!</strong> ' . $notif['pesan'];
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';
                        unset($_SESSION['notif']); // Hapus notifikasi setelah ditampilkan
                    }
                    ?>
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header p-0">
                                    <ul class="nav nav-tabs card-header-tabs" id="kategoriTab" role="tablist">
                                        <?php
                                        $first = true;
                                        foreach (array_keys($produk_by_kategori) as $kategori) {
                                            $id_tab = str_replace(' ', '-', strtolower($kategori));
                                            echo '<li class="nav-item" role="presentation"><button class="nav-link ' . ($first ? 'active' : '') . '" id="' . $id_tab . '-tab" data-bs-toggle="tab" data-bs-target="#' . $id_tab . '" type="button" role="tab">' . htmlspecialchars($kategori) . '</button></li>';
                                            $first = false;
                                        }
                                        ?>
                                    </ul>
                                </div>
                                <div class="card-body" style="max-height: 85vh; overflow-y: auto;">
                                    <div class="tab-content" id="kategoriTabContent">
                                        <?php
                                        $first = true;
                                        foreach ($produk_by_kategori as $kategori => $produks) {
                                            $id_tab = str_replace(' ', '-', strtolower($kategori));
                                        ?>
                                            <div class="tab-pane fade <?= ($first ? 'show active' : '') ?>" id="<?= $id_tab ?>" role="tabpanel">

                                                <?php if ($kategori == 'Kue Balok'): ?>
                                                    <div class="porsi-controls mb-3 border-bottom pb-3">
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-outline-success flex-fill btn-porsi-shortcut" data-porsi="setengah"><i class="fas fa-box-open me-2"></i>Tambah Setengah Porsi</button>
                                                            <button class="btn btn-success flex-fill btn-porsi-shortcut" data-porsi="satu"><i class="fas fa-box me-2"></i>Tambah Satu Porsi</button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="row g-3">
                                                    <?php foreach ($produks as $produk): ?>
                                                        <?php
                                                        // ======================================================
                                                        // LANGKAH 2: MENENTUKAN STOK & STATUS KARTU
                                                        // ======================================================
                                                        $stok_tampil = 0;
                                                        if (!empty($produk['id_kategori_stok'])) {
                                                            $stok_tampil = $stok_terkini['kategori'][$produk['id_kategori_stok']] ?? 0;
                                                        } else {
                                                            $stok_tampil = $stok_terkini['produk'][$produk['id_produk']] ?? 0;
                                                        }
                                                        $is_out_of_stock = $stok_tampil <= 0;
                                                        ?>
                                                        <div class="col-md-3 col-6">
                                                            <div class="card pos-product-card text-center h-100 <?= $is_out_of_stock ? 'out-of-stock' : '' ?>"
                                                                data-id="<?= htmlspecialchars($produk['id_produk']) ?>"
                                                                data-name="<?= htmlspecialchars($produk['nama_produk']) ?>"
                                                                data-price="<?= htmlspecialchars($produk['harga']) ?>"
                                                                data-kategori="<?= htmlspecialchars($kategori) ?>"
                                                                data-stock="<?= $stok_tampil ?>">
                                                                <img src="../../assets/img/produk/<?= htmlspecialchars($produk['poto_produk']) ?>" class="card-img-top" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
                                                                <div class="card-body p-2 d-flex flex-column">
                                                                    <h6 class="card-title small mb-1 flex-grow-1"><?= htmlspecialchars($produk['nama_produk']) ?></h6>
                                                                    <p class="card-text fw-bold">Rp <?= number_format($produk['harga']) ?></p>
                                                                    <?php if ($is_out_of_stock): ?>
                                                                        <span class="badge bg-danger mt-auto">Stok Habis</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary mt-auto">Stok: <?= $stok_tampil ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php
                                            $first = false;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <form id="orderForm" action="proses_pesanan_baru.php" method="POST">
                                <div class="card sticky-top" style="top: 20px;">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Pesanan</h5>
                                        <button type="button" class="btn btn-danger btn-sm" id="btn-clear-cart"><i class="fas fa-trash-alt me-1"></i> Kosongkan</button>
                                    </div>
                                    <div class="card-body">
                                        <ul class="cart-item-list" id="cart-items">
                                            <li class="text-center text-muted py-4">Keranjang masih kosong</li>
                                        </ul>
                                        <div class="mt-2 border-top pt-2">
                                            <div class="d-flex justify-content-between"><span>Subtotal</span><span id="cart-subtotal">Rp 0</span></div>
                                            <div class="d-flex justify-content-between text-danger"><span>Diskon</span><span id="cart-diskon">- Rp 0</span></div>
                                            <hr>
                                            <div class="d-flex justify-content-between h4 fw-bold"><span>TOTAL</span><span id="cart-total">Rp 0</span></div>
                                        </div>
                                        <hr>
                                        <div class="form-group mb-2"><label for="nama_pemesan" class="form-label small">Nama Pemesan</label><input type="text" class="form-control form-control-sm" id="nama_pemesan" name="nama_pemesan" placeholder="Walk-in Customer"></div>
                                        <div class="form-group mb-2"><label for="catatan" class="form-label small">Catatan</label><textarea class="form-control form-control-sm" name="catatan" id="catatan" rows="2"></textarea></div>
                                        <div class="row">
                                            <div class="col-6"><label class="form-label small">Jenis Pesanan</label><select class="form-select form-select-sm" name="jenis_pesanan">
                                                    <option value="dine_in">Dine In</option>
                                                    <option value="take_away" selected>Take Away</option>
                                                </select></div>
                                            <div class="col-6"><label class="form-label small">Pembayaran</label><select class="form-select form-select-sm" name="pembayaran">
                                                    <option value="tunai">Tunai</option>
                                                    <option value="qris">QRIS</option>
                                                </select></div>
                                        </div>
                                        <input type="hidden" name="cart_json" id="cart_json"><input type="hidden" name="total_harga" id="total_harga"><input type="hidden" name="total_diskon" id="total_diskon">
                                    </div>
                                    <div class="card-footer p-3">
                                        <div id="validation-message-area" class="text-danger small text-center mb-2 fw-bold"></div>
                                        <div class="d-grid"><button type="submit" class="btn btn-success btn-lg" id="btn-buat-pesanan" disabled><i class="fas fa-check-circle me-2"></i>Buat Pesanan</button></div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../../js/scripts.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // =================================================================================
            // BAGIAN 1: INISIALISASI VARIABEL & ELEMEN DOM
            // =================================================================================

            // State utama aplikasi
            let cart = {};

            // Elemen-elemen penting dari halaman
            const cartItemsEl = document.getElementById('cart-items');
            const cartSubtotalEl = document.getElementById('cart-subtotal');
            const cartDiskonEl = document.getElementById('cart-diskon');
            const cartTotalEl = document.getElementById('cart-total');
            const checkoutBtn = document.getElementById('btn-buat-pesanan');
            const validationMessageEl = document.getElementById('validation-message-area');

            const productCards = document.querySelectorAll('.pos-product-card');
            const porsiShortcutButtons = document.querySelectorAll('.btn-porsi-shortcut');
            const clearCartBtn = document.getElementById('btn-clear-cart');
            const orderForm = document.getElementById('orderForm');

            // Hidden input elements untuk dikirim ke PHP
            const cartJsonInput = document.getElementById('cart_json');
            const totalHargaInput = document.getElementById('total_harga');
            const totalDiskonInput = document.getElementById('total_diskon');

            // =================================================================================
            // BAGIAN 2: FUNGSI-FUNGSI UTAMA PENGELOLA KERANJANG (CART)
            // =================================================================================

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }).format(amount);
            }

            // Fungsi utama untuk menggambar ulang seluruh tampilan keranjang berdasarkan state 'cart'
            function updateCartView() {
                // Hitung ulang diskon otomatis berdasarkan item saat ini
                calculateAndApplyDiscounts();

                cartItemsEl.innerHTML = '';
                let subtotal = 0;
                let diskon = 0;

                if (Object.keys(cart).length === 0) {
                    cartItemsEl.innerHTML = '<li class="text-center text-muted py-4">Keranjang masih kosong</li>';
                } else {
                    // Urutkan item agar diskon selalu di bawah
                    const sortedCartIds = Object.keys(cart).sort((a, b) => {
                        if (cart[a].price < 0) return 1; // Pindahkan diskon ke akhir
                        if (cart[b].price < 0) return -1;
                        return 0;
                    });

                    for (const id of sortedCartIds) {
                        const item = cart[id];
                        const itemSubtotal = item.price * item.quantity;

                        if (item.price > 0) {
                            subtotal += itemSubtotal;
                        } else {
                            diskon += Math.abs(itemSubtotal);
                        }

                        const isDiscount = item.price < 0;
                        const itemHtml = `
                    <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <strong class="d-block">${item.name} ${item.quantity > 1 ? `(x${item.quantity})` : ''}</strong>
                            ${!isDiscount ? `<small class="text-muted">${formatRupiah(item.price)}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <strong class="d-block">${formatRupiah(itemSubtotal)}</strong>
                            ${!isDiscount ? `
                            <div class="d-flex align-items-center justify-content-end mt-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-qty btn-minus" data-id="${id}">-</button>
                                <span class="mx-2 fw-bold">${item.quantity}</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-qty btn-plus" data-id="${id}">+</button>
                            </div>` : ''}
                        </div>
                    </li>`;
                        cartItemsEl.innerHTML += itemHtml;
                    }
                }

                const total = subtotal - diskon;
                cartSubtotalEl.textContent = formatRupiah(subtotal);
                cartDiskonEl.textContent = `- ${formatRupiah(diskon)}`;
                cartTotalEl.textContent = formatRupiah(total);

                // Update hidden inputs untuk form submission
                cartJsonInput.value = JSON.stringify(cart);
                totalHargaInput.value = subtotal;
                totalDiskonInput.value = diskon;

                validateCartRules();
            }

            // Fungsi untuk menambah produk satuan ke keranjang (dengan kategori)
            function addToCart(id, name, price, kategori) {
                if (cart[id]) {
                    cart[id].quantity++;
                } else {
                    cart[id] = {
                        name,
                        price: parseFloat(price),
                        quantity: 1,
                        category: kategori
                    };
                }
                updateCartView();
            }

            // =================================================================================
            // BAGIAN 3: FUNGSI LOGIKA OTOMATIS (VERSI FINAL)
            // =================================================================================

            function calculateAndApplyDiscounts() {
                // Hitung dulu total Kue Balok (yang bukan item diskon)
                let kueBalokCount = 0;
                for (const id in cart) {
                    const item = cart[id];
                    // Pastikan item ada, punya kategori, dan bukan diskon
                    if (item && item.category === 'Kue Balok' && item.price > 0) {
                        kueBalokCount += item.quantity;
                    }
                }

                // Selalu hapus diskon lama sebelum menghitung ulang agar tidak duplikat
                delete cart['DISKON_SATU'];
                delete cart['DISKON_SETENGAH'];

                if (kueBalokCount === 0) return; // Jika tidak ada kue balok, hentikan fungsi

                // ATURAN 1: HANYA JIKA TEPAT 5 BUAH
                if (kueBalokCount === 5) {
                    cart['DISKON_SETENGAH'] = {
                        name: 'Diskon Setengah Porsi',
                        price: -2000,
                        quantity: 1,
                        category: 'Diskon'
                    };
                }
                // ATURAN 2: JIKA 10 ATAU LEBIH
                else if (kueBalokCount >= 10) {
                    const jumlahSatuPorsi = Math.floor(kueBalokCount / 10);
                    if (jumlahSatuPorsi > 0) {
                        cart['DISKON_SATU'] = {
                            name: 'Diskon Satu Porsi',
                            price: -5000,
                            quantity: jumlahSatuPorsi,
                            category: 'Diskon'
                        };
                    }
                }
            }

            function validateCartRules() {
                let kueBalokCount = 0;
                let containsOtherItems = false;
                validationMessageEl.textContent = '';

                for (const id in cart) {
                    const item = cart[id];
                    if (item.price > 0) { // Hanya hitung produk, bukan diskon
                        if (item.category === 'Kue Balok') {
                            kueBalokCount += item.quantity;
                        } else {
                            containsOtherItems = true;
                        }
                    }
                }

                let isCartValid = true;
                // Aturan Validasi: Minimal 5 Kue Balok jika hanya membeli Kue Balok
                if (!containsOtherItems && kueBalokCount > 0 && kueBalokCount < 5) {
                    isCartValid = false;
                    validationMessageEl.textContent = 'Pembelian Kue Balok saja minimal 5 pcs.';
                }

                checkoutBtn.disabled = Object.keys(cart).length === 0 || !isCartValid;
            }

            // =================================================================================
            // BAGIAN 4: EVENT LISTENERS
            // =================================================================================

            // Klik kartu produk untuk menambah ke keranjang
            productCards.forEach(card => {
                card.addEventListener('click', () => {
                    const {
                        id,
                        name,
                        price,
                        kategori
                    } = card.dataset;
                    addToCart(id, name, price, kategori);
                });
            });

            // Klik tombol shortcut porsi
            porsiShortcutButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const type = btn.dataset.porsi;
                    const qtyToAdd = (type === 'satu') ? 10 : 5;
                    // Cari produk Kue Balok pertama sebagai default untuk ditambahkan
                    const defaultKueBalokCard = document.querySelector('.pos-product-card[data-kategori="Kue Balok"]');

                    if (!defaultKueBalokCard) {
                        alert('Tidak ada produk Kue Balok untuk ditambahkan.');
                        return;
                    }
                    const {
                        id,
                        name,
                        price,
                        kategori
                    } = defaultKueBalokCard.dataset;

                    // Tambahkan ke keranjang sebanyak qtyToAdd
                    for (let i = 0; i < qtyToAdd; i++) {
                        if (cart[id]) {
                            cart[id].quantity++;
                        } else {
                            cart[id] = {
                                name,
                                price: parseFloat(price),
                                quantity: 1,
                                category: kategori
                            };
                        }
                    }
                    updateCartView();
                });
            });

            // Klik tombol di dalam keranjang (+, -)
            cartItemsEl.addEventListener('click', e => {
                const id = e.target.dataset.id;
                if (!id || !cart[id] || cart[id].price < 0) return; // Abaikan jika item diskon

                if (e.target.classList.contains('btn-plus')) {
                    cart[id].quantity++;
                } else if (e.target.classList.contains('btn-minus')) {
                    cart[id].quantity--;
                    if (cart[id].quantity <= 0) {
                        delete cart[id];
                    }
                }
                updateCartView();
            });

            // Klik tombol kosongkan keranjang
            clearCartBtn.addEventListener('click', () => {
                if (Object.keys(cart).length > 0 && confirm('Anda yakin ingin mengosongkan seluruh pesanan?')) {
                    cart = {};
                    updateCartView();
                }
            });

            // Menyiapkan data sebelum form di-submit
            orderForm.addEventListener('submit', function(e) {
                if (checkoutBtn.disabled) {
                    e.preventDefault();
                    alert('Pesanan tidak dapat diproses. Harap periksa kembali keranjang Anda.');
                }
            });

            // Panggil pertama kali untuk inisialisasi
            updateCartView();
        });
    </script>

</body>

</html>