<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h3 class="text-center">Login</h3>
                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username (Reg No / Email)</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <p class="text-center mt-3">Don't have an account? <a href="register.php">Register</a></p>
                <p class="text-center mt-3"><a href="forgot_password.php">Forgot Password?</a></p>
            </div>
        </div>
    </div>

    <?php
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Include database connection
        require '../app/db.php';

        // Get form data
        $username = $_POST['username'] ?? null; // Can be registration number or email
        $password = $_POST['password'] ?? null;

        // Validate input
        if (!$username || !$password) {
            echo "<div class='alert alert-danger text-center'>Both fields are required.</div>";
            exit();
        }

        // Check if the user exists in the database
        $sql = "SELECT * FROM users WHERE registration_number = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "<div class='alert alert-danger text-center'>Database error: " . $conn->error . "</div>";
            exit();
        }

        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Store user information in the session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['full_name']; // Store full name in session
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else if ($user['role'] === 'student') {
                    header("Location: ../user/student_dashboard.php");
                } else if ($user['role'] === 'lecturer') {
                    header("Location: lecturer_dashboard.php");
                } else {
                    echo "<div class='alert alert-danger text-center'>Unknown role: " . htmlspecialchars($user['role']) . "</div>";
                }
                exit();
            } else {
                echo "<div class='alert alert-danger text-center'>Invalid password.</div>";
            }
        } else {
            echo "<div class='alert alert-danger text-center'>User not found.</div>";
        }

        $stmt->close();
        $conn->close();
    }
    ?>
</body>
</html>
