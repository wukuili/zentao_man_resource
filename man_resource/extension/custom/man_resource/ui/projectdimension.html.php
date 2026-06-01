<?php
declare(strict_types=1);
/**
 * The projectdimension view file of man_resource module of ZenTaoPMS.
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
jsVar('workHours', $defaultWorkhours);
jsVar('module', 'man_resource');

css
(<<<CSS
.load-rate-bar { width: 100%; background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px; }
.load-rate-fill { height: 100%; border-radius: 4px; }
#projectList td { vertical-align: middle !important; }
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
if(common::hasPriv('man_resource', 'orgdimension'))    $navItems[] = array('text' => $lang->man_resource->company,          'url' => createLink('man_resource', 'orgdimension'),    'active' => false);
if(common::hasPriv('man_resource', 'projectdimension')) $navItems[] = array('text' => $lang->man_resource->projectCalendar, 'url' => createLink('man_resource', 'projectdimension'), 'active' => true);
if(common::hasPriv('man_resource', 'memberdimension'))  $navItems[] = array('text' => $lang->man_resource->person,           'url' => createLink('man_resource', 'memberdimension'),  'active' => false);

featureBar
(
    set::current($status),
    set::linkParams("projectID={$projectID}&status={key}&begin={$begin}&end={$end}"),
    to::item($navItems)
);

/* Toolbar. */
$toolbarItems = array();

if(common::hasPriv('man_resource', 'exportProject'))
{
    $toolbarItems[] = btn
    (
        set::type('ghost'),
        set::icon('export'),
        set::text($lang->export),
        set::url(helper::createLink('man_resource', 'exportProject', "projectID={$projectID}&begin=" . strtotime($begin) . "&end=" . strtotime($end) . "&mode={$status}", '', true)),
        set('data-toggle', 'modal'),
        set('data-type', 'iframe'),
        set('data-width', '600px')
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

if(common::hasPriv('man_resource', 'editProjectSim') && common::hasPriv('man_resource', 'simulate'))
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
        set::action(createLink('man_resource', 'projectdimension')),
        div
        (
            setClass('search-form-row'),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->projectCol),
                div(setClass('picker-box'), picker(set::name('projectID'), set::items($projects), set::value($projectID)))
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
        $tableData[] = $row;
    }
}

dtable
(
    setID('projectList'),
    set::cols(array
    (
        array('name' => 'realname', 'title' => $lang->man_resource->user, 'width' => '150px', 'sortType' => true, 'html' => true),
        array('name' => 'estimated_hours', 'title' => $lang->man_resource->estimatedHoursCol, 'width' => '100px', 'sortType' => true),
        array('name' => 'consumed_hours', 'title' => $lang->man_resource->consumeHoursCol, 'width' => '100px', 'sortType' => true),
        array('name' => 'remain_hours', 'title' => $lang->man_resource->totalEstimatedHoursCol, 'width' => '100px', 'sortType' => true),
        array('name' => 'parallel_tasks', 'title' => $lang->man_resource->taskCountCol, 'width' => '100px', 'sortType' => true),
        array('name' => 'load_rate', 'title' => $lang->man_resource->loadRateCol, 'width' => '100px', 'html' => true),
        array('name' => 'status', 'title' => $lang->man_resource->status, 'width' => '100px')
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

/* Resource gantt panel. */
panel
(
    set::title($lang->man_resource->ganttTitle),
    setClass('mt-4'),
    div(setID('resourceGantt'), setStyle(array('width' => '100%', 'height' => '420px')))
);

/* Chart data and initialization - using html() because js() does not execute in ZIN. */
$chartDataJson   = json_encode(isset($dailySeries) ? $dailySeries : array('dates' => array(), 'series' => array(), 'overall' => array()));
$ganttDataJson   = json_encode(isset($ganttData) ? $ganttData : array('lanes' => new stdclass()));
$trendOverall    = json_encode($lang->man_resource->loadTrendOverall);
$trendY          = json_encode($lang->man_resource->loadTrendY);
$ganttEmptyText  = json_encode($lang->man_resource->ganttEmpty);
$ganttBeginTs    = strtotime($begin);
$ganttEndTs      = strtotime($end);

$chartJS = "<script>\n" .
    "window.loadSeries = " . $chartDataJson . ";\n" .
    "window.loadTrendOverallText = " . $trendOverall . ";\n" .
    "window.loadTrendYText = " . $trendY . ";\n" .
    "window.ganttData = " . $ganttDataJson . ";\n" .
    "window.ganttEmptyText = " . $ganttEmptyText . ";\n" .
    "window.ganttBeginTs = " . $ganttBeginTs . ";\n" .
    "window.ganttEndTs = " . $ganttEndTs . ";\n" .
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
    "function initProjectLoadTrend(){\n" .
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
    "function initResourceGantt(){\n" .
    "  if(!window.ganttData || !ganttData.lanes){\n" .
    "    var el = document.getElementById('resourceGantt');\n" .
    "    if(el) el.innerHTML = '<div style=\"padding:40px;text-align:center;color:#94a3b8\">' + (window.ganttEmptyText || '') + '</div>';\n" .
    "    return;\n" .
    "  }\n" .
    "  var el = document.getElementById('resourceGantt');\n" .
    "  if(!el || el._chart) return;\n" .
    "  loadEcharts(function(){\n" .
    "    if(!window.echarts || el._chart) return;\n" .
    "    var lanes = ganttData.lanes || {};\n" .
    "    var members = [];\n" .
    "    var data = [];\n" .
    "    var statusColors = {doing:'#3b82f6', wait:'#94a3b8', pause:'#f59e0b', done:'#10b981', closed:'#10b981'};\n" .
    "    Object.keys(lanes).forEach(function(acc, idx){\n" .
    "      var lane = lanes[acc];\n" .
    "      members.push(lane.realname);\n" .
    "      (lane.tasks || []).forEach(function(t){\n" .
    "        var s = (new Date(t.estStarted)).getTime();\n" .
    "        var e = (new Date(t.deadline)).getTime();\n" .
    "        if(!s || !e || e < s) return;\n" .
    "        data.push({name: t.name, value: [idx, s, e, t.status, t.estimate, t.id, t.projectName || '', t.executionName || ''], itemStyle: {color: statusColors[t.status] || '#64748b'}});\n" .
    "      });\n" .
    "    });\n" .
    "    if(!members.length){ el.innerHTML = '<div style=\"padding:40px;text-align:center;color:#94a3b8\">' + (window.ganttEmptyText || '') + '</div>'; return; }\n" .
    "    var chart = echarts.init(el);\n" .
    "    el._chart = chart;\n" .
    "    var renderItem = function(params, api){\n" .
    "      var y = api.coord([0, api.value(0)])[1];\n" .
    "      var start = api.coord([api.value(1), api.value(0)]);\n" .
    "      var end   = api.coord([api.value(2), api.value(0)]);\n" .
    "      var height = api.size([0, 1])[1] * 0.55;\n" .
    "      var rect = echarts.graphic.clipRectByRect(\n" .
    "        {x: start[0], y: y - height/2, width: Math.max(end[0]-start[0], 2), height: height},\n" .
    "        {x: params.coordSys.x, y: params.coordSys.y, width: params.coordSys.width, height: params.coordSys.height}\n" .
    "      );\n" .
    "      return rect && {type: 'rect', shape: rect, style: api.style()};\n" .
    "    };\n" .
    "    chart.setOption({\n" .
    "      tooltip: {formatter: function(p){\n" .
    "        var v = p.value;\n" .
    "        var fmt = function(ts){ var d = new Date(ts); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); };\n" .
    "        return '<b>'+p.name+'</b><br/>' + (v[6] ? v[6]+(v[7]?' / '+v[7]:'')+'<br/>' : '') +\n" .
    "               'status: '+v[3]+'<br/>est: '+v[4]+'h<br/>'+fmt(v[1])+' ~ '+fmt(v[2]);\n" .
    "      }},\n" .
    "      grid: {left: 110, right: 30, top: 30, bottom: 50},\n" .
    "      xAxis: {type: 'time', min: window.ganttBeginTs ? window.ganttBeginTs * 1000 : 'dataMin', max: window.ganttEndTs ? (window.ganttEndTs + 86400) * 1000 : 'dataMax'},\n" .
    "      yAxis: {type: 'category', data: members, axisLabel: {interval: 0}, splitArea: {show: true}},\n" .
    "      series: [{type: 'custom', renderItem: renderItem, encode: {x: [1,2], y: 0}, data: data}]\n" .
    "    });\n" .
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
    "waitForElement('loadTrendChart', function(){ initProjectLoadTrend(); });\n" .
    "waitForElement('resourceGantt', function(){ initResourceGantt(); });\n" .
    "</script>";

/* Conflicts panel. */
$conflictRows = array();
if(!empty($conflicts))
{
    foreach($conflicts as $idx => $row)
    {
        $colorObj  = zget($config->man_resource->loadRangeColors, $row['level'], new stdClass());
        $textColor = isset($colorObj->text) ? $colorObj->text : '#d97706';
        $levelName = zget($lang->man_resource->loadType, $row['level'], $row['level']);
        $conflictRows[] = array(
            'id'        => $idx + 1,
            'realname'  => $row['realname'],
            'date'      => $row['date'],
            'hours'     => $row['hours'],
            'taskCount' => $row['taskCount'],
            'ratio'     => "<span style='color:{$textColor};font-weight:600'>{$row['ratio']}%</span>",
            'level'     => "<span style='color:{$textColor}'>{$levelName}</span>"
        );
    }
}

panel
(
    set::title($lang->man_resource->conflictTitle),
    setClass('mt-4'),
    div(setClass('px-3 pt-2 text-gray'), $lang->man_resource->conflictTip),
    dtable
    (
        setID('conflictList'),
        set::cols(array
        (
            array('name' => 'realname',  'title' => $lang->man_resource->user,             'width' => '150px'),
            array('name' => 'date',      'title' => $lang->man_resource->conflictDateCol,  'width' => '120px', 'sortType' => true),
            array('name' => 'hours',     'title' => $lang->man_resource->conflictHoursCol, 'width' => '140px', 'sortType' => true),
            array('name' => 'taskCount', 'title' => $lang->man_resource->conflictTaskCol,  'width' => '120px', 'sortType' => true),
            array('name' => 'ratio',     'title' => $lang->man_resource->conflictRatioCol, 'width' => '120px', 'html' => true),
            array('name' => 'level',     'title' => $lang->man_resource->conflictLevelCol, 'width' => '100px', 'html' => true)
        )),
        set::data($conflictRows),
        set::footPager(usePager()),
        set::emptyTip($lang->man_resource->conflictEmpty)
    )
);

render();

html($chartJS);
