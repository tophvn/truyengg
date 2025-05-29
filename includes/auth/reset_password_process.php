<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
ob_clean();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Yêu cầu không hợp lệ.');
    }
    $token = filter_input(INPUT_POST, 'token', FILTER_UNSAFE_RAW);
    $token = $token !== false ? htmlspecialchars($token, ENT_QUOTES, 'UTF-8') : '';
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
    $password = $password !== false ? htmlspecialchars($password, ENT_QUOTES, 'UTF-8') : '';
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW);
    $confirm_password = $confirm_password !== false ? htmlspecialchars($confirm_password, ENT_QUOTES, 'UTF-8') : '';
    if (empty($token) || empty($password) || empty($confirm_password)) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }
    if ($password !== $confirm_password) {
        throw new Exception('Mật khẩu xác nhận không khớp.');
    }
    if (strlen($password) < 6) {
        throw new Exception('Mật khẩu phải có ít nhất 6 ký tự.');
    }

    $token = mysqli_real_escape_string($conn, $token);
    $query = "SELECT email FROM users WHERE reset_token = '$token'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception('Lỗi cơ sở dữ liệu: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Link không hợp lệ hoặc đã được sử dụng.');
    }

    $reset = mysqli_fetch_assoc($result);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $email = mysqli_real_escape_string($conn, $reset['email']);
    $hashed_password = mysqli_real_escape_string($conn, $hashed_password);
    $query = "UPDATE users SET password = '$hashed_password', reset_token = NULL WHERE email = '$email'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception('Không thể cập nhật mật khẩu: ' . mysqli_error($conn));
    }

    echo json_encode(['success' => true, 'message' => 'Mật khẩu đã được cập nhật thành công.']);
} catch (Exception $e) {
    error_log('Error in reset_password_process.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
