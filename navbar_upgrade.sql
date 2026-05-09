-- 导航栏功能升级SQL
-- 运行此SQL前请先备份数据库

-- 0. 修改表字符集为 utf8mb4（支持emoji）
ALTER TABLE `navbar_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `navbar_resources` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. 修改navbar_items表，添加/修改图标相关字段（使用utf8mb4）
ALTER TABLE `navbar_items` 
MODIFY COLUMN `icon_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'emoji' COMMENT '图标类型：emoji=emoji图标，fontawesome=fontawesome图标，custom=自定义图片',
MODIFY COLUMN `icon_emoji` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'emoji图标编码',
MODIFY COLUMN `icon_fa` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'FontAwesome图标',
MODIFY COLUMN `icon_custom` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '自定义图标Base64编码';

-- 如果上述语句报"Duplicate column"错误，说明字段已存在，跳过即可

-- 2. 添加右侧悬浮导航开关设置
INSERT INTO homepage_settings (setting_key, setting_value, setting_description) 
VALUES ('enable_side_nav', '1', '是否开启右侧悬浮导航栏')
ON DUPLICATE KEY UPDATE setting_value = '1', setting_description = '是否开启右侧悬浮导航栏';
