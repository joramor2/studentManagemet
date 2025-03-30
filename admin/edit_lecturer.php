<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Fetch lecturer details
if (isset($_GET['lecturer_id'])) {
    $lecturerId = $_GET['lecturer_id'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'lecturer'");
    $stmt->bind_param("i", $lecturerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $lecturer = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Lecturer not found.";
        header("Location: dashboard.php");
        exit();
    }

    $stmt->close();
}

// Handle form submission to update lecturer details
if (isset($_POST['update_lecturer'])) {
    $lecturerId = $_POST['lecturer_id'];
    $lecturerName = $_POST['lecturer_name'];
    $lecturerEmail = $_POST['lecturer_email'];
    $lecturerPhone = $_POST['lecturer_phone'];

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'lecturer'");
    $stmt->bind_param("sssi", $lecturerName, $lecturerEmail, $lecturerPhone, $lecturerId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Lecturer updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating lecturer: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lecturer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h3>Edit Lecturer</h3>
        <form action="edit_lecturer.php" method="POST">
            <input type="hidden" name="lecturer_id" value="<?php echo htmlspecialchars($lecturer['id']); ?>">
            <div class="mb-3">
                <label for="lecturer_name" class="form-label">Full Name</label>
                <input type="text" name="lecturer_name" id="lecturer_name" class="form-control" value="<?php echo htmlspecialchars($lecturer['full_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="lecturer_email" class="form-label">Email</label>
                <input type="email" name="lecturer_email" id="lecturer_email" class="form-control" value="<?php echo htmlspecialchars($lecturer['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="lecturer_phone" class="form-label">Phone Number</label>
                <input type="text" name="lecturer_phone" id="lecturer_phone" class="form-control" value="<?php echo htmlspecialchars($lecturer['phone']); ?>" required>
            </div>
            <button type="submit" name="update_lecturer" class="btn btn-primary">Update</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>