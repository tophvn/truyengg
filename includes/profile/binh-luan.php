<?php
require_once '../../config/routes.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . HOME_URL);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch comments
$stmt = $conn->prepare("
    SELECT c.id, c.content, c.created_at, cm.title AS comic_title
    FROM comments c
    LEFT JOIN comics cm ON c.comic_id = cm.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$comments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="col-md-12 payment-history">
    <div class="payment-info">
        <div class="history-button">
            <a href="<?php echo RECHARGE_URL; ?>" title="Nạp Xu">Nạp Xu</a>
            <a href="<?php echo RECHARGE_HISTORY_URL; ?>" title="Lịch Sử Nạp Xu">Lịch Sử Nạp Xu</a>
            <a href="<?php echo PAYMENT_HISTORY_URL; ?>" title="Lịch Sử Thanh Toán">Lịch Sử Thanh Toán</a>
            <a class="active" href="<?php echo ACCOUNT_SETTINGS_URL; ?>?section=binh-luan" title="Bình Luận">Bình Luận</a>
        </div>
    </div>
    <div class="text-center">
        <h1>Bình Luận</h1><br>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <table class="table table-bordered" id="example">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Truyện</th>
                        <th>Nội dung</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comments)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Bạn chưa có bình luận nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comment['id']); ?></td>
                                <td><?php echo htmlspecialchars($comment['comic_title'] ?: 'Truyện không tồn tại'); ?></td>
                                <td><?php echo htmlspecialchars($comment['content']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="clear"></div>
        </div>
    </div>
    <br>
</div>
<div class="clear"></div>