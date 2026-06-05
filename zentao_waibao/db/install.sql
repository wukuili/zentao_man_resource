-- 外包标识插件数据库安装脚本
-- 在 zt_user 表中新增 outsourced 字段，0=自有人员，1=外包人员

ALTER TABLE `zt_user` ADD COLUMN `outsourced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否外包(0:自有,1:外包)' AFTER `role`;