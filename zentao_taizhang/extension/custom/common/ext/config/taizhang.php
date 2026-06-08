<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->taizhang)) $config->taizhang = new stdclass();

/* 声明本模块包含的权限方法 */
$config->taizhang->includedPriv['taizhang'] = array('browse', 'edit', 'delete', 'export');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->taizhang = 'taizhang';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->taizhang = 'taizhang';
