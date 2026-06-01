CREATE TABLE IF NOT EXISTS `zt_resource_calendar` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `estimated_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `consumed_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remain_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `load_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `project_id` (`project_id`),
  KEY `task_id` (`task_id`),
  KEY `work_date` (`work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `zt_load_simulation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `simulation_name` varchar(255) NOT NULL,
  `operator` bigint(20) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `result_json` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
