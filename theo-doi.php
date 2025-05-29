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
    $error_message = 'Vui lòng đăng nhập để xem danh sách truyện theo dõi!';
} else {
    // Phân trang
    $comics_per_page = 24;
    $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($current_page - 1) * $comics_per_page;

    // Lấy danh sách truyện theo dõi
    try {
        $user_id = (int)$_SESSION['user_id'];
        $api = new OTruyenAPI();

        // Đếm tổng số truyện
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM user_follows uf 
            JOIN comics c ON uf.comic_id = c.id 
            WHERE uf.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_comics = $result->fetch_assoc()['total'];
        $total_pages = ceil($total_comics / $comics_per_page);
        $stmt->close();

        // Lấy danh sách truyện và chương mới nhất
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.slug, c.thumb_url, c.status, c.updated_at,
                   (SELECT chapter_name 
                    FROM chapters ch 
                    WHERE ch.comic_id = c.id 
                    ORDER BY CAST(chapter_name AS DECIMAL(10,2)) DESC 
                    LIMIT 1) as latest_chapter
            FROM user_follows uf
            JOIN comics c ON uf.comic_id = c.id
            WHERE uf.user_id = ?
            ORDER BY c.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $user_id, $comics_per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $followed_comics = [];
        while ($row = $result->fetch_assoc()) {
            $followed_comics[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = 'Lỗi hệ thống. Vui lòng thử lại sau!';
    }
}
?>

<body class="dark-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <h1 class="title_cate mr-auto"><i class="bi bi-bookmark-plus-fill"></i> Theo Dõi</h1>
            </div>
            <div class="row list_item_home">
                <?php if (isset($error_message)): ?>
                    <div class="warning-list"><?php echo htmlspecialchars($error_message); ?></div>
                <?php elseif (empty($followed_comics)): ?>
                    <div class="warning-list">Bạn chưa theo dõi truyện nào!</div>
                <?php else: ?>
                    <?php foreach ($followed_comics as $comic): ?>
                        <?php
                        $chapter_name = $comic['latest_chapter'] ?? 'N/A';
                        $chapter_count = is_numeric($chapter_name) ? (int)$chapter_name : 0;
                        $tag = $api->getTag($chapter_count);
                        // Chỉ hiển thị tag FULL
                        if ($tag !== 'FULL') {
                            $tag = null;
                        }
                        $time_ago = timeAgo($comic['updated_at']);
                        ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <span class="remove-subscribe" title="Hủy Theo Dõi" data-id="<?php echo $comic['id']; ?>">
                                    <i class="bi bi-x-circle-fill"></i>
                                </span>
                                <a href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                                   class="thumbblock thumb140x195">
                                    <img data-src="<?php echo htmlspecialchars($comic['thumb_url'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($comic['name']); ?>" 
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
                                <div class="bottom-notice">
                                    <span class="rate-star"><i class="bi bi-star-fill"></i> 3.2</span>
                                </div>
                            </div>
                            <a href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                               class="fs16 txt_oneline book_name" 
                               title="<?php echo htmlspecialchars($comic['name']); ?>">
                                <?php echo htmlspecialchars($comic['name']); ?>
                            </a>
                            <div>
                                <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($comic['slug']); ?>&chapter=<?php echo htmlspecialchars($chapter_name); ?>" 
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
                        <a href="<?php echo FOLLOW_URL; ?>?page=<?php echo $current_page + 1; ?>" 
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

    <script src="assets/js/main.js" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
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
            // Xử lý nút hủy theo dõi
            $('.remove-subscribe').on('click', function() {
                var comic_id = $(this).data('id');
                var item = $(this).closest('.item_home');
                $.ajax({
                    url: '<?php echo BASE_URL; ?>includes/handlers/follow_handler.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_follow',
                        comic_id: comic_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && !response.is_following) {
                            item.remove();
                            showToast('Đã hủy theo dõi truyện!', true);
                            if ($('.list_item_home .item_home').length === 0) {
                                $('.list_item_home').prepend('<div class="warning-list">Bạn chưa theo dõi truyện nào!</div>');
                            }
                        } else {
                            showToast(response.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Lỗi kết nối máy chủ. Vui lòng thử lại.', false);
                    }
                });
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
            margin-bottom: 5px;
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
        .remove-subscribe {
            position: absolute;
            top: 5px;
            right: 5px;
            cursor: pointer;
            z-index: 10;
        }
        .remove-subscribe i {
            font-size: 20px;
            color: #dc3545;
            transition: color 0.2s;
        }
        .remove-subscribe:hover i {
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
</body>
</html>