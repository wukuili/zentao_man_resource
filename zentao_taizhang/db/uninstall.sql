-- 项目台账插件卸载 SQL —— 由禅道 extensionModel::executeDB($code, 'uninstall') 自动执行。
-- 框架按 ';' 切分逐句执行，并把 'zt_' 自动替换为实际表前缀（$config->db->prefix）。
-- 注意：只清理权限残留，不删数据库表（保留业务数据）。

DELETE FROM `zt_grouppriv` WHERE `module` = 'taizhang';
