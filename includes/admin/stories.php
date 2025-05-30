<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || 
    !($_SESSION['roles'] === 'admin' || $_SESSION['roles'] === 'translator')) {
    header('Location: ' . LOGIN_URL);
    exit;
}

define('DOWNLOADS_DIR', realpath(__DIR__ . '/downloads'));

// Lấy API key từ bảng settings
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$key = 'imgbb_api_key';
$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$imgbb_api_key = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : null;
$stmt->close();

if (empty($imgbb_api_key)) {
    die('Lỗi: Không tìm thấy ImgBB API key trong bảng settings.');
}

function generate_slug($string) {
    $string = trim($string);
    
    $transliteration = [
        'à'=>'a', 'á'=>'a', 'ả'=>'a', 'ã'=>'a', 'ạ'=>'a',
        'ă'=>'a', 'ằ'=>'a', 'ắ'=>'a', 'ẳ'=>'a', 'ẵ'=>'a', 'ặ'=>'a',
        'â'=>'a', 'ầ'=>'a', 'ấ'=>'a', 'ẩ'=>'a', 'ẫ'=>'a', 'ậ'=>'a',
        'è'=>'e', 'é'=>'e', 'ẻ'=>'e', 'ẽ'=>'e', 'ẹ'=>'e',
        'ê'=>'e', 'ề'=>'e', 'ế'=>'e', 'ể'=>'e', 'ễ'=>'e', 'ệ'=>'e',
        'ì'=>'i', 'í'=>'i', 'ỉ'=>'i', 'ĩ'=>'i', 'ị'=>'i',
        'ò'=>'o', 'ó'=>'o', 'ỏ'=>'o', 'õ'=>'o', 'ọ'=>'o',
        'ô'=>'o', 'ồ'=>'o', 'ố'=>'o', 'ổ'=>'o', 'ỗ'=>'o', 'ộ'=>'o',
        'ơ'=>'o', 'ờ'=>'o', 'ớ'=>'o', 'ở'=>'o', 'ỡ'=>'o', 'ợ'=>'o',
        'ù'=>'u', 'ú'=>'u', 'ủ'=>'u', 'ũ'=>'u', 'ụ'=>'u',
        'ư'=>'u', 'ừ'=>'u', 'ứ'=>'u', 'ử'=>'u', 'ữ'=>'u', 'ự'=>'u',
        'ỳ'=>'y', 'ý'=>'y', 'ỷ'=>'y', 'ỹ'=>'y', 'ỵ'=>'y',
        'À'=>'A', 'Á'=>'A', 'Ả'=>'A', 'Ã'=>'A', 'Ạ'=>'A',
        'Ă'=>'A', 'Ằ'=>'A', 'Ắ'=>'A', 'Ẳ'=>'A', 'Ẵ'=>'A', 'Ặ'=>'A',
        'Â'=>'A', 'Ầ'=>'A', 'Ấ'=>'A', 'Ẩ'=>'A', 'Ẫ'=>'A', 'Ậ'=>'A',
        'È'=>'E', 'É'=>'E', 'Ẻ'=>'E', 'Ẽ'=>'E', 'Ẹ'=>'E',
        'Ê'=>'E', 'Ề'=>'E', 'Ế'=>'E', 'Ể'=>'E', 'Ễ'=>'E', 'Ệ'=>'E',
        'Ì'=>'I', 'Í'=>'I', 'Ỉ'=>'I', 'Ĩ'=>'I', 'Ị'=>'I',
        'Ò'=>'O', 'Ó'=>'O', 'Ỏ'=>'O', 'Õ'=>'O', 'Ọ'=>'O',
        'Ô'=>'O', 'Ồ'=>'O', 'Ố'=>'O', 'Ổ'=>'O', 'Ỗ'=>'O', 'Ộ'=>'O',
        'Ơ'=>'O', 'Ờ'=>'O', 'Ớ'=>'O', 'Ở'=>'O', 'Ỡ'=>'O', 'Ợ'=>'O',
        'Ù'=>'U', 'Ú'=>'U', 'Ủ'=>'U', 'Ũ'=>'U', 'Ụ'=>'U',
        'Ư'=>'U', 'Ừ'=>'U', 'Ứ'=>'U', 'Ử'=>'U', 'Ữ'=>'U', 'Ự'=>'U',
        'Ỳ'=>'Y', 'Ý'=>'Y', 'Ỷ'=>'Y', 'Ỹ'=>'Y', 'Ỵ'=>'Y',
    ];
    
    $string = strtr($string, $transliteration);
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    return strtolower($string) . '-aaa';
}

function upload_to_imgbb($image_path) {
    global $imgbb_api_key;

    if (!file_exists($image_path) || !is_readable($image_path)) {
        return ['success' => false, 'message' => 'File ảnh không tồn tại hoặc không thể đọc: ' . $image_path];
    }

    $image_info = getimagesize($image_path);
    if (!$image_info || !in_array($image_info['mime'], ['image/jpeg', 'image/png', 'image/gif'])) {
        return ['success' => false, 'message' => 'Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPEG, PNG, hoặc GIF.'];
    }

    $ch = curl_init();
    $data = [
        'key' => $imgbb_api_key,
        'image' => new CURLFile($image_path)
    ];

    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($http_code == 200 && isset($result['status']) && $result['status'] == 200 && isset($result['data']['url'])) {
        return ['success' => true, 'url' => $result['data']['url']];
    }
    return ['success' => false, 'message' => $result['error']['message'] ?? 'Lỗi upload ảnh lên ImgBB (HTTP ' . $http_code . ').'];
}

function delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? delete_directory($path) : unlink($path);
    }
    rmdir($dir);
}

function fetch_categories($conn) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://otruyenapi.com/v1/api/the-loai');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    
    if ($data['status'] !== 'success' || empty($data['data']['items'])) {
        return [];
    }

    $categories = $data['data']['items'];
    
    $stmt_check = $conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
    $stmt_insert = $conn->prepare("INSERT INTO categories (category_id, name, slug) VALUES (?, ?, ?)");
    
    foreach ($categories as $cat) {
        $stmt_check->bind_param('s', $cat['_id']);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows === 0) {
            $stmt_insert->bind_param('sss', $cat['_id'], $cat['name'], $cat['slug']);
            $stmt_insert->execute();
        }
    }
    $stmt_check->close();
    $stmt_insert->close();

    $result = $conn->query("SELECT id, category_id, name FROM categories ORDER BY name ASC");
    $categories_db = [];
    while ($row = $result->fetch_assoc()) {
        $categories_db[] = $row;
    }
    return $categories_db;
}

$categories = fetch_categories($conn);

$comics = [];
$user_id = $_SESSION['user_id'];
$query = "SELECT id, comic_id, name, slug, origin_name, content, status, thumb_url, author, is_hot, created_at, updated_at 
          FROM comics 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['origin_name'] = json_decode($row['origin_name'] ?? '[]', true) ?: [];
    $row['author'] = json_decode($row['author'] ?? '[]', true) ?: [];
    $cat_stmt = $conn->prepare("SELECT category_id FROM comic_categories WHERE comic_id = ?");
    $cat_stmt->bind_param('i', $row['id']);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result();
    $row['categories'] = [];
    while ($cat_row = $cat_result->fetch_assoc()) {
        $row['categories'][] = $cat_row['category_id'];
    }
    $cat_stmt->close();
    $comics[] = $row;
}
$stmt->close();

$download_folders = [];
$download_error = '';
if (is_dir(DOWNLOADS_DIR) && is_readable(DOWNLOADS_DIR)) {
    $folders = scandir(DOWNLOADS_DIR);
    foreach ($folders as $folder) {
        if ($folder !== '.' && $folder !== '..' && is_dir(DOWNLOADS_DIR . '/' . $folder)) {
            $download_folders[] = $folder;
        }
    }
} else {
    $download_error = 'Không thể đọc thư mục downloads. Vui lòng kiểm tra quyền truy cập hoặc cấu hình DOWNLOADS_DIR.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_chapters') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'chapters' => []];

    $folder = trim($_POST['folder'] ?? '');
    if (empty($folder)) {
        $response['message'] = 'Vui lòng chọn thư mục truyện.';
        echo json_encode($response);
        exit;
    }

    $chapter_dir = DOWNLOADS_DIR . '/' . $folder;
    if (!is_dir($chapter_dir) || !is_readable($chapter_dir)) {
        $response['message'] = 'Thư mục truyện không tồn tại hoặc không thể đọc: ' . $chapter_dir;
        echo json_encode($response);
        exit;
    }

    $chapter_list = scandir($chapter_dir);
    $chapters = [];
    foreach ($chapter_list as $chapter) {
        if ($chapter !== '.' && $chapter !== '..' && is_dir($chapter_dir . '/' . $chapter)) {
            $chapters[] = $chapter;
        }
    }

    if (empty($chapters)) {
        $response['message'] = 'Không tìm thấy chapter nào trong thư mục.';
        echo json_encode($response);
        exit;
    }

    $response['success'] = true;
    $response['chapters'] = $chapters;
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_comic') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    try {
        $name = trim($_POST['name'] ?? '');
        $origin_name = trim($_POST['origin_name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'ongoing';
        $main_category = isset($_POST['main_category']) && is_array($_POST['main_category']) ? $_POST['main_category'] : [];
        $author = trim($_POST['author'] ?? '');
        $is_hot = isset($_POST['is_hot']) ? 1 : 0;

        if (empty($name)) {
            $response['messages'][] = 'Tên truyện là bắt buộc.';
            echo json_encode($response);
            exit;
        }

        if (empty($main_category)) {
            $response['messages'][] = 'Vui lòng chọn ít nhất một thể loại.';
            echo json_encode($response);
            exit;
        }

        $valid_statuses = ['ongoing', 'completed', 'onhold', 'dropped', 'coming_soon'];
        if (!in_array($status, $valid_statuses)) {
            $response['messages'][] = 'Trạng thái không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        $thumb_url = null;
        if (!empty($_FILES['thumb']['name'])) {
            $file_extension = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $response['messages'][] = 'Ảnh bìa phải có định dạng JPG, JPEG, PNG hoặc GIF.';
                echo json_encode($response);
                exit;
            }
            if ($_FILES['thumb']['size'] > 32 * 1024 * 1024) {
                $response['messages'][] = 'Ảnh bìa không được vượt quá 32MB.';
                echo json_encode($response);
                exit;
            }
            $thumb_result = upload_to_imgbb($_FILES['thumb']['tmp_name']);
            if (!$thumb_result['success']) {
                $response['messages'][] = 'Lỗi upload ảnh bìa lên ImgBB: ' . $thumb_result['message'];
                echo json_encode($response);
                exit;
            }
            $thumb_url = $thumb_result['url'];
        }

        $slug = generate_slug($name);
        $origin_name_array = array_filter(array_map('trim', explode(',', $origin_name)));
        $author_array = array_filter(array_map('trim', explode(',', $author)));

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO comics (comic_id, name, slug, origin_name, content, status, thumb_url, author, is_hot, is_backed_up, views)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
        ");
        $comic_id = 'local_' . uniqid();
        $origin_name_json = json_encode($origin_name_array, JSON_UNESCAPED_UNICODE);
        $author_json = json_encode($author_array, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param(
            'ssssssssi',
            $comic_id,
            $name,
            $slug,
            $origin_name_json,
            $content,
            $status,
            $thumb_url,
            $author_json,
            $is_hot
        );
        $stmt->execute();
        $new_comic_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO comic_categories (comic_id, category_id) VALUES (?, ?)");
        foreach ($main_category as $cat_id) {
            $cat_id = (int)$cat_id;
            $stmt->bind_param('ii', $new_comic_id, $cat_id);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Tạo truyện thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_comic') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    try {
        $comic_id = (int)($_POST['comic_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $origin_name = trim($_POST['origin_name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'ongoing';
        $main_category = isset($_POST['main_category']) && is_array($_POST['main_category']) ? $_POST['main_category'] : [];
        $author = trim($_POST['author'] ?? '');
        $is_hot = isset($_POST['is_hot']) ? 1 : 0;

        if ($comic_id <= 0) {
            $response['messages'][] = 'ID truyện không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        if (empty($name)) {
            $response['messages'][] = 'Tên truyện là bắt buộc.';
            echo json_encode($response);
            exit;
        }

        if (empty($main_category)) {
            $response['messages'][] = 'Vui lòng chọn ít nhất một thể loại.';
            echo json_encode($response);
            exit;
        }

        $valid_statuses = ['ongoing', 'completed', 'onhold', 'dropped', 'coming_soon'];
        if (!in_array($status, $valid_statuses)) {
            $response['messages'][] = 'Trạng thái không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("SELECT thumb_url FROM comics WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Truyện không tồn tại.';
            echo json_encode($response);
            exit;
        }
        $comic = $result->fetch_assoc();
        $thumb_url = $comic['thumb_url'];
        $stmt->close();

        if (!empty($_FILES['thumb']['name'])) {
            $file_extension = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $response['messages'][] = 'Ảnh bìa phải có định dạng JPG, JPEG, PNG hoặc GIF.';
                echo json_encode($response);
                exit;
            }
            if ($_FILES['thumb']['size'] > 32 * 1024 * 1024) {
                $response['messages'][] = 'Ảnh bìa không được vượt quá 32MB.';
                echo json_encode($response);
                exit;
            }
            $thumb_result = upload_to_imgbb($_FILES['thumb']['tmp_name']);
            if (!$thumb_result['success']) {
                $response['messages'][] = 'Lỗi upload ảnh bìa lên ImgBB: ' . $thumb_result['message'];
                echo json_encode($response);
                exit;
            }
            $thumb_url = $thumb_result['url'];
        }

        $slug = generate_slug($name);
        $origin_name_array = array_filter(array_map('trim', explode(',', $origin_name)));
        $author_array = array_filter(array_map('trim', explode(',', $author)));

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE comics 
            SET name = ?, slug = ?, origin_name = ?, content = ?, status = ?, thumb_url = ?, author = ?, is_hot = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $origin_name_json = json_encode($origin_name_array, JSON_UNESCAPED_UNICODE);
        $author_json = json_encode($author_array, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param(
            'ssssssssi',
            $name,
            $slug,
            $origin_name_json,
            $content,
            $status,
            $thumb_url,
            $author_json,
            $is_hot,
            $comic_id
        );
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM comic_categories WHERE comic_id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO comic_categories (comic_id, category_id) VALUES (?, ?)");
        foreach ($main_category as $cat_id) {
            $cat_id = (int)$cat_id;
            $stmt->bind_param('ii', $comic_id, $cat_id);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Cập nhật truyện thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comic') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    try {
        $comic_id = (int)($_POST['comic_id'] ?? 0);
        if ($comic_id <= 0) {
            $response['messages'][] = 'ID truyện không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("DELETE FROM comics WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Xóa truyện thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_chapter') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    try {
        $comic_id = (int)($_POST['comic_id'] ?? 0);
        $download_folder = trim($_POST['download_folder'] ?? '');
        $chapter_folder = trim($_POST['chapter_folder'] ?? '');

        if ($comic_id <= 0 || empty($download_folder) || empty($chapter_folder)) {
            $response['messages'][] = 'ID truyện, thư mục tải về và chapter là bắt buộc.';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("SELECT name FROM comics WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Truyện không tồn tại.';
            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $conn->begin_transaction();

        $chapter_dir = DOWNLOADS_DIR . '/' . $download_folder . '/' . $chapter_folder;
        if (!is_dir($chapter_dir) || !is_readable($chapter_dir)) {
            throw new Exception('Thư mục chapter không tồn tại hoặc không thể đọc: ' . $chapter_dir);
        }

        $stmt = $conn->prepare("
            INSERT INTO chapters (comic_id, chapter_name, chapter_title, is_backed_up, imgur_album_link)
            VALUES (?, ?, ?, 1, NULL)
        ");
        $chapter_name = preg_replace('/[^0-9]/', '', $chapter_folder);
        $chapter_title = $chapter_folder;
        $stmt->bind_param('iss', $comic_id, $chapter_name, $chapter_title);
        $stmt->execute();
        $chapter_id = $conn->insert_id;
        $stmt->close();

        $image_dir = $chapter_dir;
        $images = scandir($image_dir);
        $image_order = 1;
        $image_count = count(array_filter($images, function($item) {
            return preg_match('/\.(jpg|jpeg|png|gif)$/i', $item);
        }));
        $current_progress = 0;
        $first_image_url = null;

        foreach ($images as $image) {
            if ($image === '.' || $image === '..' || !preg_match('/\.(jpg|jpeg|png|gif)$/i', $image)) {
                continue;
            }

            $image_path = $image_dir . '/' . $image;
            $upload_result = upload_to_imgbb($image_path);
            if (!$upload_result['success']) {
                throw new Exception('Lỗi upload ảnh ' . $image . ': ' . $upload_result['message']);
            }

            $image_page = $upload_result['url'];
            $clean_filename = preg_replace('/[^\x20-\x7E]/', '', basename($image_path));
            $original_url = 'local:' . $clean_filename;

            if (strlen($original_url) > 65535) {
                throw new Exception('original_url quá dài: ' . substr($original_url, 0, 100) . '...');
            }

            if (strlen($image_page) > 512) {
                throw new Exception('URL ảnh từ ImgBB quá dài: ' . $image_page);
            }

            $stmt = $conn->prepare("
                INSERT INTO chapter_images (chapter_id, image_page, original_url, image_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('issi', $chapter_id, $image_page, $original_url, $image_order);
            $stmt->execute();
            $stmt->close();

            if ($image_order === 1) {
                $first_image_url = $image_page;
            }

            $image_order++;
            $current_progress++;
        }

        if ($first_image_url) {
            $stmt = $conn->prepare("UPDATE chapters SET imgur_album_link = ? WHERE id = ?");
            $stmt->bind_param('si', $first_image_url, $chapter_id);
            $stmt->execute();
            $stmt->close();
        }

        delete_directory($chapter_dir);

        $stmt = $conn->prepare("UPDATE comics SET updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Thêm chapter thành công và đã xóa chapter khỏi bộ nhớ!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_chapter_files') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    try {
        $comic_id = (int)($_POST['comic_id'] ?? 0);
        $chapter_name = trim($_POST['chapter_name'] ?? '');

        if ($comic_id <= 0 || empty($chapter_name)) {
            $response['messages'][] = 'ID truyện và tên chapter là bắt buộc.';
            echo json_encode($response);
            exit;
        }

        if (empty($_FILES['chapter_files']['name'])) {
            $response['messages'][] = 'Vui lòng chọn ít nhất một ảnh.';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("SELECT name FROM comics WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Truyện không tồn tại.';
            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $conn->begin_transaction();

        $chapter_number = preg_replace('/[^0-9]/', '', $chapter_name);
        $chapter_title = $chapter_name;
        $stmt = $conn->prepare("
            INSERT INTO chapters (comic_id, chapter_name, chapter_title, is_backed_up, imgur_album_link)
            VALUES (?, ?, ?, 1, NULL)
        ");
        $stmt->bind_param('iss', $comic_id, $chapter_number, $chapter_title);
        $stmt->execute();
        $chapter_id = $conn->insert_id;
        $stmt->close();

        $image_order = 1;
        $image_count = count($_FILES['chapter_files']['name']);
        $current_progress = 0;
        $first_image_url = null;
        $temp_dir = sys_get_temp_dir() . '/comic_upload_' . uniqid();
        if (!mkdir($temp_dir, 0777, true)) {
            throw new Exception('Không thể tạo thư mục tạm: ' . $temp_dir);
        }

        foreach ($_FILES['chapter_files']['name'] as $index => $file_name) {
            if ($_FILES['chapter_files']['error'][$index] !== UPLOAD_ERR_OK) {
                throw new Exception('Lỗi upload file: ' . $file_name);
            }

            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                throw new Exception('Ảnh không hợp lệ: ' . $file_name . '. Chỉ chấp nhận JPG, JPEG, PNG hoặc GIF.');
            }

            if ($_FILES['chapter_files']['size'][$index] > 32 * 1024 * 1024) {
                throw new Exception('Ảnh vượt quá 32MB: ' . $file_name);
            }

            $temp_path = $temp_dir . '/' . $file_name;
            if (!move_uploaded_file($_FILES['chapter_files']['tmp_name'][$index], $temp_path)) {
                throw new Exception('Không thể di chuyển file: ' . $file_name);
            }

            $upload_result = upload_to_imgbb($temp_path);
            if (!$upload_result['success']) {
                throw new Exception('Lỗi upload ảnh ' . $file_name . ': ' . $upload_result['message']);
            }

            $image_page = $upload_result['url'];
            $clean_filename = $_FILES['chapter_files']['name'][$index];
            $original_url = 'local:' . $clean_filename;

            if (strlen($original_url) > 65535) {
                throw new Exception('original_url quá dài: ' . substr($original_url, 0, 100) . '...');
            }

            if (strlen($image_page) > 512) {
                throw new Exception('URL ảnh từ ImgBB quá dài: ' . $image_page);
            }

            $stmt = $conn->prepare("
                INSERT INTO chapter_images (chapter_id, image_page, original_url, image_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('issi', $chapter_id, $image_page, $original_url, $image_order);
            $stmt->execute();
            $stmt->close();

            if ($image_order === 1) {
                $first_image_url = $image_page;
            }

            $image_order++;
            $current_progress++;
        }

        if ($first_image_url) {
            $stmt = $conn->prepare("UPDATE chapters SET imgur_album_link = ? WHERE id = ?");
            $stmt->bind_param('si', $first_image_url, $chapter_id);
            $stmt->execute();
            $stmt->close();
        }

        delete_directory($temp_dir);

        $stmt = $conn->prepare("UPDATE comics SET updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Thêm chapter thành công và đã xóa file tạm!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
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
    <title>TruyenGG - Quản lý truyện</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <style>
        .main-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }
        .content-wrapper {
            background-color: #f4f6f9;
        }
        .status-ongoing { color: orange; font-weight: bold; }
        .status-completed { color: green; font-weight: bold; }
        .status-onhold { color: blue; font-weight: bold; }
        .status-dropped { color: red; font-weight: bold; }
        .status-coming_soon { color: purple; font-weight: bold; }
        .card { margin-bottom: 20px; }
        .thumb-img { max-width: 100px; height: auto; }
        #crawlOutput, #chapterOutput, #editOutput { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; }
        .progress { height: 25px; }
        .category-checkboxes { max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #dee2e6; }
        .category-checkboxes label { display: block; margin-bottom: 5px; }
        .action-buttons { display: flex; gap: 5px; }
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
                <a href="<?php echo BASE_URL; ?>includes/admin/index.php" class="nav-link">Home</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo LOGOUT_URL; ?>" title="Đăng xuất">
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
                        <h1 class="m-0">Quản lý truyện</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo HOME_URL; ?>">Home</a></li>
                            <li class="breadcrumb-item active">Quản lý truyện</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Danh sách truyện</h3>
                                <div class="card-tools">
                                    <input type="text" id="searchInput" class="form-control d-inline-block" placeholder="Tìm kiếm truyện..." style="width: 250px; margin-right: 10px;">
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createComicModal">
                                        <i class="fas fa-plus"></i> Thêm Truyện Mới
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="comicsTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tên truyện</th>
                                            <th>Tên khác</th>
                                            <th>Tác giả</th>
                                            <th>Trạng thái</th>
                                            <th>Ảnh bìa</th>
                                            <th>Ngày tạo</th>
                                            <th>Ngày cập nhật</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($comics)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center">Chưa có truyện nào.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($comics as $comic): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($comic['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($comic['name']); ?></td>
                                                    <td><?php echo htmlspecialchars(implode(', ', $comic['origin_name'] ?: ['Chưa có tên khác'])); ?></td>
                                                    <td><?php echo htmlspecialchars(implode(', ', $comic['author'] ?: ['Chưa có tác giả'])); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo $comic['status']; ?>">
                                                            <?php
                                                            $status_display = [
                                                                'ongoing' => 'Đang tiến hành',
                                                                'completed' => 'Hoàn thành',
                                                                'onhold' => 'Tạm hoãn',
                                                                'dropped' => 'Đã hủy',
                                                                'coming_soon' => 'Sắp ra mắt'
                                                            ];
                                                            echo $status_display[$comic['status']] ?? $comic['status'];
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($comic['thumb_url']): ?>
                                                            <img src="<?php echo htmlspecialchars($comic['thumb_url']); ?>" class="thumb-img" alt="Thumbnail">
                                                        <?php else: ?>
                                                            Không có ảnh
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($comic['created_at']))); ?></td>
                                                    <td><?php echo htmlspecialchars($comic['updated_at'] ? date('d/m/Y H:i', strtotime($comic['updated_at'])) : 'Chưa cập nhật'); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button type="button" class="btn btn-sm btn-success add-chapter-btn" 
                                                                    data-toggle="modal" data-target="#addChapterModal" 
                                                                    data-comic-id="<?php echo $comic['id']; ?>" 
                                                                    data-comic-name="<?php echo htmlspecialchars($comic['name']); ?>">
                                                                <i class="fas fa-plus"></i> Thêm Chapter
                                                            </button>
                                                            <a href="<?php echo BASE_URL; ?>includes/admin/quan-ly-chapter.php?comic_id=<?php echo $comic['id']; ?>" 
                                                                class="btn btn-sm btn-info manage-chapter-btn">
                                                                <i class="fas fa-list"></i> Quản lý Chapter
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-warning edit-comic-btn" 
                                                                    data-toggle="modal" data-target="#editComicModal" 
                                                                    data-comic='<?php echo json_encode($comic, JSON_UNESCAPED_UNICODE); ?>'>
                                                                <i class="fas fa-edit"></i> Sửa
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger delete-comic-btn" 
                                                                    data-comic-id="<?php echo $comic['id']; ?>" 
                                                                    data-comic-name="<?php echo htmlspecialchars($comic['name']); ?>">
                                                                <i class="fas fa-trash"></i> Xóa
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="createComicModal" tabindex="-1" role="dialog" aria-labelledby="createComicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createComicModalLabel">Thêm Truyện Mới</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createComicForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Tên truyện</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="origin_name">Tên khác (phân cách bằng dấu phẩy)</label>
                            <textarea class="form-control" id="origin_name" name="origin_name"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="content">Mô tả</label>
                            <textarea class="form-control" id="content" name="content" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="status">Trạng thái</label>
                            <select class="form-control" id="status" name="status">
                                <option value="ongoing">Đang tiến hành</option>
                                <option value="completed">Hoàn thành</option>
                                <option value="onhold">Tạm hoãn</option>
                                <option value="dropped">Đã hủy</option>
                                <option value="coming_soon">Sắp ra mắt</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Thể loại (chọn ít nhất một)</label>
                            <div class="category-checkboxes">
                                <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox" name="main_category[]" value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="author">Tác giả (phân cách bằng dấu phẩy)</label>
                            <textarea class="form-control" id="author" name="author"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="thumb">Ảnh bìa</label>
                            <input type="file" class="form-control-file" id="thumb" name="thumb" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="form-group">
                            <label for="is_hot">Truyện hot</label>
                            <input type="checkbox" id="is_hot" name="is_hot">
                        </div>
                        <div class="progress" style="display: none;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div id="crawlOutput" class="mt-3"></div>
                        <button type="submit" class="btn btn-primary" id="saveComicButton">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editComicModal" tabindex="-1" role="dialog" aria-labelledby="editComicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editComicModalLabel">Sửa Truyện</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editComicForm" enctype="multipart/form-data">
                        <input type="hidden" id="edit_comic_id" name="comic_id">
                        <div class="form-group">
                            <label for="edit_name">Tên truyện</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_origin_name">Tên khác (phân cách bằng dấu phẩy)</label>
                            <textarea class="form-control" id="edit_origin_name" name="origin_name"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_content">Mô tả</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Trạng thái</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="ongoing">Đang tiến hành</option>
                                <option value="completed">Hoàn thành</option>
                                <option value="onhold">Tạm hoãn</option>
                                <option value="dropped">Đã hủy</option>
                                <option value="coming_soon">Sắp ra mắt</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Thể loại (chọn ít nhất một)</label>
                            <div class="category-checkboxes">
                                <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox" name="main_category[]" value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_author">Tác giả (phân cách bằng dấu phẩy)</label>
                            <textarea class="form-control" id="edit_author" name="author"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_thumb">Ảnh bìa (để trống nếu không thay đổi)</label>
                            <input type="file" class="form-control-file" id="edit_thumb" name="thumb" accept="image/jpeg,image/png,image/gif">
                            <img id="edit_thumb_preview" class="thumb-img mt-2" style="display: none;" alt="Thumbnail Preview">
                        </div>
                        <div class="form-group">
                            <label for="edit_is_hot">Truyện hot</label>
                            <input type="checkbox" id="edit_is_hot" name="is_hot">
                        </div>
                        <div class="progress" style="display: none;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div id="editOutput" class="mt-3"></div>
                        <button type="submit" class="btn btn-primary" id="saveEditComicButton">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addChapterModal" tabindex="-1" role="dialog" aria-labelledby="addChapterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addChapterModalLabel">Thêm Chapter</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addChapterForm" enctype="multipart/form-data">
                        <input type="hidden" id="chapter_comic_id" name="comic_id">
                        <div class="form-group">
                            <label for="chapter_comic_name">Truyện</label>
                            <input type="text" class="form-control" id="chapter_comic_name" readonly>
                        </div>
                        <div class="form-group">
                            <label>Phương thức thêm chapter</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="upload_method" id="upload_folder" value="folder" checked>
                                <label class="form-check-label" for="upload_folder">
                                    Chọn thư mục truyện đã tải
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="upload_method" id="upload_files" value="files">
                                <label class="form-check-label" for="upload_files">
                                    Tải ảnh từ thiết bị
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="folder_selection">
                            <label for="chapter_download_folder">Chọn thư mục truyện đã tải</label>
                            <select class="form-control" id="chapter_download_folder" name="download_folder">
                                <option value="">Chọn thư mục</option>
                                <?php foreach ($download_folders as $folder): ?>
                                    <option value="<?php echo htmlspecialchars($folder); ?>">
                                        <?php echo htmlspecialchars($folder); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($download_error): ?>
                                <div class="text-danger"><?php echo htmlspecialchars($download_error); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group" id="chapter_selection" style="display: none;">
                            <label for="chapter_folder">Chọn chapter</label>
                            <select class="form-control" id="chapter_folder" name="chapter_folder">
                                <option value="">Chọn chapter</option>
                            </select>
                        </div>
                        <div class="form-group" id="file_selection" style="display: none;">
                            <label for="chapter_files">Chọn ảnh chapter</label>
                            <input type="file" class="form-control-file" id="chapter_files" name="chapter_files[]" accept="image/jpeg,image/png,image/gif" multiple>
                            <small class="form-text text-muted">Chọn nhiều ảnh (JPG, PNG, GIF). Tối đa 32MB mỗi ảnh.</small>
                        </div>
                        <div class="form-group" id="chapter_name_input" style="display: none;">
                            <label for="chapter_name">Tên chapter</label>
                            <input type="text" class="form-control" id="chapter_name" name="chapter_name" placeholder="Nhập tên chapter">
                        </div>
                        <div class="progress" style="display: none;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div id="chapterOutput" class="mt-3"></div>
                        <button type="submit" class="btn btn-primary" id="saveChapterButton">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo HOME_URL; ?>">TruyenGG</a>.</strong>
        All rights reserved.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    // Search functionality
    var $table = $('#comicsTable');
    var $tbody = $table.find('tbody');
    var originalRows = $tbody.find('tr').clone(); // Store original rows

    $('#searchInput').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $tbody.empty();

        var hasMatches = false;
        originalRows.each(function() {
            var $row = $(this);
            var name = $row.find('td:eq(1)').text().toLowerCase();
            var originName = $row.find('td:eq(2)').text().toLowerCase();

            if (name.includes(searchTerm) || originName.includes(searchTerm)) {
                $tbody.append($row.clone());
                hasMatches = true;
            }
        });

        if (!hasMatches) {
            $tbody.append('<tr><td colspan="9" class="text-center">Không tìm thấy truyện nào.</td></tr>');
        }
    });

    $('#createComicForm').on('submit', function(e) {
        e.preventDefault();
        var saveButton = $('#saveComicButton');
        var categoriesChecked = $('input[name="main_category[]"]:checked').length;

        if (categoriesChecked === 0) {
            $('#crawlOutput').html('<div class="alert alert-danger">Vui lòng chọn ít nhất một thể loại.</div>');
            return;
        }

        saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang lưu...');
        $('.progress').show();
        $('.progress-bar').css('width', '0%').text('0%');
        $('#crawlOutput').html('');

        var formData = new FormData(this);
        formData.append('action', 'create_comic');

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function() {
                $('.progress-bar').css('width', '10%').text('10%');
            },
            success: function(response) {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(function() { $('.progress').hide(); }, 1000);
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');

                if (response.success) {
                    $('#crawlOutput').html('<div class="alert alert-success">' + response.messages.join('<br>') + '</div>');
                    setTimeout(function() {
                        $('#createComicModal').modal('hide');
                        location.reload();
                    }, 2000);
                } else {
                    $('#crawlOutput').html('<div class="alert alert-danger">' + response.messages.join('<br>') + '</div>');
                }
                $('#crawlOutput').scrollTop($('#crawlOutput')[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                $('.progress').hide();
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');
                $('#crawlOutput').html('<div class="alert alert-danger">Lỗi AJAX: ' + xhr.status + ' ' + error + '</div>');
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });

    $(document).on('click', '.add-chapter-btn', function() {
        var comicId = $(this).data('comic-id');
        var comicName = $(this).data('comic-name') || $(this).closest('tr').find('td:eq(1)').text().trim();
        console.log('Add chapter clicked - comicId:', comicId, 'comicName:', comicName);
        $('#chapter_comic_id').val(comicId);
        $('#chapter_comic_name').val(comicName);
        $('#chapter_download_folder').val('');
        $('#chapter_selection').hide();
        $('#chapter_folder').empty().append('<option value="">Chọn chapter</option>');
        $('#file_selection').hide();
        $('#chapter_name_input').hide();
        $('#chapterOutput').html('');
        $('#upload_folder').prop('checked', true);
        $('#folder_selection').show();

        // Lấy số chapter hiện tại từ cơ sở dữ liệu
        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
            type: 'POST',
            data: {
                action: 'get_latest_chapter',
                comic_id: comicId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.latest_chapter) {
                    var nextChapter = parseInt(response.latest_chapter) + 1;
                    $('#chapter_name').val('Chapter ' + nextChapter);
                } else {
                    $('#chapter_name').val('Chapter 1');
                }
                if ($('input[name="upload_method"]:checked').val() === 'files') {
                    $('#chapter_name_input').show();
                }
            },
            error: function() {
                $('#chapter_name').val('Chapter 1');
                if ($('input[name="upload_method"]:checked').val() === 'files') {
                    $('#chapter_name_input').show();
                }
            }
        });
    });

    $('input[name="upload_method"]').on('change', function() {
        var method = $(this).val();
        if (method === 'folder') {
            $('#folder_selection').show();
            $('#chapter_selection').hide();
            $('#file_selection').hide();
            $('#chapter_name_input').hide();
            $('#chapter_folder').empty().append('<option value="">Chọn chapter</option>');
            $('#chapter_files').val('');
            $('#chapter_name').val('');
        } else if (method === 'files') {
            $('#folder_selection').hide();
            $('#chapter_selection').hide();
            $('#file_selection').show();
            $('#chapter_name_input').show();
        }
    });

    $('#chapter_download_folder').on('change', function() {
        var folder = $(this).val();
        if (folder && $('input[name="upload_method"]:checked').val() === 'folder') {
            $.ajax({
                url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
                type: 'POST',
                data: {
                    action: 'get_chapters',
                    folder: folder
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#chapter_folder').empty();
                        $('#chapter_folder').append('<option value="">Chọn chapter</option>');
                        $.each(response.chapters, function(index, chapter) {
                            $('#chapter_folder').append('<option value="' + chapter + '">' + chapter + '</option>');
                        });
                        $('#chapter_selection').show();
                    } else {
                        $('#chapter_selection').hide();
                        $('#chapterOutput').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#chapter_selection').hide();
                    $('#chapterOutput').html('<div class="alert alert-danger">Lỗi khi lấy danh sách chapter.</div>');
                }
            });
        } else {
            $('#chapter_selection').hide();
            $('#chapter_folder').empty().append('<option value="">Chọn chapter</option>');
        }
    });

    $('#addChapterForm').on('submit', function(e) {
        e.preventDefault();
        var saveButton = $('#saveChapterButton');
        var uploadMethod = $('input[name="upload_method"]:checked').val();

        if (uploadMethod === 'files' && $('#chapter_files')[0].files.length === 0) {
            $('#chapterOutput').html('<div class="alert alert-danger">Vui lòng chọn ít nhất một ảnh.</div>');
            return;
        }

        if (uploadMethod === 'files' && !$('#chapter_name').val().trim()) {
            $('#chapterOutput').html('<div class="alert alert-danger">Vui lòng nhập tên chapter.</div>');
            return;
        }

        saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang lưu...');
        $('.progress').show();
        $('.progress-bar').css('width', '0%').text('0%');
        $('#chapterOutput').html('');

        var formData = new FormData(this);
        formData.append('action', uploadMethod === 'folder' ? 'add_chapter' : 'add_chapter_files');

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function() {
                $('.progress-bar').css('width', '10%').text('10%');
            },
            success: function(response) {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(function() { $('.progress').hide(); }, 1000);
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');

                if (response.success) {
                    $('#chapterOutput').html('<div class="alert alert-success">' + response.messages.join('<br>') + '</div>');
                    setTimeout(function() {
                        $('#addChapterModal').modal('hide');
                        location.reload();
                    }, 2000);
                } else {
                    $('#chapterOutput').html('<div class="alert alert-danger">' + response.messages.join('<br>') + '</div>');
                }
                $('#chapterOutput').scrollTop($('#chapterOutput')[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                $('.progress').hide();
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');
                $('#chapterOutput').html('<div class="alert alert-danger">Lỗi AJAX: ' + xhr.status + ' ' + error + '</div>');
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });

    $(document).on('click', '.edit-comic-btn', function() {
        var comic = JSON.parse($(this).attr('data-comic'));
        $('#edit_comic_id').val(comic.id);
        $('#edit_name').val(comic.name);
        $('#edit_origin_name').val(comic.origin_name.join(', '));
        $('#edit_content').val(comic.content);
        $('#edit_status').val(comic.status);
        $('#edit_author').val(comic.author.join(', '));
        $('#edit_is_hot').prop('checked', comic.is_hot === 1);
        
        if (comic.thumb_url) {
            $('#edit_thumb_preview').attr('src', comic.thumb_url).show();
        } else {
            $('#edit_thumb_preview').hide();
        }

        $('#editComicForm input[name="main_category[]"]').prop('checked', false);
        if (comic.categories) {
            comic.categories.forEach(function(cat_id) {
                $('#editComicForm input[name="main_category[]"][value="' + cat_id + '"]').prop('checked', true);
            });
        }

        $('#editOutput').html('');
    });

    $('#editComicForm').on('submit', function(e) {
        e.preventDefault();
        var saveButton = $('#saveEditComicButton');
        var categoriesChecked = $('#editComicForm input[name="main_category[]"]:checked').length;

        if (categoriesChecked === 0) {
            $('#editOutput').html('<div class="alert alert-danger">Vui lòng chọn ít nhất một thể loại.</div>');
            return;
        }

        saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang lưu...');
        $('.progress').show();
        $('.progress-bar').css('width', '0%').text('0%');
        $('#editOutput').html('');

        var formData = new FormData(this);
        formData.append('action', 'edit_comic');

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function() {
                $('.progress-bar').css('width', '10%').text('10%');
            },
            success: function(response) {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(function() { $('.progress').hide(); }, 1000);
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');

                if (response.success) {
                    $('#editOutput').html('<div class="alert alert-success">' + response.messages.join('<br>') + '</div>');
                    setTimeout(function() {
                        $('#editComicModal').modal('hide');
                        location.reload();
                    }, 2000);
                } else {
                    $('#editOutput').html('<div class="alert alert-danger">' + response.messages.join('<br>') + '</div>');
                }
                $('#editOutput').scrollTop($('#editOutput')[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                $('.progress').hide();
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');
                $('#editOutput').html('<div class="alert alert-danger">Lỗi AJAX: ' + xhr.status + ' ' + error + '</div>');
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });

    $(document).on('click', '.delete-comic-btn', function() {
        var comic_id = $(this).data('comic-id');
        var comic_name = $(this).data('comic-name');

        if (confirm('Bạn có chắc chắn muốn xóa truyện "' + comic_name + '"? Hành động này không thể hoàn tác.')) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>includes/admin/stories.php',
                type: 'POST',
                data: {
                    action: 'delete_comic',
                    comic_id: comic_id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Xóa truyện thành công!');
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.messages.join('\n'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Lỗi hệ thống khi xóa truyện: ' + xhr.status + ' ' + error);
                }
            });
        }
    });

    // Xử lý AJAX để lấy chapter mới nhất
    $.ajaxSetup({
        data: {
            action: 'get_latest_chapter'
        }
    });
});
</script>
</body>
</html>
