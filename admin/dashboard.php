<?php
include '../app/db.php'; // Database connection

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}



// Fetch total counts for statistics
$totalStudents = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$totalLecturers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'")->fetch_assoc()['total'];
$totalCourses = $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc()['total'];

// Fetch distinct programs from the courses table
$programsResult = $conn->query("SELECT DISTINCT program FROM courses");
if (!$programsResult) {
    die("Error fetching programs: " . $conn->error);
}

// Store programs in an array
$programs = [];
while ($row = $programsResult->fetch_assoc()) {
    $programs[] = $row['program'];
}

// Handle student approval
if (isset($_POST['approve_student'])) {
    $studentId = $_POST['student_id'];

    if (!is_numeric($studentId)) {
        $_SESSION['message'] = "Invalid student ID.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $studentId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student approved successfully!";
        } else {
            $_SESSION['message'] = "Error approving student: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: dashboard.php");
    exit();
}

// Handle student addition
if (isset($_POST['add_student'])) {
    $studentName = $_POST['student_name'];
    $studentEmail = $_POST['student_email'];
    $studentProgram = $_POST['student_program'];
    $studentRegistrationNumber = $_POST['student_registration_number'];
    $studentPhone = $_POST['student_phone'];

    // Check if the registration number already exists
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM users WHERE registration_number = ?");
    $stmt->bind_param("s", $studentRegistrationNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        $_SESSION['message'] = "Error: Registration number already exists.";
        header("Location: dashboard.php");
        exit();
    }

    // Generate a random password
    $randomPassword = bin2hex(random_bytes(4)); // Generates an 8-character random password
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

    // Insert the student into the database
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, program, registration_number, role, password, status) VALUES (?, ?, ?, ?, ?, 'student', ?, 'pending')");
    $stmt->bind_param("ssssss", $studentName, $studentEmail, $studentPhone, $studentProgram, $studentRegistrationNumber, $hashedPassword);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Student added successfully! Password: $randomPassword";
    } else {
        $_SESSION['message'] = "Error adding student: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle course addition
if (isset($_POST['add_course'])) {
    $courseCode = $_POST['course_code'];
    $courseName = $_POST['course_name'];
    $program = $_POST['program'];
    $semester = $_POST['semester'];
    $academicYear = $_POST['academic_year'];
    $creditUnit = $_POST['credit_unit'];

    // Insert the course into the database
    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, program, semester, academic_year, credit_unit) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $courseCode, $courseName, $program, $semester, $academicYear, $creditUnit);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Course added successfully!";
    } else {
        $_SESSION['message'] = "Error adding course: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle course deletion
if (isset($_POST['delete_course'])) {
    $courseId = $_POST['course_id'];

    // Delete the course from the database
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $courseId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Course deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting course: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle lecturer addition
if (isset($_POST['add_lecturer'])) {
    $lecturerName = $_POST['lecturer_name'];
    $lecturerEmail = $_POST['lecturer_email'];
    $lecturerPhone = $_POST['lecturer_phone'];

    // Generate a random password
    $randomPassword = bin2hex(random_bytes(4)); // Generates an 8-character random password
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

    // Insert the lecturer into the database
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, role, password, registration_number) VALUES (?, ?, ?, 'lecturer', ?, NULL)");
    $stmt->bind_param("ssss", $lecturerName, $lecturerEmail, $lecturerPhone, $hashedPassword);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Lecturer added successfully! Password: $randomPassword";
    } else {
        $_SESSION['message'] = "Error adding lecturer: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle lecturer deletion
if (isset($_POST['delete_lecturer'])) {
    $lecturerId = $_POST['lecturer_id'];

    // Delete the lecturer from the database
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'lecturer'");
    $stmt->bind_param("i", $lecturerId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Lecturer deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting lecturer: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle course assignment to lecturers
if (isset($_POST['assign_courses'])) {
    $lecturerId = $_POST['lecturer_id'];
    $courseIds = $_POST['course_ids'];

    if (!is_numeric($lecturerId) || empty($courseIds)) {
        $_SESSION['message'] = "Invalid lecturer or courses selected.";
        header("Location: dashboard.php");
        exit();
    }

    // Validate course_ids
    $validCourseIds = [];
    $result = $conn->query("SELECT id FROM courses");
    while ($row = $result->fetch_assoc()) {
        $validCourseIds[] = $row['id'];
    }

    foreach ($courseIds as $courseId) {
        if (!in_array($courseId, $validCourseIds)) {
            $_SESSION['message'] = "Error: Invalid course selected.";
            header("Location: dashboard.php");
            exit();
        }
    }

    // Insert assignments into the lecturer_courses table
    $stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
    foreach ($courseIds as $courseId) {
        $stmt->bind_param("ii", $lecturerId, $courseId);
        $stmt->execute();
    }
    $stmt->close();

    $_SESSION['message'] = "Courses assigned successfully!";
    header("Location: dashboard.php");
    exit();
}

// Handle delete assignment
if (isset($_POST['delete_assignment'])) {
    $lecturerId = $_POST['lecturer_id'];

    // Delete all assignments for the selected lecturer
    $stmt = $conn->prepare("DELETE FROM lecturer_courses WHERE lecturer_id = ?");
    $stmt->bind_param("i", $lecturerId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Assignments deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting assignments: " . $stmt->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Pagination settings
$recordsPerPage = 10;

// Manage Students Pagination with Search
$searchQuery = isset($_GET['search_students']) ? trim($_GET['search_students']) : '';
$searchCondition = $searchQuery ? "AND (full_name LIKE ? OR email LIKE ? OR registration_number LIKE ?)" : '';

$pageStudents = isset($_GET['page_students']) ? (int)$_GET['page_students'] : 1;
$offsetStudents = ($pageStudents - 1) * $recordsPerPage;

$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE role = 'student' $searchCondition 
    LIMIT $recordsPerPage OFFSET $offsetStudents
");

if ($searchQuery) {
    $searchTerm = "%$searchQuery%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}

$stmt->execute();
$students = $stmt->get_result();

// Count total students for pagination
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM users 
    WHERE role = 'student' $searchCondition
");

if ($searchQuery) {
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}

$stmt->execute();
$totalStudentsCount = $stmt->get_result()->fetch_assoc()['total'];
$totalStudentsPages = ceil($totalStudentsCount / $recordsPerPage);

// Manage Lecturers Pagination
$pageLecturers = isset($_GET['page_lecturers']) ? (int)$_GET['page_lecturers'] : 1;
$offsetLecturers = ($pageLecturers - 1) * $recordsPerPage;
$totalLecturersCount = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'")->fetch_assoc()['total'];
$totalLecturersPages = ceil($totalLecturersCount / $recordsPerPage);
$lecturers = $conn->query("SELECT * FROM users WHERE role = 'lecturer' LIMIT $recordsPerPage OFFSET $offsetLecturers");

// Manage Courses Pagination
$pageCourses = isset($_GET['page_courses']) ? (int)$_GET['page_courses'] : 1;
$offsetCourses = ($pageCourses - 1) * $recordsPerPage;
$totalCoursesCount = $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc()['total'];
$totalCoursesPages = ceil($totalCoursesCount / $recordsPerPage);
$courses = $conn->query("SELECT * FROM courses LIMIT $recordsPerPage OFFSET $offsetCourses");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .menu-bar {
            margin-bottom: 20px;
        }
        .menu-bar a {
            margin-right: 10px;
        }
        .section {
            display: none;
        }
        .section.active {
            display: block;
        }
    </style>
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
            <div class="alert <?php echo strpos($_SESSION['message'], 'Error') !== false ? 'alert-danger' : 'alert-info'; ?> text-center">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <h3>Welcome, Admin!</h3>

        <!-- Statistics Section -->
        <div class="row text-center mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Students</h5>
                        <p class="card-text display-4"><?php echo $totalStudents; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Lecturers</h5>
                        <p class="card-text display-4"><?php echo $totalLecturers; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Courses</h5>
                        <p class="card-text display-4"><?php echo $totalCourses; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Bar -->
        <div class="menu-bar">
            <a href="#" class="btn btn-primary" onclick="showSection('students')">Manage Students</a>
            <a href="#" class="btn btn-primary" onclick="showSection('lecturers')">Manage Lecturers</a>
            <a href="#" class="btn btn-primary" onclick="showSection('courses')">Manage Courses</a>
            <a href="#" class="btn btn-primary" onclick="showSection('assign_courses')">Assign Courses to Lecturers</a>
        </div>

        <!-- Manage Students Section -->
        <div id="students" class="section active">
            <h4 class="mt-4">Manage Students</h4>

            <div class="mb-3">
                <a href="export_students.php" class="btn btn-success">Download Students as Excel</a>
            </div>

            <!-- Search Bar -->
            <form method="GET" action="dashboard.php" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search_students" class="form-control" placeholder="Search students by name, email, or registration number" value="<?php echo isset($_GET['search_students']) ? htmlspecialchars($_GET['search_students']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>

            <!-- Add Student Form -->
            <form action="dashboard.php" method="POST" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" name="student_name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" name="student_email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="col-md-2">
                        <select name="student_program" class="form-control" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program); ?>">
                                    <?php echo htmlspecialchars($program); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="student_registration_number" class="form-control" placeholder="Registration Number" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="student_phone" class="form-control" placeholder="Phone Number" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="add_student" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </form>

            <!-- Display Students -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Registration Number</th> <!-- New column -->
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['program'] ?? 'Program not selected yet'); ?></td>
                                <td><?php echo htmlspecialchars($student['registration_number']); ?></td> <!-- New column -->
                                <td><?php echo htmlspecialchars($student['status']); ?></td>
                                <td>
                                    <?php if ($student['status'] === 'pending'): ?>
                                        <form action="dashboard.php" method="POST" class="d-inline">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="approve_student" class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="dashboard.php" method="POST" class="d-inline">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="reset_password" class="btn btn-warning btn-sm">Reset Password</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination for Students -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalStudentsPages; $i++): ?>
                        <li class="page-item <?php echo $i == $pageStudents ? 'active' : ''; ?>">
                            <a class="page-link" href="?page_students=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

        <!-- Manage Lecturers Section -->
        <div id="lecturers" class="section">
            <h4 class="mt-4">Manage Lecturers</h4>

            <!-- Add Lecturer Form -->
            <form action="dashboard.php" method="POST" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" name="lecturer_name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="col-md-4">
                        <input type="email" name="lecturer_email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="lecturer_phone" class="form-control" placeholder="Phone Number" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="add_lecturer" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </form>

            <!-- Display Lecturers -->
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
                    <?php if ($lecturers && $lecturers->num_rows > 0): ?>
                        <?php while ($lecturer = $lecturers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lecturer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                                <td><?php echo htmlspecialchars($lecturer['phone']); ?></td>
                                <td>
                                    <!-- Edit Action -->
                                    <form action="edit_lecturer.php" method="GET" class="d-inline">
                                        <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </form>

                                    <!-- Delete Action -->
                                    <form action="dashboard.php" method="POST" class="d-inline">
                                        <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                        <button type="submit" name="delete_lecturer" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this lecturer?');">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No lecturers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination for Lecturers -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalLecturersPages; $i++): ?>
                        <li class="page-item <?php echo $i == $pageLecturers ? 'active' : ''; ?>">
                            <a class="page-link" href="?page_lecturers=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

        <!-- Manage Courses Section -->
        <div id="courses" class="section">
            <h4 class="mt-4">Manage Courses</h4>

            <!-- Add Course Form -->
            <form action="dashboard.php" method="POST" class="mb-4">
                <div class="row">
                    <div class="col-md-2">
                        <input type="text" name="course_code" class="form-control" placeholder="Course Code (e.g., NUR101)" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="course_name" class="form-control" placeholder="Course Name" required>
                    </div>
                    <div class="col-md-2">
                        <select name="program" class="form-control" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program); ?>">
                                    <?php echo htmlspecialchars($program); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <option value="First">First</option>
                            <option value="Second">Second</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="academic_year" class="form-control" placeholder="Academic Year (e.g., 2024/2025)" required>
                    </div>
                    <div class="col-md-1">
                        <input type="number" name="credit_unit" class="form-control" placeholder="Credit Unit" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="add_course" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </form>

            <!-- Display Courses -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Credit Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses && $courses->num_rows > 0): ?>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['program']); ?></td>
                                <td><?php echo htmlspecialchars($course['semester']); ?></td>
                                <td><?php echo htmlspecialchars($course['academic_year']); ?></td>
                                <td><?php echo htmlspecialchars($course['credit_unit']); ?></td>
                                <td>
                                    <!-- Edit Action -->
                                    <form action="edit_course.php" method="GET" class="d-inline">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </form>

                                    <!-- Delete Action -->
                                    <form action="dashboard.php" method="POST" class="d-inline">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="delete_course" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this course?');">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No courses found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination for Courses -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalCoursesPages; $i++): ?>
                        <li class="page-item <?php echo $i == $pageCourses ? 'active' : ''; ?>">
                            <a class="page-link" href="?page_courses=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

        <!-- Assign Courses to Lecturers Section -->
        <div id="assign_courses" class="section">
            <h4 class="mt-4">Assign Courses to Lecturers</h4>

            <!-- Assign Courses Form -->
            <form action="dashboard.php" method="POST" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <select name="lecturer_id" class="form-control" required>
                            <option value="">Select Lecturer</option>
                            <?php
                            $lecturersResult = $conn->query("SELECT id, full_name FROM users WHERE role = 'lecturer'");
                            while ($lecturer = $lecturersResult->fetch_assoc()): ?>
                                <option value="<?php echo $lecturer['id']; ?>">
                                    <?php echo htmlspecialchars($lecturer['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select name="course_ids[]" class="form-control" multiple required>
                            <option value="">Select Courses</option>
                            <?php
                            $coursesResult = $conn->query("SELECT id, course_name FROM courses");
                            while ($course = $coursesResult->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple courses.</small>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="assign_courses" class="btn btn-primary">Assign</button>
                    </div>
                </div>
            </form>

            <!-- Display Assigned Courses -->
            <h5 class="mt-4">Assigned Courses</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Assigned Courses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $assignments = $conn->query("
                        SELECT u.id AS lecturer_id, u.full_name AS lecturer_name, 
                               GROUP_CONCAT(c.course_name SEPARATOR ', ') AS courses
                        FROM lecturer_courses lc
                        JOIN users u ON lc.lecturer_id = u.id
                        JOIN courses c ON lc.course_id = c.id
                        GROUP BY lc.lecturer_id
                    ");
                    if ($assignments->num_rows > 0):
                        while ($assignment = $assignments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['lecturer_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['courses']); ?></td>
                                <td>
                                    <!-- Edit Action -->
                                    <form action="edit_assignment.php" method="GET" class="d-inline">
                                        <input type="hidden" name="lecturer_id" value="<?php echo $assignment['lecturer_id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </form>

                                    <!-- Delete Action -->
                                    <form action="dashboard.php" method="POST" class="d-inline">
                                        <input type="hidden" name="lecturer_id" value="<?php echo $assignment['lecturer_id']; ?>">
                                        <button type="submit" name="delete_assignment" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this assignment?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No assignments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });

            // Show the selected section
            document.getElementById(sectionId).classList.add('active');
        }
    </script>
</body>
</html>