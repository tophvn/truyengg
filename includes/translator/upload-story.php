<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || 
    !($_SESSION['roles'] === 'admin' || $_SESSION['roles'] === 'translator')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Validate input
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$categories = $_POST['categories'] ?? [];
$status = $_POST['status'] ?? 'ongoing';

if (empty($title) || empty($description) || empty($categories)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
    exit;
}

// Generate slug
$slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title));
$slug = trim($slug, '-');

// Handle file upload
$thumbnail_path = '';
if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = dirname(__DIR__, 2) . '/uploads/covers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $thumbnail_name = uniqid() . '_' . basename($_FILES['thumbnail']['name']);
    $thumbnail_path = $upload_dir . $thumbnail_name;
    if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbnail_path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi khi tải ảnh bìa']);
        exit;
    }
    $thumbnail_path = '/truyengg/uploads/covers/' . $thumbnail_name;
}

// Save to database
try {
    $categories_str = implode(',', $categories); // Store as comma-separated string
    $stmt = $conn->prepare("INSERT INTO stories (title, slug, description, thumbnail, categories, status, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssssssi', $title, $slug, $description, $thumbnail_path, $categories_str, $status, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Đăng truyện thành công']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>