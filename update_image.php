<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $encrypted_code = $input['encrypted_code'] ?? '';
    $preview_image = $input['preview_image'] ?? '';

    if (empty($encrypted_code)) {
        throw new Exception('缺少加密码');
    }

    // 更新配图地址
    $stmt = $pdo->prepare("UPDATE link_records SET preview_image = ? WHERE encrypted_code = ?");
    $result = $stmt->execute([$preview_image, $encrypted_code]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => '配图更新成功']);
    } else {
        throw new Exception('未找到对应记录或无需更新');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
