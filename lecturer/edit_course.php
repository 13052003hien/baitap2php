<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get lecturer ID first
$stmt = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    header('Location: dashboard.php');
    exit();
}

$course_id = $_GET['id'] ?? 0;

// Verify course belongs to lecturer
$stmt = $conn->prepare("SELECT * FROM internship_courses WHERE course_id = ? AND lecturer_id = ?");
$stmt->execute([$course_id, $lecturer['lecturer_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE internship_courses SET 
            course_code = ?, 
            course_name = ?, 
            description = ? 
            WHERE course_id = ? AND lecturer_id = ?");
            
        $stmt->execute([
            $_POST['course_code'],
            $_POST['course_name'],
            $_POST['description'],
            $course_id,
            $lecturer['lecturer_id']
        ]);
        
        $message = "Course updated successfully!";
        
        // Refresh course data
        $stmt = $conn->prepare("SELECT * FROM internship_courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error updating course: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Course</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <a href="dashboard.php">Back to Dashboard</a>
        </nav>

        <div class="content-wrapper">
            <div class="modern-form">
                <header class="form-header">
                    <h2>Edit Course</h2>
                </header>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" class="grid-form">
                    <div class="form-section">
                        <div class="form-group">
                            <label>Course Code</label>
                            <input type="text" 
                                name="course_code" 
                                value="<?= htmlspecialchars($course['course_code']) ?>" 
                                required>
                        </div>

                        <div class="form-group">
                            <label>Course Name</label>
                            <input type="text" 
                                name="course_name" 
                                value="<?= htmlspecialchars($course['course_name']) ?>" 
                                required>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-modern">Update Course</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
