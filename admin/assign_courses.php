<?php
include '../app/db.php'; // Database connection

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get the lecturer ID from the GET request
if (!isset($_GET['lecturer_id'])) {
    echo "<div class='alert alert-danger text-center'>No lecturer selected. Please go back and try again.</div>";
    exit();
}

$lecturerId = $_GET['lecturer_id'];

// Fetch lecturer details
$lecturerQuery = $conn->prepare("SELECT * FROM lecturers WHERE id = ?");
$lecturerQuery->bind_param("i", $lecturerId);
$lecturerQuery->execute();
$lecturer = $lecturerQuery->get_result()->fetch_assoc();
$lecturerQuery->close();

if (!$lecturer) {
    echo "<div class='alert alert-danger text-center'>Lecturer not found. Please go back and try again.</div>";
    exit();
}

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourses = $_POST['courses'] ?? [];

    if (empty($selectedCourses)) {
        echo "<div class='alert alert-danger text-center'>Please select at least one course to assign.</div>";
    } else {
        // Remove existing course assignments for this lecturer
        $deleteStmt = $conn->prepare("DELETE FROM lecturer_courses WHERE lecturer_id = ?");
        $deleteStmt->bind_param("i", $lecturerId);
        $deleteStmt->execute();
        $deleteStmt->close();

        // Assign selected courses to the lecturer
        $assignStmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
        foreach ($selectedCourses as $courseId) {
            $assignStmt->bind_param("ii", $lecturerId, $courseId);
            $assignStmt->execute();
        }
        $assignStmt->close();

        echo "<div class='alert alert-success text-center'>Courses assigned successfully!</div>";
    }
}

// Fetch all courses
$courses = $conn->query("SELECT * FROM courses");

// Fetch courses already assigned to the lecturer
$assignedCoursesQuery = $conn->prepare("SELECT course_id FROM lecturer_courses WHERE lecturer_id = ?");
$assignedCoursesQuery->bind_param("i", $lecturerId);
$assignedCoursesQuery->execute();
$assignedCoursesResult = $assignedCoursesQuery->get_result();
$assignedCourses = [];
while ($row = $assignedCoursesResult->fetch_assoc()) {
    $assignedCourses[] = $row['course_id'];
}
$assignedCoursesQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Courses</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">Admin Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>Assign Courses to Lecturer</h3>
        <p><strong>Lecturer:</strong> <?php echo htmlspecialchars($lecturer['full_name']); ?></p>

        <form action="assign_courses.php?lecturer_id=<?php echo $lecturerId; ?>" method="POST">
            <div class="mb-3">
                <label for="courses" class="form-label">Select Courses</label>
                <div>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                name="courses[]" 
                                value="<?php echo $course['id']; ?>" 
                                id="course_<?php echo $course['id']; ?>"
                                <?php echo in_array($course['id'], $assignedCourses) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['program']); ?> - <?php echo htmlspecialchars($course['semester']); ?>)
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Assign Courses</button>
        </form>
    </div>
</body>
</html>