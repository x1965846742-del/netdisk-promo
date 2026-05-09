<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引用统一配置文件
require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['encrypted_code'])) {
        throw new Exception('无效的数据');
    }

    // 开启事务
    $pdo->beginTransaction();
    
    try {
        // 先获取资源ID，用于清理关联数据
        $stmt = $pdo->prepare("SELECT id FROM link_records WHERE encrypted_code = ?");
        $stmt->execute([$data['encrypted_code']]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $resourceId = $record ? $record['id'] : null;
        
        // 删除链接记录
        $stmt = $pdo->prepare("DELETE FROM link_records WHERE encrypted_code = ?");
        $stmt->execute([$data['encrypted_code']]);
        
        // 同时删除链接状态记录
        $stmt = $pdo->prepare("DELETE FROM link_status WHERE encrypted_code = ?");
        $stmt->execute([$data['encrypted_code']]);
        
        // 删除导航栏资源关联（如果资源ID存在且表存在）
        if ($resourceId) {
            try {
                $stmt = $pdo->prepare("DELETE FROM navbar_resources WHERE resource_id = ?");
                $stmt->execute([$resourceId]);
            } catch (PDOException $e) {
                // navbar_resources 表可能不存在，忽略错误
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 