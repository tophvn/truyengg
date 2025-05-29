<?php
require_once 'includes/layouts/header.php';
require_once 'config/routes.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . HOME_URL);
    exit;
}

require_once 'config/database.php';

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, xu, points, level, progress, last_name, first_name, gender, type_rank, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Define navigation items
$nav_items = [
    'thong-tin-ca-nhan' => ['title' => 'Thông Tin Cá Nhân', 'icon' => 'bi-person-circle'],
    'thay-doi-mat-khau' => ['title' => 'Đổi Mật Khẩu', 'icon' => 'bi-shield-lock-fill'],
    'lich-su' => ['title' => 'Lịch Sử Đọc Truyện', 'icon' => 'bi-clock-history', 'url' => HISTORY_URL],
    'theo-doi' => ['title' => 'Truyện Theo Dõi', 'icon' => 'bi-bookmark-heart-fill', 'url' => FOLLOW_URL],
    'nap-xu' => ['title' => 'Nạp Xu', 'icon' => 'bi-currency-dollar', 'url' => RECHARGE_URL],
    'lich-su-nap-xu' => ['title' => 'Lịch Sử Nạp Xu', 'icon' => 'bi-card-checklist', 'url' => RECHARGE_HISTORY_URL],
    'lich-su-thanh-toan' => ['title' => 'Lịch Sử Thanh Toán', 'icon' => 'bi-credit-card', 'url' => PAYMENT_HISTORY_URL],
    'binh-luan' => ['title' => 'Bình Luận', 'icon' => 'bi-chat-left-text'],
    'dang-ky-nhom-dich' => ['title' => 'Đăng Truyện', 'icon' => 'bi-pencil-square'],
    'dong-bo-tai-khoan' => ['title' => 'Đồng Bộ Tài Khoản', 'icon' => 'bi-arrow-repeat'],
    'logout' => ['title' => 'Thoát', 'icon' => 'bi-sign-turn-slight-right', 'url' => LOGOUT_URL . '?redirect=' . urlencode(HOME_URL)],
];

// Determine current section
$section = isset($_GET['section']) ? basename($_GET['section']) : 'thong-tin-ca-nhan';
$current_page = $section;

// Validate section
$valid_sections = array_keys($nav_items);
if (!in_array($section, $valid_sections)) {
    $section = 'thong-tin-ca-nhan';
    $current_page = 'thong-tin-ca-nhan';
}

// Map section to include file
$section_files = [
    'thong-tin-ca-nhan' => 'includes/profile/thong-tin-ca-nhan.php',
    'thay-doi-mat-khau' => 'includes/profile/thay-doi-mat-khau.php',
    'binh-luan' => 'includes/profile/binh-luan.php',
    'dang-ky-nhom-dich' => 'includes/profile/dang-ky-nhom-dich.php',
    'dong-bo-tai-khoan' => 'includes/profile/dong-bo-tai-khoan.php',
];

// Set page title based on section
$page_titles = [
    'thong-tin-ca-nhan' => 'Thông Tin Cá Nhân',
    'thay-doi-mat-khau' => 'Thay Đổi Mật Khẩu',
    'lich-su' => 'Lịch Sử Đọc Truyện',
    'theo-doi' => 'Truyện Theo Dõi',
    'nap-xu' => 'Nạp Xu',
    'lich-su-nap-xu' => 'Lịch Sử Nạp Xu',
    'lich-su-thanh-toan' => 'Lịch Sử Thanh Toán',
    'binh-luan' => 'Bình Luận',
    'dang-ky-nhom-dich' => 'Đăng Truyện',
    'dong-bo-tai-khoan' => 'Đồng Bộ Tài Khoản',
    'logout' => 'Thoát',
];
$title = $page_titles[$section] ?? 'Thông Tin Cá Nhân';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <title><?php echo htmlspecialchars($title); ?></title>
</head>
<body class="dark-style">
    <div class="container container-background">
        <div class="row">
            <div class="col-md-3">
                <ul class="list_acc">
                    <?php foreach ($nav_items as $key => $item): ?>
                        <li class="<?php echo ($current_page === $key) ? 'active' : ''; ?>">
                            <a href="<?php echo isset($item['url']) ? $item['url'] : 'javascript:void(0)'; ?>" 
                               <?php echo !isset($item['url']) ? 'data-section="' . $key . '" class="section-link"' : ''; ?>>
                                <i class="bi <?php echo $item['icon']; ?>"></i> <?php echo $item['title']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-9" id="content-area">
                <?php
                // Include the content for the initial section (PHP fallback)
                $content_file = $section_files[$section] ?? 'includes/profile/thong-tin-ca-nhan.php';
                if (file_exists($content_file)) {
                    require_once $content_file;
                } else {
                    echo '<p>Nội dung không tồn tại.</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
    <?php require_once 'includes/layouts/footer.php'; ?>
    <script src="assest/js/main.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            // Handle section link clicks for AJAX-loaded sections
            $('.section-link').on('click', function(e) {
                e.preventDefault();
                var section = $(this).data('section');

                // Update active link
                $('.list_acc li').removeClass('active');
                $(this).parent('li').addClass('active');

                // Load content via AJAX
                $.ajax({
                    url: '<?php echo LOAD_SECTION_URL; ?>',
                    type: 'GET',
                    data: { section: section },
                    dataType: 'html',
                    timeout: 10000,
                    success: function(response) {
                        console.log('AJAX success for section:', section);
                        $('#content-area').html(response);

                        // Update page title
                        var titles = <?php echo json_encode($page_titles); ?>;
                        document.title = titles[section] || 'Thông Tin Cá Nhân';

                        // Re-initialize scripts for specific sections
                        if (section === 'thong-tin-ca-nhan') {
                            $.getScript('https://st.truyengg.net/template/frontend/js/jquery.ui.widget.js', function() {
                                $.getScript('https://st.truyengg.net/template/frontend/js/jquery.iframe-transport.js', function() {
                                    $.getScript('https://st.truyengg.net/template/frontend/js/jquery.fileupload.js', function() {
                                        console.log('File upload scripts loaded');
                                        initializeProfileScripts();
                                    });
                                });
                            });
                        } else if (section === 'thay-doi-mat-khau') {
                            console.log('Initializing password scripts');
                            initializePasswordScripts();
                        } else if (section === 'binh-luan') {
                            console.log('Initializing comment scripts');
                            if (typeof $.fn.DataTable !== 'undefined') {
                                $('#example').DataTable({
                                    "language": {
                                        "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Vietnamese.json"
                                    }
                                });
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX error:', status, error, xhr.responseText);
                        $('#content-area').html('<p>Lỗi khi tải nội dung: ' + error + '</p>');
                    },
                    complete: function() {
                        console.log('AJAX completed for section:', section);
                    }
                });
            });
        });
    </script>
</body>
</html>