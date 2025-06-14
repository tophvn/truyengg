<?php
require_once 'config/database.php';
require_once 'includes/layouts/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Xử lý form nạp tiền
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = intval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    
    if ($amount < 10000) {
        $error = "Số tiền nạp tối thiểu là 10,000 VNĐ";
    } else {
        // Tạo mã giao dịch
        $transaction_id = uniqid('NAP_');
        
        // Tạo thông tin thanh toán
        $payment_info = [
            'amount' => $amount,
            'momo_number' => '0123456789',
            'bank_account' => '9876543121',
            'bank_name' => 'BIDV',
            'transaction_id' => $transaction_id
        ];
        
        // Lưu thông tin giao dịch vào database
        $sql = "INSERT INTO transactions (user_id, amount, payment_method, transaction_id, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $user_id, $amount, $payment_method, $transaction_id);
        $stmt->execute();
    }
}
?>

<body class="dark-style">
<div class="container container-background bg-dark text-white rounded-lg p-4">
    <div class="detail_acc">
        <div class="fs20 mb-4"><strong>Nạp Xu</strong></div>
        
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active bg-dark text-white" data-toggle="tab" href="#nap-xu">Nạp Xu</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" data-toggle="tab" href="#lich-su-nap">Lịch Sử Nạp Xu</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" data-toggle="tab" href="#lich-su-thanh-toan">Lịch Sử Thanh Toán</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Tab Nạp Xu -->
            <div class="tab-pane fade show active" id="nap-xu">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <form method="POST" action="" class="payment-form">
                            <div class="form-group">
                                <label class="font-weight-bold">Số tiền nạp (VNĐ):</label>
                                <div class="input-group">
                                    <input type="number" name="amount" class="form-control bg-dark text-white" min="10000" step="10000" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-dark text-white">VNĐ</span>
                                    </div>
                                </div>
                                <small class="form-text text-white">Số tiền tối thiểu: 10,000 VNĐ</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="font-weight-bold">Phương thức thanh toán:</label>
                                <div class="payment-methods">
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="momo" name="payment_method" value="momo" class="custom-control-input" checked>
                                        <label class="custom-control-label" for="momo">
                                            MoMo
                                        </label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" id="atm" name="payment_method" value="atm" class="custom-control-input">
                                        <label class="custom-control-label" for="atm">
                                            Chuyển khoản ATM
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                <i class="fas fa-money-bill-wave mr-2"></i>Tiếp tục
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($payment_info)): ?>
                <div class="card mt-4 bg-dark text-white">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Thông tin thanh toán</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="payment-details">
                                    <p><strong>Mã giao dịch:</strong> <?php echo $payment_info['transaction_id']; ?></p>
                                    <p><strong>Số tiền:</strong> <?php echo number_format($payment_info['amount']); ?> VNĐ</p>
                                    
                                    <?php if ($payment_method === 'momo'): ?>
                                        <div class="momo-info">
                                            <h6 class="font-weight-bold">Thanh toán qua MoMo</h6>
                                            <p><strong>Số điện thoại MoMo:</strong> <?php echo $payment_info['momo_number']; ?></p>
                                            <p><strong>Nội dung chuyển khoản:</strong> <?php echo $payment_info['transaction_id']; ?></p>
                                            
                                            <div class="qr-code text-center mt-4">
                                                <?php
                                                $momoWebUrl = "https://nhantien.momo.vn/" . $payment_info['momo_number'] . "/" . $payment_info['amount'] . "/" . urlencode($payment_info['transaction_id']);
                                                ?>
                                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($momoWebUrl); ?>" 
                                                     alt="QR Code MoMo" class="img-fluid">
                                                <p class="mt-3">
                                                    <a href="<?php echo $momoWebUrl; ?>" target="_blank" class="btn btn-success">
                                                        <i class="fas fa-external-link-alt mr-2"></i>Mở MoMo để thanh toán
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bank-info">
                                            <h6 class="font-weight-bold">Chuyển khoản ngân hàng</h6>
                                            <p><strong>Số tài khoản:</strong> <?php echo $payment_info['bank_account']; ?></p>
                                            <p><strong>Ngân hàng:</strong> <?php echo $payment_info['bank_name']; ?></p>
                                            <p><strong>Nội dung chuyển khoản:</strong> <?php echo $payment_info['transaction_id']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-note">
                                    <div class="alert alert-info">
                                        <h6 class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Lưu ý quan trọng:</h6>
                                        <ul class="mb-0">
                                            <li>Vui lòng chuyển khoản đúng số tiền và nội dung chuyển khoản</li>
                                            <li>Giao dịch sẽ được xác nhận tự động trong vòng 5 phút</li>
                                            <li>Nếu sau 5 phút chưa nhận được xu, vui lòng liên hệ hỗ trợ</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab Lịch Sử Nạp Xu -->
            <div class="tab-pane fade" id="lich-su-nap">
                <div class="card bg-dark text-white border border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Lịch sử nạp xu</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã giao dịch</th>
                                        <th>Số tiền</th>
                                        <th>Phương thức</th>
                                        <th>Trạng thái</th>
                                        <th>Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql_history = "SELECT transaction_id, amount, payment_method, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC";
                                    $stmt_history = $conn->prepare($sql_history);
                                    $stmt_history->bind_param("i", $user_id);
                                    $stmt_history->execute();
                                    $result_history = $stmt_history->get_result();

                                    if ($result_history->num_rows > 0) {
                                        while ($row = $result_history->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['transaction_id']) . "</td>";
                                            echo "<td>" . number_format($row['amount']) . " VNĐ</td>";
                                            echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan=\"5\" class=\"text-center\">Chưa có giao dịch nào.</td></tr>";
                                    }
                                    $stmt_history->close();
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Lịch Sử Thanh Toán -->
            <div class="tab-pane fade" id="lich-su-thanh-toan">
                <div class="card bg-dark text-white border border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Lịch sử thanh toán</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã giao dịch</th>
                                        <th>Số xu</th>
                                        <th>Nội dung</th>
                                        <th>Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            Đây là phần hiển thị lịch sử chi tiêu của bạn (ví dụ: mua chương, mua truyện).
                                            <br>Vui lòng tạo một bảng cơ sở dữ liệu để lưu trữ thông tin này và thêm logic PHP tại đây.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layouts/footer.php'; ?>
<script src="assest/js/main.js" type="text/javascript"></script>
<script>
$(document).ready(function() {
    // Xử lý chuyển tab
    $('.nav-tabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        // Update background color for active tab
        $('.nav-tabs .nav-link').removeClass('bg-dark text-white').addClass('text-white');
        $(this).addClass('bg-dark text-white').removeClass('text-white');
    });

    // Xử lý chọn phương thức thanh toán
    $('input[name="payment_method"]').change(function() {
        // Có thể thêm xử lý khi thay đổi phương thức thanh toán
    });
});
</script>
</body>
</html>



