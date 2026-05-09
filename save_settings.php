<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['type']) || !isset($data['value'])) {
        throw new Exception('无效的请求数据');
    }

    switch($data['type']) {
        case 'bing':
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('bing_api_key', ?) 
                                  ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$data['value'], $data['value']]);
            break;

        case 'baidu':
            $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('baidu_token', ?) 
                                  ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$data['value'], $data['value']]);
            break;

        default:
            throw new Exception('无效的设置类型');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 