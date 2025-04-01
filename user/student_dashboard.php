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

// var_dump($_SESSION['user_id']);
// exit();

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

// Fetch the student's level from the database
$levelQuery = $conn->prepare("SELECT level FROM users WHERE id = ?");
$levelQuery->bind_param("i", $studentId);
$levelQuery->execute();
$levelResult = $levelQuery->get_result();

if ($levelResult->num_rows === 1) {
    $levelRow = $levelResult->fetch_assoc();
    $level = $levelRow['level'];
} else {
    $level = null; // Default to null if no level is found
}
$levelQuery->close();

// Check if the user has confirmed their level for the current session
if (!isset($_SESSION['level_confirmed']) || $_SESSION['level_confirmed'] !== true) {
    // Prompt the user to confirm or update their level
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['level'])) {
        $selectedLevel = $_POST['level'];

        // Update the level in the database
        $updateLevelQuery = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
        $updateLevelQuery->bind_param("si", $selectedLevel, $studentId);

        if ($updateLevelQuery->execute()) {
            $_SESSION['level_confirmed'] = true; // Mark level as confirmed
            $_SESSION['message'] = "Level updated successfully!";
            header("Location: student_dashboard.php");
            exit();
        } else {
            echo "<div class='alert alert-danger text-center'>Error updating level: " . $updateLevelQuery->error . "</div>";
        }

        $updateLevelQuery->close();
    }

    // Display the level selection form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Level</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container mt-5">
            <h3 class="text-center">Select Your Level</h3>
            <form action="student_dashboard.php" method="POST">
                <div class="mb-3">
                    <label for="level" class="form-label">Select Level</label>
                    <select class="form-control" id="level" name="level" required>
                        <option value="">Select Level</option>
                        <option value="100" <?php echo ($level === '100') ? 'selected' : ''; ?>>100 Level</option>
                        <option value="200" <?php echo ($level === '200') ? 'selected' : ''; ?>>200 Level</option>
                        <option value="300" <?php echo ($level === '300') ? 'selected' : ''; ?>>300 Level</option>
                        <option value="400" <?php echo ($level === '400') ? 'selected' : ''; ?>>400 Level</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Level</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Stop further execution until the level is confirmed
}

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

// Handle level selection form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['level'])) {
    $selectedLevel = $_POST['level'];

    // Update the level in the database
    $updateLevelQuery = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
    $updateLevelQuery->bind_param("si", $selectedLevel, $studentId);

    if ($updateLevelQuery->execute()) {
        $_SESSION['message'] = "Level updated successfully!";
        header("Location: student_dashboard.php");
        exit();
    } else {
        echo "<div class='alert alert-danger text-center'>Error updating level: " . $updateLevelQuery->error . "</div>";
    }

    $updateLevelQuery->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['program']) && !isset($_POST['level'])) {
    $academicYear = $_POST['academic_year'] ?? null;
    $semester = $_POST['semester'] ?? null;
    $selectedCourses = $_POST['courses'] ?? [];

    if (!$academicYear || !$semester || empty($selectedCourses)) {
        echo "<div class='alert alert-danger text-center'>All fields are required.</div>";
    } else {
        // Prepare the query to check for existing registrations
        $checkQuery = $conn->prepare("
            SELECT COUNT(*) AS count 
            FROM course_registrations 
            WHERE student_id = ? AND academic_year = ? AND semester = ? AND course_id = ?
        ");

        // Prepare the query to insert new registrations
        $insertQuery = $conn->prepare("
            INSERT INTO course_registrations (student_id, academic_year, semester, course_id) 
            VALUES (?, ?, ?, ?)
        ");

        foreach ($selectedCourses as $courseId) {
            // Check if the course is already registered
            $checkQuery->bind_param("isss", $studentId, $academicYear, $semester, $courseId);
            $checkQuery->execute();
            $checkResult = $checkQuery->get_result();
            $row = $checkResult->fetch_assoc();

            if ($row['count'] == 0) {
                // If not registered, insert the course
                $insertQuery->bind_param("isss", $studentId, $academicYear, $semester, $courseId);
                $insertQuery->execute();
            } else {
                // Fetch the course code for the duplicate course
                $courseCodeQuery = $conn->prepare("SELECT course_code FROM courses WHERE id = ?");
                $courseCodeQuery->bind_param("i", $courseId);
                $courseCodeQuery->execute();
                $courseCodeResult = $courseCodeQuery->get_result();
                $courseCodeRow = $courseCodeResult->fetch_assoc();
                $courseCode = $courseCodeRow['course_code'];
                $courseCodeQuery->close();

                // Display the error message with the course code
                echo "<div class='alert alert-danger text-center'>Course with code ($courseCode) is already registered.</div>";
            }
        }

        $checkQuery->close();
        $insertQuery->close();

        echo "<div class='alert alert-success text-center'>Course registration saved successfully!</div>";
    }
}

// Handle loading courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_courses'])) {
    $academicYear = $_POST['academic_year'] ?? null;
    $semester = $_POST['semester'] ?? null;

    if (!$academicYear || !$semester) {
        echo "<div class='alert alert-danger text-center'>Please select both Academic Year and Semester.</div>";
    } else {
        // Fetch courses based on the student's program, academic year, and semester
        $coursesQuery = $conn->prepare("
            SELECT * FROM courses 
            WHERE program = ? AND academic_year = ? AND semester = ?
        ");
        $coursesQuery->bind_param("sss", $program, $academicYear, $semester);
        $coursesQuery->execute();
        $courses = $coursesQuery->get_result();
        $coursesQuery->close();
    }
}

// Fetch courses based on the student's program, academic year, and semester
$courses = [];
if (!empty($program) && isset($_POST['academic_year']) && isset($_POST['semester'])) {
    $academicYear = $_POST['academic_year'];
    $semester = $_POST['semester'];

    $coursesQuery = $conn->prepare("
        SELECT * FROM courses 
        WHERE program = ? AND academic_year = ? AND semester = ?
    ");
    $coursesQuery->bind_param("sss", $program, $academicYear, $semester);
    $coursesQuery->execute();
    $courses = $coursesQuery->get_result();
    $coursesQuery->close();
}

// Handle course registration deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_registration'])) {
    $registrationId = $_POST['registration_id'];

    // Delete the course registration
    $deleteQuery = $conn->prepare("DELETE FROM course_registrations WHERE id = ?");
    $deleteQuery->bind_param("i", $registrationId);

    if ($deleteQuery->execute()) {
        $_SESSION['message'] = "Course registration deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting course registration: " . $deleteQuery->error;
    }

    $deleteQuery->close();
    header("Location: student_dashboard.php");
    exit();
}

// Fetch registered courses for the student
$registeredCoursesQuery = $conn->prepare("
    SELECT r.id AS registration_id, c.course_code, c.course_name, c.credit_unit, r.academic_year, r.semester 
    FROM course_registrations r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ?
");
$registeredCoursesQuery->bind_param("i", $studentId);
$registeredCoursesQuery->execute();
$registeredCourses = $registeredCoursesQuery->get_result();
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

        <!-- Level Selection -->
        <?php if (empty($level)): ?>
            <div class="alert alert-warning text-center">
                You have not selected your level yet. Please select your level below.
            </div>
            <form action="student_dashboard.php" method="POST">
                <div class="mb-3">
                    <label for="level" class="form-label">Select Level</label>
                    <select class="form-control" id="level" name="level" required>
                        <option value="">Select Level</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Level</button>
            </form>
        <?php endif; ?>

        <!-- Academic Year and Semester Selection -->
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
            <button type="submit" name="load_courses" class="btn btn-secondary">Load Courses</button>
        </form>

        <!-- Courses Section -->
        <?php if (isset($_POST['load_courses']) && !empty($courses) && $courses->num_rows > 0): ?>
            <form action="student_dashboard.php" method="POST" class="mt-4">
                <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academicYear); ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                <div class="mb-3">
                    <label for="courses" class="form-label">Courses</label>
                    <div>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="courses[]" value="<?php echo $course['id']; ?>" id="course_<?php echo $course['id']; ?>">
                                <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Submit Registration</button>
            </form>
        <?php elseif (isset($_POST['load_courses'])): ?>
            <p class="text-muted">No courses available for the selected program, academic year, and semester.</p>
        <?php endif; ?>

        <!-- Display Registered Courses -->
        <h5 class="mt-5">Registered Courses:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Credit Unit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $registeredCourses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                        <td><?php echo htmlspecialchars($row['credit_unit']); ?></td>
                        <td>
                            <!-- Edit Button -->
                            <form action="edit_registration.php" method="GET" class="d-inline">
                                <input type="hidden" name="registration_id" value="<?php echo $row['registration_id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm">Edit</button>
                            </form>

                            <!-- Delete Button -->
                            <form action="student_dashboard.php" method="POST" class="d-inline">
                                <input type="hidden" name="registration_id" value="<?php echo $row['registration_id']; ?>">
                                <button type="submit" name="delete_registration" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this registration?');">Delete</button>
                            </form>
                        </td>
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
                    <th>Course Code</th> <!-- New column -->
                    <th>Course Name</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($courses && $courses->num_rows > 0): ?>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td> <!-- Display Course Code -->
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['academic_year']); ?></td>
                            <td><?php echo htmlspecialchars($course['semester']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No courses available for your program and semester.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="generate_crf.php" class="btn btn-primary">Download CRF</a>
    </div>
</body>
</html>