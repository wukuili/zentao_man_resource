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
 * CRM 调用 receive/products 时带 X-Resource-Token 头或 ?token= 参数命中即放行,
 * 命中后以对应账号身份建项目(动态记录的 openedBy)。请在"对接设置"页或此处配置强随机 token。
 */
$config->crmsync->apiTokens = array(
    // 'please-rotate-this-token' => 'admin',
);

/* 数据表常量。 */
$dbPrefix = (isset($config->db) && isset($config->db->prefix)) ? $config->db->prefix : 'zt_';
if(!defined('TABLE_CRMSYNC_MAP')) define('TABLE_CRMSYNC_MAP', '`' . $dbPrefix . 'crmsync_map`');
