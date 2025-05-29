<?php
// Map type_rank to display text
$type_rank_options = [
    0 => 'Mặc Định',
    1 => 'Pháp Sư',
    2 => 'Ma Vương',
    3 => 'Tu Tiên',
    4 => 'Game',
    5 => 'Tinh Không'
];

// Map gender to display text
$gender_display = [
    'male' => 'Con Trai',
    'female' => 'Con Gái'
];
?>

<div class="detail_acc">
    <div class="fs20 mb-4"><strong>Thông Tin Tài Khoản</strong></div>
    <div class="mb-4">
        <div class="row">
            <div class="col-md-9">
                <div class="mb-2 cl66 fs13"><strong>User Name</strong></div>
                <div class="mb-3">
                    <input type="text" class="txt_cm" disabled value="<?php echo htmlspecialchars($user['username']); ?>">
                </div>
                <div class="mb-2 cl66 fs13"><strong>Email</strong></div>
                <div class="mb-3">
                    <input type="text" disabled class="txt_cm" value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
                <div class="mb-2 cl66 fs13"><strong>Xu</strong></div>
                <div class="mb-3">
                    <input type="text" class="txt_cm" disabled value="<?php echo htmlspecialchars($user['xu']); ?>">
                </div>
                <div class="mb-2 cl66 fs13"><strong>Điểm</strong></div>
                <div class="mb-3">
                    <input type="text" disabled class="txt_cm" value="<?php echo htmlspecialchars($user['points'] . ' (' . $user['progress'] . '% - Cấp ' . $user['level'] . ')'); ?>">
                    <div class="progress">
                        <span class="progress-bar" style="width: <?php echo $user['progress']; ?>%"><?php echo $user['progress']; ?>%</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <input type="file" id="uploadavatar" accept="image/jpeg,image/png,image/gif">
                    <div class="thumbblock thumbavar">
                        <img class="image-avatar" src="<?php echo $user['avatar'] ? htmlspecialchars($user['avatar']) : 'https://st.truyengg.net/template/frontend/images/noavatar.png'; ?>" />
                    </div>
                    <label class="btn_upload" for="uploadavatar">Upload Avatar</label>
                </div>
            </div>
        </div>
    </div>
    <div class="fs20 mb-4"><strong>Thông Tin Cá Nhân</strong></div>
    <div class="mb-4">
        <form id="updateProfileForm" method="post" action="<?php echo $base_url; ?>/includes/profile/update_profile.php">
            <div class="row">
                <input type="hidden" id="avatar" name="avatar" value="<?php echo htmlspecialchars($user['avatar'] ?? ''); ?>">
                <input type="hidden" id="inputDelImage" name="inputDelImage" value="">
                <div class="col-md-9">
                    <div class="mb-2 cl66 fs13"><strong>Họ</strong></div>
                    <div class="mb-3">
                        <input type="text" placeholder="Họ" class="txt_cm" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-2 cl66 fs13"><strong>Tên</strong></div>
                    <div class="mb-3">
                        <input type="text" placeholder="Tên" class="txt_cm" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-2">
                        <div class="d-flex align-items-center">
                            <strong class="cl66 fs13 mr-3">Giới Tính</strong>
                            <label class="d-flex align-items-center mr-4">
                                <input type="radio" class="rd_gen" name="gender" value="male" <?php echo ($user['gender'] === 'male') ? 'checked' : ''; ?> /> Con Trai
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="radio" name="gender" class="rd_gen" value="female" <?php echo ($user['gender'] === 'female') ? 'checked' : ''; ?> /> Con Gái
                            </label>
                        </div>
                    </div>
                    <div class="mb-2 cl66 fs13"><strong>Cảnh Giới</strong></div>
                    <div class="mb-3">
                        <select name="type_rank" id="type_rank" class="txt_cm">
                            <?php foreach ($type_rank_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($user['type_rank'] == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"></div>
                </div>
            </div>
            <button type="submit" class="btn_update">Lưu</button>
        </form>
    </div>
</div>

<script type="text/javascript" src="https://st.truyengg.net/template/frontend/js/jquery.ui.widget.js"></script>
<script type="text/javascript" src="https://st.truyengg.net/template/frontend/js/jquery.iframe-transport.js"></script>
<script type="text/javascript" src="https://st.truyengg.net/template/frontend/js/jquery.fileupload.js"></script>
<script>
    function initializeProfileScripts() {
        console.log('thong-tin-ca-nhan.php script loaded'); // Debug

        // Avatar upload
        $("#uploadavatar").fileupload({
            url: "<?php echo $base_url; ?>/includes/handlers/upload_avatar.php",
            dataType: 'json',
            add: function(e, data) {
                console.log('File selected:', data.files); // Debug
                if (!data.files || data.files.length === 0) {
                    showToast('Vui lòng chọn một file để tải lên.', false);
                    return;
                }
                // Validate file type and size client-side
                var file = data.files[0];
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('Chỉ hỗ trợ file JPEG, PNG hoặc GIF.', false);
                    return;
                }
                if (file.size > 5 * 1024 * 1024) { // 5MB
                    showToast('File quá lớn, tối đa 5MB.', false);
                    return;
                }
                console.log('Submitting file:', file.name); // Debug
                data.submit(); // Proceed with upload
            },
            done: function(e, data) {
                console.log('Avatar upload response:', data.result); // Debug
                if (data.result.error) {
                    showToast(data.result.error, false);
                } else {
                    // Store old avatar URL for potential rollback
                    if ($('#inputDelImage').val() === '') {
                        $('#inputDelImage').val($('#avatar').val());
                    } else if ($('#avatar').val()) {
                        $('#inputDelImage').val($('#inputDelImage').val() + ',' + $('#avatar').val());
                    }
                    // Update image source and avatar input with ImgBB URL
                    $(".image-avatar").attr("src", data.result.url);
                    $("#avatar").val(data.result.url);
                    showToast('Tải lên avatar thành công.', true);
                }
                $(".btn_upload").text('Upload Avatar');
            },
            progressall: function(e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                console.log('Upload progress:', progress + '%'); // Debug
                $(".btn_upload").text(progress + "%");
            },
            error: function(xhr, status, error) {
                console.log('Avatar upload error:', xhr.responseText, status, error); // Debug
                var errorMessage = 'Lỗi khi tải lên avatar: ' + (status || 'Unknown error');
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMessage = 'Lỗi khi tải lên avatar: ' + response.error;
                    }
                } catch (e) {
                    console.log('Failed to parse error response:', e); // Debug
                }
                showToast(errorMessage, false);
                $(".btn_upload").text('Upload Avatar');
            }
        });

        // Form submission
        $('#updateProfileForm').off('submit').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            e.stopPropagation(); // Stop event bubbling
            console.log('Form submitted, data:', $(this).serialize()); // Debug

            var $form = $(this);
            var $button = $form.find('.btn_update');
            var originalText = $button.text();

            $button.addClass('btn-loading').text('').prop('disabled', true);

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    console.log('Update profile response:', response); // Debug
                    $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                    showToast(response.message, response.success);
                    if (response.success) {
                        setTimeout(function() {
                            console.log('Triggering reload via section-link click...'); // Debug
                            $('.section-link[data-section="thong-tin-ca-nhan"]').click();
                        }, 2000);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Update profile error:', xhr.responseText, status, error); // Debug
                    var errorMessage = 'Lỗi khi cập nhật: ' + (xhr.responseText || status);
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = 'Lỗi khi cập nhật: ' + response.message;
                        }
                    } catch (e) {
                        console.log('Failed to parse update error response:', e); // Debug
                    }
                    $button.removeClass('btn-loading').text(originalText).prop('disabled', false);
                    showToast(errorMessage, false);
                }
            });
        });
    }

    $(document).ready(function() {
        // Ensure all scripts are loaded before initializing
        if (typeof $.fn.fileupload === 'undefined') {
            console.error('jQuery File Upload plugin not loaded'); // Debug
            showToast('Lỗi: Không tải được plugin upload.', false);
        } else {
            console.log('jQuery File Upload plugin loaded'); // Debug
            initializeProfileScripts();
        }
    });
</script>