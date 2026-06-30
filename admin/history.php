<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Lọc dữ liệu
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_package = $_GET['package'] ?? '';
$filter_username = $_GET['username'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;

$sql = "SELECT * FROM activation_history WHERE 1=1";
$params = [];
$countSql = "SELECT COUNT(*) FROM activation_history WHERE 1=1";
$countParams = [];

if(!empty($filter_date)) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
    $countSql .= " AND DATE(created_at) = ?";
    $countParams[] = $filter_date;
}

if(!empty($filter_package)) {
    $sql .= " AND package = ?";
    $params[] = $filter_package;
    $countSql .= " AND package = ?";
    $countParams[] = $filter_package;
}

if(!empty($filter_username)) {
    $sql .= " AND username LIKE ?";
    $params[] = '%' . $filter_username . '%';
    $countSql .= " AND username LIKE ?";
    $countParams[] = '%' . $filter_username . '%';
}

if($filter_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $countSql .= " AND status = ?";
    $countParams[] = $filter_status;
}

// Đếm tổng
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total = $stmt->fetchColumn();

// Pagination
$pagination = paginate($total, $perPage, $page);
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Thống kê
$total_success = 0;
$package_stats = [];
foreach($logs as $log) {
    if($log['status']) $total_success++;
    if(!isset($package_stats[$log['package']])) {
        $package_stats[$log['package']] = 0;
    }
    $package_stats[$log['package']]++;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử - LocketUser Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar p-0">
                <div class="position-sticky">
                    <div class="sidebar-header p-3 text-center">
                        <h4><i class="bi bi-box"></i> LocketUser</h4>
                        <small class="text-muted">API Management</small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="history.php">
                                <i class="bi bi-clock-history"></i> Lịch sử
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Đăng xuất
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content py-3">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-clock-history"></i> Lịch sử kích hoạt</h1>
                    <div>
                        <button onclick="exportCSV()" class="btn btn-success btn-sm">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Ngày</label>
                                <input type="date" name="date" class="form-control" value="<?php echo e($filter_date); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Package</label>
                                <select name="package" class="form-select">
                                    <option value="">Tất cả</option>
                                    <option value="15s" <?php echo $filter_package == '15s' ? 'selected' : ''; ?>>15s</option>
                                    <option value="3s" <?php echo $filter_package == '3s' ? 'selected' : ''; ?>>3s</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Tất cả</option>
                                    <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Thành công</option>
                                    <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Thất bại</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" 
                                       placeholder="Tìm username..." value="<?php echo e($filter_username); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Lọc
                                </button>
                                <a href="history.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stats -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Tổng</h6>
                                <h3><?php echo count($logs); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Thành công</h6>
                                <h3><?php echo $total_success; ?></h3>
                            </div>
                        </div>
                    </div>
                    <?php foreach($package_stats as $package => $count): ?>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6>Package <?php echo $package; ?></h6>
                                <h3><?php echo $count; ?></h3>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Username</th>
                                        <th>Package</th>
                                        <th>API Key</th>
                                        <th>Status</th>
                                        <th>IP</th>
                                        <th>Thời gian</th>
                                        <th>Response</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox"></i> Không có dữ liệu
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php foreach($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><strong><?php echo e($log['username']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $log['package'] == '15s' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $log['package']; ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo substr($log['api_key'] ?? '', 0, 15) . '...'; ?></code></td>
                                        <td>
                                            <span class="badge <?php echo $log['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $log['status'] ? '✅ Thành công' : '❌ Thất bại'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['ip_address']; ?></td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="showResponse('<?php echo e($log['response']); ?>')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if($pagination['total_pages'] > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo !$pagination['has_previous'] ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&date=<?php echo $filter_date; ?>&package=<?php echo $filter_package; ?>&username=<?php echo urlencode($filter_username); ?>&status=<?php echo $filter_status; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $filter_date; ?>&package=<?php echo $filter_package; ?>&username=<?php echo urlencode($filter_username); ?>&status=<?php echo $filter_status; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&date=<?php echo $filter_date; ?>&package=<?php echo $filter_package; ?>&username=<?php echo urlencode($filter_username); ?>&status=<?php echo $filter_status; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showResponse(response) {
        if (!response) {
            alert('Không có response');
            return;
        }
        try {
            const data = JSON.parse(response);
            alert('Response: ' + JSON.stringify(data, null, 2));
        } catch(e) {
            alert('Response: ' + response);
        }
    }

    function exportCSV() {
        window.location.href = 'export.php?' + window.location.search.substring(1);
    }
    </script>
</body>
</html>
