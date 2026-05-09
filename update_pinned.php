<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['encrypted_code']) || !isset($input['is_pinned'])) {
        throw new Exception('缺少必要参数');
    }
    
    $encryptedCode = trim($input['encrypted_code']);
    $isPinned = intval($input['is_pinned']);
    
    if (empty($encryptedCode)) {
        throw new Exception('加密码不能为空');
    }
    
    // 检查 is_pinned 字段是否存在，不存在则添加
    try {
        $pdo->query("SELECT is_pinned FROM link_records LIMIT 1");
    } catch (PDOException $e) {
        // 字段不存在，添加字段
        $pdo->exec("ALTER TABLE `link_records` ADD COLUMN `is_pinned` tinyint(1) DEFAULT '0' COMMENT '是否置顶 0-否 1-是' AFTER `show_on_homepage`");
    }
    
    // 更新置顶状态
    $stmt = $pdo->prepare("UPDATE link_records SET is_pinned = ? WHERE encrypted_code = ?");
    $result = $stmt->execute([$isPinned, $encryptedCode]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('记录不存在或更新失败');
    }
    
    echo json_encode([
        'success' => true,
        'message' => '置顶状态更新成功',
        'is_pinned' => $isPinned
    ]);
    
} catch (Exception $e) {
    error_log("更新置顶状态失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
