<?php
session_start();
session_unset();
session_destroy();
$redirect_url = isset($_GET['url']) ? urldecode($_GET['url']) : '/truyengg';
header("Location: $redirect_url");
exit();
?>