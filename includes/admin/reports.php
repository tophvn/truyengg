<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

// Security check: Allow admin or translator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || 
    !($_SESSION['roles'] === 'admin' || $_SESSION['roles'] === 'translator')) {
    header('Location: ' . HOME_URL);
    exit;
}

// Fetch error reports from database
$reports = [];
$user_id = $_SESSION['user_id'];
$query = "
    SELECT 
        ce.id AS error_id,
        ce.error_type,
        ce.error_description,
        ce.created_at,
        c.name AS comic_name,
        c.slug AS comic_slug,
        ch.chapter_name,
        u.username
    FROM chapter_errors ce
    JOIN chapters ch ON ce.chapter_id = ch.id
    JOIN comics c ON ch.comic_id = c.id
    JOIN users u ON ce.user_id = u.id
    ORDER BY ce.created_at DESC
";
if ($_SESSION['roles'] === 'translator') {
    // Placeholder: Filter reports for translators (e.g., via comic_translators table)
    // Example: JOIN comic_translators ct ON c.id = ct.comic_id WHERE ct.user_id = ?
    // For now, translators see all reports
}
$stmt = $conn->prepare($query);
if ($_SESSION['roles'] === 'translator') {
    // If filtering is implemented, bind $user_id here
    // $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();

// Map error types to human-readable text
$error_types = [
    '1' => 'Ảnh lỗi, không thấy ảnh',
    '2' => 'Chương bị trùng',
    '3' => 'Chương chưa dịch',
    '4' => 'Up sai truyện',
    '-1' => 'Lỗi khác'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Quản lý báo lỗi</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        .card { margin-bottom: 20px; }
        .error-description {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .error-description:hover {
            white-space: normal;
            overflow: visible;
        }
    </style>
</head>
<body class="hold-transition layout-fixed">
<?php include __DIR__ . '/sidebar.php'; ?>

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

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Quản lý báo lỗi</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo HOME_URL; ?>">Home</a></li>
                            <li class="breadcrumb-item active">Quản lý báo lỗi</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Error Reports List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Danh sách báo lỗi</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Truyện</th>
                                            <th>Chương</th>
                                            <th>Loại lỗi</th>
                                            <th>Mô tả</th>
                                            <th>Người báo</th>
                                            <th>Thời gian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reports)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Chưa có báo lỗi nào.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reports as $report): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($report['error_id']); ?></td>
                                                    <td>
                                                        <a href="<?php echo CHAPTER_URL; ?>?slug=<?php echo htmlspecialchars($report['comic_slug']); ?>&chapter=<?php echo htmlspecialchars($report['chapter_name']); ?>">
                                                            <?php echo htmlspecialchars($report['comic_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($report['chapter_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($error_types[$report['error_type']] ?? 'Không xác định'); ?></td>
                                                    <td class="error-description" title="<?php echo htmlspecialchars($report['error_description']); ?>">
                                                        <?php echo htmlspecialchars($report['error_description']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($report['username']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($report['created_at']))); ?></td>
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
</body>
</html>