-- 存量升级: zt_crmsync_map 增加同步目标类型(项目/项目集)。
-- 新装环境由 install.sql 直接建出该列; 此脚本对已存在的表重复执行会报 Duplicate column, 部署脚本已容错。
ALTER TABLE `zt_crmsync_map`
  ADD COLUMN `targetType` enum('project','program') NOT NULL DEFAULT 'project' COMMENT '同步目标类型' AFTER `projectID`;
