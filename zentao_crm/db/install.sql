CREATE TABLE IF NOT EXISTS `zt_crmsync_map` (
  `id`            mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `opportunityId` varchar(64)  NOT NULL DEFAULT ''  COMMENT 'CRM商机ID',
  `projectID`     mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '禅道项目ID',
  `productId`     mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '关联产品ID, 0=单纯项目',
  `customerName`  varchar(255) NOT NULL DEFAULT '' COMMENT '客户名称',
  `oppName`       varchar(255) NOT NULL DEFAULT '' COMMENT '商机名称',
  `payload`       text                            COMMENT '原始回调JSON, 排错用',
  `syncStatus`    enum('success','failed') NOT NULL DEFAULT 'success' COMMENT '同步状态',
  `errorMsg`      varchar(500) NOT NULL DEFAULT '' COMMENT '失败原因',
  `createdBy`     varchar(30)  NOT NULL DEFAULT '' COMMENT '操作者',
  `createdDate`   datetime     DEFAULT NULL        COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `opportunityId` (`opportunityId`),
  KEY `projectID` (`projectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
