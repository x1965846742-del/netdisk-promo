<?php
/**
 * 获取导航栏项目列表
 * GET 请求
 * 返回: { success: true, data: [{ id, name, sort_weight, resource_count, icon_type, icon_emoji, icon_fa, icon_custom, create_time }] }
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
    // 检查 navbar_items 表是否存在，不存在则创建
    try {
        $pdo->query("SELECT 1 FROM navbar_items LIMIT 1");
    } catch (PDOException $e) {
        // 表不存在，创建它（使用utf8mb4支持emoji）
        $pdo->exec("CREATE TABLE IF NOT EXISTS `navbar_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '导航名称',
            `sort_weight` int(11) DEFAULT '0' COMMENT '排序权重，数字越大越靠前',
            `icon_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'emoji' COMMENT '图标类型',
            `icon_emoji` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'emoji图标编码',
            `icon_fa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'FontAwesome图标',
            `icon_custom` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '自定义图标Base64编码',
            `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导航栏项目表'");
    }
    
    // 检查 navbar_resources 表是否存在，不存在则创建
    try {
        $pdo->query("SELECT 1 FROM navbar_resources LIMIT 1");
    } catch (PDOException $e) {
        // 表不存在，创建它
        $pdo->exec("CREATE TABLE IF NOT EXISTS `navbar_resources` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `navbar_id` int(11) NOT NULL COMMENT '导航项目ID',
            `resource_id` int(11) NOT NULL COMMENT '资源ID',
            `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_navbar_resource` (`navbar_id`, `resource_id`),
            KEY `idx_navbar_id` (`navbar_id`),
            KEY `idx_resource_id` (`resource_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导航栏资源关联表'");
    }
    
    // 检查是否有图标字段
    $hasIconFields = true;
    try {
        $pdo->query("SELECT icon_type, icon_emoji, icon_fa, icon_custom FROM navbar_items LIMIT 1");
    } catch (PDOException $e) {
        $hasIconFields = false;
        // 确保表使用utf8mb4
        $pdo->exec("ALTER TABLE `navbar_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    
    // 确保现有列使用utf8mb4
    try {
        $pdo->exec("ALTER TABLE `navbar_items` MODIFY COLUMN `icon_emoji` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT ''");
    } catch (PDOException $e) {
        // 列可能不存在，忽略
    }
    
    // 获取所有导航项目，按 sort_weight 降序排列
    if ($hasIconFields) {
        $sql = "SELECT 
                    n.id,
                    n.name,
                    n.sort_weight,
                    n.icon_type,
                    n.icon_emoji,
                    n.icon_fa,
                    n.icon_custom,
                    n.create_time,
                    n.update_time,
                    COUNT(nr.id) as resource_count
                FROM navbar_items n
                LEFT JOIN navbar_resources nr ON n.id = nr.navbar_id
                GROUP BY n.id
                ORDER BY n.sort_weight DESC, n.id DESC";
    } else {
        $sql = "SELECT 
                    n.id,
                    n.name,
                    n.sort_weight,
                    'emoji' as icon_type,
                    '' as icon_emoji,
                    '' as icon_fa,
                    '' as icon_custom,
                    n.create_time,
                    n.update_time,
                    COUNT(nr.id) as resource_count
                FROM navbar_items n
                LEFT JOIN navbar_resources nr ON n.id = nr.navbar_id
                GROUP BY n.id
                ORDER BY n.sort_weight DESC, n.id DESC";
    }
    
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
    
} catch (PDOException $e) {
    error_log("获取导航项目失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '获取导航项目失败'
    ]);
}
?>
