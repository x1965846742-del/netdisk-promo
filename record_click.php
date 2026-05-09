<?php
/**
 * 记录链接点击次数
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $encryptedCode = $data['encrypted_code'] ?? '';
    
    if (empty($encryptedCode)) {
        echo json_encode(['success' => false, 'error' => '缺少参数']);
        exit;
    }
    
    $today = date('Y-m-d');
    
    // 检查必要字段是否存在，如果不存在则添加
    try {
        $pdo->query("SELECT click_count FROM link_records LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE link_records ADD COLUMN click_count INT DEFAULT 0");
    }
    
    try {
        $pdo->query("SELECT today_clicks FROM link_records LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE link_records ADD COLUMN today_clicks INT DEFAULT 0");
    }
    
    try {
        $pdo->query("SELECT last_click_date FROM link_records LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE link_records ADD COLUMN last_click_date DATE DEFAULT NULL");
    }
    
    // 检查记录是否存在
    $stmt = $pdo->prepare("SELECT id, last_click_date FROM link_records WHERE encrypted_code = ?");
    $stmt->execute([$encryptedCode]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        if (($record['last_click_date'] ?? '') !== $today) {
            // 新的一天，重置今日点击数
            $stmt = $pdo->prepare("UPDATE link_records SET click_count = COALESCE(click_count, 0) + 1, today_clicks = 1, last_click_date = ? WHERE encrypted_code = ?");
            $stmt->execute([$today, $encryptedCode]);
        } else {
            // 同一天，累加点击数
            $stmt = $pdo->prepare("UPDATE link_records SET click_count = COALESCE(click_count, 0) + 1, today_clicks = COALESCE(today_clicks, 0) + 1 WHERE encrypted_code = ?");
            $stmt->execute([$encryptedCode]);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '记录不存在']);
    }
    
} catch (Exception $e) {
    error_log("记录点击失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
