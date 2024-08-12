<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_2/header_balita_p2.php';

// Menangani pengiriman formulir untuk menambah dan mengedit balita
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'add') {
        // Ambil nilai 'no' terbesar dari tabel balita_2
        $stmt = $db->prepare("SELECT MAX(no) AS max_no FROM balita_2");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $max_no = $result['max_no'] ?? 0; // Jika NULL, set 0

        if (!isset($_POST['no']) || empty($_POST['no'])) {
            $_POST['no'] = $max_no + 1;
        }
        
        // Jika 'no' tidak disediakan, buat no baru
        $no = $_POST['no'] ?? ($max_no + 1);

        // Gunakan htmlspecialchars untuk menghindari error deprecation
        $stmt = $db->prepare("INSERT INTO balita_2 (no, nama_balita, jenis_kelamin, nik, tanggal_lahir, berat_badan_lahir, nama_ayah, nama_ibu, alamat, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $no,
            htmlspecialchars($_POST['nama_balita'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['jenis_kelamin'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nik'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['tanggal_lahir'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['berat_badan_lahir'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nama_ayah'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nama_ibu'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['alamat'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8')
        ]);
    } elseif ($action === 'edit') {
        // Tambahkan ini untuk mendefinisikan $no
        $no = $_POST['no'] ?? null;

        // Gunakan htmlspecialchars untuk menghindari error deprecation
        $stmt = $db->prepare("UPDATE balita_2 SET no = ?, nama_balita = ?, jenis_kelamin = ?, nik = ?, tanggal_lahir = ?, berat_badan_lahir = ?, nama_ayah = ?, nama_ibu = ?, alamat = ?, status = ? WHERE id_balita = ?");
        $stmt->execute([
            $no,
            htmlspecialchars($_POST['nama_balita'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['jenis_kelamin'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nik'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['tanggal_lahir'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['berat_badan_lahir'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nama_ayah'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['nama_ibu'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['alamat'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8'),
            $_POST['id_balita']
        ]);
        
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM balita_2 WHERE id_balita = ?");
        $result = $stmt->execute([$_POST['id_balita']]);
        if ($result) {
            // Hapus juga data pengukuran terkait
            $stmt = $db->prepare("DELETE FROM pengukuran_balita_2 WHERE id_balita = ?");
            $stmt->execute([$_POST['id_balita']]);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true]);
                exit;
            }
        } else {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
                exit;
            }
        }
    }
}

// Mengambil data balita untuk diedit
$balita = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM balita_2 WHERE id_balita = ?");
    $stmt->execute([$_GET['edit']]);
    $balita = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mengambil daftar semua balita
$balitas = $db->query("SELECT * FROM balita_2")->fetchAll(PDO::FETCH_ASSOC);
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
                <h1 class="display-4">Data Daftar Balita</h1>
                <p class="lead">Sistem Informasi Pengelolaan Data Balita</p>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Tambah/Edit Balita</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="id_balita" value="<?php echo isset($balita) ? htmlspecialchars($balita['id_balita'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <div class="mb-3">
                            <label for="no" class="form-label">No:</label>
                            <input type="number" name="no" id="no" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['no'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama_balita" class="form-label">Nama Balita:</label>
                            <input type="text" name="nama_balita" id="nama_balita" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['nama_balita'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin:</label>
                            <select name="jenis_kelamin" id="jenis_kelamin" class="form-select" required>
                                <option value="L" <?php echo isset($balita) && $balita['jenis_kelamin'] === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo isset($balita) && $balita['jenis_kelamin'] === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="nik" class="form-label">NIK:</label>
                            <input type="text" name="nik" id="nik" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['nik'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir:</label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['tanggal_lahir'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="berat_badan_lahir" class="form-label">Berat Badan Lahir:</label>
                            <input type="number" step="0.01" name="berat_badan_lahir" id="berat_badan_lahir" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['berat_badan_lahir'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama_ayah" class="form-label">Nama Ayah:</label>
                            <input type="text" name="nama_ayah" id="nama_ayah" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['nama_ayah'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="nama_ibu" class="form-label">Nama Ibu:</label>
                            <input type="text" name="nama_ibu" id="nama_ibu" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['nama_ibu'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat:</label>
                            <input type="text" name="alamat" id="alamat" class="form-control" value="<?php echo isset($balita) ? htmlspecialchars($balita['alamat'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status:</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="Aktif" <?php echo isset($balita) && $balita['status'] === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Lulus" <?php echo isset($balita) && $balita['status'] === 'Lulus' ? 'selected' : ''; ?>>Lulus</option>
                                <option value="Keluar" <?php echo isset($balita) && $balita['status'] === 'Keluar' ? 'selected' : ''; ?>>Keluar</option>
                                <option value="Pindah" <?php echo isset($balita) && $balita['status'] === 'Pindah' ? 'selected' : ''; ?>>Pindah</option>
                                <option value="Meninggal" <?php echo isset($balita) && $balita['status'] === 'Meninggal' ? 'selected' : ''; ?>>Meninggal</option>
                                <option value="Tidak Aktif" <?php echo isset($balita) && $balita['status'] === 'Tidak Aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                            </select>
                        </div>
                        <button type="submit" name="action" value="<?php echo isset($balita) ? 'edit' : 'add'; ?>" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Daftar Balita</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Jenis Kelamin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($balitas as $balita): ?>
                                    <tr id="balita-row-<?php echo htmlspecialchars($balita['id_balita'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <td><?php echo htmlspecialchars($balita['no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($balita['id_balita'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($balita['nama_balita'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $balita['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $balita['id_balita']; ?>" class="btn btn-sm btn-warning mb-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="id_balita" value="<?php echo htmlspecialchars($balita['id_balita'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.delete-balita').on('click', function() {
        var balitaId = $(this).data('id');
        if (confirm('Apakah Anda yakin ingin menghapus balita ini?')) {
            $.ajax({
                url: 'balita_daftar_p2.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id_balita: balitaId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#balita-row-' + balitaId).fadeOut(300, function() {
                            $(this).remove();
                        });
                        showAlert('success', 'Data balita berhasil dihapus dari balita_2.');
                    } else {
                        showAlert('danger', 'Gagal menghapus data balita dari balita_2.');
                    }
                },
                error: function() {
                    showAlert('danger', 'Terjadi kesalahan saat menghapus data dari balita_2.');
                }
            });
        }
    });

    function showAlert(type, message) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                        message +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>';
        $('.container').prepend(alertHtml);
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }

    // Tambahkan animasi pada form input
    $('.form-control, .form-select').focus(function() {
        $(this).parent().addClass('form-group-focus');
    }).blur(function() {
        $(this).parent().removeClass('form-group-focus');
    });

    // Tambahkan efek hover pada baris tabel
    $('tbody tr').hover(
        function() {
            $(this).addClass('table-hover-highlight');
        },
        function() {
            $(this).removeClass('table-hover-highlight');
        }
    );
});
</script>

<style>
    .form-group-focus {
        transform: translateY(-5px);
        transition: all 0.3s ease;
    }
    .table-hover-highlight {
        background-color: #f8f9fa;
        transition: background-color 0.3s ease;
    }
    .fade-out {
        animation: fadeOut 0.5s ease;
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
</style>

</body>
</html>

<?php include __DIR__ . '/../pos_2/footer_balita_p2.php'; ?>