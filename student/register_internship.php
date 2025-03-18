<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get student information first
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available courses that the student hasn't registered for yet
$stmt = $conn->prepare("
    SELECT ic.* 
    FROM internship_courses ic
    LEFT JOIN internship_details id ON ic.course_id = id.course_id 
        AND id.student_id = ?
    WHERE id.id IS NULL
");
$stmt->execute([$student['student_id']]);
$available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("INSERT INTO internship_details 
            (student_id, course_id, company_name, company_address, industry, 
             supervisor_name, supervisor_phone, supervisor_email, start_date, 
             end_date, job_position, job_description, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
        $stmt->execute([
            $student['student_id'], // Use the fetched student_id
            $_POST['course_id'],
            $_POST['company_name'],
            $_POST['company_address'],
            $_POST['industry'],
            $_POST['supervisor_name'],
            $_POST['supervisor_phone'],
            $_POST['supervisor_email'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['job_position'],
            $_POST['job_description']
        ]);
        
        // Also enroll student in the course
        $stmt = $conn->prepare("INSERT INTO student_courses (student_id, course_id, status) VALUES (?, ?, 'active')");
        $stmt->execute([$student['student_id'], $_POST['course_id']]);
        
        $message = "Internship registration submitted successfully!";
    } catch (PDOException $e) {
        $error = "Error submitting registration: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register Internship</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">My Profile</a>
            </div>
            <a href="../logout.php" class="btn-modern">Logout</a>
        </nav>

        <div class="content-wrapper">
            <div class="modern-form">
                <?php if (empty($available_courses)): ?>
                    <div class="empty-state">
                        <h2>No Available Courses</h2>
                        <p>You have already registered for all available internship courses.</p>
                        <a href="dashboard.php" class="btn-modern">Return to Dashboard</a>
                    </div>
                <?php else: ?>
                    <header class="form-header">
                        <h2>Register New Internship</h2>
                        <p>Fill in the details of your internship position</p>
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

                    <form method="POST" class="grid-form">
                        <div class="form-section">
                            <h3>Course Information</h3>
                            <div class="form-group">
                                <label>Select Course</label>
                                <select name="course_id" required class="styled-select">
                                    <option value="">Choose a course...</option>
                                    <?php foreach ($available_courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>">
                                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Company Details</h3>
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" name="company_name" required>
                            </div>
                            <div class="form-group">
                                <label>Company Address</label>
                                <textarea name="company_address" required rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Industry</label>
                                <input type="text" name="industry" required>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Supervisor Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Supervisor Name</label>
                                    <input type="text" name="supervisor_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="supervisor_phone" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="supervisor_email" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Internship Period</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Position Details</h3>
                            <div class="form-group">
                                <label>Job Position</label>
                                <input type="text" name="job_position" required>
                            </div>
                            <div class="form-group">
                                <label>Job Description</label>
                                <textarea name="job_description" required rows="4"></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-modern">Submit Registration</button>
                            <button type="reset" class="btn-secondary">Reset Form</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
