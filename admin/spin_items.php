<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Kiểm tra đăng nhập
if (!isAdminLoggedIn()) {
    sendJSON(['success' => false, 'msg' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch($action) {
            case 'add':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $tickets = intval($_POST['tickets'] ?? 1);
                $image_url = $_POST['image_url'] ?? '';
                
                if (empty($title) || $price <= 0 || $tickets <= 0) {
                    sendJSON(['success' => false, 'msg' => 'Thiếu thông tin hoặc giá trị không hợp lệ'], 400);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO spin_items (title, description, image_url, price, tickets) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $image_url, $price, $tickets]);
                
                logActivity($pdo, 'add_spin_item', ['title' => $title, 'price' => $price]);
                
                sendJSON(['success' => true, 'msg' => 'Thêm mục thành công', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $description = $_POST['description'] ?? '';
                $tickets = intval($_POST['tickets'] ?? 1);
                
                if ($id <= 0 || empty($title) || $price <= 0) {
                    sendJSON(['success' => false, 'msg' => 'Thiếu thông tin hoặc giá trị không hợp lệ'], 400);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE spin_items 
                    SET title = ?, description = ?, price = ?, tickets = ?, is_active = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $price, $tickets, $is_active, $id]);
                
                logActivity($pdo, 'edit_spin_item', ['id' => $id, 'title' => $title]);
                
                sendJSON(['success' => true, 'msg' => 'Cập nhật thành công']);
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    sendJSON(['success' => false, 'msg' => 'ID không hợp lệ'], 400);
                }
                
                // Lấy thông tin trước khi xóa
                $stmt = $pdo->prepare("SELECT title FROM spin_items WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                
                $stmt = $pdo->prepare("DELETE FROM spin_items WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity($pdo, 'delete_spin_item', ['id' => $id, 'title' => $item['title'] ?? '']);
                
                sendJSON(['success' => true, 'msg' => 'Xóa thành công']);
                break;
                
            case 'toggle':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    sendJSON(['success' => false, 'msg' => 'ID không hợp lệ'], 400);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE spin_items 
                    SET is_active = NOT is_active 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                
                sendJSON(['success' => true, 'msg' => 'Cập nhật trạng thái thành công']);
                break;
                
            default:
                sendJSON(['success' => false, 'msg' => 'Action không hợp lệ'], 400);
        }
        
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'msg' => 'Lỗi database: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'msg' => 'Lỗi: ' . $e->getMessage()], 500);
    }
} else {
    sendJSON(['success' => false, 'msg' => 'Method not allowed'], 405);
}
?>
