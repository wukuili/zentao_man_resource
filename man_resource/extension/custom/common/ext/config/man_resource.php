<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->man_resource)) $config->man_resource = new stdclass();

$config->man_resource->includedPriv['man_resource'] = array('orgdimension', 'projectdimension', 'memberdimension', 'simulate', 'prediction');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->man_resource = 'man_resource';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->man_resource = 'man_resource';