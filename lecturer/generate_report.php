<?php
require '../config/database.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'];
    
    // Get course details and student data
    $stmt = $conn->prepare("
        SELECT s.student_code, s.first_name, s.last_name, s.class_code,
               id.company_name, id.status, id.evaluation_score
        FROM students s
        JOIN internship_details id ON s.student_id = id.student_id
        WHERE id.course_id = ?
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add headers
    $sheet->setCellValue('A1', 'Student Code');
    $sheet->setCellValue('B1', 'Name');
    $sheet->setCellValue('C1', 'Class');
    $sheet->setCellValue('D1', 'Company');
    $sheet->setCellValue('E1', 'Status');
    $sheet->setCellValue('F1', 'Score');
    
    // Add data
    $row = 2;
    foreach ($students as $student) {
        $sheet->setCellValue('A'.$row, $student['student_code']);
        $sheet->setCellValue('B'.$row, $student['first_name'] . ' ' . $student['last_name']);
        $sheet->setCellValue('C'.$row, $student['class_code']);
        $sheet->setCellValue('D'.$row, $student['company_name']);
        $sheet->setCellValue('E'.$row, $student['status']);
        $sheet->setCellValue('F'.$row, $student['evaluation_score']);
        $row++;
    }
    
    // Generate and download file
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="internship_report.xlsx"');
    $writer->save('php://output');
    exit();
}

// Get lecturer's courses
$stmt = $conn->prepare("SELECT course_id, course_code, course_name FROM internship_courses WHERE lecturer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Generate Report</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <h2>Generate Internship Report</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Select Course:</label>
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['course_id'] ?>">
                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Generate Report</button>
        </form>
    </div>
</body>
</html>
