-- 项目走查插件卸载 SQL，由禅道 extensionModel::executeDB 在卸载时自动执行。
-- 框架按分号切分逐句执行，并把 zt_ 自动替换为实际表前缀。
-- 注意：注释里不能出现分号，否则会被切成无效 SQL 片段而报错。
-- 作用：清掉用户组里残留的走查权限授权行（本插件无业务表可删）。

DELETE FROM `zt_grouppriv` WHERE `module` = 'zoucha';
