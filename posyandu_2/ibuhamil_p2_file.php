<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_2/header_ih_p2.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use FPDF\FPDF;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Definisikan kelas PDF di sini
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30,10,'Data Ibu Hamil dan Catatan Kehamilan',0,0,'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Halaman '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

// Hapus atau komentari baris ini
// $pdf = new Fpdf();

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
        if ($action == 'import_csv' || $action == 'import_ibu_hamil_csv') {
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['import_file']['tmp_name'];
                if ($action == 'import_csv') {
                    $result = importFromCSV($uploadedFile, $db);
                } else {
                    $result = importIbuHamilFromCSV($uploadedFile, $db);
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
            $id_ibu = isset($_POST['id_ibu']) ? (int)$_POST['id_ibu'] : 1;
            $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';

            $ibuHamilData = getIbuHamil2AndCatatanKehamilan($db, $id_ibu);
            if ($bulan === 'all') {
                $catatanKehamilanData = getAllCatatan2($db, $id_ibu);
            } else {
                $catatanKehamilanData = getCatatanKehamilanByBulan2($db, $id_ibu, $bulan);
            }

            $namaIbu = !empty($ibuHamilData) ? $ibuHamilData[0]['nama_ibu_hamil'] : 'Unknown';
            $filename = "ibu_hamil_data_" . $namaIbu . "_" . ($bulan === 'all' ? 'semua_bulan' : $bulan) . "_" . date('Y-m-d');

            $exportInfo = prepareDataForExport($ibuHamilData, $catatanKehamilanData);

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
                        unlink($filename . '.pdf');
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
$id_ibu = isset($_POST['id_ibu']) ? (int)$_POST['id_ibu'] : 1;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';

// Fetch data for the selected id_ibu and bulan
$ibuHamilData = getIbuHamil2AndCatatanKehamilan($db, $id_ibu);
if ($bulan === 'all') {
    $catatanKehamilanData = getAllCatatan2($db, $id_ibu);
} else {
    $catatanKehamilanData = getCatatanKehamilanByBulan2($db, $id_ibu, $bulan);
}

// Fetch list of ibu hamil for dropdown
$ibuHamils = $db->query("SELECT id_ibu, nama_ibu_hamil FROM ibu_hamil_2")->fetchAll(PDO::FETCH_ASSOC);

// List of months for dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

function prepareDataForExport($ibuHamilData, $catatanKehamilanData) {
    $exportData = [];
    $catatanCount = 0;
    
    if (!empty($ibuHamilData)) {
        $ibuHamil = $ibuHamilData[0];
        
        // Data ibu hamil
        $exportData[] = ['Data Ibu Hamil'];
        $exportData[] = ['ID Ibu', $ibuHamil['id_ibu']];
        $exportData[] = ['Nama Ibu', $ibuHamil['nama_ibu_hamil']];
        $exportData[] = ['NIK', $ibuHamil['nik']];
        $exportData[] = ['Tanggal Lahir Ibu Hamil', $ibuHamil['tempat_tanggal_lahir_ibu']];
        $exportData[] = ['Nama Suami', $ibuHamil['nama_suami']];
        $exportData[] = ['Nik Suami', $ibuHamil['nik_suami']];
        $exportData[] = ['Tanggal Lahir Suami', $ibuHamil['tempat_tanggal_lahir_suami']];
        $exportData[] = ['Alamat', $ibuHamil['alamat']];
        
        $exportData[] = [];
        
        // Data catatan kehamilan
        $exportData[] = ['Data Catatan Kehamilan'];
        
        if (!empty($catatanKehamilanData)) {
            $exportData[] = [
                'Hamil Ke',
                'HPHT',
                'HPL',
                'Usia Hamil',
                'Status Hamil',
                'Tinggi Badan',
                'Berat Badan',
                'LILA',
                'Laboratorium',
                'Imunisasi',
                'Ada Bantuan',
                'Ada BPJS',
                'Bulan'
            ];
            
            foreach ($catatanKehamilanData as $catatan) {
                $exportData[] = [
                    $catatan['hamil_keberapa'],
                    $catatan['hpht'],
                    $catatan['hpl'],
                    $catatan['usia_kehamilan'],
                    $catatan['status_kehamilan'],
                    $catatan['tinggi_badan'],
                    $catatan['berat_badan'],
                    $catatan['lila'],
                    $catatan['laboratorium'],
                    $catatan['imunisasi'],
                    $catatan['mendapatkan_bantuan'],
                    $catatan['mempunyai_bpjs'],
                    $catatan['bulan']
                ];
                $catatanCount++;
            }
        } else {
            $exportData[] = ['Tidak ada data catatan kehamilan untuk ibu hamil ini.'];
        }
    }
    
    return ['data' => $exportData, 'catatanCount' => $catatanCount];
}

function exportToExcel($exportInfo, $filename) {
    $data = $exportInfo['data'];
    $catatanCount = $exportInfo['catatanCount'];

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
    if ($catatanCount > 0) {
        $sheet->getStyle('A' . ($row - $catatanCount - 1) . ':M' . ($row - $catatanCount - 1))->applyFromArray($headerStyle);
    }

    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. $filename .'.xlsx"');
    $writer->save('php://output');
}

function exportToPDF($exportInfo, $filename) {
    $data = $exportInfo['data'];
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Data Ibu Hamil
    foreach ($data as $row) {
        if ($row[0] === 'Data Ibu Hamil') {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif ($row[0] === 'Data Catatan Kehamilan') {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif (count($row) === 2) {
            $pdf->Cell(50, 6, $row[0] . ':', 0);
            $pdf->Cell(0, 6, $row[1], 0, 1);
        } elseif (count($row) === 13) {
            // PERUBAHAN: Ukuran font untuk header catatan kehamilan dikurangi menjadi 6
            $pdf->SetFont('Arial', 'B', 6);
            $cellWidth = 190 / 13;
            foreach ($row as $cell) {
                // PERUBAHAN: Tinggi sel dikurangi menjadi 6
                $pdf->Cell($cellWidth, 6, $cell, 1, 0, 'C');
            }
            $pdf->Ln();
            // PERUBAHAN: Ukuran font untuk data catatan kehamilan dikurangi menjadi 6
            $pdf->SetFont('Arial', '', 6);
        } elseif (count($row) === 13) {
            // Data catatan kehamilan
            $cellWidth = 190 / 13;
            foreach ($row as $cell) {
                // PERUBAHAN: Tinggi sel dikurangi menjadi 5
                $pdf->Cell($cellWidth, 5, $cell, 1, 0, 'C');
            }
            $pdf->Ln();
        } elseif (count($row) === 1 && $row[0] === 'Tidak ada data catatan kehamilan untuk ibu hamil ini.') {
            $pdf->Cell(0, 8, $row[0], 0, 1);
        }
    }

    $pdf->Output('F', $filename . '.pdf');
}

function printData($exportInfo) {
    // Bersihkan semua output sebelumnya
    ob_clean();

    if (empty($exportInfo['data'])) {
        echo "Tidak ada data untuk dicetak.";
        return;
    }

    $data = $exportInfo['data'];
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    

    // Data Ibu Hamil
    foreach ($data as $row) {
        if (!is_array($row)) continue; // Skip jika bukan array

        if ($row[0] === 'Data Ibu Hamil') {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif ($row[0] === 'Data Catatan Kehamilan') {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $row[0], 0, 1);
            $pdf->SetFont('Arial', '', 10);
        } elseif (count($row) === 2) {
            $pdf->Cell(50, 6, $row[0] . ':', 0);
            $pdf->Cell(0, 6, $row[1], 0, 1);
        } elseif (count($row) === 13) {
            // Header untuk catatan kehamilan
            $pdf->SetFont('Arial', 'B', 6);
            $cellWidth = 190 / 13;
            foreach ($row as $cell) {
                $pdf->Cell($cellWidth, 6, $cell, 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 6);
        } elseif (count($row) === 13) {
            // Data catatan kehamilan
            $cellWidth = 190 / 13;
            foreach ($row as $cell) {
                $pdf->Cell($cellWidth, 5, $cell, 1, 0, 'C');
            }
            $pdf->Ln();
        } elseif (count($row) === 1 && $row[0] === 'Tidak ada data catatan kehamilan untuk ibu hamil ini.') {
            $pdf->Cell(0, 8, $row[0], 0, 1);
        }
    }

    // Pastikan tidak ada output lain setelah ini
    ob_end_clean();

    // Keluarkan PDF
    $pdf->Output('I', 'Data_Ibu_Hamil.pdf');
    exit;
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

            $stmt = $db->prepare("INSERT INTO catatan_kehamilan_2 (id_ibu, hamil_keberapa, hpht, hpl, usia_kehamilan, status_kehamilan, tinggi_badan, berat_badan, lila, laboratorium, imunisasi, mendapatkan_bantuan, mempunyai_bpjs, bulan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $lineNumber = 2;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                logImport("Membaca baris $lineNumber: " . implode(", ", $data));
                
                if (count($data) != 14) {
                    $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
                    logImport("Error: Jumlah kolom tidak sesuai pada baris $lineNumber");
                    continue;
                }

                $id_ibu = filter_var($data[0], FILTER_VALIDATE_INT);
                $hamil_keberapa = filter_var($data[1], FILTER_VALIDATE_INT);
                $hpht = date('Y-m-d', strtotime($data[2]));
                $hpl = date('Y-m-d', strtotime($data[3]));
                $usia_kehamilan = filter_var($data[4], FILTER_VALIDATE_INT);
                $status_kehamilan = trim($data[5]);
                $tinggi_badan = filter_var($data[6], FILTER_VALIDATE_FLOAT);
                $berat_badan = filter_var($data[7], FILTER_VALIDATE_FLOAT);
                $lila = filter_var($data[8], FILTER_VALIDATE_FLOAT);
                $laboratorium = trim($data[9]);
                $imunisasi = trim($data[10]);
                $mendapatkan_bantuan = trim($data[11]);
                $mempunyai_bpjs = trim($data[12]);
                $bulan = trim($data[13]);

                if ($id_ibu === false || $hamil_keberapa === false || $usia_kehamilan === false || 
                    $tinggi_badan === false || $berat_badan === false || $lila === false) {
                    $errors[] = "Baris $lineNumber: Format data tidak valid";
                    logImport("Error: Format data tidak valid pada baris $lineNumber");
                    continue;
                }

                if (!$stmt->execute([$id_ibu, $hamil_keberapa, $hpht, $hpl, $usia_kehamilan, $status_kehamilan, 
                                     $tinggi_badan, $berat_badan, $lila, $laboratorium, $imunisasi, 
                                     $mendapatkan_bantuan, $mempunyai_bpjs, $bulan])) {
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

function importIbuHamilFromCSV($filename, $db) {
    logImport("Mulai impor file ibu hamil: $filename");
    $successCount = 0;
    $errors = [];

    try {
        if (($handle = fopen($filename, "r")) !== FALSE) {
            logImport("File ibu hamil berhasil dibuka");
            fgetcsv($handle, 1000, ","); // Skip header row
            
            $db->beginTransaction();
            logImport("Transaksi database dimulai");

            $stmt = $db->prepare("INSERT INTO ibu_hamil_2 (id_ibu, nama_ibu_hamil, nik, tempat_tanggal_lahir_ibu, nama_suami, nik_suami, tempat_tanggal_lahir_suami, alamat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $lineNumber = 2;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                logImport("Membaca baris $lineNumber: " . implode(", ", $data));
                
                if (count($data) != 8) {
                    $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
                    logImport("Error: Jumlah kolom tidak sesuai pada baris $lineNumber");
                    continue;
                }

                $id_ibu = filter_var($data[0], FILTER_VALIDATE_INT);
                $nama_ibu_hamil = trim($data[1]);
                $nik = trim($data[2]);
                $tempat_tanggal_lahir_ibu = date('Y-m-d', strtotime($data[3]));
                $nama_suami = trim($data[4]);
                $nik_suami = trim($data[5]);
                $tempat_tanggal_lahir_suami = trim($data[6]);
                $alamat = trim($data[7]);

                if ($id_ibu === false) {
                    $errors[] = "Baris $lineNumber: Format data tidak valid";
                    logImport("Error: Format data tidak valid pada baris $lineNumber");
                    continue;
                }

                if (!$stmt->execute([$id_ibu, $nama_ibu_hamil, $nik, $tempat_tanggal_lahir_ibu, $nama_suami, $nik_suami, $nik, $tempat_tanggal_lahir_suami, $alamat])) {
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
                logImport("Transaksi berhasil. $successCount data ibu hamil diimpor.");
                return ["success" => true, "message" => "$successCount data ibu hamil berhasil diimpor."];
            } else {
                $db->rollBack();
                logImport("Transaksi dibatalkan karena ada error.");
                return ["success" => false, "message" => "Impor ibu hamil gagal. " . implode("; ", $errors)];
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

    logImport("Gagal membuka file CSV ibu hamil.");
    return ["success" => false, "message" => "Gagal membuka file CSV ibu hamil."];
}

function exportToCSV($exportInfo, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Use UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Export Ibu Hamil data
    fputcsv($output, ['Data Ibu Hamil']);
    fputcsv($output, ['ID Ibu', 'Nama Ibu Hamil', 'NIK', 'Tanggal Lahir Ibu Hamil', 'Nama Suami', 'Nik Suami', 'Tempat tanggal lahir suami', 'Alamat']);
    fputcsv($output, [
        $exportInfo['data'][1][1],  // ID Ibu hamil
        $exportInfo['data'][2][1],  // Nama Ibu hamil
        $exportInfo['data'][3][1],  // NIK
        $exportInfo['data'][4][1],  // Tanggal Lahir Ibu Hamil
        $exportInfo['data'][5][1],  // Nama Suami
        $exportInfo['data'][6][1],  // Nik Suami
        $exportInfo['data'][7][1],   // Tempat tanggal lahir suami
        $exportInfo['data'][8][1]  // Alamat
    ]);
    
    fputcsv($output, []);  // Empty row for separation
    
    // Export Catatan Kehamilan data
    fputcsv($output, ['Data Catatan Kehamilan']);
    fputcsv($output, [
        'Hamil Keberapa',
        'HPHT',
        'HPL',
        'Usia Kehamilan',
        'Status Kehamilan',
        'Tinggi Badan',
        'Berat Badan',
        'LILA',
        'Laboratorium',
        'Imunisasi',
        'Mendapatkan Bantuan',
        'Mempunyai BPJS',
        'Bulan'
    ]);
    
    // Assuming catatan kehamilan data starts from index 12 in the exportInfo['data']
    for ($i = 12; $i < count($exportInfo['data']); $i++) {
        if (count($exportInfo['data'][$i]) > 1) {  // Check if it's not a header row
            fputcsv($output, $exportInfo['data'][$i]);
        }
    }
    
    fclose($output);
}

// Handle export, import, dan print requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Pastikan data ibu hamil dan catatan kehamilan diambil lagi berdasarkan POST
    $id_ibu = isset($_POST['id_ibu']) ? (int)$_POST['id_ibu'] : 1;
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'all';
    
    $ibuHamilData = getIbuHamil2AndCatatanKehamilan($db, $id_ibu);
    if ($bulan === 'all') {
        $catatanKehamilanData = getAllCatatan2($db, $id_ibu);
    } else {
        $catatanKehamilanData = getCatatanKehamilanByBulan2($db, $id_ibu, $bulan);
    }
    
    // Ambil nama ibu dari data ibu hamil
    $namaIbu = !empty($ibuHamilData) ? $ibuHamilData[0]['nama_ibu_hamil'] : 'Unknown';
    
    $filename = "ibu_hamil_data_" . $namaIbu . "_" . ($bulan === 'all' ? 'semua_bulan' : $bulan) . "_" . date('Y-m-d');
    
    $exportInfo = prepareDataForExport($ibuHamilData, $catatanKehamilanData);
    
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
            case 'print':
                ob_end_clean();
                ob_start();
                printData($exportInfo);
                $pdfContent = ob_get_clean();
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdfContent));
                header('Content-Disposition: inline; filename="ibu_hamil_data_print.pdf"');
                echo $pdfContent;
                exit;
            case 'export_csv':
                ob_end_clean();
                exportToCSV($exportInfo, $filename);
                exit;
            // Tambahkan case untuk aksi ekspor lainnya di sini jika diperlukan
        }
    } catch (Exception $e) {
        // Log error
        error_log('Error during export: ' . $e->getMessage());
        // Tampilkan pesan error kepada pengguna
        $_SESSION['error'] = "Terjadi kesalahan saat mengekspor data. Silakan coba lagi.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
// Jika tidak ada aksi ekspor, lanjutkan dengan output HTML
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
                    <form method="post">
                        <div class="mb-3">
                            <label for="id_ibu" class="form-label">Pilih Ibu Hamil:</label>
                            <select name="id_ibu" id="id_ibu" class="form-select">
                                <?php foreach ($ibuHamils as $ibuHamil): ?>
                                    <option value="<?php echo htmlspecialchars($ibuHamil['id_ibu']); ?>"
                                        <?php if ($ibuHamil['id_ibu'] == $id_ibu): ?> selected <?php endif; ?>>
                                        <?php echo htmlspecialchars($ibuHamil['nama_ibu_hamil']); ?>
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

       <!-- New section: Aksi Data -->
       <div class="container mb-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Aksi Data</h5>
                </div>
                <div class="card-body">
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="id_ibu" value="<?php echo htmlspecialchars($id_ibu); ?>">
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
require_once __DIR__ . '/../pos_2/footer_ih_p2.php';
ob_end_flush();
?>