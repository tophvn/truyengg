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
        throw new Exception('Vui lòng đăng nhập để bình luận.');
    }

    if (!$conn) {
        throw new Exception('Lỗi kết nối cơ sở dữ liệu.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $comic_id = isset($_POST['comic_id']) ? (int)$_POST['comic_id'] : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) && (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;
    $user_id = (int)$_SESSION['user_id'];

    if ($action !== 'post_comment' || $comic_id <= 0 || empty($content)) {
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

    // Kiểm tra parent_id hợp lệ (nếu có)
    if ($parent_id !== null) {
        $stmt = $conn->prepare("SELECT id FROM comments WHERE id = ? AND comic_id = ?");
        $stmt->bind_param("ii", $parent_id, $comic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Bình luận cha không tồn tại.');
        }
        $stmt->close();
    }

    // Thêm bình luận
    $stmt = $conn->prepare("
        INSERT INTO comments (user_id, comic_id, parent_id, content, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiss", $user_id, $comic_id, $parent_id, $content);
    $stmt->execute();
    $comment_id = $conn->insert_id;
    $stmt->close();

    // Lấy thông tin bình luận vừa thêm
    $stmt = $conn->prepare("
        SELECT c.id, c.content, c.created_at, c.parent_id, u.username, u.avatar, u.level
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_comment = $result->fetch_assoc();
    $stmt->close();

    // Đếm tổng số bình luận chính
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM comments WHERE comic_id = ? AND parent_id IS NULL");
    $stmt->bind_param("i", $comic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_comments = $result->fetch_assoc()['total'];
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Bình luận đã được gửi.';
    $response['comment'] = [
        'id' => $new_comment['id'],
        'content' => htmlspecialchars($new_comment['content']),
        'created_at' => $new_comment['created_at'],
        'parent_id' => $new_comment['parent_id'],
        'username' => htmlspecialchars($new_comment['username']),
        'avatar' => $new_comment['avatar'] ?? 'https://st.truyengg.net/template/frontend/img/placeholder.jpg',
        'level' => $new_comment['level']
    ];
    $response['total_comments'] = $total_comments;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>