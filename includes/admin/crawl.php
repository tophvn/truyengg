<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || $_SESSION['roles'] !== 'admin') {
    header('Location: ' . HOME_URL);
    exit;
}

// Danh sách User-Agent
$user_agents = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36",
    "Mozilla/5.0 (iPad; CPU OS 15_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/104.0.5112.99 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.3",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0",
    "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.3"
];

// Hàm slugify
function slugify($string) {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = preg_replace('/\s+/', '-', strtolower($string));
    return trim($string, '-');
}

// Hàm làm sạch tên file
function sanitize_filename($filename) {
    return preg_replace('/[\\/*:"<>|?]/', '', $filename);
}

// Hàm gửi yêu cầu HTTP bằng cURL
function fetch_url($url, $headers, $is_json = false, $retries = 3, $delay = 2) {
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir() . '/cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, sys_get_temp_dir() . '/cookies.txt');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = ['success' => true, 'http_code' => $http_code, 'response' => $response];

        if ($http_code == 403 || $http_code == 503) {
            $result['success'] = false;
            $result['message'] = "Bị chặn bởi Cloudflare hoặc CAPTCHA (lần thử $attempt/$retries).";
            if ($attempt < $retries) {
                $result['message'] .= " Thử lại sau $delay giây...";
                sleep($delay);
                $delay *= 2;
            }
        } elseif (curl_errno($ch)) {
            $result['success'] = false;
            $result['message'] = "Lỗi cURL: " . curl_error($ch);
        } elseif ($is_json && $response) {
            $json = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['success'] = false;
                $result['message'] = "Lỗi phân tích JSON: " . json_last_error_msg();
            } else {
                $result['response'] = $json;
            }
        }
        curl_close($ch);
        if ($result['success']) {
            return $result;
        }
    }
    return $result;
}

// Hàm tải một chương từ MimiHentai
function one_chapter_mimi($chapter_id, $chapter_name, $headers, $output_dir, $manga_title) {
    $result = ['success' => true, 'messages' => []];
    $chapter_name = sanitize_filename($chapter_name);
    $manga_title = sanitize_filename($manga_title);
    $folder = "$output_dir/$manga_title/$chapter_name";
    if (!file_exists($folder)) {
        if (!mkdir($folder, 0777, true)) {
            $result['success'] = false;
            $result['messages'][] = "Lỗi: Không thể tạo thư mục $folder";
            return $result;
        }
    }

    $url = "https://mimihentai.com/api/v1/manga/chapter?id=$chapter_id";
    $response = fetch_url($url, $headers, true);
    if (!$response['success']) {
        $result['success'] = false;
        $result['messages'][] = $response['message'];
        return $result;
    }

    $images = $response['response']['pages'] ?? [];
    if (empty($images)) {
        $result['success'] = false;
        $result['messages'][] = "Không tìm thấy hình ảnh trong chương $chapter_name.";
        return $result;
    }

    foreach ($images as $index => $img_url) {
        $result['messages'][] = "Đang tải hình ảnh: $img_url";
        $file = "$folder/image_" . sprintf("%03d", $index + 1) . ".jpg";
        $img_content = fetch_url($img_url, $headers);
        if ($img_content['success'] && file_put_contents($file, $img_content['response'])) {
            $result['messages'][] = "Đã lưu $file";
        } else {
            $result['success'] = false;
            $result['messages'][] = "Lỗi: Không thể lưu $file";
        }
    }
    sleep(rand(1, 2));
    $result['messages'][] = "Hoàn thành chương: $chapter_name";
    return $result;
}

// Hàm tải toàn bộ truyện từ MimiHentai
function all_chapters_mimi($manga_id, $manga_title, $headers, $output_dir, $parts_only = false, $part_start = 0, $part_end = 0) {
    $result = ['success' => true, 'messages' => []];
    $url = "https://mimihentai.com/api/v1/manga/gallery/$manga_id";
    $response = fetch_url($url, $headers, true);
    if (!$response['success']) {
        // Thử crawl HTML nếu API thất bại
        $html_response = fetch_url("https://mimihentai.com/g/$manga_id", $headers);
        if (!$html_response['success']) {
            $result['success'] = false;
            $result['messages'][] = "API và HTML đều thất bại: " . $html_response['message'];
            return $result;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML($html_response['response']);
        $xpath = new DOMXPath($doc);

        $title_nodes = $xpath->query("//h1[@class='gallery-title']");
        $manga_title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : $manga_title;
        $result['messages'][] = "Tiêu đề truyện: $manga_title";

        // Giả lập một chương duy nhất vì MimiHentai thường là gallery đơn
        $chapters = [['id' => $manga_id, 'title' => $manga_title]];
    } else {
        $chapters = $response['response'] ?? [];
        $result['messages'][] = "Tìm thấy " . count($chapters) . " chương cho $manga_title.";
    }

    if (empty($chapters)) {
        $result['success'] = false;
        $result['messages'][] = "Không tìm thấy chương nào.";
        return $result;
    }

    $manga_title = sanitize_filename($manga_title);
    $folder = "$output_dir/$manga_title";

    if ($parts_only) {
        $part_start = max(1, $part_start);
        $part_end = min($part_end, count($chapters));
        $result['messages'][] = "Tải từ chương $part_start đến $part_end.";
        for ($i = $part_start - 1; $i < $part_end && $i < count($chapters); $i++) {
            $chap = $chapters[$i];
            $chapter_result = one_chapter_mimi($chap['id'], $chap['title'] ?? "Chap {$chap['id']}", $headers, $output_dir, $manga_title);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    } else {
        foreach ($chapters as $index => $chap) {
            $chapter_result = one_chapter_mimi($chap['id'], $chap['title'] ?? "Chap {$chap['id']}", $headers, $output_dir, $manga_title);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    }
    return $result;
}

// Hàm crawl một chương từ TruyenQQ
function one_chapter_truyenqq($web, $headers, $output_dir) {
    $result = ['success' => true, 'messages' => []];
    $html_content = fetch_url($web, $headers);
    if (!$html_content['success']) {
        $result['success'] = false;
        $result['messages'][] = $html_content['message'];
        return $result;
    }

    $doc = new DOMDocument();
    @$doc->loadHTML($html_content['response']);
    $xpath = new DOMXPath($doc);

    $h1_nodes = $xpath->query("//h1[contains(@class, 'detail-title txt-primary')]");
    $h1_text = '';
    if ($h1_nodes->length > 0) {
        $h1_text = trim(preg_replace('/\s+/', ' ', $h1_nodes->item(0)->textContent));
        $result['messages'][] = "Tiêu đề chương: $h1_text";
        $h1_text = sanitize_filename($h1_text);
    } else {
        $result['messages'][] = "Không tìm thấy tiêu đề chương.";
    }

    $folder = empty($output_dir) ? __DIR__ . "/downloads/$h1_text" : "$output_dir/$h1_text";
    if (!file_exists($folder)) {
        if (!mkdir($folder, 0777, true)) {
            $result['success'] = false;
            $result['messages'][] = "Lỗi: Không thể tạo thư mục $folder";
            return $result;
        }
    }

    $img_links = [];
    $div_nodes = $xpath->query("//div[contains(@class, 'page-chapter')]//img");
    foreach ($div_nodes as $img) {
        $img_url = $img->getAttribute('data-original');
        if ($img_url) {
            $img_links[] = $img_url;
        }
    }

    if (empty($img_links)) {
        $result['success'] = false;
        $result['messages'][] = "Không tìm thấy hình ảnh trong chương.";
        return $result;
    }

    foreach ($img_links as $index => $link) {
        $result['messages'][] = "Đang tải hình ảnh: $link";
        $file = "$folder/image_" . sprintf("%03d", $index + 1) . ".jpg";
        $img_content = fetch_url($link, $headers);
        if ($img_content['success'] && file_put_contents($file, $img_content['response'])) {
            $result['messages'][] = "Đã lưu $file";
        } else {
            $result['success'] = false;
            $result['messages'][] = "Lỗi: Không thể lưu $file";
        }
    }
    sleep(1);
    $result['messages'][] = "Hoàn thành chương: $h1_text";
    return $result;
}

// Hàm crawl tất cả chương từ TruyenQQ
function all_chapters_truyenqq($web, $headers, $domain, $output_dir, $parts_only = false, $part_start = 0, $part_end = 0) {
    $result = ['success' => true, 'messages' => []];
    $html_content = fetch_url($web, $headers);
    if (!$html_content['success']) {
        $result['success'] = false;
        $result['messages'][] = $html_content['message'];
        return $result;
    }

    $doc = new DOMDocument();
    @$doc->loadHTML($html_content['response']);
    $xpath = new DOMXPath($doc);

    $chapters = [];
    $chapter_nodes = $xpath->query("//div[contains(@class, 'works-chapter-item')]//a");
    foreach ($chapter_nodes as $a) {
        $href = $a->getAttribute('href');
        $chapters[] = $domain . $href;
    }
    $chapters = array_reverse($chapters);
    $result['messages'][] = "Tìm thấy " . count($chapters) . " chương.";
    if (empty($chapters)) {
        $result['success'] = false;
        $result['messages'][] = "Lỗi: Không tìm thấy chương nào. Vui lòng kiểm tra URL hoặc cấu trúc trang.";
        return $result;
    }

    $h1_nodes = $xpath->query("//h1[@itemprop='name']");
    $title = '';
    if ($h1_nodes->length > 0) {
        $title = trim(preg_replace('/\s+/', ' ', $h1_nodes->item(0)->textContent));
        $result['messages'][] = "Tiêu đề truyện: $title";
        $title = sanitize_filename($title);
    } else {
        $result['messages'][] = "Không tìm thấy tiêu đề truyện.";
    }
    $folder = empty($output_dir) ? __DIR__ . "/downloads/$title" : "$output_dir/$title";

    if ($parts_only) {
        $part_start = max(1, $part_start);
        $part_end = min($part_end, count($chapters));
        $result['messages'][] = "Tải từ chương $part_start đến $part_end.";
        for ($i = $part_start - 1; $i < $part_end && $i < count($chapters); $i++) {
            $chapter_result = one_chapter_truyenqq($chapters[$i], $headers, $folder);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    } else {
        foreach ($chapters as $index => $link) {
            $result['messages'][] = "Tải chương " . ($index + 1) . ": $link";
            $chapter_result = one_chapter_truyenqq($link, $headers, $folder);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    }
    return $result;
}

// Xử lý yêu cầu AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crawl_manga') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    $web = trim($_POST['web'] ?? '');
    $source = strtolower(trim($_POST['source'] ?? 'truyenqq'));
    $choose = strtoupper(trim($_POST['choose'] ?? ''));
    $part_start = (int)($_POST['part_start'] ?? 0);
    $part_end = (int)($_POST['part_end'] ?? 0);

    if (empty($web)) {
        $response['messages'][] = "Vui lòng nhập đường link của truyện.";
        echo json_encode($response);
        exit;
    }

    if (!in_array($source, ['truyenqq', 'mimihentai'])) {
        $response['messages'][] = "Nguồn không hợp lệ. Vui lòng chọn TruyenQQ hoặc MimiHentai.";
        echo json_encode($response);
        exit;
    }

    $domain = ($source === 'truyenqq') ? 'https://truyenqqgo.com' : 'https://mimihentai.com';
    $referer = $domain . '/';
    $response['messages'][] = "Nguồn: " . ucfirst($source);
    $response['messages'][] = "Server: $referer";

    $headers = [
        "Connection: keep-alive",
        "Cache-Control: max-age=0",
        "Upgrade-Insecure-Requests: 1",
        "User-Agent: " . $user_agents[array_rand($user_agents)],
        "Accept: text/html,application/json,application/xhtml+xml,application/xml;q=0.9,image/webp,image/jpeg,*/*;q=0.8",
        "Accept-Encoding: gzip, deflate",
        "Accept-Language: en-US,en;q=0.9,vi;q=0.8",
        "Referer: $referer"
    ];

    if ($source === 'truyenqq') {
        if (strpos($web, 'chap') !== false) {
            $response['messages'][] = "Đây là link của một chương đơn. Tiến hành tải...";
            $result = one_chapter_truyenqq($web, $headers, __DIR__ . '/downloads');
            $response['success'] = $result['success'];
            $response['messages'] = array_merge($response['messages'], $result['messages']);
        } else {
            $response['messages'][] = "Đây là link của cả một truyện.";
            if ($choose === 'T') {
                $response['messages'][] = "Tải toàn bộ truyện.";
                $result = all_chapters_truyenqq($web, $headers, $domain, __DIR__ . '/downloads');
                $response['success'] = $result['success'];
                $response['messages'] = array_merge($response['messages'], $result['messages']);
            } elseif ($choose === 'M') {
                $response['messages'][] = "Tải các chương từ $part_start đến $part_end.";
                $result = all_chapters_truyenqq($web, $headers, $domain, __DIR__ . '/downloads', true, $part_start, $part_end);
                $response['success'] = $result['success'];
                $response['messages'] = array_merge($response['messages'], $result['messages']);
            } else {
                $response['messages'][] = "Lựa chọn không hợp lệ. Vui lòng chọn 'T' hoặc 'M'.";
            }
        }
    } elseif ($source === 'mimihentai') {
        // Trích xuất manga_id từ URL
        if (preg_match('/\/g\/(\d+)/', $web, $matches)) {
            $manga_id = $matches[1];
        } else {
            $response['messages'][] = "URL không hợp lệ. Vui lòng nhập URL dạng https://mimihentai.com/g/60986.";
            echo json_encode($response);
            exit;
        }

        $response['messages'][] = "Tìm thấy ID truyện: $manga_id.";
        $manga_title = "Manga_$manga_id"; // Mặc định, sẽ cập nhật nếu crawl HTML

        if ($choose === 'T') {
            $response['messages'][] = "Tải toàn bộ truyện.";
            $result = all_chapters_mimi($manga_id, $manga_title, $headers, __DIR__ . '/downloads');
            $response['success'] = $result['success'];
            $response['messages'] = array_merge($response['messages'], $result['messages']);
        } elseif ($choose === 'M') {
            $response['messages'][] = "Tải các chương từ $part_start đến $part_end.";
            $result = all_chapters_mimi($manga_id, $manga_title, $headers, __DIR__ . '/downloads', true, $part_start, $part_end);
            $response['success'] = $result['success'];
            $response['messages'] = array_merge($response['messages'], $result['messages']);
        } else {
            $response['messages'][] = "Lựa chọn không hợp lệ. Vui lòng chọn 'T' hoặc 'M'.";
        }
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
    <title>TruyenGG - Công cụ Crawl Truyện</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <style>
        .sidebar-dark-primary {
            background-color: #1a252f;
        }
        .main-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }
        .content-wrapper {
            background-color: #f4f6f9;
        }
        .progress {
            height: 25px;
            margin-bottom: 20px;
        }
        .progress-bar {
            transition: width 0.3s ease-in-out;
        }
        #crawlOutput {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            padding: 10px;
            background-color: #fff;
        }
        .alert {
            margin-bottom: 10px;
        }
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
                        <h1 class="m-0">Công cụ Crawl Truyện</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo HOME_URL; ?>">Home</a></li>
                            <li class="breadcrumb-item active">Crawl Truyện</li>
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
                                <h3 class="card-title">Crawl Truyện</h3>
                            </div>
                            <div class="card-body">
                                <form id="crawlForm">
                                    <div class="form-group">
                                        <label for="source">Chọn nguồn truyện:</label>
                                        <select class="form-control" id="source" name="source">
                                            <option value="truyenqq">TruyenQQ (truyenqqgo.com)</option>
                                            <option value="mimihentai">MimiHentai (mimihentai.com)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="web">Nhập đường dẫn của truyện:</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-link"></i></span>
                                            </div>
                                            <input type="text" class="form-control" id="web" name="web" placeholder="https://truyenqqgo.com/truyen-tranh/ten-truyen hoặc https://mimihentai.com/g/60986" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Bạn muốn tải toàn bộ hay một phần truyện?</label>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="choose_all" name="choose" value="T" class="custom-control-input" checked>
                                            <label class="custom-control-label" for="choose_all">Toàn bộ</label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="choose_part" name="choose" value="M" class="custom-control-input">
                                            <label class="custom-control-label" for="choose_part">Một phần</label>
                                        </div>
                                    </div>
                                    <div class="form-group" id="part_range" style="display: none;">
                                        <label>Ví dụ: Đầu: 1, Cuối: 5 sẽ tải các chương từ 1 đến 5.</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="part_start">Đầu:</label>
                                                <input type="number" class="form-control" id="part_start" name="part_start" min="1" value="1">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="part_end">Cuối:</label>
                                                <input type="number" class="form-control" id="part_end" name="part_end" min="1" value="2">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="crawlButton">
                                        <i class="fas fa-download"></i> Bắt đầu tải
                                    </button>
                                </form>
                                <div class="mt-3">
                                    <div class="progress" style="display: none;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                    </div>
                                    <div id="crawlOutput" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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
    $('input[name="choose"]').on('change', function() {
        $('#part_range').toggle(this.value === 'M');
    });

    $('#source').on('change', function() {
        var source = $(this).val();
        var placeholder = source === 'truyenqq' 
            ? 'https://truyenqqgo.com/truyen-tranh/ten-truyen' 
            : 'https://mimihentai.com/g/60986';
        $('#web').attr('placeholder', placeholder);
    });

    $('#crawlForm').on('submit', function(e) {
        e.preventDefault();
        var crawlButton = $('#crawlButton');
        crawlButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang tải...');
        $('.progress').show();
        $('.progress-bar').css('width', '0%').text('0%');
        $('#crawlOutput').html('');

        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'crawl_manga' });

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/crawl.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                $('.progress-bar').css('width', '30%').text('30%');
            },
            success: function(response) {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(function() { $('.progress').hide(); }, 1000);
                crawlButton.prop('disabled', false).html('<i class="fas fa-download"></i> Bắt đầu tải');

                if (response.success) {
                    $('#crawlOutput').append('<div class="alert alert-success">Crawl hoàn tất! Kiểm tra thư mục downloads.</div>');
                } else {
                    $('#crawlOutput').append('<div class="alert alert-danger">Lỗi: ' + response.messages.join('<br>') + '</div>');
                }
                response.messages.forEach(function(message) {
                    $('#crawlOutput').append('<p>' + message + '</p>');
                });
                $('#crawlOutput').scrollTop($('#crawlOutput')[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                $('.progress').hide();
                crawlButton.prop('disabled', false).html('<i class="fas fa-download"></i> Bắt đầu tải');
                $('#crawlOutput').html('<div class="alert alert-danger">Lỗi hệ thống: ' + xhr.status + ' ' + error + '</div>');
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });
});
</script>
</body>
</html>