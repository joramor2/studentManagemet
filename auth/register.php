<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System - Register</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h3 class="text-center">Student Registration</h3>
                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="registration_number" class="form-label">Registration Number</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <p class="text-center mt-3"><a href="login.php">Already have an account? Login here</a></p>
            </div>
        </div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Include database connection
        require '../app/db.php';

        // Get form data and validate
        $full_name = $_POST['full_name'] ?? null;
        $registration_number = $_POST['registration_number'] ?? null;
        $email = $_POST['email'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $password = $_POST['password'] ?? null;

        // Hardcode the role as 'student'
        $role = 'student';

        // Check for missing fields
        if (!$full_name || !$registration_number || !$email || !$phone || !$password) {
            die("<div class='alert alert-danger text-center'>All fields are required.</div>");
        }

        // Check for duplicate email or registration number
        $check_sql = "SELECT * FROM users WHERE email = ? OR registration_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $email, $registration_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            die("<div class='alert alert-danger text-center'>Error: Duplicate entry for Registration Number or Email.</div>");
        }

        $check_stmt->close();

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert data into the database
        $sql = "INSERT INTO users (full_name, registration_number, email, phone, role, password) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("<div class='alert alert-danger text-center'>SQL error: " . $conn->error . "</div>");
        }

        $stmt->bind_param("ssssss", $full_name, $registration_number, $email, $phone, $role, $hashed_password);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success text-center'>Registration successful! Redirecting to login...</div>";
            // Redirect to login page
            header("refresh:3;url=../auth/login.php");
            exit();
        } else {
            die("<div class='alert alert-danger text-center'>Error executing query: " . $stmt->error . "</div>");
        }

        $stmt->close();
        $conn->close();
    }
    ?>
</body>
</html>