<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !is_array($input)) {
        throw new Exception('无效的请求数据');
    }
    
    // 验证和清理设置数据
    $allowedSettings = [
        'homepage_enabled' => 'boolean',
        'navbar_enabled' => 'boolean',
        'enable_side_nav' => 'boolean',
        'side_nav_pc' => 'boolean',
        'side_nav_mobile' => 'boolean',
        'site_title' => 'string',
        'site_subtitle' => 'string',
        'announcement_enabled' => 'boolean',
        'announcement_content' => 'text',
        'contact_qq' => 'string',
        'contact_email' => 'email',
        'records_per_page' => 'number',
        'show_create_time' => 'boolean',
        'show_platform_tag' => 'boolean',
        'display_layout' => 'string',
        'theme_color' => 'color',
        'background_style' => 'string',
        'xunlei_promo_enabled' => 'boolean',
        'xunlei_promo_keyword' => 'string',
        'xunlei_download_url' => 'string'
    ];
    
    $pdo->beginTransaction();
    
    foreach ($input as $key => $value) {
        if (!array_key_exists($key, $allowedSettings)) {
            continue; // 跳过不允许的设置
        }
        
        // 数据验证和清理
        $cleanValue = cleanSettingValue($value, $allowedSettings[$key]);
        
        // 更新或插入设置
        $stmt = $pdo->prepare("
            INSERT INTO homepage_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            update_time = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $cleanValue]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '设置保存成功'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("保存主页设置失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '保存设置失败: ' . $e->getMessage()
    ]);
}

function cleanSettingValue($value, $type) {
    switch ($type) {
        case 'boolean':
            return $value ? '1' : '0';
        
        case 'number':
            return strval(max(1, intval($value)));
        
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
        
        case 'color':
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#667eea';
        
        case 'text':
            return trim(mb_substr($value, 0, 2000, 'UTF-8')); // 限制长度，支持中文和emoji
        
        case 'string':
        default:
            return trim(mb_substr($value, 0, 500, 'UTF-8')); // 限制长度，支持中文和emoji
    }
}
?>
