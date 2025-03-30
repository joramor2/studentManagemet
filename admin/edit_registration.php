<?php
// session_start();
include '../app/db.php'; // Database connection

// Check if registration_id is provided
if (!isset($_GET['registration_id']) || !is_numeric($_GET['registration_id'])) {
    $_SESSION['message'] = "Invalid registration ID.";
    header("Location: student_dashboard.php");
    exit();
}

$registrationId = $_GET['registration_id'];

// Fetch the registration details
$registrationQuery = $conn->prepare("
    SELECT r.id, r.academic_year, r.semester, c.course_name 
    FROM course_registrations r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.id = ?
");
$registrationQuery->bind_param("i", $registrationId);
$registrationQuery->execute();
$registration = $registrationQuery->get_result()->fetch_assoc();
$registrationQuery->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academicYear = $_POST['academic_year'];
    $semester = $_POST['semester'];

    // Update the registration
    $updateQuery = $conn->prepare("
        UPDATE course_registrations 
        SET academic_year = ?, semester = ? 
        WHERE id = ?
    ");
    $updateQuery->bind_param("ssi", $academicYear, $semester, $registrationId);

    if ($updateQuery->execute()) {
        $_SESSION['message'] = "Course registration updated successfully!";
        header("Location: student_dashboard.php");
        exit();
    } else {
        echo "<div class='alert alert-danger text-center'>Error updating registration: " . $updateQuery->error . "</div>";
    }

    $updateQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Registration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h3>Edit Registration for <?php echo htmlspecialchars($registration['course_name']); ?></h3>
        <form action="edit_registration.php?registration_id=<?php echo $registrationId; ?>" method="POST">
            <div class="mb-3">
                <label for="academic_year" class="form-label">Academic Year</label>
                <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($registration['academic_year']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="semester" class="form-label">Semester</label>
                <select class="form-control" id="semester" name="semester" required>
                    <option value="First" <?php echo $registration['semester'] === 'First' ? 'selected' : ''; ?>>First</option>
                    <option value="Second" <?php echo $registration['semester'] === 'Second' ? 'selected' : ''; ?>>Second</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>