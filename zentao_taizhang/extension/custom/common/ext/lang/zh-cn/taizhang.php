<?php
/**
 * 顶级主导航注册 —— 在最外层主导航栏（我的地盘/项目集/…/组织/后台）新增独立入口「项目台账」。
 *
 * 关键机制（见 module/common/lang/menu.php 与 module/common/model.php::getMainNavList）：
 *   1) getMainNavList() 只遍历 $lang->mainNav->menuOrder 里登记的项；
 *   2) 每个 $lang->mainNav->{key} 必须是字符串 "标题|模块|方法|参数"，会被 explode('|') 解析；
 *   3) 显示与否由 common::hasPriv(模块,方法) 决定（taizhang.browse 已在 config/ext 注册为 logonMethod，登录即可访问）。
 */

/* 顶部主导航图标 */
if(!isset($lang->navIcons))     $lang->navIcons     = array();
if(!isset($lang->navIconNames)) $lang->navIconNames = array();
$lang->navIcons['taizhang']     = "<i class='icon icon-table'></i>";
$lang->navIconNames['taizhang'] = 'table';

/* 1) 顶级主导航项（字符串格式，含图标）。
 * 注意：下标 62 已被商业版 OA(办公) 占用（extension/max/.../zentaobiz.php:
 *   $lang->mainNav->menuOrder[62] = 'oa';），台账 ext 在其后加载，若复用 62
 *   会把 OA 顶替掉。故改用未被占用的 64，排在「OA(62)」之后、「后台(65)」之前。 */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->taizhang      = "{$lang->navIcons['taizhang']} 项目台账|taizhang|browse|";
$lang->mainNav->menuOrder[64] = 'taizhang';

/* 2) 进入 taizhang 应用后的顶部一级导航标签 */
if(!isset($lang->taizhang)) $lang->taizhang = new stdclass();
if(!isset($lang->taizhang->menu)) $lang->taizhang->menu = new stdclass();
$lang->taizhang->menu->browse = array('link' => '台账列表|taizhang|browse', 'order' => 5);
$lang->taizhang->menuOrder[5] = 'browse';

/* 3) 导航高亮分组：自身归属自身，避免借用其他模块高亮 */
$lang->navGroup->taizhang = 'taizhang';
