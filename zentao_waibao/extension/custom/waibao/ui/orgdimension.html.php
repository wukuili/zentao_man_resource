<?php
/**
 * 外包标识插件 — 组织维度工时统计页面
 *
 * 按部门分别展示自有人员/外包人员的工时对比数据。
 */
namespace zin;

$data           = $this->view->data;
$depts          = $this->view->depts;
$currentDept    = $this->view->currentDept;
$begin          = $this->view->begin;
$end            = $this->view->end;
$date           = $this->view->date;
$loadRangeColors = $this->view->loadRangeColors;
$summary        = isset($data['summary']) ? $data['summary'] : array();
$deptData       = isset($data['depts']) ? $data['depts'] : array();

/* ── 顶部筛选栏 ── */
featureBar
(
    set::current($currentDept),
    div
    (
        setClass('flex items-center gap-2 ml-4'),
        label($lang->waibao->filterDept),
        picker
        (
            set::name('dept'),
            set::items($depts),
            set::value($currentDept),
            set::placeholder($lang->waibao->allDept)
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
    /* 自有人员汇总 */
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->internal),
        setClass('panel-success'),
        div
        (
            setClass('grid grid-cols-4 gap-4 text-center p-2'),
            div(span(setClass('text-lg font-bold'), round($internalSummary['estimated'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-lg font-bold'), round($internalSummary['consumed'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-lg font-bold'), round($internalSummary['remain'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours)),
            div(span(setClass('text-lg font-bold'), ($internalSummary['count'] ?? 0) . '人'), br(), span(setClass('text-gray-500'), $lang->waibao->internalCount))
        )
    ),
    /* 外包人员汇总 */
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->outsourcedLabel),
        setClass('panel-warning'),
        div
        (
            setClass('grid grid-cols-4 gap-4 text-center p-2'),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['estimated'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->estimatedHours)),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['consumed'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-lg font-bold'), round($outsourcedSummary['remain'] ?? 0, 1)), br(), span(setClass('text-gray-500'), $lang->waibao->remainHours)),
            div(span(setClass('text-lg font-bold'), ($outsourcedSummary['count'] ?? 0) . '人'), br(), span(setClass('text-gray-500'), $lang->waibao->outsourcedCount))
        )
    ),
    /* 总计汇总 */
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

/* ── 部门明细表格 ── */
$orgCols = array();
$orgCols['deptName']           = array('title' => $lang->waibao->department, 'width' => '150px');
$orgCols['internalEstimated']  = array('title' => $lang->waibao->internalEstimated, 'width' => '100px');
$orgCols['internalConsumed']   = array('title' => $lang->waibao->internalConsumed, 'width' => '100px');
$orgCols['internalRemain']     = array('title' => $lang->waibao->internalRemain, 'width' => '100px');
$orgCols['internalMembers']    = array('title' => $lang->waibao->internalCount, 'width' => '80px');
$orgCols['outsourcedEstimated'] = array('title' => $lang->waibao->outsourcedEstimated, 'width' => '100px');
$orgCols['outsourcedConsumed']  = array('title' => $lang->waibao->outsourcedConsumed, 'width' => '100px');
$orgCols['outsourcedRemain']    = array('title' => $lang->waibao->outsourcedRemain, 'width' => '100px');
$orgCols['outsourcedMembers']   = array('title' => $lang->waibao->outsourcedCount, 'width' => '80px');

$orgData = array();
foreach($deptData as $deptID => $dept)
{
    $orgData[] = array(
        'deptName'           => $dept['deptName'],
        'internalEstimated'  => round($dept['internal']['estimated'], 1),
        'internalConsumed'   => round($dept['internal']['consumed'], 1),
        'internalRemain'     => round($dept['internal']['remain'], 1),
        'internalMembers'    => $dept['internal']['memberCount'],
        'outsourcedEstimated' => round($dept['outsourced']['estimated'], 1),
        'outsourcedConsumed'  => round($dept['outsourced']['consumed'], 1),
        'outsourcedRemain'    => round($dept['outsourced']['remain'], 1),
        'outsourcedMembers'   => $dept['outsourced']['memberCount'],
    );
}

panel
(
    set::title($lang->waibao->orgdimension),
    dtable
    (
        set::cols($orgCols),
        set::data($orgData),
        set::hover(true),
        set::striped(true)
    )
);

/* ── ECharts 对比图 ── */
div
(
    setClass('panel mt-4'),
    set::title('外包/自有工时对比'),
    div(setID('orgChart'), setStyle(array('width' => '100%', 'height' => '400px')))
);

/* PHP 端生成 URL 和图表数据，稍后直接拼入 <script> 块 */
$orgSubmitURL     = helper::createLink('waibao', 'orgdimension');
$orgChartDepts    = array_column($orgData, 'deptName');
$orgChartInternal = array_column($orgData, 'internalConsumed');
$orgChartOutsrc   = array_column($orgData, 'outsourcedConsumed');

/* ── JavaScript：PHP 数据直接拼入同一 <script> 块 ── */
$jsHead = 'var orgSubmitURL='     . json_encode($orgSubmitURL)     . ';'
        . 'var orgChartDepts='    . json_encode($orgChartDepts)    . ';'
        . 'var orgChartInternal=' . json_encode($orgChartInternal) . ';'
        . 'var orgChartOutsrc='   . json_encode($orgChartOutsrc)   . ';';

$jsBody = <<<'JS'
function submitFilter()
{
    var deptEl = document.querySelector('[name="dept"]');
    var dateEl = document.querySelector('[name="date"]');
    var dept   = deptEl ? (deptEl.value || '0') : '0';
    var date   = dateEl ? (dateEl.value || '')  : '';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = orgSubmitURL;
    form.innerHTML = '<input type="hidden" name="dept" value="' + dept + '">'
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
    var chartDom = document.getElementById('orgChart');
    if(!chartDom || chartDom._chart) return;
    var myChart = echarts.init(chartDom);
    chartDom._chart = myChart;
    var option = {
        tooltip: { trigger: 'axis' },
        legend: { data: ['自有人员', '外包人员'] },
        xAxis: { type: 'category', data: orgChartDepts },
        yAxis: { type: 'value', name: '工时(h)' },
        series: [
            { name: '自有人员', type: 'bar', data: orgChartInternal,  itemStyle: { color: '#22c55e' } },
            { name: '外包人员', type: 'bar', data: orgChartOutsrc,    itemStyle: { color: '#f59e0b' } }
        ]
    };
    myChart.setOption(option);
    window.addEventListener('resize', function(){ myChart.resize(); });
});
JS;

html('<script>' . $jsHead . $jsBody . '</script>');