<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Phương thức không hợp lệ.');
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Vui lòng đăng nhập để báo lỗi.');
    }

    if (!$conn) {
        throw new Exception('Lỗi kết nối cơ sở dữ liệu.');
    }

    $comic_id = isset($_POST['comic_id']) ? trim($_POST['comic_id']) : '';
    $chapter_name = isset($_POST['chapter_name']) ? trim($_POST['chapter_name']) : '';
    $error_type = isset($_POST['error_type']) ? trim($_POST['error_type']) : '0';
    $error_description = isset($_POST['error_description']) ? trim($_POST['error_description']) : '';
    $user_id = (int)$_SESSION['user_id'];

    if ($error_type === '0') {
        throw new Exception('Vui lòng chọn loại lỗi.');
    }

    if (strlen($error_description) < 10) {
        throw new Exception('Mô tả lỗi phải có ít nhất 10 ký tự.');
    }

    // Tìm comic_id trong bảng comics
    $stmt = $conn->prepare("SELECT id FROM comics WHERE comic_id = ?");
    $stmt->bind_param("s", $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comic_row = $result->fetch_assoc();
    $stmt->close();

    if (!$comic_row) {
        throw new Exception('Truyện không tồn tại.');
    }
    $comic_db_id = $comic_row['id'];

    // Tìm hoặc tạo chapter trong bảng chapters
    $stmt = $conn->prepare("SELECT id FROM chapters WHERE comic_id = ? AND chapter_name = ?");
    $stmt->bind_param("is", $comic_db_id, $chapter_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $chapter_row = $result->fetch_assoc();
    $stmt->close();

    if (!$chapter_row) {
        // Tạo chapter mới nếu không tồn tại
        $stmt = $conn->prepare("
            INSERT INTO chapters (comic_id, chapter_name, chapter_title, chapter_api_data, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $chapter_title = ''; // Có thể lấy từ API nếu cần
        $chapter_api_data = ''; // Cần lấy từ API hoặc để trống
        $stmt->bind_param("isss", $comic_db_id, $chapter_name, $chapter_title, $chapter_api_data);
        $stmt->execute();
        $chapter_id = $conn->insert_id;
        $stmt->close();
    } else {
        $chapter_id = $chapter_row['id'];
    }

    // Lưu báo lỗi
    $stmt = $conn->prepare("
        INSERT INTO chapter_errors (chapter_id, user_id, error_type, error_description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $chapter_id, $user_id, $error_type, $error_description);
    $stmt->execute();
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Báo lỗi thành công. Cảm ơn bạn!';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>