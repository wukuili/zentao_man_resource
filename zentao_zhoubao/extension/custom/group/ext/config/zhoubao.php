<?php
if(isset($config->group->package))
{
    $config->group->package->zhoubaoother = new stdclass();
    $config->group->package->zhoubaoother->subset = 'zhoubao';
    $config->group->package->zhoubaoother->privs  = array();
    $config->group->package->zhoubaoother->privs['zhoubao-browse']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-edit']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-view']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 7,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-export']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 8,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-copyLast'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 9,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-cronPush'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 10, 'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-manage']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 11, 'depend' => array());
}
