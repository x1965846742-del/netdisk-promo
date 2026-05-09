<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 设置缓存控制
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 如果是 OPTIONS 请求，直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    // 获取分页参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(50, intval($_GET['per_page'] ?? 10))); // 默认每页10条，最多50条
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    error_log("搜索开始 =====================");
    error_log("搜索关键词: " . $search);
    error_log("分页参数: 第{$page}页，每页{$perPage}条");

    // 构建基础查询
    $query = "SELECT r.*, s.submit_bing, s.submit_baidu, s.is_valid, s.check_time 
             FROM link_records r 
             LEFT JOIN link_status s ON r.encrypted_code = s.encrypted_code";
    $params = array();

    // 如果有搜索关键词，添加搜索条件
    if (!empty($search)) {
        $query .= " WHERE (r.custom_name LIKE :search 
                   OR r.original_url LIKE :search 
                   OR r.share_url LIKE :search)";
        $params[':search'] = "%{$search}%";
        error_log("搜索条件: " . json_encode($params));
    }

    // 先获取总记录数
    $countQuery = str_replace("SELECT r.*, s.submit_bing, s.submit_baidu, s.is_valid, s.check_time", "SELECT COUNT(*) as total", $query);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);
    
    // 添加分页限制
    $offset = ($page - 1) * $perPage;
    $query .= " ORDER BY r.create_time DESC LIMIT {$perPage} OFFSET {$offset}";
    error_log("完整SQL: " . $query);
    
    // 准备并执行查询
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("找到记录数: " . count($records) . "/" . $totalRecords);
    error_log("分页信息: 第{$page}页，共{$totalPages}页");
    error_log("搜索结束 =====================");

    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => (int)$totalRecords,
            'total_pages' => (int)$totalPages
        ],
        'search_term' => $search
    ]);

} catch (Exception $e) {
    error_log("搜索错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 