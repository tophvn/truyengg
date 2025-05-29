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
    }

    // Nếu không có lỗi, kiểm tra thông tin đăng nhập
    if (empty($errors)) {
        $query = "SELECT id, email, password, roles, banned_until FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            $errors[] = 'Lỗi hệ thống khi đăng nhập';
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = 'Email hoặc mật khẩu không đúng';
            } else {
                $user = $result->fetch_assoc();
                // Kiểm tra trạng thái cấm
                if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
                    $ban_expiry = date('d/m/Y H:i', strtotime($user['banned_until']));
                    $errors[] = "Tài khoản của bạn đã bị cấm đến $ban_expiry";
                } elseif (password_verify($password, $user['password'])) {
                    // Đăng nhập thành công, lưu session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['roles'] = $user['roles'];
                    echo json_encode(['success' => true, 'message' => 'Đăng nhập thành công!']);
                    $stmt->close();
                    $conn->close();
                    exit;
                } else {
                    $errors[] = 'Email hoặc mật khẩu không đúng';
                }
            }
            $stmt->close();
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