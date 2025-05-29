<?php
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<div class="detail_acc">
    <div class="fs20 mb-4"><strong>Thay Đổi Mật Khẩu</strong></div>
    <div class="mb-4">
        <form id="changePasswordForm" method="post">
            <div class="row">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="col-md-9">
                    <div class="mb-2 cl66 fs13"><strong>Mật Khẩu Hiện Tại</strong></div>
                    <div class="mb-3 position-relative">
                        <input placeholder="Mật Khẩu Hiện Tại" class="txt_cm" type="password" name="password_old" id="password_old">
                        <i class="bi bi-eye-slash position-absolute password-toggle" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                    </div>
                    <div class="mb-2 cl66 fs13"><strong>Mật Khẩu Mới</strong></div>
                    <div class="mb-3 position-relative">
                        <input placeholder="Mật Khẩu Mới" class="txt_cm" type="password" name="password_new" id="password_new">
                        <i class="bi bi-eye-slash position-absolute password-toggle" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                    </div>
                    <div class="mb-2 cl66 fs13"><strong>Xác Nhận Mật Khẩu</strong></div>
                    <div class="mb-3 position-relative">
                        <input placeholder="Xác Nhận Mật Khẩu" class="txt_cm" type="password" name="confirm_password_new" id="confirm_password_new">
                        <i class="bi bi-eye-slash position-absolute password-toggle" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn_update">Lưu</button>
        </form>
    </div>
</div>

<script>
    function initializePasswordScripts() {
        // Ensure previous event listeners are removed
        $('#changePasswordForm').off('submit');
        $('.password-toggle').off('click');

        // Password toggle functionality
        $('.password-toggle').on('click', function() {
            var $input = $(this).prev('input');
            var type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).toggleClass('bi-eye bi-eye-slash');
        });

        // Form submission
        $('#changePasswordForm').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $form = $(this);
            var $button = $form.find('.btn_update');
            var originalText = $button.text();

            $button.addClass('btn-loading').text('').prop('disabled', true);

            $.ajax({
                url: '<?php echo $base_url; ?>/includes/profile/change_password.php',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                    showToast(response.message, response.success);
                    if (response.success) {
                        $form[0].reset();
                        $('.password-toggle').removeClass('bi-eye').addClass('bi-eye-slash');
                        $form.find('input[type="text"]').attr('type', 'password');
                    }
                },
                error: function(xhr, status, error) {
                    $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                    var errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Lỗi không xác định khi thay đổi mật khẩu.';
                    showToast(errorMessage, false);
                }
            });
        });
    }

    $(document).ready(function() {
        initializePasswordScripts();
    });
</script>