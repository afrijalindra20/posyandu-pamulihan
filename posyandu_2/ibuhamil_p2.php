<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_2/header_ih_p2.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil daftar ibu hamil untuk dropdown
$ibuHamilList = $db->query("SELECT id_ibu, nama_ibu_hamil FROM ibu_hamil_2")->fetchAll(PDO::FETCH_ASSOC);

// Tetapkan nilai default
$default_id_ibu = $ibuHamilList[0]['id_ibu'] ?? null;
$default_bulan = 'januari';

// Tangani pengiriman form atau gunakan nilai default
$id_ibu = isset($_POST['id_ibu']) ? (int)$_POST['id_ibu'] : $default_id_ibu;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : $default_bulan;

// Ambil data untuk id_ibu dan bulan yang dipilih atau default
$ibuHamilData = getIbuHamil2AndCatatanKehamilan($db, $id_ibu);
$catatanKehamilanData = getCatatanKehamilanByBulan2($db, $id_ibu, $bulan);

// List of months for dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Ibu Hamil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>

body {

    background-color: #f8f9fa;

    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

}

.container {

    max-width: 1200px;

    margin: 0 auto;

    padding: 0 15px;

}

.jumbotron {

    background: linear-gradient(135deg, #007bff, #6610f2);

    color: white;

    padding: 4rem 2rem;

    margin-bottom: 2rem;

    border-radius: 0.5rem;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

}

.jumbotron .row {

    margin: 0;

}

.jumbotron h1 {

    font-weight: 700;

    text-shadow: 2px 2px 4px rgba(255,255,255,0.5);

    animation: fadeInDown 1s ease-out;

    margin-bottom: 0.5rem;

}

.jumbotron p {

    animation: fadeInUp 1s ease-out 0.5s;

    animation-fill-mode: both;

    margin-bottom: 0;

}

.title-icon {

    font-size: 4rem;

    color: #ffffff;

    animation: bounceIn 1s ease-out;

    margin-right: 1rem;

}

@keyframes fadeInDown {

    from { opacity: 0; transform: translateY(-20px); }

    to { opacity: 1; transform: translateY(0); }

}

@keyframes fadeInUp {

    from { opacity: 0; transform: translateY(20px); }

    to { opacity: 1; transform: translateY(0); }

}

@keyframes bounceIn {

    0% { opacity: 0; transform: scale(0.3); }

    50% { opacity: 1; transform: scale(1.05); }

    70% { transform: scale(0.9); }

    100% { transform: scale(1); }

}

.card {

    border: none;

    border-radius: 15px;

    box-shadow: 0 5px 15px rgba(0,0,0,0.08);

    transition: all 0.3s ease;

    margin-bottom: 2rem;

}

.card:hover {

    transform: translateY(-5px);

    box-shadow: 0 8px 20px rgba(0,0,0,0.12);

}

.card-header {

    border-radius: 15px 15px 0 0 !important;

    font-weight: 600;

    padding: 1rem 1.5rem;

}

.card-body {

    padding: 1.5rem;

}

.form-control, .btn {

    border-radius: 10px;

}

.btn-primary {

    background-color: #007bff;

    border: none;

    transition: all 0.3s ease;

    padding: 0.5rem 1rem;

}

.btn-primary:hover {

    background-color: #0056b3;

    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.table {
    border-radius: 15px;
    overflow: hidden;
}
.table th {
    background-color: #007bff;
    color: white;
    border: none;
}
.table td {
    vertical-align: middle;
}
@media (max-width: 768px) {
    .jumbotron {
        padding: 3rem 1rem;
    }
    .title-icon {
        font-size: 3rem;
        margin-right: 0.5rem;
    }
    .jumbotron h1 {
        font-size: 2rem;
    }
    .jumbotron p {
        font-size: 1rem;
    }
}
</style>


</head>
<body>
    <div class="container">
        <div class="jumbotron text-center">
            <div class="row align-items-center justify-content-center">
                <div class="col-auto">
                    <i class="fas fa-female title-icon"></i>
                </div>
                <div class="col-auto">
                    <h1 class="display-4">Data Ibu Hamil</h1>
                    <p class="lead">Sistem Informasi Pengelolaan Data Ibu Hamil</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Pilih Ibu Hamil dan Bulan</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="id_ibu" class="form-label">Pilih Ibu Hamil:</label>
                            <select name="id_ibu" id="id_ibu" class="form-select">
                                <?php foreach ($ibuHamilList as $ibu): ?>
                                    <option value="<?= $ibu['id_ibu'] ?>" <?= $ibu['id_ibu'] == $id_ibu ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ibu['nama_ibu_hamil']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="bulan" class="form-label">Pilih Bulan:</label>
                            <select name="bulan" id="bulan" class="form-select">
                                <?php foreach ($months as $month): ?>
                                    <option value="<?= $month ?>" <?= $month === $bulan ? 'selected' : '' ?>>
                                        <?= ucfirst($month) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Tampilkan Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

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
                <div class="alert alert-info mt-4" role="alert">
                    <i class="fas fa-info-circle"></i> Tidak ada data untuk ibu hamil yang dipilih.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
require_once __DIR__ . '/../pos_2/footer_ih_p2.php';
?>