<?php
/**
 * 外包标识插件 — 项目级权限映射
 *
 * 将项目维度相关方法注册到项目级权限中，
 * 使项目经理无需系统级权限即可查看项目维度统计。
 */
$config->project->includedPriv['waibao'] = array('projectdimension', 'projectOverview');
$config->project->noSprintPriv['waibao'] = array('projectdimension', 'projectOverview');