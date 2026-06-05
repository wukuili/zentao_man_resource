<?php
/**
 * 外包标识插件 — 成员维度工时统计页面
 *
 * 按成员展示每个人的工时及外包标识，底部汇总自有/外包人员工时对比。
 */
namespace zin;

$data            = $this->view->data;
$users           = $this->view->users;
$currentUser     = $this->view->currentUser;
$begin           = $this->view->begin;
$end             = $this->view->end;
$date            = $this->view->date;
$loadRangeColors = $this->view->loadRangeColors;
$summary         = isset($data['summary']) ? $data['summary'] : array();
$memberData      = isset($data['members']) ? $data['members'] : array();

/* ── 顶部筛选栏 ── */
featureBar
(
    set::current($currentUser),
    div
    (
        setClass('flex items-center gap-2 ml-4'),
        label($lang->waibao->filterMember),
        picker
        (
            set::name('userID'),
            set::items($users),
            set::value($currentUser),
            set::placeholder($lang->waibao->allMember)
        ),
        label($lang->waibao->filterDate),
        datePicker
        (
            set::name('date'),
            set::value(str_replace('-', '_', $date))
        ),
        btn
        (
            setClass('btn btn-primary'),
            set::onClick("submitFilter()"),
            '查询'
        )
    )
);

/* ── 汇总统计卡片 ── */
$internalSummary = isset($summary['internal']) ? $summary['internal'] : array();
$outsourcedSummary = isset($summary['outsourced']) ? $summary['outsourced'] : array();
$totalSummary = isset($summary['total']) ? $summary['total'] : array();

div
(
    setClass('flex gap-4 mb-4'),
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->internal),
        div
        (
            setClass('grid grid-cols-4 gap-4 text-center p-2'),
            div(span(setClass('text-lg font-bold'), round($internalSummary['estimated'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-lg font-bold'), round($internalSummary['consumed'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-lg font-bold'), round($internalSummary['remain'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours)),
            div(span(setClass('text-lg font-bold'), ($internalSummary['taskCount'] ?? 0) . '任务'), br(), span(setClass('text-gray-500'), $lang->waibao->taskCount))
        )
    ),
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->outsourcedLabel),
        div
        (
            setClass('grid grid-cols-4 gap-4 text-center p-2'),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['estimated'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['consumed'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['remain'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours)),
            div(span(setClass('text-lg font-bold'), ($outsourcedSummary['taskCount'] ?? 0) . '任务'), br(), span(setClass('text-gray-500'), $lang->waibao->taskCount))
        )
    ),
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->totalHours),
        div
        (
            setClass('grid grid-cols-3 gap-4 text-center p-2'),
            div(span(setClass('text-xl font-bold text-blue-600'), round($totalSummary['estimated'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-xl font-bold text-green-600'), round($totalSummary['consumed'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-xl font-bold text-orange-600'), round($totalSummary['remain'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours))
        )
    )
);

/* ── 成员明细表格 ── */
$memberCols = array();
$memberCols['realname']        = array('title' => $lang->waibao->member, 'width' => '100px');
$memberCols['account']         = array('title' => $lang->waibao->account, 'width' => '100px');
$memberCols['deptName']        = array('title' => $lang->waibao->department, 'width' => '120px');
$memberCols['outsourcedLabel'] = array(
    'title'    => $lang->waibao->outsourced,
    'width'    => '80px',
    'type'     => 'tpl',
    'tplFunc'  => 'renderOutsourcedLabel',
);
$memberCols['estimated']       = array('title' => $lang->waibao->estimatedHours, 'width' => '100px');
$memberCols['consumed']        = array('title' => $lang->waibao->consumedHours, 'width' => '100px');
$memberCols['remain']          = array('title' => $lang->waibao->remainHours, 'width' => '100px');
$memberCols['loadRate']        = array(
    'title'    => $lang->waibao->loadRate,
    'width'    => '100px',
    'type'     => 'tpl',
    'tplFunc'  => 'renderLoadRate',
);
$memberCols['taskCount']       = array('title' => $lang->waibao->taskCount, 'width' => '80px');

$memberTableData = array();
foreach($memberData as $account => $member)
{
    $memberTableData[] = $member;
}

panel
(
    set::title($lang->waibao->memberdimension),
    dtable
    (
        set::cols($memberCols),
        set::data($memberTableData),
        set::hover(true),
        set::striped(true),
        set::sortBy('outsourced')
    )
);

/* ── ECharts 工时分布图 ── */
div
(
    setClass('panel mt-4'),
    set::title('成员工时分布'),
    div(setID('memberChart'), setStyle(array('width' => '100%', 'height' => '400px')))
);

/* PHP 端生成 URL 和图表数据，稍后直接拼入 <script> 块 */
$memberSubmitURL   = helper::createLink('waibao', 'memberdimension');
$memberChartData   = array_values($memberTableData);

/* ── JavaScript：PHP 数据直接拼入同一 <script> 块 ── */
$jsHead = 'var memberSubmitURL=' . json_encode($memberSubmitURL) . ';'
        . 'var memberChartData=' . json_encode($memberChartData)  . ';';

$jsBody = <<<'JS'
function submitFilter()
{
    var userEl = document.querySelector('[name="userID"]');
    var dateEl = document.querySelector('[name="date"]');
    var userID = userEl ? (userEl.value || '') : '';
    var date   = dateEl ? (dateEl.value || '') : '';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = memberSubmitURL;
    form.innerHTML = '<input type="hidden" name="userID" value="' + userID + '">'
                   + '<input type="hidden" name="date" value="' + date + '">'
                   + '<input type="hidden" name="outsourced" value="">'
                   + '<input type="hidden" name="status" value="todo">';
    document.body.appendChild(form);
    form.submit();
}

window.renderOutsourcedLabel = function(result, col, row)
{
    var isOutsourced = row.outsourced;
    var label = isOutsourced == 1 ? '外包' : '自有';
    var cls = isOutsourced == 1 ? 'label label-warning' : 'label label-success';
    return '<span class="' + cls + '">' + label + '</span>';
};

window.renderLoadRate = function(result, col, row)
{
    var rate = row.loadRate;
    var status = row.loadStatus;
    var colorMap = { relax: '#8b5cf6', spare: '#3b82f6', normal: '#22c55e', full: '#f59e0b', over: '#ef4444', extreme: '#dc2626' };
    var color = colorMap[status] || '#6b7280';
    return '<span style="color:' + color + ';font-weight:bold;">' + rate + '%</span>';
};

function loadEcharts(cb)
{
    if(window.echarts){ cb(); return; }
    var src = (window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js';
    if(typeof $ != 'undefined' && $.getLib)
    {
        $.getLib({src: [src], root: false}, cb);
    }
    else
    {
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        document.head.appendChild(s);
    }
}

loadEcharts(function()
{
    var chartDom = document.getElementById('memberChart');
    if(!chartDom || chartDom._chart) return;
    var myChart = echarts.init(chartDom);
    chartDom._chart = myChart;
    var internalNames = [], internalConsumed = [], outsourcedNames = [], outsourcedConsumed = [];

    for(var i = 0; i < memberChartData.length; i++)
    {
        if(memberChartData[i].outsourced == 1)
        {
            outsourcedNames.push(memberChartData[i].realname);
            outsourcedConsumed.push(memberChartData[i].consumed);
        }
        else
        {
            internalNames.push(memberChartData[i].realname);
            internalConsumed.push(memberChartData[i].consumed);
        }
    }

    var option = {
        tooltip: { trigger: 'axis' },
        legend: { data: ['自有人员', '外包人员'] },
        xAxis: { type: 'category', data: internalNames.concat(outsourcedNames) },
        yAxis: { type: 'value', name: '已消耗工时(h)' },
        series: [
            { name: '自有人员', type: 'bar', data: internalConsumed.concat(new Array(outsourcedConsumed.length).fill(null)), itemStyle: { color: '#22c55e' } },
            { name: '外包人员', type: 'bar', data: new Array(internalConsumed.length).fill(null).concat(outsourcedConsumed), itemStyle: { color: '#f59e0b' } }
        ]
    };
    myChart.setOption(option);
    window.addEventListener('resize', function(){ myChart.resize(); });
});
JS;

html('<script>' . $jsHead . $jsBody . '</script>');