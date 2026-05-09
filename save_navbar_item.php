<?php
/**
 * 创建或更新导航项目
 * POST 请求
 * 参数: { id?: number, name: string, sort_weight?: number, icon_type?: string, icon_emoji?: string, icon_fa?: string, icon_custom?: string }
 * 返回: { success: true, id: number }
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

// 验证名称非空
$name = isset($input['name']) ? trim($input['name']) : '';
if (empty($name)) {
    echo json_encode([
        'success' => false,
        'error' => '导航名称不能为空'
    ]);
    exit;
}

$id = isset($input['id']) ? intval($input['id']) : 0;
$sort_weight = isset($input['sort_weight']) ? intval($input['sort_weight']) : 0;

// 图标相关字段
$icon_type = isset($input['icon_type']) ? trim($input['icon_type']) : 'emoji';
$icon_emoji = isset($input['icon_emoji']) ? trim($input['icon_emoji']) : '';
$icon_fa = isset($input['icon_fa']) ? trim($input['icon_fa']) : '';
$icon_custom = isset($input['icon_custom']) ? trim($input['icon_custom']) : '';

try {
    // 检查 navbar_items 表是否存在，不存在则创建
    try {
        $pdo->query("SELECT 1 FROM navbar_items LIMIT 1");
    } catch (PDOException $e) {
        // 表不存在，创建它（使用utf8mb4支持emoji）
        $pdo->exec("CREATE TABLE IF NOT EXISTS `navbar_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '导航名称',
            `sort_weight` int(11) DEFAULT '0' COMMENT '排序权重',
            `icon_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'emoji' COMMENT '图标类型',
            `icon_emoji` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'emoji图标',
            `icon_fa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'FontAwesome',
            `icon_custom` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '自定义图标',
            `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导航栏项目表'");
    }
    
    // 检查是否有图标字段，没有则添加
    try {
        $pdo->query("SELECT icon_type FROM navbar_items LIMIT 1");
    } catch (PDOException $e) {
        // 先修改表字符集
        $pdo->exec("ALTER TABLE `navbar_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        // 添加新字段
        $pdo->exec("ALTER TABLE `navbar_items` 
            ADD COLUMN `icon_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'emoji' COMMENT '图标类型' AFTER `sort_weight`,
            ADD COLUMN `icon_emoji` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'emoji图标' AFTER `icon_type`,
            ADD COLUMN `icon_fa` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'FontAwesome' AFTER `icon_emoji`,
            ADD COLUMN `icon_custom` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '自定义图标' AFTER `icon_fa`");
    }
    
    // 确保现有数据的icon_emoji列使用utf8mb4
    try {
        $pdo->exec("ALTER TABLE `navbar_items` MODIFY COLUMN `icon_emoji` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT ''");
    } catch (PDOException $e) {
        // 列可能不存在或已是正确类型，忽略错误
    }
    
    if ($id > 0) {
        // 更新现有导航项目
        $sql = "UPDATE navbar_items SET name = ?, sort_weight = ?, icon_type = ?, icon_emoji = ?, icon_fa = ?, icon_custom = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $sort_weight, $icon_type, $icon_emoji, $icon_fa, $icon_custom, $id]);
        
        echo json_encode([
            'success' => true,
            'id' => $id,
            'message' => '导航更新成功'
        ]);
    } else {
        // 创建新导航项目
        $sql = "INSERT INTO navbar_items (name, sort_weight, icon_type, icon_emoji, icon_fa, icon_custom) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $sort_weight, $icon_type, $icon_emoji, $icon_fa, $icon_custom]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'id' => $newId,
            'message' => '导航创建成功'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("保存导航项目失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '保存导航项目失败: ' . $e->getMessage()
    ]);
}
?>