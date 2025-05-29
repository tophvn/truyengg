<?php
if (!empty($api_images)) {
    foreach ($api_images as $index => $image) {
        ?>
        <img class="lazy" src="<?php echo htmlspecialchars($image['image_page']); ?>" 
             alt="<?php echo $comic_name; ?> Chương <?php echo htmlspecialchars($chapter_number); ?> - Trang <?php echo $index + 1; ?>" 
             onerror="this.src='https://st.truyengg.net/template/frontend/img/placeholder.jpg';" />
        <?php
    }
} else {
    ?>
    <div class="error-container">
        <p>Không tìm thấy hình ảnh cho chương này từ API! Vui lòng thử server khác hoặc báo lỗi.</p>
        <p><a href="<?php echo BASE_URL; ?>">Quay lại trang chủ</a></p>
    </div>
    <?php
}
?>