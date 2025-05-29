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
        throw new Exception('Vui lòng đăng nhập để xóa lịch sử.');
    }

    if (!$conn) {
        throw new Exception('Lỗi kết nối cơ sở dữ liệu.');
    }

    $action = $_POST['action'] ?? '';
    $comic_id = isset($_POST['comic_id']) ? (int)$_POST['comic_id'] : 0;
    $user_id = (int)$_SESSION['user_id'];

    if ($action !== 'delete_history' || $comic_id <= 0) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }

    // Xóa lịch sử đọc
    $stmt = $conn->prepare("DELETE FROM reading_history WHERE user_id = ? AND comic_id = ?");
    $stmt->bind_param("ii", $user_id, $comic_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Đã xóa lịch sử đọc thành công.';
    } else {
        $response['message'] = 'Không tìm thấy lịch sử đọc.';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>