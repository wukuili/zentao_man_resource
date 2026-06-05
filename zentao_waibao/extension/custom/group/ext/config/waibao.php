<?php
/**
 * 外包标识插件 — 权限包注册
 *
 * 定义权限包 waibaother，将其归入 waibao 子集，
 * 并声明每个权限方法的适用版本与排序。
 */

if(isset($config->group->package))
{
    $config->group->package->waibaother = new stdclass();
    $config->group->package->waibaother->subset = 'waibao';
    $config->group->package->waibaother->privs  = array();
    $config->group->package->waibaother->privs['waibao-browse']              = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5,  'depend' => array());
    $config->group->package->waibaother->privs['waibao-orgdimension']        = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 10, 'depend' => array());
    $config->group->package->waibaother->privs['waibao-projectdimension']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 15, 'depend' => array());
    $config->group->package->waibaother->privs['waibao-memberdimension']      = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 20, 'depend' => array());
    $config->group->package->waibaother->privs['waibao-setUserOutsourced']    = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 25, 'depend' => array());
    $config->group->package->waibaother->privs['waibao-batchSetOutsourced']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 30, 'depend' => array());
}