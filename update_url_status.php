<?php
/**
 * 更新链接有效性状态
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $encryptedCode = $data['encrypted_code'] ?? '';
    $isValid = isset($data['is_valid']) ? ($data['is_valid'] ? 1 : 0) : 1;
    
    if (empty($encryptedCode)) {
        echo json_encode(['success' => false, 'error' => '缺少参数']);
        exit;
    }
    
    $checkTime = date('Y-m-d H:i:s');
    
    // 更新或插入 link_status 表
    $stmt = $pdo->prepare("
        INSERT INTO link_status (encrypted_code, is_valid, check_time) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        is_valid = VALUES(is_valid),
        check_time = VALUES(check_time)
    ");
    $stmt->execute([$encryptedCode, $isValid, $checkTime]);
    
    echo json_encode(['success' => true, 'message' => '状态已更新']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
