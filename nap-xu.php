
<?php
require_once 'includes/layouts/header.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url . '/index.php');
    exit;
}
?>

<body class="dark-style">
<div class="container container-background ">
    <div class="detail_acc">
        <div class="fs20 mb-4"><strong>Nạp Xu</strong></div>
        <div class="payment">
        <div class="payment-info text-center">
            <div class="history-button">
                    <a class="btn btn-primary button-type active" href="javascript:void(0)" data-section="nap-xu">Nạp Xu</a>
                    <a class="btn btn-primary button-type" href="javascript:void(0)" data-section="lich-su-nap-xu">Lịch Sử Nạp Xu</a>
                    <a class="btn btn-primary button-type" href="javascript:void(0)" data-section="lich-su-thanh-toan">Lịch Sử Thanh Toán</a>
                </div>
            </div>

            <div class="payment-detail">
                <p>
                    <a class="btn btn-primary button-type active" href="javascript:void(0)" data-payment="card">Thẻ Cào</a>
                    <a class="btn btn-primary button-type" href="javascript:void(0)" data-payment="momo">Momo</a>
                    <a class="btn btn-primary button-type" href="javascript:void(0)" data-payment="bank">Ngân Hàng</a>
                </p>
                <div id="body-info">
                    <!-- Card Payment -->
                    <div class="panel-body row payment-content" data-payment="card">
                        <div class="info col-xl-6 col-md-12">
                            <h6 class="message-title-card">
                                - Vui lòng điền thông tin thẻ bên dưới, chọn mệnh giá và điền đầy đủ thông tin.<br>
                                <b>- Chú ý: Vui lòng chọn đúng mệnh giá, chọn sai có thể mất thẻ.</b>
                            </h6><br>
                            <div class="panel panel-default">
                                <div class="panel-body form-horizontal payment-form">
                                    <form id="cardPaymentForm" method="POST">
                                        <div class="form-group">
                                            <label for="card_type" class="col-sm-12 control-label">Loại thẻ</label>
                                            <div class="col-sm-12">
                                                <select class="form-control" id="card_type" name="card_type">
                                                    <option value="VIETTEL">Viettel</option>
                                                    <option value="VINAPHONE">Vinaphone</option>
                                                    <option value="MOBIFONE">Mobifone</option>
                                                    <option value="GATE">Gate</option>
                                                    <option value="ZING">Zing</option>
                                                    <option value="GARENA">Garena</option>
                                                    <option value="VCOIN">Vcoin</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="card_amount" class="col-sm-12 control-label">Mệnh giá</label>
                                            <div class="col-sm-12">
                                                <select class="form-control" id="card_amount" name="card_amount">
                                                    <option value="50000">50,000</option>
                                                    <option value="100000">100,000</option>
                                                    <option value="200000">200,000</option>
                                                    <option value="500000">500,000</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="card_number" class="col-sm-12 control-label">Mã thẻ</label>
                                            <div class="col-sm-12">
                                                <input type="text" class="form-control txt_cm" id="card_number" name="card_number" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="card_serial" class="col-sm-12 control-label">Số serial</label>
                                            <div class="col-sm-12">
                                                <input type="text" class="form-control txt_cm" id="card_serial" name="card_serial" required>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-info confirmBtnCard">Nạp Xu</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="rate-info col-xl-6 col-md-12">
                            <div class="alert alert-success">
                                <strong>Lưu ý!</strong> Sau khi nhập thông tin mã thẻ, hệ thống sẽ kiểm tra và cộng xu cho bạn ngay khi xác thực thẻ thành công. Bạn có thể kiểm tra tình trạng nạp thẻ trong phần lịch sử nạp xu.
                            </div>
                            <h4>Bảng tỉ lệ quy đổi (x0.8)</h4>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Số tiền nạp</th>
                                        <th>Số xu tương ứng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>50,000 đ</td><td>400 xu</td></tr>
                                    <tr><td>100,000 đ</td><td>800 xu</td></tr>
                                    <tr><td>200,000 đ</td><td>1600 xu</td></tr>
                                    <tr><td>500,000 đ</td><td>4000 xu</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Momo Payment -->
                    <div class="panel-body row payment-content" data-payment="momo" style="display: none;">
                        <div class="info col-xl-6 col-md-12">
                            <h6 class="message-title-card">
                                - Vui lòng chọn mệnh giá và làm theo hướng dẫn.
                            </h6><br>
                            <div class="panel panel-default">
                                <div class="panel-body form-horizontal payment-form">
                                    <form id="momoPaymentForm" method="POST">
                                        <div class="form-group">
                                            <label for="momo_amount" class="col-sm-12 control-label">Chọn mệnh giá</label>
                                            <div class="col-sm-12">
                                                <select class="form-control" id="momo_amount" name="momo_amount">
                                                    <option value="20000">20,000</option>
                                                    <option value="50000">50,000</option>
                                                    <option value="100000">100,000</option>
                                                    <option value="200000">200,000</option>
                                                    <option value="500000">500,000</option>
                                                    <option value="1000000">1,000,000</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-info confirmBtnMomo">Nạp Xu</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="rate-info col-xl-6 col-md-12">
                            <h4>Bảng tỉ lệ quy đổi</h4>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Số tiền nạp</th>
                                        <th>Số xu tương ứng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>20,000 đ</td><td>200 xu</td></tr>
                                    <tr><td>50,000 đ</td><td>500 xu</td></tr>
                                    <tr><td>100,000 đ</td><td>1000 xu</td></tr>
                                    <tr><td>200,000 đ</td><td>2000 xu</td></tr>
                                    <tr><td>500,000 đ</td><td>5000 xu</td></tr>
                                    <tr><td>1,000,000 đ</td><td>10000 xu</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Bank Payment -->
                    <div class="panel-body row payment-content" data-payment="bank" style="display: none;">
                        <div class="info col-xl-6 col-md-12">
                            <h6 class="message-title-card">
                                - Vui lòng chọn mệnh giá và làm theo hướng dẫn.
                            </h6><br>
                            <div class="panel panel-default">
                                <div class="panel-body form-horizontal payment-form">
                                    <form id="bankPaymentForm" method="POST">
                                        <div class="form-group">
                                            <label for="bank_code" class="col-sm-12 control-label">Chọn ngân hàng</label>
                                            <div class="col-sm-12">
                                                <select class="form-control" id="bank_code" name="bank_code">
                                                    <option value="ACB">ACB Bank</option>
                                                    <option value="MB">MBBank</option>
                                                    <option value="VCB">Vietcombank</option>
                                                    <option value="VTB">Vietinbank</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="bank_amount" class="col-sm-12 control-label">Chọn mệnh giá</label>
                                            <div class="col-sm-12">
                                                <select class="form-control" id="bank_amount" name="bank_amount">
                                                    <option value="20000">20,000</option>
                                                    <option value="50000">50,000</option>
                                                    <option value="100000">100,000</option>
                                                    <option value="200000">200,000</option>
                                                    <option value="500000">500,000</option>
                                                    <option value="1000000">1,000,000</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-info confirmBtnBank">Nạp Xu</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="rate-info col-xl-6 col-md-12">
                            <h4>Bảng tỉ lệ quy đổi</h4>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Số tiền nạp</th>
                                        <th>Số xu tương ứng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>20,000 đ</td><td>200 xu</td></tr>
                                    <tr><td>50,000 đ</td><td>500 xu</td></tr>
                                    <tr><td>100,000 đ</td><td>1000 xu</td></tr>
                                    <tr><td>200,000 đ</td><td>2000 xu</td></tr>
                                    <tr><td>500,000 đ</td><td>5000 xu</td></tr>
                                    <tr><td>1,000,000 đ</td><td>10000 xu</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

    </div>
    <a id="back-to-top">
        <i class="bi bi-chevron-double-up"></i>
    </a>
<?php require_once 'includes/layouts/footer.php'; ?>
<script src="assest/js/main.js" type="text/javascript"></script>
<script src="https://st.truyengg.net/template/frontend/js/main-donate.js?v=3.1" type="text/javascript"></script>

<script>
    function initializePaymentScripts() {
        // Remove previous event listeners
        $('.button-type').off('click');
        $('#cardPaymentForm, #momoPaymentForm, #bankPaymentForm').off('submit');

        // Handle payment type switching
        $('.button-type').on('click', function(e) {
            e.preventDefault();
            var paymentType = $(this).data('payment');
            
            // Update active button
            $('.button-type').removeClass('active');
            $(this).addClass('active');

            // Show/hide payment content
            $('.payment-content').hide();
            $('.payment-content[data-payment="' + paymentType + '"]').show();
        });

        // Placeholder for form submissions
        $('#cardPaymentForm').on('submit', function(e) {
            e.preventDefault();
            showToast('Chức năng nạp xu bằng thẻ cào đang được phát triển.', false);
        });

        $('#momoPaymentForm').on('submit', function(e) {
            e.preventDefault();
            showToast('Chức năng nạp xu bằng Momo đang được phát triển.', false);
        });

        $('#bankPaymentForm').on('submit', function(e) {
            e.preventDefault();
            showToast('Chức năng nạp xu bằng ngân hàng đang được phát triển.', false);
        });
    }

    $(document).ready(function() {
        initializePaymentScripts();
    });
</script>

</body>
</html>



