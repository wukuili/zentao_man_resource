<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->man_resource)) $config->man_resource = new stdclass();
$config->man_resource->workHoursPerDay = 8;
$config->man_resource->taskHourPredict    = 0;
$config->man_resource->notTaskHourPredict = 0;
$config->man_resource->predictHours       = 0;

$config->man_resource->loadRange = array();
$config->man_resource->loadRange['relax']  = '50';
$config->man_resource->loadRange['spare']  = '70';
$config->man_resource->loadRange['normal'] = '90';
$config->man_resource->loadRange['full']   = '100';
$config->man_resource->loadRange['over']   = '120';

$config->man_resource->loadRangeColors = new stdClass();
$config->man_resource->loadRangeColors->relax = new stdClass();
$config->man_resource->loadRangeColors->relax->fore = '#02850C';
$config->man_resource->loadRangeColors->relax->bg   = '#BBEFBF';
$config->man_resource->loadRangeColors->relax->text = '#8CE393';

$config->man_resource->loadRangeColors->spare = new stdClass();
$config->man_resource->loadRangeColors->spare->fore = '#02850C';
$config->man_resource->loadRangeColors->spare->bg   = '#8CE393';
$config->man_resource->loadRangeColors->spare->text = '#8CE393';

$config->man_resource->loadRangeColors->normal = new stdClass();
$config->man_resource->loadRangeColors->normal->fore = '#AF750C';
$config->man_resource->loadRangeColors->normal->bg   = '#FFD281';
$config->man_resource->loadRangeColors->normal->text = '#EDA523';

$config->man_resource->loadRangeColors->full = new stdClass();
$config->man_resource->loadRangeColors->full->fore = '#EC5B0F';
$config->man_resource->loadRangeColors->full->bg   = '#FFECE2';
$config->man_resource->loadRangeColors->full->text = '#EC5B0F';

$config->man_resource->loadRangeColors->over = new stdClass();
$config->man_resource->loadRangeColors->over->fore = '#C50404';
$config->man_resource->loadRangeColors->over->bg   = '#FF7C7C';
$config->man_resource->loadRangeColors->over->text = '#FF7C7C';

$config->man_resource->loadRangeColors->extreme = new stdClass();
$config->man_resource->loadRangeColors->extreme->fore = '#C20000';
$config->man_resource->loadRangeColors->extreme->bg   = '#FBA8A8';
$config->man_resource->loadRangeColors->extreme->text = '#C20000';

$config->man_resource->setload = new stdClass();
$config->man_resource->setload->requiredFields = 'relax,spare,normal,full,over';

$config->man_resource->apiTokens = array(
    // 'sample-token-please-rotate' => 'admin',
);

/* Tables */
$dbPrefix = (isset($config->db) && isset($config->db->prefix)) ? $config->db->prefix : 'zt_';
if(!defined('TABLE_RESOURCE_CALENDAR')) define('TABLE_RESOURCE_CALENDAR', '`' . $dbPrefix . 'resource_calendar`');
if(!defined('TABLE_LOAD_SIMULATION'))   define('TABLE_LOAD_SIMULATION', '`' . $dbPrefix . 'load_simulation`');
if(!defined('TABLE_HOLIDAY'))           define('TABLE_HOLIDAY', '`' . $dbPrefix . 'holiday`');
if(!defined('TABLE_TASK'))              define('TABLE_TASK', '`' . $dbPrefix . 'task`');
if(!defined('TABLE_TEAM'))              define('TABLE_TEAM', '`' . $dbPrefix . 'team`');
if(!defined('TABLE_USER'))              define('TABLE_USER', '`' . $dbPrefix . 'user`');
if(!defined('TABLE_DEPT'))              define('TABLE_DEPT', '`' . $dbPrefix . 'dept`');
if(!defined('TABLE_PROJECT'))           define('TABLE_PROJECT', '`' . $dbPrefix . 'project`');
