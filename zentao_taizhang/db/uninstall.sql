-- 项目台账插件卸载 SQL —— 由禅道 extensionModel::executeDB($code, 'uninstall') 自动执行。
-- 框架按 ';' 切分逐句执行，并把 'zt_' 自动替换为实际表前缀（$config->db->prefix）。
-- 作用：删除台账数据表，并清掉用户组里残留的台账权限授权行。

DROP TABLE IF EXISTS `zt_taizhang`;

DELETE FROM `zt_grouppriv` WHERE `module` = 'taizhang';
