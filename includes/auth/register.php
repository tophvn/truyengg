<?php
require_once __DIR__ . '/../../config/database.php';
session_start();

// Fetch Turnstile Secret Key from settings
$settings_query = "SELECT setting_value FROM settings WHERE setting_key = 'turnstile_secret_key'";
$settings_result = $conn->query($settings_query);
$turnstile_secret_key = $settings_result && $settings_result->num_rows > 0 ? $settings_result->fetch_assoc()['setting_value'] : '0x4AAAAAABBmdz5FqnaxoDoaMqkvkbV7Q1o';

// Thiết lập header JSON
header('Content-Type: application/json');

$errors = [];
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra Cloudflare Turnstile
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
    if (empty($turnstile_response)) {
        $errors[] = 'Vui lòng xác minh CAPTCHA';
    } else {
        $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $turnstile_secret_key,
            'response' => $turnstile_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($verify_url, false, $context);
        if ($result === false) {
            $errors[] = 'Lỗi kết nối CAPTCHA';
        } else {
            $captcha_result = json_decode($result, true);
            if (!$captcha_result['success']) {
                $errors[] = 'Xác minh CAPTCHA không thành công';
            }
        }
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($email)) {
        $errors[] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }

    if (empty($password)) {
        $errors[] = 'Vui lòng nhập mật khẩu';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Mật khẩu quá dài';
    }

    // Kiểm tra email tồn tại
    $email_query = "SELECT id FROM users WHERE email = ?";
    $stmt_email = $conn->prepare($email_query);
    if (!$stmt_email) {
        $errors[] = 'Lỗi hệ thống khi kiểm tra email';
    } else {
        $stmt_email->bind_param("s", $email);
        $stmt_email->execute();
        $email_result = $stmt_email->get_result();
        if ($email_result->num_rows > 0) {
            $errors[] = 'Email đã được sử dụng';
        }
        $stmt_email->close();
    }

    // Nếu không có lỗi, chèn dữ liệu và đăng nhập
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $username = $email; // Use email as username
        $role = 'user';
        $name = null; // Optional field, set to null
        $reset_token = null; // Optional field, set to null
        $avatar = null; // Optional field, set to null

        $sql = "INSERT INTO users (username, email, password, name, reset_token, roles, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        if (!$stmt_insert) {
            $errors[] = 'Lỗi hệ thống khi đăng ký';
        } else {
            $stmt_insert->bind_param("sssssss", $username, $email, $hashed_password, $name, $reset_token, $role, $avatar);
            if ($stmt_insert->execute()) {
                // Lưu thông tin phiên để đăng nhập tự động
                $user_id = $stmt_insert->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['roles'] = $role;

                echo json_encode(['success' => true, 'message' => 'Đăng ký và đăng nhập thành công!']);
                $stmt_insert->close();
                $conn->close();
                exit;
            } else {
                $errors[] = 'Đăng ký thất bại';
            }
            $stmt_insert->close();
        }
    }

    // Trả về lỗi nếu có
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        $conn->close();
        exit;
    }
}

// Trả về lỗi nếu không phải POST
echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
$conn->close();
?>