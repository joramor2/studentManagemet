<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Check if course_id is provided
if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    $_SESSION['message'] = "Invalid course ID.";
    header("Location: dashboard.php");
    exit();
}

$courseId = $_GET['course_id'];

// Fetch the course details
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['message'] = "Course not found.";
    header("Location: dashboard.php");
    exit();
}

$course = $result->fetch_assoc();
$stmt->close();

// Handle course update
if (isset($_POST['update_course'])) {
    $courseCode = $_POST['course_code'];
    $courseName = $_POST['course_name'];
    $program = $_POST['program'];
    $semester = $_POST['semester'];
    $academicYear = $_POST['academic_year'];
    $creditUnit = $_POST['credit_unit'];

    // Update the course in the database
    $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_name = ?, program = ?, semester = ?, academic_year = ?, credit_unit = ? WHERE id = ?");
    $stmt->bind_param("sssssii", $courseCode, $courseName, $program, $semester, $academicYear, $creditUnit, $courseId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Course updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating course: " . $stmt->error;
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
    <title>Edit Course</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h3>Edit Course</h3>
        <form action="edit_course.php?course_id=<?php echo $courseId; ?>" method="POST">
            <div class="mb-3">
                <label for="course_code" class="form-label">Course Code</label>
                <input type="text" name="course_code" id="course_code" class="form-control" value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="course_name" class="form-label">Course Name</label>
                <input type="text" name="course_name" id="course_name" class="form-control" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="program" class="form-label">Program</label>
                <select name="program" id="program" class="form-control" required>
                    <option value="ND-Nursing" <?php echo $course['program'] === 'ND-Nursing' ? 'selected' : ''; ?>>ND-Nursing</option>
                    <option value="ND-Midwifery" <?php echo $course['program'] === 'ND-Midwifery' ? 'selected' : ''; ?>>ND-Midwifery</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="semester" class="form-label">Semester</label>
                <select name="semester" id="semester" class="form-control" required>
                    <option value="First" <?php echo $course['semester'] === 'First' ? 'selected' : ''; ?>>First</option>
                    <option value="Second" <?php echo $course['semester'] === 'Second' ? 'selected' : ''; ?>>Second</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="academic_year" class="form-label">Academic Year</label>
                <input type="text" name="academic_year" id="academic_year" class="form-control" value="<?php echo htmlspecialchars($course['academic_year']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="credit_unit" class="form-label">Credit Unit</label>
                <input type="number" name="credit_unit" id="credit_unit" class="form-control" value="<?php echo htmlspecialchars($course['credit_unit']); ?>" required>
            </div>
            <button type="submit" name="update_course" class="btn btn-success">Update Course</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>