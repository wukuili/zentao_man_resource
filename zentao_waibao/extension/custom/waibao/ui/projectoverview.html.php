<?php
/**
 * 外包标识插件 — 项目级外包工时总览
 *
 * 在项目左侧「外包工时」菜单下展示：本项目团队内各外包人员的
 * 已消耗（主）/ 预计 / 剩余工时。已消耗取自 zt_effort 工时日志。
 */
namespace zin;

$project  = $this->view->project;
$members  = $this->view->members;
$totalRow = $this->view->totalRow;

/* ── 顶部合计卡片 ── */
div
(
    setClass('flex gap-4 mb-4'),
    panel
    (
        setClass('flex-1'),
        set::title($project ? ($project->name . ' · ' . $lang->waibao->projectOverview) : $lang->waibao->projectOverview),
        div
        (
            setClass('grid grid-cols-4 gap-4 text-center p-2'),
            div(span(setClass('text-xl font-bold text-orange-600'), zget($totalRow, 'consumed', 0)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-lg font-bold'), zget($totalRow, 'estimated', 0)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-lg font-bold'), zget($totalRow, 'remain', 0)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours)),
            div(span(setClass('text-lg font-bold'), count($members) . '人'), br(), span(setClass('text-gray-500'), $lang->waibao->outsourcedCount))
        )
    )
);

/* ── 外包成员明细表 ── */
$cols = array();
$cols['realname']  = array('title' => $lang->waibao->realname,       'width' => '160px');
$cols['deptName']  = array('title' => $lang->waibao->department,     'width' => '160px');
$cols['consumed']  = array('title' => $lang->waibao->consumedHours,  'width' => '140px');
$cols['estimated'] = array('title' => $lang->waibao->estimatedHours, 'width' => '120px');
$cols['remain']    = array('title' => $lang->waibao->remainHours,    'width' => '120px');
$cols['taskCount'] = array('title' => $lang->waibao->taskCount,      'width' => '100px');

$tableData = array();
foreach($members as $m)
{
    $tableData[] = array(
        'realname'  => $m['realname'],
        'deptName'  => $m['deptName'],
        'consumed'  => $m['consumed'],
        'estimated' => $m['estimated'],
        'remain'    => $m['remain'],
        'taskCount' => $m['taskCount'],
    );
}

panel
(
    set::title($lang->waibao->outsourcedLabel),
    empty($tableData) ? div(setClass('p-8 text-center text-gray-500'), $lang->waibao->noData) : dtable
    (
        set::cols($cols),
        set::data($tableData),
        set::hover(true),
        set::striped(true)
    )
);

render();
