<?php

include '../app/db.php'; // Database connection
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if the user is a student
if ($_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}


// Fetch student-specific data
$studentId = $_SESSION['user_id'];

// Fetch the program from the database
$programQuery = $conn->prepare("SELECT program FROM users WHERE id = ?");
$programQuery->bind_param("i", $studentId);
$programQuery->execute();
$programResult = $programQuery->get_result();

if ($programResult->num_rows === 1) {
    $programRow = $programResult->fetch_assoc();
    $program = $programRow['program'];
} else {
    $program = null; // Default to null if no program is found
}
$programQuery->close();

// var_dump($program);

// Handle program selection form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['program'])) {
    $selectedProgram = $_POST['program'];

    // Update the program in the database
    $updateProgramQuery = $conn->prepare("UPDATE users SET program = ? WHERE id = ?");
    $updateProgramQuery->bind_param("si", $selectedProgram, $studentId);

    if ($updateProgramQuery->execute()) {
        $_SESSION['message'] = "Program updated successfully!";
        header("Location: student_dashboard.php");
        exit();
    } else {
        echo "<div class='alert alert-danger text-center'>Error updating program: " . $updateProgramQuery->error . "</div>";
    }

    $updateProgramQuery->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['program'])) {
    $academicYear = $_POST['academic_year'] ?? null;
    $semester = $_POST['semester'] ?? null;
    $selectedCourses = $_POST['courses'] ?? [];

    if (!$academicYear || !$semester || empty($selectedCourses)) {
        echo "<div class='alert alert-danger text-center'>All fields are required.</div>";
    } else {
        // Save course registration
        $stmt = $conn->prepare("INSERT INTO course_registrations (student_id, academic_year, semester, course_id) VALUES (?, ?, ?, ?)");
        foreach ($selectedCourses as $courseId) {
            $stmt->bind_param("isss", $studentId, $academicYear, $semester, $courseId);
            $stmt->execute();
        }
        $stmt->close();

        echo "<div class='alert alert-success text-center'>Course registration saved successfully!</div>";
    }
}

// Fetch courses based on the student's program and semester
$courses = [];
if (isset($_POST['semester'])) {
    $studentProgram = $_SESSION['program']; // Assuming the student's program is stored in the session
    $studentSemester = $_POST['semester']; // Assuming the semester is selected by the student

    $coursesQuery = $conn->prepare("SELECT * FROM courses WHERE program = ? AND semester = ?");
    $coursesQuery->bind_param("ss", $studentProgram, $studentSemester);
    $coursesQuery->execute();
    $courses = $coursesQuery->get_result();
    $coursesQuery->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Student Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success text-center">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Program Selection -->
        <?php if (empty($program)): ?>
            <div class="alert alert-warning text-center">
                You have not selected a program yet. Please select your program below.
            </div>
            <form action="student_dashboard.php" method="POST">
                <div class="mb-3">
                    <label for="program" class="form-label">Select Program</label>
                    <select class="form-control" id="program" name="program" required>
                        <option value="">Select Program</option>
                        <option value="ND-Nursing">ND-Nursing</option>
                        <option value="ND-Midwifery">ND-Midwifery</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Program</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info text-center">
                Your selected program: <strong><?php echo htmlspecialchars($program); ?></strong>
            </div>
        <?php endif; ?>

        <!-- Course Registration Form -->
        <form action="student_dashboard.php" method="POST" class="mt-4">
            <div class="mb-3">
                <label for="academic_year" class="form-label">Academic Year</label>
                <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="e.g., 2024/2025" required>
            </div>
            <div class="mb-3">
                <label for="semester" class="form-label">Semester</label>
                <select class="form-control" id="semester" name="semester" required>
                    <option value="">Select Semester</option>
                    <option value="First">First</option>
                    <option value="Second">Second</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="courses" class="form-label">Courses</label>
                <div>
                    <?php if (!empty($courses)): ?>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="courses[]" value="<?php echo $course['id']; ?>" id="course_<?php echo $course['id']; ?>">
                                <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No courses available for the selected semester.</p>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit Registration</button>
        </form>

        <!-- Display Registered Courses -->
        <h5 class="mt-5">Registered Courses:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $registeredCoursesQuery = $conn->prepare("SELECT c.course_name, r.academic_year, r.semester FROM course_registrations r JOIN courses c ON r.course_id = c.id WHERE r.student_id = ?");
                $registeredCoursesQuery->bind_param("i", $studentId);
                $registeredCoursesQuery->execute();
                $registeredCourses = $registeredCoursesQuery->get_result();
                while ($row = $registeredCourses->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php $registeredCoursesQuery->close(); ?>
            </tbody>
        </table>

        <!-- Available Courses -->
        <h4 class="mt-4">Available Courses</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Academic Year</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($courses && $courses->num_rows > 0): ?>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['academic_year']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">No courses available for your program and semester.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>