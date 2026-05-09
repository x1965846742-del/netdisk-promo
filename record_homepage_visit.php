<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

try {
    $today = date('Y-m-d');
    
    // 检查表是否存在
    $tableExists = $pdo->query("SHOW TABLES LIKE 'homepage_stats'")->fetch();
    if (!$tableExists) {
        // 创建统计表
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
    
    // 简单的防刷机制：使用Session检查是否为同一用户的重复访问
    session_start();
    $sessionKey = 'homepage_visit_' . $today;
    
    $isNewVisitor = !isset($_SESSION[$sessionKey]);
    
    if ($isNewVisitor) {
        // 新访客：插入或更新，同时增加访问次数和独立访客数
        $stmt = $pdo->prepare("
            INSERT INTO homepage_stats (visit_date, visit_count, unique_visitors) 
            VALUES (?, 1, 1)
            ON DUPLICATE KEY UPDATE 
            visit_count = visit_count + 1,
            unique_visitors = unique_visitors + 1
        ");
        $stmt->execute([$today]);
        $_SESSION[$sessionKey] = true;
    } else {
        // 老访客：只增加访问次数
        $stmt = $pdo->prepare("
            INSERT INTO homepage_stats (visit_date, visit_count, unique_visitors) 
            VALUES (?, 1, 0)
            ON DUPLICATE KEY UPDATE 
            visit_count = visit_count + 1
        ");
        $stmt->execute([$today]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("记录主页访问失败: " . $e->getMessage());
    echo json_encode(['success' => false]);
}
?>
