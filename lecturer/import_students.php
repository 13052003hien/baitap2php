<?php
require '../vendor/autoload.php';
require '../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get lecturer_id first
$stmt = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    die("Lecturer profile not found");
}

// Get lecturer's courses
$stmt = $conn->prepare("SELECT * FROM internship_courses WHERE lecturer_id = ?");
$stmt->execute([$lecturer['lecturer_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $conn->beginTransaction();
        
        $course_id = $_POST['course_id'];
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        array_shift($rows); // Skip header row
        $success_count = 0;
        $existing_count = 0;
        
        foreach ($rows as $row) {
            if (empty($row[0]) || empty($row[1]) || empty($row[2])) {
                continue; // Skip empty rows
            }

            $student_code = trim($row[0]);
            
            // Check if student already exists
            $stmt = $conn->prepare("SELECT s.student_id, s.user_id FROM students s WHERE s.student_code = ?");
            $stmt->execute([$student_code]);
            $existing_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_student) {
                // Check if already enrolled in this course
                $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ?");
                $stmt->execute([$existing_student['student_id'], $course_id]);
                if ($stmt->fetchColumn() == 0) {
                    // Enroll existing student
                    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$existing_student['student_id'], $course_id]);
                    $success_count++;
                } else {
                    $existing_count++;
                }
            } else {
                // Create new user account
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_first_login) VALUES (?, ?, 'student', 1)");
                $password = password_hash($student_code, PASSWORD_DEFAULT); // Use student code as initial password
                $stmt->execute([$student_code, $password]);
                $user_id = $conn->lastInsertId();

                // Create student record
                $stmt = $conn->prepare("INSERT INTO students (user_id, student_code, first_name, last_name, email, phone, class_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $student_code,
                    $row[2], // first_name
                    $row[1], // last_name
                    $row[3] ?? '', // email
                    $row[4] ?? '', // phone
                    $row[5] ?? ''  // class_code
                ]);
                $student_id = $conn->lastInsertId();

                // Enroll new student
                $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$student_id, $course_id]);
                $success_count++;
            }
        }
        
        $conn->commit();
        $message = "Successfully processed: $success_count new/enrolled students. $existing_count already enrolled.";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error importing students: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Students</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="../logout.php">Logout</a>
        </nav>

        <div class="content-wrapper">
            <div class="modern-form">
                <header class="form-header">
                    <h2>Import Students to Course</h2>
                    <p>Upload an Excel file containing student information</p>
                </header>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-section">
                        <div class="form-group">
                            <label>Select Course</label>
                            <select name="course_id" required>
                                <option value="">Choose a course...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['course_id'] ?>">
                                        <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Excel File</label>
                            <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                            <small class="form-help">
                                Expected columns: Student Code, Last Name, First Name, Email, Phone, Class Code
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-modern">Import Students</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
