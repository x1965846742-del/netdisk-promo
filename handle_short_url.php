<?php
// 设置内容类型
header('Content-Type: text/html; charset=utf-8');

require_once 'config.php';

try {
    // 获取短链接参数
    $customUrl = isset($_GET['custom']) ? trim($_GET['custom']) : '';
    
    if (empty($customUrl)) {
        // 如果没有参数，重定向到首页
        header('Location: /shencheng.html');
        exit;
    }

    // URL解码，支持中文
    $customUrl = urldecode($customUrl);
    
    // 首先尝试查找自定义短链接
    $stmt = $pdo->prepare("SELECT original_url, custom_name FROM link_records WHERE custom_short_url = ?");
    $stmt->execute([$customUrl]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // 找到自定义短链接，重定向到xiazai.html并传递相关信息
        $query = http_build_query([
            'type' => 'custom',
            'custom' => $customUrl
        ]);
        header("Location: /xiazai.html?{$query}");
        exit;
    }
    
    // 如果不是自定义短链接，检查是否是系统生成的加密码
    if (preg_match('/^[a-zA-Z0-9]{6,20}$/', $customUrl)) {
        $stmt = $pdo->prepare("SELECT original_url, custom_name FROM link_records WHERE encrypted_code = ?");
        $stmt->execute([$customUrl]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // 找到系统加密码，重定向到标准格式
            header("Location: /xiazai.html?={$customUrl}");
            exit;
        }
    }
    
    // 如果都没找到，重定向到xiazai.html并传递错误信息
    header("Location: /xiazai.html?error=notfound");
    exit;
    
} catch (Exception $e) {
    error_log("Short URL处理错误: " . $e->getMessage());
    http_response_code(500);
    echo "服务器错误";
}
?>
