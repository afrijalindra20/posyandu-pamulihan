<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_3/header_balita_p3.php';
require_once __DIR__ . '/../vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use FPDF\FPDF;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;



function logMessage($message) {
    file_put_contents('import_log.txt', date('Y-m-d H:i:s') . ': ' . $message . "\n", FILE_APPEND);
}

function logImport($message) {
    $logFile = __DIR__ . '/import_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log('POST request received: ' . print_r($_POST, true));
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action == 'import_csv' || $action == 'import_balita_csv') {
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['import_file']['tmp_name'];
                if ($action == 'import_csv') {
                    $result = importFromCSV($uploadedFile, $db);
                } else {
                    $result = importBalitaFromCSV($uploadedFile, $db);
                }
                if ($result['success']) {
                    $_SESSION['message'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
            } else {
                $_SESSION['error'] = "Error saat upload file.";
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            // Handle export and print actions

        $id_balita = isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : 1;
        $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';

        $balitaData = getBalita3AndPengukuran($db, $id_balita);
        if ($bulan === 'all') {
            $pengukuranData = getAllPengukuran3($db, $id_balita);
        } else {
            $pengukuranData = getPengukuranByBulan3($db, $id_balita, $bulan);
        }

        $namaBalita = !empty($balitaData) ? $balitaData[0]['nama_balita'] : 'Unknown';
        $filename = "balita_data_" . $namaBalita . "_" . ($bulan === 'all' ? 'semua_bulan' : $bulan) . "_" . date('Y-m-d');

        $exportInfo = prepareDataForExport($balitaData, $pengukuranData);

        try {
            switch ($action) {
                case 'export_excel':
                    ob_end_clean();
                    exportToExcel($exportInfo, $filename);
                    exit;
                    case 'export_pdf':
                        ob_end_clean();
                        exportToPDF($exportInfo, $filename);
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                        readfile($filename . '.pdf');
                        unlink($filename . '.pdf'); // Hapus file setelah diunduh
                        exit;
                case 'export_csv':
                    ob_end_clean();
                    exportToCSV($exportInfo, $filename);
                    exit;
                case 'print':
                    ob_end_clean();
                    printData($exportInfo);
                    exit;
               
            }
        } catch (Exception $e) {
            error_log('Error during action: ' . $e->getMessage());
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
}

if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle form submission
$id_balita = isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : 1;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';

// Fetch data for the selected id_balita and bulan
$balitaData = getBalita3AndPengukuran($db, $id_balita);
if ($bulan === 'all') {
    $pengukuranData = getAllPengukuran3($db, $id_balita);
} else {
    $pengukuranData = getPengukuranByBulan3($db, $id_balita, $bulan);
}

// Fetch list of balita for dropdown
$balitas = $db->query("SELECT id_balita, nama_balita FROM balita_3")->fetchAll(PDO::FETCH_ASSOC);

// List of months for dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];


function prepareDataForExport($balitaData, $pengukuranData) {
    $exportData = [];
    $pengukuranCount = 0;
    
    if (!empty($balitaData)) {
        $balita = $balitaData[0];
        
        // Data balita
        $exportData[] = ['Data Balita'];
        $exportData[] = ['ID Balita', $balita['id_balita']];
        $exportData[] = ['Nama Balita', $balita['nama_balita']];
        $exportData[] = ['Jenis Kelamin', $balita['jenis_kelamin']];
        $exportData[] = ['NIK', $balita['nik']];
        $exportData[] = ['Tanggal Lahir', $balita['tanggal_lahir']];
        $exportData[] = ['Berat Badan Lahir', $balita['berat_badan_lahir']];
        $exportData[] = ['Nama Ayah', $balita['nama_ayah']];
        $exportData[] = ['Nama Ibu', $balita['nama_ibu']];
        $exportData[] = ['Alamat', $balita['alamat']];
        $exportData[] = ['Status', $balita['status']];
        
        $exportData[] = [];
        
        // Data pengukuran
        $exportData[] = ['Data Pengukuran'];
        
        if (!empty($pengukuranData)) {
            $exportData[] = [
                'Tanggal Pengukuran',
                'Berat Badan',
                'Tinggi Badan',
                'Status Gizi',
                'Bulan'
            ];
            
            foreach ($pengukuranData as $pengukuran) {
                $exportData[] = [
                    $pengukuran['tanggal_pengukuran'],
                    $pengukuran['berat_badan'],
                    $pengukuran['tinggi_badan'],
                    $pengukuran['status_gizi'],
                    $pengukuran['bulan']
                ];
                $pengukuranCount++;
            }
        } else {
            $exportData[] = ['Tidak ada data pengukuran untuk balita ini.'];
        }
    }
    
    return ['data' => $exportData, 'pengukuranCount' => $pengukuranCount];
}

function exportToExcel($exportInfo, $filename) {
    $data = $exportInfo['data'];
    $pengukuranCount = $exportInfo['pengukuranCount'];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $row = 1;
    foreach ($data as $rowData) {
        $column = 1;
        foreach ($rowData as $cellData) {
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($column) . $row,
                $cellData,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $column++;
        }
        $row++;
    }

    // Auto-size kolom
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Tambahkan style untuk header
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']],
    ];
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
    if ($pengukuranCount > 0) {
        $sheet->getStyle('A' . ($row - $pengukuranCount - 1) . ':E' . ($row - $pengukuranCount - 1))->applyFromArray($headerStyle);
    }

    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. $filename .'.xlsx"');
    $writer->save('php://output');
}


class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30,10,'Data Balita dan Pengukuran',0,0,'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Halaman '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

function exportToPDF($exportInfo, $filename) {
    $data = $exportInfo['data'];
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Judul
    $pdf->Cell(0, 10, 'Data Balita dan Pengukuran', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);

    // Data Balita
    foreach ($data as $row) {
        if ($row[0] === 'Data Balita') {
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 12);
        } elseif ($row[0] === 'Data Pengukuran') {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif (count($row) === 2) {
            $pdf->Cell(60, 10, $row[0] . ':', 0);
            $pdf->Cell(0, 10, $row[1], 0, 1);
        } elseif (count($row) === 5) {
            $pdf->Cell(40, 10, $row[0], 1);
            $pdf->Cell(30, 10, $row[1], 1);
            $pdf->Cell(30, 10, $row[2], 1);
            $pdf->Cell(50, 10, $row[3], 1);
            $pdf->Cell(40, 10, $row[4], 1, 1);
        } elseif (count($row) === 1 && $row[0] === 'Tidak ada data pengukuran untuk balita ini.') {
            $pdf->Cell(0, 10, $row[0], 0, 1);
        }
    }

    $pdf->Output('F', $filename . '.pdf');
}

function printData($exportInfo) {
    if (empty($exportInfo['data'])) {
        echo "Tidak ada data untuk dicetak.";
        return;
    }

    $data = $exportInfo['data'];
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Judul
    $pdf->Cell(0, 10, 'Data Balita dan Pengukuran', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);

    // Data Balita
    foreach ($data as $row) {
        if (!is_array($row)) continue; // Skip jika bukan array

        if (isset($row[0]) && $row[0] === 'Data Balita') {
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 12);
        } elseif (isset($row[0]) && $row[0] === 'Data Pengukuran') {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif (count($row) === 2) {
            $pdf->Cell(60, 10, $row[0] . ':', 0);
            $pdf->Cell(0, 10, $row[1], 0, 1);
        } elseif (count($row) === 5) {
            $pdf->Cell(40, 10, $row[0], 1);
            $pdf->Cell(30, 10, $row[1], 1);
            $pdf->Cell(30, 10, $row[2], 1);
            $pdf->Cell(50, 10, $row[3], 1);
            $pdf->Cell(40, 10, $row[4], 1, 1);
        } elseif (count($row) === 1 && $row[0] === 'Tidak ada data pengukuran untuk balita ini.') {
            $pdf->Cell(0, 10, $row[0], 0, 1);
        }
    }

    $pdf->Output();
}

function importFromCSV($filename, $db) {
    logImport("Mulai impor file: $filename");
    $successCount = 0;
    $errors = [];

    try {
        if (($handle = fopen($filename, "r")) !== FALSE) {
            logImport("File berhasil dibuka");
            fgetcsv($handle, 1000, ",");
            
            $db->beginTransaction();
            logImport("Transaksi database dimulai");

            $stmt = $db->prepare("INSERT INTO pengukuran_balita_3 (id_balita, tanggal_pengukuran, berat_badan, tinggi_badan, status_gizi, bulan) VALUES (?, ?, ?, ?, ?, ?)");
            
            $lineNumber = 2;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                logImport("Membaca baris $lineNumber: " . implode(", ", $data));
                
                if (count($data) != 6) {
                    $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
                    logImport("Error: Jumlah kolom tidak sesuai pada baris $lineNumber");
                    continue;
                }

                $id_balita = filter_var($data[0], FILTER_VALIDATE_INT);
                $tanggal_pengukuran = date('Y-m-d', strtotime($data[1]));
                $berat_badan = filter_var($data[2], FILTER_VALIDATE_FLOAT);
                $tinggi_badan = filter_var($data[3], FILTER_VALIDATE_FLOAT);
                $status_gizi = trim($data[4]);
                $bulan = trim($data[5]);

                if ($id_balita === false || $berat_badan === false || $tinggi_badan === false) {
                    $errors[] = "Baris $lineNumber: Format data tidak valid";
                    logImport("Error: Format data tidak valid pada baris $lineNumber");
                    continue;
                }

                if (!$stmt->execute([$id_balita, $tanggal_pengukuran, $berat_badan, $tinggi_badan, $status_gizi, $bulan])) {
                    $errors[] = "Baris $lineNumber: " . implode(", ", $stmt->errorInfo());
                    logImport("Error database pada baris $lineNumber: " . implode(", ", $stmt->errorInfo()));
                    continue;
                }

                $successCount++;
                logImport("Baris $lineNumber berhasil diimpor");
                $lineNumber++;
            }
            
            fclose($handle);

            if (empty($errors)) {
                $db->commit();
                logImport("Transaksi berhasil. $successCount data diimpor.");
                return ["success" => true, "message" => "$successCount data berhasil diimpor."];
            } else {
                $db->rollBack();
                logImport("Transaksi dibatalkan karena ada error.");
                return ["success" => false, "message" => "Impor gagal. " . implode("; ", $errors)];
            }
        }
    } catch (PDOException $e) {
        $db->rollBack();
        logImport("Error PDO: " . $e->getMessage());
        return ["success" => false, "message" => "Error database: " . $e->getMessage()];
    } catch (Exception $e) {
        $db->rollBack();
        logImport("Error umum: " . $e->getMessage());
        return ["success" => false, "message" => "Error umum: " . $e->getMessage()];
    }

    logImport("Gagal membuka file CSV.");
    return ["success" => false, "message" => "Gagal membuka file CSV."];
}

function importBalitaFromCSV($filename, $db) {
    logImport("Mulai impor file balita: $filename");
    $successCount = 0;
    $errors = [];

    try {
        if (($handle = fopen($filename, "r")) !== FALSE) {
            logImport("File balita berhasil dibuka");
            fgetcsv($handle, 1000, ","); // Skip header row
            
            $db->beginTransaction();
            logImport("Transaksi database dimulai");

            $stmt = $db->prepare("INSERT INTO balita_3 (id_balita, nama_balita, jenis_kelamin, nik, tanggal_lahir, berat_badan_lahir, nama_ayah, nama_ibu, alamat, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $lineNumber = 2;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                logImport("Membaca baris $lineNumber: " . implode(", ", $data));
                
                if (count($data) != 10) {
                    $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
                    logImport("Error: Jumlah kolom tidak sesuai pada baris $lineNumber");
                    continue;
                }

                $id_balita = filter_var($data[0], FILTER_VALIDATE_INT);
                $nama_balita = trim($data[1]);
                $jenis_kelamin = trim($data[2]);
                $nik = trim($data[3]);
                $tanggal_lahir = date('Y-m-d', strtotime($data[4]));
                $berat_badan_lahir = filter_var($data[5], FILTER_VALIDATE_FLOAT);
                $nama_ayah = trim($data[6]);
                $nama_ibu = trim($data[7]);
                $alamat = trim($data[8]);
                $status = trim($data[9]);

                if ($id_balita === false || $berat_badan_lahir === false) {
                    $errors[] = "Baris $lineNumber: Format data tidak valid";
                    logImport("Error: Format data tidak valid pada baris $lineNumber");
                    continue;
                }

                if (!$stmt->execute([$id_balita, $nama_balita, $jenis_kelamin, $nik, $tanggal_lahir, $berat_badan_lahir, $nama_ayah, $nama_ibu, $alamat, $status])) {
                    $errors[] = "Baris $lineNumber: " . implode(", ", $stmt->errorInfo());
                    logImport("Error database pada baris $lineNumber: " . implode(", ", $stmt->errorInfo()));
                    continue;
                }

                $successCount++;
                logImport("Baris $lineNumber berhasil diimpor");
                $lineNumber++;
            }
            
            fclose($handle);

            if (empty($errors)) {
                $db->commit();
                logImport("Transaksi berhasil. $successCount data balita diimpor.");
                return ["success" => true, "message" => "$successCount data balita berhasil diimpor."];
            } else {
                $db->rollBack();
                logImport("Transaksi dibatalkan karena ada error.");
                return ["success" => false, "message" => "Impor balita gagal. " . implode("; ", $errors)];
            }
        }
    } catch (PDOException $e) {
        $db->rollBack();
        logImport("Error PDO: " . $e->getMessage());
        return ["success" => false, "message" => "Error database: " . $e->getMessage()];
    } catch (Exception $e) {
        $db->rollBack();
        logImport("Error umum: " . $e->getMessage());
        return ["success" => false, "message" => "Error umum: " . $e->getMessage()];
    }

    logImport("Gagal membuka file CSV balita.");
    return ["success" => false, "message" => "Gagal membuka file CSV balita."];
}

function exportToCSV($exportInfo, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Use UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    foreach ($exportInfo['data'] as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

// Handle export, import, dan print requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Pastikan data balita dan pengukuran diambil lagi berdasarkan POST
    $id_balita = isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : 1;
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';
    
    $balitaData = getBalita3AndPengukuran($db, $id_balita);
    if ($bulan === 'all') {
        $pengukuranData = getAllPengukuran3($db, $id_balita);
    } else {
        $pengukuranData = getPengukuranByBulan3($db, $id_balita, $bulan);
    }
    
    // Ambil nama balita dari data balita
    $namaBalita = !empty($balitaData) ? $balitaData[0]['nama_balita'] : 'Unknown';
    
    $filename = "balita_data_" . $namaBalita . "_" . ($bulan === 'all' ? 'semua_bulan' : $bulan) . "_" . date('Y-m-d');
    
    $exportInfo = prepareDataForExport($balitaData, $pengukuranData);
    
    try {
        switch ($action) {
            case 'export_excel':
                ob_end_clean();
                exportToExcel($exportInfo, $filename);
                exit;
            case 'export_pdf':
                ob_end_clean();
                exportToPDF($exportInfo, $filename);
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                readfile($filename . '.pdf');
                unlink($filename . '.pdf'); // Hapus file setelah diunduh
                exit;
                
            // Tambahkan case untuk aksi ekspor lainnya di sini
        }
    } catch (Exception $e) {
        // Log error
        error_log('Error during export: ' . $e->getMessage());
        // Tampilkan pesan error kepada pengguna
        $_SESSION['error'] = "Terjadi kesalahan saat mengekspor data. Silakan coba lagi.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    switch ($action) {
        case 'export_excel':
            ob_end_clean();
            exportToExcel($exportInfo, $filename);
            exit;
        case 'export_pdf':
            ob_end_clean();
            exportToPDF($exportInfo, $filename);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            readfile($filename . '.pdf');
            unlink($filename . '.pdf');
            exit;
            
            case 'print':
                ob_end_clean(); // Bersihkan output buffer
                ob_start(); // Mulai output buffering baru
                printData($exportInfo);
                $pdfContent = ob_get_clean(); // Ambil konten PDF dan bersihkan buffer
            
                // Kirim header PDF
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdfContent));
                header('Content-Disposition: inline; filename="balita_data_print.pdf"');
                
                // Keluarkan konten PDF
                echo $pdfContent;
                exit;
                case 'export_csv':  // New case for CSV export
                    ob_end_clean();
                    exportToCSV($exportInfo, $filename);
                    exit;
            // ... (case lainnya jika ada)
        // Tambahkan case untuk aksi ekspor lainnya di sini
    }
}
// Jika tidak ada aksi ekspor, lanjutkan dengan output HTML
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
    #actionSelect, #importFile {
        transition: all 0.3s ease;
    }
    #actionSelect:focus, #importFile:focus {
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        transition: all 0.3s ease;
    }
    .btn-success:hover {
        background-color: #218838;
        border-color: #1e7e34;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                <h1 class="display-4">Transfer Data Detail Balita</h1>
                <p class="lead">Sistem Informasi Pengelolaan Data Balita</p>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Pilih Balita dan Bulan</h5>
                    <form method="post">
                        <div class="mb-3">
                            <label for="id_balita" class="form-label">Pilih Balita:</label>
                            <select name="id_balita" id="id_balita" class="form-select">
                                <?php foreach ($balitas as $balita): ?>
                                    <option value="<?php echo htmlspecialchars($balita['id_balita']); ?>"
                                        <?php if ($balita['id_balita'] == $id_balita): ?> selected <?php endif; ?>>
                                        <?php echo htmlspecialchars($balita['nama_balita']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="bulan" class="form-label">Pilih Bulan:</label>
                            <select name="bulan" id="bulan" class="form-select">
                                <option value="all" <?php if ($bulan === 'all'): ?> selected <?php endif; ?>>Semua Bulan</option>
                                <?php foreach ($months as $month): ?>
                                    <option value="<?php echo htmlspecialchars($month); ?>"
                                        <?php if ($month === $bulan): ?> selected <?php endif; ?>>
                                        <?php echo ucfirst($month); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if (!empty($balitaData)): ?>
                <?php $balita = $balitaData[0]; ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Data Balita dan Pengukuran</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">Informasi Balita</h6>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>ID Balita:</strong> <?php echo htmlspecialchars($balita['id_balita']); ?></p>
                                <p><strong>Nama:</strong> <?php echo htmlspecialchars($balita['nama_balita']); ?></p>
                                <p><strong>Jenis Kelamin:</strong> <?php echo htmlspecialchars($balita['jenis_kelamin']); ?></p>
                                <p><strong>NIK:</strong> <?php echo htmlspecialchars($balita['nik']); ?></p>
                                <p><strong>Tanggal Lahir:</strong> <?php echo htmlspecialchars($balita['tanggal_lahir']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Berat Badan Lahir:</strong> <?php echo htmlspecialchars($balita['berat_badan_lahir']); ?> kg</p>
                                <p><strong>Nama Ayah:</strong> <?php echo htmlspecialchars($balita['nama_ayah']); ?></p>
                                <p><strong>Nama Ibu:</strong> <?php echo htmlspecialchars($balita['nama_ibu']); ?></p>
                                <p><strong>Alamat:</strong> <?php echo htmlspecialchars($balita['alamat']); ?></p>
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($balita['status']); ?></p>
                            </div>
                        </div>

                        <h6 class="card-subtitle mb-3 text-muted">Riwayat Pengukuran <?php echo $bulan === 'all' ? '(Semua Bulan)' : '(' . ucfirst($bulan) . ')'; ?></h6>
                        <?php if (!empty($pengukuranData)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tanggal Pengukuran</th>
                                            <th>Berat Badan (kg)</th>
                                            <th>Tinggi Badan (cm)</th>
                                            <th>Status Gizi</th>
                                            <th>Bulan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pengukuranData as $pengukuran): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pengukuran['tanggal_pengukuran']); ?></td>
                                                <td><?php echo htmlspecialchars($pengukuran['berat_badan']); ?></td>
                                                <td><?php echo htmlspecialchars($pengukuran['tinggi_badan']); ?></td>
                                                <td><?php echo htmlspecialchars($pengukuran['status_gizi']); ?></td>
                                                <td><?php echo htmlspecialchars($pengukuran['bulan']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info" role="alert">
                                Tidak ada data pengukuran <?php echo $bulan === 'all' ? 'untuk semua bulan' : 'untuk bulan ' . ucfirst($bulan); ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    Tidak ada data untuk balita ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
        <!-- New section: Aksi Data -->
        <div class="container mb-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Aksi Data</h5>
                </div>
                <div class="card-body">
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="id_balita" value="<?php echo htmlspecialchars($id_balita); ?>">
    <input type="hidden" name="bulan" value="<?php echo htmlspecialchars($bulan); ?>">
    <div class="col-md-4">
        <label for="actionSelect" class="form-label">Pilih Aksi:</label>
        <select name="action" class="form-select" id="actionSelect">
            <option value="export_excel">Export Excel</option>
            <option value="export_pdf">Export PDF</option>
            <option value="export_csv">Export CSV</option>
            <option value="import_csv">Import CSV Pengukuran</option>
            <option value="import_balita_csv">Import CSV Balita</option>
            <option value="print">Cetak</option>
        </select>
    </div>
    <div class="col-md-4">
        <label for="importFile" class="form-label">File Import:</label>
        <input type="file" name="import_file" id="importFile" class="form-control" accept=".csv" style="display: none;">
    </div>
    <div class="col-md-4">
        <label class="form-label">&nbsp;</label>
        <button type="submit" class="btn btn-success w-100">Proses</button>
    </div>
</form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionSelect = document.getElementById('actionSelect');
    const importFile = document.getElementById('importFile');

    actionSelect.addEventListener('change', function() {
        if (this.value === 'import_csv' || this.value === 'import_balita_csv') {
            importFile.style.display = 'block';
        } else {
            importFile.style.display = 'none';
        }
    });
});
</script>
</body>
</html>

<?php 
require_once __DIR__ . '/../pos_3/footer_balita_p3.php';
ob_end_flush();
?>