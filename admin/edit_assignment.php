<?php
session_start();
include 'db.php'; // Database connection

// Check if lecturer_id is provided
if (!isset($_GET['lecturer_id']) || !is_numeric($_GET['lecturer_id'])) {
    $_SESSION['message'] = "Invalid lecturer ID.";
    header("Location: dashboard.php");
    exit();
}

$lecturerId = $_GET['lecturer_id'];

// Fetch lecturer details
$lecturer = $conn->query("SELECT full_name FROM users WHERE id = $lecturerId AND role = 'lecturer'")->fetch_assoc();
if (!$lecturer) {
    $_SESSION['message'] = "Lecturer not found.";
    header("Location: dashboard.php");
    exit();
}

// Fetch all courses
$coursesResult = $conn->query("SELECT id, course_name FROM courses");

// Fetch assigned courses
$assignedCoursesResult = $conn->query("SELECT course_id FROM lecturer_courses WHERE lecturer_id = $lecturerId");
$assignedCourses = [];
while ($row = $assignedCoursesResult->fetch_assoc()) {
    $assignedCourses[] = $row['course_id'];
}

// Handle form submission
if (isset($_POST['update_assignment'])) {
    $newCourseIds = $_POST['course_ids'];

    // Delete existing assignments
    $conn->query("DELETE FROM lecturer_courses WHERE lecturer_id = $lecturerId");

    // Insert new assignments
    $stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
    foreach ($newCourseIds as $courseId) {
        $stmt->bind_param("ii", $lecturerId, $courseId);
        $stmt->execute();
    }
    $stmt->close();

    $_SESSION['message'] = "Assignments updated successfully!";
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h3>Edit Assignments for <?php echo htmlspecialchars($lecturer['full_name']); ?></h3>
        <form action="edit_assignment.php?lecturer_id=<?php echo $lecturerId; ?>" method="POST">
            <div class="mb-3">
                <label for="course_ids" class="form-label">Select Courses</label>
                <select name="course_ids[]" id="course_ids" class="form-control" multiple required>
                    <?php while ($course = $coursesResult->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo in_array($course['id'], $assignedCourses) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple courses.</small>
            </div>
            <button type="submit" name="update_assignment" class="btn btn-primary">Update</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>