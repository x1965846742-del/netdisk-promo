<?php
/**
 * 更新导航项目的资源关联
 * POST 请求
 * 参数: { navbar_id: number, resource_ids: number[] }
 * 返回: { success: true }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

$navbar_id = isset($input['navbar_id']) ? intval($input['navbar_id']) : 0;
$resource_ids = isset($input['resource_ids']) ? $input['resource_ids'] : [];

if ($navbar_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => '无效的导航ID'
    ]);
    exit;
}

// 确保 resource_ids 是数组
if (!is_array($resource_ids)) {
    $resource_ids = [];
}

try {
    // 检查 navbar_resources 表是否存在
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
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 先删除该导航的所有资源关联
    $sql = "DELETE FROM navbar_resources WHERE navbar_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$navbar_id]);
    
    // 插入新的资源关联
    $insertedCount = 0;
    if (!empty($resource_ids)) {
        $sql = "INSERT INTO navbar_resources (navbar_id, resource_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($resource_ids as $resource_id) {
            $resource_id = intval($resource_id);
            if ($resource_id > 0) {
                $stmt->execute([$navbar_id, $resource_id]);
                $insertedCount++;
            }
        }
    }
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '资源关联更新成功',
        'inserted_count' => $insertedCount
    ]);
    
} catch (PDOException $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("更新资源关联失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '更新资源关联失败: ' . $e->getMessage()
    ]);
}
?>
