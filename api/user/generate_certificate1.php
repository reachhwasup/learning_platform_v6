<?php
// Start output buffering to prevent header issues
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if PDF generation is requested FIRST
$generate_pdf = isset($_GET['generate']) && $_GET['generate'] == '1';

if ($generate_pdf) {
    // Clean output buffer before PDF generation
    ob_clean();
    
    // --- FPDF Library ---
    $fpdf_path = __DIR__ . '/../../includes/lib/fpdf.php';
    if (!file_exists($fpdf_path)) {
        die('Error: FPDF library not found at: ' . $fpdf_path);
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
        
        // Generate PDF with enhanced design matching the UI
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetTitle("Certificate of Completion");

        // Background gradient simulation with rectangles
        $pdf->SetFillColor(248, 250, 255); // Light blue background
        $pdf->Rect(0, 0, 297, 210, 'F');

        // Main certificate border with gradient colors
        $pdf->SetLineWidth(3);
        $pdf->SetDrawColor(30, 60, 114); // Dark blue
        $pdf->Rect(15, 15, 267, 180);
        
        $pdf->SetLineWidth(1.5);
        $pdf->SetDrawColor(42, 82, 152); // Medium blue
        $pdf->Rect(18, 18, 261, 174);

        // Header section background
        $pdf->SetFillColor(30, 60, 114); // Dark blue header
        $pdf->Rect(18, 18, 261, 45, 'F');

        // Decorative elements in header
        $pdf->SetFillColor(255, 255, 255); // White decorative elements
        $pdf->SetDrawColor(255, 255, 255);
        for ($i = 0; $i < 15; $i++) {
            $x = 25 + ($i * 17);
            $pdf->Circle($x, 25, 1, 'F');
            $pdf->Circle($x + 8, 30, 0.8, 'F');
        }

        // Logo placeholder (circle)
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(2);
        $pdf->Circle(60, 40, 12, 'FD');
        
        // Add "LOGO" text in the circle
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetXY(50, 37);
        $pdf->Cell(20, 6, 'LOGO', 0, 0, 'C');

        // Header text
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(18, 30);
        $pdf->Cell(261, 12, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(18, 45);
        $pdf->Cell(261, 8, 'Information Security Training Program', 0, 1, 'C');

        // Main body background
        $pdf->SetFillColor(255, 255, 255); // White body
        $pdf->Rect(18, 63, 261, 129, 'F');

        // Decorative ornament at top of body
        $pdf->SetFillColor(102, 126, 234); // Purple-blue gradient simulation
        $pdf->Rect(124, 70, 50, 3, 'F');
        $pdf->SetFillColor(118, 75, 162);
        $pdf->Rect(129, 70, 40, 3, 'F');

        // Floating decorative stars
        $pdf->SetFont('Arial', '', 16);
        $pdf->SetTextColor(102, 126, 234);
        $pdf->SetXY(40, 80);
        $pdf->Cell(10, 10, '*', 0, 0, 'C');
        $pdf->SetXY(250, 120);
        $pdf->Cell(10, 10, '*', 0, 0, 'C');
        $pdf->SetXY(35, 140);
        $pdf->Cell(10, 10, '*', 0, 0, 'C');

        // "This certificate is proudly presented to" text
        $pdf->SetY(85);
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(107, 114, 128); // Gray
        $pdf->Cell(0, 10, 'This certificate is proudly presented to', 0, 1, 'C');

        // User's Name with enhanced styling
        $pdf->SetY(100);
        $pdf->SetFont('Arial', 'B', 36);
        $pdf->SetTextColor(30, 60, 114); // Dark blue
        $name = $certificate['first_name'] . ' ' . $certificate['last_name'];
        $pdf->Cell(0, 20, $name, 0, 1, 'C');

        // Decorative line under name
        $pdf->SetDrawColor(102, 126, 234);
        $pdf->SetLineWidth(2);
        $pdf->Line(100, 125, 200, 125);

        // "for successfully completing the" text
        $pdf->SetY(135);
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell(0, 8, 'for successfully completing the', 0, 1, 'C');
        
        // Course title
        $pdf->SetFont('Arial', 'BI', 18);
        $pdf->SetTextColor(42, 82, 152);
        $pdf->Cell(0, 12, 'Information Security Awareness Training', 0, 1, 'C');

        // Details section with modern grid layout
        $pdf->SetY(160);
        
        // Completion Date
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->SetXY(50, 160);
        $pdf->Cell(50, 5, 'COMPLETION DATE', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetXY(50, 168);
        $pdf->Cell(50, 6, date('F j, Y', strtotime($certificate['completed_at'])), 0, 0, 'C');

        // Final Score
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->SetXY(130, 160);
        $pdf->Cell(40, 5, 'FINAL SCORE', 0, 1, 'C');
        
        // Score badge background
        $pdf->SetFillColor(16, 185, 129); // Green background
        $pdf->SetDrawColor(16, 185, 129);
        $pdf->RoundedRect(140, 166, 20, 8, 4, 'FD');
        
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(140, 168);
        $pdf->Cell(20, 6, intval($certificate['score']) . ' pts', 0, 0, 'C');

        // Certificate ID
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->SetXY(200, 160);
        $pdf->Cell(50, 5, 'CERTIFICATE ID', 0, 1, 'C');
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetXY(200, 168);
        $pdf->Cell(50, 6, $certificate['certificate_code'], 0, 0, 'C');

        // Signature section
        $pdf->SetY(180);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell(0, 5, 'AUTHORIZED BY', 0, 1, 'C');
        
        // Signature line
        $pdf->SetDrawColor(30, 60, 114);
        $pdf->SetLineWidth(1);
        $pdf->Line(120, 188, 177, 188);
        
        $pdf->SetY(185);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->Cell(0, 6, 'Mr. Thol Lyna', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell(0, 5, 'Head of Information Technology', 0, 1, 'C');

        // Add subtle pattern overlay
        $pdf->SetDrawColor(30, 60, 114);
        $pdf->SetLineWidth(0.1);
        for ($i = 0; $i < 20; $i++) {
            $x1 = 20 + ($i * 13);
            $pdf->Line($x1, 65, $x1 + 6, 75);
            $pdf->Line($x1 + 3, 65, $x1 + 9, 75);
        }

        // Clean filename for download
        $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $filename = 'Certificate_' . $clean_name . '.pdf';

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        $pdf->Output('D', $filename);
        exit;
        
    } catch (PDOException $e) {
        ob_clean();
        die('Database error: ' . $e->getMessage());
    } catch (Exception $e) {
        ob_clean();
        die('PDF Generation error: ' . $e->getMessage());
    }
}

// If not generating PDF, show the debug page with enhanced styling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Generator - Debug</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .debug-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        .debug-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .debug-body {
            padding: 2rem;
        }
        .status-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .status-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .status-value {
            font-family: 'Courier New', monospace;
            background: #e9ecef;
            padding: 0.5rem;
            border-radius: 5px;
            word-break: break-all;
        }
        .success {
            border-left: 4px solid #28a745;
        }
        .info {
            border-left: 4px solid #17a2b8;
        }
        .generate-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            color: white;
            text-decoration: none;
        }
        .certificate-preview {
            background: linear-gradient(to bottom, #f8faff 0%, #ffffff 100%);
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="debug-header">
            <h1>Certificate Generator Debug</h1>
            <p>Enhanced Design Version</p>
        </div>
        
        <div class="debug-body">
            <?php
            // DEBUG VERSION - This will show if the new code is running
            echo '<div class="status-item success">';
            echo '<div class="status-label">System Status</div>';
            echo '<div class="status-value">NEW VERSION LOADED - ' . date('Y-m-d H:i:s') . '</div>';
            echo '</div>';

            echo '<div class="status-item info">';
            echo '<div class="status-label">File Path</div>';
            echo '<div class="status-value">' . __FILE__ . '</div>';
            echo '</div>';

            // --- FPDF Library Check ---
            $fpdf_path = __DIR__ . '/../../includes/lib/fpdf.php';
            echo '<div class="status-item ' . (file_exists($fpdf_path) ? 'success' : 'error') . '">';
            echo '<div class="status-label">FPDF Library</div>';
            echo '<div class="status-value">Path: ' . $fpdf_path . '<br>';
            echo 'Status: ' . (file_exists($fpdf_path) ? '✅ Found' : '❌ Not Found') . '</div>';
            echo '</div>';

            // --- Authentication ---
            if (!is_logged_in()) {
                die('<div class="status-item error"><div class="status-label">Authentication</div><div class="status-value">❌ Access Denied. Please log in.</div></div>');
            }
            $user_id = $_SESSION['user_id'];

            echo '<div class="status-item success">';
            echo '<div class="status-label">User Authentication</div>';
            echo '<div class="status-value">✅ Authenticated - User ID: ' . $user_id . '</div>';
            echo '</div>';

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
                    echo '<div class="status-item error">';
                    echo '<div class="status-label">Certificate Status</div>';
                    echo '<div class="status-value">❌ No certificate found for this user.</div>';
                    echo '</div>';
                } else {
                    echo '<div class="status-item success">';
                    echo '<div class="status-label">Certificate Data</div>';
                    echo '<div class="status-value">';
                    echo '✅ Certificate found<br>';
                    echo 'Name: ' . htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']) . '<br>';
                    echo 'Certificate ID: ' . htmlspecialchars($certificate['certificate_code']) . '<br>';
                    echo 'Score: ' . intval($certificate['score']) . ' points<br>';
                    echo 'Completed: ' . date('F j, Y', strtotime($certificate['completed_at']));
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<div class="certificate-preview">';
                    echo '<h3 style="color: #1e3c72; margin-bottom: 1rem;">Certificate Preview Ready</h3>';
                    echo '<p style="color: #6b7280; margin-bottom: 2rem;">Enhanced design matching your website UI</p>';
                    echo '<a href="' . $_SERVER['PHP_SELF'] . '?generate=1" class="generate-btn">';
                    echo '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
                    echo '</svg>';
                    echo 'Generate Enhanced PDF Certificate';
                    echo '</a>';
                    echo '</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="status-item error">';
                echo '<div class="status-label">Database Error</div>';
                echo '<div class="status-value">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>
<?php
// Custom RoundedRect function for FPDF (add this as a helper function)
if (!function_exists('RoundedRect')) {
    class ExtendedFPDF extends FPDF {
        function RoundedRect($x, $y, $w, $h, $r, $style = '') {
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

        function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
                $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
        }
    }
}
?>