<?php
if(!isset($config->taizhang)) $config->taizhang = new stdclass();

/* 利润率警告阈值（低于此值显示红色文字） */
$config->taizhang->profitRateWarn = 35;

/* 利润率危险阈值（低于此值显示红色背景） */
$config->taizhang->profitRateDanger = 0;

/* 项目阶段选项（键为存储值，值为显示文字） */
$config->taizhang->phaseList = array(
    '立项'     => '立项',
    '开发中'   => '开发中',
    '测试中'   => '测试中',
    '交付中'   => '交付中',
    '验收'     => '验收',
    '运维支持' => '运维支持',
    '已结项'   => '已结项',
    '暂停'     => '暂停',
);

/* 每月标准工时（用于人月换算，默认 160h/月） */
$config->taizhang->hoursPerMonth = 160;
