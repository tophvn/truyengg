<?php
session_start();
require_once '../../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Phương thức không được phép.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_name = trim($_POST['last_name'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$gender = in_array($_POST['gender'] ?? '', ['male', 'female']) ? $_POST['gender'] : null;
$type_rank = isset($_POST['type_rank']) && in_array($_POST['type_rank'], ['0', '1', '2', '3', '4', '5']) ? (int)$_POST['type_rank'] : 0;
$avatar = trim($_POST['avatar'] ?? '');

// Validate inputs
$errors = [];
if (strlen($last_name) > 255) {
    $errors[] = 'Họ không được vượt quá 255 ký tự.';
}
if (strlen($first_name) > 255) {
    $errors[] = 'Tên không được vượt quá 255 ký tự.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Update user data
$stmt = $conn->prepare("UPDATE users SET last_name = ?, first_name = ?, gender = ?, type_rank = ?, avatar = ? WHERE id = ?");
$stmt->bind_param("sssisi", $last_name, $first_name, $gender, $type_rank, $avatar, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật thông tin thành công.']);
} else {
    error_log('SQL Error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Cập nhật thất bại: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>