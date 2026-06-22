-- 升级: 为已部署的旧版 zt_taizhang 补充金额列(新装环境 install.sql 已包含, 无需执行)。
-- MariaDB 支持 IF NOT EXISTS; 若为原生 MySQL 报错, 去掉 IF NOT EXISTS 后单独执行缺失列。
ALTER TABLE `zt_taizhang`
  ADD COLUMN IF NOT EXISTS `outsourcingAmount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '外采金额(万元)' AFTER `revenue`,
  ADD COLUMN IF NOT EXISTS `receivedAmount`    decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '回款金额(万元)' AFTER `outsourcingAmount`;
