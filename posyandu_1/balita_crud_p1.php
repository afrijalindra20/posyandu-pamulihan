<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_1/header_balita_p1.php';

// Fungsi untuk mendapatkan nama balita berdasarkan id_balita
function getBalitaName($db, $id_balita) {
    $stmt = $db->prepare("SELECT nama_balita FROM balita WHERE id_balita = ?");
    $stmt->execute([$id_balita]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['nama_balita'] : '';
}

// Menangani pengiriman formulir untuk menambah, mengedit, dan menghapus pengukuran balita
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $data = [
            'no' => $_POST['no'],
            'id_balita' => $_POST['id_balita'],
            'tanggal_pengukuran' => $_POST['tanggal_pengukuran'],
            'berat_badan' => $_POST['berat_badan'],
            'tinggi_badan' => $_POST['tinggi_badan'],
            'status_gizi' => $_POST['status_gizi'],
            'bulan' => $_POST['bulan']
        ];

  // Modify the 'add' action in the form submission handling section
  if ($action === 'add') {
    if (empty($_POST['no'])) {
        $stmt = $db->query("SELECT MAX(no) as max_no FROM pengukuran_balita");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['no'] = ($result['max_no'] ?? 0) + 1;
    }
    $stmt = $db->prepare("INSERT INTO pengukuran_balita (no, id_balita, tanggal_pengukuran, berat_badan, tinggi_badan, status_gizi, bulan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array_values($data));

        } elseif ($action === 'edit') {
            $data['id_pengukuran'] = $_POST['id_pengukuran'];
            $stmt = $db->prepare("UPDATE pengukuran_balita SET no = ?, id_balita = ?, tanggal_pengukuran = ?, berat_badan = ?, tinggi_badan = ?, status_gizi = ?, bulan = ? WHERE id_pengukuran = ?");
            $stmt->execute(array_values($data));
        }
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM pengukuran_balita WHERE id_pengukuran = ?");
        $stmt->execute([$_POST['id_pengukuran']]);
    }


// Redirect untuk menghindari pengiriman ulang form
header("Location: " . $_SERVER['PHP_SELF']);
exit();
}

// Mengambil data pengukuran untuk diedit
$pengukuran = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM pengukuran_balita WHERE id_pengukuran = ?");
    $stmt->execute([$_GET['edit']]);
    $pengukuran = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mengambil daftar semua balita untuk dropdown
$balitas = $db->query("SELECT id_balita, nama_balita FROM balita")->fetchAll(PDO::FETCH_ASSOC);

// Mengambil daftar semua pengukuran balita
$pengukuran_balitas = $db->query("SELECT p.*, b.nama_balita FROM pengukuran_balita p JOIN balita b ON p.id_balita = b.id_balita ORDER BY p.no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Daftar bulan
$bulan_list = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Balita</title>
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
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
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
                <i class="fas fa-baby title-icon"></i>
            </div>
            <div class="col-auto">
                <h1 class="display-4">Data Pengukuran Balita</h1>
                <p class="lead">Sistem Informasi Pengelolaan Data Balita</p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100 animate-on-scroll">
                <div class="card-header">
                    <h5 class="card-title mb-0">Tambah/Edit Pengukuran Balita</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="id_pengukuran" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['id_pengukuran']) : ''; ?>">
                        
                        <div class="mb-3">
                            <label for="no" class="form-label">No:</label>
                            <input type="number" name="no" id="no" class="form-control" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['no']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_balita" class="form-label">Nama Balita:</label>
                            <select name="id_balita" id="id_balita" class="form-select" required onchange="fillNoBasedOnBalita(this.value)">
                                <option value="">Pilih Nama Balita</option>
                                <?php foreach ($balitas as $balita): ?>
                                    <option value="<?php echo $balita['id_balita']; ?>" <?php echo (isset($pengukuran) && $pengukuran['id_balita'] == $balita['id_balita']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($balita['nama_balita']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tanggal_pengukuran" class="form-label">Tanggal Pengukuran:</label>
                            <input type="date" name="tanggal_pengukuran" id="tanggal_pengukuran" class="form-control" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['tanggal_pengukuran']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="berat_badan" class="form-label">Berat Badan (kg):</label>
                            <input type="number" step="0.1" name="berat_badan" id="berat_badan" class="form-control" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['berat_badan']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tinggi_badan" class="form-label">Tinggi Badan (cm):</label>
                            <input type="number" step="0.1" name="tinggi_badan" id="tinggi_badan" class="form-control" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['tinggi_badan']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_gizi" class="form-label">Status Gizi:</label>
                            <input type="text" name="status_gizi" id="status_gizi" class="form-control" value="<?php echo isset($pengukuran) ? htmlspecialchars($pengukuran['status_gizi']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulan" class="form-label">Bulan:</label>
                            <select name="bulan" id="bulan" class="form-select" required>
                                <?php foreach ($bulan_list as $bulan): ?>
                                    <option value="<?php echo $bulan; ?>" <?php echo (isset($pengukuran) && $pengukuran['bulan'] == $bulan) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($bulan); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="action" value="<?php echo isset($pengukuran) ? 'edit' : 'add'; ?>" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h5 class="mb-0">Daftar Pengukuran Balita</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Balita</th>
                                    <th>Tanggal</th>
                                    <th>Berat (kg)</th>
                                    <th>Tinggi (cm)</th>
                                    <th>Status Gizi</th>
                                    <th>Bulan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pengukuran_balitas as $pengukuran): ?>
                                    <tr>
                                    <td><?php echo htmlspecialchars($pengukuran['no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pengukuran['nama_balita']); ?></td>
                                        <td><?php echo htmlspecialchars($pengukuran['tanggal_pengukuran']); ?></td>
                                        <td><?php echo htmlspecialchars($pengukuran['berat_badan']); ?></td>
                                        <td><?php echo htmlspecialchars($pengukuran['tinggi_badan']); ?></td>
                                        <td><?php echo htmlspecialchars($pengukuran['status_gizi']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($pengukuran['bulan'])); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $pengukuran['id_pengukuran']; ?>" class="btn btn-sm btn-warning mb-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="id_pengukuran" value="<?php echo $pengukuran['id_pengukuran']; ?>">
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function fillNoBasedOnBalita(id_balita) {
        const balitas = <?php echo json_encode($balitas); ?>;
        const selectedBalita = balitas.find(balita => balita.id_balita == id_balita);
        if (selectedBalita) {
            const index = balitas.indexOf(selectedBalita) + 1;
            document.getElementById('no').value = index;
        }
    }

    // Animate elements on scroll
    function animateOnScroll() {
        const elements = document.querySelectorAll('.animate-on-scroll');
        elements.forEach(element => {
            const rect = element.getBoundingClientRect();
            const windowHeight = window.innerHeight || document.documentElement.clientHeight;
            if (rect.top <= windowHeight * 0.75) {
                element.classList.add('visible');
            }
        });
    }

    window.addEventListener('scroll', animateOnScroll);
    window.addEventListener('load', animateOnScroll);
</script>

</body>
</html>
<?php require_once __DIR__ . '/../pos_1/footer_balita_p1.php'; ?>