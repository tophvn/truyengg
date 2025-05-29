<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/send_email.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Yêu cầu không hợp lệ.');
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email không hợp lệ.');
    }

    $email = mysqli_real_escape_string($conn, $email);
    $query = "SELECT id FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception('Lỗi cơ sở dữ liệu: ' . mysqli_error($conn));
    }
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Email không tồn tại.');
    }

    $token = bin2hex(random_bytes(50));
    $token = mysqli_real_escape_string($conn, $token);
    $query = "UPDATE users SET reset_token = '$token' WHERE email = '$email'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception('Lỗi cập nhật token: ' . mysqli_error($conn));
    }

    send_password_reset_email($email, $token);

    echo json_encode(['success' => true, 'message' => 'Link đặt lại mật khẩu đã được gửi đến email của bạn.']);
} catch (Exception $e) {
    error_log('Error in forgot_password.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>