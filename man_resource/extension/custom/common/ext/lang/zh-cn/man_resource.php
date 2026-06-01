<?php

/* 独立应用的二级菜单 (ZenTao 20+ 左侧应用菜单需要自身有menu) */
if(!isset($lang->man_resource)) $lang->man_resource = new stdclass();
if(!isset($lang->man_resource->menu)) $lang->man_resource->menu = new stdclass();
$lang->man_resource->menu->orgdimension = array('link' => '组织日历|man_resource|orgdimension');
$lang->man_resource->menu->projectdimension = array('link' => '项目日历|man_resource|projectdimension');

/* 将自己的模块分组设为自己，避免高亮错误 */
$lang->navGroup->man_resource = 'man_resource';

/* ZenTao 20+ 视觉模式 (rnd vision) */
if($config->vision == 'rnd')
{
    $lang->system->menu->man_resource = array('link' => '资源日历|man_resource|orgdimension');
    $lang->system->menuOrder[14] = 'man_resource';

    $lang->navGroup->man_resource = 'system';

    $lang->scrum->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
    $lang->scrum->menuOrder[44] = 'man_resource';

    $lang->waterfall->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
    $lang->waterfall->menuOrder[79] = 'man_resource';

    if(isset($lang->kanbanProject->menu))
    {
        $lang->kanbanProject->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->kanbanProject->menuOrder[14] = 'man_resource';
    }

    if(isset($lang->agileplus->menu))
    {
        $lang->agileplus->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->agileplus->menuOrder[44] = 'man_resource';
    }

    if(isset($lang->waterfallplus->menu))
    {
        $lang->waterfallplus->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->waterfallplus->menuOrder[79] = 'man_resource';
    }

    if(isset($lang->ipd->menu))
    {
        $lang->ipd->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->ipd->menuOrder[79] = 'man_resource';
    }

    if(isset($lang->project->noMultiple))
    {
        $lang->project->noMultiple->scrum->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->project->noMultiple->scrum->menuOrder[49] = 'man_resource';

        $lang->project->noMultiple->kanban->menu->man_resource = array('link' => '资源日历|man_resource|projectdimension|projectID=%s');
        $lang->project->noMultiple->kanban->menuOrder[19] = 'man_resource';
    }
}