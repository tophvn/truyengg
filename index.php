<?php
require_once 'includes/layouts/header.php';
require_once 'config/database.php';
require_once 'config/routes.php';

function timeAgo($dateString) {
    if (empty($dateString)) return 'Vừa tạo';
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
        return 'Vừa tạo';
    }
}

function formatNumber($number) {
    if ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return $number;
}
?>

<!DOCTYPE html>
<html lang="vi" translate="no">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="google" content="notranslate">
    <title>TruyenGG - Đọc Truyện Tranh, Truyện Chữ Miễn Phí</title>
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" type="text/css" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" type="text/css" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" type="text/css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes" />
    <style>
        .type-label.mới {
            background-color: #FFC107 !important;
        }
        .popup-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100%);
            background-color: #1e2125;
            color: #e0e0e0;
            padding: 12px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 350px;
            width: 90%;
            font-size: 15px;
            font-weight: 500;
            border: 1px solid #007bff;
            opacity: 0;
            transition: transform 0.5s ease, opacity 0.5s ease;
        }
        .popup-container.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .popup-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .popup-content i {
            color: #007bff;
            font-size: 18px;
        }
        .popup-content span {
            color: #ffc107;
            font-weight: 600;
        }
        .popup-container .close-btn {
            background: none;
            border: none;
            color: #ff5555;
            font-size: 20px;
            cursor: pointer;
            padding: 0 5px;
            transition: color 0.2s;
        }
        .popup-container .close-btn:hover {
            color: #ff3333;
        }
    </style>
</head>
<body class="dark-style">
    <div class="container mt15 h1-home d-flex align-items-center">
        <i class="bi bi-house-heart-fill me-2"></i>
        <h1 class="fw600 mb-0">TruyenGG - Đọc Truyện Tranh, Truyện Chữ Miễn Phí</h1>
    </div>
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <a href="<?php echo NEW_COMICS_URL; ?>" class="title_cate mr-auto"><i class="bi bi-cloud-arrow-down-fill"></i> Mới Cập Nhật</a>
            </div>
            <div class="row list_item_home">
                <?php
                require_once 'api/otruyen.php';
                $api = new OTruyenAPI();

                // Lấy truyện từ OTruyen API
                $api_comics = [];
                $home_data = $api->getHomeComics();
                if (isset($home_data['data']['items']) && !empty($home_data['data']['items'])) {
                    foreach ($home_data['data']['items'] as $comic) {
                        $api_comics[] = [
                            'source' => 'api',
                            'name' => $comic['name'] ?? '',
                            'slug' => $comic['slug'] ?? '',
                            'thumb_url' => $api->getImageUrl($comic['thumb_url'] ?? ''),
                            'chapter_name' => isset($comic['chaptersLatest'][0]['chapter_name']) ? $comic['chaptersLatest'][0]['chapter_name'] : null,
                            'updated_at' => $comic['updatedAt'] ?? '1970-01-01T00:00:00Z',
                            'timestamp' => strtotime($comic['updatedAt'] ?? '1970-01-01T00:00:00Z')
                        ];
                    }
                }

                // Lấy truyện từ database cục bộ
                $local_comics = [];
                try {
                    $stmt = $conn->prepare("
                        SELECT c.id, c.comic_id, c.name, c.slug, c.thumb_url, c.created_at, c.updated_at,
                               (SELECT chapter_name FROM chapters ch WHERE ch.comic_id = c.id 
                                ORDER BY CAST(ch.chapter_name AS UNSIGNED) DESC LIMIT 1) as latest_chapter
                        FROM comics c
                        ORDER BY COALESCE(c.updated_at, c.created_at) DESC
                        LIMIT 50
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $local_comics[] = [
                            'source' => 'local',
                            'name' => $row['name'] ?? '',
                            'slug' => $row['slug'] ?? '',
                            'thumb_url' => $row['thumb_url'] ?? '',
                            'chapter_name' => $row['latest_chapter'] ?? null,
                            'updated_at' => $row['updated_at'] ?? $row['created_at'],
                            'timestamp' => strtotime($row['updated_at'] ?? $row['created_at'])
                        ];
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    error_log("Lỗi khi lấy truyện cục bộ: " . $e->getMessage());
                }

                // Kết hợp và loại bỏ trùng lặp, ưu tiên truyện từ API
                $unique_comics = [];
                foreach ($api_comics as $comic) {
                    $unique_comics[$comic['slug']] = $comic;
                }
                foreach ($local_comics as $comic) {
                    if (!isset($unique_comics[$comic['slug']])) {
                        $unique_comics[$comic['slug']] = $comic;
                    }
                }
                $all_comics = array_values($unique_comics);

                // Sắp xếp theo timestamp
                usort($all_comics, function($a, $b) {
                    return $b['timestamp'] <=> $a['timestamp'];
                });
                $comics = array_slice($all_comics, 0, 24);

                if (!empty($comics)) {
                    foreach ($comics as $comic) {
                        $chapter_name = $comic['chapter_name'] ?? null;
                        $chapter_count = is_numeric($chapter_name) ? (int)$chapter_name : 0;
                        $tag = $chapter_name ? $api->getTag($chapter_count) : 'Mới';
                        $time_ago = timeAgo($comic['updated_at']);
                ?>
                <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                    <div class="image-cover">
                        <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug']); ?>" class="thumbblock thumb140x195">
                            <img data-src="<?php echo htmlspecialchars($comic['thumb_url']); ?>" 
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
                        <div class="bottom-notice"><span class="rate-star"><i class="bi bi-star-fill"></i> 3.5</span></div>
                    </div>
                    <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug']); ?>" 
                       class="fs16 txt_oneline book_name" 
                       title="<?php echo htmlspecialchars($comic['name']); ?>">
                        <?php echo htmlspecialchars($comic['name']); ?>
                    </a>
                    <div>
                        <?php if ($chapter_name): ?>
                        <a href="<?php echo CHAPTER_URL . '?slug=' . htmlspecialchars($comic['slug']) . '&chapter=' . htmlspecialchars($chapter_name); ?>" 
                           class="fs14 cl99" 
                           title="Chương <?php echo htmlspecialchars($chapter_name); ?>">
                            Chương <?php echo htmlspecialchars($chapter_name); ?>
                        </a>
                        <?php else: ?>
                        <span class="fs14 cl99">Chưa có chapter</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<p>Không tìm thấy truyện!</p>';
                }
                ?>
                <div class="has-text-centered">
                    <a href="<?php echo NEW_COMICS_URL . '?page=2'; ?>" class="view view-more-btn">Xem Thêm</a>
                </div>
            </div>
        </section>
        <div class="line_hor mt15 mb40"></div>
        <section>
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex mb15">
                        <a href="<?php echo TOP_VOTED_URL; ?>" class="title_cate mr-auto"><i class="bi bi-star-fill"></i> Bình Chọn</a>
                    </div>
                    <div class="row list_item_home">
                        <?php
                        try {
                            $stmt = $conn->prepare("
                                SELECT c.id, c.comic_id, c.name, c.slug, c.thumb_url, c.updated_at, c.views,
                                       (SELECT COUNT(*) FROM user_follows uf WHERE uf.comic_id = c.id) as follow_count,
                                       (SELECT chapter_name FROM chapters ch WHERE ch.comic_id = c.id ORDER BY CAST(ch.chapter_name AS UNSIGNED) DESC LIMIT 1) as latest_chapter
                                FROM comics c
                                WHERE c.is_hot = 1
                                ORDER BY c.updated_at DESC
                                LIMIT 10
                            ");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $hot_comics = $result->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();

                            if (!empty($hot_comics)) {
                                foreach ($hot_comics as $comic) {
                                    $thumb_url = $comic['thumb_url'] ?? '';
                                    $latest_chapter = $comic['latest_chapter'] ?? null;
                                    $chapter_count = is_numeric($latest_chapter) ? (int)$latest_chapter : 0;
                                    $tag = $latest_chapter ? $api->getTag($chapter_count) : 'Mới';
                                    $time_ago = timeAgo($comic['updated_at'] ?? '');
                        ?>
                        <div class="col-md-12 col-lg-6 item_home">
                            <div class="d-flex">
                                <div class="mr-3">
                                    <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? ''); ?>" class="thumbblock thumb70x85">
                                        <img data-src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                             alt="<?php echo htmlspecialchars($comic['name'] ?? ''); ?>" 
                                             class="lazy-image" 
                                             src="https://st.truyengg.net/template/frontend/img/loading.jpg"
                                             onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';"/>
                                    </a>
                                </div>
                                <div class="flex-one wc70">
                                    <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? ''); ?>" 
                                       class="fs14 txt_oneline fw600" 
                                       title="<?php echo htmlspecialchars($comic['name'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($comic['name'] ?? ''); ?>
                                    </a>
                                    <div>
                                        <?php if ($latest_chapter): ?>
                                        <a href="<?php echo CHAPTER_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? '') . '&chapter=' . htmlspecialchars($latest_chapter); ?>" 
                                           class="fs13 cl99" 
                                           title="Chương <?php echo htmlspecialchars($latest_chapter); ?>">
                                            Chương <?php echo htmlspecialchars($latest_chapter); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="fs13 cl99">Chưa có chapter</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fs13 cl99"><i class="bi bi-eye-fill"></i> <em><?php echo formatNumber($comic['views']); ?></em></div>
                                    <div class="fs13 cl99"><i class="bi bi-bookmark-plus-fill"></i> <em><?php echo formatNumber($comic['follow_count']); ?></em></div>
                                </div>
                            </div>
                        </div>
                        <?php
                                }
                            } else {
                                echo '<p>Không tìm thấy truyện hot nào!</p>';
                            }
                        } catch (Exception $e) {
                            error_log("Lỗi khi lấy danh sách truyện hot: " . $e->getMessage());
                            echo '<p>Lỗi khi tải danh sách truyện hot!</p>';
                        }
                        ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex mb15">
                        <a href="<?php echo TOP_VIEWED_URL; ?>" class="title_cate mr-auto"><i class="bi bi-eye-fill"></i> Xem Nhiều</a>
                        <a href="<?php echo TOP_VIEWED_URL . '?page=2'; ?>" class="more_cate ml-auto">Xem Thêm</a>
                    </div>
                    <div class="row list_item_home">
                        <?php
                        try {
                            $stmt = $conn->prepare("
                                SELECT c.id, c.comic_id, c.name, c.slug, c.thumb_url, c.updated_at, c.views,
                                       (SELECT COUNT(*) FROM user_follows uf WHERE uf.comic_id = c.id) as follow_count,
                                       (SELECT chapter_name FROM chapters ch WHERE ch.comic_id = c.id ORDER BY CAST(ch.chapter_name AS UNSIGNED) DESC LIMIT 1) as latest_chapter
                                FROM comics c
                                ORDER BY c.views DESC
                                LIMIT 10
                            ");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $top_comics = $result->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();

                            if (!empty($top_comics)) {
                                foreach ($top_comics as $comic) {
                                    $thumb_url = $comic['thumb_url'] ?? '';
                                    $latest_chapter = $comic['latest_chapter'] ?? null;
                                    $chapter_count = is_numeric($latest_chapter) ? (int)$latest_chapter : 0;
                                    $tag = $latest_chapter ? $api->getTag($chapter_count) : 'Mới';
                                    $time_ago = timeAgo($comic['updated_at'] ?? '');
                        ?>
                        <div class="col-md-12 col-lg-6 item_home">
                            <div class="d-flex">
                                <div class="mr-3">
                                    <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? ''); ?>" class="thumbblock thumb70x85">
                                        <img data-src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                             alt="<?php echo htmlspecialchars($comic['name'] ?? ''); ?>" 
                                             class="lazy-image" 
                                             src="https://st.truyengg.net/template/frontend/img/loading.jpg"
                                             onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';"/>
                                    </a>
                                </div>
                                <div class="flex-one wc70">
                                    <a href="<?php echo COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? ''); ?>" 
                                       class="fs14 txt_oneline fw600" 
                                       title="<?php echo htmlspecialchars($comic['name'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($comic['name'] ?? ''); ?>
                                    </a>
                                    <div>
                                        <?php if ($latest_chapter): ?>
                                        <a href="<?php echo CHAPTER_URL . '?slug=' . htmlspecialchars($comic['slug'] ?? '') . '&chapter=' . htmlspecialchars($latest_chapter); ?>" 
                                           class="fs13 cl99" 
                                           title="Chương <?php echo htmlspecialchars($latest_chapter); ?>">
                                            Chương <?php echo htmlspecialchars($latest_chapter); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="fs13 cl99">Chưa có chapter</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fs13 cl99"><i class="bi bi-eye-fill"></i> <em><?php echo formatNumber($comic['views']); ?></em></div>
                                    <div class="fs13 cl99"><i class="bi bi-bookmark-plus-fill"></i> <em><?php echo formatNumber($comic['follow_count']); ?></em></div>
                                </div>
                            </div>
                        </div>
                        <?php
                                }
                            } else {
                                echo '<p>Không tìm thấy truyện nào!</p>';
                            }
                        } catch (Exception $e) {
                            error_log("Lỗi khi lấy danh sách truyện xem nhiều: " . $e->getMessage());
                            echo '<p>Lỗi khi tải danh sách truyện xem nhiều!</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
    <div class="container mb15">
        TruyenGG là website đọc truyện tranh và truyện chữ miễn phí, nơi hội tụ hàng ngàn tựa truyện thuộc nhiều thể loại như hành động, lãng mạn, hài hước,... Trang web cập nhật liên tục các chương truyện mới, đảm bảo người đọc không bỏ lỡ diễn biến hấp dẫn.
        Với giao diện thân thiện, dễ sử dụng trên mọi thiết bị, TruyenGG mang đến trải nghiệm đọc mượt mà và phong phú.
    </div>
    <div id="custom-popup" class="popup-container">
        <div class="popup-content">
            <i class="bi bi-code-slash"></i>
            Source code by 
            <span>
                <a href="https://www.facebook.com/tophvn" target="_blank" style="color: inherit; text-decoration: none;">
                    Hoàng Toph
                </a>
            </span>
        </div>
        <button class="close-btn" onclick="closePopup()">×</button>
    </div>
    <?php require_once 'includes/layouts/footer.php'; ?>
    <script src="assest/js/main.js" type="text/javascript"></script>
    <script>
        // Kiểm tra và hiển thị popup
        function checkPopup() {
            const popupShown = localStorage.getItem('popupShown');
            const now = new Date().getTime();
            const sixtyMinutes = 60 * 60 * 1000; // 60 phút
            if (!popupShown || now - popupShown > sixtyMinutes) {
                const popup = document.getElementById('custom-popup');
                popup.style.display = 'flex';
                popup.classList.add('show');

                // Tự động ẩn sau 5 giây
                setTimeout(() => {
                    if (popup.style.display !== 'none') {
                        closePopup();
                    }
                }, 10000);
            }
        }

        // Đóng popup và lưu thời gian
        function closePopup() {
            const popup = document.getElementById('custom-popup');
            popup.style.display = 'none';
            localStorage.setItem('popupShown', new Date().getTime());
        }
        window.onload = checkPopup;
    </script>
</body>
</html>
