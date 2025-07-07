<?php
session_start();
include '../../koneksi.php';

// Keamanan: Pastikan hanya kasir/admin/owner yang bisa akses dan ada ID pesanan
if (!isset($_SESSION['user']) || !isset($_GET['id'])) {
    die("Akses ditolak atau ID Pesanan tidak ditemukan.");
}

$id_pesanan = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil data pesanan utama
$query_pesanan = "SELECT * FROM pesanan WHERE id_pesanan = ?";
$stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
mysqli_stmt_bind_param($stmt_pesanan, 's', $id_pesanan);
mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);
$pesanan = mysqli_fetch_assoc($result_pesanan);

if (!$pesanan) {
    die("Error: Data pesanan dengan ID '$id_pesanan' tidak ditemukan.");
}

// Ambil data detail pesanan dan join dengan tabel produk untuk mendapatkan nama produk
$query_detail = "SELECT dp.*, p.nama_produk 
                 FROM detail_pesanan dp 
                 JOIN produk p ON dp.id_produk = p.id_produk 
                 WHERE dp.id_pesanan = ?";
$stmt_detail = mysqli_prepare($koneksi, $query_detail);
mysqli_stmt_bind_param($stmt_detail, 's', $id_pesanan);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pesanan <?= htmlspecialchars($id_pesanan) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            width: 280px; /* Lebar umum kertas struk termal 80mm */
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h3, .header p {
            margin: 0;
            line-height: 1.2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 2px 0;
        }
        .text-right {
            text-align: right;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
        }
        /* Sembunyikan tombol saat mencetak */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h3>KEDAI KUE BALOK</h3>
        <p>Jl. Otista no. 50, Subang</p>
        <p>Telp: 0812-3456-7890</p>
    </div>

    <div class="divider"></div>
    <table>
        <tr>
            <td>No</td>
            <td>: <?= htmlspecialchars($pesanan['id_pesanan']) ?></td>
        </tr>
        <tr>
            <td>Tgl</td>
            <td>: <?= date('d/m/y H:i', strtotime($pesanan['tgl_pesanan'])) ?></td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>: <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td>Pelanggan</td>
            <td>: <?= htmlspecialchars($pesanan['nama_pemesan']) ?></td>
        </tr>
    </table>
    <div class="divider"></div>

    <table>
        <tbody>
            <?php while ($item = mysqli_fetch_assoc($result_detail)): ?>
            <tr>
                <td colspan="3"><?= htmlspecialchars($item['nama_produk']) ?></td>
            </tr>
            <tr>
                <td style="width:50px;"><?= htmlspecialchars($item['jumlah']) ?> x</td>
                <td style="width:100px;"><?= number_format($item['harga_saat_transaksi']) ?></td>
                <td class="text-right"><?= number_format($item['sub_total']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="divider"></div>
    
    <table>
        <tbody>
            <tr>
                <td>Subtotal</td>
                <td class="text-right"><?= number_format($pesanan['total_harga']) ?></td>
            </tr>
            <?php if ($pesanan['total_diskon'] > 0): ?>
            <tr>
                <td>Diskon</td>
                <td class="text-right">- <?= number_format($pesanan['total_diskon']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="font-size: 14px;"><strong>TOTAL</strong></td>
                <td class="text-right" style="font-size: 14px;"><strong><?= number_format($pesanan['total_harga'] - $pesanan['total_diskon']) ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <div class="divider"></div>
    <p>Metode Bayar: <?= strtoupper(htmlspecialchars($pesanan['metode_pembayaran'])) ?></p>
    <div class="divider"></div>

    <div class="footer">
        <p>-- Terima Kasih --</p>
        <p>Atas Kunjungan Anda</p>
    </div>

    <button class="no-print" onclick="window.print()" style="width:100%; padding:10px; margin-top: 20px;">Cetak Ulang</button>

</body>
</html>