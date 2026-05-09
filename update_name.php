<?php
header('Content-Type: application/json');

// 引用统一配置文件
require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['encrypted_code']) || !isset($data['new_name'])) {
        throw new Exception('无效的数据');
    }

    $stmt = $pdo->prepare("UPDATE link_records SET custom_name = ? WHERE encrypted_code = ?");
    $result = $stmt->execute([$data['new_name'], $data['encrypted_code']]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('更新失败');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 