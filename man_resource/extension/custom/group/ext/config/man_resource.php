<?php
if(isset($config->group->package))
{
    $config->group->package->man_resourceother = new stdclass();
    $config->group->package->man_resourceother->subset = 'man_resource';
    $config->group->package->man_resourceother->privs  = array();
    $config->group->package->man_resourceother->privs['man_resource-orgdimension'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5, 'depend' => array());
}