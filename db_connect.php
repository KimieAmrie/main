<?php
// =====================================================
// db_connect.php — Database Connection
// =====================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // Tukar kepada username phpMyAdmin kamu
define('DB_PASS', '');       // Tukar kepada password phpMyAdmin kamu
define('DB_NAME', 'student_registration_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:20px;color:red;'>
        ❌ Database connection failed: " . $conn->connect_error . "
        <br><br>Sila pastikan:<br>
        1. XAMPP/WAMP dah running<br>
        2. MySQL service dah start<br>
        3. Database 'student_registration_db' dah di-import ke phpMyAdmin
    </div>");
}
$conn->set_charset("utf8");

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $role = $_SESSION['role'] ?? 'student';
        $map  = ['student' => 'student_dashboard.php', 'staff' => 'staff_dashboard.php', 'lecturer' => 'lect_dashboard.php', 'admin' => 'admin_dashboard.php'];
        header("Location: " . ($map[$role] ?? 'login_page.php'));
        exit();
    }
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login_page.php");
        exit();
    }
}