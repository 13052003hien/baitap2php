<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$internship_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE internship_details SET 
            status = ?, 
            feedback = ?,
            evaluation_score = ?,
            evaluated_at = CURRENT_TIMESTAMP,
            evaluated_by = ?
            WHERE id = ?");
            
        $stmt->execute([
            $_POST['status'],
            $_POST['feedback'],
            $_POST['score'],
            $_SESSION['user_id'],
            $internship_id
        ]);
        
        $message = "Evaluation submitted successfully!";
    } catch (PDOException $e) {
        $error = "Error submitting evaluation: " . $e->getMessage();
    }
}

// Get internship details
$stmt = $conn->prepare("
    SELECT id.*, s.first_name, s.last_name, s.student_code 
    FROM internship_details id
    JOIN students s ON id.student_id = s.student_id
    WHERE id.id = ?
");
$stmt->execute([$internship_id]);
$internship = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Evaluate Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="evaluation-form">
        <h2>Evaluate Student Internship</h2>
        <h3>Student: <?= htmlspecialchars($internship['first_name'] . ' ' . $internship['last_name']) ?></h3>
        <form method="POST">
            <div class="form-group">
                <label>Status:</label>
                <select name="status" required>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Score (0-100):</label>
                <input type="number" name="score" min="0" max="100" required>
            </div>
            
            <div class="form-group">
                <label>Feedback:</label>
                <textarea name="feedback" rows="4" required></textarea>
            </div>
            
            <button type="submit">Submit Evaluation</button>
        </form>
    </div>
</body>
</html>
