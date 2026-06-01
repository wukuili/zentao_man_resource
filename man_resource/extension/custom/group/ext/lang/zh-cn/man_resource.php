<?php
/* 将 资源日历 作为一个独立的视图权限暴露出来 */
$lang->resource->man_resource = new stdclass();
$lang->resource->man_resource->orgdimension = "orgdimension";
$lang->resource->man_resource->projectdimension = "projectdimension";

if (!isset($lang->group->views)) {
    // 兼容老版本
} else {
    // 这里不是把 man_resource 加入到视图控制（顶级菜单开关），ZenTao 18+ 如果要在视图里勾选，需要注册在 moduleList 里？
    // 先简单注入，可能需要看 ZenTao 20+ 的特定做法
}

