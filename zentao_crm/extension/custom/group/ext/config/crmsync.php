<?php
/* 注册权限分组包, 使插件方法可在"权限-视图"中授权。 */
if(isset($config->group->package))
{
    $config->group->package->crmsyncother = new stdclass();
    $config->group->package->crmsyncother->subset = 'crmsync';
    $config->group->package->crmsyncother->privs  = array();
    $config->group->package->crmsyncother->privs['crmsync-browse']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5, 'depend' => array());
    $config->group->package->crmsyncother->privs['crmsync-settings'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6, 'depend' => array());
    $config->group->package->crmsyncother->privs['crmsync-retry']    = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 7, 'depend' => array());
}
