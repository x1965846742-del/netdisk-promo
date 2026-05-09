<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'submit_functions.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['encrypted_code'])) {
        throw new Exception('无效的请求数据');
    }

    // 获取链接信息
    $stmt = $pdo->prepare("SELECT r.*, s.id as status_id 
                          FROM link_records r 
                          LEFT JOIN link_status s ON r.encrypted_code = s.encrypted_code 
                          WHERE r.encrypted_code = ?");
    $stmt->execute([$data['encrypted_code']]);
    $linkInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$linkInfo) {
        throw new Exception('找不到对应的链接');
    }

    // 记录开始时间
    $startTime = microtime(true);

    // 检查链接有效性并更新状态
    $isValid = checkUrlValid($linkInfo['share_url']);
    updateLinkStatus($data['encrypted_code'], $isValid);
    
    if (!$isValid) {
        throw new Exception('链接已失效，请检查原始链接');
    }

    // 重新提交到搜索引擎
    $bingSuccess = submitToBing($linkInfo['share_url']);
    $baiduSuccess = submitToBaidu($linkInfo['share_url']);

    // 计算耗时
    $duration = round((microtime(true) - $startTime) * 1000);

    // 如果两个搜索引擎都提交成功，删除失败记录
    if ($bingSuccess && $baiduSuccess) {
        // 删除原有的失败记录
        $stmt = $pdo->prepare("DELETE FROM link_status WHERE encrypted_code = ?");
        $stmt->execute([$data['encrypted_code']]);
        
        // 记录成功状态
        $stmt = $pdo->prepare("INSERT INTO link_status 
            (encrypted_code, submit_bing, submit_baidu, submit_time, is_valid) 
            VALUES (?, 1, 1, NOW(), 1)");
        $stmt->execute([$data['encrypted_code']]);
        
        error_log("成功提交并清理旧记录: " . $data['encrypted_code']);
    } else {
        // 如果还有失败，更新状态
        if ($linkInfo['status_id']) {
            $stmt = $pdo->prepare("UPDATE link_status SET 
                submit_bing = ?, 
                submit_baidu = ?,
                submit_time = NOW() 
                WHERE encrypted_code = ?");
        } else {
            $stmt = $pdo->prepare("INSERT INTO link_status 
                (encrypted_code, submit_bing, submit_baidu, submit_time) 
                VALUES (?, ?, ?, NOW())");
        }
        
        $stmt->execute([
            $bingSuccess ? 1 : 0,
            $baiduSuccess ? 1 : 0,
            $data['encrypted_code']
        ]);
    }

    // 记录日志
    error_log(sprintf(
        "重新提交结果 - 链接: %s, Bing: %s, 百度: %s, 耗时: %dms",
        $linkInfo['share_url'],
        $bingSuccess ? '成功' : '失败',
        $baiduSuccess ? '成功' : '失败',
        $duration
    ));

    echo json_encode([
        'success' => true,
        'link_info' => [
            'custom_name' => $linkInfo['custom_name'],
            'share_url' => $linkInfo['share_url'],
            'create_time' => $linkInfo['create_time']
        ],
        'submit_status' => [
            'bing' => $bingSuccess,
            'baidu' => $baiduSuccess
        ],
        'duration' => $duration
    ]);

} catch (Exception $e) {
    error_log("重新提交失败: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?> 