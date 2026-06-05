<?php
/**
 * 外包标识插件 — 全局路由与过滤器配置
 *
 * 注册无需登录即可访问的方法、仅需登录的方法、
 * 以及过滤器默认值。
 */

/* 无需登录即可访问的方法 */
$config->openMethods[] = 'waibao.browse';

/* 仅需登录即可访问的方法（无需特定权限） */
$config->logonMethods[] = 'waibao.summary';
$config->logonMethods[] = 'waibao.projectOverview';
$config->logonMethods[] = 'waibao.browse';
$config->logonMethods[] = 'waibao.orgdimension';
$config->logonMethods[] = 'waibao.projectdimension';
$config->logonMethods[] = 'waibao.memberdimension';
$config->logonMethods[] = 'waibao.setUserOutsourced';
$config->logonMethods[] = 'waibao.batchSetOutsourced';

/* 过滤器默认值 */
$filter->waibao = new stdclass();
$filter->waibao->default = new stdclass();
$filter->waibao->default->cookie['showOutsourced'] = 'code';
$filter->waibao->default->cookie['searchType'] = 'code';

/* 项目级权限注入 */
if(isset($config->programPriv))
{
    $config->programPriv->scrum[]           = 'waibao';
    $config->programPriv->waterfall[]       = 'waibao';
    $config->programPriv->agileplus[]       = 'waibao';
    $config->programPriv->waterfallplus[]   = 'waibao';
    $config->programPriv->ipd[]             = 'waibao';
    $config->programPriv->noSprint[]        = 'waibao';
}