<?php
/**
 * 外包标识插件 — 模块配置
 *
 * 定义工作时长、负载范围、数据库表常量等。
 */

if(!isset($config)) $config = new stdclass();
if(!isset($config->waibao)) $config->waibao = new stdclass();

/* 每日标准工时 */
$config->waibao->workHoursPerDay = 8;

/* 负载率阈值（百分比） */
$config->waibao->loadRange = array();
$config->waibao->loadRange['relax']  = '50';
$config->waibao->loadRange['spare']  = '70';
$config->waibao->loadRange['normal'] = '90';
$config->waibao->loadRange['full']   = '100';
$config->waibao->loadRange['over']   = '120';

/* 负载状态颜色 */
$config->waibao->loadRangeColors = new stdclass();
$config->waibao->loadRangeColors->relax  = (object)array('fore' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => '宽松');
$config->waibao->loadRangeColors->spare  = (object)array('fore' => '#3b82f6', 'bg' => '#dbeafe', 'text' => '空闲');
$config->waibao->loadRangeColors->normal = (object)array('fore' => '#22c55e', 'bg' => '#dcfce7', 'text' => '正常');
$config->waibao->loadRangeColors->full   = (object)array('fore' => '#f59e0b', 'bg' => '#fef3c7', 'text' => '饱满');
$config->waibao->loadRangeColors->over   = (object)array('fore' => '#ef4444', 'bg' => '#fee2e2', 'text' => '超载');

/* 外包标识默认值 */
$config->waibao->outsourcedDefault = 0;

/* 数据库表常量 — 使用禅道已有的 TABLE_* 常量，仅定义本插件独有的表 */
$dbPrefix = (isset($config->db) && isset($config->db->prefix)) ? $config->db->prefix : 'zt_';
if(!defined('TABLE_WAIBAO_HOLIDAY')) define('TABLE_WAIBAO_HOLIDAY', '`' . $dbPrefix . 'holiday`');

/* 是否排除已关闭项目/执行的任务（默认排除） */
$config->waibao->excludeClosedProjects = true;