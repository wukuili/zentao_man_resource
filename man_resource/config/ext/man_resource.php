<?php
$config->openMethods[]  = 'man_resource.sethours';
$config->openMethods[]  = 'man_resource.setload';
$config->openMethods[]  = 'man_resource.setpredicthours';
$config->logonMethods[] = 'man_resource.sethours';
$config->logonMethods[] = 'man_resource.setload';
$config->logonMethods[] = 'man_resource.setpredicthours';

$filter->man_resource = new stdclass();
$filter->man_resource->default = new stdclass();
$filter->man_resource->default->cookie['showHoliday'] = 'code';
$filter->man_resource->default->cookie['searchType']  = 'code';

if(isset($config->programPriv))
{
    $config->programPriv->scrum[]         = 'man_resource';
    $config->programPriv->waterfall[]     = 'man_resource';
    $config->programPriv->agileplus[]     = 'man_resource';
    $config->programPriv->waterfallplus[] = 'man_resource';
    $config->programPriv->ipd[]           = 'man_resource';
    $config->programPriv->noSprint[]      = 'man_resource';
}