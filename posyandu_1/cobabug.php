<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_1/header_balita_p1.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil daftar ibu hamil untuk dropdown
$ibuHamilList = $db->query("SELECT id_ibu, nama_ibu_hamil FROM ibu_hamil")->fetchAll(PDO::FETCH_ASSOC);

// Tetapkan nilai default
$default_id_ibu = $ibuHamilList[0]['id_ibu'] ?? null;
$default_bulan = 'januari';

// Tangani pengiriman form atau gunakan nilai default
$id_ibu = isset($_POST['id_ibu']) ? (int)$_POST['id_ibu'] : $default_id_ibu;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : $default_bulan;

// Ambil data untuk id_ibu dan bulan yang dipilih atau default
$ibuHamilData = getIbuHamilAndCatatanKehamilan($db, $id_ibu);
$catatanKehamilanData = getCatatanKehamilanByBulan($db, $id_ibu, $bulan);

// Daftar bulan untuk dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Ibu Hamil dan Catatan Kehamilan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <?php if (!empty($ibuHamilData)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Data Ibu Hamil</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nama:</strong> <?= htmlspecialchars($ibuHamilData[0]['nama_ibu_hamil']) ?></p>
                                <p><strong>NIK:</strong> <?= htmlspecialchars($ibuHamilData[0]['nik']) ?></p>
                                <p><strong>Tempat Tanggal Lahir:</strong> <?= htmlspecialchars($ibuHamilData[0]['tempat_tanggal_lahir_ibu']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Nama Suami:</strong> <?= htmlspecialchars($ibuHamilData[0]['nama_suami']) ?></p>
                                <p><strong>NIK Suami:</strong> <?= htmlspecialchars($ibuHamilData[0]['nik_suami']) ?></p>
                                <p><strong>Tempat Tanggal Lahir Suami:</strong> <?= htmlspecialchars($ibuHamilData[0]['tempat_tanggal_lahir_suami']) ?></p>
                            </div>
                        </div>
                        <p><strong>Alamat:</strong> <?= htmlspecialchars($ibuHamilData[0]['alamat']) ?></p>
                    </div>
                </div>

                <?php if (!empty($catatanKehamilanData)): ?>
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-notes-medical"></i> Catatan Kehamilan (<?= ucfirst($bulan) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Hamil Ke</th>
                                            <th>HPHT</th>
                                            <th>HPL</th>
                                            <th>Usia Kehamilan</th>
                                            <th>Status Kehamilan</th>
                                            <th>Tinggi Badan</th>
                                            <th>Berat Badan</th>
                                            <th>LILA</th>
                                            <th>Laboratorium</th>
                                            <th>Imunisasi</th>
                                            <th>Mendapat Bantuan</th>
                                            <th>Memiliki BPJS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($catatanKehamilanData as $catatan): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($catatan['hamil_keberapa']) ?></td>
                                                <td><?= htmlspecialchars($catatan['hpht']) ?></td>
                                                <td><?= htmlspecialchars($catatan['hpl']) ?></td>
                                                <td><?= htmlspecialchars($catatan['usia_kehamilan']) ?></td>
                                                <td><?= htmlspecialchars($catatan['status_kehamilan']) ?></td>
                                                <td><?= htmlspecialchars($catatan['tinggi_badan']) ?></td>
                                                <td><?= htmlspecialchars($catatan['berat_badan']) ?></td>
                                                <td><?= htmlspecialchars($catatan['lila']) ?></td>
                                                <td><?= htmlspecialchars($catatan['laboratorium']) ?></td>
                                                <td><?= htmlspecialchars($catatan['imunisasi']) ?></td>
                                                <td><?= $catatan['mendapatkan_bantuan'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>' ?></td>
                                                <td><?= $catatan['mempunyai_bpjs'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-4" role="alert">
                        <i class="fas fa-info-circle"></i> Tidak ada catatan kehamilan untuk bulan <?= ucfirst($bulan) ?>.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning mt-4" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> Tidak ada data ibu hamil yang tersedia.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>