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
$stmt = $conn->prepare("SELECT * FROM internship_courses WHERE course_id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found");
}

// Add student to course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $stmt->execute([$_POST['student_id'], $course_id]);
        $message = "Student added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding student: " . $e->getMessage();
    }
}

// Remove student from course
if (isset($_GET['remove']) && isset($_GET['student_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$_GET['student_id'], $course_id]);
        $message = "Student removed successfully!";
    } catch (PDOException $e) {
        $error = "Error removing student: " . $e->getMessage();
    }
}

// Get enrolled students
$stmt = $conn->prepare("
    SELECT s.*, e.enrollment_date, id.company_name, id.status as internship_status
    FROM students s
    JOIN enrollments e ON s.student_id = e.student_id
    LEFT JOIN internship_details id ON s.student_id = id.student_id AND id.course_id = e.course_id
    WHERE e.course_id = ?
");
$stmt->execute([$course_id]);
$enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available students (not enrolled in this course)
$stmt = $conn->prepare("
    SELECT s.* FROM students s
    WHERE s.student_id NOT IN (
        SELECT student_id FROM enrollments WHERE course_id = ?
    )
");
$stmt->execute([$course_id]);
$available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Course Students</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <a href="dashboard.php">Back to Dashboard</a>
        </nav>

        <div class="content-wrapper">
            <header class="page-header">
                <h2>Manage Students - <?= htmlspecialchars($course['course_name']) ?></h2>
            </header>

            <?php if (isset($message)): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <!-- Add Student Form -->
            <div class="form-section">
                <h3>Add New Student</h3>
                <form method="POST" class="inline-form">
                    <select name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($available_students as $student): ?>
                            <option value="<?= $student['student_id'] ?>">
                                <?= htmlspecialchars($student['student_code'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_student" class="btn-modern">Add Student</button>
                </form>
            </div>

            <!-- Enrolled Students Table -->
            <div class="table-responsive">
                <h3>Enrolled Students</h3>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Student Code</th>
                            <th>Name</th>
                            <th>Enrollment Date</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrolled_students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['student_code']) ?></td>
                                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td><?= htmlspecialchars($student['enrollment_date']) ?></td>
                                <td><?= htmlspecialchars($student['company_name'] ?? 'Not registered') ?></td>
                                <td><?= htmlspecialchars($student['internship_status'] ?? 'Pending') ?></td>
                                <td>
                                    <a href="?course_id=<?= $course_id ?>&remove=1&student_id=<?= $student['student_id'] ?>" 
                                       onclick="return confirm('Are you sure?')"
                                       class="btn-danger">Remove</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
