<?php
/**
 * 获取导航项目关联的资源
 * GET 请求
 * 参数: navbar_id (可选，不传则返回所有资源及其导航关联)
 * 返回: { success: true, data: [...] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

$navbar_id = isset($_GET['navbar_id']) ? intval($_GET['navbar_id']) : 0;

try {
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

    if ($navbar_id > 0) {
        // 获取指定导航的资源列表
        $sql = "SELECT 
                    lr.id,
                    lr.custom_name,
                    lr.original_url,
                    lr.create_time
                FROM link_records lr
                INNER JOIN navbar_resources nr ON lr.id = nr.resource_id
                WHERE nr.navbar_id = ?
                ORDER BY lr.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$navbar_id]);
        $resources = $stmt->fetchAll();
    } else {
        // 获取所有资源及其导航关联状态（不再限制 show_on_homepage，让管理员可以分配所有资源）
        $sql = "SELECT 
                    lr.id,
                    lr.custom_name,
                    lr.original_url,
                    lr.create_time,
                    lr.show_on_homepage,
                    GROUP_CONCAT(nr.navbar_id) as navbar_ids
                FROM link_records lr
                LEFT JOIN navbar_resources nr ON lr.id = nr.resource_id
                GROUP BY lr.id
                ORDER BY lr.id DESC";
        $stmt = $pdo->query($sql);
        $resources = $stmt->fetchAll();
        
        // 处理 navbar_ids 为数组
        foreach ($resources as &$resource) {
            if (!empty($resource['navbar_ids'])) {
                $resource['navbar_ids'] = array_map('intval', explode(',', $resource['navbar_ids']));
            } else {
                $resource['navbar_ids'] = [];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $resources
    ]);
    
} catch (PDOException $e) {
    error_log("获取导航资源失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '获取导航资源失败: ' . $e->getMessage()
    ]);
}
?>
