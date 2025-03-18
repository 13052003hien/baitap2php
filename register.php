<?php
require 'config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->bindParam(':username', $_POST['username']);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = "Registration failed: Username already exists.";
        } else {
            // Create user account
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([$_POST['username'], $hashedPassword, $_POST['role']]);
            $userId = $conn->lastInsertId();
            
            // Add user details based on role
            if ($_POST['role'] === 'lecturer') {
                $stmt = $conn->prepare("INSERT INTO lecturers (user_id, first_name, last_name, email, department) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['department']
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO students (user_id, student_code, first_name, last_name, 
                                      email, phone, major, class_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $_POST['student_code'],
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['major'],
                    $_POST['class_code']
                ]);
            }
            
            $conn->commit();
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - Internship Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <form method="POST" id="registrationForm">
            <div class="form-group">
                <label>Role:</label>
                <select name="role" id="role" required>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                </select>
            </div>

            <div class="form-group">
                <input type="text" name="username" required placeholder="Username">
            </div>

            <div class="form-group">
                <input type="password" name="password" required placeholder="Password">
            </div>

            <div class="form-group">
                <input type="text" name="first_name" required placeholder="First Name">
            </div>

            <div class="form-group">
                <input type="text" name="last_name" required placeholder="Last Name">
            </div>

            <div class="form-group">
                <input type="email" name="email" required placeholder="Email">
            </div>

            <div id="studentFields">
                <div class="form-group">
                    <input type="text" name="student_code" placeholder="Student Code">
                </div>
                <div class="form-group">
                    <input type="text" name="phone" placeholder="Phone Number">
                </div>
                <div class="form-group">
                    <input type="text" name="major" placeholder="Major">
                </div>
                <div class="form-group">
                    <input type="text" name="class_code" placeholder="Class Code">
                </div>
            </div>

            <div id="lecturerFields" style="display: none;">
                <div class="form-group">
                    <input type="text" name="department" placeholder="Department">
                </div>
            </div>

            <button type="submit">Register</button>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>

    <script>
        document.getElementById('role').addEventListener('change', function() {
            const studentFields = document.getElementById('studentFields');
            const lecturerFields = document.getElementById('lecturerFields');
            
            if (this.value === 'student') {
                studentFields.style.display = 'block';
                lecturerFields.style.display = 'none';
            } else {
                studentFields.style.display = 'none';
                lecturerFields.style.display = 'block';
            }
        });
    </script>
</body>
</html>
