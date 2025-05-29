<!-- Tools Crawl truyện từ TruyenQQ by HoangToph auto vượt Captcha Cloudflare -->
<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || $_SESSION['roles'] !== 'admin') {
    header('Location: ' . HOME_URL);
    exit;
}

// Danh sách các chuỗi User-Agent để giả lập trình duyệt
$user_agents = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36",
    "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36",
    "Mozilla/5.0 (iPad; CPU OS 15_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/104.0.5112.99 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.3",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0",
    "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.3"
];

// Hàm làm sạch tên tệp để loại bỏ ký tự không hợp lệ
function sanitize_filename($filename) {
    return preg_replace('/[\\/*:"<>|?]/', '', $filename);
}

// Hàm gửi yêu cầu HTTP bằng cURL để lấy nội dung trang web
function fetch_url($url, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // auto giải nén nội dung gzip/deflate
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result = ['success' => true, 'http_code' => $http_code, 'response' => $response];
    if ($http_code == 403 || $http_code == 503) {
        $result['success'] = false;
        $result['message'] = "Bị chặn bởi Cloudflare hoặc CAPTCHA. Vui lòng thử lại sau.";
    } elseif (curl_errno($ch)) {
        $result['success'] = false;
        $result['message'] = "Lỗi cURL: " . curl_error($ch);
    }
    curl_close($ch);
    return $result;
}

// Hàm thu thập một chương truyện
function one_chapter($web, $headers, $output_dir) {
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

    // Download images
    foreach ($img_links as $index => $link) {
        $result['messages'][] = "Đang tải hình ảnh: $link";
        $file = "$folder/image_$index.jpg";
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

// Hàm thu thập tất cả các chương của truyện
function all_chapters($web, $headers, $domain, $output_dir, $parts_only = false, $part_start = 0, $part_end = 0) {
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
    $result['messages'][] = "Danh sách chương: " . json_encode($chapters);
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
        for ($i = $part_start - 1; $i < $part_end && $i < count($chapters); $i++) {
            $chapter_result = one_chapter($chapters[$i], $headers, $folder);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    } else {
        foreach ($chapters as $link) {
            $chapter_result = one_chapter($link, $headers, $folder);
            $result['success'] = $result['success'] && $chapter_result['success'];
            $result['messages'] = array_merge($result['messages'], $chapter_result['messages']);
        }
    }
    return $result;
}

// Xử lý yêu cầu AJAX từ giao diện
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crawl_manga') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];

    $web = isset($_POST['web']) ? trim($_POST['web']) : '';
    $choose = isset($_POST['choose']) ? strtoupper(trim($_POST['choose'])) : '';
    $part_start = isset($_POST['part_start']) ? (int)$_POST['part_start'] : 0;
    $part_end = isset($_POST['part_end']) ? (int)$_POST['part_end'] : 0;

    if (empty($web)) {
        $response['messages'][] = "Vui lòng nhập đường link của truyện.";
        echo json_encode($response);
        exit;
    }

    $parsed_url = parse_url($web);
    $domain = "https://" . ($parsed_url['host'] ?? 'truyenqqgo.com');
    $referer = $domain . "/";
    $response['messages'][] = "Server: $referer";

    $headers = [
        "Connection: keep-alive",
        "Cache-Control: max-age=0",
        "Upgrade-Insecure-Requests: 1",
        "User-Agent: " . $user_agents[array_rand($user_agents)],
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
        "Accept-Encoding: gzip, deflate",
        "Accept-Language: en-US,en;q=0.9,fr;q=0.8",
        "Referer: $referer"
    ];

    if (strpos($web, 'chap') !== false) {
        $response['messages'][] = "Có vẻ đây là link của 1 chap đơn. Tiến hành tải...";
        $output_dir = __DIR__ . '/downloads';
        $result = one_chapter($web, $headers, $output_dir);
        $response['success'] = $result['success'];
        $response['messages'] = array_merge($response['messages'], $result['messages']);
    } else {
        $response['messages'][] = "Có vẻ như đây là đường link của cả một truyện.";
        if ($choose === 'T') {
            $response['messages'][] = "Bạn đã chọn tải toàn bộ các chương truyện.";
            $response['messages'][] = "Tiến hành tải tất cả chương mà truyện hiện có...";
            $result = all_chapters($web, $headers, $domain, __DIR__ . '/downloads');
            $response['success'] = $result['success'];
            $response['messages'] = array_merge($response['messages'], $result['messages']);
        } elseif ($choose === 'M') {
            $response['messages'][] = "Bạn đã chọn tải một phần của truyện.";
            $response['messages'][] = "Tiến hành tải các chương từ $part_start đến $part_end...";
            $result = all_chapters($web, $headers, $domain, __DIR__ . '/downloads', true, $part_start, $part_end);
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
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Font: Source Sans Pro -->
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
    <!-- Navbar -->
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
    <!-- /.navbar -->

    <!-- Main Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
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
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Crawl Truyện từ TruyenQQ</h3>
                            </div>
                            <div class="card-body">
                                <form id="crawlForm">
                                    <div class="form-group">
                                        <label for="web">Nhập đường link của truyện:</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-link"></i></span>
                                            </div>
                                            <input type="text" class="form-control" id="web" name="web" placeholder="https://truyenqqgo.com/truyen-tranh/ten-truyen" required>
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
                                        <label>Ví dụ: Đầu: 60, Cuối: 100 sẽ tải các chương từ 60 đến 100.</label>
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
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo HOME_URL; ?>">TruyenGG</a>.</strong>
        All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    $('input[name="choose"]').on('change', function() {
        $('#part_range').toggle(this.value === 'M');
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
                $('.progress-bar').css('width', '50%').text('50%');  
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
            error: function() {
                $('.progress').hide();
                crawlButton.prop('disabled', false).html('<i class="fas fa-download"></i> Bắt đầu tải');
                $('#crawlOutput').html('<div class="alert alert-danger">Đã xảy ra lỗi khi crawl truyện.</div>');
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });
});
</script>
</body>
</html>

