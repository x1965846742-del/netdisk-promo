<?php
/**
 * 搜索引擎提交相关函数
 */

/**
 * 检查URL是否有效
 */
function checkUrlValid($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 400;
}

/**
 * 更新链接状态
 */
function updateLinkStatus($encryptedCode, $isValid) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE link_status SET is_valid = ?, check_time = NOW() WHERE encrypted_code = ?");
    $stmt->execute([$isValid ? 1 : 0, $encryptedCode]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO link_status (encrypted_code, is_valid, check_time) VALUES (?, ?, NOW())");
        $stmt->execute([$encryptedCode, $isValid ? 1 : 0]);
    }
}

/**
 * 提交到Bing搜索引擎
 */
function submitToBing($url) {
    global $pdo;
    
    try {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    } catch (Exception $e) {
        error_log("Bing推送失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 提交到百度搜索引擎
 */
function submitToBaidu($url) {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'baidu_token'");
        $token = $stmt->fetchColumn();
        
        if (!$token) {
            error_log("百度Token未配置");
            return false;
        }
        
        // 从URL中提取域名
        $parsedUrl = parse_url($url);
        $site = $parsedUrl['host'] ?? 'wangzhuanku.com';
        
        $api = 'http://data.zz.baidu.com/urls?site=' . $site . '&token=' . $token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    } catch (Exception $e) {
        error_log("百度推送失败: " . $e->getMessage());
        return false;
    }
}
?>
