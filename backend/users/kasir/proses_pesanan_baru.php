<?php
ini_set('display_errors', 1); // Baris ini bisa dihapus jika sudah tidak diperlukan
error_reporting(E_ALL);   // Baris ini bisa dihapus jika sudah tidak diperlukan

session_start();
include '../../koneksi.php';

// 1. OTENTIKASI & OTORISASI
if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] !== 'kasir') {
    die("Akses ditolak. Anda harus login sebagai kasir.");
}

// 2. Pastikan request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. Ambil dan amankan data dari form
    // PERBAIKAN 1: Menggunakan 'id' sesuai struktur sesi Anda
    $id_karyawan = $_SESSION['user']['id']; 
    $nama_pemesan = !empty($_POST['nama_pemesan']) ? mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']) : 'Walk-in Customer';
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    $jenis_pesanan = mysqli_real_escape_string($koneksi, $_POST['jenis_pesanan']);
    $metode_pembayaran = mysqli_real_escape_string($koneksi, $_POST['pembayaran']);

    $total_harga = filter_var($_POST['total_harga'], FILTER_VALIDATE_FLOAT);
    $total_diskon = filter_var($_POST['total_diskon'], FILTER_VALIDATE_FLOAT);
    $cart_json = $_POST['cart_json'];
    $cart = json_decode($cart_json, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($cart)) {
        $_SESSION['notif'] = ['tipe' => 'danger', 'pesan' => 'Data keranjang tidak valid atau kosong.'];
        header('Location: pesanan_input.php');
        exit;
    }

    mysqli_begin_transaction($koneksi);

    try {
        // === Validasi Stok di Sisi Server ===
        $produk_info_map = [];
        $stmt_produk_info = mysqli_prepare($koneksi, "SELECT id_produk, nama_produk, harga, id_kategori_stok FROM produk WHERE id_produk = ? AND status_produk = 'aktif'");
        $stmt_cek_stok_kategori = mysqli_prepare($koneksi, "SELECT SUM(jumlah_perubahan) as total FROM log_stok WHERE id_kategori_stok = ?");
        $stmt_cek_stok_individu = mysqli_prepare($koneksi, "SELECT SUM(jumlah_perubahan) as total FROM log_stok WHERE id_produk = ?");

        foreach ($cart as $id => $item) {
            if ($item['price'] <= 0) continue;

            if ($item['category'] === 'Paket') {
                if (empty($item['items'])) throw new Exception("Paket '{$item['name']}' tidak memiliki rincian item.");
                foreach ($item['items'] as $sub_item) {
                    $id_produk_komponen = $sub_item['id_produk'];
                    $jumlah_dibutuhkan = $sub_item['jumlah'] * $item['quantity'];
                    
                    mysqli_stmt_bind_param($stmt_produk_info, "s", $id_produk_komponen);
                    mysqli_stmt_execute($stmt_produk_info);
                    $produk_db = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_produk_info));
                    if (!$produk_db) throw new Exception("Komponen '{$sub_item['nama_produk']}' dalam paket tidak tersedia.");
                    
                    $stok_saat_ini = 0;
                    if (!empty($produk_db['id_kategori_stok'])) {
                        mysqli_stmt_bind_param($stmt_cek_stok_kategori, "s", $produk_db['id_kategori_stok']);
                        mysqli_stmt_execute($stmt_cek_stok_kategori);
                        $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_kategori))['total'];
                    } else {
                        mysqli_stmt_bind_param($stmt_cek_stok_individu, "s", $id_produk_komponen);
                        mysqli_stmt_execute($stmt_cek_stok_individu);
                        $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_individu))['total'];
                    }
                    if ($stok_saat_ini < $jumlah_dibutuhkan) throw new Exception("Stok untuk komponen '{$sub_item['nama_produk']}' tidak cukup (sisa: {$stok_saat_ini}, butuh: {$jumlah_dibutuhkan}).");
                    
                    $produk_info_map[$id_produk_komponen] = $produk_db;
                }
            } else {
                mysqli_stmt_bind_param($stmt_produk_info, "s", $id);
                mysqli_stmt_execute($stmt_produk_info);
                $produk_db = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_produk_info));
                if (!$produk_db) throw new Exception("Produk '{$item['name']}' tidak tersedia atau tidak aktif.");
                
                $stok_saat_ini = 0;
                if (!empty($produk_db['id_kategori_stok'])) {
                    mysqli_stmt_bind_param($stmt_cek_stok_kategori, "s", $produk_db['id_kategori_stok']);
                    mysqli_stmt_execute($stmt_cek_stok_kategori);
                    $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_kategori))['total'];
                } else {
                    mysqli_stmt_bind_param($stmt_cek_stok_individu, "s", $id);
                    mysqli_stmt_execute($stmt_cek_stok_individu);
                    $stok_saat_ini = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek_stok_individu))['total'];
                }
                if ($stok_saat_ini < $item['quantity']) throw new Exception("Stok untuk produk '{$item['name']}' tidak mencukupi (sisa: {$stok_saat_ini}).");
                
                $produk_info_map[$id] = $produk_db;
            }
        }
        
        $sql_beban = "SELECT COUNT(id_pesanan) AS antrean FROM pesanan WHERE status_pesanan IN ('pending', 'diproses')";
        $result_beban = mysqli_query($koneksi, $sql_beban);
        $beban_dapur = mysqli_fetch_assoc($result_beban)['antrean'] ?? 0;
        $status_awal = ($beban_dapur < 20) ? 'diproses' : 'pending';
        
        $id_pesanan_baru = "KSR-" . date("YmdHis");
        $tgl_pesanan = date("Y-m-d H:i:s");
        
        $query_pesanan = "INSERT INTO pesanan (id_pesanan, id_karyawan, tipe_pesanan, jenis_pesanan, nama_pemesan, tgl_pesanan, total_harga, total_diskon, metode_pembayaran, status_pesanan, catatan) VALUES (?, ?, 'kasir', ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
        mysqli_stmt_bind_param($stmt_pesanan, "sisssddsss", $id_pesanan_baru, $id_karyawan, $jenis_pesanan, $nama_pemesan, $tgl_pesanan, $total_harga, $total_diskon, $metode_pembayaran, $status_awal, $catatan);
        mysqli_stmt_execute($stmt_pesanan);
        mysqli_stmt_close($stmt_pesanan);

        $stmt_detail = mysqli_prepare($koneksi, "INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_saat_transaksi, sub_total, id_paket_asal) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_log = mysqli_prepare($koneksi, "INSERT INTO log_stok (id_produk, id_kategori_stok, jumlah_perubahan, jenis_aksi, id_pesanan, keterangan) VALUES (?, ?, ?, 'penjualan', ?, 'Penjualan Kasir')");

        foreach ($cart as $id => $item) {
            if ($item['price'] <= 0) continue;

            if ($item['category'] === 'Paket') {
                foreach ($item['items'] as $sub_item) {
                    $id_produk_komponen = $sub_item['id_produk'];
                    $info_produk = $produk_info_map[$id_produk_komponen];
                    
                    // PERBAIKAN 2: Hitung sub-total ke dalam variabel sebelum bind_param
                    $harga_komponen = $info_produk['harga'];
                    $jumlah_komponen = $sub_item['jumlah'];
                    $sub_total_komponen = $harga_komponen * $jumlah_komponen;
                    
                    mysqli_stmt_bind_param($stmt_detail, "ssidds", $id_pesanan_baru, $id_produk_komponen, $jumlah_komponen, $harga_komponen, $sub_total_komponen, $id);
                    mysqli_stmt_execute($stmt_detail);
                    
                    $id_produk_log = null;
                    $id_kategori_log = null;
                    $jumlah_pengurangan = -1 * abs($jumlah_komponen);
                    if (!empty($info_produk['id_kategori_stok'])) { $id_kategori_log = $info_produk['id_kategori_stok']; } 
                    else { $id_produk_log = $id_produk_komponen; }
                    mysqli_stmt_bind_param($stmt_log, "ssis", $id_produk_log, $id_kategori_log, $jumlah_pengurangan, $id_pesanan_baru);
                    mysqli_stmt_execute($stmt_log);
                }
            } else {
                $info_produk = $produk_info_map[$id];
                $sub_total_item = $item['price'] * $item['quantity'];
                $id_paket_asal = null;
                mysqli_stmt_bind_param($stmt_detail, "ssidds", $id_pesanan_baru, $id, $item['quantity'], $item['price'], $sub_total_item, $id_paket_asal);
                mysqli_stmt_execute($stmt_detail);

                $id_produk_log = null;
                $id_kategori_log = null;
                $jumlah_pengurangan = -1 * abs($item['quantity']);
                if (!empty($info_produk['id_kategori_stok'])) { $id_kategori_log = $info_produk['id_kategori_stok']; } 
                else { $id_produk_log = $id; }
                mysqli_stmt_bind_param($stmt_log, "ssis", $id_produk_log, $id_kategori_log, $jumlah_pengurangan, $id_pesanan_baru);
                mysqli_stmt_execute($stmt_log);
            }
        }
        mysqli_stmt_close($stmt_detail);
        mysqli_stmt_close($stmt_log);

        mysqli_commit($koneksi);

        $pesan_sukses = "Pesanan berhasil dibuat dengan ID: <strong>$id_pesanan_baru</strong>.";
        $pesan_sukses .= " <a href='cetak_struk.php?id=$id_pesanan_baru' target='_blank' class='btn btn-sm btn-info fw-bold'><i class='fas fa-print'></i> Cetak Struk</a>";
        $_SESSION['notif'] = ['tipe' => 'success', 'pesan' => $pesan_sukses];
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['notif'] = ['tipe' => 'danger', 'pesan' => "Transaksi Gagal: " . $e->getMessage()];
    }

    header('Location: pesanan_input.php');
    exit;
} else {
    header('Location: pesanan_input.php');
    exit;
}
?>