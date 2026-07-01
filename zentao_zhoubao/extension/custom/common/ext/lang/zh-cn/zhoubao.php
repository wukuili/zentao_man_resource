<?php
/**
 * 顶级主导航注册 —— 在最外层主导航栏新增独立入口「项目周报」。
 * 机制见 module/common/model.php::getMainNavList：只遍历 menuOrder，
 * 每项为字符串 "标题|模块|方法|参数"，显示与否由 common::hasPriv 决定。
 */
if(!isset($lang->navIcons))     $lang->navIcons     = array();
if(!isset($lang->navIconNames)) $lang->navIconNames = array();
$lang->navIcons['zhoubao']     = "<i class='icon icon-calendar'></i>";
$lang->navIconNames['zhoubao'] = 'calendar';

/* 顶级主导航项。下标避开 OA=62/台账=64/后台=65/走查=63，用空闲的 61。 */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->zhoubao        = "{$lang->navIcons['zhoubao']} 项目周报|zhoubao|browse|";
$lang->mainNav->menuOrder[61]  = 'zhoubao';

/* 进入 zhoubao 应用后的顶部一级导航标签 */
if(!isset($lang->zhoubao)) $lang->zhoubao = new stdclass();
if(!isset($lang->zhoubao->menu)) $lang->zhoubao->menu = new stdclass();
$lang->zhoubao->menu->browse = array('link' => '周报看板|zhoubao|browse', 'order' => 5);
$lang->zhoubao->menuOrder[5] = 'browse';

/* 导航高亮分组：自身归属自身 */
if(!isset($lang->navGroup)) $lang->navGroup = new stdclass();
$lang->navGroup->zhoubao = 'zhoubao';
