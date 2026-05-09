<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    // 获取参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(50, intval($_GET['per_page'] ?? 9))); // 限制每页最多50条
    $search = trim($_GET['search'] ?? '');
    $navbar_id = isset($_GET['navbar_id']) ? intval($_GET['navbar_id']) : 0;
    
    // 计算偏移量
    $offset = ($page - 1) * $perPage;
    
    // 构建查询条件 - 先检查字段是否存在
    $whereConditions = ['1=1']; // 默认条件
    $params = [];
    $joinClause = '';
    
    // 检查show_on_homepage字段是否存在
    try {
        $pdo->query("SELECT show_on_homepage FROM link_records LIMIT 1");
        $whereConditions = ['lr.show_on_homepage = 1'];
    } catch (PDOException $e) {
        // 字段不存在，忽略此条件
        error_log("show_on_homepage字段不存在，跳过筛选条件");
    }
    
    // 如果指定了导航ID，则按导航筛选
    if ($navbar_id > 0) {
        // 检查 navbar_resources 表是否存在
        try {
            $pdo->query("SELECT 1 FROM navbar_resources LIMIT 1");
            $joinClause = 'INNER JOIN navbar_resources nr ON lr.id = nr.resource_id';
            $whereConditions[] = 'nr.navbar_id = ?';
            $params[] = $navbar_id;
        } catch (PDOException $e) {
            // 表不存在，返回空结果
            echo json_encode([
                'success' => true,
                'resources' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => 0,
                    'total_pages' => 0
                ],
                'search' => $search
            ]);
            exit;
        }
    }
    
    if (!empty($search)) {
        $whereConditions[] = '(lr.custom_name LIKE ? OR lr.original_url LIKE ?)';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 查询总数
    $countSql = "SELECT COUNT(*) as total FROM link_records lr {$joinClause} WHERE {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 计算总页数
    $totalPages = ceil($totalRecords / $perPage);
    
    // 构建 SELECT 字段列表
    $selectFields = "lr.id,
                    lr.custom_name,
                    lr.original_url,
                    lr.custom_short_url,
                    lr.preview_image,
                    lr.encrypted_code,
                    lr.create_time";
    
    // 检查sort_weight字段是否存在，以及is_pinned字段
    $sortClause = "lr.create_time DESC";
    $hasPinned = false;
    try {
        $pdo->query("SELECT is_pinned FROM link_records LIMIT 1");
        $hasPinned = true;
        $selectFields .= ", lr.is_pinned";
    } catch (PDOException $e) {
        // is_pinned字段不存在
    }
    
    try {
        $pdo->query("SELECT sort_weight FROM link_records LIMIT 1");
        if ($hasPinned) {
            $sortClause = "lr.is_pinned DESC, lr.sort_weight DESC, lr.create_time DESC";
        } else {
            $sortClause = "lr.sort_weight DESC, lr.create_time DESC";
        }
    } catch (PDOException $e) {
        // sort_weight字段不存在
        if ($hasPinned) {
            $sortClause = "lr.is_pinned DESC, lr.create_time DESC";
        }
    }
    
    // 查询数据
    $dataSql = "SELECT {$selectFields}
                FROM link_records lr
                {$joinClause}
                WHERE {$whereClause}
                ORDER BY {$sortClause}
                LIMIT {$perPage} OFFSET {$offset}";
    
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $resources = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 格式化数据
    foreach ($resources as &$resource) {
        // 格式化时间
        if ($resource['create_time']) {
            $resource['create_time'] = date('Y-m-d H:i', strtotime($resource['create_time']));
        }
        
        // 确保字段存在
        $resource['custom_name'] = $resource['custom_name'] ?: '未命名资源';
        $resource['custom_short_url'] = $resource['custom_short_url'] ?: null;
        $resource['preview_image'] = $resource['preview_image'] ?? null;
    }
    
    echo json_encode([
        'success' => true,
        'resources' => $resources,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => (int)$totalRecords,
            'total_pages' => (int)$totalPages
        ],
        'search' => $search
    ]);
    
} catch (Exception $e) {
    error_log("获取主页资源失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '获取资源失败: ' . $e->getMessage(),
        'resources' => [],
        'pagination' => [
            'current_page' => 1,
            'per_page' => 9,
            'total_records' => 0,
            'total_pages' => 0
        ]
    ]);
}
?>
