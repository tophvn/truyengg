<?php
require_once 'includes/layouts/header.php';
require_once 'config/database.php';
require_once 'api/otruyen.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 24;

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

$base_url = rtrim((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/truyengg', '/') . '/';
$title = 'Truyện Mới';

// Lấy danh sách truyện từ API
$api = new OTruyenAPI();
$list_data = $api->getComicList('truyen-moi', $page, $per_page);
$comics = [];
$total_comics = 0;
$total_pages = 1;

if (isset($list_data['status']) && $list_data['status'] === 'success' && isset($list_data['data']['items'])) {
    $comics = $list_data['data']['items'];
    $total_comics = $list_data['data']['params']['pagination']['totalItems'] ?? count($comics);
    $total_pages = ceil($total_comics / $per_page);
    error_log("Page $page - Comics fetched: " . count($comics) . ", Total comics: $total_comics");
    // Debug: Kiểm tra trạng thái
    foreach ($comics as $index => $comic) {
        error_log("Comic " . ($index + 1) . ": " . ($comic['name'] ?? 'Unknown') . " - Status: " . ($comic['status'] ?? 'None'));
    }
} else {
    error_log("API error: " . ($list_data['msg'] ?? 'Unknown error'));
}

?>

<!DOCTYPE html>
<html lang="vi" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="google" content="notranslate">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes">
    <title>TruyenGG - Truyện Mới</title>
    <meta name="description" content="<?php echo htmlspecialchars($list_data['data']['seoOnPage']['descriptionHead'] ?? 'Danh sách truyện tranh mới nhất.'); ?>">
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .thumbblock.thumb140x195 {
            width: 140px;
            height: 195px;
            display: block;
            overflow: hidden;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .thumbblock.thumb140x195 img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }
        .thumbblock.thumb140x195:hover img {
            transform: scale(1.05);
        }
        .item_home {
            margin-bottom: 10px;
            text-align: center;
        }
        .list_item_home {
            margin: 0 -5px;
        }
        .list_item_home .col-lg-2 {
            padding: 5px;
        }
        .book_name {
            display: block;
            margin: 5px 0;
            height: 22px;
            line-height: 22px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .top-notice, .bottom-notice {
            font-size: 12px;
        }
        .type-label.mới {
            background-color: #FFC107 !important;
            color: #000 !important;
        }
        .type-label.hot {
            background-color: #FF4444 !important;
            color: #FFF !important;
        }
        .pagination .page-item {
            margin: 0 5px;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            color: #333;
        }
        .pagination .page-item.active {
            background-color: #007BFF;
            color: #FFF;
        }
        .pagination .page-item:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body class="dark-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <h1 class="title_cate mr-auto"><i class="bi bi-cloud-arrow-down-fill"></i> Truyện Mới</h1>
            </div>
            <div class="row list_item_home">
                <?php if (!empty($comics)): ?>
                    <?php foreach ($comics as $comic): ?>
                        <?php
                        // Xử lý thumb_url để đảm bảo URL hợp lệ
                        $thumb_url = !empty($comic['thumb_url']) 
                            ? (strpos($api->getImageUrl($comic['thumb_url']), 'http') === 0 ? htmlspecialchars($api->getImageUrl($comic['thumb_url'])) : $base_url . '/' . ltrim(htmlspecialchars($api->getImageUrl($comic['thumb_url'])), '/'))
                            : 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
                        ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <a href="<?php echo $base_url; ?>truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                                   class="thumbblock thumb140x195">
                                    <img src="<?php echo $thumb_url; ?>" 
                                         alt="<?php echo htmlspecialchars($comic['name']); ?>" 
                                         class="lazy-image"/>
                                </a>
                                <div class="top-notice">
                                    <span class="time-ago"><?php echo timeAgo($comic['updatedAt'] ?? ''); ?></span>
                                    <?php
                                    $chapter_name = isset($comic['chaptersLatest'][0]['chapter_name']) ? $comic['chaptersLatest'][0]['chapter_name'] : '0';
                                    $chapter_count = is_numeric($chapter_name) ? (int)$chapter_name : 0;
                                    $tag = $api->getTag($chapter_count);
                                    ?>
                                    <?php if ($tag): ?>
                                        <span class="type-label <?php echo strtolower($tag); ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom-notice">
                                    <span class="rate-star"><i class="bi bi-star-fill"></i> 3.5</span>
                                </div>
                            </div>
                            <a href="<?php echo $base_url; ?>truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                               class="fs16 txt_oneline book_name" 
                               title="<?php echo htmlspecialchars($comic['name']); ?>">
                                <?php echo htmlspecialchars($comic['name']); ?>
                            </a>
                            <div>
                                <a href="<?php echo $base_url; ?>chapter.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>&chapter=<?php echo htmlspecialchars($chapter_name); ?>" 
                                   class="fs14 cl99" 
                                   title="Chương <?php echo htmlspecialchars($chapter_name); ?>">
                                    Chương <?php echo htmlspecialchars($chapter_name); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Chưa có truyện mới nào.</p>
                <?php endif; ?>
            </div>
            <div class="pagination mt-4 mb20">
                <?php
                $max_pages = 5;
                $start_page = max(1, $page - floor($max_pages / 2));
                $end_page = min($total_pages, $start_page + $max_pages - 1);
                if ($end_page - $start_page + 1 < $max_pages) {
                    $start_page = max(1, $end_page - $max_pages + 1);
                }

                if ($page > 1) {
                    echo '<a class="page-item" href="' . $base_url . 'truyen-moi.php?page=1"><span aria-hidden="true">«</span></a>';
                    echo '<a class="page-item" href="' . $base_url . 'truyen-moi.php?page=' . ($page - 1) . '"><span aria-hidden="true">‹</span></a>';
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<a href="' . $base_url . 'truyen-moi.php?page=' . $i . '" 
                           class="page-item ' . ($page === $i ? 'active' : '') . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    echo '<a class="page-item" href="' . $base_url . 'truyen-moi.php?page=' . ($page + 1) . '"><span aria-hidden="true">›</span></a>';
                    echo '<a class="page-item" href="' . $base_url . 'truyen-moi.php?page=' . $total_pages . '"><span aria-hidden="true">»</span></a>';
                }
                ?>
            </div>
        </section>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
    <?php require_once 'includes/layouts/footer.php'; ?>
    <script src="assest/js/main.js"></script>
</body>
</html>
