<?php
/**
 * 调试脚本：检查导航栏相关数据
 */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache');

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>导航栏调试</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 15px 0; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #667eea; color: white; }
        .ok { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 导航栏调试信息</h1>
";

try {
    // 1. 检查数据库连接
    echo "<div class='card'>";
    echo "<h2>1. 数据库连接状态</h2>";
    echo "<p class='ok'>✅ 数据库连接成功</p>";
    echo "<p>数据库: " . $db_name . " | 用户: " . $db_user . "</p>";
    echo "</div>";

    // 2. 检查主页设置表
    echo "<div class='card'>";
    echo "<h2>2. 主页设置 (homepage_settings)</h2>";
    
    $tableExists = $pdo->query("SHOW TABLES LIKE 'homepage_settings'")->fetch();
    if (!$tableExists) {
        echo "<p class='error'>❌ homepage_settings 表不存在</p>";
    } else {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
        $settings = [];
        echo "<table><tr><th>设置项</th><th>值</th><th>状态</th></tr>";
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
            $isOk = ($row['setting_value'] === '1');
            $status = $isOk ? "<span class='ok'>✅ 开启</span>" : "<span class='warning'>⚠️ 关闭</span>";
            echo "<tr><td>{$row['setting_key']}</td><td>{$row['setting_value']}</td><td>{$status}</td></tr>";
        }
        echo "</table>";
        
        // 检查关键设置
        echo "<h3>关键设置检查:</h3>";
        $checks = [
            'enable_side_nav' => '右侧悬浮导航',
            'side_nav_pc' => 'PC端显示',
            'side_nav_mobile' => '手机端显示',
            'navbar_enabled' => '顶部导航栏'
        ];
        
        foreach ($checks as $key => $label) {
            $value = isset($settings[$key]) ? $settings[$key] : '❌ 不存在';
            if ($value === '1') {
                echo "<p class='ok'>✅ {$label} = {$value}</p>";
            } elseif ($value === '0') {
                echo "<p class='error'>❌ {$label} = {$value} (需要开启)</p>";
            } else {
                echo "<p class='warning'>⚠️ {$label} = {$value}</p>";
            }
        }
    }
    echo "</div>";

    // 3. 检查导航项目表
    echo "<div class='card'>";
    echo "<h2>3. 导航项目 (navbar_items)</h2>";
    
    $tableExists = $pdo->query("SHOW TABLES LIKE 'navbar_items'")->fetch();
    if (!$tableExists) {
        echo "<p class='error'>❌ navbar_items 表不存在</p>";
    } else {
        $stmt = $pdo->query("SELECT * FROM navbar_items ORDER BY sort_weight DESC, id DESC");
        $count = $stmt->rowCount();
        
        echo "<p>共有 <strong>{$count}</strong> 个导航项目</p>";
        
        if ($count == 0) {
            echo "<p class='error'>❌ 没有导航项目！需要先在后台添加导航分类</p>";
        } else {
            echo "<table><tr><th>ID</th><th>名称</th><th>排序</th><th>图标类型</th><th>创建时间</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['sort_weight']}</td>
                    <td>{$row['icon_type']}</td>
                    <td>{$row['create_time']}</td>
                </tr>";
            }
            echo "</table>";
        }
    }
    echo "</div>";

    // 4. API 返回测试
    echo "<div class='card'>";
    echo "<h2>4. API 接口测试</h2>";
    
    echo "<h3>get_navbar_items.php 返回:</h3>";
    ob_start();
    include 'get_navbar_items.php';
    $output = ob_get_clean();
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    $json = json_decode($output, true);
    if ($json && $json['success']) {
        echo "<p class='ok'>✅ API 返回成功，共有 " . count($json['data']) . " 个导航项目</p>";
    } else {
        echo "<p class='error'>❌ API 返回失败</p>";
    }
    echo "</div>";

    // 5. 解决方案
    echo "<div class='card'>";
    echo "<h2>5. 解决方案</h2>";
    echo "<ol>";
    echo "<li>确保 <strong>navbar_items</strong> 表中有导航项目数据</li>";
    echo "<li>确保后台设置了 <strong>enable_side_nav = 1</strong></li>";
    echo "<li>确保后台设置了 <strong>side_nav_pc = 1</strong></li>";
    echo "<li>添加导航项目后，清除浏览器缓存重新访问</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ 发生错误</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
