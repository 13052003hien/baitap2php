<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get student information
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get enrolled courses
$stmt = $conn->prepare("
    SELECT ic.* FROM internship_courses ic
    JOIN student_courses sc ON ic.course_id = sc.course_id
    WHERE sc.student_id = ?
");
$stmt->execute([$student['student_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get internship progress
$stmt = $conn->prepare("
    SELECT id.*, ic.course_code, ic.course_name 
    FROM internship_details id
    JOIN internship_courses ic ON id.course_id = ic.course_id
    WHERE id.student_id = ?
");
$stmt->execute([$student['student_id']]);
$internships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if internship_documents table exists
try {
    $documents = [];
    $stmt = $conn->prepare("
        SELECT d.*, id.company_name 
        FROM internship_documents d
        JOIN internship_details id ON d.internship_id = id.id
        WHERE id.student_id = ?
        ORDER BY d.uploaded_at DESC
    ");
    $stmt->execute([$student['student_id']]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist - we'll skip showing documents
    $documents = [];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Welcome, <?= htmlspecialchars($student['first_name']) ?></h1>
            <p>Manage your internship journey</p>
        </header>

        <nav class="nav-modern">
            <div class="nav-links">
                <a href="profile.php">My Profile</a>
                <a href="register_internship.php">Register Internship</a>
                <a href="upload_document.php">Upload Documents</a>
            </div>
            <a href="../logout.php" class="btn-modern">Logout</a>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Courses Enrolled</h3>
                <p class="stat-number"><?= count($courses) ?></p>
            </div>
            <div class="stat-card">
                <h3>Active Internships</h3>
                <p class="stat-number"><?= count($internships) ?></p>
            </div>
            <div class="stat-card">
                <h3>Documents Submitted</h3>
                <p class="stat-number"><?= count($documents) ?></p>
            </div>
        </div>

        <section class="content-section">
            <h3>Your Courses</h3>
            <?php if ($courses): ?>
                <div class="modern-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course['course_code']) ?></td>
                                    <td><?= htmlspecialchars($course['course_name']) ?></td>
                                    <td><span class="status-badge">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">You are not enrolled in any courses yet.</p>
            <?php endif; ?>
        </section>

        <section class="content-section">
            <h3>Your Internships</h3>
            <?php if ($internships): ?>
                <div class="modern-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Company</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internships as $internship): ?>
                                <tr>
                                    <td><?= htmlspecialchars($internship['course_code']) ?></td>
                                    <td><?= htmlspecialchars($internship['company_name']) ?></td>
                                    <td><?= htmlspecialchars($internship['status']) ?></td>
                                    <td><?= $internship['evaluation_score'] ?? 'Pending' ?></td>
                                    <td><?= htmlspecialchars($internship['feedback'] ?? 'No feedback yet') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">You haven't registered for any internships yet.</p>
            <?php endif; ?>
        </section>

        <section class="content-section">
            <h3>Your Documents</h3>
            <?php if ($documents): ?>
                <div class="modern-table">
                    <table>
                        <thead>
                            <tr></tr>
                                <th>Company</th>
                                <th>Document Type</th>
                                <th>Uploaded Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['company_name']) ?></td>
                                    <td><?= htmlspecialchars($doc['document_type']) ?></td>
                                    <td><?= htmlspecialchars($doc['uploaded_at']) ?></td>
                                    <td></td>
                                        <a href="../uploads/documents/<?= $doc['file_path'] ?>" 
                                           target="_blank">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">No documents uploaded yet.</p>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
