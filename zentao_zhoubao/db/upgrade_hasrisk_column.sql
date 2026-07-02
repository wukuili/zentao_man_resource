-- 升级: 为已部署的旧版 zt_zhoubao 补充 hasRisk 列(新装环境 install.sql 已包含, 无需执行)。
-- MariaDB 支持 IF NOT EXISTS; 若为原生 MySQL 报错, 去掉 IF NOT EXISTS 后单独执行。
ALTER TABLE `zt_zhoubao`
  ADD COLUMN IF NOT EXISTS `hasRisk` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '是否有风险/需协调资源' AFTER `risk`;

UPDATE `zt_zhoubao` SET `hasRisk` = 'yes' WHERE `risk` IS NOT NULL AND `risk` != '';
