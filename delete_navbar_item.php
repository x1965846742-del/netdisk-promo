<?php
/**
 * 删除导航项目
 * POST 请求
 * 参数: { id: number }
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

$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => '无效的导航ID'
    ]);
    exit;
}

try {
    // 检查表是否存在（删除操作时表不存在则直接返回成功）
    try {
        $pdo->query("SELECT 1 FROM navbar_items LIMIT 1");
    } catch (PDOException $e) {
        // 表不存在，无需删除
        echo json_encode([
            'success' => true,
            'message' => '导航删除成功'
        ]);
        exit;
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 先删除相关的资源关联（表可能不存在）
    try {
        $sql = "DELETE FROM navbar_resources WHERE navbar_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // navbar_resources 表不存在，忽略
    }
    
    // 再删除导航项目
    $sql = "DELETE FROM navbar_items WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '导航删除成功'
    ]);
    
} catch (PDOException $e) {
    // 回滚事务
    $pdo->rollBack();
    error_log("删除导航项目失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '删除导航项目失败'
    ]);
}
?>
