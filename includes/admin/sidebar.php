<?php
require_once dirname(__DIR__, 2) . '/config/routes.php';

// Kiểm tra xem người dùng có phải nhóm dịch không
$is_translator = isset($_SESSION['roles']) && $_SESSION['roles'] === 'translator';
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <div style="text-align: center;">
        <a href="<?php echo BASE_URL; ?>includes/admin/index.php" class="brand-link">
            <span class="brand-text font-weight-light">Admin</span>
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Admin'); ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <?php if (!$is_translator): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Quản lý người dùng</p>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>includes/admin/stories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stories.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book"></i>
                        <p>Quản lý truyện</p>
                    </a>
                </li>
                <?php if (!$is_translator): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/backup-truyen.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup-truyen.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tags"></i>
                            <p>Backup Truyện</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/crawl.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'crawl.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-spider"></i>
                            <p>Crawl Truyện</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>Báo cáo</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>includes/admin/settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Cài đặt</p>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="<?php echo LOGOUT_URL; ?>" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Đăng xuất</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>