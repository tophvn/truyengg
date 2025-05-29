<!-- Chưa update -->



<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/api/otruyen.php';

// Tạo base URL động
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname($_SERVER['SCRIPT_NAME'], 3);
if (strpos($basePath, '/truyengg') === false) {
    $basePath .= '/truyengg';
}
$baseUrl = rtrim($protocol . $host . $basePath, '/') . '/';

// Security check: Chỉ admin được truy cập
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || $_SESSION['roles'] !== 'admin') {
    header('Location: ' . $baseUrl . 'index.php');
    exit;
}

// Khởi tạo API OTruyen
$api = new OTruyenAPI();

// Xử lý AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        if ($_POST['action'] === 'fetch_comics') {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
            $search = isset($_POST['search']) ? trim($_POST['search']) : '';
            $offset = ($page - 1) * $limit;

            $query = "SELECT c.id, c.comic_id, c.name, c.slug, COUNT(ch.id) as chapter_count 
                      FROM comics c 
                      LEFT JOIN chapters ch ON c.id = ch.comic_id 
                      WHERE c.is_backed_up = 0";
            if ($search) {
                $query .= " AND (c.name LIKE ? OR c.slug LIKE ?)";
            }
            $query .= " GROUP BY c.id LIMIT ? OFFSET ?";

            $stmt = $conn->prepare($query);
            if ($search) {
                $search_param = "%$search%";
                $stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
            } else {
                $stmt->bind_param("ii", $limit, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    '_id' => $row['comic_id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'chapter_count' => $row['chapter_count']
                ];
            }

            $count_query = "SELECT COUNT(*) as total FROM comics WHERE is_backed_up = 0";
            if ($search) {
                $count_query .= " AND (name LIKE ? OR slug LIKE ?)";
            }
            $stmt = $conn->prepare($count_query);
            if ($search) {
                $stmt->bind_param("ss", $search_param, $search_param);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);

            $response['success'] = true;
            $response['data'] = $data;
            $response['total_pages'] = $total_pages;
            $stmt->close();
        } elseif ($_POST['action'] === 'start_backup') {
            $comic_ids = isset($_POST['comic_ids']) ? json_decode($_POST['comic_ids'], true) : [];
            if (empty($comic_ids)) {
                throw new Exception('Vui lòng chọn ít nhất một truyện để backup.');
            }

            $client_id = '3cea3f0e5d5c043';
            foreach ($comic_ids as $comic_id) {
                // Tạo log backup
                $stmt = $conn->prepare("INSERT INTO backup_logs (comic_id, status, message, started_at) 
                                        VALUES ((SELECT id FROM comics WHERE comic_id = ?), 'processing', 'Đang xử lý truyện', NOW())");
                $stmt->bind_param("s", $comic_id);
                $stmt->execute();
                $log_id = $conn->insert_id;
                $stmt->close();

                // Lấy thông tin truyện
                $stmt = $conn->prepare("SELECT id, name FROM comics WHERE comic_id = ?");
                $stmt->bind_param("s", $comic_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $comic_row = $result->fetch_assoc();
                $comic_db_id = $comic_row['id'];
                $comic_name = $comic_row['name'];
                $stmt->close();

                // Lấy dữ liệu từ API
                $comic_data = $api->getComicDetails($comic_id);
                if (!isset($comic_data['data']['item'])) {
                    $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', message = ?, completed_at = NOW() WHERE id = ?");
                    $message = $comic_data['message'] ?? 'Không tìm thấy truyện';
                    $stmt->bind_param("si", $message, $log_id);
                    $stmt->execute();
                    $stmt->close();
                    continue;
                }

                $comic = $comic_data['data']['item'];
                $chapters = $comic['chapters'][0]['server_data'] ?? [];

                // Lưu dữ liệu truyện dạng JSON
                $backup_data = json_encode($comic, JSON_UNESCAPED_UNICODE);
                $stmt = $conn->prepare("UPDATE comics SET backup_data = ?, is_backed_up = 1 WHERE id = ?");
                $stmt->bind_param("si", $backup_data, $comic_db_id);
                $stmt->execute();
                $stmt->close();

                // Xử lý từng chương
                $total_chapters = count($chapters);
                $processed_chapters = 0;

                foreach ($chapters as $chapter) {
                    $chapter_name = $chapter['chapter_name'] ?? '';
                    $chapter_api_data = $chapter['chapter_api_data'] ?? '';

                    // Kiểm tra chapter đã backup
                    $stmt = $conn->prepare("SELECT id, is_backed_up FROM chapters WHERE comic_id = ? AND chapter_name = ?");
                    $stmt->bind_param("is", $comic_db_id, $chapter_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $chapter_row = $result->fetch_assoc();
                    if ($chapter_row && $chapter_row['is_backed_up'] == 1) {
                        $processed_chapters++;
                        $stmt->close();
                        continue;
                    }
                    $chapter_db_id = $chapter_row['id'] ?? null;
                    $stmt->close();

                    // Lấy dữ liệu ảnh
                    $chapter_details = $api->getChapterData($chapter_api_data);
                    if (!isset($chapter_details['data']['item']['chapter_image'])) {
                        error_log("Không lấy được ảnh cho chapter $chapter_name của truyện $comic_name");
                        $processed_chapters++;
                        continue;
                    }

                    $domain_cdn = $chapter_details['data']['domain_cdn'] ?? 'https://sv1.otruyencdn.com';
                    $chapter_path = $chapter_details['data']['item']['chapter_path'] ?? '';
                    $images = $chapter_details['data']['item']['chapter_image'] ?? [];

                    // Tạo album Imgur
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/album');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Client-ID $client_id"]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, ['title' => "$comic_name - Chapter $chapter_name"]);
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code !== 200 || !($album_data = json_decode($response, true)) || !isset($album_data['data']['id'])) {
                        error_log("Lỗi tạo album cho chapter $chapter_name: " . ($response ?: 'Không có phản hồi'));
                        $processed_chapters++;
                        continue;
                    }
                    $album_id = $album_data['data']['id'];
                    $album_link = "https://imgur.com/a/$album_id";

                    // Upload ảnh
                    foreach ($images as $image) {
                        $original_url = $domain_cdn . '/' . $chapter_path . '/' . $image['image_file'];
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Client-ID $client_id"]);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                            'image' => $original_url,
                            'type' => 'url',
                            'album' => $album_id
                        ]);
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code !== 200 || !($img_data = json_decode($response, true)) || !isset($img_data['data']['link'])) {
                            error_log("Lỗi upload ảnh $original_url: " . ($response ?: 'Không có phản hồi'));
                        }
                    }

                    // Cập nhật chapter
                    $stmt = $conn->prepare("UPDATE chapters SET imgur_album_link = ?, is_backed_up = 1 WHERE id = ?");
                    $stmt->bind_param("si", $album_link, $chapter_db_id);
                    $stmt->execute();
                    $stmt->close();

                    $processed_chapters++;
                    $progress = round(($processed_chapters / $total_chapters) * 100);

                    // Cập nhật tiến độ
                    $stmt = $conn->prepare("UPDATE backup_logs SET progress = ?, message = ? WHERE id = ?");
                    $message = "Đã xử lý $processed_chapters/$total_chapters chương";
                    $stmt->bind_param("isi", $progress, $message, $log_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Hoàn tất
                $stmt = $conn->prepare("UPDATE backup_logs SET status = 'completed', message = 'Backup hoàn tất', completed_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $log_id);
                $stmt->execute();
                $stmt->close();
            }

            $response['success'] = true;
            $response['message'] = 'Backup hoàn tất. Vui lòng kiểm tra tiến độ.';
        } elseif ($_POST['action'] === 'check_progress') {
            $stmt = $conn->prepare("SELECT bl.id, bl.comic_id, c.name, bl.status, bl.message, bl.progress 
                                    FROM backup_logs bl 
                                    JOIN comics c ON bl.comic_id = c.id 
                                    WHERE bl.status IN ('processing', 'completed')");
            $stmt->execute();
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();
            $response['success'] = true;
            $response['logs'] = $logs;
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Backup Truyện</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .centered-card { max-width: 800px; margin: 0 auto; }
        .progress-container { margin-top: 20px; }
        .progress-bar { transition: width 0.5s ease-in-out; }
        .comic-list { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
        .comic-item { padding: 5px 0; }
        .comic-item input { margin-right: 10px; }
        .search-container { margin-bottom: 15px; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo $baseUrl; ?>includes/admin/index.php" class="nav-link">Home</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $baseUrl; ?>includes/logout.php" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </nav>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Backup Truyện</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo $baseUrl; ?>includes/admin/index.php">Home</a></li>
                            <li class="breadcrumb-item active">Backup Truyện</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card centered-card">
                            <div class="card-header">
                                <h3 class="card-title text-primary fw-bold fs-4">Chọn Truyện để Backup</h3>
                            </div>
                            <div class="card-body">
                                <div class="search-container">
                                    <input type="text" id="searchComic" class="form-control" placeholder="Tìm kiếm truyện...">
                                </div>
                                <form id="backupForm">
                                    <div class="form-group">
                                        <label for="limit">Số lượng truyện mỗi trang</label>
                                        <select id="limit" class="form-control" style="width: 100px;">
                                            <option value="20">20</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Danh sách truyện</label>
                                        <div class="comic-list" id="comicList"></div>
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-primary" id="loadMore">Tải thêm</button>
                                        <button type="submit" class="btn btn-success">Bắt đầu Backup</button>
                                    </div>
                                </form>
                                <div class="progress-container">
                                    <h4>Tiến độ Backup</h4>
                                    <div id="progressList"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo $baseUrl; ?>">TruyenGG</a>.</strong> All rights reserved.
    </footer>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    let page = 1;
    let limit = 20;
    let totalPages = 1;
    let search = '';
    const baseUrl = '<?php echo $baseUrl; ?>';

    function loadComics() {
        $.ajax({
            url: baseUrl + 'includes/admin/backup-truyen.php',
            type: 'POST',
            data: { action: 'fetch_comics', page: page, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    totalPages = response.total_pages;
                    response.data.forEach(function(comic) {
                        $('#comicList').append(`
                            <div class="comic-item">
                                <input type="checkbox" name="comic_ids[]" value="${comic._id}">
                                ${comic.name} (${comic.slug}) - ${comic.chapter_count} chương
                            </div>
                        `);
                    });
                    if (page >= totalPages) {
                        $('#loadMore').hide();
                    }
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function() {
                alert('Lỗi khi tải danh sách truyện.');
            }
        });
    }

    $('#searchComic').on('input', function() {
        search = $(this).val();
        page = 1;
        $('#comicList').empty();
        loadComics();
    });

    $('#limit').on('change', function() {
        limit = parseInt($(this).val());
        page = 1;
        $('#comicList').empty();
        loadComics();
    });

    $('#loadMore').on('click', function() {
        page++;
        loadComics();
    });

    $('#backupForm').on('submit', function(e) {
        e.preventDefault();
        let comic_ids = [];
        $('input[name="comic_ids[]"]:checked').each(function() {
            comic_ids.push($(this).val());
        });
        if (comic_ids.length === 0) {
            alert('Vui lòng chọn ít nhất một truyện.');
            return;
        }

        $.ajax({
            url: baseUrl + 'includes/admin/backup-truyen.php',
            type: 'POST',
            data: { action: 'start_backup', comic_ids: JSON.stringify(comic_ids) },
            dataType: 'json',
            success: function(response) {
                alert(response.message);
                if (response.success) {
                    checkProgress();
                }
            },
            error: function() {
                alert('Lỗi khi bắt đầu backup.');
            }
        });
    });

    function checkProgress() {
        $.ajax({
            url: baseUrl + 'includes/admin/backup-truyen.php',
            type: 'POST',
            data: { action: 'check_progress' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#progressList').empty();
                    response.logs.forEach(function(log) {
                        $('#progressList').append(`
                            <div class="progress-container">
                                <p>Truyện: ${log.name} - ${log.message}</p>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: ${log.progress}%;" aria-valuenow="${log.progress}" aria-valuemin="0" aria-valuemax="100">${log.progress}%</div>
                                </div>
                            </div>
                        `);
                    });
                    if (response.logs.some(log => log.status === 'processing')) {
                        setTimeout(checkProgress, 5000);
                    }
                }
            }
        });
    }

    loadComics();
});
</script>
</body>
</html>