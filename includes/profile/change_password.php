<?php
session_start();
require_once '../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được phép.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$password_old = trim($_POST['password_old'] ?? '');
$password_new = trim($_POST['password_new'] ?? '');
$confirm_password_new = trim($_POST['confirm_password_new'] ?? '');

// Validate inputs
$errors = [];
if (empty($password_old)) {
    $errors[] = 'Vui lòng nhập mật khẩu hiện tại.';
}
if (empty($password_new)) {
    $errors[] = 'Vui lòng nhập mật khẩu mới.';
} elseif (strlen($password_new) < 6) {
    $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
} elseif (strlen($password_new) > 255) {
    $errors[] = 'Mật khẩu mới quá dài.';
}
if ($password_new !== $confirm_password_new) {
    $errors[] = 'Mật khẩu xác nhận không khớp.';
}

// Rate limiting
$max_attempts = 5;
$lockout_duration = 10; // 10 minutes
$stmt = $conn->prepare("SELECT failed_attempts, lockout_time FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$lockout_data = $result->fetch_assoc();
$stmt->close();

if ($lockout_data['lockout_time'] && strtotime($lockout_data['lockout_time']) > time()) {
    $errors[] = 'Quá nhiều lần thử. Vui lòng thử lại sau 10 phút.';
} elseif ($lockout_data['lockout_time'] && strtotime($lockout_data['lockout_time']) <= time()) {
    // Reset lockout if time has passed
    $stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $lockout_data['failed_attempts'] = 0;
}

// Verify current password
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password_old, $user['password'])) {
        $attempts = $lockout_data['failed_attempts'] + 1;
        $lockout_sql = ($attempts >= $max_attempts) ? ", lockout_time = NOW() + INTERVAL 10 MINUTE" : "";
        $stmt = $conn->prepare("UPDATE users SET failed_attempts = ? $lockout_sql WHERE id = ?");
        $stmt->bind_param("ii", $attempts, $user_id);
        $stmt->execute();
        $stmt->close();
        $errors[] = 'Mật khẩu hiện tại không đúng.';
    } else {
        // Reset attempts on correct password
        $stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// If errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Update password
$hashed_password = password_hash($password_new, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_time = NULL WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Thay đổi mật khẩu thành công.']);
} else {
    error_log('SQL Error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Thay đổi mật khẩu thất bại: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>