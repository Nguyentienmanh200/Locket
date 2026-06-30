<?php
// ============================================
// API ENDPOINT - KÍCH HOẠT PACKAGE
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Xử lý preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'msg' => 'Method not allowed. Only POST is accepted.'
    ]);
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Lấy dữ liệu từ request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJSON([
        'success' => false,
        'msg' => 'Invalid JSON body'
    ], 400);
}

// Validate input
$errors = validateAPIRequest($input);
if (!empty($errors)) {
    sendJSON([
        'success' => false,
        'msg' => implode(', ', $errors)
    ], 400);
}

$api_key = $input['api_key'];
$username = trim($input['username']);
$package = $input['package'];

try {
    // Kiểm tra token
    $stmt = $pdo->prepare("
        SELECT * FROM api_config 
        WHERE token_secret = ? AND is_active = 1 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$api_key]);
    $config = $stmt->fetch();

    if (!$config) {
        sendJSON([
            'success' => false,
            'msg' => 'Invalid API key or API is inactive'
        ], 401);
    }

    // Log activation
    $logId = logActivation($pdo, [
        'api_key' => $api_key,
        'username' => $username,
        'package' => $package
    ]);

    if (!$logId) {
        sendJSON([
            'success' => false,
            'msg' => 'Failed to log activation'
        ], 500);
    }

    // Gọi API bên ngoài (nếu có)
    $externalResponse = callExternalAPI($config['api_endpoint'], [
        'api_key' => $api_key,
        'username' => $username,
        'package' => $package
    ]);

    if ($externalResponse['success']) {
        // Cập nhật log thành công
        updateActivationStatus($pdo, $logId, 1, $externalResponse['response']);
        
        sendJSON([
            'success' => true,
            'msg' => 'Kích hoạt thành công!',
            'id' => $logId,
            'data' => json_decode($externalResponse['response'], true)
        ]);
    } else {
        // Cập nhật log thất bại
        updateActivationStatus($pdo, $logId, 0, $externalResponse['response']);
        
        sendJSON([
            'success' => false,
            'msg' => 'Kích hoạt thất bại',
            'id' => $logId,
            'error' => $externalResponse['response']
        ], 500);
    }

} catch (PDOException $e) {
    sendJSON([
        'success' => false,
        'msg' => 'Database error: ' . $e->getMessage()
    ], 500);
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'msg' => 'System error: ' . $e->getMessage()
    ], 500);
}
?>
