<?php
/* 将 CRM对接 的管理方法作为独立权限资源暴露(receive/products 为对外免登录接口, 走 token 鉴权, 不在此登记)。 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->crmsync = new stdclass();
$lang->resource->crmsync->browse   = 'browse';
$lang->resource->crmsync->settings = 'settings';
$lang->resource->crmsync->retry    = 'retry';
