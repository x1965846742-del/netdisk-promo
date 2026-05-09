<?php
/**
 * 获取后台统计数据
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $today = date('Y-m-d');
    
    // 检查homepage_stats表是否存在
    $tableExists = $pdo->query("SHOW TABLES LIKE 'homepage_stats'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `homepage_stats` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `visit_date` date NOT NULL,
              `visit_count` int(11) DEFAULT 1,
              `unique_visitors` int(11) DEFAULT 1,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_date` (`visit_date`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='主页访问统计表'
        ");
    }
    
    // 检查link_records表的统计字段是否存在
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
    
    // 获取今日网站访问量
    $stmt = $pdo->prepare("SELECT visit_count, unique_visitors FROM homepage_stats WHERE visit_date = ?");
    $stmt->execute([$today]);
    $todayVisits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取总访问量
    $stmt = $pdo->query("SELECT COALESCE(SUM(visit_count), 0) as total_visits, COALESCE(SUM(unique_visitors), 0) as total_unique FROM homepage_stats");
    $totalVisits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 重置过期的today_clicks
    $stmt = $pdo->prepare("UPDATE link_records SET today_clicks = 0 WHERE last_click_date != ? OR last_click_date IS NULL");
    $stmt->execute([$today]);
    
    // 获取今日总点击量
    $stmt = $pdo->query("SELECT COALESCE(SUM(today_clicks), 0) as today_clicks FROM link_records");
    $todayClicks = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取总点击量
    $stmt = $pdo->query("SELECT COALESCE(SUM(click_count), 0) as total_clicks FROM link_records");
    $totalClicks = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取总资源数
    $stmt = $pdo->query("SELECT COUNT(*) as total_resources FROM link_records");
    $totalResources = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取点击量TOP10资源（总计）
    $stmt = $pdo->query("SELECT custom_name, encrypted_code, COALESCE(click_count, 0) as click_count, COALESCE(today_clicks, 0) as today_clicks FROM link_records ORDER BY click_count DESC LIMIT 10");
    $topResources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取今日点击量TOP10资源
    $stmt = $pdo->query("SELECT custom_name, encrypted_code, COALESCE(click_count, 0) as click_count, COALESCE(today_clicks, 0) as today_clicks FROM link_records WHERE today_clicks > 0 ORDER BY today_clicks DESC LIMIT 10");
    $topResourcesToday = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取最近7天访问趋势
    $stmt = $pdo->query("SELECT visit_date, visit_count, unique_visitors FROM homepage_stats ORDER BY visit_date DESC LIMIT 7");
    $recentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'today_visits' => (int)($todayVisits['visit_count'] ?? 0),
            'today_unique' => (int)($todayVisits['unique_visitors'] ?? 0),
            'total_visits' => (int)($totalVisits['total_visits'] ?? 0),
            'total_unique' => (int)($totalVisits['total_unique'] ?? 0),
            'today_clicks' => (int)($todayClicks['today_clicks'] ?? 0),
            'total_clicks' => (int)($totalClicks['total_clicks'] ?? 0),
            'total_resources' => (int)($totalResources['total_resources'] ?? 0),
            'top_resources' => $topResources,
            'top_resources_today' => $topResourcesToday,
            'recent_stats' => array_reverse($recentStats)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("获取统计数据失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
