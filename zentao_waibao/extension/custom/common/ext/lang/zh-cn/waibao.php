<?php
/**
 * 外包标识插件 — 导航菜单集成
 *
 * - 顶部「外包工时」应用菜单落到多维汇总页 summary（全局）。
 * - 各项目模型（敏捷/瀑布/…）的项目内左侧菜单落到 projectOverview，
 *   通过 %s 占位由禅道自动填入当前项目ID，展示该项目组的外包工时。
 */

/* 顶部系统菜单（全局多维汇总） */
$lang->system->menu->waibao = array('link' => '外包工时|waibao|summary', 'alias' => 'browse,orgdimension,projectdimension,memberdimension', 'order' => 120);

/* 项目内左侧菜单（项目级外包工时，%s = 当前项目ID） */
$projectMenuLink = '外包工时|waibao|projectOverview|projectID=%s';

/* 敏捷项目菜单 */
$lang->scrum->menu->waibao = array('link' => $projectMenuLink, 'order' => 120);

/* 瀑布项目菜单 */
$lang->waterfall->menu->waibao = array('link' => $projectMenuLink, 'order' => 120);

/* 敏捷+ 项目菜单 */
if(isset($lang->agileplus))
{
    $lang->agileplus->menu->waibao = array('link' => $projectMenuLink, 'order' => 120);
}

/* 瀑布+ 项目菜单 */
if(isset($lang->waterfallplus))
{
    $lang->waterfallplus->menu->waibao = array('link' => $projectMenuLink, 'order' => 120);
}

/* IPD 项目菜单 */
if(isset($lang->ipd))
{
    $lang->ipd->menu->waibao = array('link' => $projectMenuLink, 'order' => 120);
}

/* 导航分组 */
$lang->navGroup->waibao = 'waibao';
