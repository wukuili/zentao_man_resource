-- 升级: 为已部署的旧版 zt_taizhang 补充工程类台账字段(新装环境 install.sql 已包含, 无需执行)。
-- MariaDB 支持 IF NOT EXISTS; 若为原生 MySQL 报错, 去掉 IF NOT EXISTS 后单独执行缺失列。
ALTER TABLE `zt_taizhang`
  ADD COLUMN IF NOT EXISTS `projectCategory`   varchar(20)   NOT NULL DEFAULT '' COMMENT '项目类别(施工类/软件类/集成类)' AFTER `phase`,
  ADD COLUMN IF NOT EXISTS `projectIntro`      text                              COMMENT '项目简介' AFTER `currentStatus`,
  ADD COLUMN IF NOT EXISTS `contractSignDate`  date          DEFAULT NULL        COMMENT '合同签署时间' AFTER `projectIntro`,
  ADD COLUMN IF NOT EXISTS `subcontractStatus` varchar(50)   NOT NULL DEFAULT '' COMMENT '分包合同状态' AFTER `contractSignDate`,
  ADD COLUMN IF NOT EXISTS `engineeringStatus` varchar(50)   NOT NULL DEFAULT '' COMMENT '工程状态' AFTER `subcontractStatus`,
  ADD COLUMN IF NOT EXISTS `procurementMethod` varchar(50)   NOT NULL DEFAULT '' COMMENT '采购方式' AFTER `engineeringStatus`,
  ADD COLUMN IF NOT EXISTS `supplyUnit`        varchar(100)  NOT NULL DEFAULT '' COMMENT '供货单位' AFTER `procurementMethod`,
  ADD COLUMN IF NOT EXISTS `constructionUnit`  varchar(100)  NOT NULL DEFAULT '' COMMENT '安装/施工单位' AFTER `supplyUnit`,
  ADD COLUMN IF NOT EXISTS `planStartDate`     date          DEFAULT NULL        COMMENT '计划开工日期' AFTER `constructionUnit`,
  ADD COLUMN IF NOT EXISTS `actualStartDate`   date          DEFAULT NULL        COMMENT '实际开工日期' AFTER `planStartDate`,
  ADD COLUMN IF NOT EXISTS `planEndDate`       date          DEFAULT NULL        COMMENT '计划完工日期' AFTER `actualStartDate`,
  ADD COLUMN IF NOT EXISTS `actualEndDate`     date          DEFAULT NULL        COMMENT '实际完工日期' AFTER `planEndDate`,
  ADD COLUMN IF NOT EXISTS `acceptanceStatus`  varchar(50)   NOT NULL DEFAULT '' COMMENT '验收情况' AFTER `actualEndDate`,
  ADD COLUMN IF NOT EXISTS `securityMeasures`  varchar(10)   NOT NULL DEFAULT '' COMMENT '安保措施是否到位(是/否)' AFTER `acceptanceStatus`,
  ADD COLUMN IF NOT EXISTS `hazardousWork`     varchar(10)   NOT NULL DEFAULT '' COMMENT '是否涉及危险作业(是/否)' AFTER `securityMeasures`,
  ADD COLUMN IF NOT EXISTS `startDocsComplete` varchar(10)   NOT NULL DEFAULT '' COMMENT '开工资料是否齐全(是/否)' AFTER `hazardousWork`,
  ADD COLUMN IF NOT EXISTS `progressDeviation` text                              COMMENT '进度偏差说明' AFTER `receivedAmount`,
  ADD COLUMN IF NOT EXISTS `remark`            text                              COMMENT '备注' AFTER `progressDeviation`;
