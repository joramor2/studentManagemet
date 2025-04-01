<?php
// filepath: /c:/laragon/www/studentManagemet/user/generate_crf.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../app/fpdf/fpdf.php');

include '../app/db.php'; // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch student details
$studentId = $_SESSION['user_id'];
$studentQuery = $conn->prepare("
    SELECT full_name, registration_number, program, level 
    FROM users 
    WHERE id = ?
");
$studentQuery->bind_param("i", $studentId);
$studentQuery->execute();
$student = $studentQuery->get_result()->fetch_assoc();
$studentQuery->close();

// Fetch registered courses
$registeredCoursesQuery = $conn->prepare("
    SELECT c.course_code, c.course_name, c.credit_unit 
    FROM course_registrations r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.student_id = ?
");
$registeredCoursesQuery->bind_param("i", $studentId);
$registeredCoursesQuery->execute();
$registeredCourses = $registeredCoursesQuery->get_result();

// Handle undefined POST keys
$academicYear = $_POST['academic_year'] ?? 'Not Provided';
$semester = $_POST['semester'] ?? 'Not Provided';

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Add School Logo
$logoPath = '../image/logo.jpeg'; // Path to the logo file
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 30); // Place logo at top-left (x=10, y=10, width=30)
} else {
    error_log("Logo file not found at: " . $logoPath);
}

// Add a border around the CRF
$pdf->Rect(5, 5, 200, 287); // x=5, y=5, width=200, height=287

// Header
$pdf->Cell(0, 10, 'Hamdala College of Nursing Science, Kano', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Course Registration Form (CRF)', 0, 1, 'C');
$pdf->Ln(10);

// Add Date and Time Stamp
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R'); // Right-aligned timestamp
$pdf->Ln(5);

// Student Details Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Student Information', 0, 1, 'L'); // Bold title for student information
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Full Name:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $student['full_name'], 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Registration Number:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $student['registration_number'], 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Program:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $student['program'], 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Level:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $student['level'], 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Academic Year:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $academicYear, 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, 'Semester:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $semester, 0, 1);
$pdf->Ln(10);

// Registered Courses Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Registered Courses', 0, 1, 'L'); // Bold title for registered courses
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Course Code', 1);
$pdf->Cell(80, 10, 'Course Title', 1);
$pdf->Cell(30, 10, 'Credit Unit', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
while ($course = $registeredCourses->fetch_assoc()) {
    $pdf->Cell(40, 10, $course['course_code'], 1);
    $pdf->Cell(80, 10, $course['course_name'], 1);
    $pdf->Cell(30, 10, $course['credit_unit'], 1);
    $pdf->Ln();
}
$registeredCoursesQuery->close();

// Signatures Section
$pdf->Ln(20);
$pdf->Cell(0, 10, '__________________________', 0, 1, 'L');
$pdf->Cell(0, 10, 'Student Signature', 0, 1, 'L');
$pdf->Ln(10);
$pdf->Cell(0, 10, '__________________________', 0, 1, 'L');
$pdf->Cell(0, 10, 'School Authority Signature', 0, 1, 'L');

// Output PDF
$pdf->Output('I', 'Course_Registration_Form.pdf');
?>