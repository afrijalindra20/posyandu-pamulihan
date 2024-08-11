<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../pos_2/header_balita_p2.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use FPDF\FPDF;

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle form submission
$id_balita = isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : 1;
$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : 'januari'; 

// Fetch data untuk id_balita dan bulan yang dipilih
$balitaData = getBalita2AndPengukuran($db, $id_balita);

// Fetch list balita untuk dropdown
$balitas = $db->query("SELECT id_balita, nama_balita FROM balita_2")->fetchAll(PDO::FETCH_ASSOC);

// List bulan untuk dropdown
$months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

function prepareDataForExport($balitaData) {
    return [$balitaData[0]];
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
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    
    // Tambahkan BOM untuk UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Tulis headers
    fputcsv($output, array_keys($data[0]), ',', '"', '\\');
    
    // Tulis data
    foreach ($data as $row) {
        $modifiedRow = [];
        foreach ($row as $key => $value) {
            if ($key === 'nik') {
                // Pastikan NIK diperlakukan sebagai string tanpa tanda kutip
                $modifiedRow[] = '\'' . $value;
            } else {
                $modifiedRow[] = $value;
            }
        }
        fputcsv($output, $modifiedRow, ',', '"', '\\');
    }
    
    fclose($output);
}

function filterDataForPDF($data) {
    $filteredData = [];
    foreach ($data as $row) {
        unset($row['id_balita']);
        unset($row['id_pengukuran']);
        $filteredData[] = $row;
    }
    return $filteredData;
}

function exportToPDF($data, $filename) {
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 10, 'Data Balita', 0, 1, 'C');
            $this->Ln(2);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 7);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
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
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 7);

    // Hitung lebar kolom
    $headers = array_keys($data[0]);
    $column_widths = array();
    foreach ($headers as $header) {
        $column_widths[$header] = $pdf->GetStringWidth($header) + 4;
    }
    foreach ($data as $row) {
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
        $pdf->Cell($column_widths[$header], 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Cetak data
    $pdf->SetFont('Arial', '', 7);
    foreach ($data as $row) {
        $max_height = 4;
        $line_heights = array();

        // Hitung tinggi maksimum yang dibutuhkan
        foreach ($headers as $header) {
            $pdf->SetFont('Arial', '', 7);
            $line_heights[$header] = $pdf->NbLines($column_widths[$header], $row[$header]);
            $cell_height = $line_heights[$header] * 4;
            $max_height = max($max_height, $cell_height);
        }

        // Cek apakah perlu pindah ke halaman baru
        if ($pdf->GetY() + $max_height > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 8);
            foreach ($headers as $header) {
                $pdf->Cell($column_widths[$header], 7, $header, 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 7);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        foreach ($headers as $header) {
            $pdf->MultiCell($column_widths[$header], 4, $row[$header], 1);
            $pdf->SetXY($x + $column_widths[$header], $y);
            $x += $column_widths[$header];
        }
        $pdf->Ln($max_height);
    }

    $pdf->Output('D', $filename . '.pdf');
}

function importFromCSV($file) {
    $data = [];
    if (($handle = fopen($file, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    return $data;
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
    $filteredData = filterDataForPDF($data);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Data Balita</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #c8e0ff; }
            h1 { text-align: center; }
        </style>
    </head>
    <body>
        <h1>Data Balita</h1>
        <table>';

    // Print headers
    echo '<tr>';
    foreach (array_keys($filteredData[0]) as $header) {
        echo "<th>$header</th>";
    }
    echo '</tr>';

    // Print data
    foreach ($filteredData as $row) {
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
            $filteredData = filterDataForPDF($exportData);
            exportToPDF($filteredData, $filename);
            exit;
        case 'import':
            if (isset($_FILES['import_file'])) {
                $importedData = importFromCSV($_FILES['import_file']['tmp_name']);
                // Proses $importedData sesuai kebutuhan (misalnya, masukkan ke database)
                $_SESSION['message'] = "Data berhasil diimpor.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            break;
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
</head>
<body>
    <h1>Data Balita Posyandu Cempaka 2</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <p><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></p>
    <?php endif; ?>

    <form method="POST">
        <select name="id_balita">
            <?php foreach ($balitas as $balita): ?>
                <option value="<?php echo $balita['id_balita']; ?>" <?php echo ($balita['id_balita'] == $id_balita) ? 'selected' : ''; ?>>
                    <?php echo $balita['nama_balita']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="bulan">
            <?php foreach ($months as $month): ?>
                <option value="<?php echo $month; ?>" <?php echo ($month == $bulan) ? 'selected' : ''; ?>>
                    <?php echo ucfirst($month); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" value="Tampilkan Data">
    </form>

    <h2>Data Balita</h2>
    <?php
    if (!empty($balitaData)) {
        printTable([$balitaData[0]]);
    } else {
        echo "<p>Data balita tidak tersedia.</p>";
    }
    ?>

    <h2>Aksi Data</h2>
    <form method="POST" enctype="multipart/form-data">
        <select name="action">
            <option value="export_excel">Export Excel</option>
            <option value="export_csv">Export CSV</option>
            <option value="export_pdf">Export PDF</option>
            <option value="import">Import CSV</option>
            <option value="print">Cetak</option>
        </select>
        <input type="file" name="import_file" accept=".csv">
        <input type="submit" value="Proses">
    </form>
</body>
</html>
<?php
ob_end_flush();
?>