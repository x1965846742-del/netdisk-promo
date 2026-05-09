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
    
    if (!isset($input['encrypted_code']) || !isset($input['show_on_homepage'])) {
        throw new Exception('缺少必要参数');
    }
    
    $encryptedCode = trim($input['encrypted_code']);
    $showOnHomepage = intval($input['show_on_homepage']);
    
    if (empty($encryptedCode)) {
        throw new Exception('加密码不能为空');
    }
    
    // 更新主页显示状态
    $stmt = $pdo->prepare("UPDATE link_records SET show_on_homepage = ? WHERE encrypted_code = ?");
    $result = $stmt->execute([$showOnHomepage, $encryptedCode]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('记录不存在或更新失败');
    }
    
    // 如果隐藏资源，同时取消置顶
    if ($showOnHomepage === 0) {
        try {
            $stmt = $pdo->prepare("UPDATE link_records SET is_pinned = 0 WHERE encrypted_code = ?");
            $stmt->execute([$encryptedCode]);
        } catch (PDOException $e) {
            // is_pinned 字段可能不存在，忽略错误
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => '主页显示状态更新成功',
        'show_on_homepage' => $showOnHomepage
    ]);
    
} catch (Exception $e) {
    error_log("更新主页显示状态失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
