<?php
header('Content-Type: application/json');

// 引用统一配置文件
require_once 'config.php';

try {
    // 从 URL 中获取加密码
    $code = isset($_GET['code']) ? trim($_GET['code']) : '';
    // 记录日志，帮助调试
    error_log("Received code: " . $code);

    if (empty($code)) {
        throw new Exception('无效的访问代码');
    }

    $stmt = $pdo->prepare("SELECT original_url, custom_name, preview_image FROM link_records WHERE encrypted_code = ?");
    $stmt->execute([$code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    // 记录查询结果
    error_log("Query result: " . print_r($result, true));

    if ($result) {
        echo json_encode([
            'success' => true,
            'url' => $result['original_url'],
            'name' => $result['custom_name'],
            'preview_image' => $result['preview_image'] ?? null
        ]);
    } else {
        throw new Exception('链接不存在或已失效');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 