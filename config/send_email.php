<?php
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';
require __DIR__ . '/../PHPMailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$common_css = "
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #2C3E50;
            text-align: center;
        }
        p {
            font-size: 16px;
            text-align: center;
        }
        .button-container {
            text-align: center;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            text-decoration: none;
            border-radius: 5px;
            background-color: #007bff;
            color: #fff;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #888;
        }
    </style>
";

function send_password_reset_email($email, $token) {
    global $common_css;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->CharSet = "utf-8";
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tophvn17@gmail.com'; //Thay bằng Email của bạn
        $mail->Password = 'aaaa bbbb cccc dddd'; //Thay bằng mật khẩu ứng dụng (không phải mật khẩu gmail)
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('tophvn17@gmail.com', 'TruyenGG');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Đặt Lại Mật Khẩu';
        $resetLink = "http://localhost/truyengg/includes/auth/reset_password.php?token=" . $token;
        $mail->Body = "
            <html>
            <head>
                $common_css
            </head>
            <body>
                <div class='container'>
                    <h2>Đặt Lại Mật Khẩu</h2>
                    <p>Vui lòng nhấp vào nút dưới đây để đặt lại mật khẩu của bạn:</p>
                    <div class='button-container'>
                        <a href='$resetLink' class='button'>Đặt Lại Mật Khẩu</a>
                    </div>
                    <p>Nếu bạn không yêu cầu thay đổi mật khẩu, vui lòng bỏ qua email này.</p>
                </div>
                <div class='footer'>
                    <p>TruyenGG</p>
                </div>
            </body>
            </html>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log('Lỗi khi gửi email: ' . $mail->ErrorInfo);
        throw new Exception('Không thể gửi email đặt lại mật khẩu.');
    }
}
?>
