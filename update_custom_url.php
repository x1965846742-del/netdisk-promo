<?php
/**
 * 更新自定义短链接
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $encryptedCode = $data['encrypted_code'] ?? '';
    $customShortUrl = trim($data['custom_short_url'] ?? '');
    
    if (empty($encryptedCode)) {
        echo json_encode(['success' => false, 'error' => '缺少参数']);
        exit;
    }
    
    // 如果自定义短链为空，则设置为NULL
    if (empty($customShortUrl)) {
        $stmt = $pdo->prepare("UPDATE link_records SET custom_short_url = NULL WHERE encrypted_code = ?");
        $stmt->execute([$encryptedCode]);
        echo json_encode(['success' => true, 'message' => '已清除自定义短链']);
        exit;
    }
    
    // 检查自定义短链是否已被其他记录使用
    $stmt = $pdo->prepare("SELECT encrypted_code FROM link_records WHERE custom_short_url = ? AND encrypted_code != ?");
    $stmt->execute([$customShortUrl, $encryptedCode]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => '该自定义短链已被使用']);
        exit;
    }
    
    // 更新自定义短链
    $stmt = $pdo->prepare("UPDATE link_records SET custom_short_url = ? WHERE encrypted_code = ?");
    $stmt->execute([$customShortUrl, $encryptedCode]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => '更新成功']);
    } else {
        echo json_encode(['success' => false, 'error' => '记录不存在或无变化']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
