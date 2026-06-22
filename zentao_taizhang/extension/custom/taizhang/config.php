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

/* 项目类别选项（键为存储值，值为显示文字） */
$config->taizhang->projectCategoryList = array(
    '施工类' => '施工类',
    '软件类' => '软件类',
    '集成类' => '集成类',
);

/* 是/否类字段通用选项 */
$config->taizhang->yesNoList = array(
    '是' => '是',
    '否' => '否',
);

/* 每月标准工时（历史字段保留，当前页面按人天展示） */
$config->taizhang->hoursPerMonth = 160;

/* 每人每天标准工时与人天成本 */
$config->taizhang->hoursPerDay = 8;
$config->taizhang->costPerDay  = 1100;

/* 列表默认分页条数 */
$config->taizhang->recPerPage = 15;
