<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->zoucha)) $config->zoucha = new stdclass();

/* 声明本模块包含的权限方法 */
$config->zoucha->includedPriv['zoucha'] = array('browse', 'export', 'diagnostic');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->zoucha = 'zoucha';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->zoucha = 'zoucha';
