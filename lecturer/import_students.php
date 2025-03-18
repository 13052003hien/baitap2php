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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Skip header row
        array_shift($rows);
        
        foreach ($rows as $row) {
            // Validate required fields
            if (empty($row[0]) || empty($row[1]) || empty($row[2])) {
                throw new Exception("Student code, last name, and first name are required");
            }

            // Create user account
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_first_login) VALUES (?, ?, 'student', 1)");
            $username = $row[0]; // student_code as username
            $password = password_hash($row[0], PASSWORD_DEFAULT); // student_code as initial password
            $stmt->execute([$username, $password]);
            $userId = $conn->lastInsertId();

            // Insert student data
            $stmt = $conn->prepare("INSERT INTO students 
                (user_id, student_code, last_name, first_name, phone, email, major, dob, class_code) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $userId,
                $row[0], // student_code
                $row[1], // last_name
                $row[2], // first_name
                $row[3] ?? '', // phone (optional)
                $row[4] ?? '', // email (optional)
                $row[5] ?? '', // major (optional)
                !empty($row[6]) ? date('Y-m-d', strtotime($row[6])) : null, // dob (optional)
                $row[7] ?? '' // class_code (optional)
            ]);
        }
        
        $conn->commit();
        $message = "Students imported successfully!";
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
                    <h2>Import Students from Excel</h2>
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
                            <label>Excel File</label>
                            <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                            <small class="form-help">
                                Expected columns: Student Code, Last Name, First Name, Phone, Email, Major, DOB, Class Code
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
