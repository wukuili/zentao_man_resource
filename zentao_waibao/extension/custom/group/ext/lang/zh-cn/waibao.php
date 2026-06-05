<?php
/**
 * 外包标识插件 — 视图级权限资源定义
 *
 * 将每个控制器方法注册为权限资源，以便在权限分配界面展示。
 */

$lang->resource->waibao = new stdclass();
$lang->resource->waibao->summary             = 'summary';
$lang->resource->waibao->projectOverview     = 'projectOverview';
$lang->resource->waibao->browse              = 'browse';
$lang->resource->waibao->orgdimension        = 'orgdimension';
$lang->resource->waibao->projectdimension    = 'projectdimension';
$lang->resource->waibao->memberdimension     = 'memberdimension';
$lang->resource->waibao->setUserOutsourced   = 'setUserOutsourced';
$lang->resource->waibao->batchSetOutsourced  = 'batchSetOutsourced';