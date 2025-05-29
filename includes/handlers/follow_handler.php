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
        throw new Exception('Vui lòng đăng nhập để theo dõi.');
    }

    if (!$conn) {
        throw new Exception('Lỗi kết nối cơ sở dữ liệu.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $comic_id = isset($_POST['comic_id']) ? (int)$_POST['comic_id'] : 0;
    $user_id = (int)$_SESSION['user_id'];

    if ($action !== 'toggle_follow' || $comic_id <= 0) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }

    // Kiểm tra comic_id tồn tại
    $stmt = $conn->prepare("SELECT id FROM comics WHERE id = ?");
    $stmt->bind_param("i", $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Truyện không tồn tại.');
    }
    $stmt->close();

    // Kiểm tra trạng thái theo dõi
    $stmt = $conn->prepare("SELECT id FROM user_follows WHERE user_id = ? AND comic_id = ?");
    $stmt->bind_param("ii", $user_id, $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_following = $result->num_rows > 0;
    $stmt->close();

    if ($is_following) {
        // Hủy theo dõi
        $stmt = $conn->prepare("DELETE FROM user_follows WHERE user_id = ? AND comic_id = ?");
        $stmt->bind_param("ii", $user_id, $comic_id);
        $stmt->execute();
        $stmt->close();
        $response['message'] = 'Đã hủy theo dõi.';
    } else {
        // Theo dõi
        $stmt = $conn->prepare("INSERT INTO user_follows (user_id, comic_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $comic_id);
        $stmt->execute();
        $stmt->close();
        $response['message'] = 'Đã theo dõi.';
    }

    // Đếm số người theo dõi
    $stmt = $conn->prepare("SELECT COUNT(*) as follow_count FROM user_follows WHERE comic_id = ?");
    $stmt->bind_param("i", $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['follow_count'] = $result->fetch_assoc()['follow_count'];
    $response['is_following'] = !$is_following;
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>