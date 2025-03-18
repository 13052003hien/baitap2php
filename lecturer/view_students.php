<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$course_id = $_GET['course_id'] ?? 0;

// Get course details
$stmt = $conn->prepare("SELECT * FROM internship_courses WHERE course_id = ? AND lecturer_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: dashboard.php');
    exit();
}

// Get enrolled students
$stmt = $conn->prepare("
    SELECT s.*, sc.status as enrollment_status, 
           id.company_name, id.status as internship_status,
           id.evaluation_score
    FROM students s
    JOIN student_courses sc ON s.student_id = sc.student_id
    LEFT JOIN internship_details id ON s.student_id = id.student_id AND id.course_id = sc.course_id
    WHERE sc.course_id = ?
");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Students</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <h2>Students in <?= htmlspecialchars($course['course_name']) ?></h2>
        
        <table class="students-table">
            <thead>
                <tr>
                    <th>Student Code</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['student_code']) ?></td>
                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                        <td><?= htmlspecialchars($student['company_name'] ?? 'Not registered') ?></td>
                        <td><?= htmlspecialchars($student['internship_status'] ?? 'Pending') ?></td>
                        <td><?= $student['evaluation_score'] ?? '-' ?></td>
                        <td>
                            <a href="evaluate_student.php?id=<?= $student['student_id'] ?>&course=<?= $course_id ?>">Evaluate</a>
                            |
                            <a href="message_student.php?id=<?= $student['student_id'] ?>">Message</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
