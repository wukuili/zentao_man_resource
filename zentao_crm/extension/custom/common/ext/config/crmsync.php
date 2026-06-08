<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->crmsync)) $config->crmsync = new stdclass();

/* 该应用包含的权限方法。 */
$config->crmsync->includedPriv['crmsync'] = array('browse', 'settings', 'retry');

/* 注册新应用到系统 config。 */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->crmsync = 'crmsync';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->crmsync = 'crmsync';
