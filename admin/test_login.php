var_dump($_POST);
exit;
<?php
session_start();
require 'db.php';  // Include the database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Check if user exists
    $sql = "SELECT * FROM users WHERE reg_no = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify password
        $enteredPassword = $password; // Replace with the password you want to test
        $storedHash = $user["password"]; // Replace with the hash from your database

        if (password_verify($enteredPassword, $storedHash)) {
            echo "Password matched!";
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["reg_no"];
            $_SESSION["role"] = $user["role"]; // Role can be 'student' or 'lecturer'

            // Redirect based on role
            if ($user["role"] == "student") {
                header("Location: student_dashboard.php");
            } else {
                header("Location: lecturer_dashboard.php");
            }
            exit();
        } else {
            echo "Password did not match.";
        }
    } else {
        echo "User not found.";
    }

    $stmt->close();
}
$conn->close();
?>
