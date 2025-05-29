<?php
require_once __DIR__ . '/config/routes.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/api/otruyen.php';
require_once __DIR__ . '/includes/layouts/header.php';

// Hàm tính thời gian từ ngày cập nhật
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

// Lấy slug và chapter từ URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$chapter_number = isset($_GET['chapter']) ? trim($_GET['chapter']) : '';

if (empty($slug) || empty($chapter_number)) {
    header("Location: " . BASE_URL);
    exit;
}

// Khởi tạo API
$api = new OTruyenAPI();
$comic = null;
$comic_db_id = null;
$comic_id = null;
$chapters = [];
$chapter_data = null;
$chapter_images = [];
$chapter_id = null;
$api_images = [];
$is_api_source = false;

// Tìm truyện trong CSDL
try {
    $stmt = $conn->prepare("
        SELECT id, comic_id, name, slug, thumb_url, updated_at
        FROM comics
        WHERE slug = ?
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($comic_row = $result->fetch_assoc()) {
        $comic = $comic_row;
        $comic_db_id = $comic_row['id'];
        $comic_id = $comic_row['comic_id'];
        $comic_name = htmlspecialchars($comic_row['name']);
        $comic_slug = $comic_row['slug'];
        $updated_at = $comic_row['updated_at'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Lỗi khi lấy truyện từ CSDL: " . $e->getMessage());
}

// Nếu không tìm thấy trong CSDL, lấy từ API
if (!$comic) {
    $is_api_source = true;
    $comic_data = $api->getComicDetails($slug);
    if (isset($comic_data['data']['item']) && !empty($comic_data['data']['item'])) {
        $comic = $comic_data['data']['item'];
        $comic_id = $comic['_id'];
        $comic_name = htmlspecialchars($comic['name']);
        $comic_slug = $comic['slug'];
        $updated_at = $comic['updatedAt'];

        // Đồng bộ truyện vào CSDL
        try {
            $stmt = $conn->prepare("SELECT id FROM comics WHERE comic_id = ? OR slug = ?");
            $stmt->bind_param("ss", $comic_id, $comic_slug);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$result->fetch_assoc()) {
                $stmt = $conn->prepare("
                    INSERT INTO comics (comic_id, name, slug, thumb_url, updated_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $thumb_file = $comic['thumb_url'] ?? '';
                $thumb_url = $thumb_file && !filter_var($thumb_file, FILTER_VALIDATE_URL)
                    ? 'https://img.otruyenapi.com/uploads/comics/' . $thumb_file
                    : ($thumb_file ?: 'https://st.truyengg.net/template/frontend/img/placeholder.jpg');
                $updated_at_db = date('Y-m-d H:i:s', strtotime($updated_at));
                $stmt->bind_param("sssss", $comic_id, $comic_name, $comic_slug, $thumb_url, $updated_at_db);
                $stmt->execute();
                $comic_db_id = $conn->insert_id;
                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT id FROM comics WHERE comic_id = ? OR slug = ?");
                $stmt->bind_param("ss", $comic_id, $comic_slug);
                $stmt->execute();
                $result = $stmt->get_result();
                $comic_db_id = $result->fetch_assoc()['id'];
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Lỗi khi đồng bộ truyện: " . $e->getMessage());
        }
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="vi" translate="no">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="google" content="notranslate">
            <title>Lỗi - Không tìm thấy truyện</title>
            <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" type="text/css" rel="stylesheet">
            <style>
                .error-container { text-align: center; padding: 50px; background-color: #2c2f33; color: #ffc107; border: 2px solid #007bff; border-radius: 8px; margin: 20px auto; max-width: 600px; font-weight: 600; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3); }
                .error-container p { font-size: 18px; margin-bottom: 20px; }
                .error-container a { color: #ffffff; background-color: #007bff; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
                .error-container a:hover { background-color: #0056b3; }
            </style>
        </head>
        <body class="dark-style">
            <div class="error-container">
                <p>Không tìm thấy truyện với slug: '<?php echo htmlspecialchars($slug); ?>'.</p>
                <p><a href="<?php echo BASE_URL; ?>">Quay lại trang chủ</a></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Lấy danh sách chương
if ($comic_db_id && !$is_api_source) {
    try {
        $stmt = $conn->prepare("
            SELECT id, chapter_name, chapter_title, chapter_api_data
            FROM chapters
            WHERE comic_id = ? AND chapter_name = ?
        ");
        $stmt->bind_param("is", $comic_db_id, $chapter_number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $chapter_data = [
                'chapter_name' => $row['chapter_name'],
                'chapter_title' => $row['chapter_title'],
                'chapter_api_data' => $row['chapter_api_data']
            ];
            $chapter_id = $row['id'];
            $chapters[] = $chapter_data;
        }
        $stmt->close();

        // Lấy các chương khác
        $stmt = $conn->prepare("
            SELECT chapter_name, chapter_title, chapter_api_data
            FROM chapters
            WHERE comic_id = ? AND chapter_name != ?
            ORDER BY CAST(chapter_name AS UNSIGNED) ASC
        ");
        $stmt->bind_param("is", $comic_db_id, $chapter_number);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $chapters[] = [
                'chapter_name' => $row['chapter_name'],
                'chapter_title' => $row['chapter_title'],
                'chapter_api_data' => $row['chapter_api_data']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy danh sách chương từ CSDL: " . $e->getMessage());
    }
}

if (empty($chapters) || !$chapter_data) {
    $is_api_source = true;
    $chapters = isset($comic['chapters'][0]['server_data']) ? $comic['chapters'][0]['server_data'] : [];
    foreach ($chapters as $chap) {
        if ($chap['chapter_name'] === $chapter_number) {
            $chapter_data = $chap;
            break;
        }
    }
}

// Đồng bộ chương vào CSDL
if ($chapter_data && $comic_db_id) {
    try {
        $stmt = $conn->prepare("SELECT id FROM chapters WHERE comic_id = ? AND chapter_name = ?");
        $stmt->bind_param("is", $comic_db_id, $chapter_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $chapter_row = $result->fetch_assoc();
        $stmt->close();

        if (!$chapter_row) {
            $stmt = $conn->prepare("
                INSERT INTO chapters (comic_id, chapter_name, chapter_title, chapter_api_data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $chapter_title = $chapter_data['chapter_title'] ?? '';
            $chapter_api_data = $chapter_data['chapter_api_data'] ?? '';
            $stmt->bind_param("isss", $comic_db_id, $chapter_number, $chapter_title, $chapter_api_data);
            $stmt->execute();
            $chapter_id = $conn->insert_id;
            $stmt->close();
        } else {
            $chapter_id = $chapter_row['id'];
        }
    } catch (Exception $e) {
        error_log("Lỗi khi đồng bộ chương: " . $e->getMessage());
    }
}

if (!$chapter_data) {
    ?>
    <!DOCTYPE html>
    <html lang="vi" translate="no">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="google" content="notranslate">
        <title>Lỗi - Không tìm thấy chương</title>
        <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" type="text/css" rel="stylesheet">
        <style>
            .error-container { text-align: center; padding: 50px; background-color: #2c2f33; color: #ffc107; border: 2px solid #007bff; border-radius: 8px; margin: 20px auto; max-width: 600px; font-weight: 600; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3); }
            .error-container p { font-size: 18px; margin-bottom: 20px; }
            .error-container a { color: #ffffff; background-color: #007bff; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
            .error-container a:hover { background-color: #0056b3; }
        </style>
    </head>
    <body class="dark-style">
        <div class="error-container">
            <p>Không tìm thấy chương '<?php echo htmlspecialchars($chapter_number); ?>' của truyện '<?php echo $comic_name; ?>'.</p>
            <p><a href="<?php echo BASE_URL; ?>">Quay lại trang chủ</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Lấy hình ảnh từ CSDL
if ($chapter_id) {
    try {
        $stmt = $conn->prepare("
            SELECT image_page, original_url, image_order
            FROM chapter_images
            WHERE chapter_id = ?
            ORDER BY image_order ASC
        ");
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $chapter_images[] = [
                'image_page' => $row['image_page'],
                'original_url' => $row['original_url'],
                'image_order' => $row['image_order']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy hình ảnh từ CSDL: " . $e->getMessage());
    }
}

// Lấy hình ảnh từ API
if ($chapter_data && !empty($chapter_data['chapter_api_data']) && empty($chapter_images)) {
    try {
        $chapter_details = $api->getChapterData($chapter_data['chapter_api_data']);
        if (isset($chapter_details['data']['item']['chapter_image']) && is_array($chapter_details['data']['item']['chapter_image'])) {
            $domain_cdn = $chapter_details['data']['domain_cdn'] ?? 'https://sv1.otruyencdn.com';
            $chapter_path = $chapter_details['data']['item']['chapter_path'] ?? '';
            $image_order = 1;

            foreach ($chapter_details['data']['item']['chapter_image'] as $image) {
                $image_url = $domain_cdn . '/' . $chapter_path . '/' . $image['image_file'];
                $api_images[] = [
                    'image_page' => $image_url,
                    'original_url' => $image_url,
                    'image_order' => $image_order
                ];
                $image_order++;
            }
        }
    } catch (Exception $e) {
        error_log("Lỗi khi lấy hình ảnh từ API: " . $e->getMessage());
    }
}

// Lưu lịch sử đọc
if (isset($_SESSION['user_id']) && $chapter_data) {
    $user_id = (int)$_SESSION['user_id'];
    try {
        $stmt = $conn->prepare("
            INSERT INTO reading_history (user_id, comic_id, slug, name, thumb_url, chapter_name, last_read_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                slug = VALUES(slug),
                name = VALUES(name),
                thumb_url = VALUES(thumb_url),
                chapter_name = VALUES(chapter_name),
                last_read_at = NOW()
        ");
        $thumb_url = $comic['thumb_url'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg';
        $stmt->bind_param("isssss", $user_id, $comic_db_id, $comic_slug, $comic_name, $thumb_url, $chapter_number);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Lỗi khi lưu lịch sử đọc: " . $e->getMessage());
    }
}

// Kiểm tra trạng thái theo dõi
$is_following = false;
if (isset($_SESSION['user_id']) && $comic_db_id) {
    $user_id = (int)$_SESSION['user_id'];
    try {
        $stmt = $conn->prepare("SELECT id FROM user_follows WHERE user_id = ? AND comic_id = ?");
        $stmt->bind_param("ii", $user_id, $comic_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_following = $result->num_rows > 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Lỗi khi kiểm tra theo dõi: " . $e->getMessage());
    }
}

// Tìm chapter trước và sau
$prev_chapter = null;
$next_chapter = null;
$chapter_index = array_search($chapter_data, $chapters);
if ($chapter_index !== false) {
    if ($chapter_index > 0) {
        $prev_chapter = $chapters[$chapter_index - 1];
    }
    if ($chapter_index < count($chapters) - 1) {
        $next_chapter = $chapters[$chapter_index + 1];
    }
}

// Quyết định file hiển thị dựa trên nguồn hình ảnh
$display_file = !empty($chapter_images) ? 'layouts/chapter-data.php' : 'layouts/chapter-api.php';
?>

<!DOCTYPE html>
<html lang="vi" translate="no">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="google" content="notranslate">
    <title><?php echo $comic_name; ?> - Chương <?php echo htmlspecialchars($chapter_number); ?> - TruyenGG</title>
    <link href="https://st.truyengg.net/template/frontend/styles/style.css?v=1.9.4" type="text/css" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/styles/dark_style.css?v=1.3.4" type="text/css" rel="stylesheet">
    <link href="https://st.truyengg.net/template/frontend/icon/css/font-awesome.min.css?v=1.2.3" type="text/css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=6.0, user-scalable=yes" />
    <style>
        .out-select-chap .chapter-select { padding: 6px; border-radius: 4px; border: 1px solid #ccc; background-color: #fff; font-size: 14px; min-width: 120px; cursor: pointer; color: #333; vertical-align: middle; }
        .out-select-chap .chapter-select:focus { outline: none; border-color: #007bff; }
        .out-select-chap { display: flex; align-items: center; gap: 5px; }
        .out-select-chap .pre_chap, .out-select-chap .next_chap { font-size: 24px; color: #007bff; text-decoration: none; }
        .out-select-chap .pre_chap.disable, .out-select-chap .next_chap.disable { color: #6c757d; cursor: not-allowed; }
        .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 1000; justify-content: center; align-items: center; }
        .box_content_reg { background: #fff; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; position: relative; }
        .close { position: absolute; top: 10px; right: 10px; cursor: pointer; }
        .popup_content .title { font-size: 20px; margin-bottom: 15px; text-align: center; }
        .report_error { list-style: none; padding: 0; }
        .report_error li { margin-bottom: 15px; }
        .report_error select, .report_error textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .report_error textarea { height: 100px; resize: vertical; }
        .note { font-size: 12px; color: #666; margin-bottom: 5px; }
        .yes_no { text-align: center; }
        .yes_no button, .yes_no div.submit_error { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; display: inline-block; }
        .yes_no button:hover, .yes_no div.submit_error:hover { background: #0056b3; }
        .follow-active { color: #28a745 !important; }
        .content_detail img { display: block; margin: 0 auto; max-width: 100%; height: auto; }
        .error-container { text-align: center; padding: 20px; background-color: #2c2f33; color: #ffc107; border: 2px solid #007bff; border-radius: 8px; margin: 20px auto; max-width: 600px; }
        .error-container a { color: #ffffff; background-color: #007bff; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .error-container a:hover { background-color: #0056b3; }
        .button-server .btn.active { background-color: #0056b3 !important; }
    </style>
</head>
<body class="dark-style">
    <div class="background-black container-background-manga">
        <div class="container">
            <div class="box">
                <ol class="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">
                    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="breadcrumb-item">
                        <a itemprop="item" href="<?php echo BASE_URL; ?>">
                            <span itemprop="name">Trang Chủ</span>
                        </a>
                        <meta itemprop="position" content="1" />
                    </li>
                    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="breadcrumb-item">
                        <a itemprop="item" href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>">
                            <span itemprop="name"><?php echo $comic_name; ?></span>
                        </a>
                        <meta itemprop="position" content="2" />
                    </li>
                    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="breadcrumb-item active">
                        <span itemprop="name">Chương <?php echo htmlspecialchars($chapter_number); ?></span>
                        <meta itemprop="position" content="3" />
                    </li>
                </ol>
                <div class="title-detail">
                    <h1><a href="<?php echo COMIC_DETAIL_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>"><?php echo $comic_name; ?></a> - Chương <?php echo htmlspecialchars($chapter_number); ?></h1>
                    <time datetime="<?php echo $updated_at; ?>">(Cập nhật lúc: <?php echo timeAgo($updated_at); ?>)</time>
                </div>
                <div class="chapter-control">
                    <div class="control-button-server">
                        <span class="note-server">Nếu không xem được truyện vui lòng đổi "SERVER HÌNH" bên dưới</span>
                        <div class="button-server">
                            <a rel="nofollow" href="javascript:changeServer(1)" class="loadchapter btn btn-success server_1 active" data-server="1">Server 1 (Imgur)</a>
                            <a rel="nofollow" href="javascript:changeServer(2)" class="loadchapter btn btn-primary server_2" data-server="2">Server 2 (OTruyen)</a>
                        </div>
                    </div>
                    <div class="alert alert-info mrt10">
                        <i class="fa fa-info-circle"></i> <em>Sử dụng mũi tên trái (←) hoặc phải (→) để chuyển chương</em>
                    </div>
                    <div class="button-next-prev">
                        <?php if ($prev_chapter): ?>
                            <a class="btn btn-info go-btn prev text-white" href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($prev_chapter['chapter_name']); ?>">
                                <i class="bi bi-arrow-left-short"></i> Chương Trước
                            </a>
                        <?php else: ?>
                            <a class="btn btn-info go-btn prev text-white disabled" href="javascript:void(0)" onclick="alert('Đây là chương đầu tiên!')">
                                <i class="bi bi-arrow-left-short"></i> Chương Trước
                            </a>
                        <?php endif; ?>
                        <?php if ($next_chapter): ?>
                            <a class="btn btn-info go-btn next text-white" href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($next_chapter['chapter_name']); ?>">
                                Chương Sau <i class="bi bi-arrow-right-short"></i>
                            </a>
                        <?php else: ?>
                            <a class="btn btn-info go-btn next text-white disabled" href="javascript:void(0)" onclick="alert('Hết chương rồi bạn ơi!')">
                                Chương Sau <i class="bi bi-arrow-right-short"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="content_detail content_detail_manga">
                <div id="ad_info_top">
                    <span class="text_ads"><span class="txt_qc">Quảng cáo</span></span>
                </div>
                <div id="image-container">
                    <?php require_once __DIR__ . '/includes/' . $display_file; ?>
                </div>
            </div>
        </div>
        <section class="footer_detail">
            <div class="control-detail align-items-center">
                <div class="out-item-control">
                    <a href="<?php echo BASE_URL; ?>" class="icon_home" title="Home"><i class="bi bi-house-fill"></i> <span>Trang Chủ</span></a>
                    <a href="javascript:void(0)" class="icon_server" title="Server Hình"><i class="bi bi-arrow-counterclockwise"></i> <span>Server <span id="current-server-num">1</span></span></a>
                </div>
                <div class="out-select-chap">
                    <?php if ($prev_chapter): ?>
                        <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($prev_chapter['chapter_name']); ?>" class="pre_chap" title="Chương Trước"><i class="bi bi-arrow-left-square-fill"></i></a>
                    <?php else: ?>
                        <a href="javascript:void(0);" class="pre_chap disable" title="Chương Trước"><i class="bi bi-arrow-left-square-fill"></i></a>
                    <?php endif; ?>
                    <div class="select-chap">
                        <select class="chapter-select" onchange="location = this.value;">
                            <option value="">Chọn chương</option>
                            <?php foreach (array_reverse($chapters) as $chapter): ?>
                                <option value="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($chapter['chapter_name']); ?>" 
                                        <?php echo $chapter['chapter_name'] === $chapter_number ? 'selected' : ''; ?>>
                                    Chương <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($next_chapter): ?>
                        <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($next_chapter['chapter_name']); ?>" class="next_chap" title="Chương Sau"><i class="bi bi-arrow-right-square-fill"></i></a>
                    <?php else: ?>
                        <a href="javascript:void(0);" class="next_chap disable" title="Chương Sau" onclick="alert('Hết chương rồi bạn ơi!')"><i class="bi bi-arrow-right-square-fill"></i></a>
                    <?php endif; ?>
                </div>
                <div class="out-item-control">
                    <a href="javascript:popup('report')" class="icon_error" title="Báo Lỗi"><i class="bi bi-exclamation-diamond-fill"></i> <span>Báo Lỗi</span></a>
                    <a href="javascript:void(0);" class="icon_follow subscribeBook <?php echo $is_following ? 'follow-active' : ''; ?>" 
                       data-id="<?php echo $comic_db_id; ?>" 
                       data-page="detail" 
                       title="<?php echo $is_following ? 'Hủy Theo Dõi' : 'Theo Dõi'; ?>">
                        <i class="bi <?php echo $is_following ? 'bi-bookmark-check-fill' : 'bi-bookmark-plus-fill'; ?>"></i> 
                        <span><?php echo $is_following ? 'Hủy Theo Dõi' : 'Theo Dõi'; ?></span>
                    </a>
                </div>
            </div>
        </section>
        <div class="container box-comment">
            <div class="button-next-prev">
                <?php if ($prev_chapter): ?>
                    <a class="btn btn-info go-btn prev text-white" href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($prev_chapter['chapter_name']); ?>">
                        <i class="bi bi-arrow-left-short"></i> Chương Trước
                    </a>
                <?php else: ?>
                    <a class="btn btn-info go-btn prev text-white disabled" href="javascript:void(0)" onclick="alert('Đây là chương đầu tiên!')">
                        <i class="bi bi-arrow-left-short"></i> Chương Trước
                    </a>
                <?php endif; ?>
                <?php if ($next_chapter): ?>
                    <a class="btn btn-info go-btn next text-white" href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($slug); ?>&chapter=<?php echo htmlspecialchars($next_chapter['chapter_name']); ?>">
                        Chương Sau <i class="bi bi-arrow-right-short"></i>
                    </a>
                <?php else: ?>
                    <a class="btn btn-info go-btn next text-white disabled" href="javascript:void(0)" onclick="alert('Hết chương rồi bạn ơi!')">
                        Chương Sau <i class="bi bi-arrow-right-short"></i>
                    </a>
                <?php endif; ?>
            </div>
            <section class="mt-4">
                <div class="d-flex"><span class="title_comment mr-auto">Bình Luận (<span class="comment-count">0</span>)</span></div>
                <input type="hidden" id="book_id" value="<?php echo htmlspecialchars($comic_db_id); ?>" />
                <input type="hidden" id="total_page" value="1" />
                <input type="hidden" id="current_page" value="1" />
                <input type="hidden" id="id_textarea" value="" />
                <input type="hidden" id="parent_id" value="" />
                <input type="hidden" id="chapter_name" value="<?php echo htmlspecialchars($chapter_number); ?>" />
                <input type="hidden" id="chapter_id" value="<?php echo htmlspecialchars($chapter_id); ?>" />
                <input type="hidden" id="slug" value="<?php echo htmlspecialchars($slug); ?>" />
                <input type="hidden" id="type_book" value="1" />
                <div class="comment-container" id="comment_list">
                    <div class="comments-container">
                        <div class="form-comment main_comment">
                            <div class="message-content">
                                <div class="comment-placeholder" onclick="openComment();">Viết bình luận...</div>
                                <div class="mess-input hidden">
                                    <textarea class="textarea" placeholder="Nội dung" id="content_comment"></textarea>
                                    <div class="control-comment">
                                        <button type="submit" class="button is-info is-rounded submit_comment" onclick="sendComment(0);">Gửi</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="list-comment">
                            <!-- Bình luận sẽ được tải động -->
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <!-- Report Error Popup -->
    <div class="popup-overlay" id="report_popup">
        <div class="box_content_reg">
            <div onclick="popup('report')" class="close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"></path>
                </svg>
            </div>
            <div id="popup_content">
                <div class="popup_content popup_center_important">
                    <div class="title"><span class="title_report">Báo Lỗi</span></div>
                    <ul class="report_error">
                        <li>
                            <select id="report_error_title" class="txt_cm" onchange="$('.note').text($(this).children('option:selected').attr('note'));">
                                <option note="Chọn lỗi bạn gặp phải" value="0">--Chọn loại lỗi--</option>
                                <option note="Ảnh tải lâu hay lỗi toàn bộ ảnh?" value="1">Ảnh lỗi, không thấy ảnh</option>
                                <option note="Trùng với chương mấy?" value="2">Chương bị trùng</option>
                                <option note="Chưa dịch toàn bộ hay vài trang?" value="3">Chương chưa dịch</option>
                                <option note="Chương không phải truyện này?" value="4">Up sai truyện</option>
                                <option note="Miêu tả vấn đề bạn gặp phải!" value="-1">Lỗi khác</option>
                            </select>
                        </li>
                        <li>
                            <div class="note">Chọn lỗi bạn gặp phải</div>
                            <textarea id="report_error_text" placeholder="Miêu tả lỗi..."></textarea>
                        </li>
                    </ul>
                    <div class="yes_no">
                        <button type="button" class="yes module_login submit_error" id="submit_error">Gửi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
    <?php require_once __DIR__ . '/includes/layouts/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script>
        // Dữ liệu hình ảnh từ PHP
        const dbImages = <?php echo json_encode($chapter_images); ?>;
        const apiImages = <?php echo json_encode($api_images); ?>;
        let currentServer = <?php echo !empty($chapter_images) ? '1' : '2'; ?>;

        // Hàm thay đổi server hình ảnh
        function changeServer(serverId) {
            currentServer = serverId;
            const $container = $('#image-container');
            $container.empty();

            // Cập nhật trạng thái nút server
            $('.loadchapter').removeClass('active');
            $(`.server_${serverId}`).addClass('active');

            // Chọn danh sách hình ảnh
            let images = serverId === 1 ? dbImages : apiImages;

            if (images.length > 0) {
                images.forEach((image, index) => {
                    const imgHtml = `
                        <img class="lazy" src="${image.image_page}" 
                             alt="<?php echo $comic_name; ?> Chương <?php echo htmlspecialchars($chapter_number); ?> - Trang ${index + 1}" 
                             onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';" />
                    `;
                    $container.append(imgHtml);
                });
            } else {
                const errorHtml = `
                    <div class="error-container">
                        <p>Không tìm thấy hình ảnh cho chương này trên Server ${serverId}! Vui lòng thử server khác hoặc báo lỗi.</p>
                        <p><a href="<?php echo BASE_URL; ?>">Quay lại trang chủ</a></p>
                    </div>
                `;
                $container.append(errorHtml);
            }

            // Cập nhật số server hiện tại
            $('#current-server-num').text(serverId);
        }

        // Khởi tạo server
        $(document).ready(function() {
            changeServer(currentServer);
        });

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

        // Toggle report popup
        function popup(type) {
            if (type === 'report') {
                $('#report_popup').toggle();
            }
        }

        // Reset report form
        function reset() {
            $('#report_error_title').val('0');
            $('#report_error_text').val('');
            $('.note').text('Chọn lỗi bạn gặp phải');
            $('#report_popup').hide();
        }

        // Handle follow button click
        $('.subscribeBook').on('click', function() {
            const $button = $(this);
            const comic_id = $button.data('id');
            console.log('Follow clicked, comic_id:', comic_id);

            $.ajax({
                url: '<?php echo BASE_URL; ?>/includes/handlers/follow_handler.php',
                type: 'POST',
                data: {
                    action: 'toggle_follow',
                    comic_id: comic_id
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Follow response:', response);
                    if (response.success) {
                        const is_following = response.is_following;
                        $button.toggleClass('follow-active', is_following);
                        $button.find('i').removeClass('bi-bookmark-plus-fill bi-bookmark-check-fill')
                               .addClass(is_following ? 'bi-bookmark-check-fill' : 'bi-bookmark-plus-fill');
                        $button.find('span').text(is_following ? 'Hủy Theo Dõi' : 'Theo Dõi');
                        $button.attr('title', is_following ? 'Hủy Theo Dõi' : 'Theo Dõi');
                        showToast(response.message, true);
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Follow AJAX error:', error, xhr.responseText);
                    showToast('Lỗi khi xử lý yêu cầu: ' + error, false);
                }
            });
        });

        // Handle report error submission
        $('#submit_error').on('click', function() {
            const error_type = $('#report_error_title').val();
            const error_description = $('#report_error_text').val().trim();
            console.log('Report submitted:', { error_type, error_description });

            if (error_type === '0') {
                showToast('Vui lòng chọn loại lỗi.', false);
                return;
            }
            if (error_description.length < 10) {
                showToast('Mô tả lỗi phải có ít nhất 10 ký tự.', false);
                return;
            }

            $.ajax({
                url: '<?php echo BASE_URL; ?>/includes/handlers/chapter_error_handler.php',
                type: 'POST',
                data: {
                    comic_id: '<?php echo htmlspecialchars($comic_id); ?>',
                    chapter_name: '<?php echo htmlspecialchars($chapter_number); ?>',
                    error_type: error_type,
                    error_description: error_description
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Report response:', response);
                    if (response.success) {
                        reset();
                        showToast(response.message, true);
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Report AJAX error:', error, xhr.responseText);
                    showToast('Lỗi khi gửi báo cáo: ' + error, false);
                }
            });
        });

        // Handle keyboard navigation
        $(document).on('keydown', function(e) {
            if (e.key === 'ArrowLeft' && '<?php echo $prev_chapter ? 'true' : 'false'; ?>' === 'true') {
                window.location.href = '<?php echo $prev_chapter ? CHAPTER_URL . "?slug=" . htmlspecialchars($slug) . "&chapter=" . htmlspecialchars($prev_chapter['chapter_name']) : ''; ?>';
            } else if (e.key === 'ArrowRight' && '<?php echo $next_chapter ? 'true' : 'false'; ?>' === 'true') {
                window.location.href = '<?php echo $next_chapter ? CHAPTER_URL . "?slug=" . htmlspecialchars($slug) . "&chapter=" . htmlspecialchars($next_chapter['chapter_name']) : ''; ?>';
            }
        });

        // Handle comments
        function openComment() {
            $('.comment-placeholder').addClass('hidden');
            $('.mess-input').removeClass('hidden');
            $('#content_comment').focus();
        }

        function sendComment(parent_id) {
            const content = $('#content_comment').val().trim();
            const comic_id = $('#book_id').val();
            console.log('Comment sending:', { content, comic_id, parent_id });

            if (!content) {
                showToast('Nội dung bình luận không được để trống!', false);
                return;
            }

            $.ajax({
                url: '<?php echo BASE_URL; ?>/includes/handlers/comment_handler.php',
                type: 'POST',
                data: {
                    action: 'post_comment',
                    comic_id: comic_id,
                    content: content,
                    parent_id: parent_id
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Comment response:', response);
                    if (response.success) {
                        const comment = response.comment;
                        const comment_html = `
                            <article class="info-comment child_${comment.id} parent_${comment.parent_id || comment.id} comment-main-level">
                                <div class="avartar-comment">
                                    <div class="avatar-img"><img src="${comment.avatar || 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'}" alt="${comment.username}"></div>
                                </div>
                                <div class="outsite-comment comment-main-level">
                                    <div class="header-comment">
                                        <div class="info-user-comment">
                                            <div>
                                                <strong class="level name_${comment.level}">${comment.username}</strong>
                                                <span title="Cấp ${comment.level}" class="title-user-comment title-member level_${comment.level}">Cấp ${comment.level}</span>
                                                <span class="title-user-comment">Chương <?php echo htmlspecialchars($chapter_number); ?></span>
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
                                            <span class="break-line">|</span>
                                            <span class="dislike-comment" data-id="${comment.id}"><i class="bi bi-hand-thumbs-down"></i> <span class="total-dislike-comment">0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </article>`;
                        $('.list-comment').prepend(comment_html);
                        $('.comment-placeholder').removeClass('hidden');
                        $('.mess-input').addClass('hidden');
                        $('#content_comment').val('');
                        $('.comment-count').text(parseInt($('.comment-count').text()) + 1);
                        showToast(response.message, true);
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Comment AJAX error:', error, xhr.responseText);
                    showToast('Lỗi khi gửi bình luận: ' + error, false);
                }
            });
        }

        function addReply(comment_id) {
            const reply_form = `
                <div class="form-comment reply-comment reply_${comment_id}">
                    <div class="message-content">
                        <textarea class="textarea" placeholder="Trả lời bình luận..." id="reply_content_${comment_id}"></textarea>
                        <div class="control-comment">
                            <button type="button" class="button is-info is-rounded submit_comment" onclick="sendReply(${comment_id});">Gửi</button>
                            <button type="button" class="button is-light is-rounded" onclick="$('.reply_${comment_id}').remove();">Hủy</button>
                        </div>
                    </div>
                </div>`;
            $(`.child_${comment_id}`).append(reply_form);
        }

        function sendReply(parent_id) {
            const content = $(`#reply_content_${parent_id}`).val().trim();
            const comic_id = $('#book_id').val();
            console.log('Reply sending:', { content, comic_id, parent_id });

            if (!content) {
                showToast('Nội dung trả lời không được để trống.', false);
                return;
            }

            $.ajax({
                url: '<?php echo BASE_URL; ?>/includes/handlers/comment_handler.php',
                type: 'POST',
                data: {
                    action: 'post_comment',
                    comic_id: comic_id,
                    content: content,
                    parent_id: parent_id
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Reply response:', response);
                    if (response.success) {
                        const comment = response.comment;
                        const comment_html = `
                            <article class="info-comment child_${comment.id} parent_${comment.parent_id}">
                                <div class="avartar-comment">
                                    <img src="${comment.avatar || 'https://st.truyengg.net/template/frontend/img/placeholder.jpg'}" alt="${comment.username}">
                                </div>
                                <div class="outsite-comment">
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
                                            <span class="like-comment" data-id="${comment.id}">
                                                <i class="bi bi-hand-thumbs-up"></i>
                                                <span class="total-like-comment">0</span>
                                            </span>
                                            <span class="break-line">|</span>
                                            <span class="dislike-comment" data-id="${comment.id}">
                                                <i class="bi bi-hand-thumbs-down"></i>
                                                <span class="total-dislike-comment">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </article>`;
                        $(`.child_${parent_id} .child-comments`).length ?
                            $(`.child_${parent_id} .child-comments`).append(comment_html) :
                            $(`.child_${parent_id}`).append('<div class="child-comments">' + comment_html + '</div>');
                        $(`.reply_${parent_id}`).remove();
                        $('.comment-count').text(parseInt($('.comment-count').text()) + 1);
                        showToast(response.message, true);
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reply AJAX error:', error, xhr.responseText);
                    showToast('Lỗi khi gửi trả lời: ' + error, false);
                }
            });
        }
    </script>
</body>
</html>