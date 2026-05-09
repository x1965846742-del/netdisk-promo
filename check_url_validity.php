<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

/**
 * 使用ulq.cc API检测网盘链接（支持除迅雷外的大部分网盘）
 */
function checkWithUlqAPI($url) {
    $apiUrl = 'http://api.ulq.cc/int/v1/ispanlink?url=' . urlencode($url);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['code'])) {
            // API返回格式：code: -1失效, msg: "网盘链接无效"
            if ($data['code'] == -1) {
                return ['state' => -1, 'message' => $data['msg'] ?? '链接失效'];
            } else {
                return ['state' => 1, 'message' => $data['msg'] ?? '链接有效'];
            }
        }
    }
    
    return false;
}

/**
 * 迅雷网盘检测（使用类似油猴脚本的方法）
 */
function checkXunleiPan($url) {
    // 提取分享ID
    if (!preg_match('/pan\.xunlei\.com\/s\/([\w\-]{25,})/', $url, $matches)) {
        return false;
    }
    
    $shareId = $matches[1];
    
    // 获取captcha token
    $tokenData = [
        'client_id' => 'Xqp0kJBXWhwaTpB6',
        'device_id' => '925b7631473a13716b791d7f28289cad',
        'action' => 'get:/drive/v1/share',
        'meta' => [
            'package_name' => 'pan.xunlei.com',
            'client_version' => '1.45.0',
            'captcha_sign' => '1.fe2108ad808a74c9ac0243309242726c',
            'timestamp' => (string)(time() * 1000)
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://xluser-ssl.xunlei.com/v1/shield/captcha/init');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return false;
    }
    
    $tokenResult = json_decode($response, true);
    if (!$tokenResult || !isset($tokenResult['captcha_token'])) {
        return false;
    }
    
    $token = $tokenResult['captcha_token'];
    
    // 检测分享链接
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api-pan.xunlei.com/drive/v1/share?share_id=' . $shareId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-captcha-token: ' . $token,
        'x-client-id: Xqp0kJBXWhwaTpB6',
        'x-device-id: 925b7631473a13716b791d7f28289cad'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        if (strpos($response, 'NOT_FOUND') !== false || 
            strpos($response, 'SENSITIVE_RESOURCE') !== false || 
            strpos($response, 'EXPIRED') !== false) {
            return ['state' => -1, 'message' => '迅雷网盘链接失效'];
        } else if (strpos($response, 'PASS_CODE_EMPTY') !== false) {
            return ['state' => 2, 'message' => '迅雷网盘需要提取码'];
        } else {
            return ['state' => 1, 'message' => '迅雷网盘链接有效'];
        }
    }
    
    return false;
}

/**
 * 其他网盘简单检测
 */
function checkOtherNetdisk($url) {
    // 简单的HTTP状态码检测
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
    
    if ($httpCode === 200 && $response) {
        // 检查常见的失效关键词
        $invalidKeywords = ['不存在', '已失效', '已删除', '违反', '过期', '链接失效', 'not found', '404'];
        $responseText = strtolower($response);
        
        foreach ($invalidKeywords as $keyword) {
            if (strpos($responseText, strtolower($keyword)) !== false) {
                return ['state' => -1, 'message' => '链接可能已失效'];
            }
        }
        
        // 检查需要密码的关键词
        $passwordKeywords = ['密码', '提取码', '访问码', 'password', 'pass code'];
        foreach ($passwordKeywords as $keyword) {
            if (strpos($responseText, strtolower($keyword)) !== false) {
                return ['state' => 2, 'message' => '链接需要提取码'];
            }
        }
        
        return ['state' => 1, 'message' => '链接可能有效'];
    }
    
    return ['state' => 0, 'message' => '无法检测'];
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['encrypted_code']) || !isset($input['original_url'])) {
        throw new Exception('缺少必要参数');
    }
    
    $encryptedCode = trim($input['encrypted_code']);
    $originalUrl = trim($input['original_url']);
    
    if (empty($encryptedCode) || empty($originalUrl)) {
        throw new Exception('参数不能为空');
    }
    
    // 检测链接有效性
    $result = false;
    
    // 1. 优先使用ulq.cc API（支持大部分网盘，但不支持迅雷）
    if (!preg_match('/xunlei/i', $originalUrl)) {
        $result = checkWithUlqAPI($originalUrl);
    }
    
    // 2. 如果是迅雷网盘或API检测失败，使用专门方法
    if (!$result && preg_match('/xunlei/i', $originalUrl)) {
        $result = checkXunleiPan($originalUrl);
    }
    
    // 3. 如果都失败，使用简单检测
    if (!$result) {
        $result = checkOtherNetdisk($originalUrl);
    }
    
    // 如果还是没有结果，返回未知状态
    if (!$result) {
        $result = ['state' => 0, 'message' => '检测失败'];
    }
    
    // 更新数据库中的检测结果
    $stmt = $pdo->prepare("UPDATE link_status SET is_valid = ?, check_time = NOW() WHERE encrypted_code = ?");
    $stmt->execute([$result['state'] === 1 ? 1 : 0, $encryptedCode]);
    
    // 如果link_status中没有记录，则插入
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO link_status (encrypted_code, is_valid, check_time) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE is_valid = ?, check_time = NOW()");
        $stmt->execute([$encryptedCode, $result['state'] === 1 ? 1 : 0, $result['state'] === 1 ? 1 : 0]);
    }
    
    echo json_encode([
        'success' => true,
        'result' => $result,
        'check_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("检测链接有效性失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'result' => ['state' => 0, 'message' => '检测失败']
    ]);
}
?>
