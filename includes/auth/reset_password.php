<?php
session_start();
require_once __DIR__ . '/../../config/routes.php';
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - TruyenGG</title>
    <link rel="icon" type="image/png" href="../../assest/img/short-logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .reset-container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .reset-container h2 {
            color: #003366;
            font-weight: 600;
        }

        .form-control {
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.25);
        }

        .btn-reset {
            width: 100%;
            padding: 12px;
            background: #007bff;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .btn-reset:hover {
            background: #0056b3;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
        }

        .alert {
            font-size: 14px;
            padding: 10px;
        }

        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo img {
            width: 60px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <img src="../../assest/img/logo.png" alt="TruyenGG Logo">
        </div>
        <h2 class="text-center mb-4">ĐẶT LẠI MẬT KHẨU</h2>
        <?php
        $token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
        if (empty($token)) {
            echo '<div class="alert alert-danger">Link không hợp lệ.</div>';
            exit;
        }

        $token = mysqli_real_escape_string($conn, $token);
        $query = "SELECT email FROM users WHERE reset_token = '$token'";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            echo '<div class="alert alert-danger">Link không hợp lệ hoặc đã được sử dụng.</div>';
            exit;
        }
        $reset = mysqli_fetch_assoc($result);
        ?>
        <form id="resetForm" method="POST">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <div class="form-group password-container">
                <input type="password" name="password" class="form-control" placeholder="Mật khẩu mới" required>
                <i class="fas fa-eye password-toggle" data-target="password"></i>
            </div>
            <div class="form-group password-container">
                <input type="password" name="confirm_password" class="form-control" placeholder="Xác nhận mật khẩu" required>
                <i class="fas fa-eye password-toggle" data-target="confirm_password"></i>
            </div>
            <button type="submit" class="btn-reset">Cập nhật mật khẩu</button>
        </form>
        <div id="reset_message" class="mt-3"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.password-toggle').click(function () {
                const input = $(this).siblings('input');
                const type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
                $(this).toggleClass('fa-eye fa-eye-slash');
            });

            $('#resetForm').submit(function (e) {
                e.preventDefault();
                const password = $('input[name="password"]').val().trim();
                const confirmPassword = $('input[name="confirm_password"]').val().trim();
                const $button = $('.btn-reset');
                const originalText = $button.text();

                if (password.length < 6) {
                    $('#reset_message').html('<div class="alert alert-danger">Mật khẩu phải có ít nhất 6 ký tự.</div>');
                    return;
                }

                if (password !== confirmPassword) {
                    $('#reset_message').html('<div class="alert alert-danger">Mật khẩu xác nhận không khớp.</div>');
                    return;
                }

                $button.addClass('btn-loading').text('').prop('disabled', true);

                $.ajax({
                    url: '<?php echo BASE_URL; ?>includes/auth/reset_password_process.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (response) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        if (response.success) {
                            $('#reset_message').html('<div class="alert alert-success">' + response.message + '</div>');
                            setTimeout(function () {
                                window.location.href = '<?php echo BASE_URL; ?>';
                            }, 2000);
                        } else {
                            $('#reset_message').html('<div class="alert alert-danger">' + response.message + '</div>');
                        }
                    },
                    error: function (xhr, status, error) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        $('#reset_message').html('<div class="alert alert-danger">Đã xảy ra lỗi: ' + error + '</div>');
                        console.log('Raw response:', xhr.responseText);
                    }
                });
            });
        });
    </script>
</body>
</html>
