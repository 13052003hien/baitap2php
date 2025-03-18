<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $internship_id = $_POST['internship_id'];
    $document_type = $_POST['document_type'];
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
        $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;
        $upload_path = '../uploads/documents/' . $new_filename;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
            $stmt = $conn->prepare("INSERT INTO internship_documents 
                (internship_id, document_type, file_path, uploaded_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$internship_id, $document_type, $new_filename]);
            $message = "Document uploaded successfully!";
        }
    }
}

// Get student's internships
$stmt = $conn->prepare("SELECT * FROM internship_details WHERE student_id = ?");
$stmt->execute([$_SESSION['student_id']]);
$internships = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Documents</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Upload Internship Documents</h2>
        <?php if (isset($message)) echo "<p class='success'>$message</p>"; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select Internship:</label>
                <select name="internship_id" required>
                    <?php foreach ($internships as $internship): ?>
                        <option value="<?= $internship['id'] ?>">
                            <?= htmlspecialchars($internship['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Document Type:</label>
                <select name="document_type" required>
                    <option value="weekly_report">Weekly Report</option>
                    <option value="final_report">Final Report</option>
                    <option value="evaluation_form">Evaluation Form</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Upload File:</label>
                <input type="file" name="document" required>
            </div>
            
            <button type="submit">Upload Document</button>
        </form>
    </div>
</body>
</html>
