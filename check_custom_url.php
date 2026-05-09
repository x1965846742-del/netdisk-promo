<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['custom_url'])) {
        throw new Exception('无效的请求数据');
    }

    $customUrl = trim($data['custom_url']);
    
    // 检查是否为空
    if (empty($customUrl)) {
        echo json_encode(['available' => true]);
        exit;
    }

    // 检查长度限制
    if (mb_strlen($customUrl, 'UTF-8') > 50) {
        echo json_encode([
            'available' => false, 
            'error' => '自定义链接不能超过50个字符'
        ]);
        exit;
    }

    // 检查是否包含非法字符
    if (preg_match('/[<>"\'\s\\\\\/\?#&]/', $customUrl)) {
        echo json_encode([
            'available' => false, 
            'error' => '自定义链接不能包含空格和特殊字符：< > " \' \\ / ? # &'
        ]);
        exit;
    }

    // 检查是否与系统文件冲突
    $systemFiles = [
        'index.php', 'shencheng.html', 'xiazai.html', 'config.php',
        'save_record.php', 'get_records.php', 'delete_record.php',
        'get_original_url.php', 'update_name.php', 'update_url.php',
        'check_links.php', 'submit_search.php', 'style.css',
        'qrcode.min.js', 'admin', 'api', 'assets', 'css', 'js', 'images'
    ];

    if (in_array(strtolower($customUrl), array_map('strtolower', $systemFiles))) {
        echo json_encode([
            'available' => false, 
            'error' => '该名称与系统文件冲突，请选择其他名称'
        ]);
        exit;
    }

    // 检查数据库中是否已存在
    $stmt = $pdo->prepare("SELECT id FROM link_records WHERE custom_short_url = ?");
    $stmt->execute([$customUrl]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'available' => false, 
            'error' => '该自定义链接已被使用，请选择其他名称'
        ]);
    } else {
        echo json_encode(['available' => true]);
    }

} catch (Exception $e) {
    echo json_encode([
        'available' => false, 
        'error' => '检查失败：' . $e->getMessage()
    ]);
}
?>
