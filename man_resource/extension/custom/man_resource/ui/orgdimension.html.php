<?php
declare(strict_types=1);
/**
 * The orgdimension view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

jsVar('options', $calendarData);
jsVar('today', $today);
jsVar('mode', $status);
jsVar('begin', strtotime($begin));
jsVar('end', strtotime($end));
jsVar('method', $app->rawMethod);
jsVar('userAccount', $accounts);
jsVar('title', $title);
jsVar('userNotEmpty', sprintf($lang->error->notempty, $lang->man_resource->user));
jsVar('paramList', $paramList);
jsVar('isSimulate', $isSimulate);
jsVar('workHours', $defaultWorkhours);
jsVar('taskHourPredict', $config->man_resource->taskHourPredict);
jsVar('notTaskHourPredict', $config->man_resource->notTaskHourPredict);
jsVar('actionURL', $actionURL);
jsVar('module', 'man_resource');

css
(<<<CSS
.load-rate-bar { width: 100%; background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px; }
.load-rate-fill { height: 100%; border-radius: 4px; }
#orgList td { vertical-align: middle !important; }
.search-form-row { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 12px; }
.search-form-row .form-group { display: flex; align-items: center; gap: 8px; }
.search-form-row .form-group label { white-space: nowrap; font-weight: 600; }
.search-form-row .picker-box { width: 220px; }
.search-form-row .date-box { width: 140px; }
.search-form-row .status-box { width: 100px; }
CSS
);

/* Nav tabs for dimension switching. */
$navItems = array();
if(common::hasPriv('man_resource', 'orgdimension'))    $navItems[] = array('text' => $lang->man_resource->company,          'url' => createLink('man_resource', 'orgdimension'),    'active' => ($app->rawMethod == 'orgdimension'));
if(common::hasPriv('man_resource', 'projectdimension')) $navItems[] = array('text' => $lang->man_resource->projectCalendar, 'url' => createLink('man_resource', 'projectdimension'), 'active' => ($app->rawMethod == 'projectdimension'));
if(common::hasPriv('man_resource', 'memberdimension')) $navItems[] = array('text' => $lang->man_resource->person,           'url' => createLink('man_resource', 'memberdimension'),  'active' => ($app->rawMethod == 'memberdimension'));
featureBar
(
    set::current($status),
    set::linkParams("status={key}&begin={$begin}&end={$end}"),
    to::item($navItems)
);

/* Toolbar. */
$toolbarItems = array();

if($app->rawMethod == 'orgdimension' && common::hasPriv('man_resource', 'exportCompany'))
{
    $toolbarItems[] = btn
    (
        set::type('ghost'),
        set::icon('export'),
        set::text($lang->export),
        set::url(helper::createLink('man_resource', 'exportCompany', "begin=" . strtotime($begin) . "&end=" . strtotime($end) . "&mode={$status}")),
        setID('exportCompanyBtn')
    );
}

/* Settings dropdown. */
$settingsItems = array();
if(common::hasPriv('man_resource', 'setHours'))       $settingsItems[] = array('text' => $lang->man_resource->setHours, 'url' => helper::createLink('man_resource', 'setHours', '', '', true), 'data-toggle' => 'modal', 'data-width' => '500px');
if(common::hasPriv('holiday', 'browse'))               $settingsItems[] = array('text' => $lang->man_resource->setHoliday, 'url' => helper::createLink('holiday', 'browse'));
if(common::hasPriv('man_resource', 'setLoad'))          $settingsItems[] = array('text' => $lang->man_resource->setLoad, 'url' => helper::createLink('man_resource', 'setLoad', '', '', true), 'data-toggle' => 'modal', 'data-width' => '600px');
if(common::hasPriv('man_resource', 'setPredictHours'))  $settingsItems[] = array('text' => $lang->man_resource->setPredictHours, 'url' => helper::createLink('man_resource', 'setPredictHours', '', '', true), 'data-toggle' => 'modal', 'data-width' => common::checkNotCN() ? '900px' : '820px');

if(!empty($settingsItems))
{
    $toolbarItems[] = dropdown
    (
        btn(set::type('ghost'), set::icon('cog-outline'), set::square(true)),
        set::items($settingsItems),
        set::placement('bottom-end')
    );
}

if(common::hasPriv('man_resource', 'editSimulate') && common::hasPriv('man_resource', 'simulate'))
{
    $toolbarItems[] = btn
    (
        set::type('ghost'),
        set::icon('rocket'),
        set::text($lang->man_resource->simulatedLoad),
        set::url(helper::createLink('man_resource', 'simulate'))
    );
}

toolbar($toolbarItems);

/* Search form. */
panel
(
    setClass('mb-4'),
    form
    (
        set::method('post'),
        set::action(createLink('man_resource', 'orgdimension')),
        div
        (
            setClass('search-form-row'),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->departmentCol),
                div(setClass('picker-box'), picker(set::name('departmentID'), set::items($departments), set::value($departmentID), set::multiple(true), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->roleCol),
                div(setClass('picker-box'), picker(set::name('roleID'), set::items($rolePairs), set::value($roleID), set::multiple(true), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->user),
                div(setClass('picker-box'), picker(set::name('users'), set::items($userList), set::value($users), set::multiple(true), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->projectCol),
                div(setClass('picker-box'), picker(set::name('project'), set::items($projectList), set::value($project), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->executionCol),
                div(setClass('picker-box'), picker(set::name('execution'), set::items($executionList), set::value($execution), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->date),
                div(setClass('date-box'), datePicker(set::name('begin'), set::value($begin))),
                span($lang->man_resource->to),
                div(setClass('date-box'), datePicker(set::name('end'), set::value($end)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->status),
                div(setClass('status-box'), select(set::name('status'), set::items(array('todo' => $lang->man_resource->wait, 'done' => $lang->man_resource->done)), set::value($status)))
            ),
            div
            (
                setClass('form-group'),
                checkbox(set::name('showHoliday'), set::value('1'), set::checked(!empty($showHoliday)), set::text($lang->man_resource->showHoliday))
            ),
            input(set::type('submit'), set::className('btn btn-primary'), set::value($lang->man_resource->search))
        )
    )
);

/* Prepare dtable data. */
$tableData = array();
if(!empty($calendarData))
{
    foreach($calendarData as $userID => $data)
    {
        $loadStatus = $data['load_status'];
        $colorObj   = zget($config->man_resource->loadRangeColors, $loadStatus, new stdClass());
        $textColor  = isset($colorObj->text) ? $colorObj->text : '#000';
        $loadName   = zget($lang->man_resource->loadType, $loadStatus, $loadStatus);
        $loadRate   = min(100, $data['load_rate']);

        $row = new stdClass();
        $row->id               = $userID;
        $row->realname         = html::a(createLink('man_resource', 'memberdimension', "userID=$userID"), $data['realname']);
        $row->estimated_hours  = $data['estimated_hours'];
        $row->consumed_hours   = $data['consumed_hours'];
        $row->remain_hours     = $data['remain_hours'];
        $row->parallel_tasks   = $data['parallel_tasks'];
        $row->load_rate        = "<span style='color:{$textColor}'>{$data['load_rate']}%</span><div class='load-rate-bar'><div class='load-rate-fill' style='width:{$loadRate}%;background:{$textColor}'></div></div>";
        $row->status           = $loadName;
        $row->bug_count        = isset($data['bug_count'])        ? $data['bug_count']        : 0;
        $row->bug_fix_days     = isset($data['bug_fix_days'])     ? $data['bug_fix_days']     : 0;
        $row->bug_reopen_count = isset($data['bug_reopen_count']) ? $data['bug_reopen_count'] : 0;
        $tableData[] = $row;
    }
}

dtable
(
    setID('orgList'),
    set::cols(array
    (
        array('name' => 'realname',         'title' => $lang->man_resource->user,                  'width' => '150px', 'sortType' => true, 'html' => true),
        array('name' => 'estimated_hours',  'title' => $lang->man_resource->estimatedHoursCol,      'width' => '100px', 'sortType' => true),
        array('name' => 'consumed_hours',   'title' => $lang->man_resource->consumeHoursCol,        'width' => '100px', 'sortType' => true),
        array('name' => 'remain_hours',     'title' => $lang->man_resource->totalEstimatedHoursCol, 'width' => '100px', 'sortType' => true),
        array('name' => 'parallel_tasks',   'title' => $lang->man_resource->taskCountCol,           'width' => '100px', 'sortType' => true),
        array('name' => 'load_rate',        'title' => $lang->man_resource->loadRateCol,            'width' => '100px', 'html' => true),
        array('name' => 'status',           'title' => $lang->man_resource->status,                 'width' => '100px'),
        array('name' => 'bug_count',        'title' => $lang->man_resource->bugCountCol,            'width' => '80px',  'sortType' => true),
        array('name' => 'bug_fix_days',     'title' => $lang->man_resource->bugFixDaysCol,          'width' => '100px', 'sortType' => true),
        array('name' => 'bug_reopen_count', 'title' => $lang->man_resource->bugReopenCountCol,      'width' => '100px', 'sortType' => true)
    )),
    set::data($tableData),
    set::footPager(usePager()),
    set::emptyTip($lang->man_resource->browseTip)
);

/* Load trend chart. */
panel
(
    set::title($lang->man_resource->loadTrendTitle),
    setClass('mt-4'),
    div(setID('loadTrendChart'), setStyle(array('width' => '100%', 'height' => '320px')))
);

/* Work-hour heatmap. */
panel
(
    set::title($lang->man_resource->heatmapTitle),
    setClass('mt-4'),
    div(setID('loadHeatmap'), setStyle(array('width' => '100%', 'height' => '360px')))
);

/* Chart data and initialization - using html() because js() does not execute in ZIN. */
$chartDataJson   = json_encode(isset($dailySeries) ? $dailySeries : array('dates' => array(), 'series' => array(), 'overall' => array()));
$trendOverall    = json_encode($lang->man_resource->loadTrendOverall);
$trendY          = json_encode($lang->man_resource->loadTrendY);
$heatmapUnit     = json_encode($lang->man_resource->heatmapHourUnit);

$chartJS = "<script>\n" .
    "window.loadSeries = " . $chartDataJson . ";\n" .
    "window.loadTrendOverallText = " . $trendOverall . ";\n" .
    "window.loadTrendYText = " . $trendY . ";\n" .
    "window.heatmapHourUnit = " . $heatmapUnit . ";\n" .
    "function loadEcharts(cb){\n" .
    "  if(window.echarts){ cb(); return; }\n" .
    "  var src = (window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js';\n" .
    "  if(typeof $ != 'undefined' && $.getLib){\n" .
    "    $.getLib({src: [src], root: false}, cb);\n" .
    "  } else {\n" .
    "    var s = document.createElement('script');\n" .
    "    s.src = src;\n" .
    "    s.onload = cb;\n" .
    "    document.head.appendChild(s);\n" .
    "  }\n" .
    "}\n" .
    "function initLoadTrendChart(){\n" .
    "  if(!window.loadSeries || !loadSeries.dates || !loadSeries.dates.length){\n" .
    "    var el = document.getElementById('loadTrendChart');\n" .
    "    if(el) el.innerHTML = '<div style=\"padding:40px;text-align:center;color:#94a3b8\">' + (window.loadTrendOverallText || '') + '</div>';\n" .
    "    return;\n" .
    "  }\n" .
    "  var el = document.getElementById('loadTrendChart');\n" .
    "  if(!el || el._chart) return;\n" .
    "  loadEcharts(function(){\n" .
    "    if(!window.echarts || el._chart) return;\n" .
    "    var dates = loadSeries.dates;\n" .
    "    var series = [];\n" .
    "    Object.keys(loadSeries.series).forEach(function(acc){\n" .
    "      var row = loadSeries.series[acc];\n" .
    "      series.push({name: row.realname, type: 'bar', stack: 'load', data: row.rates, emphasis: {focus: 'series'}});\n" .
    "    });\n" .
    "    if(loadSeries.overall && loadSeries.overall.length){\n" .
    "      series.push({name: window.loadTrendOverallText || '\\u5e73\\u5747\\u8d1f\\u8f7d\\u7387', type: 'line', smooth: true, symbol: 'circle', lineStyle: {width: 2, color: '#dc2626'}, itemStyle: {color: '#dc2626'}, data: loadSeries.overall, z: 5});\n" .
    "    }\n" .
    "    var chart = echarts.init(el);\n" .
    "    el._chart = chart;\n" .
    "    chart.setOption({tooltip: {trigger: 'axis', axisPointer: {type: 'shadow'}}, legend: {type: 'scroll', top: 0}, grid: {left: 50, right: 30, top: 40, bottom: 50}, xAxis: {type: 'category', data: dates, axisLabel: {rotate: 35}}, yAxis: {type: 'value', name: window.loadTrendYText || '\\u8d1f\\u8f7d\\u7387(%)'}, series: series});\n" .
    "    window.addEventListener('resize', function(){ chart.resize(); });\n" .
    "  });\n" .
    "}\n" .
    "function initLoadHeatmap(){\n" .
    "  if(!window.loadSeries || !loadSeries.dates || !loadSeries.dates.length){\n" .
    "    var el = document.getElementById('loadHeatmap');\n" .
    "    if(el) el.innerHTML = '<div style=\"padding:40px;text-align:center;color:#94a3b8\">' + (window.heatmapHourUnit || 'h') + '</div>';\n" .
    "    return;\n" .
    "  }\n" .
    "  var el = document.getElementById('loadHeatmap');\n" .
    "  if(!el || el._chart) return;\n" .
    "  loadEcharts(function(){\n" .
    "    if(!window.echarts || el._chart) return;\n" .
    "    var dates = loadSeries.dates;\n" .
    "    var members = [], data = [];\n" .
    "    var accs = Object.keys(loadSeries.series);\n" .
    "    accs.forEach(function(acc, yIdx){\n" .
    "      var row = loadSeries.series[acc];\n" .
    "      members.push(row.realname);\n" .
    "      row.hours.forEach(function(h, xIdx){ data.push([xIdx, yIdx, +h]); });\n" .
    "    });\n" .
    "    var maxHours = (window.workHours || 8) * 1.5;\n" .
    "    var chart = echarts.init(el);\n" .
    "    el._chart = chart;\n" .
    "    chart.setOption({tooltip: {position: 'top', formatter: function(p){ return dates[p.value[0]] + '<br/>' + members[p.value[1]] + ': ' + p.value[2] + ' ' + (window.heatmapHourUnit || 'h'); }}, grid: {left: 100, right: 30, top: 30, bottom: 60}, xAxis: {type: 'category', data: dates, axisLabel: {rotate: 35}, splitArea: {show: true}}, yAxis: {type: 'category', data: members, splitArea: {show: true}}, visualMap: {min: 0, max: maxHours, calculable: true, orient: 'horizontal', left: 'center', bottom: 10, inRange: {color: ['#dcfce7','#fef3c7','#fee2e2','#dc2626']}}, series: [{name: 'hours', type: 'heatmap', data: data, label: {show: false}, emphasis: {itemStyle: {shadowBlur: 6, shadowColor: 'rgba(0,0,0,0.2)'}}}] });\n" .
    "    window.addEventListener('resize', function(){ chart.resize(); });\n" .
    "  });\n" .
    "}\n" .
    "function waitForElement(id, cb, maxWait){\n" .
    "  var el = document.getElementById(id);\n" .
    "  if(el){ cb(el); return; }\n" .
    "  var tries = 0;\n" .
    "  var iv = setInterval(function(){\n" .
    "    tries++;\n" .
    "    var el2 = document.getElementById(id);\n" .
    "    if(el2 || tries > (maxWait || 50)){ clearInterval(iv); if(el2) cb(el2); }\n" .
    "  }, 100);\n" .
    "}\n" .
    "waitForElement('loadTrendChart', function(){ initLoadTrendChart(); });\n" .
    "waitForElement('loadHeatmap', function(){ initLoadHeatmap(); });\n" .
    "</script>";

render();

/* TEMP DEBUG: show team task debug info */
if(!empty($debugInfo))
{
    panel
    (
        set::title('DEBUG: Team Task Data'),
        setClass('mt-4'),
        setStyle(array('font-family' => 'monospace')),
        h::pre(html($debugInfo), setStyle(array('white-space' => 'pre-wrap', 'background' => '#f5f5f5', 'padding' => '12px', 'border-radius' => '4px', 'max-height' => '400px', 'overflow' => 'auto')))
    );
}

html($chartJS);