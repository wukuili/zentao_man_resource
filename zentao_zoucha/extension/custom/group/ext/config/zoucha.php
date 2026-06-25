<?php
if(isset($config->group->package))
{
    $config->group->package->zouchaother = new stdclass();
    $config->group->package->zouchaother->subset = 'zoucha';
    $config->group->package->zouchaother->privs  = array();
    $config->group->package->zouchaother->privs['zoucha-browse'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5, 'depend' => array());
    $config->group->package->zouchaother->privs['zoucha-export'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6, 'depend' => array());
}
