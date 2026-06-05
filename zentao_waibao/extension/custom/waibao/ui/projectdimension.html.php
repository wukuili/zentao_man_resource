<?php
/**
 * 外包标识插件 — 项目维度工时统计页面
 *
 * 按项目分别展示自有人员/外包人员的工时对比数据。
 */
namespace zin;

$data            = $this->view->data;
$projects        = $this->view->projects;
$currentProject  = $this->view->currentProject;
$begin           = $this->view->begin;
$end             = $this->view->end;
$date            = $this->view->date;
$loadRangeColors = $this->view->loadRangeColors;
$summary         = isset($data['summary']) ? $data['summary'] : array();
$projectData     = isset($data['projects']) ? $data['projects'] : array();

/* 构建项目下拉列表 */
$projectList = array(0 => $lang->waibao->allProject);
foreach($projects as $proj)
{
    $projectList[$proj->id] = $proj->name;
}

/* ── 顶部筛选栏 ── */
featureBar
(
    set::current($currentProject),
    div
    (
        setClass('flex items-center gap-2 ml-4'),
        label($lang->waibao->filterProject),
        picker
        (
            set::name('project'),
            set::items($projectList),
            set::value($currentProject),
            set::placeholder($lang->waibao->allProject)
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
            div(span(setClass('text-lg font-bold'), ($internalSummary['memberCount'] ?? 0) . '人'), br(), span(setClass('text-gray-500'), $lang->waibao->internalCount))
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
            div(span(setClass('text-lg font-bold'), ($outsourcedSummary['memberCount'] ?? 0) . '人'), br(), span(setClass('text-gray-500'), $lang->waibao->outsourcedCount))
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

/* ── 项目明细表格 ── */
$projCols = array();
$projCols['projectName']        = array('title' => $lang->waibao->project, 'width' => '180px');
$projCols['internalEstimated']  = array('title' => $lang->waibao->internalEstimated, 'width' => '100px');
$projCols['internalConsumed']   = array('title' => $lang->waibao->internalConsumed, 'width' => '100px');
$projCols['internalRemain']     = array('title' => $lang->waibao->internalRemain, 'width' => '100px');
$projCols['internalMembers']    = array('title' => $lang->waibao->internalCount, 'width' => '80px');
$projCols['outsourcedEstimated'] = array('title' => $lang->waibao->outsourcedEstimated, 'width' => '100px');
$projCols['outsourcedConsumed']  = array('title' => $lang->waibao->outsourcedConsumed, 'width' => '100px');
$projCols['outsourcedRemain']    = array('title' => $lang->waibao->outsourcedRemain, 'width' => '100px');
$projCols['outsourcedMembers']   = array('title' => $lang->waibao->outsourcedCount, 'width' => '80px');

$projData = array();
foreach($projectData as $pid => $proj)
{
    $projData[] = array(
        'projectName'        => $proj['projectName'],
        'internalEstimated'  => round($proj['internal']['estimated'], 1),
        'internalConsumed'   => round($proj['internal']['consumed'], 1),
        'internalRemain'     => round($proj['internal']['remain'], 1),
        'internalMembers'    => $proj['internal']['memberCount'],
        'outsourcedEstimated' => round($proj['outsourced']['estimated'], 1),
        'outsourcedConsumed'  => round($proj['outsourced']['consumed'], 1),
        'outsourcedRemain'    => round($proj['outsourced']['remain'], 1),
        'outsourcedMembers'   => $proj['outsourced']['memberCount'],
    );
}

panel
(
    set::title($lang->waibao->projectdimension),
    dtable
    (
        set::cols($projCols),
        set::data($projData),
        set::hover(true),
        set::striped(true)
    )
);

/* ── ECharts 对比图 ── */
div
(
    setClass('panel mt-4'),
    set::title('项目外包/自有工时对比'),
    div(setID('projectChart'), setStyle(array('width' => '100%', 'height' => '400px')))
);

/* PHP 端生成 URL 和图表数据，稍后直接拼入 <script> 块 */
$projSubmitURL    = helper::createLink('waibao', 'projectdimension');
$projChartNames   = array_column($projData, 'projectName');
$projChartInt     = array_column($projData, 'internalConsumed');
$projChartOutsrc  = array_column($projData, 'outsourcedConsumed');

/* ── JavaScript：PHP 数据直接拼入同一 <script> 块 ── */
$jsHead = 'var projSubmitURL='  . json_encode($projSubmitURL)  . ';'
        . 'var projChartNames=' . json_encode($projChartNames)  . ';'
        . 'var projChartInt='   . json_encode($projChartInt)    . ';'
        . 'var projChartOutsrc='. json_encode($projChartOutsrc) . ';';

$jsBody = <<<'JS'
function submitFilter()
{
    var projEl = document.querySelector('[name="project"]');
    var dateEl = document.querySelector('[name="date"]');
    var project = projEl ? (projEl.value || '0') : '0';
    var date    = dateEl ? (dateEl.value || '')  : '';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = projSubmitURL;
    form.innerHTML = '<input type="hidden" name="project" value="' + project + '">'
                   + '<input type="hidden" name="date" value="' + date + '">'
                   + '<input type="hidden" name="outsourced" value="">'
                   + '<input type="hidden" name="status" value="todo">';
    document.body.appendChild(form);
    form.submit();
}

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
    var chartDom = document.getElementById('projectChart');
    if(!chartDom || chartDom._chart) return;
    var myChart = echarts.init(chartDom);
    chartDom._chart = myChart;
    var option = {
        tooltip: { trigger: 'axis' },
        legend: { data: ['自有人员', '外包人员'] },
        xAxis: { type: 'category', data: projChartNames },
        yAxis: { type: 'value', name: '工时(h)' },
        series: [
            { name: '自有人员', type: 'bar', data: projChartInt,    itemStyle: { color: '#22c55e' } },
            { name: '外包人员', type: 'bar', data: projChartOutsrc, itemStyle: { color: '#f59e0b' } }
        ]
    };
    myChart.setOption(option);
    window.addEventListener('resize', function(){ myChart.resize(); });
});
JS;

html('<script>' . $jsHead . $jsBody . '</script>');