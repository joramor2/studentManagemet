<?php
session_start();
include 'db.php'; // Database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch user from database
    $stmt = $conn->prepare("SELECT id, full_name, registration_number, password, role FROM users WHERE registration_number = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on role
        if ($user['role'] == 'student') {
            header("Location: student_dashboard.php");
        } elseif ($user['role'] == 'lecturer') {
            header("Location: lecturer_dashboard.php");
        } else {
            header("Location: admin_dashboard.php");
        }
        exit();
    } else {
        echo "<script>alert('Invalid login credentials!'); window.location.href='login.html';</script>";
    }
}
?>

