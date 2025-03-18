<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// First get lecturer_id
$stmt = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    $error = "Lecturer profile not found. Please contact administrator.";
} else {
    // Get lecturer's courses using lecturer_id
    $stmt = $conn->prepare("SELECT * FROM internship_courses WHERE lecturer_id = ?");
    $stmt->execute([$lecturer['lecturer_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <div class="nav-links">
                <a href="create_course.php">Create New Course</a>
                <a href="import_students.php">Import Students</a>
                <a href="generate_report.php">Generate Reports</a>
            </div>
            <a href="../logout.php" class="btn-modern">Logout</a>
        </nav>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <div class="content-section">
            <h2>Your Courses</h2>
            <?php if ($courses && count($courses) > 0): ?>
                <div class="modern-table">
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['course_code']) ?></td>
                                    <td><?= htmlspecialchars($course['course_name']) ?></td>
                                    <td>
                                        <a href="view_students.php?course_id=<?= $course['course_id'] ?>" 
                                           class="btn-link">View Students</a>
                                    </td>
                                    <td>
                                        <a href="edit_course.php?id=<?= $course['course_id'] ?>" 
                                           class="btn-link">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No courses found. <a href="create_course.php" class="btn-modern">Create your first course</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
