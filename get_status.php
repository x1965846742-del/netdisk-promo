<?php
header('Content-Type: application/json');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查数据库连接
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }

    $type = isset($_GET['type']) ? $_GET['type'] : '';

    // 验证type参数
    if (!in_array($type, ['bing', 'google', 'baidu', 'stats', 'invalid'])) {
        throw new Exception('无效的请求类型');
    }

    // 添加分页支持
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    switch($type) {
        case 'bing':
            // 获取Bing API设置
            $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'bing_api_key'");
            $apiKey = $stmt->fetchColumn();
            echo json_encode([
                'success' => true,
                'api_key' => $apiKey
            ]);
            break;

        case 'baidu':
            // 获取百度API设置
            $stmt = $pdo->query("SELECT value FROM settings WHERE name = 'baidu_token'");
            $token = $stmt->fetchColumn();
            echo json_encode([
                'success' => true,
                'token' => $token
            ]);
            break;

        case 'stats':
            // 收录统计
            $stats = [
                'total_links' => $pdo->query("SELECT COUNT(*) FROM link_records")->fetchColumn(),
                'submitted_count' => $pdo->query("SELECT COUNT(*) FROM link_status WHERE submit_bing = 1 OR submit_baidu = 1")->fetchColumn(),
                'invalid_links' => $pdo->query("SELECT COUNT(*) FROM link_status WHERE is_valid = 0")->fetchColumn(),
                'last_check_time' => $pdo->query("SELECT MAX(check_time) FROM link_status")->fetchColumn(),
                // 获取提交失败的记录
                'failed_submissions' => $pdo->query("
                    SELECT r.custom_name, r.share_url, r.encrypted_code, 
                           s.submit_bing, s.submit_baidu, s.is_valid, s.check_time
                    FROM link_records r 
                    JOIN link_status s ON r.encrypted_code = s.encrypted_code 
                    WHERE s.submit_bing = 0 OR s.submit_baidu = 0
                    ORDER BY r.create_time DESC
                ")->fetchAll(PDO::FETCH_ASSOC)
            ];
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'invalid':
            $stmt = $pdo->prepare("SELECT r.*, s.submit_bing, s.submit_baidu, s.submit_time, 
                                   s.check_time, s.is_valid
                                   FROM link_records r 
                                   LEFT JOIN link_status s ON r.encrypted_code = s.encrypted_code 
                                   WHERE (s.submit_bing = 0 OR s.submit_baidu = 0)");
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'records' => $records]);
            break;

        default:
            throw new Exception('无效的请求类型');
    }

} catch (Exception $e) {
    error_log('Error in get_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'type' => 'error'
    ]);
}
?> 