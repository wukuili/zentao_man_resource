<?php
/**
 * 外包标识插件 — 多维外包工时汇总页
 *
 * 顶部筛选：时间范围 / 部门 / 项目 / 迭代；可切换分组维度（人员/项目/迭代/部门/月份）。
 * 工时口径以「已消耗」为主，数据源为 zt_effort 工时日志。
 */
namespace zin;

$data            = $this->view->data;
$rows            = isset($data['rows']) ? $data['rows'] : array();
$totalConsumed   = isset($data['total']) ? $data['total'] : 0;
$groupBy         = $this->view->groupBy;
$depts           = $this->view->depts;
$projects        = $this->view->projects;
$executions      = $this->view->executions;
$begin           = $this->view->begin;
$end             = $this->view->end;
$currentDept     = $this->view->currentDept;
$currentProject  = $this->view->currentProject;
$currentExecution = $this->view->currentExecution;

/* 下拉数据 */
$deptList = array(0 => $lang->waibao->allDept) + $depts;
$projList = array(0 => $lang->waibao->allProject);
foreach($projects as $pid => $pname) $projList[$pid] = $pname;
$execList = array(0 => $lang->waibao->allExecution);
foreach($executions as $eid => $ename) $execList[$eid] = $ename;
$groupByList = $lang->waibao->groupByList;

/* ── 顶部筛选栏 ── */
featureBar
(
    div
    (
        setClass('flex items-center flex-wrap gap-2'),
        label($lang->waibao->filterBegin),
        datePicker(set::name('begin'), set::value(str_replace('-', '_', $begin))),
        label($lang->waibao->filterEnd),
        datePicker(set::name('end'), set::value(str_replace('-', '_', $end))),
        label($lang->waibao->filterDept),
        picker(set::name('dept'), set::items($deptList), set::value($currentDept), set::placeholder($lang->waibao->allDept)),
        label($lang->waibao->filterProject),
        picker(set::name('project'), set::items($projList), set::value($currentProject), set::placeholder($lang->waibao->allProject)),
        label($lang->waibao->filterExecution),
        picker(set::name('execution'), set::items($execList), set::value($currentExecution), set::placeholder($lang->waibao->allExecution)),
        label($lang->waibao->groupBy),
        picker(set::name('groupBy'), set::items($groupByList), set::value($groupBy)),
        btn(setClass('btn btn-primary'), set::onClick('submitFilter()'), $lang->waibao->search)
    )
);

/* ── 合计卡片 ── */
div
(
    setClass('flex gap-4 mb-4'),
    panel
    (
        setClass('flex-1'),
        set::title($lang->waibao->totalConsumed),
        div
        (
            setClass('grid grid-cols-2 gap-4 text-center p-2'),
            div(span(setClass('text-xl font-bold text-orange-600'), $totalConsumed), br(), span(setClass('text-gray-500'), $lang->waibao->consumedHours)),
            div(span(setClass('text-xl font-bold text-blue-600'), count($rows)), br(), span(setClass('text-gray-500'), zget($groupByList, $groupBy, '')))
        )
    )
);

/* ── 明细汇总表 ── */
$firstColTitle = zget($lang->waibao->dimensionTitle, $groupBy, $lang->waibao->realname);

$cols = array();
$cols['name']     = array('title' => $firstColTitle,            'width' => '260px');
$cols['consumed'] = array('title' => $lang->waibao->consumedHours, 'width' => '140px');
$cols['percent']  = array('title' => $lang->waibao->percent,       'width' => '120px');
$cols['records']  = array('title' => $lang->waibao->records,       'width' => '120px');

$tableData = array();
foreach($rows as $row)
{
    $tableData[] = array(
        'name'     => $row['name'],
        'consumed' => $row['consumed'],
        'percent'  => $row['percent'] . '%',
        'records'  => $row['records'],
    );
}

panel
(
    set::title($lang->waibao->summary),
    empty($tableData) ? div(setClass('p-8 text-center text-gray-500'), $lang->waibao->noData) : dtable
    (
        set::cols($cols),
        set::data($tableData),
        set::hover(true),
        set::striped(true)
    )
);

/* ── ECharts 柱状图 ── */
div
(
    setClass('panel mt-4'),
    set::title($lang->waibao->chartTitle),
    div(setID('summaryChart'), setStyle(array('width' => '100%', 'height' => '400px')))
);

/* PHP 端生成 URL 和图表数据 */
$submitURL   = helper::createLink('waibao', 'summary');
$chartNames  = array_column($rows, 'name');
$chartValues = array_column($rows, 'consumed');

$jsHead = 'var wbSubmitURL='   . json_encode($submitURL)   . ';'
        . 'var wbChartNames='  . json_encode($chartNames)  . ';'
        . 'var wbChartValues=' . json_encode($chartValues) . ';';

$jsBody = <<<'JS'
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
function pickVal(name)
{
    var el = document.querySelector('[name="' + name + '"]');
    return el ? (el.value || '') : '';
}
function submitFilter()
{
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = wbSubmitURL;
    var fields = {
        begin:     pickVal('begin'),
        end:       pickVal('end'),
        dept:      pickVal('dept')      || '0',
        project:   pickVal('project')   || '0',
        execution: pickVal('execution') || '0',
        groupBy:   pickVal('groupBy')   || 'member'
    };
    var html = '';
    for(var k in fields) html += '<input type="hidden" name="' + k + '" value="' + fields[k] + '">';
    form.innerHTML = html;
    document.body.appendChild(form);
    form.submit();
}

loadEcharts(function()
{
    var chartDom = document.getElementById('summaryChart');
    if(!chartDom || chartDom._chart) return;
    var myChart = echarts.init(chartDom);
    chartDom._chart = myChart;
    var option = {
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: wbChartNames, axisLabel: { rotate: wbChartNames.length > 8 ? 30 : 0 } },
        yAxis: { type: 'value', name: '已消耗(h)' },
        series: [ { name: '已消耗工时', type: 'bar', data: wbChartValues, itemStyle: { color: '#f59e0b' } } ]
    };
    myChart.setOption(option);
    window.addEventListener('resize', function(){ myChart.resize(); });
});
JS;

html('<script>' . $jsHead . $jsBody . '</script>');
