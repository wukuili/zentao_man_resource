<?php
/**
 * 外包标识插件 — 应用注册与菜单配置
 *
 * 注册 waibao 模块为独立应用，并加入顶部应用菜单。
 * 声明 includedPriv 以便权限系统识别本模块的所有方法。
 */

if(!isset($config)) $config = new stdclass();
if(!isset($config->waibao)) $config->waibao = new stdclass();

/* 声明本模块包含的权限方法，供权限过滤使用 */
$config->waibao->includedPriv['waibao'] = array('summary', 'projectOverview', 'browse', 'orgdimension', 'projectdimension', 'memberdimension', 'setUserOutsourced', 'batchSetOutsourced');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->waibao = 'waibao';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->waibao = 'waibao';