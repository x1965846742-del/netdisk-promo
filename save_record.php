<?php
header('Content-Type: application/json');

// 数据库配置
require_once 'config.php';

// 自动推送到Bing
function submitToBing($url) {
    global $pdo;
    try {
        // 获取Bing API密钥
        $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'bing_api_key'");
        $apiKey = $stmt->fetchColumn();
        
        if (!$apiKey) {
            error_log("Bing API密钥未配置");
            return false;
        }
        
        $endpoint = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=' . $apiKey;
        $data = array('siteUrl' => $url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    } catch (Exception $e) {
        error_log("Bing推送失败: " . $e->getMessage());
        return false;
    }
}

// 自动推送到百度
function submitToBaidu($url) {
    global $pdo;
    try {
        // 获取百度Token
        $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'baidu_token'");
        $token = $stmt->fetchColumn();
        
        if (!$token) {
            error_log("百度Token未配置");
            return false;
        }
        
        $api = 'http://data.zz.baidu.com/urls?site=wangzhuanku.com&token=' . $token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    } catch (Exception $e) {
        error_log("百度推送失败: " . $e->getMessage());
        return false;
    }
}

try {
    // 获取POST数据
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('无效的数据');
    }

    // 兼容两种命名方式（下划线和驼峰）
    $originalUrl = $data['original_url'] ?? $data['originalUrl'] ?? null;
    $shareUrl = $data['share_url'] ?? $data['shareUrl'] ?? null;
    $customName = $data['custom_name'] ?? $data['customName'] ?? null;
    $customShortUrl = $data['custom_short_url'] ?? $data['customShortUrl'] ?? null;
    $previewImage = $data['preview_image'] ?? $data['previewImage'] ?? null;
    $encryptedCode = $data['encrypted_code'] ?? null;

    // 验证必填字段
    if (empty($originalUrl)) {
        throw new Exception('网盘链接不能为空');
    }

    // 从shareUrl中提取encrypted_code（如果没有直接传递）
    if (empty($encryptedCode) && $shareUrl) {
        $parts = explode('?=', $shareUrl);
        $encryptedCode = $parts[1] ?? null;
    }
    
    if (empty($encryptedCode)) {
        throw new Exception('加密码生成失败');
    }
    
    // 处理自定义短链接
    $customShortUrl = !empty(trim($customShortUrl ?? '')) ? trim($customShortUrl) : null;
    
    // 如果有自定义短链接，先检查是否可用
    if ($customShortUrl) {
        $stmt = $pdo->prepare("SELECT id FROM link_records WHERE custom_short_url = ?");
        $stmt->execute([$customShortUrl]);
        if ($stmt->fetch()) {
            throw new Exception('自定义链接已被使用，请选择其他名称');
        }
    }
    
    // 执行插入
    $stmt = $pdo->prepare("INSERT INTO link_records (custom_name, original_url, share_url, custom_short_url, preview_image, encrypted_code, show_on_homepage) VALUES (?, ?, ?, ?, ?, ?, ?)");
    try {
        $stmt->execute([
            $customName,
            $originalUrl,
            $shareUrl,
            $customShortUrl,
            $previewImage,
            $encryptedCode,
            1  // 默认在主页显示
        ]);
        $lastId = $pdo->lastInsertId();
        error_log("成功插入记录，ID: " . $lastId);
        
        // 初始化链接状态
        $stmt = $pdo->prepare("INSERT INTO link_status (encrypted_code, is_valid, submit_bing, submit_baidu) VALUES (?, 1, 0, 0)");
        $stmt->execute([$encryptedCode]);
        
        echo json_encode([
            'success' => true,
            'id' => $lastId
        ]);
        
    } catch (PDOException $e) {
        throw new Exception("插入失败: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("保存记录失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 