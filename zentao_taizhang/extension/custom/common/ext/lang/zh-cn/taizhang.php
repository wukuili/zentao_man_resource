<?php
/* ── 模块内一级菜单 ── */
if(!isset($lang->taizhang)) $lang->taizhang = new stdclass();
if(!isset($lang->taizhang->menu)) $lang->taizhang->menu = new stdclass();
$lang->taizhang->menu->browse = array('link' => '台账列表|taizhang|browse', 'order' => 10);

/* ── 左侧顶级主导航（所有 vision 均生效） ── */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->taizhang         = new stdclass();
$lang->mainNav->taizhang->name   = '项目台账';
$lang->mainNav->taizhang->link   = 'taizhang|browse';
$lang->mainNav->taizhang->icon   = 'table';
$lang->mainNav->taizhang->order  = 25;

/* 导航高亮分组：活跃页面归属自身，不借用其他模块高亮 */
$lang->navGroup->taizhang = 'taizhang';

/* ── rnd 视觉：同时加入顶部 system 菜单（可选，保持顶部也能访问） ── */
if(isset($config->vision) && $config->vision == 'rnd')
{
    $lang->system->menu->taizhang          = array('link' => '项目台账|taizhang|browse');
    $lang->system->menuOrder[15]           = 'taizhang';
}
