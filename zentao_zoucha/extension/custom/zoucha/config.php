<?php
if(!isset($config->zoucha)) $config->zoucha = new stdclass();

/* ── 走查阈值（管理员可改本文件调整）── */
$config->zoucha->staleDays    = 7;   // R2：近一周未更新（天）
$config->zoucha->longTaskDays = 14;  // R5：任务持续时间超 2 周（天）
$config->zoucha->overdueMin   = 1;   // R3：命中所需最少逾期任务数

/* 启用哪些规则（按此顺序展示）。可删项以停用某条规则。 */
$config->zoucha->rules = array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');

/* 列表默认分页条数 */
$config->zoucha->recPerPage = 20;

/* 规则标签颜色 */
$config->zoucha->ruleColors = array(
    'noTask'      => '#e74c3c',
    'stale'       => '#e67e22',
    'overdue'     => '#c0392b',
    'noExecution' => '#8e44ad',
    'longTask'    => '#d35400',
);
