<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdminLogin();

$filter_date = $_GET['date'] ?? '';
$filter_package = $_GET['package'] ?? '';
$filter_username = $_GET['username'] ?? '';
$filter_status = $_GET['status'] ?? '';

$sql = "SELECT id, username, package, status, ip_address, created_at FROM activation_history WHERE 1=1";
$params = [];

if(!empty($filter_date)) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
}

if(!empty($filter_package)) {
    $sql .= " AND package = ?";
    $params[] = $filter_package;
}

if(!empty($filter_username)) {
    $sql .= " AND username LIKE ?";
    $params[] = '%' . $filter_username . '%';
}

if($filter_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Format data
$exportData = [];
foreach($data as $row) {
    $exportData[] = [
        'ID' => $row['id'],
        'Username' => $row['username'],
        'Package' => $row['package'],
        'Status' => $row['status'] ? 'Thành công' : 'Thất bại',
        'IP' => $row['ip_address'],
        'Thời gian' => $row['created_at']
    ];
}

exportToCSV($exportData, 'activation_history_' . date('Y-m-d') . '.csv');
?>
