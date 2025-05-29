<?php
session_start();
require_once '../../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Vui lòng đăng nhập.';
    exit;
}

// Fetch user data (for sections that need it)
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, xu, points, level, progress, last_name, first_name, gender, type_rank, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo 'Người dùng không tồn tại.';
    exit;
}

// Get requested section
$section = isset($_GET['section']) ? basename($_GET['section']) : 'thong-tin-ca-nhan';

// Define valid sections and their files
$section_files = [
    'thong-tin-ca-nhan' => 'thong-tin-ca-nhan.php',
    'thay-doi-mat-khau' => 'thay-doi-mat-khau.php',
    // Add other sections later
];

// Validate section
if (!isset($section_files[$section])) {
    echo 'Nội dung không tồn tại.';
    exit;
}

// Set base_url dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/truyengg';

// Include the section content
$content_file = $section_files[$section];
if (file_exists($content_file)) {
    require_once $content_file;
} else {
    echo 'Nội dung không tồn tại.';
}
?>