<?php
// ============================================
// HÀM TIỆN ÍCH CHUNG
// ============================================

/**
 * Tạo token ngẫu nhiên
 */
function generateToken($length = 32) {
    return 'LK_' . bin2hex(random_bytes($length));
}

/**
 * Format số tiền
 */
function formatPrice($price) {
    return number_format($price, 0, ',', '.') . ' VND';
}

/**
 * Lấy IP người dùng
 */
function getUserIP() {
    $ip = '0.0.0.0';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

/**
 * Log hoạt động
 */
function logActivity($pdo, $action, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (action, details, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$action, json_encode($details), getUserIP()]);
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Kiểm tra CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get setting value
 */
function getSetting($pdo, $key) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Update setting
 */
function updateSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get API config
 */
function getAPIConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM api_config ORDER BY id DESC LIMIT 1");
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate API request
 */
function validateAPIRequest($input) {
    $errors = [];
    
    if (empty($input['api_key'])) {
        $errors[] = 'api_key is required';
    }
    
    if (empty($input['username'])) {
        $errors[] = 'username is required';
    }
    
    if (empty($input['package'])) {
        $errors[] = 'package is required';
    }
    
    if (!in_array($input['package'], ['15s', '3s'])) {
        $errors[] = 'package must be "15s" or "3s"';
    }
    
    return $errors;
}

/**
 * Log activation
 */
function logActivation($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activation_history 
            (api_key, username, package, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['api_key'],
            $data['username'],
            $data['package'],
            getUserIP()
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update activation status
 */
function updateActivationStatus($pdo, $id, $status, $response) {
    try {
        $stmt = $pdo->prepare("
            UPDATE activation_history 
            SET status = ?, response = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$status, $response, $id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Call external API
 */
function callExternalAPI($endpoint, $data) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode == 200,
        'response' => $response,
        'http_code' => $httpCode
    ];
}

/**
 * Get recent activities
 */
function getRecentActivities($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activation_history 
            ORDER BY id DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get statistics
 */
function getStatistics($pdo) {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM activation_history")->fetchColumn();
        $today = $pdo->query("SELECT COUNT(*) FROM activation_history WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $users = $pdo->query("SELECT COUNT(DISTINCT username) FROM activation_history")->fetchColumn();
        
        $packages = $pdo->query("
            SELECT package, COUNT(*) as count 
            FROM activation_history 
            GROUP BY package
        ")->fetchAll();
        
        $package15s = 0;
        $package3s = 0;
        foreach ($packages as $pkg) {
            if ($pkg['package'] == '15s') $package15s = $pkg['count'];
            if ($pkg['package'] == '3s') $package3s = $pkg['count'];
        }
        
        return [
            'total' => $total,
            'today' => $today,
            'users' => $users,
            'package15s' => $package15s,
            'package3s' => $package3s
        ];
    } catch (Exception $e) {
        return [
            'total' => 0,
            'today' => 0,
            'users' => 0,
            'package15s' => 0,
            'package3s' => 0
        ];
    }
}

/**
 * Check if user is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require admin login
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate admin password hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify admin password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get current admin username
 */
function getAdminUsername() {
    return $_SESSION['admin_user'] ?? 'admin';
}

/**
 * Flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show">';
        echo $flash['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Pagination helper
 */
function paginate($total, $perPage = 20, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Export to CSV
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>
