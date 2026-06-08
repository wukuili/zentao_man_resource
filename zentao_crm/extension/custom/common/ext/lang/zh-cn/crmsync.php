<?php
/* 独立应用的左侧菜单 (ZenTao 20+ 应用自身需要有 menu)。 */
if(!isset($lang->crmsync)) $lang->crmsync = new stdclass();
if(!isset($lang->crmsync->menu)) $lang->crmsync->menu = new stdclass();
$lang->crmsync->menu->browse   = array('link' => '同步记录|crmsync|browse');
$lang->crmsync->menu->settings = array('link' => '对接设置|crmsync|settings');

/* 将自己的模块分组设为自己, 避免高亮错误。 */
$lang->navGroup->crmsync = 'crmsync';

/* ZenTao 20+ 研发(rnd)视觉: 挂到"系统后台"菜单下。 */
if($config->vision == 'rnd')
{
    $lang->system->menu->crmsync = array('link' => 'CRM对接|crmsync|browse');
    $lang->system->menuOrder[15] = 'crmsync';

    $lang->navGroup->crmsync = 'system';
}
