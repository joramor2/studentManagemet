<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Handle student approval
if (isset($_POST['approve_student'])) {
    $studentId = $_POST['student_id'];

    if (!is_numeric($studentId)) {
        $_SESSION['message'] = "Invalid student ID.";
    } else {
        $stmt = $conn->prepare("UPDATE students SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student approved successfully!";
        } else {
            $_SESSION['message'] = "Error approving student: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $studentId = $_POST['student_id'];

    if (!is_numeric($studentId)) {
        $_SESSION['message'] = "Invalid student ID.";
    } else {
        $newPassword = password_hash("default123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPassword, $studentId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Password reset successfully! Default password is 'default123'.";
        } else {
            $_SESSION['message'] = "Error resetting password: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch students
$students = $conn->query("SELECT * FROM students");
if (!$students) {
    die("Error fetching students: " . $conn->error);
}

// Fetch lecturers
$lecturers = $conn->query("SELECT * FROM lecturer");
if (!$lecturers) {
    die("Error fetching lecturers: " . $conn->error);
}

// Fetch courses
$courses = $conn->query("SELECT * FROM courses");
if (!$courses) {
    die("Error fetching courses: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Admin Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info text-center">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <h3>Welcome, Admin!</h3>

        <!-- Manage Students -->
        <h4 class="mt-4">Manage Students</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Registration Number</th>
                    <th>Email</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($student = $students->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['program']); ?></td>
                        <td><?php echo htmlspecialchars($student['status']); ?></td>
                        <td>
                            <?php if ($student['status'] === 'pending'): ?>
                                <form action="admin_dashboard.php" method="POST" class="d-inline">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" name="approve_student" class="btn btn-success btn-sm">Approve</button>
                                </form>
                            <?php endif; ?>
                            <form action="admin_dashboard.php" method="POST" class="d-inline">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <button type="submit" name="reset_password" class="btn btn-warning btn-sm">Reset Password</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Manage Lecturers -->
        <h4 class="mt-4">Manage Lecturers</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($lecturer = $lecturers->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lecturer['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                        <td><?php echo htmlspecialchars($lecturer['phone']); ?></td>
                        <td>
                            <form action="assign_courses.php" method="GET" class="d-inline">
                                <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Assign Courses</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Manage Courses -->
        <h4 class="mt-4">Manage Courses</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Program</th>
                    <th>Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($course = $courses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['program']); ?></td>
                        <td><?php echo htmlspecialchars($course['semester']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>