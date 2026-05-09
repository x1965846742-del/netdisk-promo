<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    // 检查表是否存在，如果不存在则创建默认设置
    $tableExists = $pdo->query("SHOW TABLES LIKE 'homepage_settings'")->fetch();
    if (!$tableExists) {
        // 创建表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `homepage_settings` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `setting_key` varchar(50) NOT NULL COMMENT '设置键名',
              `setting_value` text COMMENT '设置值',
              `setting_description` varchar(255) DEFAULT NULL COMMENT '设置描述',
              `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_setting_key` (`setting_key`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='主页设置表'
        ");
        
        // 插入默认设置
        $defaultSettings = [
            ['homepage_enabled', '0', '是否开启主页预览模式'],
            ['navbar_enabled', '0', '是否开启导航栏'],
            ['enable_side_nav', '1', '是否开启右侧悬浮导航栏'],
            ['side_nav_pc', '1', '电脑端显示悬浮导航'],
            ['side_nav_mobile', '1', '手机端显示悬浮导航'],
            ['site_title', '资源分享站', '网站标题'],
            ['site_subtitle', '精品资源，免费分享', '网站副标题'],
            ['announcement_enabled', '1', '是否显示公告'],
            ['announcement_content', '欢迎访问本站！这里有最新最全的网盘资源分享。', '公告内容'],
            ['contact_qq', '155215141', '联系QQ'],
            ['contact_email', '', '联系邮箱'],
            ['records_per_page', '9', '每页显示记录数'],
            ['show_create_time', '1', '是否显示创建时间'],
            ['show_platform_tag', '1', '是否显示网盘标签'],
            ['display_layout', 'single', '显示布局：single单排/double双排'],
            ['theme_color', '#667eea', '主题色'],
            ['background_style', 'gradient', '背景样式'],
            ['xunlei_promo_enabled', '0', '是否开启迅雷推广'],
            ['xunlei_promo_keyword', 'AI领航局', '迅雷推广口令'],
            ['xunlei_download_url', 'https://x.xunlei.com/', '迅雷下载链接']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }
    
    // 获取所有设置
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
    $settings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // 检查并添加缺失的设置项
    $defaultKeys = ['side_nav_pc', 'side_nav_mobile', 'display_layout', 'xunlei_promo_enabled', 'xunlei_promo_keyword', 'xunlei_download_url'];
    foreach ($defaultKeys as $key) {
        if (!isset($settings[$key])) {
            $defaultValue = 'single';
            if ($key === 'side_nav_pc' || $key === 'side_nav_mobile') $defaultValue = '1';
            if ($key === 'xunlei_promo_enabled') $defaultValue = '0';
            if ($key === 'xunlei_promo_keyword') $defaultValue = 'AI领航局';
            if ($key === 'xunlei_download_url') $defaultValue = 'https://x.xunlei.com/';
            $settings[$key] = $defaultValue;
            try {
                $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $defaultValue]);
            } catch (Exception $e) {
                // 忽略插入错误
            }
        }
    }
    
    // 如果没有设置，返回默认值
    if (empty($settings)) {
        $settings = [
            'homepage_enabled' => '0',
            'navbar_enabled' => '0',
            'enable_side_nav' => '1',
            'side_nav_pc' => '1',
            'side_nav_mobile' => '1',
            'site_title' => '资源分享站',
            'site_subtitle' => '精品资源，免费分享',
            'announcement_enabled' => '1',
            'announcement_content' => '欢迎访问本站！这里有最新最全的网盘资源分享。',
            'contact_qq' => '155215141',
            'contact_email' => '',
            'records_per_page' => '9',
            'show_create_time' => '1',
            'show_platform_tag' => '1',
            'display_layout' => 'single',
            'theme_color' => '#667eea',
            'background_style' => 'gradient',
            'xunlei_promo_enabled' => '0',
            'xunlei_promo_keyword' => 'AI领航局',
            'xunlei_download_url' => 'https://x.xunlei.com/'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    error_log("获取主页设置失败: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '获取设置失败: ' . $e->getMessage()
    ]);
}
?>
