<?php
require '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE users SET password = ?, is_first_login = 0 WHERE user_id = ?");
        $stmt->execute([$new_password, $user_id]);
        $_SESSION['is_first_login'] = false;
        header('Location: dashboard.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error updating password: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
</head>
<body>
    <h1>Change Password</h1>
    <?php if (isset($error)) echo "<p style='color: red'>$error</p>"; ?>
    
    <form method="POST">
        <input type="password" name="new_password" required placeholder="New Password">
        <button type="submit">Change Password</button>
    </form>
</body>
</html>
