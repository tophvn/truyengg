<?php
require_once __DIR__ . '/config/routes.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/api/otruyen.php';
require_once __DIR__ . '/includes/layouts/header.php';

// Hàm tính khoảng thời gian
function timeAgo($dateString) {
    if (empty($dateString)) return 'Chưa cập nhật';
    try {
        $updateTime = new DateTime($dateString);
        $currentTime = new DateTime();
        $interval = $currentTime->diff($updateTime);
        if ($interval->y > 0) return $interval->y . ' năm trước';
        elseif ($interval->m > 0) return $interval->m . ' tháng trước';
        elseif ($interval->d > 0) return $interval->d . ' ngày trước';
        elseif ($interval->h > 0) return $interval->h . ' giờ trước';
        elseif ($interval->i > 0) return $interval->i . ' phút trước';
        else return 'Vừa xong';
    } catch (Exception $e) {
        return 'Chưa cập nhật';
    }
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="vi" translate="no">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="google" content="notranslate">
        <title>Lỗi - Chưa đăng nhập</title>
        <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" type="text/css" rel="stylesheet">
        <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" type="text/css" rel="stylesheet">
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes" />
        <style>
            .error-container {
                text-align: center;
                padding: 50px;
                background-color: #2c2f33;
                color: #ffc107;
                border: 2px solid #007bff;
                border-radius: 8px;
                margin: 20px auto;
                max-width: 600px;
                font-weight: 600;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            }
            .error-container p {
                font-size: 18px;
                margin-bottom: 20px;
            }
            .error-container a {
                color: #ffffff;
                background-color: #007bff;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-weight: bold;
            }
            .error-container a:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body class="dark-style">
        <div class="error-container">
            <p>Vui lòng đăng nhập để xem lịch sử đọc!</p>
            <p><a href="<?php echo LOGIN_URL; ?>">Đăng nhập</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Phân trang
$comics_per_page = 24; // Đồng bộ với trang Theo Dõi
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $comics_per_page;

// Lấy danh sách lịch sử đọc
try {
    $user_id = (int)$_SESSION['user_id'];
    $api = new OTruyenAPI();

    // Đếm tổng số truyện
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT comic_id) as total 
        FROM reading_history 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_comics = $result->fetch_assoc()['total'];
    $total_pages = ceil($total_comics / $comics_per_page);
    $stmt->close();

    // Lấy danh sách truyện và chương mới nhất
    $stmt = $conn->prepare("
        SELECT r.comic_id, r.slug, r.name, r.thumb_url, r.chapter_name, r.last_read_at
        FROM reading_history r
        INNER JOIN (
            SELECT comic_id, MAX(last_read_at) as max_read_at
            FROM reading_history
            WHERE user_id = ?
            GROUP BY comic_id
        ) latest ON r.comic_id = latest.comic_id AND r.last_read_at = latest.max_read_at
        ORDER BY r.last_read_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $comics_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $reading_history = [];
    while ($row = $result->fetch_assoc()) {
        $reading_history[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error_message = 'Lỗi hệ thống. Vui lòng thử lại sau!';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lịch Sử Đọc - TruyenGG</title>
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="assets/js/main.js" type="text/javascript"></script>
    <style>
        .warning-list {
            width: 100%;
            text-align: center;
            padding: 20px;
            background: #2c2f33;
            color: #ffc107;
            border: 2px solid #007bff;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        .image-cover {
            position: relative;
            margin-bottom: 10px;
        }
        .thumbblock {
            display: block;
            width: 140px;
            height: 195px;
            overflow: hidden;
            border-radius: 5px;
            margin: 0 auto;
        }
        .thumbblock img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s ease;
        }
        .thumbblock img.lazy-image {
            opacity: 0.6;
        }
        .thumbblock img.loaded {
            opacity: 1;
        }
        .top-notice {
            position: absolute;
            top: 5px;
            left: 5px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .time-ago {
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 3px 6px;
            font-size: 12px;
            border-radius: 3px;
        }
        .type-label {
            padding: 3px 6px;
            font-size: 12px;
            color: #fff;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .type-label.full {
            background-color: #28a745;
        }
        .type-label.hot {
            background-color: #FF5722;
        }
        .bottom-notice {
            position: absolute;
            bottom: 5px;
            right: 5px;
        }
        .rate-star {
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 3px 6px;
            font-size: 12px;
            border-radius: 3px;
        }
        .book_name {
            display: block;
            margin: 5px 0;
            height: 22px;
            line-height: 22px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #333;
            text-decoration: none;
            text-align: center;
        }
        .book_name:hover {
            color: #007bff;
        }
        .item_home > div:last-child {
            text-align: center;
        }
        .item_home > div:last-child a {
            text-decoration: none;
        }
        .item_home > div:last-child a:hover {
            color: #007bff;
        }
        .remove-history {
            position: absolute;
            top: 5px;
            right: 5px;
            cursor: pointer;
            z-index: 10;
        }
        .remove-history i {
            font-size: 20px;
            color: #dc3545;
            transition: color 0.2s;
        }
        .remove-history:hover i {
            color: #a71d2a;
        }
        .has-text-centered {
            text-align: center;
            margin-top: 20px;
            width: 100%;
        }
        .view-more-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .view-more-btn:hover {
            background: #0056b3;
        }
        .toastify {
            font-family: Arial, sans-serif;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="dark-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <a href="<?php echo HISTORY_URL; ?>" class="title_cate mr-auto"><i class="bi bi-clock-history"></i> Lịch Sử Đọc</a>
            </div>
            <div class="row list_item_home">
                <?php if (isset($error_message)): ?>
                    <div class="warning-list"><?php echo htmlspecialchars($error_message); ?></div>
                <?php elseif (empty($reading_history)): ?>
                    <div class="warning-list">Bạn chưa đọc truyện nào!</div>
                <?php else: ?>
                    <?php foreach ($reading_history as $history): ?>
                        <?php
                        $chapter_name = $history['chapter_name'] ?? 'N/A';
                        $chapter_count = is_numeric($chapter_name) ? (int)$chapter_name : 0;
                        $tag = $api->getTag($chapter_count);
                        if ($tag !== 'FULL') {
                            $tag = null;
                        }
                        $time_ago = timeAgo($history['last_read_at']);
                        $name = $history['name'] ?? 'Truyện không xác định';
                        $slug = $history['slug'] ?? '#';
                        $thumb_url = $history['thumb_url'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
                        ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <span class="remove-history" title="Xóa Lịch Sử" data-id="<?php echo htmlspecialchars($history['comic_id']); ?>">
                                    <i class="bi bi-x-circle-fill"></i>
                                </span>
                                <a href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>" class="thumbblock thumb140x195">
                                    <img data-src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                         alt="<?php echo htmlspecialchars($name); ?>" 
                                         class="lazy-image" 
                                         style="width: 140px; height: 195px; object-fit: cover;"
                                         src="https://st.truyengg.net/template/frontend/img/loading.jpg"
                                         onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';"/>
                                </a>
                                <div class="top-notice">
                                    <span class="time-ago"><?php echo $time_ago; ?></span>
                                    <?php if ($tag): ?>
                                        <span class="type-label <?php echo strtolower($tag); ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom-notice"><span class="rate-star"><i class="bi bi-star-fill"></i> 3.2</span></div>
                            </div>
                            <a href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>" 
                               class="fs16 txt_oneline book_name" 
                               title="<?php echo htmlspecialchars($name); ?>">
                                <?php echo htmlspecialchars($name); ?>
                            </a>
                            <div>
                                <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($chapter_name); ?>" 
                                   class="fs14 cl99" 
                                   title="Chương <?php echo htmlspecialchars($chapter_name); ?>">
                                    Đọc Tiếp: <?php echo htmlspecialchars($chapter_name); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!isset($error_message) && $total_pages > 1 && $current_page < $total_pages): ?>
                    <div class="has-text-centered">
                        <a href="<?php echo HISTORY_URL; ?>?page=<?php echo $current_page + 1; ?>" 
                           class="view view-more-btn">Xem Thêm</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>

    <?php require_once __DIR__ . '/includes/layouts/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Hàm hiển thị thông báo Toastify
        function showToast(message, isSuccess = true) {
            Toastify({
                text: message,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: isSuccess ? "#28a745" : "#dc3545",
                stopOnFocus: true
            }).showToast();
        }

        $(document).ready(function() {
            // Xử lý nút xóa lịch sử
            $('.remove-history').on('click', function() {
                var comic_id = $(this).data('id');
                var item = $(this).closest('.item_home');

                if (confirm('Bạn có chắc muốn xóa lịch sử đọc của truyện này?')) {
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>includes/handlers/history_handler.php',
                        type: 'POST',
                        data: {
                            action: 'delete_history',
                            comic_id: comic_id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                item.remove();
                                showToast('Đã xóa lịch sử đọc!', true);
                                if ($('.list_item_home .item_home').length === 0) {
                                    $('.list_item_home').prepend('<div class="warning-list">Bạn chưa đọc truyện nào!</div>');
                                }
                            } else {
                                showToast(response.message, false);
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('Lỗi kết nối máy chủ. Vui lòng thử lại.', false);
                        }
                    });
                }
            });

            // Lazy load hình ảnh
            const images = document.querySelectorAll('.lazy-image');
            const fallbackImage = 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
            
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        let retries = 2;

                        const tryLoadImage = () => {
                            img.src = src;
                            img.onload = () => {
                                img.classList.remove('lazy-image');
                                img.classList.add('loaded');
                                observer.unobserve(img);
                            };
                            img.onerror = () => {
                                if (retries > 0) {
                                    retries--;
                                    setTimeout(tryLoadImage, 1000);
                                } else {
                                    img.src = fallbackImage;
                                    img.classList.remove('lazy-image');
                                    img.classList.add('loaded');
                                    observer.unobserve(img);
                                }
                            };
                        };

                        tryLoadImage();
                    }
                });
            }, {
                rootMargin: '0px 0px 150px 0px',
                threshold: 0.01
            });

            images.forEach(img => observer.observe(img));
        });
    </script>
</body>
</html>