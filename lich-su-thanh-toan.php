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
    <title>Lịch Sử Thanh Toán</title><div id="main_homepage">
    <div class="col-md-12 payment-history">
        <div class="payment-info">
            <div class="history-button">
                <a href="https://truyengg.net/nap-xu.html" title="Nạp Xu">Nạp Xu</a>
                <a href="https://truyengg.net/lich-su-nap-xu.html" title="Lịch Sử Nạp Xu">Lịch Sử Nạp Xu</a><br>
                <a class="active" href="https://truyengg.net/lich-su-thanh-toan.html" title="Lịch Sử Thanh Toán">Lịch Sử Thanh Toán</a> 
            </div>
        </div>
        <div class="text-center">
            <h1>Lịch Sử Thanh Toán</h1><br>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <table class="table table-bordered" id="example">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Truyện</th>
                        <th>Tên Chương</th>
                        <th>Số Xu</th>
                        <th>Thời Gian</th>
                    </tr>
                    </thead>
                    <tbody>
                    
                                        </tbody>
                </table>
                <div class="clear"></div>
                


            </div>
        </div>
        <br>
    </div>
<div class="clear"></div>
</div>

    </div>
<a id="back-to-top">
    <i class="bi bi-chevron-double-up"></i>
</a>

<?php require_once 'includes/layouts/footer.php'; ?>
</script><script src="assest/js/main.js" type="text/javascript"></script>
</body>

</html>