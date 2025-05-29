<?php
session_start();
require_once dirname(__DIR__) . '/../config/database.php';

// Security check: Allow admin or translator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || 
    !($_SESSION['roles'] === 'admin' || $_SESSION['roles'] === 'translator')) {
    header('Location: /truyengg/index.php');
    exit;
}

// Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/truyengg';

// Fetch categories from API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://otruyenapi.com/v1/api/the-loai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$categories = [];
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['data']['items'])) {
        foreach ($data['data']['items'] as $item) {
            $categories[] = [
                'slug' => $item['slug'],
                'name' => $item['name']
            ];
        }
    }
}

// Fetch stories from database
$stories = [];
$user_id = $_SESSION['user_id'];
$query = "SELECT id, title, categories, status, created_at FROM stories WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stories[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Up truyện</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <style>
        .main-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }
        .content-wrapper {
            background-color: #f4f6f9;
        }
        .completed {
            color: green;
            font-weight: bold;
        }
        .ongoing {
            color: orange;
            font-weight: bold;
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="hold-transition layout-fixed">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo $base_url; ?>/includes/stories-user.php" class="nav-link">Up truyện</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/includes/logout.php" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Up truyện</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>">Home</a></li>
                            <li class="breadcrumb-item active">Up truyện</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Upload Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Đăng truyện mới</h3>
                            </div>
                            <div class="card-body">
                                <form id="uploadStoryForm" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="story_title">Tên truyện</label>
                                        <input type="text" class="form-control" id="story_title" name="title" placeholder="Nhập tên truyện" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="story_description">Mô tả</label>
                                        <textarea class="form-control" id="story_description" name="description" rows="4" placeholder="Nhập mô tả truyện" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="story_categories">Thể loại</label>
                                        <select class="form-control" id="story_categories" name="categories[]" multiple required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category['slug']); ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="story_thumbnail">Ảnh bìa</label>
                                        <input type="file" class="form-control-file" id="story_thumbnail" name="thumbnail" accept="image/*">
                                    </div>
                                    <div class="form-group">
                                        <label for="story_status">Trạng thái</label>
                                        <select class="form-control" id="story_status" name="status">
                                            <option value="ongoing">Đang tiến hành</option>
                                            <option value="completed">Hoàn thành</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Đăng truyện</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Story List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Danh sách truyện</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tên truyện</th>
                                            <th>Thể loại</th>
                                            <th>Trạng thái</th>
                                            <th>Ngày tạo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($stories)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Chưa có truyện nào.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($stories as $story): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($story['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($story['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($story['categories'] ?: 'Chưa có thể loại'); ?></td>
                                                    <td>
                                                        <span class="<?php echo $story['status'] === 'completed' ? 'completed' : 'ongoing'; ?>">
                                                            <?php echo $story['status'] === 'completed' ? 'Hoàn thành' : 'Đang tiến hành'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($story['created_at']))); ?></td>
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
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo $base_url; ?>">TruyenGG</a>.</strong>
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
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#story_categories').select2({
        placeholder: "Chọn thể loại",
        allowClear: true
    });

    // Handle form submission
    $('#uploadStoryForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: '<?php echo $base_url; ?>/includes/translator/upload-story.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Đăng truyện thành công!');
                    $('#uploadStoryForm')[0].reset();
                    $('#story_categories').val(null).trigger('change');
                    location.reload();
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Đã xảy ra lỗi: ' + error);
            }
        });
    });
});
</script>
</body>
</html>