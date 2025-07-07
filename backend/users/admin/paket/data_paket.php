<?php
// 1. WAJIB: Mulai sesi di baris paling atas
session_start();

// Sesuaikan dengan koneksi Anda
include('../../../koneksi.php');

// 2. KEAMANAN: Cek hak akses. Hanya admin dan owner yang boleh mengakses.
if (!isset($_SESSION['user']) || ($_SESSION['user']['jabatan'] !== 'admin' && $_SESSION['user']['jabatan'] !== 'owner')) {
    header('Location: ../../../login.php');
    exit;
}

// 3. Mengambil data paket utama untuk ditampilkan di tabel
$result_paket = mysqli_query($koneksi, "SELECT * FROM paket ORDER BY id_paket ASC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Data Paket - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="../../../css/styles.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="../../../assets/img/logo-kuebalok.png">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>

<body class="sb-nav-fixed">
    <?php include "../inc/navbar.php"; ?>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <?php include "../inc/sidebar.php"; ?>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Data Paket Menu</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Data Paket</li>
                    </ol>

                    <?php
                    // Blok notifikasi dari sesi
                    if (isset($_SESSION['notif'])) {
                        $notif = $_SESSION['notif'];
                        echo '<div class="alert alert-' . htmlspecialchars($notif['tipe']) . ' alert-dismissible fade show" role="alert">';
                        echo htmlspecialchars($notif['pesan']);
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';
                        unset($_SESSION['notif']);
                    }
                    ?>

                    <div class="mb-3">
                        <a href="tambah_paket.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Paket</a>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            Daftar Paket Tersedia
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID Paket</th>
                                        <th>Nama & Rincian Isi</th>
                                        <th>Harga</th>
                                        <th>Gambar</th>
                                        <th>Status</th>
                                        <th class="text-center">Opsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result_paket && mysqli_num_rows($result_paket) > 0) {
                                        while ($paket = mysqli_fetch_assoc($result_paket)) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($paket['id_paket']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($paket['nama_paket']) ?></strong>
                                                    <ul class="list-unstyled small mt-2 mb-0">
                                                        <?php
                                                        // Query untuk mengambil detail isi paket
                                                        $id_paket_current = $paket['id_paket'];
                                                        $query_detail = "SELECT dp.jumlah, p.nama_produk 
                                                                         FROM detail_paket dp 
                                                                         JOIN produk p ON dp.id_produk = p.id_produk 
                                                                         WHERE dp.id_paket = ?";
                                                        $stmt = mysqli_prepare($koneksi, $query_detail);
                                                        mysqli_stmt_bind_param($stmt, "s", $id_paket_current);
                                                        mysqli_stmt_execute($stmt);
                                                        $result_detail = mysqli_stmt_get_result($stmt);

                                                        while ($item = mysqli_fetch_assoc($result_detail)) {
                                                            echo '<li><i class="fas fa-check-circle fa-xs text-success me-1"></i>' . htmlspecialchars($item['jumlah']) . 'x ' . htmlspecialchars($item['nama_produk']) . '</li>';
                                                        }
                                                        mysqli_stmt_close($stmt);
                                                        ?>
                                                    </ul>
                                                </td>
                                                <td>Rp <?= number_format($paket['harga_paket']) ?></td>
                                                <td>
                                                    <img src="../../../assets/img/paket/<?= htmlspecialchars($paket['poto_paket']) ?>" width="100" alt="<?= htmlspecialchars($paket['nama_paket']) ?>">
                                                </td>
                                                <td>
                                                    <?php if ($paket['status_paket'] === 'aktif'): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Tidak Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <a href="paket_edit.php?id=<?= htmlspecialchars($paket['id_paket']) ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="paket_ubah_status.php?id=<?= htmlspecialchars($paket['id_paket']) ?>" class="btn btn-secondary btn-sm" title="Ubah Status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </a>
                                                        <a href="paket_hapus.php?id=<?= htmlspecialchars($paket['id_paket']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Anda yakin ingin menghapus paket ini secara permanen?')" title="Hapus Permanen">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">Belum ada data paket.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; KueBalok 2025</div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../../../js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script src="../../../js/datatables-simple-demo.js"></script>
</body>

</html>