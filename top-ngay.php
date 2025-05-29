<?php
require_once 'includes/layouts/header.php'; // Đường dẫn đã sửa từ trước
require_once 'config/database.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 18;
$offset = ($page - 1) * $per_page;
$status_filter = isset($_GET['status']) ? $_GET['status'] : null;
$country_filter = isset($_GET['country']) ? (int)$_GET['country'] : null;

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

$base_url = rtrim((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/truyengg', '/');
$title = 'Top Ngày';

// Định nghĩa danh sách thể loại theo quốc gia
$country_categories = [
    1 => [ // Trung Quốc
        'Manhua', 'Cổ Đại', 'Ngôn Tình', 'Xuyên Không', 'Truyện Màu', 
        'Truyện scan', 'Trinh Thám', 'Martial Arts'
    ],
    2 => [ // Hàn Quốc
        'Manhwa', 'Webtoon', 'Soft Yaoi', 'Soft Yuri', 'Smut', 'Adult',
        'Tragedy', 'Drama', 'Romance'
    ],
    3 => [ // Nhật Bản
        'Manga', 'Anime', 'Seinen', 'Shounen', 'Shoujo', 'Shounen Ai', 
        'Shoujo Ai', 'Ecchi', 'Harem', 'Mecha', 'Josei', 'Slice of Life', 
        'Doujinshi', 'One shot', 'Live action', 'Cooking', 'Psychological', 
        'Fantasy'
    ],
    4 => [ // Việt Nam
        'Việt Nam', 'Tạp chí truyện tranh', 'Thiếu Nhi'
    ]
];

// Xây dựng truy vấn
$where = "WHERE 1=1";
if ($status_filter === '0') {
    $where .= " AND c.status = 'ongoing'";
} elseif ($status_filter === '2') {
    $where .= " AND c.status = 'completed'";
}
if ($country_filter && isset($country_categories[$country_filter])) {
    $categories = array_map(function($cat) use ($conn) {
        return "'" . $conn->real_escape_string($cat) . "'";
    }, $country_categories[$country_filter]);
    $category_list = implode(',', $categories);
    $where .= " AND (";
    foreach ($country_categories[$country_filter] as $index => $cat) {
        if ($index > 0) $where .= " OR ";
        $where .= "c.main_category LIKE '%" . $conn->real_escape_string($cat) . "%'";
    }
    $where .= ")";
}

// Truy vấn lấy danh sách truyện với lượt xem ngày
$query = "
    SELECT 
        c.id, 
        c.name, 
        c.slug, 
        c.thumb_url, 
        c.updated_at, 
        c.is_hot,
        COALESCE(SUM(vl.views_increment), 0) as day_views,
        (SELECT chapter_name FROM chapters ch WHERE ch.comic_id = c.id ORDER BY CAST(ch.chapter_name AS UNSIGNED) DESC LIMIT 1) as latest_chapter
    FROM comics c
    LEFT JOIN comic_view_logs vl ON c.id = vl.comic_id 
        AND DATE(vl.log_date) = CURDATE()
    $where
    GROUP BY c.id
    ORDER BY day_views DESC, c.updated_at DESC
    LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();
$comics = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Tổng số trang
$count_query = "
    SELECT COUNT(DISTINCT c.id) as total 
    FROM comics c
    $where";
$count_result = $conn->query($count_query);
$total_comics = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_comics / $per_page);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Top Ngày</title>
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="dark-style">
    <div class="container container-background">
        <section>
            <div class="d-flex mb15"><h1 class="title_cate mr-auto">Top Ngày</h1></div>
            <section class="mb40 box-filter">
                <div class="row mb-3">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Trạng Thái</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex">
                            <a class="btn_short <?php echo $status_filter === '0' ? 'active' : ''; ?>" 
                               href="<?php echo $base_url; ?>/top-ngay.php?page=1&status=0<?php echo $country_filter ? '&country=' . $country_filter : ''; ?>">
                                Đang tiến hành
                            </a>
                            <a class="btn_short <?php echo $status_filter === '2' ? 'active' : ''; ?>" 
                               href="<?php echo $base_url; ?>/top-ngay.php?page=1&status=2<?php echo $country_filter ? '&country=' . $country_filter : ''; ?>">
                                Hoàn thành
                            </a>
                            <a class="btn_short <?php echo $status_filter === null ? 'active' : ''; ?>" 
                               href="<?php echo $base_url; ?>/top-ngay.php?page=1<?php echo $country_filter ? '&country=' . $country_filter : ''; ?>">
                                Tất cả
                            </a>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 col-3">
                        <div class="title_cate">Quốc Gia</div>
                    </div>
                    <div class="col-md-10 col-9">
                        <div class="d-flex">
                            <?php
                            $countries = [
                                1 => '🇨🇳 Trung Quốc', 
                                2 => '🇰🇷 Hàn Quốc', 
                                3 => '🇯🇵 Nhật Bản', 
                                4 => '🇻🇳 Việt Nam'
                            ];
                            foreach ($countries as $id => $name) {
                                echo '<a class="btn_short ' . ($country_filter === $id ? 'active' : '') . '" 
                                        title="' . $name . '" 
                                        href="' . $base_url . '/top-ngay.php?page=1' . ($status_filter !== null ? '&status=' . $status_filter : '') . '&country=' . $id . '">
                                        ' . $name . '
                                      </a>';
                            }
                            ?>
                            <a class="btn_short <?php echo $country_filter === null ? 'active' : ''; ?>" 
                               href="<?php echo $base_url; ?>/top-ngay.php?page=1<?php echo $status_filter !== null ? '&status=' . $status_filter : ''; ?>">
                                Tất cả
                            </a>
                        </div>
                    </div>
                </div>
            </section>
            <div class="row list_item_home">
                <?php if (!empty($comics)): ?>
                    <?php foreach ($comics as $comic): ?>
                        <?php
                        // Xử lý thumb_url để đảm bảo URL hợp lệ
                        $thumb_url = !empty($comic['thumb_url']) 
                            ? (strpos($comic['thumb_url'], 'http') === 0 ? htmlspecialchars($comic['thumb_url']) : $base_url . '/' . ltrim(htmlspecialchars($comic['thumb_url']), '/'))
                            : 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
                        ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 col-6 item_home">
                            <div class="image-cover">
                                <a href="<?php echo $base_url; ?>/truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                                   class="thumbblock thumb140x195">
                                    <img src="<?php echo $thumb_url; ?>" 
                                         alt="<?php echo htmlspecialchars($comic['name']); ?>" 
                                         class="lazy-image"/>
                                </a>
                                <div class="top-notice">
                                    <span class="time-ago"><?php echo timeAgo($comic['updated_at']); ?></span>
                                    <?php if ($comic['is_hot']): ?>
                                        <span class="type-label hot">Hot</span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom-notice"><span class="rate-star"><i class="bi bi-star-fill"></i> 3.5</span></div>
                            </div>
                            <a href="<?php echo $base_url; ?>/truyen-tranh.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>" 
                               class="fs16 txt_oneline book_name" 
                               title="<?php echo htmlspecialchars($comic['name']); ?>">
                                <?php echo htmlspecialchars($comic['name']); ?>
                            </a>
                            <div>
                                <a href="<?php echo $base_url; ?>/chapter.php?slug=<?php echo htmlspecialchars($comic['slug']); ?>&chapter=<?php echo htmlspecialchars($comic['latest_chapter'] ?? '1'); ?>" 
                                   class="fs14 cl99" 
                                   title="Chương <?php echo htmlspecialchars($comic['latest_chapter'] ?? '1'); ?>">
                                    Chương <?php echo htmlspecialchars($comic['latest_chapter'] ?? '1'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Chưa có truyện nào được cập nhật.</p>
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
                    echo '<a class="page-item" 
                           href="' . $base_url . '/top-ngay.php?page=1' . ($status_filter !== null ? '&status=' . $status_filter : '') . ($country_filter ? '&country=' . $country_filter : '') . '">
                           <span aria-hidden="true">«</span></a>';
                    echo '<a class="page-item" 
                           href="' . $base_url . '/top-ngay.php?page=' . ($page - 1) . ($status_filter !== null ? '&status=' . $status_filter : '') . ($country_filter ? '&country=' . $country_filter : '') . '">
                           <span aria-hidden="true">‹</span></a>';
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<a href="' . $base_url . '/top-ngay.php?page=' . $i . ($status_filter !== null ? '&status=' . $status_filter : '') . ($country_filter ? '&country=' . $country_filter : '') . '" 
                           class="page-item ' . ($page === $i ? 'active' : '') . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    echo '<a class="page-item" 
                           href="' . $base_url . '/top-ngay.php?page=' . ($page + 1) . ($status_filter !== null ? '&status=' . $status_filter : '') . ($country_filter ? '&country=' . $country_filter : '') . '">
                           <span aria-hidden="true">›</span></a>';
                    echo '<a class="page-item" 
                           href="' . $base_url . '/top-ngay.php?page=' . $total_pages . ($status_filter !== null ? '&status=' . $status_filter : '') . ($country_filter ? '&country=' . $country_filter : '') . '">
                           <span aria-hidden="true">»</span></a>';
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