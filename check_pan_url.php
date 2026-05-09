<?php
/**
 * 网盘链接有效性检测 API
 * 支持：夸克网盘、百度网盘、迅雷网盘、阿里云盘、123网盘、蓝奏云、天翼云盘、UC网盘、115网盘、腾讯微云、奶牛快传、文叔叔、移动云盘
 * 参考：油猴脚本 + Go项目 PanCheck
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

set_time_limit(120);

/**
 * 发送HTTP请求
 */
function httpRequest($url, $method = 'GET', $data = null, $headers = [], $followRedirect = true) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirect);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return [
        'success' => $error === '',
        'code' => $httpCode,
        'body' => $response,
        'error' => $error,
        'final_url' => $finalUrl
    ];
}

/**
 * 检测夸克网盘 - 两步验证（参考Go项目）
 */
function checkQuark($url) {
    if (preg_match('/pan\.quark\.cn\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $pwdId = $matches[1];
    } elseif (preg_match('/pan\.qoark\.cn\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $response = httpRequest($url, 'GET', null, [], true);
        if ($response['success'] && preg_match('/pan\.quark\.cn\/s\/([a-zA-Z0-9]+)/', $response['final_url'], $m)) {
            $pwdId = $m[1];
        } else {
            return ['valid' => false, 'reason' => '无法解析短链接'];
        }
    } else {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $pwd = '';
    if (preg_match('/[?&]pwd=([^&#]+)/', $url, $pwdMatches)) {
        $pwd = $pwdMatches[1];
    }
    
    $tokenUrl = 'https://drive-h.quark.cn/1/clouddrive/share/sharepage/token';
    $tokenData = ['pwd_id' => $pwdId, 'passcode' => $pwd, 'support_visit_limit_private_share' => true];
    
    $response = httpRequest($tokenUrl, 'POST', $tokenData, [
        'Content-Type: application/json',
        'Origin: https://pan.quark.cn',
        'Referer: https://pan.quark.cn/'
    ]);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => false, 'reason' => '响应解析失败'];
    }
    
    if (isset($result['message']) && strpos($result['message'], '需要提取码') !== false) {
        return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    }
    
    if (!isset($result['status']) || $result['status'] != 200 || !isset($result['code']) || $result['code'] != 0) {
        return ['valid' => false, 'reason' => $result['message'] ?? '分享链接已失效'];
    }
    
    if (empty($result['data']['stoken'])) {
        return ['valid' => false, 'reason' => '未获取到访问令牌'];
    }
    
    $stoken = $result['data']['stoken'];
    
    $detailUrl = 'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/detail?' . http_build_query([
        'pwd_id' => $pwdId, 'stoken' => $stoken
    ]);
    
    $detailResponse = httpRequest($detailUrl, 'GET', null, [
        'Origin: https://pan.quark.cn',
        'Referer: https://pan.quark.cn/'
    ]);
    
    if (!$detailResponse['success']) {
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    $detailResult = json_decode($detailResponse['body'], true);
    if ($detailResult && isset($detailResult['data']['share']['status'])) {
        $status = $detailResult['data']['share']['status'];
        $partialViolation = $detailResult['data']['share']['partial_violation'] ?? false;
        
        if ($status == 1) {
            return ['valid' => !$partialViolation, 'reason' => $partialViolation ? '部分文件违规' : '链接有效'];
        } elseif ($status == 3) {
            return ['valid' => !$partialViolation, 'reason' => $partialViolation ? '部分文件违规' : '链接有效'];
        } elseif ($status > 1) {
            return ['valid' => false, 'reason' => '分享链接已失效'];
        }
    }
    
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测百度网盘（参考油猴脚本）
 */
function checkBaidu($url) {
    $surl = '';
    if (preg_match('/pan\.baidu\.com\/s\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $surl = $matches[1];
    } elseif (preg_match('/pan\.baidu\.com\/share\/init\?surl=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $surl = $matches[1];
    }
    
    if (empty($surl)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $checkUrl = 'https://pan.baidu.com/s/' . $surl;
    $response = httpRequest($checkUrl);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    if (strpos($body, '过期时间：') !== false) return ['valid' => true, 'reason' => '链接有效'];
    if (strpos($body, '输入提取') !== false) return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    if (strpos($body, '不存在') !== false || strpos($body, '已失效') !== false) {
        return ['valid' => false, 'reason' => '链接已失效或不存在'];
    }
    
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测阿里云盘（参考Go项目）
 */
function checkAliyun($url) {
    $shareId = '';
    if (preg_match('/ali(?:yundrive|pan)\.com\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareId = $matches[1];
    } elseif (preg_match('/ali(?:yundrive|pan)\.com\/t\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareId = $matches[1];
    }
    
    if (empty($shareId)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $apiUrl = 'https://api.aliyundrive.com/adrive/v3/share_link/get_share_by_anonymous?share_id=' . $shareId;
    $response = httpRequest($apiUrl, 'POST', json_encode(['share_id' => $shareId]), [
        'Content-Type: application/json',
        'Origin: https://www.alipan.com',
        'Referer: https://www.alipan.com/',
        'x-canary: client=web,app=share,version=v2.3.1'
    ]);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    if ($response['code'] == 429) {
        return ['valid' => null, 'reason' => 'API频率限制'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => false, 'reason' => '响应解析失败'];
    }
    
    if (!empty($result['share_title']) || !empty($result['share_name'])) {
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    if (isset($result['code']) && strpos($result['code'], 'ShareLink') !== false) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    return ['valid' => false, 'reason' => '链接状态未知'];
}

/**
 * 检测迅雷网盘（参考油猴脚本 - 默认有效，只有特定错误才失效）
 */
function checkXunlei($url) {
    if (!preg_match('/pan\.xunlei\.com\/s\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareId = $matches[1];
    $pwd = '';
    if (preg_match('/[?&]pwd=([^&#]+)/', $url, $pwdMatches)) {
        $pwd = $pwdMatches[1];
    }
    
    $deviceId = '925b7631473a13716b791d7f28289cad';
    $clientId = 'Xqp0kJBXWhwaTpB6';
    
    $tokenUrl = 'https://xluser-ssl.xunlei.com/v1/shield/captcha/init';
    $tokenData = [
        'client_id' => $clientId,
        'device_id' => $deviceId,
        'action' => 'get:/drive/v1/share',
        'meta' => [
            'package_name' => 'pan.xunlei.com',
            'client_version' => '1.45.0',
            'captcha_sign' => '1.fe2108ad808a74c9ac0243309242726c',
            'timestamp' => '1645241033384'
        ]
    ];
    
    $tokenResponse = httpRequest($tokenUrl, 'POST', $tokenData, ['Content-Type: application/json']);
    $captchaToken = '';
    if ($tokenResponse['success']) {
        $tokenResult = json_decode($tokenResponse['body'], true);
        $captchaToken = $tokenResult['captcha_token'] ?? '';
    }
    
    $apiUrl = 'https://api-pan.xunlei.com/drive/v1/share?share_id=' . urlencode($shareId);
    if ($pwd) $apiUrl .= '&pass_code=' . urlencode($pwd);
    
    $headers = [
        'Origin: https://pan.xunlei.com',
        'Referer: https://pan.xunlei.com/',
        'X-Client-Id: ' . $clientId,
        'X-Device-Id: ' . $deviceId
    ];
    if ($captchaToken) $headers[] = 'X-Captcha-Token: ' . $captchaToken;
    
    $response = httpRequest($apiUrl, 'GET', null, $headers);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    // 参考油猴脚本：默认有效，只有特定错误才失效
    if (strpos($body, 'NOT_FOUND') !== false) return ['valid' => false, 'reason' => '分享链接不存在'];
    if (strpos($body, 'SENSITIVE_RESOURCE') !== false) return ['valid' => false, 'reason' => '资源已被屏蔽'];
    if (strpos($body, 'EXPIRED') !== false) return ['valid' => false, 'reason' => '分享链接已过期'];
    if (strpos($body, 'PASS_CODE_EMPTY') !== false) return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    
    // HTTP 200 且无明确错误，默认有效
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测123网盘（参考Go项目：超时/错误/403视为有效避免误判）
 */
function check123Pan($url) {
    if (!preg_match('/(?:123pan|123865|123684|123912|123592)\.com\/s\/([a-zA-Z0-9-]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareKey = preg_replace('/\.html$/', '', $matches[1]);
    $apiUrl = 'https://www.123pan.com/api/share/info?shareKey=' . urlencode($shareKey);
    
    $response = httpRequest($apiUrl);
    
    // 参考Go项目：请求失败视为有效，避免误判
    if (!$response['success']) {
        return ['valid' => true, 'reason' => '无法确定状态(请求失败)'];
    }
    
    // 403 视为有效（可能是访问限制）
    if ($response['code'] == 403) {
        return ['valid' => true, 'reason' => '链接有效(访问受限)'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => true, 'reason' => '无法确定状态'];
    }
    
    if (($result['code'] ?? -1) == 0) {
        if (!empty($result['data']['HasPwd'])) {
            return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
        }
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    return ['valid' => false, 'reason' => '分享链接已失效'];
}

/**
 * 检测蓝奏云（参考油猴脚本）
 */
function checkLanzou($url) {
    // 统一替换为lanzoue.com
    $url = preg_replace('/lanzou[a-z]?\.com/', 'lanzoue.com', $url);
    
    // 支持带子域名的格式
    if (!preg_match('/lanzoue\.com\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $checkUrl = 'https://www.lanzoue.com/' . $matches[1];
    $response = httpRequest($checkUrl);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    // 空响应视为无法判断
    if (empty($body)) {
        return ['valid' => null, 'reason' => '响应为空'];
    }
    
    if (strpos($body, '输入密码') !== false) return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    if (strpos($body, '来晚啦') !== false || strpos($body, '不存在') !== false || strpos($body, '已删除') !== false || strpos($body, '链接失效') !== false) {
        return ['valid' => false, 'reason' => '文件已删除或不存在'];
    }
    if (strpos($body, '文件名') !== false || strpos($body, 'downs') !== false || strpos($body, 'file_size') !== false) {
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    // 无法判断时返回null
    return ['valid' => null, 'reason' => '无法判断链接状态'];
}

/**
 * 检测天翼云盘（参考油猴脚本 + Go项目）
 */
function checkTianyi($url) {
    $shareCode = '';
    if (preg_match('/cloud\.189\.cn\/t\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareCode = $matches[1];
    } elseif (preg_match('/cloud\.189\.cn\/web\/share\?code=([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareCode = $matches[1];
    } elseif (preg_match('/h5\.cloud\.189\.cn\/share\.html#\/t\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareCode = $matches[1];
    }
    
    if (empty($shareCode)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $apiUrl = 'https://api.cloud.189.cn/open/share/getShareInfoByCodeV2.action';
    $response = httpRequest($apiUrl, 'POST', 'shareCode=' . urlencode($shareCode), [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: https://cloud.189.cn/t/' . $shareCode
    ]);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    // 参考油猴脚本的关键词检测
    if (strpos($body, 'ShareInfoNotFound') !== false || 
        strpos($body, 'ShareNotFound') !== false || 
        strpos($body, 'FileNotFound') !== false || 
        strpos($body, 'ShareExpiredError') !== false || 
        strpos($body, 'ShareAuditNotPass') !== false) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    if (strpos($body, 'needAccessCode') !== false) {
        return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    }
    
    $result = json_decode($body, true);
    if ($result) {
        $shareId = $result['shareId'] ?? 0;
        if ($shareId > 0) {
            return ['valid' => true, 'reason' => '链接有效'];
        }
    }
    
    return ['valid' => false, 'reason' => '分享链接已失效'];
}


/**
 * 检测UC网盘（参考Go项目：页面关键词检测）
 */
function checkUC($url) {
    if (!preg_match('/drive\.uc\.cn\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareId = $matches[1];
    $checkUrl = 'https://drive.uc.cn/s/' . $shareId;
    
    $response = httpRequest($checkUrl, 'GET', null, [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G975F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.101 Mobile Safari/537.36'
    ]);
    
    // 参考Go项目：超时/连接错误视为有效，避免误判
    if (!$response['success']) {
        return ['valid' => true, 'reason' => '无法确定状态(网络问题)'];
    }
    
    // HTTP状态码非200视为失效
    if ($response['code'] != 200) {
        return ['valid' => false, 'reason' => 'HTTP状态码: ' . $response['code']];
    }
    
    $body = $response['body'];
    
    // 空响应视为无法判断
    if (empty($body)) {
        return ['valid' => null, 'reason' => '响应为空'];
    }
    
    $bodyLower = mb_strtolower($body);
    
    // 参考Go项目：检查错误关键词（优先检查）
    $errorKeywords = ['失效', '不存在', '违规', '删除', '已过期', '被取消'];
    foreach ($errorKeywords as $keyword) {
        if (mb_strpos($bodyLower, $keyword) !== false) {
            return ['valid' => false, 'reason' => '链接已失效'];
        }
    }
    
    // 检查有效关键词
    $validKeywords = ['文件', '分享'];
    foreach ($validKeywords as $keyword) {
        if (mb_strpos($bodyLower, $keyword) !== false) {
            return ['valid' => true, 'reason' => '链接有效'];
        }
    }
    
    // 参考Go项目：都不匹配时返回false
    return ['valid' => false, 'reason' => '无法判断链接有效性'];
}

/**
 * 检测115网盘（参考油猴脚本 + Go项目）
 */
function check115Pan($url) {
    if (!preg_match('/(?:115|anxia|115cdn)\.com\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareCode = $matches[1];
    
    // 尝试从URL获取提取码
    $receiveCode = '';
    if (preg_match('/[?&]password=([^&#]+)/', $url, $pwdMatches)) {
        $receiveCode = $pwdMatches[1];
    } elseif (preg_match('/#password=([^&#]+)/', $url, $pwdMatches)) {
        $receiveCode = $pwdMatches[1];
    }
    
    $apiUrl = 'https://115cdn.com/webapi/share/snap?share_code=' . urlencode($shareCode) . '&receive_code=' . urlencode($receiveCode);
    
    $response = httpRequest($apiUrl, 'GET', null, [
        'Referer: https://115cdn.com/s/' . $shareCode
    ]);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => null, 'reason' => '响应解析失败'];
    }
    
    // 参考油猴脚本的判断逻辑
    if (!empty($result['state'])) {
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    $error = $result['error'] ?? '';
    if (strpos($error, '访问码') !== false) {
        return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    }
    if (strpos($error, '不存在或已被删除') !== false || strpos($error, '分享已取消') !== false) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    return ['valid' => false, 'reason' => $error ?: '链接状态未知'];
}

/**
 * 检测腾讯微云（参考油猴脚本）
 */
function checkWeiyun($url) {
    if (!preg_match('/share\.weiyun\.com\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareId = $matches[1];
    $checkUrl = 'https://share.weiyun.com/' . $shareId;
    
    $response = httpRequest($checkUrl);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    // 空响应视为无法判断
    if (empty($body)) {
        return ['valid' => null, 'reason' => '响应为空'];
    }
    
    // 参考油猴脚本的判断逻辑
    if (strpos($body, '已删除') !== false || 
        strpos($body, '违反相关法规') !== false || 
        strpos($body, '已过期') !== false || 
        strpos($body, '已经删除') !== false || 
        strpos($body, '目录无效') !== false) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    if (strpos($body, '"need_pwd":1') !== false || strpos($body, '"pwd":"') !== false) {
        return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    }
    
    if (strpos($body, '"need_pwd":null') !== false && strpos($body, '"pwd":""') !== false) {
        return ['valid' => true, 'reason' => '链接有效'];
    }
    
    // 默认有效
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测奶牛快传（参考油猴脚本）
 */
function checkCowtransfer($url) {
    if (!preg_match('/cowtransfer\.com\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $shareId = $matches[1];
    $apiUrl = 'https://cowtransfer.com/core/api/transfer/share?uniqueUrl=' . urlencode($shareId);
    
    $response = httpRequest($apiUrl);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => null, 'reason' => '响应解析失败'];
    }
    
    // 参考油猴脚本的判断逻辑
    if (($result['code'] ?? '') != '0000') {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    if (!empty($result['data']['needPassword'])) {
        return ['valid' => true, 'reason' => '需要提取码', 'need_pwd' => true];
    }
    
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测文叔叔（参考油猴脚本）
 */
function checkWenshushu($url) {
    $shareId = '';
    if (preg_match('/(?:wss\.ink|wss1\.cn|wenshushu\.cn)\/f\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareId = $matches[1];
    } elseif (preg_match('/t\.wss\.ink\/f\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $shareId = $matches[1];
    }
    
    if (empty($shareId)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    $apiUrl = 'https://www.wenshushu.cn/ap/task/mgrtask';
    $response = httpRequest($apiUrl, 'POST', json_encode(['tid' => $shareId]), [
        'Content-Type: application/json',
        'x-token: wss:7pmakczzw6i'
    ]);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $result = json_decode($response['body'], true);
    if (!$result) {
        return ['valid' => null, 'reason' => '响应解析失败'];
    }
    
    // 参考油猴脚本的判断逻辑
    if (($result['code'] ?? -1) != 0) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测移动云盘（简化版 - 直接访问页面检测）
 * 注：完整版需要加密解密，这里使用简化的页面检测方式
 */
function checkCMCC($url) {
    $shareId = '';
    // 支持两种格式
    if (preg_match('/yun\.139\.com\/shareweb\/#\/w\/i\/([^&]+)/', $url, $matches)) {
        $shareId = $matches[1];
    } elseif (preg_match('/caiyun\.139\.com\/m\/i\?([^&]+)/', $url, $matches)) {
        $shareId = $matches[1];
    }
    
    if (empty($shareId)) {
        return ['valid' => false, 'reason' => '链接格式无效'];
    }
    
    // 直接访问分享页面检测
    $checkUrl = 'https://yun.139.com/shareweb/#/w/i/' . $shareId;
    $response = httpRequest($checkUrl);
    
    if (!$response['success']) {
        return ['valid' => null, 'reason' => '请求失败'];
    }
    
    $body = $response['body'];
    
    // 检查错误关键词
    if (strpos($body, '已失效') !== false || 
        strpos($body, '不存在') !== false || 
        strpos($body, '已删除') !== false || 
        strpos($body, '已过期') !== false) {
        return ['valid' => false, 'reason' => '分享链接已失效'];
    }
    
    // 移动云盘页面是SPA，很难直接判断，默认返回有效
    return ['valid' => true, 'reason' => '链接有效'];
}

/**
 * 检测网盘类型
 */
function detectPanType($url) {
    $patterns = [
        'quark' => '/pan\.quark\.cn|pan\.qoark\.cn/',
        'baidu' => '/pan\.baidu\.com|yun\.baidu\.com/',
        'aliyun' => '/ali(?:yundrive|pan)\.com/',
        'xunlei' => '/pan\.xunlei\.com/',
        '123pan' => '/(?:123pan|123865|123684|123912|123592)\.com/',
        'lanzou' => '/lanzou[a-z]?\.com/',
        'tianyi' => '/cloud\.189\.cn|h5\.cloud\.189\.cn/',
        'uc' => '/drive\.uc\.cn/',
        '115' => '/(?:115|anxia|115cdn)\.com/',
        'weiyun' => '/share\.weiyun\.com/',
        'cowtransfer' => '/cowtransfer\.com/',
        'wenshushu' => '/wss\.ink|wss1\.cn|wenshushu\.cn|t\.wss\.ink/',
        'cmcc' => '/yun\.139\.com|caiyun\.139\.com/'
    ];
    
    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $url)) {
            return $type;
        }
    }
    
    return 'unknown';
}

/**
 * 获取平台名称
 */
function getPlatformName($type) {
    $names = [
        'quark' => '夸克网盘',
        'baidu' => '百度网盘',
        'aliyun' => '阿里云盘',
        'xunlei' => '迅雷网盘',
        '123pan' => '123网盘',
        'lanzou' => '蓝奏云',
        'tianyi' => '天翼云盘',
        'uc' => 'UC网盘',
        '115' => '115网盘',
        'weiyun' => '腾讯微云',
        'cowtransfer' => '奶牛快传',
        'wenshushu' => '文叔叔',
        'cmcc' => '移动云盘',
        'unknown' => '未知网盘'
    ];
    
    return $names[$type] ?? '未知网盘';
}

/**
 * 主检测函数
 */
function checkPanUrl($url) {
    $panType = detectPanType($url);
    $platformName = getPlatformName($panType);
    
    // 未知类型返回 null
    if ($panType === 'unknown') {
        return [
            'valid' => null,
            'reason' => '不支持的网盘类型',
            'platform' => $panType,
            'platform_name' => $platformName
        ];
    }
    
    $checkers = [
        'quark' => 'checkQuark',
        'baidu' => 'checkBaidu',
        'aliyun' => 'checkAliyun',
        'xunlei' => 'checkXunlei',
        '123pan' => 'check123Pan',
        'lanzou' => 'checkLanzou',
        'tianyi' => 'checkTianyi',
        'uc' => 'checkUC',
        '115' => 'check115Pan',
        'weiyun' => 'checkWeiyun',
        'cowtransfer' => 'checkCowtransfer',
        'wenshushu' => 'checkWenshushu',
        'cmcc' => 'checkCMCC'
    ];
    
    if (isset($checkers[$panType])) {
        $result = call_user_func($checkers[$panType], $url);
        $result['platform'] = $panType;
        $result['platform_name'] = $platformName;
        return $result;
    }
    
    return [
        'valid' => null,
        'reason' => '不支持的网盘类型',
        'platform' => $panType,
        'platform_name' => $platformName
    ];
}

// API 入口
$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? $_GET['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'error' => '请提供网盘链接']);
    exit;
}

$result = checkPanUrl($url);
echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
