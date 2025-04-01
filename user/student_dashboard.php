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

// Fetch the level from the database
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

// Fetch available academic years from the academic_years table
$academicYearsQuery = $conn->prepare("SELECT DISTINCT year FROM academic_years ORDER BY year DESC");
$academicYearsQuery->execute();
$academicYearsResult = $academicYearsQuery->get_result();

$academicYears = [];
while ($row = $academicYearsResult->fetch_assoc()) {
    $academicYears[] = $row['year'];
}

$academicYearsQuery->close();

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
    $academicYear = $_POST['selected_academic_year'] ?? null;
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
            INSERT INTO course_registrations (student_id, academic_year, semester, course_id, level, status) 
            VALUES (?, ?, ?, ?, ?, 'current')
        ");

        foreach ($selectedCourses as $courseId) {
            // Check if the course is already registered
            $checkQuery->bind_param("isssi", $studentId, $academicYear, $semester, $courseId, $level);
            $checkQuery->execute();
            $checkResult = $checkQuery->get_result();
            $row = $checkResult->fetch_assoc();

            if ($row['count'] == 0) {
                // If not registered, insert the course
                $insertQuery->bind_param("isssi", $studentId, $academicYear, $semester, $courseId, $level);
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

        // Redirect to refresh the page and display registered courses
        $_SESSION['message'] = "Course registration saved successfully!";
        header("Location: student_dashboard.php");
        exit();
    }
}

// Handle loading courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_courses'])) {
    $semester = $_POST['semester'] ?? null;

    if (!$semester) {
        echo "<div class='alert alert-danger text-center'>Please select a semester.</div>";
    } else {
        // Fetch courses based on the student's program and semester
        $coursesQuery = $conn->prepare("
            SELECT * FROM courses WHERE program = ? AND semester = ?
        ");
        $coursesQuery->bind_param("ss", $program, $semester);
        $coursesQuery->execute();
        $courses = $coursesQuery->get_result();
        $coursesQuery->close();

        // Debugging: Log the state of $courses
        if ($courses) {
            error_log("Courses fetched successfully. Number of rows: " . $courses->num_rows);
        } else {
            error_log("Failed to fetch courses or no courses found.");
        }
    }
}

// Ensure $courses is initialized as an empty result if no query is executed
if (!isset($courses)) {
    $courses = new ArrayObject(); // Fallback to an empty object
}

// Fetch courses based on the student's program and semester
$courses = [];
if (!empty($program) && isset($_POST['semester'])) {
    $semester = $_POST['semester'];

    $coursesQuery = $conn->prepare("
        SELECT * FROM courses 
        WHERE program = ? AND semester = ?
    ");
    $coursesQuery->bind_param("ss", $program, $semester);
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

// Handle resetting registered courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_registered_courses'])) {
    // Delete all registered courses with status = 'current' for the logged-in student
    $resetQuery = $conn->prepare("DELETE FROM course_registrations WHERE student_id = ? AND status = 'current'");
    $resetQuery->bind_param("i", $studentId);

    if ($resetQuery->execute()) {
        $_SESSION['message'] = "All registered courses have been reset successfully!";
    } else {
        $_SESSION['message'] = "Error resetting registered courses: " . $resetQuery->error;
    }

    $resetQuery->close();
    header("Location: student_dashboard.php");
    exit();
}

// Fetch registered courses for the student (status = 'current')
$registeredCoursesQuery = $conn->prepare("
    SELECT r.id AS registration_id, c.course_code, c.course_name, c.credit_unit, r.academic_year, r.semester 
    FROM course_registrations r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ? AND r.status = 'current'
");
$registeredCoursesQuery->bind_param("i", $studentId);
$registeredCoursesQuery->execute();
$registeredCourses = $registeredCoursesQuery->get_result();
$registeredCoursesQuery->close();

// Debugging: Log the number of rows fetched
if ($registeredCourses && $registeredCourses->num_rows > 0) {
    error_log("Registered courses fetched successfully. Number of rows: " . $registeredCourses->num_rows);
} else {
    error_log("No registered courses found for student ID $studentId.");
}

// Fetch "My Courses" for the student
$myCoursesQuery = $conn->prepare("
    SELECT r.academic_year, r.semester, r.level, c.course_code, c.course_name, c.credit_unit
    FROM course_registrations r
    JOIN courses c ON r.course_id = c.id
    WHERE r.student_id = ?
");
$myCoursesQuery->bind_param("i", $studentId);
$myCoursesQuery->execute();
$myCourses = $myCoursesQuery->get_result();
$myCoursesQuery->close();

// Fetch "My Courses" for the student (status = 'archived')
$myCoursesQuery = $conn->prepare("
    SELECT r.academic_year, r.semester, r.level, c.course_code, c.course_name, c.credit_unit
    FROM course_registrations r
    JOIN courses c ON r.course_id = c.id
    WHERE r.student_id = ? AND r.status = 'archived'
");
$myCoursesQuery->bind_param("i", $studentId);
$myCoursesQuery->execute();
$myCourses = $myCoursesQuery->get_result();
$myCoursesQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        // Prompt the user to reset registered courses
        window.onload = function() {
            if (!sessionStorage.getItem('resetPromptShown')) {
                const shouldReset = confirm("Do you want to reset your registered courses? This action cannot be undone.");
                if (shouldReset) {
                    // Submit the reset form automatically
                    document.getElementById('resetCoursesForm').submit();
                }
                // Mark the prompt as shown in sessionStorage
                sessionStorage.setItem('resetPromptShown', 'true');
            }
        };

        function updateAcademicYear(selectedYear) {
            // Update the hidden input field with the selected academic year
            document.getElementById('selected_academic_year').value = selectedYear;
        }
    </script>
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

        <!-- Hidden Reset Form -->
        <form id="resetCoursesForm" action="student_dashboard.php" method="POST" style="display: none;">
            <input type="hidden" name="reset_registered_courses" value="1">
        </form>

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
        <div class="alert alert-warning text-center">
            Please confirm or update your level below.
        </div>
        <form action="student_dashboard.php" method="POST">
            <div class="mb-3">
                <label for="level" class="form-label">Select Level</label>
                <select class="form-control" id="level" name="level" required>
                    <option value="">Select Level</option>
                    <option value="100" <?php echo ($level === '100') ? 'selected' : ''; ?>>100</option>
                    <option value="200" <?php echo ($level === '200') ? 'selected' : ''; ?>>200</option>
                    <option value="300" <?php echo ($level === '300') ? 'selected' : ''; ?>>300</option>
                    <option value="400" <?php echo ($level === '400') ? 'selected' : ''; ?>>400</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save Level</button>
        </form>

        <!-- Academic Year and Semester Selection -->
        <form action="student_dashboard.php" method="POST" class="mt-4">
            <div class="mb-3">
                <label for="academic_year" class="form-label">Academic Year</label>
                <select class="form-control" id="academic_year" name="academic_year" required onchange="updateAcademicYear(this.value)">
                    <option value="">Select Academic Year</option>
                    <?php foreach ($academicYears as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="semester" class="form-label">Semester</label>
                <select class="form-control" id="semester" name="semester" required>
                    <option value="">Select Semester</option>
                    <option value="First">First</option>
                    <option value="Second">Second</option>
                </select>
            </div>
            <!-- Hidden input field to store the selected academic year -->
            <input type="hidden" id="selected_academic_year" name="selected_academic_year" value="">
            <button type="submit" name="load_courses" class="btn btn-secondary">Load Courses</button>
        </form>

        <!-- Courses Section -->
        <?php if (isset($_POST['load_courses']) && $program && $semester && $courses->num_rows > 0): ?>
            <form action="student_dashboard.php" method="POST" class="mt-4">
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
        <?php else: ?>
            <p class='text-muted'>No courses available for the selected program and semester.</p>
        <?php endif; ?>

        <!-- Display Registered Courses -->
        <h5 class="mt-5">Registered Courses:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Credit Unit</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($registeredCourses && $registeredCourses->num_rows > 0): ?>
                    <?php while ($row = $registeredCourses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['credit_unit']); ?></td>
                            <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                            <td><?php echo htmlspecialchars($row['semester']); ?></td>
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
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No registered courses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Download CRF Button -->
        <div class="mt-4">
            <a href="generate_crf.php" class="btn btn-success">Download CRF</a>
        </div>

        <!-- Reset Registered Courses -->
        <div class="mt-4">
            <form action="student_dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to reset all registered courses? This action cannot be undone.');">
                <input type="hidden" name="reset_registered_courses" value="1">
                <button type="submit" class="btn btn-danger">Reset Registered Courses</button>
            </form>
        </div>

        <!-- Available Courses -->
        <h4 class="mt-4">Available Courses</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($courses) && $courses instanceof mysqli_result && $courses->num_rows > 0): ?>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['semester']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No courses available for your program and semester.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- My Courses Section -->
        <h4 class="mt-5">My Courses</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Level</th>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Credit Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($myCourses && $myCourses->num_rows > 0): ?>
                    <?php while ($course = $myCourses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['academic_year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($course['semester'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($course['level'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($course['credit_unit'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No registered courses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
