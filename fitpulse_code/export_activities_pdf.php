<?php
// export_activities_pdf.php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid  = (int)$_SESSION['user_id'];
$name = $_SESSION['user_name'] ?? 'User';

// =======================
// LOAD FPDF
// =======================
// Pastikan sudah ada folder fpdf di dalam project:
//   fitpulse_u/fpdf/fpdf.php
// File fpdf.php bisa di-download dari http://www.fpdf.org/
require_once __DIR__ . '/fpdf/fpdf.php';

// =======================
// AMBIL DATA AKTIVITAS USER
// =======================
$rows = [];
$stmt = $conn->prepare("
    SELECT ActivityDate, SportType, DurationMinutes, Points, COALESCE(Notes,'') AS Notes
    FROM Activities
    WHERE UserId = ?
    ORDER BY ActivityDate DESC, Id DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$stmt->close();

// =======================
// BUAT PDF
// =======================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// JUDUL
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, 'LAPORAN RIWAYAT AKTIVITAS', 0, 1, 'C');
$pdf->Ln(2);

// INFO USER
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(30, 6, 'Nama User', 0, 0);
$pdf->Cell(3, 6, ':', 0, 0);
$pdf->Cell(0, 6, $name, 0, 1);

$pdf->Cell(30, 6, 'Tanggal Cetak', 0, 0);
$pdf->Cell(3, 6, ':', 0, 0);
$pdf->Cell(0, 6, date('d-m-Y H:i'), 0, 1);

$pdf->Ln(4);

// HEADER TABEL
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(34, 197, 94);      // hijau
$pdf->SetTextColor(255, 255, 255);    // putih

$pdf->Cell(30, 8, 'Tanggal', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Jenis Aktivitas', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Durasi', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Poin', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'Catatan', 1, 1, 'C', true);

// ISI TABEL
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);

if (empty($rows)) {
    $pdf->Cell(190, 8, 'Belum ada aktivitas yang tercatat.', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $tgl   = date('d-m-Y', strtotime($row['ActivityDate']));
        $jenis = $row['SportType'];
        $dur   = (int)$row['DurationMinutes'] . ' mnt';
        $poin  = (int)$row['Points'];
        $note  = $row['Notes'];

        // Simpan posisi awal
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Hitung tinggi baris untuk catatan (maksimal dua baris sederhana)
        // Supaya rapi, pakai MultiCell untuk kolom catatan
        $pdf->Cell(30, 8, $tgl,   1, 0, 'C');
        $pdf->Cell(40, 8, $jenis, 1, 0, 'L');
        $pdf->Cell(20, 8, $dur,   1, 0, 'C');
        $pdf->Cell(20, 8, $poin,  1, 0, 'C');

        $pdf->MultiCell(80, 8, $note, 1, 'L');
    }
}

// OUTPUT PDF
$filename = 'Riwayat_Aktivitas_' . preg_replace('/\s+/', '_', strtolower($name)) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $filename);
exit;
