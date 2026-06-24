<?php
/**
 * 外包标识插件 — 导航菜单集成
 *
 * - 顶部「外包工时」应用菜单落到多维汇总页 summary（全局，组织/system 应用）。
 * - 各项目模型（敏捷/瀑布/看板/敏捷+/瀑布+/IPD，含未启用迭代的 noMultiple 变体）
 *   的项目内菜单落到 projectOverview，%s 占位由禅道自动填入当前项目ID。
 *
 * 重要：项目应用（project app）在渲染某模块的页面前，会校验该模块是否登记进
 * 当前项目"模型 + 是否多迭代"对应的菜单表；缺失登记（尤其 noMultiple 单迭代项目）
 * 会被项目应用判定为非法模块而跳回首页。因此必须像 man_resource 一样把所有
 * 项目模型与 noMultiple 变体都登记齐全，并补 menuOrder。
 */

/* 顶部系统菜单（全局多维汇总）。点击来源为 system 应用，X-ZIN-APP=system，仍落在 system。*/
$lang->system->menu->waibao  = array('link' => '外包工时|waibao|summary', 'alias' => 'browse,orgdimension,projectdimension,memberdimension', 'order' => 120);
$lang->system->menuOrder[120] = 'waibao';

/* 项目内菜单（项目级外包工时，%s = 当前项目ID） */
$projectMenuLink = '外包工时|waibao|projectOverview|projectID=%s';

/* 多迭代项目（multiple = 1）各模型菜单 */
$lang->scrum->menu->waibao      = array('link' => $projectMenuLink, 'order' => 120);
$lang->scrum->menuOrder[120]    = 'waibao';

$lang->waterfall->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
$lang->waterfall->menuOrder[120] = 'waibao';

if(isset($lang->kanbanProject->menu))
{
    $lang->kanbanProject->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->kanbanProject->menuOrder[120] = 'waibao';
}

if(isset($lang->agileplus->menu))
{
    $lang->agileplus->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->agileplus->menuOrder[120] = 'waibao';
}

if(isset($lang->waterfallplus->menu))
{
    $lang->waterfallplus->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->waterfallplus->menuOrder[120] = 'waibao';
}

if(isset($lang->ipd->menu))
{
    $lang->ipd->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->ipd->menuOrder[120] = 'waibao';
}

/* 未启用迭代的项目（multiple = 0，noMultiple）——默认创建的项目多为此类，必须登记，
 * 否则 admin 点项目内"外包工时"会被项目应用判为非法模块而跳回首页。*/
if(isset($lang->project->noMultiple))
{
    $lang->project->noMultiple->scrum->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->project->noMultiple->scrum->menuOrder[120] = 'waibao';

    $lang->project->noMultiple->kanban->menu->waibao   = array('link' => $projectMenuLink, 'order' => 120);
    $lang->project->noMultiple->kanban->menuOrder[120] = 'waibao';
}

/* 导航分组：仅作为缺少 X-ZIN-APP 头时的兜底应用归属。
 * 设为 project，使得在项目上下文（X-ZIN-APP=project / tab=project）下，
 * commonTao::isProjectAdmin('waibao') 对"当前项目成员/项目经理"返回 true，
 * 从而免系统级权限放行项目内 projectOverview / projectdimension。
 * 从 system 应用点击 summary 时携带 X-ZIN-APP=system，不受此兜底影响。*/
$lang->navGroup->waibao = 'project';
