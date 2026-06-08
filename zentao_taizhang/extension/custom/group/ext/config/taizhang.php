<?php
if(isset($config->group->package))
{
    $config->group->package->taizhangother = new stdclass();
    $config->group->package->taizhangother->subset = 'taizhang';
    $config->group->package->taizhangother->privs  = array();
    $config->group->package->taizhangother->privs['taizhang-browse'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5, 'depend' => array());
    $config->group->package->taizhangother->privs['taizhang-edit']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6, 'depend' => array());
    $config->group->package->taizhangother->privs['taizhang-delete'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 7, 'depend' => array());
    $config->group->package->taizhangother->privs['taizhang-export'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 8, 'depend' => array());
}
