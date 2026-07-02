<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->zhoubao)) $config->zhoubao = new stdclass();

/* 声明本模块包含的权限方法 */
$config->zhoubao->includedPriv['zhoubao'] = array('browse', 'edit', 'view', 'export', 'copyLast', 'manage', 'history');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->zhoubao = 'zhoubao';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->zhoubao = 'zhoubao';
