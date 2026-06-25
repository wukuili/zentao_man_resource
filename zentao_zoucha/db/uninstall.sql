-- 项目走查插件卸载 SQL —— 由禅道 extensionModel::executeDB($code, 'uninstall') 自动执行。
-- 框架按 ';' 切分逐句执行，并把 'zt_' 自动替换为实际表前缀。
-- 作用：清掉用户组里残留的走查权限授权行（无业务表可删）。

DELETE FROM `zt_grouppriv` WHERE `module` = 'zoucha';
