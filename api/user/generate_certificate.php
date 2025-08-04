<?php
/**
 * Generate PDF Certificate API (UPDATED - Modern Design)
 *
 * This script generates a downloadable PDF certificate for the logged-in user,
 * with styling that matches the modern certificate design.
 * 
 * Last Updated: <?= date('Y-m-d H:i:s') ?>
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
    $sql = "SELECT u.first_name, u.last_name, c.certificate_code, fa.score, fa.completed_at 
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
    function Header() {
        // This will be handled manually for better control
    }
    
    function Footer() {
        // This will be handled manually for better control
    }
    
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
    
    // Method to create gradient background
    function LinearGradient($x, $y, $w, $h, $col1=array(), $col2=array(), $coords=array(0,0,1,0))
    {
        $this->Clip($x, $y, $w, $h);
        $this->SetFillColor($col1[0], $col1[1], $col1[2]);
        $this->Rect($x, $y, $w, $h, 'F');
        
        // Create multiple rectangles for gradient effect
        $steps = 100;
        for($i = 0; $i <= $steps; $i++) {
            $r = $col1[0] + ($col2[0] - $col1[0]) * $i / $steps;
            $g = $col1[1] + ($col2[1] - $col1[1]) * $i / $steps;
            $b = $col1[2] + ($col2[2] - $col1[2]) * $i / $steps;
            $this->SetFillColor($r, $g, $b);
            $this->Rect($x, $y + ($h * $i / $steps), $w, $h / $steps, 'F');
        }
    }
}

// --- PDF Generation ---
$pdf = new CertificatePDF('L', 'mm', 'A4'); // Landscape, millimeters, A4 size
$pdf->AddPage();
$pdf->SetTitle("Certificate of Completion - " . $certificate['first_name'] . ' ' . $certificate['last_name']);
$pdf->SetAutoPageBreak(false);

// --- Background ---
$pdf->SetFillColor(248, 250, 252); // Light gray background
$pdf->Rect(0, 0, 297, 210, 'F');

// --- Header Section with Gradient ---
$pdf->SetFillColor(59, 88, 152); // Dark blue similar to design
$pdf->RoundedRect(10, 10, 277, 50, 8, 'F');

// Add subtle gradient effect to header
$pdf->SetFillColor(45, 75, 140);
$pdf->RoundedRect(10, 10, 277, 25, 8, 'F');

// Logo placeholder (circular background)
$pdf->SetFillColor(255, 255, 255, 30); // Semi-transparent white
$pdf->SetXY(135, 20);
// Draw circle for logo background
$pdf->SetFillColor(255, 255, 255, 50);
for($i = 0; $i < 360; $i += 10) {
    $x = 148 + 12 * cos(deg2rad($i));
    $y = 35 + 12 * sin(deg2rad($i));
    $pdf->SetXY($x, $y);
}

// Header Text
$pdf->SetY(25);
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(220, 230, 255);
$pdf->Cell(0, 6, 'Information Security Training Program', 0, 1, 'C');

// --- Main Content Area ---
$pdf->SetFillColor(255, 255, 255);
$pdf->RoundedRect(25, 75, 247, 110, 12, 'F');

// Add subtle border
$pdf->SetDrawColor(230, 240, 255);
$pdf->SetLineWidth(0.5);
$pdf->RoundedRect(25, 75, 247, 110, 12, 'D');

// "This certificate is proudly presented to" text
$pdf->SetY(90);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 8, 'This certificate is proudly presented to', 0, 1, 'C');

// Purple decorative line above name
$pdf->SetDrawColor(139, 92, 246);
$pdf->SetLineWidth(1);
$pdf->Line(110, 105, 187, 105);

// Recipient's Name
$pdf->SetY(110);
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(59, 88, 152);
$pdf->Cell(0, 15, $certificate['first_name'] . ' ' . $certificate['last_name'], 0, 1, 'C');

// Purple decorative line below name
$pdf->Line(110, 130, 187, 130);

// "for successfully completing the" text
$pdf->SetY(140);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 8, 'for successfully completing the', 0, 1, 'C');

// Course Title
$pdf->SetY(150);
$pdf->SetFont('Arial', 'I', 16);
$pdf->SetTextColor(59, 88, 152);
$pdf->Cell(0, 10, 'Information Security Awareness Training', 0, 1, 'C');

// --- Details Section ---
$pdf->SetY(170);

// Left column - Completion Date
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(107, 114, 128);
$pdf->SetX(40);
$pdf->Cell(70, 5, 'COMPLETION DATE', 0, 0, 'C');

// Center column - Final Score
$pdf->SetX(113);
$pdf->Cell(70, 5, 'FINAL SCORE', 0, 0, 'C');

// Right column - Certificate ID
$pdf->SetX(186);
$pdf->Cell(70, 5, 'CERTIFICATE ID', 0, 1, 'C');

// Values
$pdf->SetY(177);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(59, 88, 152);

// Completion Date
$pdf->SetX(40);
$pdf->Cell(70, 6, date('F j, Y', strtotime($certificate['completed_at'])), 0, 0, 'C');

// Final Score with green background
$pdf->SetX(113);
$score = intval($certificate['score']);
$pdf->SetFillColor(34, 197, 94); // Green background
$pdf->SetTextColor(255, 255, 255);
$pdf->RoundedRect(135, 175, 25, 8, 4, 'F');
$pdf->Cell(70, 6, $score . ' points', 0, 0, 'C');

// Certificate ID
$pdf->SetX(186);
$pdf->SetTextColor(59, 88, 152);
$pdf->Cell(70, 6, $certificate['certificate_code'], 0, 1, 'C');

// --- Authorization Section ---
$pdf->SetY(195);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 4, 'AUTHORIZED BY', 0, 1, 'C');

// Signature line
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.3);
$pdf->Line(120, 205, 177, 205);

$pdf->SetY(200);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(59, 88, 152);
$pdf->Cell(0, 5, 'Mr. Thol Lyna', 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(107, 114, 128);
$pdf->Cell(0, 4, 'Head of Information Technology', 0, 1, 'C');

// --- Output the PDF ---
$filename = 'Certificate_' . str_replace(' ', '_', $certificate['first_name'] . '_' . $certificate['last_name']) . '.pdf';
$pdf->Output('D', $filename); // 'D' forces download
exit;
?>