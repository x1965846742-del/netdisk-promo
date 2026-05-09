<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // 从 URL 中获取自定义链接
    $customUrl = isset($_GET['custom']) ? trim($_GET['custom']) : '';
    error_log("Received custom URL: " . $customUrl);

    if (empty($customUrl)) {
        throw new Exception('无效的访问链接');
    }

    // URL解码，支持中文
    $customUrl = urldecode($customUrl);
    
    $stmt = $pdo->prepare("SELECT original_url, custom_name, preview_image FROM link_records WHERE custom_short_url = ?");
    $stmt->execute([$customUrl]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Query result for custom URL: " . print_r($result, true));

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
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
