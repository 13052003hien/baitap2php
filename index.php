<?php
session_start();
require 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Internship Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="landing-container">
        <header class="site-header">
            <h1>Internship Management System</h1>
            <p>Your gateway to successful internship experiences</p>
        </header>

        <div class="feature-grid">
            <div class="feature-card">
                <h3>For Students</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Easy internship registration</li>
                    <li><i class="fas fa-file-alt"></i> Submit weekly reports</li>
                    <li><i class="fas fa-chart-line"></i> Track your progress</li>
                    <li><i class="fas fa-building"></i> Connect with companies</li>
                </ul>
                <div class="card-actions">
                    <a href="register.php?role=student" class="btn">Register as Student</a>
                </div>
            </div>

            <div class="feature-card">
                <h3>For Lecturers</h3>
                <ul>
                    <li><i class="fas fa-users"></i> Manage student internships</li>
                    <li><i class="fas fa-file-excel"></i> Import student data</li>
                    <li><i class="fas fa-tasks"></i> Track student progress</li>
                    <li><i class="fas fa-chart-bar"></i> Generate reports</li>
                </ul>
                <div class="card-actions">
                    <a href="register.php?role=lecturer" class="btn">Register as Lecturer</a>
                </div>
            </div>
        </div>

        <div class="auth-actions">
            <p>Already have an account?</p>
            <a href="login.php" class="btn btn-primary">Login</a>
        </div>

        <footer class="site-footer">
            <div class="footer-content">
                <p>Need help? Contact support at support@internship.com</p>
                <div class="footer-links">
                    <a href="#">About</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://kit.fontawesome.com/your-kit-code.js"></script>
</body>
</html>