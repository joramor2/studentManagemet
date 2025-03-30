<?php
session_start();
include 'db.php'; // Include database connection

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=registered_students.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output the table headers
echo "Full Name\tEmail\tProgram\tRegistration Number\tPhone\tStatus\n";

// Fetch all students from the database
$result = $conn->query("SELECT full_name, email, program, registration_number, phone, status FROM users WHERE role = 'student'");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo htmlspecialchars($row['full_name']) . "\t" .
             htmlspecialchars($row['email']) . "\t" .
             htmlspecialchars($row['program']) . "\t" .
             htmlspecialchars($row['registration_number']) . "\t" .
             htmlspecialchars($row['phone']) . "\t" .
             htmlspecialchars($row['status']) . "\n";
    }
} else {
    echo "No students found.\n";
}

$conn->close();
exit();