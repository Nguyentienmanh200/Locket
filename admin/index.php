<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Kiểm tra đăng nhập
requireAdminLogin();

// Lấy thống kê
$stats = getStatistics($pdo);
$recentActivities = getRecentActivities($pdo, 10);
$apiConfig = getAPIConfig($pdo);
$spinItems = $pdo->query("SELECT * FROM spin_items ORDER BY sort_order ASC")->fetchAll();

// Thống kê theo ngày (7 ngày)
$dailyStats = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN package = '15s' THEN 1 ELSE 0 END) as package15s,
        SUM(CASE WHEN package = '3s' THEN 1 ELSE 0 END) as package3s
    FROM activation_history 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LocketUser Admin</title>
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
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#api-config" data-bs-toggle="collapse">
                                <i class="bi bi-key"></i> Cấu hình API
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#spin-items" data-bs-toggle="collapse">
                                <i class="bi bi-gift"></i> Vòng quay tỷ phú
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="history.php">
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
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-speedometer2"></i> Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="text-muted">
                            <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i:s'); ?>
                        </span>
                    </div>
                </div>

                <!-- Flash Message -->
                <?php displayFlashMessage(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Tổng kích hoạt</h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['total']); ?></h2>
                                        <small>Toàn thời gian</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Hôm nay</h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['today']); ?></h2>
                                        <small>24h qua</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-day"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Người dùng</h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['users']); ?></h2>
                                        <small>Unique users</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Package 15s</h6>
                                        <h2 class="mb-0"><?php echo number_format($stats['package15s']); ?></h2>
                                        <small>Kích hoạt</small>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Stats Chart -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Thống kê 7 ngày</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="100"></canvas>
                    </div>
                </div>

                <!-- API Configuration -->
                <div id="api-config" class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-key"></i> Cấu hình API</h5>
                    </div>
                    <div class="card-body">
                        <form id="apiConfigForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">API Endpoint</label>
                                    <input type="url" class="form-control" name="api_endpoint" 
                                           value="<?php echo e($apiConfig['api_endpoint'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Token Secret</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="token_secret" 
                                               value="<?php echo e($apiConfig['token_secret'] ?? ''); ?>" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="generateToken()">
                                            <i class="bi bi-arrow-repeat"></i> Tạo mới
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giá Package 15s (VND)</label>
                                    <input type="number" class="form-control" name="price_15s" 
                                           value="<?php echo $apiConfig['price_15s'] ?? 0; ?>" step="1000" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giá Package 3s (VND)</label>
                                    <input type="number" class="form-control" name="price_3s" 
                                           value="<?php echo $apiConfig['price_3s'] ?? 0; ?>" step="1000" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" 
                                               <?php echo ($apiConfig['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Kích hoạt API</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Lưu cấu hình
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Spin Items -->
                <div id="spin-items" class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-gift"></i> Vòng quay tỷ phú</h5>
                        <button class="btn btn-primary btn-sm" onclick="addSpinItem()">
                            <i class="bi bi-plus"></i> Thêm mục
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tiêu đề</th>
                                        <th>Mô tả</th>
                                        <th>Giá (VND)</th>
                                        <th>Vé</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($spinItems as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><strong><?php echo e($item['title']); ?></strong></td>
                                        <td><?php echo e(substr($item['description'] ?? '', 0, 30)); ?></td>
                                        <td><?php echo number_format($item['price']); ?></td>
                                        <td><span class="badge bg-info"><?php echo $item['tickets']; ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $item['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $item['is_active'] ? 'Hoạt động' : 'Tạm dừng'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editSpinItem(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSpinItem(<?php echo $item['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Hoạt động gần đây</h5>
                        <a href="history.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Username</th>
                                        <th>Package</th>
                                        <th>Status</th>
                                        <th>IP</th>
                                        <th>Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentActivities as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><strong><?php echo e($log['username']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $log['package'] == '15s' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $log['package']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $log['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $log['status'] ? '✅ Thành công' : '❌ Thất bại'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['ip_address']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
    // Daily Chart
    <?php if(!empty($dailyStats)): ?>
    const ctx = document.getElementById('dailyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                $labels = [];
                foreach($dailyStats as $stat) {
                    $labels[] = "'" . date('d/m', strtotime($stat['date'])) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Tổng kích hoạt',
                data: [<?php 
                    $data = [];
                    foreach($dailyStats as $stat) {
                        $data[] = $stat['total'];
                    }
                    echo implode(',', $data);
                ?>],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Package 15s',
                data: [<?php 
                    $data = [];
                    foreach($dailyStats as $stat) {
                        $data[] = $stat['package15s'];
                    }
                    echo implode(',', $data);
                ?>],
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Package 3s',
                data: [<?php 
                    $data = [];
                    foreach($dailyStats as $stat) {
                        $data[] = $stat['package3s'];
                    }
                    echo implode(',', $data);
                ?>],
                borderColor: '#FF9800',
                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
