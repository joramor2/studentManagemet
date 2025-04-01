<?php
// filepath: /c:/laragon/www/studentManagemet/auth/logout.php
session_start();
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
header("Location: login.php"); // Redirect to the login page
exit();
?>
<a href="../auth/logout.php" class="btn btn-danger">Logout</a>