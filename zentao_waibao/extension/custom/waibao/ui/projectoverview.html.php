<?php
/**
 * 外包标识插件 — 项目级外包工时总览
 *
 * 在项目左侧「外包工时」菜单下展示：本项目团队内各外包人员的
 * 已消耗（主）/ 预计 / 剩余工时，支持日期范围筛选。
 */
namespace zin;

$project   = $this->view->project;
$members   = $this->view->members;
$totalRow  = $this->view->totalRow;
$begin     = $this->view->begin;
$end       = $this->view->end;
$projectID = $this->view->projectID;

/* ── 顶部筛选栏 ── */
$submitURL = helper::createLink('waibao', 'projectOverview');

featureBar
(
    div
    (
        setClass('flex items-center flex-wrap gap-2'),
        label($lang->waibao->filterBegin),
        datePicker(set::name('begin'), set::value($begin)),
        label($lang->waibao->filterEnd),
        datePicker(set::name('end'), set::value($end)),
        btn(setClass('btn btn-primary'), set::onClick('submitFilter()'), $lang->waibao->search),
        btn(setClass('btn btn-success'), set::onClick('exportData()'), $lang->waibao->exportExcel)
    )
);

/* ── 顶部合计卡片（单行横排） ── */
$panelTitle = $project ? ($project->name . ' · ' . $lang->waibao->projectOverview) : $lang->waibao->projectOverview;
panel
(
    set::title($panelTitle),
    div
    (
        setClass('flex items-center gap-8 px-3 py-2 flex-wrap'),
        div(setClass('flex items-center gap-1'), span(setClass('text-gray-500 text-sm'), $lang->waibao->consumedHours . '：'), span(setClass('font-bold text-orange-600'), zget($totalRow, 'consumed', 0))),
        div(setClass('flex items-center gap-1'), span(setClass('text-gray-500 text-sm'), $lang->waibao->estimatedHours . '：'), span(setClass('font-bold'), zget($totalRow, 'estimated', 0))),
        div(setClass('flex items-center gap-1'), span(setClass('text-gray-500 text-sm'), $lang->waibao->remainHours . '：'), span(setClass('font-bold'), zget($totalRow, 'remain', 0))),
        div(setClass('flex items-center gap-1'), span(setClass('text-gray-500 text-sm'), $lang->waibao->outsourcedCount . '：'), span(setClass('font-bold'), count($members) . '人'))
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

/* ── JavaScript ── */
$exportURL = helper::createLink('waibao', 'exportProjectOverview');

$jsHead = 'var poSubmitURL=' . json_encode($submitURL)   . ';'
        . 'var poExportURL=' . json_encode($exportURL)   . ';'
        . 'var poProjectID=' . json_encode($projectID)   . ';';

$jsBody = <<<'JS'
function submitFilter()
{
    var beginEl = document.querySelector('[name="begin"]');
    var endEl   = document.querySelector('[name="end"]');
    var begin   = beginEl ? (beginEl.value || '') : '';
    var end     = endEl   ? (endEl.value   || '') : '';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = poSubmitURL;
    form.innerHTML = '<input type="hidden" name="projectID" value="' + poProjectID + '">'
                   + '<input type="hidden" name="begin" value="' + begin + '">'
                   + '<input type="hidden" name="end" value="' + end + '">';
    document.body.appendChild(form);
    form.submit();
}
function exportData()
{
    var beginEl = document.querySelector('[name="begin"]');
    var endEl   = document.querySelector('[name="end"]');
    var begin   = (beginEl ? (beginEl.value || '') : '').replace(/-/g, '_');
    var end     = (endEl   ? (endEl.value   || '') : '').replace(/-/g, '_');
    window.location.href = poExportURL
        + '?projectID=' + poProjectID
        + '&begin='     + begin
        + '&end='       + end;
}
JS;

html('<script>' . $jsHead . $jsBody . '</script>');

render();
