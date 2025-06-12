<?php
session_start();
require_once __DIR__ . '/../../config/routes.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../google-api/vendor/autoload.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = rtrim($protocol . '://' . $_SERVER['HTTP_HOST'], '/');
$csrf_token = bin2hex(random_bytes(32));  
$_SESSION['csrf_token'] = $csrf_token;

// Lấy danh sách thể loại từ API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://otruyenapi.com/v1/api/the-loai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
$response = curl_exec($ch);
if ($response === false) {
    error_log("Lỗi cURL khi lấy thể loại: " . curl_error($ch));
}
curl_close($ch);

$base_id = 37;
$categories = [];
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['data']['items'])) {
        foreach ($data['data']['items'] as $index => $item) {
            $categories[] = [
                'slug' => $item['slug'],
                'name' => $item['name'],
                'id' => $base_id + $index
            ];
        }
    } else {
        error_log("Lỗi API thể loại: " . json_encode($data));
    }
}

// Lấy cài đặt Google API từ database
$settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')";
$settings_result = $conn->query($settings_query);
$settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$required_settings = ['google_client_id', 'google_client_secret', 'google_redirect_uri'];
foreach ($required_settings as $key) {
    if (!isset($settings[$key]) || empty(trim($settings[$key]))) {
        error_log("Thiếu hoặc trống cài đặt Google API trong header.php: $key");
        $settings[$key] = '';
    }
}

// Khởi tạo Google Client
$google_client = new Google_Client();
$google_client->setClientId($settings['google_client_id']);
$google_client->setClientSecret($settings['google_client_secret']);
$google_client->setRedirectUri($settings['google_redirect_uri']);
$google_client->addScope('email');
$google_client->addScope('profile');
$google_login_url = $google_client->createAuthUrl();
?>

<!DOCTYPE html>
<html lang="vi">
<head itemscope itemtype="http://schema.org/WebPage">
    <meta charset="UTF-8">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="google" content="notranslate">
    <title>Đọc truyện tranh, truyện chữ online đầy đủ và miễn phí</title>
    <meta name="keyword" content="doc truyen tranh, doc truyen chu, manga, manhwa, manhua">
    <meta name="description" content="Web đọc truyện tranh, truyện chữ, manga, manhua, manhwa được cập nhật liên tục, nhanh nhất và hoàn toàn miễn phí.">
    <meta property="og:description" content="Web đọc truyện tranh, truyện chữ, manga, manhua, manhwa được cập nhật liên tục, nhanh nhất và hoàn toàn miễn phí.">
    <meta itemprop="description" content="Web đọc truyện tranh, truyện chữ, manga, manhua, manhwa được cập nhật liên tục, nhanh nhất và hoàn toàn miễn phí.">
    <meta property="og:image" content="assest/img/logo-share.png">
    <link rel="apple-touch-icon" href="assest/img/apple-touch-icon.png" />
    <link rel="shortcut icon" href="assest/img/apple-touch-icon.png" type="image/x-icon" />
    <link rel="icon" href="assest/img/favicon.ico" type="image/x-icon">
    <meta name="copyright" content="Copyright © 2025" />
    <meta name="Author" content="TruyenGG" />
    <link rel="preload" href="https://st.truyengg.net/template/frontend/fonts/quicksand-v8-vietnamese_latin-ext_latin-regular.woff2" as="font" type="font/woff" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="assest/css/custom.css" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        .shadow_bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1000;
        }
        .shadow_bg.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .box_content_reg {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            position: relative;
            z-index: 1001;
        }
        .close {
            position: absolute;
            top: 10px;
            left: 10px;
            cursor: pointer;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            min-width: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease-in-out;
        }
        .toast-success {
            background-color: #28a745;
            color: #fff;
        }
        .toast-error {
            background-color: #dc3545;
            color: #fff;
        }
        .toast-header {
            background-color: rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            font-weight: bold;
        }
        .toast-body {
            font-size: 1.1rem;
            padding: 15px;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px;
            width: 100%;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .google-btn img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        .google-btn:hover {
            background-color: #f5f5f5;
        }
        .notification {
            position: absolute;
            top: 30px;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
        }
        .notification.active {
            display: block;
        }
        .text_notification {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .notification .list .item {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .notification .list .item:last-child {
            border-bottom: none;
        }
        .setting {
            position: absolute;
            top: 30px;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 200px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
        }
        .setting.active {
            display: block;
        }
        .setting li {
            list-style: none;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .setting li:last-child {
            border-bottom: none;
        }
        .setting li a {
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
        }
        .setting li a i {
            margin-right: 10px;
        }
        .setting li a:hover {
            background: #f5f5f5;
        }
        .tab_login.active, .tab_reg.active {
            font-weight: bold;
            color: #007bff;
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
        .box_search_main {
            position: relative;
        }
        .show_result_search {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #333;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        .show_result_search.open {
            display: block;
        }
        .list_result_search {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .list_result_search li {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .list_result_search li:last-child {
            border-bottom: none;
        }
        .list_result_search li a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #fff;
        }
        .list_result_search li a:hover {
            color: #00f;
        }
        .search_avatar {
            width: 50px;
            height: 65px;
            margin-right: 10px;
        }
        .search_avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 3px;
        }
        .search_info {
            flex: 1;
        }
        .search_info .name {
            font-weight: bold;
            font-size: 14px;
            margin: 0;
        }
        .search_info .name_other {
            font-size: 12px;
            margin: 2px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .search_info p:last-child {
            font-size: 12px;
            margin: 2px 0;
        }
        /* Theme styles */
        body.light-style {
            background: #f4f4f4;
            color: #333;
        }
        body.dark-style {
            background: #1a202c;
            color: #fff;
        }
        .light-style .box_content_reg {
            background: #fff;
            color: #333;
        }
        .dark-style .box_content_reg {
            background: #2d3748;
            color: #fff;
        }
        .light-style .notification,
        .light-style .setting {
            background: #fff;
            color: #333;
        }
        .dark-style .notification,
        .dark-style .setting {
            background: #2d3748;
            color: #fff;
        }
        .light-style .show_result_search {
            background: #fff;
            color: #333;
        }
        .dark-style .show_result_search {
            background: #333;
            color: #fff;
        }
        .light-style .list_result_search li a {
            color: #333;
        }
        .dark-style .list_result_search li a {
            color: #fff;
        }
        /* Styles for button alignment */
        .box-login {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .left-actions, .right-actions {
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        .type_book_switch, .nightmode_switch, .notification_span, .profile {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            margin-right: 10px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #fff;
        }
        .type_book_switch i, .nightmode_switch i, .icon-notification i, .icon-profile i {
            font-size: 1.2rem;
        }
        .btn_show_reg, .btn_show_log {
            font-size: 0.9rem;
            padding: 5px 10px;
            text-decoration: none;
            color: #fff;
        }
        .break-line {
            margin: 0 5px;
            color: #fff;
        }
        @media (max-width: 991px) {
            .box-login {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }
            .left-actions {
                justify-content: flex-start;
            }
            .right-actions {
                justify-content: flex-end;
            }
            .type_book_switch, .nightmode_switch, .notification_span, .profile {
                margin-right: 5px;
            }
            .btn_show_reg, .btn_show_log {
                font-size: 0.85rem;
                padding: 3px 8px;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
    <script src="assest/js/readmore.min.js" type="text/javascript"></script>
    <script src="assest/js/jquery.lazy.min.js" type="text/javascript"></script>
    <script>
        // Theme toggle function
        function toggleTheme(element) {
            const body = document.body;
            const isDark = body.classList.contains('dark-style');
            if (isDark) {
                body.classList.remove('dark-style');
                body.classList.add('light-style');
                element.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
                localStorage.setItem('theme', 'light');
            } else {
                body.classList.remove('light-style');
                body.classList.add('dark-style');
                element.innerHTML = '<i class="bi bi-brightness-high-fill"></i>';
                localStorage.setItem('theme', 'dark');
            }
        }

        // Apply saved theme on page load
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const body = document.body;
            const themeSwitch = document.getElementById('setting_darkness');
            if (savedTheme === 'light') {
                body.classList.remove('dark-style');
                body.classList.add('light-style');
                themeSwitch.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
            } else {
                body.classList.remove('light-style');
                body.classList.add('dark-style');
                themeSwitch.innerHTML = '<i class="bi bi-brightness-high-fill"></i>';
            }
        });

        // Existing functions
        function setting_active_dark_mode(element) {
            toggleTheme(element);
        }

        function setting_type_book(element) {
            console.log('Toggle type book');
        }

        (function() {
            var originalAlert = window.alert;
            window.alert = function(message) {
                if (message.includes("Mật khẩu phải lớn hơn 6 ký tự") || message.includes("Mật khẩu phải có ít nhất 6 ký tự")) {
                    console.log('Đã chặn cảnh báo không mong muốn: ' + message);
                    return;
                }
                originalAlert(message);
            };
        })();

        function validateEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showToast(message, isSuccess) {
            var toastClass = isSuccess ? 'toast-success' : 'toast-error';
            var toast = $('<div class="toast ' + toastClass + '" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">' +
                '<div class="toast-header">' +
                '<strong class="mr-auto">' + (isSuccess ? 'Thành công' : 'Lỗi') + '</strong>' +
                '<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close"><span aria-hidden="true">×</span></button>' +
                '</div>' +
                '<div class="toast-body">' + message + '</div>' +
                '</div>');
            
            $('.toast-container').append(toast);
            toast.toast('show');
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        function popup(type) {
            var popup = document.getElementById('popupContainer');
            var loginForm = document.querySelector('.show_login');
            var regForm = document.querySelector('.show_reg');
            var passForm = document.querySelector('.show_pass');

            loginForm.classList.remove('active');
            regForm.classList.remove('active');
            passForm.classList.remove('active');

            if (type === 'login') {
                loginForm.classList.add('active');
            } else if (type === 'register') {
                regForm.classList.add('active');
            } else if (type === 'forgot') {
                passForm.classList.add('active');
            }

            popup.classList.add('active');
        }

        function reset() {
            var popup = document.getElementById('popupContainer');
            var loginForm = document.querySelector('.show_login');
            var regForm = document.querySelector('.show_reg');
            var passForm = document.querySelector('.show_pass');

            popup.classList.remove('active');
            loginForm.classList.remove('active');
            regForm.classList.remove('active');
            passForm.classList.remove('active');
            $('#register_message').html('');
            $('#email_register').val('');
            $('#password_register').val('');
            $('#email_login').val('');
            $('#password_login').val('');
            $('#email_forgot').val('');
        }

        $(document).ready(function() {
            console.log('Sử dụng header.php đã cập nhật (26/05/2025)');

            $('.tab_login').on('click', function(e) {
                e.preventDefault();
                popup('login');
                $('.tab_login').addClass('active');
                $('.tab_reg').removeClass('active');
            });

            $('.tab_reg').on('click', function(e) {
                e.preventDefault();
                popup('register');
                $('.tab_reg').addClass('active');
                $('.tab_login').removeClass('active');
            });

            $('.forgot_pass').on('click', function(e) {
                e.preventDefault();
                popup('forgot');
            });

            $('#loginFormUnique').on('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Form đăng nhập được gửi (loginFormUnique)');
                var email = $('#email_login').val().trim();
                var password = $('#password_login').val().trim();
                var $button = $('.button_login');
                var originalText = $button.text();

                if (email === "") {
                    showToast("Email là bắt buộc nhập.", false);
                    return;
                } else if (!validateEmail(email)) {
                    showToast("Email không đúng định dạng.", false);
                    return;
                }
                if (password === "") {
                    showToast("Mật khẩu là bắt buộc nhập.", false);
                    return;
                }

                $button.addClass('btn-loading').text('').prop('disabled', true);

                var formData = new FormData(this);
                
                $.ajax({
                    url: '<?php echo $base_url; ?>/includes/auth/login.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Phản hồi từ login.php:', response);
                        showToast(response.message, response.success);
                        if (response.success) {
                            $('#loginFormUnique')[0].reset();
                            reset();
                            setTimeout(function() {
                                window.location.href = '<?php echo $base_url; ?>';
                            }, 2000);
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Lỗi AJAX:', { rawResponse: xhr.responseText, status: status, error: error });
                        showToast('Đã xảy ra lỗi: ' + error, false);
                    }
                });
            });

            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Form đăng ký được gửi');
                var email = $('#email_register').val().trim();
                var password = $('#password_register').val().trim();
                var $button = $('#registerForm .btn_login');
                var originalText = $button.text();

                if (email === "") {
                    showToast("Email là bắt buộc nhập.", false);
                    return;
                } else if (!validateEmail(email)) {
                    showToast("Email không đúng định dạng.", false);
                    return;
                }
                if (password === "") {
                    showToast("Mật khẩu là bắt buộc nhập.", false);
                    return;
                } else if (password.length < 6) {
                    showToast("Mật khẩu phải có ít nhất 6 ký tự.", false);
                    return;
                }

                $button.addClass('btn-loading').text('').prop('disabled', true);

                var formData = new FormData(this);
                
                $.ajax({
                    url: '<?php echo $base_url; ?>/includes/auth/register.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Phản hồi từ register.php:', response);
                        showToast(response.message, response.success);
                        if (response.success) {
                            $('#registerForm')[0].reset();
                            reset();
                            setTimeout(function() {
                                window.location.href = '<?php echo $base_url; ?>';
                            }, 2000);
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Lỗi AJAX:', { rawResponse: xhr.responseText, status: status, error: error });
                        showToast('Đã xảy ra lỗi: ' + error, false);
                    }
                });
            });

            $('#forgotForm').on('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Form quên mật khẩu được gửi');
                var email = $('#email_forgot').val().trim();
                var $button = $('.button_forgot');
                var originalText = $button.text();

                if (email === "") {
                    showToast("Email là bắt buộc nhập.", false);
                    return;
                } else if (!validateEmail(email)) {
                    showToast("Email không đúng định dạng.", false);
                    return;
                }

                $button.addClass('btn-loading').text('').prop('disabled', true);

                var formData = new FormData(this);
                
                $.ajax({
                    url: '<?php echo $base_url; ?>/includes/auth/forgot_password.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Phản hồi từ forgot_password.php:', response);
                        showToast(response.message, response.success);
                        if (response.success) {
                            $('#forgotForm')[0].reset();
                            reset();
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                        console.log('Lỗi AJAX:', { rawResponse: xhr.responseText, status: status, error: error });
                        showToast('Đã xảy ra lỗi: ' + error, false);
                    }
                });
            });

            $('.icon-notification').on('click', function(e) {
                e.preventDefault();
                $('.notification').toggleClass('active');
                $('.setting').removeClass('active');
            });

            $('.icon-profile').on('click', function(e) {
                e.preventDefault();
                $('.setting').toggleClass('active');
                $('.notification').removeClass('active');
            });

            let autocomplete;
            const searchInput = $('#search_input');
            const searchResults = $('.show_result_search ul');
            const placeholderImage = 'assest/img/placeholder.jpg';

            searchInput.on('input', function() {
                const query = $(this).val().trim();
                console.log('Nhập từ khóa tìm kiếm:', query);
                if (query.length < 2) {
                    searchResults.empty();
                    $('.show_result_search').removeClass('open');
                    return;
                }

                clearTimeout(autocomplete);
                autocomplete = setTimeout(function() {
                    $.ajax({
                        url: 'https://otruyenapi.com/v1/api/tim-kiem',
                        method: 'GET',
                        data: { keyword: query },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Phản hồi từ API tìm kiếm:', response);
                            searchResults.empty();
                            if (!response.data || !response.data.items || response.data.items.length === 0) {
                                searchResults.append('<li><p style="padding: 10px;">Không tìm thấy kết quả</p></li>');
                                $('.show_result_search').addClass('open');
                                return;
                            }

                            const items = response.data.items.slice(0, 8);
                            items.forEach(item => {
                                if (!item.slug || !item.name || !item.thumb_url) {
                                    console.log('Bỏ qua mục không hợp lệ:', item);
                                    return;
                                }

                                const thumbUrl = `https://img.otruyenapi.com/uploads/comics/${item.thumb_url}`;
                                const otherName = item.origin_name && item.origin_name[0] ? item.origin_name[0] : '';
                                const latestChapter = item.chaptersLatest && item.chaptersLatest[0] ? 
                                    `Chương ${item.chaptersLatest[0].chapter_name}` : 'Chưa có chương';

                                const li = `
                                    <li>
                                        <a href="<?php echo $base_url; ?>/truyen-tranh.php?slug=${item.slug}">
                                            <div class="search_avatar">
                                                <img src="${thumbUrl}" alt="${item.name}" class="lazy" onerror="this.src='${placeholderImage}'">
                                            </div>
                                            <div class="search_info">
                                                <p class="name">${item.name}</p>
                                                <p class="name_other">${otherName}</p>
                                                <p>${latestChapter}</p>
                                            </div>
                                        </a>
                                    </li>`;
                                searchResults.append(li);
                            });

                            $('.lazy').lazy({
                                effect: 'fadeIn',
                                effectTime: 300,
                                threshold: 0,
                                onError: function(element) {
                                    console.log('Lỗi lazy load:', element.data('src'));
                                    element.attr('src', placeholderImage);
                                }
                            });

                            $('.show_result_search').addClass('open');
                        },
                        error: function(xhr, status, error) {
                            console.log('Lỗi AJAX tìm kiếm:', { rawResponse: xhr.responseText, status: status, error: error });
                            searchResults.empty();
                            searchResults.append('<li><p style="padding: 10px;">Lỗi khi tải gợi ý</p></li>');
                            $('.show_result_search').addClass('open');
                        }
                    });
                }, 800);
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.box_search_main, .show_result_search, .notification_span, .profile').length) {
                    searchResults.empty();
                    $('.show_result_search').removeClass('open');
                    $('.notification').removeClass('active');
                    $('.setting').removeClass('active');
                }
            });

            searchInput.on('keydown', function(e) {
                if (e.key === 'Enter' && $(this).val().trim().length > 0) {
                    e.preventDefault();
                    console.log('Nhấn Enter, giữ kết quả tìm kiếm hiện tại');
                }
            });

            $('.btn_search').on('click', function(e) {
                e.preventDefault();
                const keyword = $('#search_input').val().trim();
                if (keyword.length > 0) {
                    console.log('Nhấn nút tìm kiếm, giữ kết quả tìm kiếm hiện tại');
                } else {
                    console.log('Không có từ khóa tìm kiếm');
                    showToast('Vui lòng nhập từ khóa tìm kiếm.', false);
                }
            });
        });
    </script>
</head>
<body class="dark-style">
    <div class="toast-container"></div>
    <input type="hidden" id="csrf-token" value="<?php echo $csrf_token; ?>">
    <section class="head_site">
        <div class="container">
            <div class="row align-items-center">
                <div class="d-none d-lg-block col-lg-3 box-logo">
                    <a href="<?php echo $base_url; ?>" title="Đọc truyện chữ miễn phí">
                        <img width="83" height="40" alt="Logo" class="logo_main" src="assest/img/logo.png"/>
                    </a>
                </div>
                <div class="col-lg-6 box_search_mobile">
                    <div class="box_search_main d-flex">
                        <input type="text" class="mr-auto txt_search" placeholder="Tìm kiếm truyện" id="search_input" />
                        <button class="btn_search ml-auto" aria-label="Tìm"></button>
                        <div class="show_result_search">
                            <ul class="list_result_search"></ul>
                        </div>
                    </div>
                </div>
                <div class="box-login col-lg-3">
                    <div class="left-actions">
                        <span class="type_book_switch" id="setting_type_book" onclick="setting_type_book(this);">
                            <i class="bi bi-journal-richtext"></i>
                        </span>
                        <span class="nightmode_switch" id="setting_darkness" onclick="toggleTheme(this);">
                            <i class="bi bi-brightness-high-fill"></i>
                        </span>
                    </div>
                    <div class="right-actions">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <span><a class="btn_show_reg" title="Đăng Ký" href="javascript:popup('register')" rel="nofollow">Đăng Ký</a></span>
                            <span class="break-line">|</span>
                            <span><a class="btn_show_log" title="Đăng Nhập" href="javascript:popup('login')" rel="nofollow">Đăng Nhập</a></span>
                        <?php else: ?>
                            <span class="notification_span">
                                <a href="#" class="icon-notification"><i class="bi bi-bell"></i></a>
                                <div class="notification">
                                    <h6 class="text_notification">Thông báo</h6>
                                    <div class="list">
                                        <div class="item gg_chibi">
                                            <p>Bạn không có thông báo nào</p>
                                        </div>
                                    </div>
                                    <input id="id_notification" type="hidden" value="" data-totalnotification="0">
                                </div>
                            </span>
                            <span class="profile">
                                <a href="#" title="Hồ Sơ" class="icon-profile"><i class="bi bi-person-circle"></i></a>
                                <ul class="setting">
                                    <li>
                                        <a href="<?php echo $base_url; ?>/nap-xu.php" rel="nofollow">
                                            <i class="bi bi-currency-dollar"></i> 
                                            <span>Nạp xu</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="<?php echo $base_url; ?>/thiet-lap-tai-khoan.php" rel="nofollow">
                                            <i class="bi bi-person-circle"></i> 
                                            <span>Trang cá nhân</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="<?php echo $base_url; ?>/theo-doi.php">
                                            <i class="bi bi-bookmark-heart-fill"></i> 
                                            <span>Danh sách theo dõi</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="<?php echo $base_url; ?>/lich-su.php">
                                            <i class="bi bi-clock-history"></i> 
                                            <span>Lịch sử đọc truyện</span>
                                        </a>
                                    </li>
                                    <?php if (isset($_SESSION['roles']) && $_SESSION['roles'] === 'admin'): ?>
                                        <li>
                                            <a href="<?php echo $base_url; ?>/includes/admin/index.php" target="_blank" rel="nofollow">
                                                <i class="bi bi-gear-fill"></i> 
                                                <span>Quản trị</span>
                                            </a>
                                        </li>
                                    <?php elseif (isset($_SESSION['roles']) && $_SESSION['roles'] === 'translator'): ?>
                                        <li>
                                            <a href="<?php echo $base_url; ?>/includes/translator/stories-user.php" target="_blank" rel="nofollow">
                                                <i class="bi bi-upload"></i> 
                                                <span>Up truyện</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li>
                                        <a href="<?php echo $base_url; ?>/includes/auth/logout.php" rel="nofollow">
                                            <i class="bi bi-sign-turn-slight-right"></i> 
                                            <span>Đăng xuất</span>
                                        </a>
                                    </li>
                                </ul>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="menu_main_pc">
        <div class="container">
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <a class="navbar-brand d-lg-none" href="<?php echo $base_url; ?>" title="Read Light Novel English online for free">
                        <img width="83" height="40" class="logo_main" alt="Logo" src="assest/img/logo.png"/>
                    </a>
                    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#main_nav" aria-controls="main_nav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="main_nav">
                        <ul class="navbar-nav left_menu">
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>" title="Home">Trang Chủ</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Categories">Thể Loại</a>
                                <ul class="dropdown-menu dropdown-full" aria-labelledby="navbarDropdown">
                                    <?php foreach ($categories as $category): ?>
                                        <li><a class="dropdown-item" title="<?php echo htmlspecialchars($category['name']); ?>" href="<?php echo $base_url; ?>/the-loai.php?slug=<?php echo htmlspecialchars($category['slug']); ?>&id=<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="rankingDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Ranking">Xếp Hạng</a>
                                <ul class="dropdown-menu dropdown-full" aria-labelledby="rankingDropdown">
                                    <li><a class="dropdown-item" title="Top Ngày" href="<?php echo TOP_DAILY_URL; ?>">Top Ngày</a></li>
                                    <li><a class="dropdown-item" title="Top Tuần" href="<?php echo TOP_WEEKLY_URL; ?>">Top Tuần</a></li>
                                    <li><a class="dropdown-item" title="Top Tháng" href="<?php echo TOP_VIEWED_URL; ?>">Top Tháng</a></li>
                                    <li><a class="dropdown-item" title="Sắp Ra Mắt" href="<?php echo UPCOMING_URL; ?>">Sắp Ra Mắt</a></li>
                                    <li><a class="dropdown-item" title="Mới Cập Nhật" href="<?php echo NEW_COMICS_URL; ?>">Mới Cập Nhật</a></li>
                                    <li><a class="dropdown-item" title="Truyện Mới" href="<?php echo NEW_RELEASE_URL; ?>">Truyện Mới</a></li>
                                    <li><a class="dropdown-item" title="Truyện Full" href="<?php echo COMPLETED_COMICS_URL; ?>">Truyện Full</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>/tim-kiem-nang-cao.php" title="Tìm Kiếm">Tìm Kiếm</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>/lich-su.php" title="Lịch Sử">Lịch Sử</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_url; ?>/theo-doi.php" title="Theo Dõi">Theo Dõi</a></li>
                            <li class="nav-item"><a class="nav-link" href="javascript:setting_type_book(this)" rel="nofollow" title="Truyện Chữ">Truyện Chữ</a></li>
                            <li class="nav-item"><a class="nav-link" href="https://www.facebook.com/tophvn" target="_blank" rel="nofollow" title="Fanpage">Fanpage</a></li>
                            <li class="nav-item"><a class="nav-link" href="https://www.facebook.com/tophvn" target="_blank" rel="nofollow" title="Thảo Luận">Thảo Luận</a></li>
                            <li class="nav-item"><a class="nav-link" href="https://www.facebook.com/tophvn" target="_blank" rel="nofollow" title="Discord">Discord</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <div class="shadow_bg" id="popupContainer">
        <div class="box_content_reg">
            <div onclick="reset();" class="close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                </svg>
            </div>
            <div class="show_login">
                <div class="text-center mb-4">
                    <a href="javascript:void(0);" class="tab_login active">Đăng Nhập</a> | 
                    <a href="javascript:void(0);" class="tab_reg">Đăng Ký</a>
                </div>
                <form id="loginFormUnique" method="POST">
                    <div class="mb-3">
                        <input type="text" name="email" placeholder="Email" class="txt_cm" id="email_login" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" placeholder="Mật Khẩu" class="txt_cm" id="password_login" required>
                    </div>
                    <div class="mb-3">
                        <div class="cf-turnstile" data-sitekey="0x4AAAAAABeu2BlY4OucEEOI"></div>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn_login button_login">Đăng Nhập</button>
                    </div>
                    <a href="<?php echo $google_login_url; ?>" class="google-btn">
                        <img src="assest/img/icon-google.svg" alt="Google Logo">
                        <span>Đăng nhập bằng Google</span>
                    </a>
                </form>
                <div class="text-center">
                    <a href="javascript:void(0);" class="forgot_pass">Quên mật khẩu</a>
                </div>
            </div>
            <div class="show_reg">
                <div class="text-center mb-4">
                    <a href="javascript:void(0);" class="tab_login">Đăng Nhập</a> | 
                    <a href="javascript:void(0);" class="tab_reg active">Đăng Ký</a>
                </div>
                <form id="registerForm" method="POST">
                    <div class="mb-3">
                        <input type="email" name="email" placeholder="Email" class="txt_cm" id="email_register" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" placeholder="Mật Khẩu" class="txt_cm" id="password_register" required>
                    </div>
                    <div class="mb-3">
                        <div class="cf-turnstile" data-sitekey="0x4AAAAAABeu8RABJqGv7KK4"></div>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn_login">Đăng Ký</button>
                    </div>
                </form>
                <div id="register_message" class="text-center mt-3"></div>
            </div>
            <div class="show_pass">
                <div class="text-center fs20 clred mb-3"><strong>Quên mật khẩu</strong></div>
                <form id="forgotForm" method="POST">
                    <div class="mb-3">
                        <input type="email" name="email" placeholder="Email" class="txt_cm" id="email_forgot" required>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn_login button_forgot">Gửi</button>
                    </div>
                </form>
                <div class="text-center">
                    <a href="javascript:void(0);" class="tab_login fs14">Đăng Nhập</a> | 
                    <a href="javascript:void(0);" class="tab_reg fs14">Đăng Ký</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>