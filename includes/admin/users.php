<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/routes.php';

// Security check: Allow admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles']) || $_SESSION['roles'] !== 'admin') {
    header('Location: ' . HOME_URL);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    try {
        if ($_POST['action'] === 'update_role') {
            $user_id = (int)$_POST['user_id'];
            $new_role = in_array($_POST['role'], ['admin', 'user', 'translator']) ? $_POST['role'] : 'user';

            if ($user_id === (int)$_SESSION['user_id']) {
                throw new Exception('Bạn không thể thay đổi vai trò của chính mình.');
            }

            $stmt = $conn->prepare("UPDATE users SET roles = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            $stmt->execute();
            $stmt->close();

            $response['success'] = true;
            $response['message'] = 'Cập nhật vai trò thành công.';
        } elseif ($_POST['action'] === 'ban_user') {
            $user_id = (int)$_POST['user_id'];
            $duration = $_POST['duration'];

            if ($user_id === (int)$_SESSION['user_id']) {
                throw new Exception('Bạn không thể cấm chính mình.');
            }

            $banned_until = null;
            if ($duration === '1day') {
                $banned_until = date('Y-m-d H:i:s', strtotime('+1 day'));
            } elseif ($duration === '7days') {
                $banned_until = date('Y-m-d H:i:s', strtotime('+7 days'));
            } elseif ($duration === '30days') {
                $banned_until = date('Y-m-d H:i:s', strtotime('+30 days'));
            } elseif ($duration === 'permanent') {
                $banned_until = '2099-12-31 23:59:59';
            }

            $stmt = $conn->prepare("UPDATE users SET banned_until = ? WHERE id = ?");
            $stmt->bind_param("si", $banned_until, $user_id);
            $stmt->execute();
            $stmt->close();

            $response['success'] = true;
            $response['message'] = 'Cấm người dùng thành công.';
        } elseif ($_POST['action'] === 'unban_user') {
            $user_id = (int)$_POST['user_id'];

            if ($user_id === (int)$_SESSION['user_id']) {
                throw new Exception('Bạn không thể gỡ cấm chính mình.');
            }

            $stmt = $conn->prepare("UPDATE users SET banned_until = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $response['success'] = true;
            $response['message'] = 'Gỡ cấm người dùng thành công.';
        } elseif ($_POST['action'] === 'delete_user') {
            $user_id = (int)$_POST['user_id'];

            if ($user_id === (int)$_SESSION['user_id']) {
                throw new Exception('Bạn không thể xóa chính mình.');
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $response['success'] = true;
            $response['message'] = 'Xóa người dùng thành công.';
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    $conn->close();
    echo json_encode($response);
    exit;
}

// Fetch users
$users_query = "SELECT id, username, email, roles, created_at, banned_until FROM users ORDER BY id ASC";
$users_result = $conn->query($users_query);
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruyenGG - Quản lý người dùng</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .sidebar-dark-primary {
            background-color: #1a252f;
        }
        .main-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }
        .content-wrapper {
            background-color: #f4f6f9;
        }
        .banned {
            color: red;
            font-weight: bold;
        }
        .active {
            color: green;
            font-weight: bold;
        }
        .badge-admin {
            background-color: #007bff;
        }
        .badge-translator {
            background-color: #17a2b8;
        }
        .badge-user {
            background-color: #6c757d;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo BASE_URL; ?>includes/admin/index.php" class="nav-link">Home</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo LOGOUT_URL; ?>" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Quản lý người dùng</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo HOME_URL; ?>">Home</a></li>
                            <li class="breadcrumb-item active">Quản lý người dùng</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Danh sách người dùng</h3>
                            </div>
                            <div class="card-body">
                                <table id="usersTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Vai trò</th>
                                            <th>Ngày tạo</th>
                                            <th>Trạng thái</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($user['roles'] === 'admin') {
                                                        echo '<span class="badge badge-admin">Quản trị viên</span>';
                                                    } elseif ($user['roles'] === 'translator') {
                                                        echo '<span class="badge badge-translator">Nhóm dịch</span>';
                                                    } else {
                                                        echo '<span class="badge badge-user">Người dùng</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))); ?></td>
                                                <td>
                                                    <?php
                                                    if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
                                                        echo '<span class="banned">Bị cấm đến ' . date('d/m/Y H:i', strtotime($user['banned_until'])) . '</span>';
                                                    } else {
                                                        echo '<span class="active">Hoạt động</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary edit-role" data-id="<?php echo $user['id']; ?>" data-role="<?php echo $user['roles']; ?>">
                                                        <i class="fas fa-edit"></i> Vai trò
                                                    </button>
                                                    <?php if ($user['banned_until'] && strtotime($user['banned_until']) > time()): ?>
                                                        <button class="btn btn-sm btn-success unban-user" data-id="<?php echo $user['id']; ?>">
                                                            <i class="fas fa-unlock"></i> Gỡ cấm
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-warning ban-user" data-id="<?php echo $user['id']; ?>">
                                                            <i class="fas fa-ban"></i> Cấm
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-danger delete-user" data-id="<?php echo $user['id']; ?>">
                                                        <i class="fas fa-trash"></i> Xóa
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Modals -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cập nhật vai trò</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editRoleForm">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="form-group">
                            <label for="edit_role">Vai trò</label>
                            <select class="form-control" id="edit_role" name="role">
                                <option value="user">Người dùng</option>
                                <option value="translator">Nhóm dịch</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary" id="saveRole">Lưu</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="banUserModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cấm người dùng</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="banUserForm">
                        <input type="hidden" id="ban_user_id" name="user_id">
                        <div class="form-group">
                            <label for="ban_duration">Thời gian cấm</label>
                            <select class="form-control" id="ban_duration" name="duration">
                                <option value="1day">1 ngày</option>
                                <option value="7days">7 ngày</option>
                                <option value="30days">30 ngày</option>
                                <option value="permanent">Vĩnh viễn</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-warning" id="saveBan">Cấm</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xóa người dùng</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa người dùng này? Hành động này không thể hoàn tác.</p>
                    <input type="hidden" id="delete_user_id" name="user_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Xóa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright © 2024 <a href="<?php echo HOME_URL; ?>">TruyenGG</a>.</strong>
        All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json"
        },
        "pageLength": 10,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": 6 }
        ]
    });

    $('.edit-role').on('click', function() {
        var userId = $(this).data('id');
        var role = $(this).data('role');
        $('#edit_user_id').val(userId);
        $('#edit_role').val(role);
        $('#editRoleModal').modal('show');
    });

    $('#saveRole').on('click', function() {
        var userId = $('#edit_user_id').val();
        var role = $('#edit_role').val();

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/users.php',
            type: 'POST',
            data: {
                action: 'update_role',
                user_id: userId,
                role: role
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + response.message);
                }
                $('#editRoleModal').modal('hide');
            },
            error: function() {
                alert('Đã xảy ra lỗi khi cập nhật vai trò.');
                $('#editRoleModal').modal('hide');
            }
        });
    });

    $('.ban-user').on('click', function() {
        var userId = $(this).data('id');
        $('#ban_user_id').val(userId);
        $('#banUserModal').modal('show');
    });

    $('#saveBan').on('click', function() {
        var userId = $('#ban_user_id').val();
        var duration = $('#ban_duration').val();

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/users.php',
            type: 'POST',
            data: {
                action: 'ban_user',
                user_id: userId,
                duration: duration
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + response.message);
                }
                $('#banUserModal').modal('hide');
            },
            error: function() {
                alert('Đã xảy ra lỗi khi cấm người dùng.');
                $('#banUserModal').modal('hide');
            }
        });
    });

    $('.unban-user').on('click', function() {
        var userId = $(this).data('id');
        if (confirm('Bạn có chắc chắn muốn gỡ cấm người dùng này?')) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>includes/admin/users.php',
                type: 'POST',
                data: {
                    action: 'unban_user',
                    user_id: userId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.message);
                    }
                },
                error: function() {
                    alert('Đã xảy ra lỗi khi gỡ cấm người dùng.');
                }
            });
        }
    });

    $('.delete-user').on('click', function() {
        var userId = $(this).data('id');
        $('#delete_user_id').val(userId);
        $('#deleteUserModal').modal('show');
    });

    $('#confirmDelete').on('click', function() {
        var userId = $('#delete_user_id').val();

        $.ajax({
            url: '<?php echo BASE_URL; ?>includes/admin/users.php',
            type: 'POST',
            data: {
                action: 'delete_user',
                user_id: userId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Lỗi: ' + response.message);
                }
                $('#deleteUserModal').modal('hide');
            },
            error: function() {
                alert('Đã xảy ra lỗi khi xóa người dùng.');
                $('#deleteUserModal').modal('hide');
            }
        });
    });
});
</script>
</body>
</html>