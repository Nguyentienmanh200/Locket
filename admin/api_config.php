<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Kiểm tra đăng nhập
if (!isAdminLoggedIn()) {
    sendJSON(['success' => false, 'msg' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $api_endpoint = $_POST['api_endpoint'] ?? '';
        $token_secret = $_POST['token_secret'] ?? '';
        $price_15s = floatval($_POST['price_15s'] ?? 0);
        $price_3s = floatval($_POST['price_3s'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate
        if (empty($api_endpoint) || empty($token_secret)) {
            sendJSON(['success' => false, 'msg' => 'Vui lòng nhập đầy đủ thông tin'], 400);
        }
        
        if ($price_15s < 0 || $price_3s < 0) {
            sendJSON(['success' => false, 'msg' => 'Giá không được âm'], 400);
        }
        
        // Kiểm tra xem đã có config chưa
        $stmt = $pdo->query("SELECT COUNT(*) FROM api_config");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE api_config 
                SET api_endpoint = ?, token_secret = ?, price_15s = ?, price_3s = ?, is_active = ?
                WHERE id = (SELECT id FROM (SELECT id FROM api_config ORDER BY id DESC LIMIT 1) AS temp)
            ");
            $stmt->execute([$api_endpoint, $token_secret, $price_15s, $price_3s, $is_active]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO api_config (api_endpoint, token_secret, price_15s, price_3s, is_active) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$api_endpoint, $token_secret, $price_15s, $price_3s, $is_active]);
        }
        
        // Log activity
        logActivity($pdo, 'update_api_config', [
            'endpoint' => $api_endpoint,
            'price_15s' => $price_15s,
            'price_3s' => $price_3s,
            'is_active' => $is_active
        ]);
        
        sendJSON(['success' => true, 'msg' => 'Cập nhật cấu hình thành công']);
        
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'msg' => 'Lỗi database: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'msg' => 'Lỗi: ' . $e->getMessage()], 500);
    }
} else {
    sendJSON(['success' => false, 'msg' => 'Method not allowed'], 405);
}
?>
