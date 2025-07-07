<?php
session_start();
include '../../koneksi.php';

// 1. OTENTIKASI & OTORISASI
if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] !== 'kasir') {
    $_SESSION['notif'] = ['tipe' => 'danger', 'pesan' => 'Akses ditolak.'];
    header('Location: ../../login.php');
    exit;
}

// 2. Pastikan request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. Ambil dan amankan data dari form
    $id_karyawan = $_SESSION['user']['id_karyawan']; 
    $nama_pemesan = !empty($_POST['nama_pemesan']) ? mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']) : 'Walk-in Customer';
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    $jenis_pesanan = mysqli_real_escape_string($koneksi, $_POST['jenis_pesanan']);
    $metode_pembayaran = mysqli_real_escape_string($koneksi, $_POST['pembayaran']);

    $total_harga = filter_var($_POST['total_harga'], FILTER_VALIDATE_FLOAT);
    $total_diskon = filter_var($_POST['total_diskon'], FILTER_VALIDATE_FLOAT);
    $cart_json = $_POST['cart_json'];
    $cart = json_decode($cart_json, true);

    // Validasi data
    if (json_last_error() !== JSON_ERROR_NONE || empty($cart)) {
        $_SESSION['notif'] = ['tipe' => 'danger', 'pesan' => 'Data keranjang tidak valid atau kosong.'];
        header('Location: pesanan_input.php');
        exit;
    }

    // =======================================================================
    // BAGIAN INTI: PROSES KE DATABASE DENGAN TRANSAKSI
    // =======================================================================
    mysqli_begin_transaction($koneksi);

    try {
        // === LANGKAH A: VALIDASI STOK ULANG DI SISI SERVER (PENTING!) ===
        $produk_info_map = []; // Untuk menyimpan info produk yang sudah divalidasi
        
        // Siapkan statement untuk efisiensi
        $stmt_produk_info = mysqli_prepare($koneksi, "SELECT id_produk, nama_produk, id_kategori_stok FROM produk WHERE id_produk = ? AND status_produk = 'aktif'");
        $stmt_cek_stok_kategori = mysqli_prepare($koneksi, "SELECT SUM(jumlah_perubahan) as total FROM log_stok WHERE id_kategori_stok = ?");
        $stmt_cek_stok_individu = mysqli_prepare($koneksi, "SELECT SUM(jumlah_perubahan) as total FROM log_stok WHERE id_produk = ?");

        foreach ($cart as $id => $item) {
            if ($item['price'] <= 0) continue; // Lewati item diskon

            mysqli_stmt_bind_param($stmt_produk_info, "s", $id);
            mysqli_stmt_execute($stmt_produk_info);
            $produk_db = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_produk_info));

            if (!$produk_db) {
                throw new Exception("Produk '{$item['name']}' tidak tersedia atau tidak aktif.");
            }

            $stok_saat_ini = 0;
            if (!empty($produk_db['id_kategori_stok'])) {
                // Cek stok berdasarkan kategori
                mysqli_stmt_bind_param($stmt_cek_stok_kategori, "s", $produk_db['id_kategori_stok']);
                mysqli_stmt_execute($stmt_cek_stok_kategori);
                $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_kategori))['total'];
            } else {
                // Cek stok berdasarkan produk individu
                mysqli_stmt_bind_param($stmt_cek_stok_individu, "s", $id);
                mysqli_stmt_execute($stmt_cek_stok_individu);
                $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_individu))['total'];
            }

            if ($stok_saat_ini < $item['quantity']) {
                throw new Exception("Stok untuk produk '{$item['name']}' tidak mencukupi (sisa: {$stok_saat_ini}).");
            }
            $produk_info_map[$id] = $produk_db; // Simpan info produk jika valid
        }
        
        // === LANGKAH B: TENTUKAN STATUS PESANAN AWAL ===
        $sql_beban = "SELECT COUNT(id_pesanan) AS antrean FROM pesanan WHERE status_pesanan IN ('pending', 'diproses')";
        $result_beban = mysqli_query($koneksi, $sql_beban);
        $beban_dapur = mysqli_fetch_assoc($result_beban)['antrean'] ?? 0;
        $status_awal = ($beban_dapur < 20) ? 'diproses' : 'pending';

        // === LANGKAH C: INSERT DATA KE TABEL `pesanan` ===
        $id_pesanan_baru = "KSR-" . date("YmdHis");
        $tgl_pesanan = date("Y-m-d H:i:s");
        
        $query_pesanan = "INSERT INTO pesanan (id_pesanan, id_karyawan, tipe_pesanan, jenis_pesanan, nama_pemesan, tgl_pesanan, total_harga, total_diskon, metode_pembayaran, status_pesanan, catatan) 
                          VALUES (?, ?, 'kasir', ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
        mysqli_stmt_bind_param($stmt_pesanan, "sisssddsss", $id_pesanan_baru, $id_karyawan, $jenis_pesanan, $nama_pemesan, $tgl_pesanan, $total_harga, $total_diskon, $metode_pembayaran, $status_awal, $catatan);
        mysqli_stmt_execute($stmt_pesanan);
        mysqli_stmt_close($stmt_pesanan);

        // === LANGKAH D: INSERT DETAIL & CATAT PENGURANGAN STOK ===
        $stmt_detail = mysqli_prepare($koneksi, "INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_saat_transaksi, sub_total) VALUES (?, ?, ?, ?, ?)");
        $stmt_log = mysqli_prepare($koneksi, "INSERT INTO log_stok (id_produk, id_kategori_stok, jumlah_perubahan, jenis_aksi, id_pesanan, keterangan) VALUES (?, ?, ?, 'penjualan', ?, 'Penjualan Kasir')");

        foreach ($cart as $id => $item) {
            if ($item['price'] <= 0) continue; // Lewati lagi item diskon

            // 1. Insert ke detail_pesanan
            $sub_total_item = $item['price'] * $item['quantity'];
            mysqli_stmt_bind_param($stmt_detail, "ssidd", $id_pesanan_baru, $id, $item['quantity'], $item['price'], $sub_total_item);
            mysqli_stmt_execute($stmt_detail);
            
            // 2. Catat pengurangan stok di log_stok
            $id_produk_log = null;
            $id_kategori_log = null;
            $jumlah_pengurangan = -1 * abs($item['quantity']); // Pastikan nilainya negatif
            
            $info_produk = $produk_info_map[$id];
            if (!empty($info_produk['id_kategori_stok'])) {
                $id_kategori_log = $info_produk['id_kategori_stok'];
            } else {
                $id_produk_log = $id;
            }
            mysqli_stmt_bind_param($stmt_log, "ssis", $id_produk_log, $id_kategori_log, $jumlah_pengurangan, $id_pesanan_baru);
            mysqli_stmt_execute($stmt_log);
        }
        mysqli_stmt_close($stmt_detail);
        mysqli_stmt_close($stmt_log);

        // === LANGKAH E: COMMIT & SIAPKAN NOTIFIKASI SUKSES ===
        mysqli_commit($koneksi);

        $pesan_sukses = "Pesanan berhasil dibuat dengan ID: <strong>$id_pesanan_baru</strong>.";
        $pesan_sukses .= " <a href='cetak_struk.php?id=$id_pesanan_baru' target='_blank' class='btn btn-sm btn-info fw-bold'><i class='fas fa-print'></i> Cetak Struk</a>";
        $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => $pesan_sukses];
        
    } catch (Exception $e) {
        // === LANGKAH F: JIKA GAGAL, ROLLBACK & SIAPKAN NOTIFIKASI ERROR ===
        mysqli_rollback($koneksi);
        $_SESSION['notif'] = ['tipe' => 'danger', 'pesan' => "Transaksi Gagal: " . $e->getMessage()];
    }

    // === LANGKAH G: KEMBALIKAN KE HALAMAN INPUT PESANAN ===
    header('Location: pesanan_input.php');
    exit;

} else {
    // Jika halaman diakses langsung, redirect
    header('Location: pesanan_input.php');
    exit;
}
?>