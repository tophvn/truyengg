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
        $chapter_name = trim($_POST['chapter_name'] ?? '');
        $chapter_title = trim($_POST['chapter_title'] ?? '');
        $image_urls = isset($_POST['image_urls']) && is_array($_POST['image_urls']) ? array_filter(array_map('trim', $_POST['image_urls'])) : [];

        if ($chapter_id <= 0 || empty($chapter_name) || empty($chapter_title)) {
            $response['messages'][] = 'ID chapter, tên chương và tiêu đề là bắt buộc.';
            echo json_encode($response);
            exit;
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE chapters SET chapter_name = ?, chapter_title = ? WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('ssii', $chapter_name, $chapter_title, $chapter_id, $comic_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM chapter_images WHERE chapter_id = ?");
        $stmt->bind_param('i', $chapter_id);
        $stmt->execute();
        $stmt->close();

        if (!empty($image_urls)) {
            $stmt = $conn->prepare("INSERT INTO chapter_images (chapter_id, image_page, image_order) VALUES (?, ?, ?)");
            foreach ($image_urls as $index => $image_url) {
                if (!empty($image_url)) {
                    $image_order = $index + 1;
                    $stmt->bind_param('isi', $chapter_id, $image_url, $image_order);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Cập nhật chapter thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
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
        if ($chapter_id <= 0) {
            $response['messages'][] = 'ID chapter không hợp lệ.';
            echo json_encode($response);
            exit;
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("DELETE FROM chapter_images WHERE chapter_id = ?");
        $stmt->bind_param('i', $chapter_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM chapters WHERE id = ? AND comic_id = ?");
        $stmt->bind_param('ii', $chapter_id, $comic_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['messages'][] = 'Xóa chapter thành công!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['messages'][] = 'Lỗi: ' . $e->getMessage();
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
                                <?php if ($comic_id <= 0 || empty($comic_name)): ?>
                                    <div class="alert alert-danger">Không tìm thấy truyện.</div>
                                <?php else: ?>
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
                                                                data-chapter-title="<?php echo htmlspecialchars($chapter['chapter_title']); ?>">
                                                            <i class="fas fa-edit"></i> Sửa
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-chapter-btn"
                                                                data-chapter-id="<?php echo $chapter['id']; ?>"
                                                                data-chapter-title="<?php echo htmlspecialchars($chapter['chapter_title']); ?>">
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
                        <div class="form-group">
                            <label for="edit_chapter_name">Tên số (chỉ số)</label>
                            <input type="text" class="form-control" id="edit_chapter_name" name="chapter_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_chapter_title">Tiêu đề</label>
                            <input type="text" class="form-control" id="edit_chapter_title" name="chapter_title" required>
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
<script>
$(document).ready(function() {
    $(document).on('click', '.edit-chapter-btn', function() {
        const chapterId = $(this).data('chapter-id');
        const chapterName = $(this).data('chapter-name');
        const chapterTitle = $(this).data('chapter-title');

        $('#edit_chapter_id').val(chapterId);
        $('#edit_chapter_name').val(chapterName);
        $('#edit_chapter_title').val(chapterTitle);
        $('#chapterOutput').empty();

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
                <input type="text" class="form-control" name="image_urls[]" value="${url}" placeholder="URL ảnh từ Imgur hoặc ImgBB">
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

        // Reset file input
        $(this).val('');
    });

    $('#editChapterForm').on('submit', function(e) {
        e.preventDefault();
        const saveButton = $('#saveEditChapterButton');
        saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');
        $('.progress').show().find('.progress-bar').css('width', '0%').text('0%');
        $('#chapterOutput').empty();
        const formData = new FormData(this);
        formData.append('action', 'edit_chapter');
        $.ajax({
            url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: !1,
            dataType: 'json',
            beforeSend: () => $('.progress-bar').css('width', '10%').text('OK'),
            success: response => {
                $('.progress-bar').css('width', '100%').text('100%');
                setTimeout(() => $('.progress').hide(), 1000);
                saveButton.prop('disabled', !1).html('<i class="fas fa-save"></i> Lưu');
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
                saveButton.prop('disabled', !1).html('<i class="fas fa-save"></i> Lưu');
                $('#chapterOutput').html(`<div class="alert alert-danger">Lỗi: ${xhr.status} ${error}</div>`);
                $('.progress-bar').css('width', '0%').text('0%');
            }
        });
    });

    $(document).on('click', '.delete-chapter-btn', function() {
        const chapter_id = $(this).data('chapter-id');
        const chapter_title = $(this).data('chapter-title');
        if (confirm(`Bạn có chắc chắn muốn xóa chapter "${chapter_title}"?`)) {
            $.ajax({
                url: window.baseUrl + 'includes/admin/quan-ly-chapter.php',
                type: 'POST',
                data: { action: 'delete_chapter', chapter_id: chapter_id },
                dataType: 'json',
                success: response => {
                    alert(response.success ? 'Xóa chapter thành công!' : `Lỗi: ${response.messages.join(', ')}`);
                    if (response.success) location.reload();
                },
                error: (xhr, status, error) => alert(`Lỗi hệ thống: ${xhr.status} ${error}`)
            });
        }
    });
});
</script>
</body>
</html>

