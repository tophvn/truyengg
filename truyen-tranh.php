<?php
require_once __DIR__ . '/config/routes.php';
require_once __DIR__ . '/includes/layouts/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/api/otruyen.php';

// Hàm tính khoảng thời gian từ ngày cập nhật
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

// Hàm tạo chuỗi thể loại
function getCategoriesString($categories) {
    if (empty($categories)) return null;
    $category_names = array_map(function($cat) {
        return trim($cat['name'] ?? '');
    }, $categories);
    $category_names = array_filter($category_names);
    return !empty($category_names) ? implode(', ', $category_names) : null;
}

// Hàm hiển thị trạng thái bằng tiếng Việt với màu sắc
function displayStatus($status) {
    switch ($status) {
        case 'ongoing':
            return '<span style="color: #28a745;">Đang Tiến Hành</span>';
        case 'completed':
            return '<span style="color: #dc3545;">Hoàn Thành</span>';
        case 'coming_soon':
            return '<span style="color: #007bff;">Sắp Ra Mắt</span>';
        case 'onhold':
            return '<span style="color: #ffc107;">Tạm Dừng</span>';
        case 'dropped':
            return '<span style="color: #6c757d;">Đã Hủy</span>';
        default:
            return '<span style="color: #6c757d;">Không Xác Định</span>';
    }
}

// Lấy slug từ URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$comic_data = null;
$comic = null;
$comic_db_id = null;
$is_local = false;
$categories = [];
$chapters = [];
$thumb_url = '';
$main_category = null;
$origin_name = 'N/A';
$first_chapter = '1';
$status = 'ongoing';
$new_views = 0;
$follow_count = 0;
$is_following = false;
$chapter_count = 0;

// Kiểm tra comic trong database trước
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.comic_id, c.name, c.slug, c.origin_name, c.content, c.status, c.thumb_url, c.author, c.views, c.updated_at
        FROM comics c
        WHERE c.slug = ?
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $comic_row = $result->fetch_assoc();
    $stmt->close();

    if ($comic_row && strpos($comic_row['comic_id'], 'local_') === 0) {
        // Comic là local (từ stories.php)
        $is_local = true;
        $comic_db_id = $comic_row['id'];
        $comic = [
            '_id' => $comic_row['comic_id'],
            'name' => $comic_row['name'],
            'slug' => $comic_row['slug'],
            'content' => $comic_row['content'],
            'status' => $comic_row['status'],
            'thumb_url' => $comic_row['thumb_url'],
            'updatedAt' => $comic_row['updated_at'],
            'author' => json_decode($comic_row['author'], true) ?: [],
            'origin_name' => json_decode($comic_row['origin_name'], true) ?: [],
        ];
        $thumb_url = $comic_row['thumb_url'] ?: 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
        $status = $comic_row['status'];
        $new_views = $comic_row['views'];

        // Lấy thể loại
        $stmt = $conn->prepare("
            SELECT cat.id, cat.category_id, cat.name, cat.slug
            FROM categories cat
            JOIN comic_categories cc ON cat.id = cc.category_id
            WHERE cc.comic_id = ?
        ");
        $stmt->bind_param("i", $comic_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($cat = $result->fetch_assoc()) {
            $categories[] = [
                'id' => $cat['category_id'],
                'name' => $cat['name'],
                'slug' => $cat['slug'],
            ];
        }
        $stmt->close();
        $main_category = getCategoriesString($categories);

        // Lấy chapters
        $stmt = $conn->prepare("
            SELECT c.id, c.chapter_name, c.chapter_title, c.created_at
            FROM chapters c
            LEFT JOIN chapter_images ci ON c.id = ci.chapter_id
            WHERE c.comic_id = ?
            GROUP BY c.id
            ORDER BY CAST(c.chapter_name AS UNSIGNED) ASC
        ");
        $stmt->bind_param("i", $comic_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($chapter = $result->fetch_assoc()) {
            $chapters[] = [
                'chapter_name' => $chapter['chapter_name'],
                'chapter_title' => $chapter['chapter_title'] ?? '',
                'image_url' => null, // Ảnh được lưu trong chapter_images, xử lý ở chapter.php
                'updated_at' => $chapter['created_at'],
            ];
        }
        $stmt->close();
        $chapter_count = count($chapters);
        error_log("Chapters found for comic_id $comic_db_id: $chapter_count");
        $first_chapter = !empty($chapters) ? $chapters[0]['chapter_name'] : '1';

        // Xử lý origin_name
        $origin_name = !empty($comic['origin_name']) ? (is_array($comic['origin_name']) ? $comic['origin_name'][0] : $comic['origin_name']) : 'N/A';
    }
} catch (Exception $e) {
    error_log("Lỗi kiểm tra comic trong database, slug: $slug, lỗi: " . $e->getMessage());
}

if (!$is_local) {
    // Fetch từ OTruyenAPI nếu không phải comic local
    $api = new OTruyenAPI();
    $comic_data = $api->getComicDetails($slug);

    if (!isset($comic_data['data']['item']) || empty($comic_data['data']['item'])) {
        error_log("Không tìm thấy truyện với slug: $slug");
        echo '<div class="container"><p>Không tìm thấy truyện!</p></div>';
        require_once __DIR__ . '/includes/layouts/footer.php';
        exit;
    }

    $comic = $comic_data['data']['item'];
    $comic_id = $comic['_id'] ?? uniqid();
    $thumb_url = $api->getImageUrl($comic['thumb_url'] ?? '');
    $categories = $comic['category'] ?? [];
    $chapters = (isset($comic['chapters']) && is_array($comic['chapters']) && !empty($comic['chapters']) && isset($comic['chapters'][0]['server_data']))
        ? $comic['chapters'][0]['server_data']
        : [];
    $chapter_count = count($chapters);
    $main_category = getCategoriesString($categories);
    $origin_name = isset($comic['origin_name']) && !is_null($comic['origin_name'])
        ? (is_array($comic['origin_name']) && !empty($comic['origin_name']) ? $comic['origin_name'][0] : $comic['origin_name'])
        : 'N/A';
    $first_chapter = !empty($chapters) && isset($chapters[0]['chapter_name']) ? $chapters[0]['chapter_name'] : '1';
    $status = $comic['status'] ?? 'ongoing';
}

// Lưu hoặc cập nhật dữ liệu vào database (chỉ cho comic từ API)
if (!$is_local) {
    try {
        $stmt = $conn->prepare("SELECT id, views FROM comics WHERE comic_id = ?");
        $stmt->bind_param("s", $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $comic_row = $result->fetch_assoc();
        $comic_db_id = $comic_row['id'] ?? null;
        $current_views = $comic_row['views'] ?? 0;
        $stmt->close();

        // Tăng lượt xem ngẫu nhiên từ 10-100
        $random_views = rand(10, 100);
        $new_views = $current_views + $random_views;

        // Chuẩn hóa trạng thái
        $valid_statuses = ['ongoing', 'completed', 'onhold', 'dropped', 'coming_soon'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'ongoing';
        }

        if (!$comic_db_id) {
            // Thêm truyện mới
            $stmt = $conn->prepare("
                INSERT INTO comics (comic_id, name, slug, origin_name, content, status, thumb_url, author, updated_at, is_backed_up, views)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $origin_name_db = json_encode(is_array($comic['origin_name']) ? $comic['origin_name'] : [$comic['origin_name']]);
            $author = json_encode($comic['author'] ?? []);
            $updated_at = date('Y-m-d H:i:s', strtotime($comic['updatedAt'] ?? 'now'));
            $thumb_url_db = $thumb_url ?: null;
            $content = $comic['content'] ?? '';
            $stmt->bind_param(
                "sssssssssi",
                $comic_id,
                $comic['name'],
                $comic['slug'],
                $origin_name_db,
                $content,
                $status,
                $thumb_url_db,
                $author,
                $updated_at,
                $new_views
            );
            $stmt->execute();
            $comic_db_id = $conn->insert_id;
            $stmt->close();

            // Ghi log lượt xem
            $stmt = $conn->prepare("INSERT INTO comic_view_logs (comic_id, views_increment, log_date) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $comic_db_id, $random_views);
            $stmt->execute();
            $stmt->close();

            // Lưu thể loại
            foreach ($categories as $cat) {
                $stmt = $conn->prepare("
                    INSERT INTO categories (category_id, name, slug)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)
                ");
                $cat_id = $cat['id'] ?? '';
                $cat_name = $cat['name'] ?? '';
                $cat_slug = $cat['slug'] ?? '';
                $stmt->bind_param("sss", $cat_id, $cat_name, $cat_slug);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("SELECT id FROM categories WHERE category_id = ?");
                $stmt->bind_param("s", $cat_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $category_id = $result->fetch_assoc()['id'] ?? null;
                $stmt->close();

                if ($category_id) {
                    $stmt = $conn->prepare("
                        INSERT INTO comic_categories (comic_id, category_id)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE comic_id = comic_id
                    ");
                    $stmt->bind_param("ii", $comic_db_id, $category_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Lưu chapters
            if (!empty($chapters)) {
                $stmt = $conn->prepare("
                    INSERT INTO chapters (comic_id, chapter_name, chapter_title, filename, chapter_api_data, is_backed_up)
                    VALUES (?, ?, ?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE
                        chapter_title = VALUES(chapter_title),
                        filename = VALUES(filename),
                        chapter_api_data = VALUES(chapter_api_data)
                ");
                foreach ($chapters as $chapter) {
                    $chapter_name = $chapter['chapter_name'] ?? '';
                    $chapter_title = $chapter['chapter_title'] ?? '';
                    $filename = $chapter['filename'] ?? '';
                    $chapter_api_data = $chapter['chapter_api_data'] ?? '';
                    if (empty($chapter_name)) continue;
                    $stmt->bind_param("issss", $comic_db_id, $chapter_name, $chapter_title, $filename, $chapter_api_data);
                    $stmt->execute();
                }
                $stmt->close();
            }
        } else {
            // Cập nhật truyện cũ
            $stmt = $conn->prepare("
                UPDATE comics 
                SET views = ?, status = ?, updated_at = ?
                WHERE id = ?
            ");
            $updated_at = date('Y-m-d H:i:s', strtotime($comic['updatedAt'] ?? 'now'));
            $stmt->bind_param("issi", $new_views, $status, $updated_at, $comic_db_id);
            $stmt->execute();
            $stmt->close();

            // Ghi log lượt xem
            $stmt = $conn->prepare("INSERT INTO comic_view_logs (comic_id, views_increment, log_date) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $comic_db_id, $random_views);
            $stmt->execute();
            $stmt->close();

            // Cập nhật thể loại
            foreach ($categories as $cat) {
                $stmt = $conn->prepare("
                    INSERT INTO categories (category_id, name, slug)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)
                ");
                $cat_id = $cat['id'] ?? '';
                $cat_name = $cat['name'] ?? '';
                $cat_slug = $cat['slug'] ?? '';
                $stmt->bind_param("sss", $cat_id, $cat_name, $cat_slug);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("SELECT id FROM categories WHERE category_id = ?");
                $stmt->bind_param("s", $cat_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $category_id = $result->fetch_assoc()['id'] ?? null;
                $stmt->close();

                if ($category_id) {
                    $stmt = $conn->prepare("
                        INSERT INTO comic_categories (comic_id, category_id)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE comic_id = comic_id
                    ");
                    $stmt->bind_param("ii", $comic_db_id, $category_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Lỗi lưu dữ liệu truyện $slug: " . $e->getMessage());
    }
}

// Cập nhật lượt xem cho comic local
if ($is_local) {
    try {
        $random_views = rand(10, 100);
        $new_views += $random_views;
        $stmt = $conn->prepare("UPDATE comics SET views = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_views, $comic_db_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO comic_view_logs (comic_id, views_increment, log_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $comic_db_id, $random_views);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Lỗi cập nhật lượt xem cho comic local $slug: " . $e->getMessage());
    }
}

// Kiểm tra trạng thái theo dõi
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM user_follows WHERE user_id = ? AND comic_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $comic_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_following = $result->num_rows > 0;
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as follow_count FROM user_follows WHERE comic_id = ?");
$stmt->bind_param("i", $comic_db_id);
$stmt->execute();
$result = $stmt->get_result();
$follow_count = $result->fetch_assoc()['follow_count'];
$stmt->close();

// Lấy bình luận
$comments_per_page = 10;
$current_page = isset($_GET['comment_page']) ? max(1, (int)$_GET['comment_page']) : 1;
$offset = ($current_page - 1) * $comments_per_page;

$stmt = $conn->prepare("
    SELECT c.id, c.user_id, c.content, c.likes, c.dislikes, c.created_at, c.parent_id, u.username, u.avatar, u.level
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.comic_id = ? AND c.parent_id IS NULL
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $comic_db_id, $comments_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE comic_id = ? AND parent_id IS NULL");
$stmt->bind_param("i", $comic_db_id);
$stmt->execute();
$result = $stmt->get_result();
$total_comments = $result->fetch_assoc()['total'];
$total_comment_pages = ceil($total_comments / $comments_per_page);
$stmt->close();

$child_comments = [];
foreach ($comments as $comment) {
    $stmt = $conn->prepare("
        SELECT c.id, c.user_id, c.content, c.likes, c.dislikes, c.created_at, c.parent_id, u.username, u.avatar, u.level
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.parent_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("i", $comment['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $child_comments[$comment['id']][] = $row;
    }
    $stmt->close();
}

// Tạo tag dựa trên số chương
$tag = '';
if ($chapter_count >= 0 && $chapter_count <= 10) $tag = 'Mới ra mắt';
elseif ($chapter_count > 10 && $chapter_count <= 50) $tag = 'Đang phát triển';
elseif ($chapter_count > 50 && $chapter_count <= 100) $tag = 'Nổi bật';
elseif ($chapter_count > 100) $tag = 'Dài tập';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($comic['name']) . ' - TruyenGG'; ?></title>
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>
<body class="dark-style">
<div class="container container-background container_info mb15">
    <ol class="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="breadcrumb-item">
            <a itemprop="item" href="<?php echo BASE_URL; ?>">
                <span itemprop="name">Trang Chủ</span>
            </a>
            <meta itemprop="position" content="1" />
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="breadcrumb-item active">
            <a itemprop="item" href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>">
                <span itemprop="name"><?php echo htmlspecialchars($comic['name']); ?></span>
            </a>
            <meta itemprop="position" content="2" />
        </li>
    </ol>

    <section class="mt-4">
        <div class="d-flex box_info_tale">
            <div class="thumbblock thumb170x220">
                <img data-src="<?php echo htmlspecialchars($thumb_url); ?>" 
                     alt="<?php echo htmlspecialchars($comic['name']); ?>" 
                     class="lazy-image" 
                     style="width: 230px; height: 300px; object-fit: cover;" 
                     src="assets/img/loading.jpg"
                     onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';"/>
            </div>
            <div class="info_tale fs15">
                <div class="title_tale">
                    <h1 itemprop="name"><?php echo htmlspecialchars($comic['name']); ?></h1>
                </div>
                <div class="mb-2 row">
                    <p class="name-title col-lg-2 col-md-4 col-4"><i class="bi bi-person-badge-fill"></i> Tác Giả:</p>
                    <p class="col-lg-10 col-md-8 col-8">
                        <?php echo !empty($comic['author']) && is_array($comic['author']) && !empty($comic['author'][0]) ? htmlspecialchars($comic['author'][0]) : 'Đang Cập Nhật'; ?>
                    </p>
                </div>
                <div class="mb-2 row">
                    <p class="name-title col-lg-2 col-md-4 col-4"><i class="bi bi-bar-chart-line-fill"></i> Trạng Thái:</p>
                    <p class="col-lg-10 col-md-8 col-8"><?php echo displayStatus($status); ?></p>
                </div>
                <div class="mb-2 row">
                    <p class="name-title col-lg-2 col-md-4 col-4"><i class="bi bi-eye-fill"></i> Lượt Xem:</p>
                    <p class="col-lg-10 col-md-8 col-8"><?php echo number_format($new_views, 0, ',', '.'); ?></p>
                </div>
                <div class="mb-2 row">
                    <p class="name-title col-lg-2 col-md-4 col-4"><i class="bi bi-bookmark-plus-fill"></i> Theo Dõi:</p>
                    <p class="col-lg-10 col-md-8 col-8"><span id="follow-count"><?php echo $follow_count; ?></span></p>
                </div>
                <div class="mb-2 row">
                    <p class="name-title col-lg-2 col-md-4 col-4"><i class="bi bi-list-stars"></i> Tên gốc:</p>
                    <p class="col-lg-10 col-md-8 col-8"><?php echo htmlspecialchars($origin_name); ?></p>
                </div>
                <div class="mb-2 row">
                    <p class="col-lg-12 col-md-12 col-12">
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $category_id = $category['id'] ?? '';
                            if ($is_local) {
                                $stmt = $conn->prepare("SELECT id FROM categories WHERE category_id = ?");
                                $stmt->bind_param("s", $category_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $category_db_id = $result->fetch_assoc()['id'] ?? 37;
                                $stmt->close();
                            } else {
                                $category_db_id = 37; // Fallback cho API
                            }
                            ?>
                            <a class="clblue" title="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" 
                               href="<?php echo BASE_URL; ?>the-loai.php?slug=<?php echo htmlspecialchars($category['slug'] ?? ''); ?>&id=<?php echo $category_db_id; ?>">
                                <?php echo htmlspecialchars($category['name'] ?? ''); ?>
                            </a>
                        <?php endforeach; ?>
                    </p>
                </div>
                <div class="mb-2 row">
                    <p class="col-lg-12 col-md-12 col-8"><span id="rating" data-rateyo-half-star="true"></span></p>
                </div>
                <div>
                    <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($first_chapter); ?>" 
                       class="btn_tale firt_button"><i class="bi bi-door-open-fill"></i> Đọc Từ Đầu</a>
                    <a href="javascript:void(0);" 
                       class="btn_tale subscribe_button <?php echo $is_following ? 'following' : ''; ?>" 
                       id="follow-button" 
                       data-id="<?php echo $comic_db_id; ?>">
                        <i class="bi bi-bookmark-plus-fill"></i> 
                        <span><?php echo $is_following ? 'Hủy Theo Dõi' : 'Theo Dõi'; ?></span>
                    </a>
                </div>
            </div>
        </div>
        <div class="fs15 mt-5 story-detail-info>
            <a href="<?php echo COMIC_DETAIL_URL; ?>?slug="<?php echo htmlspecialchars($slug); ?>" 
               title="<?php echo htmlspecialchars($comic['name']); ?>">
               Truyện tranh <?php echo htmlspecialchars($comic['name']); ?>
            </a> được cập nhật nhanh và đầy đủ nhất tại TruyenGG. Bạn đọc đừng quên để lại bình luận và chia sẻ để ủng hộ TruyenGG ra các chương mới nhanh hơn.
        </div>
        <div class="mt-5">
            <div class="box_list_chap">
                <div class="title_cate mr-auto"><i class="bi bi-list-task"></i> Danh Sách Chương</div>
                <div class="sorting_chap">
                    <input type="text" class="finchap_list" onkeyup="findList()" placeholder="Số Chương">
                    <i class="bi bi-sort-down"></i>
                </div>
            </div>
            <ul class="list_chap">
                <?php if (!empty($chapters)): ?>
                    <?php foreach (array_reverse($chapters) as $chapter): ?>
                        <li class="item_chap">
                            <div class="wc110">
                                <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($chapter['chapter_name']); ?>" 
                                   class="txt_oneline">
                                    Chương <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                </a>
                            </div>
                            <div class="w110 text-right">
                                <span class="cl99"><em><?php echo timeAgo($chapter['updated_at'] ?? ($comic['updatedAt'] ?? '')); ?></em></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="item_chap">
                        <div class="wc110">
                            <p>Chưa có chương nào được hiển thị.</p>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <section class="mt-4">
        <div class="d-flex">
            <span class="title_comment mr-auto"><i class="bi bi-chat-left-dots-fill"></i> Bình Luận (<span class="chapter-count"><?php echo $total_comments; ?></span>)</span>
        </div>
        <input type="hidden" id="comic_id" value="<?php echo $comic_db_id; ?>" />
        <div class="comment-container" id="comment_list">
            <div class="comments-container">
                <div class="form-comment main_comment">
                    <div class="message-content">
                        <div class="comment-placeholder" onclick="openComment();">Nội Dung Bình Luận</div>
                        <div class="mess-input hidden">
                            <textarea class="textarea" placeholder="Nội dung bình luận" id="content_comment"></textarea>
                            <div class="control-comment">
                                <button type="submit" class="button is-info is-rounded submit_comment" onclick="sendComment(0);">Gửi</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="list-comment">
                    <?php foreach ($comments as $comment): ?>
                        <article class="info-comment child_<?php echo $comment['id']; ?> parent_<?php echo $comment['parent_id'] ?: 0; ?> comment-main-level">
                            <div class="avartar-comment">
                                <img src="<?php echo htmlspecialchars($comment['avatar'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($comment['username']); ?>">
                            </div>
                            <div class="outsite-comment comment-main-level">
                                <div class="header-comment">
                                    <div class="info-user-comment">
                                        <div>
                                            <strong class="level name_<?php echo $comment['level']; ?>"><?php echo htmlspecialchars($comment['username']); ?></strong>
                                            <span title="Cấp <?php echo $comment['level']; ?>" class="title-user-comment title-member level_<?php echo $comment['level']; ?>">Cấp <?php echo $comment['level']; ?></span>
                                        </div>
                                    </div>
                                    <span class="time"><i class="fa fa-clock"></i> <?php echo timeAgo($comment['created_at']); ?></span>
                                </div>
                                <div class="content-comment"><?php echo htmlspecialchars($comment['content']); ?></div>
                                <div class="action-comment">
                                    <div>
                                        <span class="reply-comment" onclick="addReply(<?php echo $comment['id']; ?>)"><i class="bi bi-chat-dots"></i> Trả lời</span>
                                    </div>
                                    <div>
                                        <span class="like-comment" data-id="<?php echo $comment['id']; ?>"><i class="bi bi-hand-thumbs-up"></i> <span class="total-like-comment"><?php echo $comment['likes']; ?></span></span>
                                        <span class="break-line">| </span>
                                        <span class="dislike-comment" data-id="<?php echo $comment['id']; ?>"><i class="bi bi-hand-thumbs-down"></i> <span class="total-dislike-comment"><?php echo $comment['dislikes']; ?></span></span>
                                    </div>
                                </div>
                                <?php if (!empty($child_comments[$comment['id']])): ?>
                                    <div class="child-comments">
                                        <?php foreach ($child_comments[$comment['id']] as $child): ?>
                                            <article class="info-comment child_<?php echo $child['id']; ?> parent_<?php echo $child['parent_id']; ?>">
                                                <div class="avartar-comment">
                                                    <img src="<?php echo htmlspecialchars($child['avatar'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($child['username']); ?>">
                                                </div>
                                                <div class="outsite-comment">
                                                    <div class="header-comment">
                                                        <div class="info-user-comment">
                                                            <div>
                                                                <strong class="level name_<?php echo $child['level']; ?>"><?php echo htmlspecialchars($child['username']); ?></strong>
                                                                <span title="Cấp <?php echo $child['level']; ?>" class="title-user-comment title-member level_<?php echo $child['level']; ?>">Cấp <?php echo $child['level']; ?></span>
                                                            </div>
                                                        </div>
                                                        <span class="time"><i class="fa fa-clock"></i> <?php echo timeAgo($child['created_at']); ?></span>
                                                    </div>
                                                    <div class="content-comment"><?php echo htmlspecialchars($child['content']); ?></div>
                                                    <div class="action-comment">
                                                        <div>
                                                            <span class="like-comment" data-id="<?php echo $child['id']; ?>">
                                                                <i class="bi bi-hand-thumbs-up"></i>
                                                                <span class="total-like-comment"><?php echo $child['likes']; ?></span>
                                                            </span>
                                                            <span class="break-line">| </span>
                                                            <span class="dislike-comment" data-id="<?php echo $child['id']; ?>"><i class="bi bi-hand-thumbs-down"></i> <span class="total-dislike-comment"><?php echo $child['dislikes']; ?></span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($total_comment_pages > 1): ?>
                    <div class="pagination mt-4">
                        <?php
                        if ($current_page > 1) {
                            echo '<a class="page-item" href="' . COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($slug) . '&comment_page=1"><span aria-hidden="true">«</span></a>';
                        }
                        if ($current_page > 1) {
                            echo '<a class="page-item" href="' . COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($slug) . '&comment_page=' . ($current_page - 1) . '"><span aria-hidden="true">‹</span></a>';
                        }
                        $max_pages = 5;
                        $half = floor($max_pages / 2);
                        $start_page = max(1, $current_page - $half);
                        $end_page = min($total_comment_pages, $start_page + $max_pages - 1);
                        if ($end_page - $start_page + 1 < $max_pages) {
                            $start_page = max(1, $end_page - $max_pages + 1);
                        }
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<a href="javascript:void(0)" class="page-item active">' . $i . '</a>';
                            } else {
                                echo '<a class="page-item" href="' . COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($slug) . '&comment_page=' . $i . '">' . $i . '</a>';
                            }
                        }
                        if ($current_page < $total_comment_pages) {
                            echo '<a class="page-item" href="' . COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($slug) . '&comment_page=' . ($current_page + 1) . '"><span aria-hidden="true">›</span></a>';
                        }
                        if ($current_page < $total_comment_pages) {
                            echo '<a class="page-item" href="' . COMIC_DETAIL_URL . '?slug=' . htmlspecialchars($slug) . '&comment_page=' . $total_comment_pages . '"><span aria-hidden="true">»</span></a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script type="text/javascript">
        var urlFollow = '<?php echo BASE_URL; ?>includes/handlers/follow_handler.php';
        var urlComment = '<?php echo BASE_URL; ?>includes/handlers/comment_handler.php';
        var urlRating = '<?php echo BASE_URL; ?>frontend/user/rating';
        var baseUrl = '<?php echo BASE_URL; ?>';

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
            $("#rating").rateYo({
                rating: 2.6,
                halfStar: true,
                onSet: function(rating) {
                    $.ajax({
                        url: urlRating,
                        type: 'POST',
                        data: {
                            rating: rating,
                            comic_id: <?php echo $comic_db_id; ?>
                        },
                        dataType: 'json',
                        success: function(response) {
                            showToast(response.message, response.success);
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            showToast('Lỗi kết nối máy chủ. Vui lòng thử lại.', false);
                        }
                    });
                }
            });

            $('#follow-button').on('click', function() {
                var comic_id = $(this).data('id');
                $.ajax({
                    url: urlFollow,
                    type: 'POST',
                    data: {
                        action: 'toggle_follow',
                        comic_id: comic_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#follow-count').text(response.follow_count);
                            var button = $('#follow-button');
                            if (response.is_following) {
                                button.addClass('following').find('span').text('Hủy Theo Dõi');
                            } else {
                                button.removeClass('following').find('span').text('Theo Dõi');
                            }
                            showToast(response.message, true);
                        } else {
                            showToast(response.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        showToast('Lỗi kết nối máy chủ. Vui lòng thử lại.', false);
                    }
                });
            });
        });

        function openComment() {
            $('.comment-placeholder').addClass('hidden');
            $('.mess-input').removeClass('hidden');
            $('#content_comment').focus();
        }

        function addReply(comment_id) {
            if (!Number.isInteger(comment_id) || comment_id <= 0) {
                console.error('Invalid comment_id:', comment_id);
                showToast('Bình luận không hợp lệ.', false);
                return;
            }
            var reply_form = `
                <div class="form-comment reply-comment reply_${comment_id}">
                    <div class="message-content">
                        <textarea class="textarea" placeholder="Trả lời bình luận" id="reply_content_${comment_id}"></textarea>
                        <div class="control-comment">
                            <button class="button is-info is-rounded submit_comment" onclick="sendComment(${comment_id});">Gửi</button>
                            <button class="button is-light is-rounded" onclick="$('.reply_${comment_id}').remove();">Hủy</button>
                        </div>
                    </div>
                </div>`;
            $(`.child_${comment_id}`).append(reply_form);
        }

        function sendComment(parent_id) {
            var content = parent_id === 0 ? $('#content_comment').val() : $('#reply_content_' + parent_id).val();
            var comic_id = $('#comic_id').val();

            if (!content.trim()) {
                showToast('Nội dung bình luận không được để trống.', false);
                return;
            }
            if (parent_id !== 0 && (!Number.isInteger(parent_id) || parent_id <= 0)) {
                showToast('Bình luận cha không hợp lệ.', false);
                return;
            }

            var data = {
                action: 'post_comment',
                comic_id: comic_id,
                content: content
            };
            if (parent_id !== 0) {
                data.parent_id = parent_id;
            }

            $.ajax({
                url: urlComment,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var comment = response.comment;
                        var comment_html = `
                            <article class="info-comment child_${comment.id} parent_${comment.parent_id || 0} ${parent_id === 0 ? 'comment-main-level' : ''}">
                                <div class="avartar-comment">
                                    <img src="${comment.avatar || 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'}" alt="${comment.username}">
                                </div>
                                <div class="outsite-comment ${parent_id === 0 ? 'comment-main-level' : ''}">
                                    <div class="header-comment">
                                        <div class="info-user-comment">
                                            <div>
                                                <strong class="level name_${comment.level}">${comment.username}</strong>
                                                <span title="Cấp ${comment.level}" class="title-user-comment title-member level_${comment.level}">Cấp ${comment.level}</span>
                                            </div>
                                        </div>
                                        <span class="time"><i class="fa fa-clock"></i> Vừa xong</span>
                                    </div>
                                    <div class="content-comment">${comment.content}</div>
                                    <div class="action-comment">
                                        <div>
                                            <span class="reply-comment" onclick="addReply(${comment.id})"><i class="bi bi-chat-dots"></i> Trả lời</span>
                                        </div>
                                        <div>
                                            <span class="like-comment" data-id="${comment.id}"><i class="bi bi-hand-thumbs-up"></i> <span class="total-like-comment">0</span></span>
                                            <span class="break-line">| </span>
                                            <span class="dislike-comment" data-id="${comment.id}"><i class="bi bi-hand-thumbs-down"></i> <span class="total-dislike-comment">0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </article>`;
                        if (parent_id === 0) {
                            $('.list-comment').prepend(comment_html);
                            $('.comment-placeholder').removeClass('hidden');
                            $('.mess-input').addClass('hidden');
                            $('#content_comment').val('');
                        } else {
                            $(`.child_${parent_id} .child-comments`).length ? 
                                $(`.child_${parent_id} .child-comments`).append(comment_html) : 
                                $(`.child_${parent_id}`).append('<div class="child-comments">' + comment_html + '</div>');
                            $(`.reply_${parent_id}`).remove();
                        }
                        $('.comment-count').text(response.total_comments);
                        showToast(response.message, true);
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    showToast('Lỗi kết nối máy chủ. Vui lòng thử lại.', false);
                }
            });
        }
    </script>
    <script src="assets/js/main.js" type="text/javascript"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                                    console.warn(`Retrying image load: ${src} (${retries} attempts left)`);
                                    retries--;
                                    setTimeout(tryLoadImage, 1000);
                                } else {
                                    console.error(`Failed to load image: ${src}`);
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
        .thumbblock {
            position: relative;
            width: 230px;
            height: 300px;
            overflow: hidden;
        }
        .thumbblock img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: #e0e0e0;
            transition: opacity 0.3s ease;
        }
        .thumbblock img.lazy-image {
            opacity: 0.6;
        }
        .thumbblock img.loaded {
            opacity: 1;
        }
        .subscribe_button.following {
            background-color: #dc3545;
        }
        .subscribe_button.following:hover {
            background-color: #c82333;
        }
        .comment-container {
            margin-top: 20px;
        }
        .form-comment {
            margin-bottom: 20px;
        }
        .comment-placeholder {
            padding: 10px;
            background: #f0f0f0;
            cursor: text;
            border-radius: 5px;
        }
        .mess-input.hidden {
            display: none;
        }
        .textarea {
            width: 100%;
            min-height: 100px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .child-comments {
            margin-left: 40px;
            margin-top: 10px;
        }
        .pagination .page-item {
            margin: 0 5px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .pagination .page-item.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .toastify {
            font-family: Arial, sans-serif;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</div>

<a id="back-to-top">
    <i class="bi bi-chevron-double-up"></i>
</a>

<?php require_once __DIR__ . '/includes/layouts/footer.php'; ?>
<script src="assest/js/main.js" type="text/javascript"></script>
</body>
</html>