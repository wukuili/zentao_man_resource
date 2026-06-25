<?php
/**
 * 顶级主导航注册 —— 在最外层主导航栏新增独立入口「项目走查」。
 *
 * 机制（见 module/common/model.php::getMainNavList）：
 *   1) 只遍历 $lang->mainNav->menuOrder 里登记的项；
 *   2) 每个 $lang->mainNav->{key} 为字符串 "标题|模块|方法|参数"，按 '|' 解析；
 *   3) 显示与否由 common::hasPriv(模块,方法) 决定（zoucha.browse 已注册为 logonMethod，登录即可访问）。
 */

/* 顶部主导航图标 */
if(!isset($lang->navIcons))     $lang->navIcons     = array();
if(!isset($lang->navIconNames)) $lang->navIconNames = array();
$lang->navIcons['zoucha']     = "<i class='icon icon-search'></i>";
$lang->navIconNames['zoucha'] = 'search';

/* 1) 顶级主导航项。下标避开已被占用的 62(OA)、64(台账)、65(后台)，用空闲的 63。 */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->zoucha        = "{$lang->navIcons['zoucha']} 项目走查|zoucha|browse|";
$lang->mainNav->menuOrder[63] = 'zoucha';

/* 2) 进入 zoucha 应用后的顶部一级导航标签 */
if(!isset($lang->zoucha)) $lang->zoucha = new stdclass();
if(!isset($lang->zoucha->menu)) $lang->zoucha->menu = new stdclass();
$lang->zoucha->menu->browse = array('link' => '走查列表|zoucha|browse', 'order' => 5);
$lang->zoucha->menuOrder[5] = 'browse';

/* 3) 导航高亮分组：自身归属自身 */
$lang->navGroup->zoucha = 'zoucha';
