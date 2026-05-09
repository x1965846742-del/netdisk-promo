<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $today = date('Y-m-d');
    
    // 检查表是否存在
    $tableExists = $pdo->query("SHOW TABLES LIKE 'homepage_stats'")->fetch();
    if (!$tableExists) {
        echo json_encode([
            'success' => false,
            'error' => '统计表不存在'
        ]);
        exit;
    }
    
    // 获取今日统计
    $stmt = $pdo->prepare("SELECT visit_count, unique_visitors FROM homepage_stats WHERE visit_date = ?");
    $stmt->execute([$today]);
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取总统计
    $stmt = $pdo->query("SELECT SUM(visit_count) as total_visits, SUM(unique_visitors) as total_unique FROM homepage_stats");
    $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取最近7天统计
    $stmt = $pdo->query("
        SELECT visit_date, visit_count, unique_visitors 
        FROM homepage_stats 
        WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        ORDER BY visit_date DESC
    ");
    $recentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'today_visits' => $todayStats['visit_count'] ?? 0,
            'today_unique' => $todayStats['unique_visitors'] ?? 0,
            'total_visits' => $totalStats['total_visits'] ?? 0,
            'total_unique' => $totalStats['total_unique'] ?? 0,
            'recent_days' => $recentStats
        ]
    ]);
    
} catch (Exception $e) {
    error_log("获取主页统计失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '获取统计失败: ' . $e->getMessage()
    ]);
}
?>
