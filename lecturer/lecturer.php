<?php
// session_start();
include '../app/db.php'; // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: ../auth/login.php");
    exit();
}

$lecturerId = $_SESSION['user_id'];

// Fetch courses assigned to the lecturer
$coursesQuery = $conn->prepare("
    SELECT c.id, c.course_code, c.course_name, c.program, c.academic_year, c.semester 
    FROM lecturer_courses lc 
    JOIN courses c ON lc.course_id = c.id 
    WHERE lc.lecturer_id = ?
");
$coursesQuery->bind_param("i", $lecturerId);
$coursesQuery->execute();
$courses = $coursesQuery->get_result();
$coursesQuery->close();

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $academicYear = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $program = $_POST['program'];
    $grades = $_POST['grades'];

    // Fetch the course ID dynamically
    $courseQuery = $conn->prepare("
        SELECT id FROM courses 
        WHERE program = ? AND academic_year = ? AND semester = ? AND id IN (
            SELECT course_id FROM lecturer_courses WHERE lecturer_id = ?
        )
    ");
    $courseQuery->bind_param("sssi", $program, $academicYear, $semester, $lecturerId);
    $courseQuery->execute();
    $courseResult = $courseQuery->get_result();
    $courseRow = $courseResult->fetch_assoc();
    $courseQuery->close();

    if (!$courseRow) {
        echo "<div class='alert alert-danger text-center'>No course found for the selected Program, Academic Year, and Semester.</div>";
        exit();
    }

    $courseId = $courseRow['id'];

    $errorMessages = []; // Array to store error messages

    foreach ($grades as $studentId => $grade) {
        $ca1 = $grade['ca1'];
        $ca2 = $grade['ca2'];
        $exam = $grade['exam'];
        $total = $ca1 + $ca2 + $exam;

        // Validate total
        if ($total > 100) {
            $errorMessages[] = "Total score for student with ID $studentId exceeds 100 and was not recorded.";
            continue; // Skip saving this student's grades
        }

        // Save the grade if total is valid
        $gradeQuery = $conn->prepare("
            INSERT INTO grades (student_id, course_id, academic_year, semester, ca1, ca2, exam) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ca1 = VALUES(ca1), ca2 = VALUES(ca2), exam = VALUES(exam)
        ");
        $gradeQuery->bind_param("iissiii", $studentId, $courseId, $academicYear, $semester, $ca1, $ca2, $exam);
        $gradeQuery->execute();
        $gradeQuery->close();
    }

    // Display error messages if any
    if (!empty($errorMessages)) {
        foreach ($errorMessages as $errorMessage) {
            echo "<div class='alert alert-danger text-center'>$errorMessage</div>";
        }
    } else {
        $_SESSION['message'] = "Grades saved successfully!";
        header("Location: lecturer.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Lecturer Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success text-center">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Assigned Courses -->
        <h5>Assigned Courses:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Program</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($course = $courses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['program']); ?></td>
                        <td><?php echo htmlspecialchars($course['academic_year']); ?></td>
                        <td><?php echo htmlspecialchars($course['semester']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Grade Entry Section -->
        <h5 class="mt-5">Enter Grades:</h5>
        <form action="lecturer.php" method="POST">
            <div class="row">
                <div class="col-md-4">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="e.g., 2024/2025" required>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label">Semester</label>
                    <select class="form-control" id="semester" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="First">First</option>
                        <option value="Second">Second</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="program" class="form-label">Program</label>
                    <select class="form-control" id="program" name="program" required>
                        <option value="">Select Program</option>
                        <option value="ND-Nursing">ND-Nursing</option>
                        <option value="ND-Midwifery">ND-Midwifery</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="search_grades" class="btn btn-primary mt-3">Search</button>
        </form>

        <!-- Grading Table -->
        <?php if (isset($_POST['search_grades'])): ?>
            <?php
            $academicYear = $_POST['academic_year'];
            $semester = $_POST['semester'];
            $program = $_POST['program'];

            echo "Program: $program<br>";
            echo "Academic Year: $academicYear<br>";
            echo "Semester: $semester<br>";
            echo "Lecturer ID: $lecturerId<br>";

            $courseQuery = $conn->prepare("
                SELECT id FROM courses 
                WHERE program = ? AND academic_year = ? AND semester = ? AND id IN (
                    SELECT course_id FROM lecturer_courses WHERE lecturer_id = ?
                )
            ");
            $courseQuery->bind_param("sssi", $program, $academicYear, $semester, $lecturerId);
            $courseQuery->execute();
            $courseResult = $courseQuery->get_result();
            $courseRow = $courseResult->fetch_assoc();
            $courseQuery->close();

            if (!$courseRow) {
                echo "<div class='alert alert-danger text-center'>No course found for the selected Program, Academic Year, and Semester.</div>";
                exit();
            }

            $courseId = $courseRow['id'];

            $studentsQuery = $conn->prepare("
                SELECT r.student_id, u.registration_number, u.full_name, c.course_code, c.course_name, 
                       MAX(g.ca1) AS ca1, MAX(g.ca2) AS ca2, MAX(g.exam) AS exam
                FROM course_registrations r
                JOIN users u ON r.student_id = u.id
                JOIN courses c ON r.course_id = c.id
                LEFT JOIN grades g ON r.student_id = g.student_id AND r.course_id = c.id
                WHERE r.academic_year = ? AND r.semester = ? AND c.program = ? AND c.id IN (
                    SELECT course_id FROM lecturer_courses WHERE lecturer_id = ?
                )
                GROUP BY r.student_id, c.id
            ");
            $studentsQuery->bind_param("sssi", $academicYear, $semester, $program, $lecturerId);
            $studentsQuery->execute();
            $students = $studentsQuery->get_result();
            ?>
            <form action="lecturer.php" method="POST" class="mt-4">
                <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academicYear); ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                <input type="hidden" name="program" value="<?php echo htmlspecialchars($program); ?>">
                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($courseId); ?>">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Registration Number</th>
                            <th>Student Name</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>CA1 (%)</th>
                            <th>CA2 (%)</th>
                            <th>Exam (%)</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($student['course_name']); ?></td>
                                <td>
                                    <input type="number" name="grades[<?php echo $student['student_id']; ?>][ca1]" class="form-control ca1" value="<?php echo htmlspecialchars($student['ca1']); ?>" required>
                                </td>
                                <td>
                                    <input type="number" name="grades[<?php echo $student['student_id']; ?>][ca2]" class="form-control ca2" value="<?php echo htmlspecialchars($student['ca2']); ?>" required>
                                </td>
                                <td>
                                    <input type="number" name="grades[<?php echo $student['student_id']; ?>][exam]" class="form-control exam" value="<?php echo htmlspecialchars($student['exam']); ?>" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control total" value="<?php echo htmlspecialchars($student['ca1'] + $student['ca2'] + $student['exam']); ?>" readonly>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <button type="submit" name="save_grades" class="btn btn-success">Save Grades</button>
            </form>
        <?php endif; ?>
    </div>
    <script>
        document.querySelectorAll('.ca1, .ca2, .exam').forEach(input => {
            input.addEventListener('input', function () {
                const row = this.closest('tr');
                const ca1 = parseFloat(row.querySelector('.ca1').value) || 0;
                const ca2 = parseFloat(row.querySelector('.ca2').value) || 0;
                const exam = parseFloat(row.querySelector('.exam').value) || 0;
                const total = ca1 + ca2 + exam;

                const totalField = row.querySelector('.total');
                totalField.value = total;

                if (total > 100) {
                    totalField.classList.add('text-danger');
                    alert('Total cannot exceed 100!');
                } else {
                    totalField.classList.remove('text-danger');
                }
            });
        });
    </script>
</body>
</html>