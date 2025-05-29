<?php
require_once __DIR__ . '/config/routes.php';
require_once __DIR__ . '/includes/layouts/header.php';
require_once __DIR__ . '/api/otruyen.php';

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

$slug = isset($_GET['slug']) ? $_GET['slug'] : 'action';
$genre_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 37;
$page = isset($_GET['trang']) && is_numeric($_GET['trang']) && $_GET['trang'] > 0 ? (int)$_GET['trang'] : 1;
$per_page = 24;

$api = new OTruyenAPI();
$categories_response = $api->getCategories();
$categories = isset($categories_response['data']['items']) ? $categories_response['data']['items'] : [];

$genre_name = 'Thể Loại';
$genre_description = 'Thể loại này chưa có mô tả.';
foreach ($categories as $category) {
    if ($category['slug'] === $slug) {
        $genre_name = $category['name'];
        $genre_description = isset($category['description']) ? $category['description'] : 'Thể loại này chưa có mô tả.';
        break;
    }
}

// Lấy danh sách truyện với phân trang
$comics_data = $api->getComicsByCategory($slug, $page, $per_page);
$comics = isset($comics_data['data']['items']) ? $comics_data['data']['items'] : [];
$total_comics = isset($comics_data['data']['params']['pagination']['totalItems']) ? $comics_data['data']['params']['pagination']['totalItems'] : 0;
$total_pages = ceil($total_comics / $per_page);

$base_id = 37;
?>

<!DOCTYPE html>
<html lang="vi">
<head itemscope itemtype="http://schema.org/WebPage">
    <meta name="robots" content="index, follow">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="google" content="notranslate">
    <meta property="og:site_name" content="TruyenGG.Net" />
    <meta name="twitter:site" content="TruyenGG.Net" />
    <meta name="twitter:creator" content="TruyenGG.Net" />
    <title>Truyện Tranh <?php echo htmlspecialchars($genre_name); ?></title>
    <meta name="keyword" content="Truyện Tranh <?php echo htmlspecialchars($genre_name); ?>, truyện chữ, manga, manhwa, manhua">
    <meta name="description" content="<?php echo htmlspecialchars($genre_description); ?>">
    <meta name="author" content="TruyenGG.Net">
    <meta property="og:title" content="Truyện Tranh <?php echo htmlspecialchars($genre_name); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($genre_description); ?>">
    <meta property="og:type" content="website" />
    <meta property="og:image" content="<?php echo BASE_URL; ?>assest/img/logo-share.png">
    <meta itemprop="description" content="<?php echo htmlspecialchars($genre_description); ?>">
    <meta itemprop="name" content="Truyện Tranh <?php echo htmlspecialchars($genre_name); ?>">
    <link rel="canonical" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>" />
    <meta property="og:url" content="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>">
    <link href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>" rel="amphtml">
    <style>
        .thumbblock.thumb140x195 {
            display: block;
            overflow: hidden;
            position: relative;
        }
        .lazy-image {
            width: 140px;
            height: 195px;
            object-fit: cover;
            display: block;
        }
        .image-cover {
            position: relative;
            overflow: hidden;
        }
        .type-label.mới {
            background-color: #FFC107 !important;
        }
    </style>
</head>
<body class="light-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15">
                <h1 class="title_cate mr-auto">Truyện Tranh <?php echo htmlspecialchars($genre_name); ?></h1>
            </div>
            <div class="tags_detail"><?php echo htmlspecialchars($genre_description); ?></div>
            <section class="mb40 box-filter">
                <div class="row mb-3">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Thể Loại</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex list_genre">
                            <select id="list-category" onchange="window.location.href=this.value">
                                <?php foreach ($categories as $index => $category): ?>
                                    <option <?php echo $category['slug'] === $slug ? 'selected' : ''; ?> value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($category['slug']); ?>&id=<?php echo htmlspecialchars($base_id + $index); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Trạng Thái</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex">
                            <a class="btn_short" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&status=0">Đang tiến hành</a>
                            <a class="btn_short" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&status=2">Hoàn thành</a>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Quốc Gia</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex">
                            <a class="btn_short" title="Trung Quốc" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&country=1">Trung Quốc</a>
                            <a class="btn_short" title="Hàn Quốc" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&country=2">Hàn Quốc</a>
                            <a class="btn_short" title="Nhật Bản" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&country=3">Nhật Bản</a>
                            <a class="btn_short" title="Việt Nam" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&country=4">Việt Nam</a>
                            <a class="btn_short" title="Mỹ" href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&country=5">Mỹ</a>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Sắp Xếp</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex list_genre">
                            <select id="category-sort" onchange="window.location.href=this.value">
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=0">Ngày đăng giảm dần</option>
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=1">Ngày đăng tăng dần</option>
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=2">Ngày cập nhật giảm dần</option>
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=3">Ngày cập nhật tăng dần</option>
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=4">Lượt xem giảm dần</option>
                                <option value="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($slug); ?>&id=<?php echo htmlspecialchars($genre_id); ?>&sort=5">Lượt xem tăng dần</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
            <div class="row list_item_home">
                <?php if (empty($comics)): ?>
                    <p>Không tìm thấy truyện cho trang <?php echo $page; ?>!</p>
                <?php else: ?>
                    <?php foreach ($comics as $comic): ?>
                        <?php
                        $last_chapter = !empty($comic['chapters']) ? end($comic['chapters'])['chapter_number'] : (!empty($comic['chaptersLatest']) ? $comic['chaptersLatest'][0]['chapter_name'] : 1);
                        $chapter_count = (int)$last_chapter;
                        $tag = $api->getTag($chapter_count);
                        ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <a href="<?php echo BASE_URL; ?>truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" class="thumbblock thumb140x195">
                                    <img data-src="<?php echo $api->getImageUrl($comic['thumb_url'] ?? ''); ?>" 
                                         alt="<?php echo htmlspecialchars($comic['name'] ?? 'Unknown'); ?>" 
                                         class="lazy-image" 
                                         style="width: 140px; height: 195px; object-fit: cover;" 
                                         src="https://st.truyengg.net/template/frontend/img/loading.jpg"
                                         onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';"/>
                                </a>
                                <div class="top-notice">
                                    <span class="time-ago"><?php echo timeAgo($comic['updatedAt'] ?? ''); ?></span>
                                    <?php if ($tag): ?>
                                        <span class="type-label <?php echo strtolower($tag); ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom-notice"><span class="rate-star"><i class="bi bi-star-fill"></i> <?php echo htmlspecialchars($comic['rating'] ?? 5); ?></span></div>
                            </div>
                            <a href="<?php echo BASE_URL; ?>truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                               class="fs16 txt_oneline book_name" 
                               title="<?php echo htmlspecialchars($comic['name'] ?? 'Unknown'); ?>">
                                <?php echo htmlspecialchars($comic['name'] ?? 'Unknown'); ?>
                            </a>
                            <div>
                                <a href="<?php echo BASE_URL; ?>truyen-tranh/<?php echo htmlspecialchars($comic['slug']); ?>-chap-<?php echo htmlspecialchars($last_chapter); ?>.html" 
                                   class="fs14 cl99" 
                                   title="Chương <?php echo htmlspecialchars($last_chapter); ?>">
                                    Chương <?php echo htmlspecialchars($last_chapter); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="pagination mt-4 mb20">
                <?php
                if ($page > 1) {
                    echo '<a class="page-item" href="' . BASE_URL . 'the-loai.php?slug=' . htmlspecialchars($slug) . '&id=' . htmlspecialchars($genre_id) . '&trang=1"><span aria-hidden="true">«</span></a>';
                }
                if ($page > 1) {
                    echo '<a class="page-item" href="' . BASE_URL . 'the-loai.php?slug=' . htmlspecialchars($slug) . '&id=' . htmlspecialchars($genre_id) . '&trang=' . ($page - 1) . '"><span aria-hidden="true">‹</span></a>';
                }
                $max_pages = 5;
                $half = floor($max_pages / 2);
                $start_page = max(1, $page - $half);
                $end_page = min($total_pages, $start_page + $max_pages - 1);
                if ($end_page - $start_page + 1 < $max_pages) {
                    $start_page = max(1, $end_page - $max_pages + 1);
                }
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<a href="javascript:void(0)" class="page-item active">' . $i . '</a>';
                    } else {
                        echo '<a class="page-item" href="' . BASE_URL . 'the-loai.php?slug=' . htmlspecialchars($slug) . '&id=' . htmlspecialchars($genre_id) . '&trang=' . $i . '">' . $i . '</a>';
                    }
                }
                if ($page < $total_pages) {
                    echo '<a class="page-item" href="' . BASE_URL . 'the-loai.php?slug=' . htmlspecialchars($slug) . '&id=' . htmlspecialchars($genre_id) . '&trang=' . ($page + 1) . '"><span aria-hidden="true">›</span></a>';
                }
                if ($page < $total_pages) {
                    echo '<a class="page-item" href="' . BASE_URL . 'the-loai.php?slug=' . htmlspecialchars($slug) . '&id=' . htmlspecialchars($genre_id) . '&trang=' . $total_pages . '"><span aria-hidden="true">»</span></a>';
                }
                ?>
            </div>
        </section>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
    <?php require_once __DIR__ . '/includes/layouts/footer.php'; ?>
    <script src="<?php echo BASE_URL; ?>assest/js/main.js" type="text/javascript"></script>
</body>
</html>