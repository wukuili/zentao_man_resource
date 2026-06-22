<?php
declare(strict_types=1);
/**
 * The simulate view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

$verdictMap = array(
    'green'  => array('label' => $lang->man_resource->simulateVerdictGreen,  'cls' => 'label label-success'),
    'yellow' => array('label' => $lang->man_resource->simulateVerdictYellow, 'cls' => 'label label-warning'),
    'red'    => array('label' => $lang->man_resource->simulateVerdictRed,    'cls' => 'label label-danger'),
);

jsVar('simResult',  !empty($lastResult) && $lastResult->result_json ? json_decode($lastResult->result_json) : null);
jsVar('verdictMap', $verdictMap);
jsVar('simLang', array(
    'overall'       => $lang->man_resource->loadTrendOverall,
    'yLabel'        => $lang->man_resource->loadTrendY,
    'adjusted'      => $lang->man_resource->simulateAdjustedTasks,
    'maxLoad'       => $lang->man_resource->simulateMaxLoad,
    'conflictDate'  => $lang->man_resource->conflictDateCol,
    'conflictUser'  => $lang->man_resource->user,
    'conflictHours' => $lang->man_resource->conflictHoursCol,
    'conflictRate'  => $lang->man_resource->conflictRatioCol,
    'conflictTask'  => $lang->man_resource->conflictTaskCol,
    'conflictLevel' => $lang->man_resource->conflictLevelCol,
    'conflictNone'  => $lang->man_resource->conflictEmpty,
    'pickProject'   => $lang->man_resource->simulatePickProject
));

$projectOptions = array(0 => '') + $projects;

panel
(
    set::title($lang->man_resource->simulatedLoad),
    form
    (
        set::method('post'),
        set::action(createLink('man_resource', 'simulate')),
        formRow(formGroup(set::label($lang->man_resource->simulateName),    set::name('simulation_name'), set::required(true))),
        formRow(formGroup(set::label($lang->man_resource->simulateProject), set::control('picker'), set::name('project_id'), set::items($projectOptions), set::value(0))),
        formRow(formGroup(set::label($lang->man_resource->beginTime),       set::control('datePicker'), set::name('start_date'), set::value($today))),
        formRow(formGroup(set::label($lang->man_resource->endTime),         set::control('datePicker'), set::name('end_date'),   set::value($plus5))),
        formRow
        (
            formGroup
            (
                set::label($lang->man_resource->simulateAdjust),
                div
                (
                    btn(setID('simLoadTasksBtn'), set::type('default'), set::text($lang->man_resource->simulateLoadTasks)),
                    div(setClass('text-muted text-sm mt-2'), $lang->man_resource->simulateAdjustHint),
                    h::input(set::type('hidden'), set::name('adjustments'), setID('simAdjustHidden'), set::value('[]')),
                    h::table
                    (
                        setID('simAdjustTable'),
                        setClass('table table-bordered mt-2'),
                        h::thead(h::tr(
                            h::th($lang->man_resource->simulateTaskCol),
                            h::th($lang->man_resource->simulateAssigneeCol),
                            h::th($lang->man_resource->simulateEstStartedCol),
                            h::th($lang->man_resource->simulateDeadlineCol),
                            h::th($lang->man_resource->simulateEstimateCol)
                        )),
                        h::tbody()
                    )
                )
            )
        ),
        formRow(div(setClass('text-center'),
            input(set::type('submit'), set::className('btn btn-primary'), set::value($lang->man_resource->simulateRun))
        ))
    )
);
if(!empty($lastResult))
{
    panel
    (
        set::title(htmlspecialchars((string)$lastResult->simulation_name) . ' — ' . $lastResult->start_date . ' ~ ' . $lastResult->end_date),
        setClass('mt-4'),
        div(setID('simulateMeta'), setStyle(array('marginBottom' => '12px'))),
        div(setID('simulateChart'), setStyle(array('width' => '100%', 'height' => '320px'))),
        div(setID('simulateConflicts'), setClass('mt-4'))
    );
}
else
{
    panel
    (
        setClass('mt-4'),
        div(setClass('text-muted'), $lang->man_resource->simulateNoResult)
    );
}

js(<<<'JS'
(function(){
  var $btn = $('#simLoadTasksBtn');
  var $proj = $('select[name="project_id"], [name="project_id"]');
  var $tbl = $('#simAdjustTable tbody');
  var $hidden = $('#simAdjustHidden');
  var labels = window.simLang || {};

  $btn.on('click', function(e){
    e.preventDefault();
    var pid = parseInt($proj.val(), 10);
    if(!pid){ alert(labels.pickProject || '请先选择项目'); return; }
    var url = (window.config && config.webRoot ? config.webRoot : '/') + 'index.php?m=man_resource&f=simulateTasks&projectID=' + pid + '&t=json';
    $.get(url, function(resp){
      if(typeof resp === 'string'){ try { resp = JSON.parse(resp); } catch(e){ alert('parse error'); return; } }
      if(!resp || resp.result !== 'success'){ alert(resp && resp.message || 'fail'); return; }
      $tbl.empty();
      (resp.tasks || []).forEach(function(t){
        var es = (t.estStarted && t.estStarted !== '0000-00-00') ? t.estStarted : '';
        var dl = (t.deadline   && t.deadline   !== '0000-00-00') ? t.deadline   : '';
        var tr = $('<tr>')
          .attr('data-task-id', t.id)
          .attr('data-orig-est-started', es)
          .attr('data-orig-deadline', dl)
          .attr('data-orig-estimate', t.estimate || 0)
          .attr('data-orig-assigned', t.assignedTo || '');
        tr.append($('<td>').text(t.name + ' (#' + t.id + ')'));
        tr.append($('<td>').text(t.assignedRealname || t.assignedTo || ''));
        tr.append($('<td>').html('<input type="date" class="form-control sim-est" value="' + es + '">'));
        tr.append($('<td>').html('<input type="date" class="form-control sim-deadline" value="' + dl + '">'));
        tr.append($('<td>').html('<input type="number" step="0.5" min="0" class="form-control sim-estimate" value="' + (t.estimate || 0) + '">'));
        $tbl.append(tr);
      });
    });
  });

  $('form').on('submit', function(){
    var rows = [];
    $tbl.find('tr').each(function(){
      var $tr = $(this);
      var taskId = parseInt($tr.attr('data-task-id'), 10);
      var es  = $tr.find('.sim-est').val() || '';
      var dl  = $tr.find('.sim-deadline').val() || '';
      var est = parseFloat($tr.find('.sim-estimate').val() || 0);
      var origEs  = $tr.attr('data-orig-est-started') || '';
      var origDl  = $tr.attr('data-orig-deadline')   || '';
      var origEst = parseFloat($tr.attr('data-orig-estimate') || 0);
      if(es !== origEs || dl !== origDl || est !== origEst){
        rows.push({task_id: taskId, est_started: es, deadline: dl, estimate: est, assigned_to: $tr.attr('data-orig-assigned')});
      }
    });
    $hidden.val(JSON.stringify(rows));
  });
})();
JS
);

if(!empty($lastResult))
{
    js(<<<'JS'
function initSimulateChart(){
  if(!window.simResult) return;
  var lang = window.simLang || {};
  var verdictMap = window.verdictMap || {};

  function renderMeta(r){
    var v = verdictMap[r.verdict] || {label: r.verdict, cls: 'label'};
    var html  = "<span class='" + v.cls + "'>" + v.label + "</span>";
    html += " &nbsp; " + (lang.adjusted || 'adjusted') + ": <strong>" + (r.adjustedTasks || 0) + "</strong>";
    html += " &nbsp; " + (lang.maxLoad  || 'max') + ": <strong>" + (r.maxLoadRate || 0) + "%</strong>";
    var el = document.getElementById('simulateMeta');
    if(el) el.innerHTML = html;
  }
  function renderConflicts(r){
    var box = document.getElementById('simulateConflicts');
    if(!box) return;
    if(!r.conflicts || !r.conflicts.length){ box.innerHTML = "<p class='text-muted'>" + (lang.conflictNone || '') + "</p>"; return; }
    var html = "<table class='table table-bordered'><thead><tr>" +
      "<th>" + lang.conflictDate + "</th><th>" + lang.conflictUser + "</th>" +
      "<th>" + lang.conflictHours + "</th><th>" + lang.conflictRate + "</th>" +
      "<th>" + lang.conflictTask + "</th><th>" + lang.conflictLevel + "</th></tr></thead><tbody>";
    r.conflicts.forEach(function(c){
      html += "<tr><td>" + c.date + "</td><td>" + c.realname + "</td><td>" + c.hours + "</td><td>" + c.ratio + "%</td><td>" + c.taskCount + "</td><td>" + c.level + "</td></tr>";
    });
    html += "</tbody></table>";
    box.innerHTML = html;
  }
  function renderChart(r){
    var s = r.series; if(!s || !s.dates || !s.dates.length) return;
    var el = document.getElementById('simulateChart'); if(!el || el._chart) return;
    var series = [];
    Object.keys(s.series || {}).forEach(function(acc){
      var row = s.series[acc];
      series.push({name: row.realname || acc, type: 'bar', stack: 'load', data: row.rates || []});
    });
    series.push({name: lang.overall, type: 'line', smooth: true, data: s.overall || [], symbol: 'circle', lineStyle: {width: 2, color: '#dc2626'}, itemStyle: {color: '#dc2626'}, z: 5});
    var chart = echarts.init(el); el._chart = chart;
    chart.setOption({
      tooltip: {trigger: 'axis'},
      legend: {type: 'scroll', top: 0},
      grid: {left: 50, right: 30, top: 40, bottom: 50},
      xAxis: {type: 'category', data: s.dates, axisLabel: {rotate: 35}},
      yAxis: {type: 'value', name: lang.yLabel},
      series: series
    });
    window.addEventListener('resize', function(){ chart.resize(); });
  }
  renderMeta(window.simResult);
  renderConflicts(window.simResult);
  function tryRender(){
    if(window.echarts){ renderChart(window.simResult); return; }
    var srcs = [(window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js'];
    if(typeof $ != 'undefined' && $.getLib){
      $.getLib({src: srcs, root: false}, function(){ renderChart(window.simResult); });
    } else {
      var s = document.createElement('script');
      s.src = srcs[0];
      s.onload = function(){ renderChart(window.simResult); };
      document.head.appendChild(s);
    }
  }
  tryRender();
}
function waitAndInitSimulate(){
  var el = document.getElementById('simulateChart');
  if(el || !window.simResult){ initSimulateChart(); return; }
  var tries = 0;
  var iv = setInterval(function(){
    tries++;
    var el2 = document.getElementById('simulateChart');
    if(el2 || tries > 30){ clearInterval(iv); if(el2) initSimulateChart(); }
  }, 100);
}
if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', waitAndInitSimulate); }
else { waitAndInitSimulate(); }
JS
    );
}

render();
