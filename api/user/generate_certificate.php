<?php
/**
 * Generate PDF Certificate API (UPDATED - Modern Design)
 *
 * This script generates a downloadable PDF certificate for the logged-in user,
 * with styling that matches the modern certificate design.
 * * Last Updated: <?= date('Y-m-d H:i:s') ?>
 */

// Clear any output buffers and disable caching
if (ob_get_level()) {
    ob_end_clean();
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// --- FPDF Library ---
$fpdf_path = __DIR__ . '/../../includes/lib/fpdf.php';
if (!file_exists($fpdf_path)) {
    die('Error: FPDF library not found. Please place fpdf.php in the /includes/lib/ folder.');
}
require_once $fpdf_path;

// --- Authentication ---
if (!is_logged_in()) {
    die('Access Denied. Please log in.');
}
$user_id = $_SESSION['user_id'];

// --- Fetch Certificate Data ---
try {
    // FIX: Simplified query to match the new design
    $sql = "SELECT u.first_name, u.last_name, fa.completed_at 
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN final_assessments fa ON c.assessment_id = fa.id
            WHERE c.user_id = ? 
            ORDER BY fa.completed_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
        die('No certificate found for this user.');
    }
} catch (PDOException $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    die('A database error occurred.');
}

// --- Extended FPDF Class for Advanced Features ---
class CertificatePDF extends FPDF
{
    function Header() {}
    function Footer() {}
    
    // Method to create rounded rectangle
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
}

// --- PDF Generation ---
$pdf = new CertificatePDF('L', 'mm', 'A4'); // Landscape, millimeters, A4 size
$pdf->AddPage();
$pdf->SetTitle("Certificate of Completion - " . $certificate['first_name'] . ' ' . $certificate['last_name']);
$pdf->SetAutoPageBreak(false);

// --- Background ---
$pdf->SetFillColor(248, 249, 250);
$pdf->Rect(0, 0, 297, 210, 'F');

// --- Main Certificate Card ---
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(229, 231, 235);
$pdf->SetLineWidth(0.2);
$pdf->RoundedRect(10, 10, 277, 190, 8, 'DF');

// --- Header Section with Gradient ---
$pdf->SetFillColor(30, 60, 114); // Dark blue base
$pdf->Rect(10, 10, 277, 55, 'F');

// Logo
$logoPath = '../../assets/images/logo.png';
if (file_exists($logoPath)) {
    // Center the logo horizontally, place it near the top
    $pdf->Image($logoPath, 133.5, 15, 30); 
}

// Header Text
$pdf->SetY(48); // Position text below the logo area
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(220, 230, 255);
$pdf->Cell(0, 6, 'Information Security Training Program', 0, 1, 'C');


// --- Main Content Area ---
// "This certificate is proudly presented to" text
$pdf->SetY(80);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 8, 'This certificate is proudly presented to', 0, 1, 'C');

// Recipient's Name
$pdf->SetY(95);
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetTextColor(30, 60, 114);
$pdf->Cell(0, 15, $certificate['first_name'] . ' ' . $certificate['last_name'], 0, 1, 'C');

// "for successfully completing the" text
$pdf->SetY(115);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 8, 'for successfully completing the', 0, 1, 'C');

// Course Title
$pdf->SetY(125);
$pdf->SetFont('Arial', 'I', 18);
$pdf->SetTextColor(42, 82, 152);
$pdf->Cell(0, 10, 'Information Security Awareness Training', 0, 1, 'C');

// --- Details Section ---
$pdf->SetY(150);
$pdf->Line(40, 150, 257, 150); // Separator line

$pdf->SetY(160);

// Left column - Completion Date
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(107, 114, 128);
$pdf->SetX(40);
$pdf->Cell(72, 5, 'COMPLETION DATE', 0, 0, 'C');

// Center column - Status
$pdf->SetX(112);
$pdf->Cell(72, 5, 'STATUS', 0, 0, 'C');

// Right column - Authorized By
$pdf->SetX(184);
$pdf->Cell(72, 5, 'AUTHORIZED BY', 0, 1, 'C');

// Values
$pdf->SetY(167);

// Completion Date
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 60, 114);
$pdf->SetX(40);
$pdf->Cell(72, 6, date('F j, Y', strtotime($certificate['completed_at'])), 0, 0, 'C');

// Status Badge
$pdf->SetX(112);
$pdf->SetFillColor(16, 185, 129); // Green background
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
// Center the badge within its column
$badgeX = 112 + (72 - 25) / 2; 
$pdf->RoundedRect($badgeX, 166, 25, 8, 4, 'F');
$pdf->Cell(72, 6, 'Passed', 0, 0, 'C');

// Authorized By
$pdf->SetX(184);
$pdf->SetTextColor(30, 60, 114);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(72, 6, 'Mr. Thol Lyna', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(107, 114, 128);
$pdf->SetX(184);
$pdf->Cell(72, 4, 'Head of Information Technology', 0, 1, 'C');


// --- Output the PDF ---
$filename = 'Certificate_' . str_replace(' ', '_', $certificate['first_name'] . '_' . $certificate['last_name']) . '.pdf';
$pdf->Output('D', $filename); // 'D' forces download
exit;
?>
