<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// First, get the lecturer_id for the current user
$stmt = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    $error = "Lecturer profile not found. Please contact administrator.";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Check if course code already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM internship_courses WHERE course_code = ?");
            $stmt->execute([$_POST['course_code']]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $error = "Course code already exists. Please use a different code.";
            } else {
                $stmt = $conn->prepare("INSERT INTO internship_courses 
                    (course_code, course_name, description, lecturer_id) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    trim($_POST['course_code']),
                    trim($_POST['course_name']),
                    trim($_POST['description']),
                    $lecturer['lecturer_id']
                ]);
                $message = "Course created successfully!";
                
                // Clear form data after successful creation
                $_POST = array();
            }
        } catch (PDOException $e) {
            $error = "Error creating course: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Course</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="import_students.php">Import Students</a>
            </div>
            <a href="../logout.php" class="btn-modern">Logout</a>
        </nav>

        <div class="content-wrapper">
            <div class="modern-form">
                <header class="form-header">
                    <h2>Create New Course</h2>
                    <p>Add a new internship course to your portfolio</p>
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

                <form method="POST" class="grid-form" id="createCourseForm">
                    <div class="form-section">
                        <div class="form-group">
                            <label>Course Code</label>
                            <input type="text" 
                                   name="course_code" 
                                   required 
                                   pattern="[A-Za-z0-9-]+"
                                   title="Only letters, numbers, and hyphens allowed"
                                   placeholder="e.g., INT3306"
                                   value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>">
                            <small class="form-help">Course code must be unique and contain only letters, numbers, and hyphens</small>
                        </div>

                        <div class="form-group">
                            <label>Course Name</label>
                            <input type="text" 
                                   name="course_name" 
                                   required
                                   placeholder="e.g., Web Development Internship"
                                   value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" 
                                     rows="4" 
                                     placeholder="Enter course description and objectives"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-modern">Create Course</button>
                        <button type="reset" class="btn-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('createCourseForm').addEventListener('submit', function(e) {
        const courseCode = this.querySelector('[name="course_code"]').value;
        if (!/^[A-Za-z0-9-]+$/.test(courseCode)) {
            e.preventDefault();
            alert('Course code can only contain letters, numbers, and hyphens');
        }
    });
    </script>
</body>
</html>
