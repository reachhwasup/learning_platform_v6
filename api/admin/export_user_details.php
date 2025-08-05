<?php
/*
File: /api/admin/export_user_details.php
Description: Export detailed assessment results for a specific user/assessment
*/

// Authenticate and initialize
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin (since auth_check.php might not exist in this path)
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Admin Authentication ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access Denied: You must be an administrator to access this feature.');
}

// --- Validate assessment_id ---
if (!isset($_GET['assessment_id']) || !filter_var($_GET['assessment_id'], FILTER_VALIDATE_INT)) {
    die('Invalid assessment ID provided.');
}
$assessment_id = (int)$_GET['assessment_id'];

// --- Check for PhpSpreadsheet ---
$vendor_autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    die('Error: The required library PhpSpreadsheet is not installed. Please run "composer install" in the project root.');
}
require_once $vendor_autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    // --- Fetch assessment and user details ---
    $sql_assessment = "SELECT u.first_name, u.last_name, u.staff_id, u.position, d.name as department_name,
                              fa.score, fa.status, fa.completed_at 
                       FROM final_assessments fa
                       JOIN users u ON fa.user_id = u.id
                       LEFT JOIN departments d ON u.department_id = d.id
                       WHERE fa.id = ?";
    $stmt_assessment = $pdo->prepare($sql_assessment);
    $stmt_assessment->execute([$assessment_id]);
    $assessment = $stmt_assessment->fetch();

    if (!$assessment) {
        die('Assessment not found.');
    }

    // --- Fetch detailed questions and answers ---
    $sql_details = "SELECT 
                        q.id as question_id,
                        q.question_text,
                        ua.selected_option_id,
                        qo_selected.option_text as selected_answer,
                        (SELECT GROUP_CONCAT(qo_correct.option_text SEPARATOR '; ') 
                         FROM question_options qo_correct 
                         WHERE qo_correct.question_id = q.id AND qo_correct.is_correct = 1) as correct_answers,
                        ua.is_correct
                    FROM user_answers ua
                    JOIN questions q ON ua.question_id = q.id
                    JOIN question_options qo_selected ON ua.selected_option_id = qo_selected.id
                    WHERE ua.assessment_id = ?
                    ORDER BY q.id";
    
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->execute([$assessment_id]);
    $question_details_raw = $stmt_details->fetchAll();

    // Group multiple answers for the same question
    $question_details = [];
    foreach ($question_details_raw as $row) {
        $question_id = $row['question_id'];
        
        if (!isset($question_details[$question_id])) {
            $question_details[$question_id] = [
                'question_text' => $row['question_text'],
                'selected_answers' => [],
                'correct_answers' => $row['correct_answers'],
                'is_correct' => true // Will be set to false if any answer is wrong
            ];
        }
        
        $question_details[$question_id]['selected_answers'][] = $row['selected_answer'];
        
        // If any answer is incorrect, mark the whole question as incorrect
        if (!$row['is_correct']) {
            $question_details[$question_id]['is_correct'] = false;
        }
    }

} catch (PDOException $e) {
    error_log("Export User Details Error: " . $e->getMessage());
    die('A database error occurred while generating the report. Details: ' . $e->getMessage());
}

// --- Create Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Assessment Details');

// --- User Information Section ---
$sheet->setCellValue('A1', 'Assessment Details Report');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->mergeCells('A1:F1');

$row = 3;
$sheet->setCellValue('A' . $row, 'Name:');
$sheet->setCellValue('B' . $row, $assessment['first_name'] . ' ' . $assessment['last_name']);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Staff ID:');
$sheet->setCellValue('B' . $row, $assessment['staff_id']);
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Position:');
$sheet->setCellValue('B' . $row, $assessment['position'] ?? 'N/A');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Department:');
$sheet->setCellValue('B' . $row, $assessment['department_name'] ?? 'N/A');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Final Score:');
$sheet->setCellValue('B' . $row, (int)$assessment['score'] . ' Points');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Status:');
$sheet->setCellValue('B' . $row, ucfirst($assessment['status']));
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$row++;
$sheet->setCellValue('A' . $row, 'Completed:');
$sheet->setCellValue('B' . $row, date('M d, Y H:i', strtotime($assessment['completed_at'])));
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

// --- Questions and Answers Section ---
$row += 3;
$sheet->setCellValue('A' . $row, 'Question Details');
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$sheet->mergeCells('A' . $row . ':F' . $row);

$row += 2;
// Headers for questions table
$headers = ['Question #', 'Question', 'Your Answer', 'Correct Answer(s)', 'Result'];
$sheet->fromArray($headers, NULL, 'A' . $row);

// Style the header row
$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '075985']]
];
$sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($header_style);

// --- Populate Question Data ---
$row++;
$question_number = 1;
foreach ($question_details as $question_id => $detail) {
    $result = $detail['is_correct'] ? 'Correct' : 'Incorrect';
    
    // Join multiple selected answers with semicolon
    $selected_answers_text = implode('; ', $detail['selected_answers']);
    
    $sheet->fromArray([
        $question_number,
        $detail['question_text'],
        $selected_answers_text,
        $detail['correct_answers'],
        $result
    ], NULL, 'A' . $row);
    
    // Color coding for result
    if ($detail['is_correct']) {
        $sheet->getStyle('E' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('D1FAE5'); // Light green
        $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('065F46'); // Dark green
    } else {
        $sheet->getStyle('E' . $row)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('FEE2E2'); // Light red
        $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('991B1B'); // Dark red
    }
    
    $row++;
    $question_number++;
}

// --- Auto-size columns ---
foreach (range('A', 'E') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set minimum width for question column
$sheet->getColumnDimension('B')->setWidth(50);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(30);

// --- Set Headers for Download ---
$user_name = str_replace(' ', '_', $assessment['first_name'] . '_' . $assessment['last_name']);
$filename = "assessment_details_{$user_name}_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// --- Output the file ---
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>