<?php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = rtrim($protocol . $host . '/truyengg', '/') . '/';

// Định nghĩa các alias URL dưới dạng hằng số
define('BASE_URL', $baseUrl);
define('HOME_URL', BASE_URL);
define('NEW_COMICS_URL', BASE_URL . 'truyen-moi-cap-nhat.php');
define('COMIC_DETAIL_URL', BASE_URL . 'truyen-tranh.php');
define('CHAPTER_URL', BASE_URL . 'chapter.php');
define('TOP_VOTED_URL', BASE_URL . 'top-binh-chon.php');
define('TOP_VIEWED_URL', BASE_URL . 'top-thang.php');
define('TOP_DAILY_URL', BASE_URL . 'top-ngay.php');
define('TOP_WEEKLY_URL', BASE_URL . 'top-tuan.php');
define('COMPLETED_COMICS_URL', BASE_URL . 'truyen-hoan-thanh.php');
define('NEW_RELEASE_URL', BASE_URL . 'truyen-moi.php');
define('UPCOMING_URL', BASE_URL . 'sap-ra-mat.php');
define('FOLLOW_URL', BASE_URL . 'theo-doi.php');
define('HISTORY_URL', BASE_URL . 'lich-su.php');
define('LOGIN_URL', BASE_URL . 'login.php');
define('RECHARGE_URL', BASE_URL . 'nap-xu.php');
define('RECHARGE_HISTORY_URL', BASE_URL . 'lich-su-nap-xu.php');
define('PAYMENT_HISTORY_URL', BASE_URL . 'lich-su-thanh-toan.php');
define('LOGOUT_URL', BASE_URL . 'includes/auth/logout.php');
define('ACCOUNT_SETTINGS_URL', BASE_URL . 'thiet-lap-tai-khoan.php');
define('LOAD_SECTION_URL', BASE_URL . 'includes/profile/load_section.php');
?>