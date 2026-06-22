<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->crmsync)) $config->crmsync = new stdclass();

/* 默认项目管理方式: 融合瀑布。 */
$config->crmsync->projectModel = 'waterfallplus';

/* 自动建项目时统一挂的默认项目经理账号(建完人工改派)。 */
$config->crmsync->defaultPM = 'admin';

/* 单纯项目(未选产品)归属的默认项目集ID; 0 表示顶层项目。 */
$config->crmsync->defaultProgram = 0;

/* 单纯项目自动创建产品时的默认产品名(留空则用项目名)。 */
$config->crmsync->defaultProductName = '';

/* 默认项目工期(月), 当商机无合同日期时用于推算结束日期。 */
$config->crmsync->defaultDurationMonths = 6;

/*
 * 对外接口令牌表: token => 操作者账号。
 * 令牌通过禅道"对接设置"页维护，保存后写入 zt_config，由框架在运行时注入 $config->crmsync->apiTokens。
 * 必须初始化为 stdclass(而非 array): config.php 先于 DB 配置加载, 框架 mergeConfig 仅在
 * section 为对象(is_object)时才会注入令牌, 若此处预置为 array() 则 DB 令牌会被静默丢弃(导致 401)。
 */
if(!isset($config->crmsync->apiTokens)) $config->crmsync->apiTokens = new stdclass();

/* 数据表常量。 */
$dbPrefix = (isset($config->db) && isset($config->db->prefix)) ? $config->db->prefix : 'zt_';
if(!defined('TABLE_CRMSYNC_MAP')) define('TABLE_CRMSYNC_MAP', '`' . $dbPrefix . 'crmsync_map`');
