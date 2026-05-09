<?php
/**
 * 修复导航栏表字符集问题
 * 运行一次即可，之后可以删除此文件
 */

header('Content-Type: text/html; charset=utf-8');

$db_host = 'localhost';
$db_user = '001001';
$db_pass = '001001';
$db_name = '001001';

echo "<h2>开始修复字符集问题...</h2>";

try {
    // 关键：必须设置 utf8mb4 为连接字符集
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:green'>✓ 数据库连接成功（使用utf8mb4）</p>";
} catch (PDOException $e) {
    die("<p style='color:red'>✗ 数据库连接失败: " . $e->getMessage() . "</p>");
}

// 验证连接字符集
$stmt = $pdo->query("SELECT @@character_set_client, @@character_set_connection");
$charset = $stmt->fetch();
echo "<p>当前连接字符集: client={$charset['@@character_set_client']}, connection={$charset['@@character_set_connection']}</p>";

echo "<h3>1. 检查表结构...</h3>";

// 检查navbar_items表
$stmt = $pdo->query("SHOW CREATE TABLE navbar_items");
$createTable = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre style='background:#f5f5f5;padding:10px;overflow-x:auto'>" . htmlspecialchars($createTable['Create Table']) . "</pre>";

// 执行字符集修复
echo "<h3>2. 修复字符集...</h3>";

try {
    $pdo->exec("ALTER TABLE `navbar_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green'>✓ navbar_items 表字符集已转换为 utf8mb4</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠ navbar_items 转换: " . $e->getMessage() . "</p>";
}

try {
    $pdo->exec("ALTER TABLE `navbar_resources` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green'>✓ navbar_resources 表字符集已转换为 utf8mb4</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠ navbar_resources 转换: " . $e->getMessage() . "</p>";
}

echo "<h3>3. 删除并重建 icon_emoji 列...</h3>";

try {
    // 删除可能存在的错误列
    $pdo->exec("ALTER TABLE `navbar_items` DROP COLUMN IF EXISTS `icon_type`");
    $pdo->exec("ALTER TABLE `navbar_items` DROP COLUMN IF EXISTS `icon_emoji`");
    $pdo->exec("ALTER TABLE `navbar_items` DROP COLUMN IF EXISTS `icon_fa`");
    $pdo->exec("ALTER TABLE `navbar_items` DROP COLUMN IF EXISTS `icon_custom`");
    echo "<p style='color:blue'>已删除旧列</p>";
    
    // 重新添加列（确保正确的字符集）
    $pdo->exec("ALTER TABLE `navbar_items` 
        ADD COLUMN `icon_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'emoji' AFTER `sort_weight`,
        ADD COLUMN `icon_emoji` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `icon_type`,
        ADD COLUMN `icon_fa` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `icon_emoji`,
        ADD COLUMN `icon_custom` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `icon_fa`");
    echo "<p style='color:green'>✓ 列已重新创建为 utf8mb4</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ 操作失败: " . $e->getMessage() . "</p>";
}

echo "<h3>4. 验证列结构...</h3>";
$stmt = $pdo->query("SHOW COLUMNS FROM navbar_items");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Field</th><th>Type</th><th>Collation</th></tr>";
foreach ($columns as $col) {
    $color = ($col['Collation'] && strpos($col['Collation'], 'utf8mb4') !== false) ? 'green' : 'red';
    echo "<tr style='color:$color'>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . ($col['Collation'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>5. 添加右侧导航开关设置...</h3>";
try {
    $stmt = $pdo->prepare("INSERT INTO homepage_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_description = VALUES(setting_description)");
    $stmt->execute(['enable_side_nav', '1', '是否开启右侧悬浮导航栏']);
    echo "<p style='color:green'>✓ 添加/更新了 enable_side_nav 设置</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>⚠ homepage_settings: " . $e->getMessage() . "</p>";
}

echo "<h3>6. 测试emoji保存...</h3>";
try {
    // 先删除测试数据
    $pdo->exec("DELETE FROM navbar_items WHERE name = '测试emoji'");
    
    // 直接用SQL测试
    $pdo->exec("INSERT INTO navbar_items (name, sort_weight, icon_type, icon_emoji) VALUES ('测试emoji', 0, 'emoji', '📁')");
    echo "<p style='color:blue'>INSERT执行成功</p>";
    
    // 读取测试
    $stmt = $pdo->query("SELECT icon_emoji FROM navbar_items WHERE name = '测试emoji'");
    $result = $stmt->fetch();
    
    if ($result && $result['icon_emoji'] === '📁') {
        echo "<p style='color:green; font-size:20px;'>✓ Emoji保存测试成功！</p>";
    } else {
        echo "<p style='color:red'>✗ Emoji保存测试失败，读取值: " . ($result['icon_emoji'] ?? 'NULL') . "</p>";
    }
    
    // 删除测试数据
    $pdo->exec("DELETE FROM navbar_items WHERE name = '测试emoji'");
    echo "<p style='color:gray'>测试数据已清理</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ 测试失败: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2 style='color:green'>修复完成！</h2>";
echo "<p>现在可以返回后台页面重新创建导航了。</p>";
echo "<p><a href='shencheng.html'>返回后台</a></p>";
echo "<p style='color:gray; font-size:12px;'>提示：此文件运行一次后建议删除，防止安全隐患。</p>";
?>
