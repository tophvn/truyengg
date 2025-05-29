<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Security check: Ensure only admin users can access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || $_SESSION['roles'] !== 'admin') {
    header('Location: /truyengg/index.php');
    exit;
}

// Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/truyengg';

// Handle AJAX form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        if ($_POST['action'] === 'save_google_settings') {
            $google_client_id = trim($_POST['google_client_id'] ?? '');
            $google_client_secret = trim($_POST['google_client_secret'] ?? '');
            $google_redirect_uri = trim($_POST['google_redirect_uri'] ?? '');
            $turnstile_secret_key = trim($_POST['turnstile_secret_key'] ?? '');

            if (empty($google_client_id) || empty($google_client_secret) || empty($google_redirect_uri) || empty($turnstile_secret_key)) {
                throw new Exception('Vui lòng điền đầy đủ tất cả các trường.');
            }

            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            if (!$stmt) {
                throw new Exception('Lỗi hệ thống khi lưu cài đặt.');
            }

            // Save Google Client ID
            $description = 'Google API Client ID';
            $stmt->bind_param("ssss", $key, $value, $description, $value);
            $key = 'google_client_id';
            $value = $google_client_id;
            $stmt->execute();

            // Save Google Client Secret
            $description = 'Google API Client Secret';
            $key = 'google_client_secret';
            $value = $google_client_secret;
            $stmt->execute();

            // Save Google Redirect URI
            $description = 'Google API Redirect URI';
            $key = 'google_redirect_uri';
            $value = $google_redirect_uri;
            $stmt->execute();

            // Save Turnstile Secret Key
            $description = 'Cloudflare Turnstile Secret Key';
            $key = 'turnstile_secret_key';
            $value = $turnstile_secret_key;
            $stmt->execute();

            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'Cập nhật cài đặt Google API và CAPTCHA thành công.';
        } elseif ($_POST['action'] === 'save_hot_comics') {
            $hot_comics = isset($_POST['hot_comics']) && is_array($_POST['hot_comics']) ? $_POST['hot_comics'] : [];

            // Reset all is_hot to 0
            $conn->query("UPDATE comics SET is_hot = 0");

            // Set is_hot to 1 for selected comics (up to 10)
            if (!empty($hot_comics)) {
                $hot_comics = array_slice($hot_comics, 0, 10); // Giới hạn 10 truyện
                $placeholders = rtrim(str_repeat('?,', count($hot_comics)), ',');
                $stmt = $conn->prepare("UPDATE comics SET is_hot = 1 WHERE id IN ($placeholders)");
                if (!$stmt) {
                    throw new Exception('Lỗi hệ thống khi lưu truyện hot.');
                }
                $stmt->bind_param(str_repeat('i', count($hot_comics)), ...$hot_comics);
                $stmt->execute();
                $stmt->close();
            }

            $response['success'] = true;
            $response['message'] = 'Cập nhật danh sách truyện hot thành công.';
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    $conn->close();
    echo json_encode($response);
    exit;
}

// Fetch current settings
$settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri', 'turnstile_secret_key')";
$settings_result = $conn->query($settings_query);
$settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch all comics for selection
$comics_query = "SELECT id, name, is_hot FROM comics ORDER BY name ASC";
$comics_result = $conn->query($comics_query);
$comics = [];
if ($comics_result) {
    while ($row = $comics_result->fetch_assoc()) {
        $comics[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Cài đặt hệ thống</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        .card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            transition: background-color 0.2s;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        /* Select2 custom styles */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 38px;
            background-color: #fff;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding: 2px 5px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff;
            color: #fff;
            border: 1px solid #0056b3;
            border-radius: 4px;
            padding: 2px 8px;
            margin: 2px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 5px;
        }
        .select2-container--default .select2-search--inline .select2-search__field {
            margin: 5px;
            width: 100% !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #007bff;
            color: #fff;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #e9ecef;
            color: #212529;
        }
        /* Ensure search input is visible */
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px;
            width: 100%;
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
                <a href="<?php echo $base_url; ?>/includes/admin/index.php" class="nav-link">Home</a>
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

    <!-- Main Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Cài đặt hệ thống</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/includes/admin/index.php">Home</a></li>
                            <li class="breadcrumb-item active">Cài đặt</li>
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
                    <!-- Google API & CAPTCHA Settings -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title text-primary fw-bold fs-4">Cấu hình Google API & CAPTCHA</h3>
                            </div>
                            <div class="card-body">
                                <form id="googleSettingsForm">
                                    <input type="hidden" name="action" value="save_google_settings">
                                    <div class="form-group">
                                        <label for="google_client_id">Google Client ID</label>
                                        <input type="text" class="form-control" id="google_client_id" name="google_client_id" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="google_client_secret">Google Client Secret</label>
                                        <input type="text" class="form-control" id="google_client_secret" name="google_client_secret" value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="google_redirect_uri">Google Redirect URI</label>
                                        <input type="url" class="form-control" id="google_redirect_uri" name="google_redirect_uri" value="<?php echo htmlspecialchars($settings['google_redirect_uri'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="turnstile_secret_key">Cloudflare Turnstile Secret Key</label>
                                        <input type="text" class="form-control" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo htmlspecialchars($settings['turnstile_secret_key'] ?? ''); ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Lưu cài đặt</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Hot Comics Settings -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title text-primary fw-bold fs-4">Chọn Truyện Hot</h3>
                            </div>
                            <div class="card-body">
                                <form id="hotComicsForm">
                                    <input type="hidden" name="action" value="save_hot_comics">
                                    <div class="form-group">
                                        <label for="hot_comics">Chọn truyện hot (tối đa 10 truyện)</label>
                                        <select class="form-control select2" id="hot_comics" name="hot_comics[]" multiple="multiple" data-placeholder="Tìm và chọn truyện">
                                            <?php foreach ($comics as $comic): ?>
                                                <option value="<?php echo $comic['id']; ?>" <?php echo $comic['is_hot'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($comic['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Lưu danh sách truyện hot</button>
                                </form>
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
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 with search
    $('.select2').select2({
        maximumSelectionLength: 10,
        placeholder: "Tìm và chọn truyện",
        allowClear: true,
        minimumResultsForSearch: 1, // Enable search
        width: '100%',
        dropdownCssClass: 'custom-select2-dropdown',
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            return $('<span>' + data.text + '</span>');
        },
        templateSelection: function(data) {
            return data.text || data.id;
        }
    });

    // Handle Google Settings Form
    $('#googleSettingsForm').on('submit', function(e) {
        e.preventDefault();
        var $button = $(this).find('button[type="submit"]');
        var originalText = $button.text();
        $button.prop('disabled', true).text('Đang lưu...');

        $.ajax({
            url: '<?php echo $base_url; ?>/includes/admin/settings.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $button.prop('disabled', false).text(originalText);
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                alert('Đã xảy ra lỗi khi lưu cài đặt.');
            }
        });
    });

    // Handle Hot Comics Form
    $('#hotComicsForm').on('submit', function(e) {
        e.preventDefault();
        var $button = $(this).find('button[type="submit"]');
        var originalText = $button.text();
        $button.prop('disabled', true).text('Đang lưu...');

        $.ajax({
            url: '<?php echo $base_url; ?>/includes/admin/settings.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $button.prop('disabled', false).text(originalText);
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                alert('Đã xảy ra lỗi khi lưu danh sách truyện hot.');
            }
        });
    });
});
</script>
</body>
</html>
