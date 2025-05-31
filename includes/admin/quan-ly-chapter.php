<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || 
    !in_array($_SESSION['roles'], ['admin', 'translator'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Function to fetch setting value from settings table
function get_setting_value($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $value = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
    $stmt->close();
    return $value;
}

$comic_id = (int)($_GET['comic_id'] ?? 0);
$comic_name = '';
$chapters = [];

if ($comic_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM comics WHERE id = ?");
    $stmt->bind_param('i', $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $comic_name = $result->fetch_assoc()['name'];
    } else {
        $comic_name = 'Truyện không tồn tại';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, chapter_name, chapter_title, imgur_album_link, created_at FROM chapters WHERE comic_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $chapters[] = $row;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_chapter') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];
    try {
        $chapter_id = (int)($_POST['chapter_id'] ?? 0);
        $comic_id = (int)($_POST['comic_id'] ?? 0); // Use POST comic_id
        $chapter_name = trim($_POST['chapter_name'] ?? '');

        // Log input values for debugging
        error_log("Edit chapter attempt: chapter_id=$chapter_id, comic_id=$comic_id, chapter_name=$chapter_name");

        // Validate inputs
        if ($chapter_id <= 0) {
            $response['messages'][] = 'ID chapter không hợp lệ.';
            error_log("Invalid chapter_id: $chapter_id");
            echo json_encode($response);
            exit;
        }
        if ($comic_id <= 0) {
            $response['messages'][] = 'ID comic không hợp lệ.';
            error_log("Invalid comic_id: $comic_id");
            echo json_encode($response);
            exit;
        }
        if (empty($chapter_name)) {
            $response['messages'][] = 'Tên số (chỉ số) là bắt buộc.';
            echo json_encode($response);
            exit;
        }
        // Validate chapter_name format (numbers and optional single decimal point)
        if (!preg_match('/^\d+(\.\d+)?$/', $chapter_name)) {
            $response['messages'][] = 'Tên số (chỉ số) chỉ được chứa số và một dấu chấm (ví dụ: 1, 1.2).';
            echo json_encode($response);
            exit;
        }

        // Verify chapter exists
        $stmt = $conn->prepare("SELECT id FROM chapters WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('ii', $chapter_id, $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Chapter không tồn tại hoặc không thuộc comic này.';
            error_log("Chapter not found: chapter_id=$chapter_id, comic_id=$comic_id");
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $conn->begin_transaction();

        // Update chapter_name only
        $stmt = $conn->prepare("UPDATE chapters SET chapter_name = ? WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('sii', $chapter_name, $chapter_id, $comic_id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            if ($stmt->error) {
                throw new Exception('Không thể cập nhật chapter: ' . $stmt->error);
            } else {
                $response['messages'][] = 'Không có thay đổi nào được áp dụng cho tên số (chỉ số).';
                $conn->rollback();
                echo json_encode($response);
                $stmt->close();
                exit;
            }
        }
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Cập nhật tên số (chỉ số) thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
        error_log("Edit chapter error: " . $e->getMessage() . " | chapter_id: $chapter_id, comic_id: $comic_id, chapter_name: $chapter_name");
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_chapter') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'messages' => []];
    try {
        $chapter_id = (int)($_POST['chapter_id'] ?? 0);
        $comic_id = (int)($_POST['comic_id'] ?? $_GET['comic_id'] ?? 0);

        if ($chapter_id <= 0 || $comic_id <= 0) {
            $response['messages'][] = 'ID chapter hoặc comic không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM comics WHERE id = ?");
        $stmt->bind_param('i', $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Comic không tồn tại.';
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT id FROM chapters WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('ii', $chapter_id, $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $response['messages'][] = 'Chapter không tồn tại hoặc không thuộc comic này.';
            $stmt->close();
            echo json_encode($response);
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM chapter_images WHERE chapter_id = ?");
        $stmt->bind_param('i', $chapter_id);
        $stmt->execute();
        if ($stmt->error) {
            throw new Exception('Không thể xóa ảnh chapter: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM chapters WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('ii', $chapter_id, $comic_id);
        $stmt->execute();
        if ($stmt->affected_rows === 0 && $stmt->error) {
            throw new Exception('Không thể xóa chapter: ' . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Xóa chapter thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
        error_log("Delete chapter error: " . $e->getMessage());
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'get_chapter_images') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'image_urls' => [], 'messages' => []];
    try {
        $chapter_id = (int)($_POST['chapter_id'] ?? 0);
        if ($chapter_id <= 0) {
            $response['messages'][] = 'ID chapter không hợp lệ.';
            echo json_encode($response);
            exit;
        }
        $stmt = $conn->prepare("SELECT image_page FROM chapter_images WHERE chapter_id = ? ORDER BY image_order ASC");
        $stmt->bind_param('i', $chapter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $image_urls = [];
        while ($row = $result->fetch_assoc()) {
            $image_urls[] = $row['image_page'];
        }
        $stmt->close();
        $response['success'] = true;
        $response['image_urls'] = $image_urls;
    } catch (Exception $e) {
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
        error_log("Get chapter images error: " . $e->getMessage());
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'upload_image') {
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'url' => '', 'message' => ''];
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('Không có ảnh được tải lên.');
        }
        $file = $_FILES['image'];
        $maxSize = 32 * 1024 * 1024; // 32MB
        $validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $validTypes)) {
            throw new Exception('Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPEG, PNG, GIF.');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('Kích thước ảnh vượt quá 32MB.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Lỗi tải lên: ' . $file['error']);
        }

        $apiKey = get_setting_value($conn, 'imgbb_api_key');
        if (empty($apiKey)) {
            throw new Exception('Khóa API ImgBB không được cấu hình trong bảng settings.');
        }
        $url = 'https://api.imgbb.com/1/upload';
        $post = [
            'image' => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
            'key' => $apiKey
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($result, true);
        if ($httpCode === 200 && isset($data['success']) && $data['success']) {
            $response['success'] = true;
            $response['url'] = $data['data']['url'];
        } else {
            $message = isset($data['error']['message']) ? $data['error']['message'] : 'Lỗi không xác định từ ImgBB.';
            throw new Exception($message);
        }
    } catch (Exception $e) {
        $response['message'] = 'Lỗi: ' . $e->getMessage();
        error_log("Image upload error: " . $e->getMessage());
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
    <title>TruyenGG - Quản lý Chapter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .main-header { background-color: #ffffff; border-bottom: 1px solid #dee2e6; }
        .content-wrapper { background-color: #f4f6f9; }
        .card { margin-bottom: 20px; }
        #chapterOutput { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; }
        .progress { height: 25px; }
        .action-buttons { display: flex; gap: 5px; }
        .image-link { word-break: break-all; }
        .image-entry { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 5px; background: #f8f9fa; border-radius: 4px; }
        .image-entry input { flex: 1; }
        .remove-image-btn { color: red; cursor: pointer; }
        .drag-handle { cursor: move; color: #6c757d; margin-right: 5px; }
        #image-list { min-height: 50px; }
        .image-entry.ui-sortable-helper { opacity: 0.8; border: 1px dashed #007bff; }
        .image-upload-btn { position: relative; overflow: hidden; }
        .image-upload-btn input[type="file"] { position: absolute; top: 0; right: 0; margin: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .ui-state-highlight { height: 50px; background: #e9ecef; border: 1px dashed #007bff; }
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
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php include 'sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Quản lý Chapter - <?php echo htmlspecialchars($comic_name); ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo HOME_URL; ?>">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>includes/admin/stories.php">Quản lý truyện</a></li>
                            <li class="breadcrumb-item active">Quản lý Chapter</li>
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
                                <h3 class="card-title">Danh sách Chapters</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($comic_id <= 0 || $comic_name === 'Truyện không tồn tại'): ?>
                                    <div class="alert alert-danger">Không tìm thấy truyện.</div>
                                <?php else: ?>
                                    <input type="hidden" id="comic_id" value="<?php echo $comic_id; ?>">
                                    <table id="chaptersTable" class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tên số</th>
                                                <th>Tiêu đề</th>
                                                <th>Ảnh đại diện</th>
                                                <th>Ngày tạo</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($chapters)): ?>
                                                <tr><td colspan="6" class="text-center">Chưa có chapter nào.</td></tr>
                                            <?php else: foreach ($chapters as $chapter): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($chapter['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($chapter['chapter_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($chapter['chapter_title']); ?></td>
                                                    <td class="image-link">
                                                        <?php echo $chapter['imgur_album_link'] ? 
                                                            '<a href="' . htmlspecialchars($chapter['imgur_album_link']) . '" target="_blank">' . htmlspecialchars($chapter['imgur_album_link']) . '</a>' : 
                                                            'Không có link'; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($chapter['created_at']))); ?></td>
                                                    <td><div class="action-buttons">
                                                        <button type="button" class="btn btn-sm btn-warning edit-chapter-btn" 
                                                                data-toggle="modal" data-target="#editChapterModal"
                                                                data-chapter-id="<?php echo $chapter['id']; ?>"
                                                                data-chapter-name="<?php echo htmlspecialchars($chapter['chapter_name']); ?>"
                                                                data-chapter-title="<?php echo htmlspecialchars($chapter['chapter_title']); ?>"
                                                                data-comic-id="<?php echo $comic_id; ?>">
                                                            <i class="fas fa-edit"></i> Sửa
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-chapter-btn"
                                                                data-chapter-id="<?php echo $chapter['id']; ?>"
                                                                data-chapter-title="<?php echo htmlspecialchars($chapter['chapter_title']); ?>"
                                                                data-comic-id="<?php echo $comic_id; ?>">
                                                            <i class="fas fa-trash"></i> Xóa
                                                        </button>
                                                    </div></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <div class="modal fade" id="editChapterModal" tabindex="-1" role="dialog" aria-labelledby="editChapterModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editChapterModalLabel">Sửa Chapter</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body">
                    <form id="editChapterForm">
                        <input type="hidden" id="edit_chapter_id" name="chapter_id">
                        <input type="hidden" id="edit_comic_id" name="comic_id">
                        <div class="form-group">
                            <label for="edit_chapter_name">Tên số (chỉ số)</label>
                            <input type="text" class="form-control" id="edit_chapter_name" name="chapter_name" required pattern="^\d+(\.\d+)?$" title="Chỉ được nhập số và một dấu chấm (ví dụ: 1, 1.2)">
                        </div>
                        <div class="form-group">
                            <label for="edit_chapter_title">Tiêu đề</label>
                            <input type="text" class="form-control" id="edit_chapter_title" name="chapter_title" readonly>
                        </div>
                        <div class="form-group">
                            <label>Danh sách URL ảnh</label>
                            <div id="image-list"></div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" id="add-image-btn"><i class="fas fa-plus"></i> Thêm URL ảnh</button>
                                <span class="btn btn-sm btn-success image-upload-btn">
                                    <i class="fas fa-upload"></i> Chọn file
                                    <input type="file" id="image-upload" name="image" accept="image/jpeg,image/png,image/gif" multiple>
                                </span>
                            </div>
                        </div>
                        <div class="progress" style="display: none;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div id="chapterOutput" class="mt-3"></div>
                        <button type="submit" class="btn btn-primary" id="saveEditChapterButton"><i class="fas fa-save"></i> Lưu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo HOME_URL; ?>">TruyenGG</a>.</strong> All rights reserved.
    </footer>
</div>
<script>window.baseUrl = '<?php echo BASE_URL; ?>';</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
$(document).ready(function() {
    $(document).on('click', '.edit-chapter-btn', function() {
        const chapterId = $(this).data('chapter-id');
        const chapterName = $(this).data('chapter-name');
        const chapterTitle = $(this).data('chapter-title');
        const comicId = $(this).data('comic-id');

        $('#edit_chapter_id').val(chapterId);
        $('#edit_comic_id').val(comicId);
        $('#edit_chapter_name').val(chapterName);
        $('#edit_chapter_title').val(chapterTitle);
        $('#chapterOutput').empty();

        console.log('Opening edit modal: chapter_id=', chapterId, 'comic_id=', comicId);

        $.ajax({
            url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
            type: 'POST',
            data: { action: 'get_chapter_images', chapter_id: chapterId },
            dataType: 'json',
            success: function(response) {
                const imageList = $('#image-list');
                imageList.empty();
                if (response.success && response.image_urls && response.image_urls.length > 0) {
                    response.image_urls.forEach((url, index) => {
                        addImageEntry(url, index);
                    });
                } else {
                    addImageEntry('', 0);
                }
                imageList.sortable({
                    handle: '.drag-handle',
                    placeholder: 'ui-state-highlight',
                    update: function() {
                        $('#image-list .image-entry').each(function(index) {
                            $(this).attr('data-index', index);
                        });
                    }
                }).disableSelection();
            },
            error: function(xhr, status, error) {
                $('#chapterOutput').html(`<div class="alert alert-danger">Lỗi tải danh sách URL ảnh: ${xhr.status} ${error}</div>`);
                addImageEntry('', 0);
            }
        });
    });

    function addImageEntry(url, index) {
        const imageList = $('#image-list');
        const entry = $(`
            <div class="image-entry" data-index="${index}">
                <i class="fas fa-grip-vertical drag-handle"></i>
                <input type="text" class="form-control" name="image_urls[]" value="${url}" placeholder="URL ảnh từ Imgur hoặc ImgBB" readonly>
                <i class="fas fa-trash remove-image-btn"></i>
            </div>
        `);
        imageList.append(entry);
    }

    $('#add-image-btn').on('click', function() {
        const imageList = $('#image-list');
        const index = imageList.children().length;
        addImageEntry('', index);
    });

    $(document).on('click', '.remove-image-btn', function() {
        $(this).closest('.image-entry').remove();
        $('#image-list .image-entry').each(function(index) {
            $(this).attr('data-index', index);
        });
    });

    $('#image-upload').on('change', function(e) {
        const files = e.target.files;
        const maxSize = 32 * 1024 * 1024; // 32MB
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];

        $('#chapterOutput').empty();
        $('.progress').show().find('.progress-bar').css('width', '0%').text('0%');

        Array.from(files).forEach((file, index) => {
            if (!validTypes.includes(file.type)) {
                $('#chapterOutput').append(`<div class="alert alert-danger">File ${file.name} không phải định dạng ảnh hợp lệ (JPEG, PNG, GIF).</div>`);
                return;
            }
            if (file.size > maxSize) {
                $('#chapterOutput').append(`<div class="alert alert-danger">File ${file.name} vượt quá kích thước 32MB.</div>`);
                return;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('action', 'upload_image');

            $.ajax({
                url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                beforeSend: () => {
                    const progress = ((index + 1) / files.length) * 50;
                    $('.progress-bar').css('width', `${progress}%`).text(`${Math.round(progress)}%`);
                },
                success: function(response) {
                    if (response.success) {
                        const imageList = $('#image-list');
                        const lastInput = imageList.find('input[name="image_urls[]"]').last();
                        if (lastInput.length && !lastInput.val()) {
                            lastInput.val(response.url);
                        } else {
                            const index = imageList.children().length;
                            addImageEntry(response.url, index);
                        }
                        $('#chapterOutput').append(`<div class="alert alert-success">Đã tải lên ${file.name}: ${response.url}</div>`);
                    } else {
                        $('#chapterOutput').append(`<div class="alert alert-danger">Lỗi tải lên ${file.name}: ${response.message}</div>`);
                    }
                    const progress = 50 + (((index + 1) / files.length) * 50);
                    $('.progress-bar').css('width', `${progress}%`).text(`${Math.round(progress)}%`);
                    if (index === files.length - 1) {
                        setTimeout(() => $('.progress').hide(), 1000);
                    }
                },
                error: function(xhr, status, error) {
                    $('#chapterOutput').append(`<div class="alert alert-danger">Lỗi tải lên ${file.name}: ${xhr.status} ${error}</div>`);
                    $('.progress').hide();
                }
            });
        });

        $(this).val('');
    });

    $('#editChapterForm').on('submit', function(e) {
        e.preventDefault();

        // Client-side validation for chapter_name
        const chapterName = $('#edit_chapter_name').val().trim();
        const chapterNameRegex = /^\d+(\.\d+)?$/;
        if (!chapterNameRegex.test(chapterName)) {
            $('#chapterOutput').html(`<div class="alert alert-danger">Tên số (chỉ số) chỉ được chứa số và một dấu chấm (ví dụ: 1, 1.2).</div>`);
            return;
        }

        const chapterId = $('#edit_chapter_id').val();
        const comicId = $('#edit_comic_id').val();
        console.log('Submitting edit: chapter_id=', chapterId, 'comic_id=', comicId, 'chapter_name=', chapterName);

        const saveButton = $('#saveEditChapterButton');
        saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');
        $('.progress').show().find('.progress-bar').css('width', '0%').text('0%');
        $('#chapterOutput').empty();

        const formData = new FormData();
        formData.append('action', 'edit_chapter');
        formData.append('chapter_id', chapterId);
        formData.append('comic_id', comicId);
        formData.append('chapter_name', chapterName);

        $.ajax({
            url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: () => $('.progress-bar').css('width', '10%').text('10%'),
            success: response => {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(() => $('.progress').hide(), 1000);
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');
                $('#chapterOutput').html(`<div class="alert alert-${response.success ? 'success' : 'danger'}">${response.messages.join('<br>')}</div>`);
                if (response.success) {
                    setTimeout(() => {
                        $('#editChapterModal').modal('hide');
                        location.reload();
                    }, 1000);
                }
                $('#chapterOutput').scrollTop($('#chapterOutput')[0].scrollHeight);
            },
            error: (xhr, status, error) => {
                $('.progress').hide();
                saveButton.prop('disabled', false).html('<i class="fas fa-save"></i> Lưu');
                $('#chapterOutput').html(`<div class="alert alert-danger">Lỗi: ${xhr.status} ${error}</div>`);
                $('.progress-bar').css('width', '0%').text('0%');
                console.error('Edit chapter AJAX error:', status, error);
            }
        });
    });

    $(document).on('click', '.delete-chapter-btn', function() {
        const chapterId = $(this).data('chapter-id');
        const chapterTitle = $(this).data('chapter-title');
        const comicId = $(this).data('comic-id');
        
        Swal.fire({
            title: 'Xác nhận xóa',
            text: `Bạn có chắc chắn muốn xóa chapter "${chapterTitle}"? Hành động này không thể hoàn tác.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
                    type: 'POST',
                    data: { 
                        action: 'delete_chapter', 
                        chapter_id: chapterId,
                        comic_id: comicId
                    },
                    dataType: 'json',
                    beforeSend: () => {
                        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xóa...');
                    },
                    success: function(response) {
                        $(this).prop('disabled', false).html('<i class="fas fa-trash"></i> Xóa');
                        Swal.fire({
                            title: response.success ? 'Thành công!' : 'Lỗi!',
                            text: response.success ? 'Xóa chapter thành công!' : response.messages.join(', '),
                            icon: response.success ? 'success' : 'error',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            if (response.success) {
                                location.reload();
                            }
                        });
                    },
                    error: function(xhr, status, error) {
                        $(this).prop('disabled', false).html('<i class="fas fa-trash"></i> Xóa');
                        Swal.fire({
                            title: 'Lỗi hệ thống',
                            text: `Lỗi: ${xhr.status} ${error}`,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>