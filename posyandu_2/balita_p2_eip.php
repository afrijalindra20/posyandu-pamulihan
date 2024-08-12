<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_2/header_balita_p2.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use FPDF\FPDF;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

function logMessage($message) {
    file_put_contents('import_log.txt', date('Y-m-d H:i:s') . ': ' . $message . "\n", FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    logMessage("Permintaan POST diterima");
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        logMessage("Aksi: " . $action);
        
        if ($action == 'import_csv' || $action == 'import_excel') {
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['import_file']['tmp_name'];
                $originalFileName = $_FILES['import_file']['name'];
                logMessage("File diterima: " . $originalFileName);
                
                if ($action == 'import_csv') {
                    $importedData = importFromCSV($uploadedFile, $db);
                } else {
                    $importedData = importFromExcel($uploadedFile, $db);
                }
                
                if ($importedData) {
                    $_SESSION['message'] = "Data berhasil diimpor.";
                    logMessage("Impor berhasil");
                } else {
                    $_SESSION['message'] = "Gagal mengimpor data.";
                    logMessage("Impor gagal");
                }
            } else {
                $_SESSION['message'] = "Error saat upload file.";
                logMessage("Error upload file");
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle form submission
$id_balita = isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : null;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'januari'; 

// Fetch data untuk id_balita dan bulan yang dipilih
$balitaData = getBalita22AndPengukuran($db, $id_balita);

// Fetch list balita untuk dropdown
$balitas = $db->query("SELECT id_balita, nama_balita FROM balita_2")->fetchAll(PDO::FETCH_ASSOC);

// List bulan untuk dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];


function prepareDataForExport($balitaData) {
    foreach ($balitaData as &$row) {
        if (isset($row['tanggal_lahir'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['tanggal_lahir']);
            if ($date) {
                $row['tanggal_lahir'] = $date->format('d/m/Y');
            }
        }
        if (isset($row['tanggal_pengukuran'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['tanggal_pengukuran']);
            if ($date) {
                $row['tanggal_pengukuran'] = $date->format('d/m/Y');
            }
        }
    }
    return $balitaData;
}


function exportToExcel($data, $filename) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Tambahkan headers
    $headers = array_keys($data[0]);
    $column = 1;
    foreach ($headers as $header) {
        $sheet->setCellValue([$column, 1], $header);
        $column++;
    }

    // Tambahkan data
    $row = 2;
    foreach ($data as $rowData) {
        $column = 1;
        foreach ($rowData as $key => $cellData) {
            // Konversi format tanggal
            if ($key === 'tanggal_lahir' || $key === 'tanggal_pengukuran') {
                $cellData = date('Y-m-d', strtotime(str_replace('/', '-', $cellData)));
            }
            
            // Cek apakah kolom saat ini adalah 'nik'
            if ($key === 'nik') {
                // Set nilai sebagai string dan format sel sebagai teks
                $sheet->setCellValueExplicit(
                    [$column, $row],
                    $cellData,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $sheet->getStyle([$column, $row])->getNumberFormat()->setFormatCode('@');
            } else {
                $sheet->setCellValue([$column, $row], $cellData);
            }
            $column++;
        }
        $row++;
    }

    // Auto-size kolom
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. $filename .'.xlsx"');
    $writer->save('php://output');
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Gunakan output buffering untuk menangani BOM dan konten
    ob_start();
    
    // Tambahkan BOM untuk UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Tulis headers
    fputcsv($output, array_keys($data[0]));
    
    // Tulis data
    foreach ($data as $row) {
        $modifiedRow = [];
        foreach ($row as $key => $value) {
            if ($key === 'nik') {
                // Pastikan NIK diperlakukan sebagai string
                $modifiedRow[] = "'" . $value;
            } elseif ($key === 'tanggal_lahir' || $key === 'tanggal_pengukuran') {
                // Konversi format tanggal
                $date = DateTime::createFromFormat('d/m/Y', $value);
                if ($date) {
                    $modifiedRow[] = $date->format('Y-m-d');
                } else {
                    $modifiedRow[] = $value; // Jika format tidak sesuai, gunakan nilai asli
                }
            } else {
                $modifiedRow[] = $value;
            }
        }
        fputcsv($output, $modifiedRow);
    }
    
    fclose($output);
    
    // Ambil konten dari buffer dan kirimkan
    $csvContent = ob_get_clean();
    echo $csvContent;
    exit;
}

function filterDataForPDF($data) {
    $filteredData = [];
    foreach ($data as $row) {
        unset($row['id_balita']);
        unset($row['id_pengukuran']);
        unset($row['no']);
        
        // Konversi format tanggal
        if (isset($row['tanggal_lahir'])) {
            $row['tanggal_lahir'] = date('Y-m-d', strtotime($row['tanggal_lahir']));
        }
        if (isset($row['tanggal_pengukuran'])) {
            $row['tanggal_pengukuran'] = date('Y-m-d', strtotime($row['tanggal_pengukuran']));
        }
        
        $filteredData[] = $row;
    }
    return $filteredData;
}

function exportToPDF($data, $filename) {
    $filteredData = filterDataForPDF($data);
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 6);  // Ukuran font dikurangi
            $this->Cell(0, 6, 'Data Balita', 0, 1, 'C');
            $this->Ln(1);
        }

        function Footer() {
            $this->SetY(-10);  // Posisi footer dinaikkan
            $this->SetFont('Arial', 'I', 5);  // Ukuran font dikurangi
            $this->Cell(0, 5, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function NbLines($w, $txt) {
            $cw = &$this->CurrentFont['cw'];
            if($w==0)
                $w = $this->w-$this->rMargin-$this->x;
            $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
            $s = str_replace("\r",'',$txt);
            $nb = strlen($s);
            if($nb>0 && $s[$nb-1]=="\n")
                $nb--;
            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $nl = 1;
            while($i<$nb) {
                $c = $s[$i];
                if($c=="\n") {
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                    continue;
                }
                if($c==' ')
                    $sep = $i;
                $l += $cw[$c];
                if($l>$wmax) {
                    if($sep==-1) {
                        if($i==$j)
                            $i++;
                    }
                    else
                        $i = $sep+1;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                }
                else
                    $i++;
            }
            return $nl;
        }
    }

    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->SetMargins(5, 5, 5);  // Margin dikurangi
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 6);  // Ukuran font dikurangi

    // Hitung lebar kolom
    $headers = array_keys($filteredData[0]);
    $column_widths = array();
    foreach ($headers as $header) {
        $column_widths[$header] = $pdf->GetStringWidth($header) + 4;
    }
    foreach ($filteredData as $row) {
        foreach ($row as $key => $value) {
            $width = $pdf->GetStringWidth($value) + 4;
            if ($width > $column_widths[$key]) {
                $column_widths[$key] = $width;
            }
        }
    }


    // Hitung total lebar tabel
    $total_width = array_sum($column_widths);
    $page_width = $pdf->GetPageWidth() - 20;

    // Jika total lebar melebihi lebar halaman, sesuaikan lebar kolom
    if ($total_width > $page_width) {
        $scale = $page_width / $total_width;
        foreach ($column_widths as &$width) {
            $width *= $scale;
        }
    }

    // Cetak headers
    $pdf->SetFillColor(200, 220, 255);
    foreach ($headers as $header) {
        $pdf->Cell($column_widths[$header], 5, $header, 1, 0, 'C', true);  // Tinggi sel dikurangi
    }
    $pdf->Ln();

    // Cetak data
    $pdf->SetFont('Arial', '', 6);  // Ukuran font dikurangi
    foreach ($data as $row) {
        $max_height = 3;  // Tinggi minimum sel dikurangi
        $line_heights = array();
 
        // Hitung tinggi maksimum yang dibutuhkan
        foreach ($headers as $header) {
            $pdf->SetFont('Arial', '', 6);  // Ukuran font dikurangi
            $line_heights[$header] = $pdf->NbLines($column_widths[$header], $row[$header]);
            $cell_height = $line_heights[$header] * 3;  // Tinggi sel dikurangi
            $max_height = max($max_height, $cell_height);
        }

        // Cek apakah perlu pindah ke halaman baru
        if ($pdf->GetY() + $max_height > $pdf->GetPageHeight() - 15) {  // Batas bawah halaman dinaikkan
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 6);  // Ukuran font dikurangi
            foreach ($headers as $header) {
                $pdf->Cell($column_widths[$header], 5, $header, 1, 0, 'C', true);  // Tinggi sel dikurangi
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 6);  // Ukuran font dikurangi
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        foreach ($headers as $header) {
            $pdf->MultiCell($column_widths[$header], 3, $row[$header], 1);  // Tinggi sel dikurangi
            $pdf->SetXY($x + $column_widths[$header], $y);
            $x += $column_widths[$header];
        }
        $pdf->Ln($max_height);
    }

    $pdf->Output('D', $filename . '.pdf');
}

function importFromCSV($file, $db) {
    if (empty($file) || !file_exists($file)) {
        return ['success' => false, 'message' => 'File tidak ditemukan'];
    }
    
    $data = [];
    $incompleteData = false;
    if (($handle = fopen($file, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row = array_combine($headers, $row);
            
            // Periksa kelengkapan data
            foreach ($row as $key => $value) {
                if (empty($value)) {
                    $incompleteData = true;
                    $row[$key] = null; // Biarkan nilai null untuk ditangani oleh database
                }
            }
            
            // Konversi format tanggal
            if (!empty($row['tanggal_lahir'])) {
                $row['tanggal_lahir'] = date('Y-m-d', strtotime(str_replace('/', '-', $row['tanggal_lahir'])));
            }
            if (!empty($row['tanggal_pengukuran'])) {
                $row['tanggal_pengukuran'] = date('Y-m-d', strtotime(str_replace('/', '-', $row['tanggal_pengukuran'])));
            }
            
            $data[] = $row;
        }
        fclose($handle);
    }

    $db->beginTransaction();
    try {
        foreach ($data as $row) {
            // Prepare data for balita_2 table
            $balitaData = [
                'nama_balita' => $row['nama_balita'] ?? null,
                'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                'nik' => $row['nik'] ?? null,
                'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                'berat_badan_lahir' => $row['berat_badan_lahir'] ?? null,
                'nama_ayah' => $row['nama_ayah'] ?? null,
                'nama_ibu' => $row['nama_ibu'] ?? null,
                'alamat' => $row['alamat'] ?? null,
                'status' => $row['status'] ?? null
            ];

            // Insert into balita_2 table
            $sql = "INSERT INTO balita_2 (nama_balita, jenis_kelamin, nik, tanggal_lahir, berat_badan_lahir, nama_ayah, nama_ibu, alamat, status) 
                    VALUES (:nama_balita, :jenis_kelamin, :nik, :tanggal_lahir, :berat_badan_lahir, :nama_ayah, :nama_ibu, :alamat, :status)";
            $stmt = $db->prepare($sql);
            $stmt->execute($balitaData);

            $id_balita = $db->lastInsertId();

            // Prepare data for pengukuran_balita_2 table
            if (!empty($row['tanggal_pengukuran']) && !empty($row['berat_badan']) && !empty($row['tinggi_badan'])) {
                $pengukuranData = [
                    'id_balita' => $id_balita,
                    'tanggal_pengukuran' => $row['tanggal_pengukuran'],
                    'berat_badan' => $row['berat_badan'],
                    'tinggi_badan' => $row['tinggi_badan'],
                    'status_gizi' => $row['status_gizi'] ?? null,
                    'bulan' => !empty($row['bulan']) ? strtolower($row['bulan']) : null
                ];

                // Validasi bulan
                $validMonths = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
                if (!in_array($pengukuranData['bulan'], $validMonths)) {
                    $pengukuranData['bulan'] = null;
                }

                // Insert into pengukuran_balita_2 table
                $sql = "INSERT INTO pengukuran_balita_2 (id_balita, tanggal_pengukuran, berat_badan, tinggi_badan, status_gizi, bulan) 
                        VALUES (:id_balita, :tanggal_pengukuran, :berat_badan, :tinggi_badan, :status_gizi, :bulan)";
                $stmt = $db->prepare($sql);
                $stmt->execute($pengukuranData);
            }
        }
        $db->commit();
        $message = $incompleteData ? "Data berhasil diimpor, namun beberapa data tidak lengkap." : "Data berhasil diimpor.";
        return ['success' => true, 'message' => $message];
    } catch (PDOException $e) {
        $db->rollBack();
        return ['success' => false, 'message' => "Gagal mengimpor data: " . $e->getMessage()];
    }
}

function importFromExcel($file, $db) {
    logMessage("Memulai impor Excel");
    if (empty($file) || !file_exists($file)) {
        logMessage("File tidak ditemukan");
        return ['success' => false, 'message' => 'File tidak ditemukan'];
    }
    
    try {
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellCoordinate = Coordinate::stringFromColumnIndex($col) . '1';
            $headers[] = $worksheet->getCell($cellCoordinate)->getValue();
        }

        $data = [];
        $incompleteData = false;
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                $value = $worksheet->getCell($cellCoordinate)->getValue();
                if (empty($value)) {
                    $incompleteData = true;
                    $value = null;
                }
                $rowData[] = $value;
            }
            $data[] = array_combine($headers, $rowData);
        }

        $db->beginTransaction();
        foreach ($data as $row) {
            // Konversi format tanggal
            if (!empty($row['tanggal_lahir'])) {
                $row['tanggal_lahir'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['tanggal_lahir'])->format('Y-m-d');
            }
            if (!empty($row['tanggal_pengukuran'])) {
                $row['tanggal_pengukuran'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['tanggal_pengukuran'])->format('Y-m-d');
            }

            // Prepare data for balita_2 table
            $balitaData = [
                'nama_balita' => $row['nama_balita'] ?? null,
                'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                'nik' => $row['nik'] ?? null,
                'tanggal_lahir' => $row['tanggal_lahir'] ?? null,
                'berat_badan_lahir' => $row['berat_badan_lahir'] ?? null,
                'nama_ayah' => $row['nama_ayah'] ?? null,
                'nama_ibu' => $row['nama_ibu'] ?? null,
                'alamat' => $row['alamat'] ?? null,
                'status' => $row['status'] ?? null
            ];

            // Insert into balita_2 table
            $sql = "INSERT INTO balita_2 (nama_balita, jenis_kelamin, nik, tanggal_lahir, berat_badan_lahir, nama_ayah, nama_ibu, alamat, status) 
                    VALUES (:nama_balita, :jenis_kelamin, :nik, :tanggal_lahir, :berat_badan_lahir, :nama_ayah, :nama_ibu, :alamat, :status)";
            $stmt = $db->prepare($sql);
            $stmt->execute($balitaData);

            $id_balita = $db->lastInsertId();

            // Prepare data for pengukuran_balita_2 table
            if (!empty($row['tanggal_pengukuran']) && !empty($row['berat_badan']) && !empty($row['tinggi_badan'])) {
                $pengukuranData = [
                    'id_balita' => $id_balita,
                    'tanggal_pengukuran' => $row['tanggal_pengukuran'],
                    'berat_badan' => $row['berat_badan'],
                    'tinggi_badan' => $row['tinggi_badan'],
                    'status_gizi' => $row['status_gizi'] ?? null,
                    'bulan' => !empty($row['bulan']) ? strtolower($row['bulan']) : null
                ];

                // Validasi bulan
                $validMonths = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
                if (!in_array($pengukuranData['bulan'], $validMonths)) {
                    $pengukuranData['bulan'] = null;
                }

                // Insert into pengukuran_balita_2 table
                $sql = "INSERT INTO pengukuran_balita_2 (id_balita, tanggal_pengukuran, berat_badan, tinggi_badan, status_gizi, bulan) 
                        VALUES (:id_balita, :tanggal_pengukuran, :berat_badan, :tinggi_badan, :status_gizi, :bulan)";
                $stmt = $db->prepare($sql);
                $stmt->execute($pengukuranData);
            }
        }
        $db->commit();

        logMessage("Impor Excel selesai");
        $message = $incompleteData ? "Data berhasil diimpor dari Excel, namun beberapa data tidak lengkap." : "Data berhasil diimpor dari Excel.";
        return ['success' => true, 'message' => $message];
    } catch (Exception $e) {
        $db->rollBack();
        logMessage("Error saat mengimpor Excel: " . $e->getMessage());
        return ['success' => false, 'message' => "Gagal mengimpor data dari Excel: " . $e->getMessage()];
    }
}

function printTable($data) {
    if (empty($data)) {
        echo "<p>Tidak ada data yang tersedia.</p>";
        return;
    }

    echo "<table border='1'>";
    
    echo "<tr>";
    foreach(array_keys($data[0]) as $header) {
        echo "<th>$header</th>";
    }
    echo "</tr>";
    
    foreach($data as $row) {
        echo "<tr>";
        foreach($row as $cell) {
            echo "<td>$cell</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
}

function printData($data) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Data Balita dan Pengukuran</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #c8e0ff; }
            h1 { text-align: center; }
        </style>
    </head>
    <body>
        <h1>Data Balita dan Pengukuran</h1>
        <table>';

    // Print headers
    echo '<tr>';
    foreach (array_keys($data[0]) as $header) {
        echo "<th>$header</th>";
    }
    echo '</tr>';

    // Print data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo "<td>$cell</td>";
        }
        echo '</tr>';
    }

    echo '</table>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>';
}

// Handle export, import, dan print requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $filename = "balita_data_" . date('Y-m-d');
    
    $exportData = prepareDataForExport($balitaData);
    
    switch ($action) {
        case 'export_excel':
            ob_end_clean();
            exportToExcel($exportData, $filename);
            exit;
        case 'export_csv':
            ob_end_clean();
            exportToCSV($exportData, $filename);
            exit;
        case 'export_pdf':
            ob_end_clean();
            exportToPDF($exportData, $filename);
            exit;
            case 'import':
                if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
                    $fileType = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);
                    if (strtolower($fileType) == 'csv') {
                        $result = importFromCSV($_FILES['import_file']['tmp_name'], $db);
                    } elseif (in_array(strtolower($fileType), ['xlsx', 'xls'])) {
                        $result = importFromExcel($_FILES['import_file']['tmp_name'], $db);
                    } else {
                        $result = ['success' => false, 'message' => "Format file tidak didukung. Silakan unggah file CSV atau Excel."];
                    }
                    $_SESSION['message'] = $result['message'];
                } else {
                    $_SESSION['message'] = "Silakan pilih file untuk diimpor terlebih dahulu.";
                }
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;

        case 'print':
            ob_end_clean();
            printData($exportData);
            exit;
    }
}

// Jika tidak ada aksi ekspor, lanjutkan dengan output HTML
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balita P2 EIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Data Balita dan Pengukuran Posyandu Cempaka 2</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <select name="id_balita" class="form-select">
                        <option value="">Semua Balita</option>
                        <?php foreach ($balitas as $balita): ?>
                            <option value="<?php echo $balita['id_balita']; ?>" <?php echo ($balita['id_balita'] == $id_balita) ? 'selected' : ''; ?>>
                                <?php echo $balita['nama_balita']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="bulan" class="form-select">
                        <option value="">Semua Bulan</option>
                        <?php foreach ($months as $month): ?>
                            <option value="<?php echo $month; ?>" <?php echo ($month == $bulan) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($month); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Tampilkan Data</button>
                </div>
            </div>
        </form>

        <h2 class="mb-3">Data Balita dan Pengukuran</h2>
        <div class="table-responsive">
            <?php
            if (!empty($balitaData)) {
                printTable($balitaData);
            } else {
                echo "<p>Data balita tidak tersedia.</p>";
            }
            ?>
        </div>

        <h2 class="mt-5 mb-3">Aksi Data</h2>
<form method="POST" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-4">
        <select name="action" class="form-select" id="actionSelect">
            <option value="export_excel">Export Excel</option>
            <option value="export_csv">Export CSV</option>
            <option value="export_pdf">Export PDF</option>
            <option value="import_csv">Import CSV</option>
            <option value="import_excel">Import Excel</option>
            <option value="print">Cetak</option>
        </select>
    </div>
    <div class="col-md-4">
        <input type="file" name="import_file" id="importFile" class="form-control" style="display: none;">
    </div>
    <div class="col-md-4">
        <button type="submit" class="btn btn-success">Proses</button>
    </div>
</form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const actionSelect = document.getElementById('actionSelect');
    const importFile = document.getElementById('importFile');

    actionSelect.addEventListener('change', function() {
        if (this.value === 'import_csv' || this.value === 'import_excel') {
            importFile.style.display = 'block';
            importFile.setAttribute('accept', this.value === 'import_csv' ? '.csv' : '.xlsx,.xls');
        } else {
            importFile.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
<?php
ob_end_flush();
?>