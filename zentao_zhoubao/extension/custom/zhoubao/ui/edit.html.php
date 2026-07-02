<?php
declare(strict_types=1);
/**
 * 项目周报 — 写/编辑周报 ZIN 模板
 *
 * 自动区（只读，实时计算）：进度工时概览 / 走查提示 / 本周完成任务 / 本周未完成+逾期任务。
 * 手写区（表单）：本周总结（置顶）/ 下周计划 / 风险与需协调资源，AJAX 保存草稿或提交。
 * 沿用 taizhang edit 页与 zoucha browse 页的约定：手写 HTML 字符串拼接后通过 html() 传入
 * panel()，不使用 dtable()/formPanel()/textarea()/btn() 等本仓库不存在的 DSL 部件。
 */
namespace zin;

$auto      = $this->view->auto;
$report    = $this->view->report;
$project   = $this->view->projectInfo;
$weekStart = $this->view->weekStart;
$stat      = $auto['stat'];

$esc = function($v){ return htmlspecialchars((string)$v); };

$rowHTML = function($rows, $overdue = false) use ($esc) {
    $html = '';
    foreach($rows as $t)
    {
        $when = $overdue ? substr($t['deadline'], 0, 10) : substr(isset($t['finishedDate']) ? $t['finishedDate'] : $t['deadline'], 0, 10);
        $extra = $overdue ? ('逾期' . $t['daysOverdue'] . '天') : (isset($t['consumed']) ? ('工时' . $t['consumed']) : '');
        $assignee = isset($t['assignedToName']) ? $t['assignedToName'] : (isset($t['assignedTo']) ? $t['assignedTo'] : '');
        $html .= '<tr' . ($overdue ? ' class="zb-overdue"' : '') . '><td>' . $esc($t['name']) . '</td><td>' . $esc($assignee) . '</td><td>' . $esc($when) . '</td><td>' . $esc($extra) . '</td></tr>';
    }
    return $html;
};

$doneRowsHTML   = $rowHTML($auto['done']);
$undoneRowsHTML = $rowHTML($auto['overdue'], true) . $rowHTML($auto['undone']);

$saveURL = helper::createLink('zhoubao', 'edit', "project={$project->id}&week=" . str_replace('-', '_', $weekStart));
$copyURL = helper::createLink('zhoubao', 'copyLast', "project={$project->id}&week=" . str_replace('-', '_', $weekStart));

$gantt = $this->view->gantt;

$historyURL = helper::createLink('zhoubao', 'history', "project={$project->id}");
$pageHTML  = '<div class="zb-view-actions"><a class="btn btn-default btn-sm" href="' . $historyURL . '" target="_blank">' . $esc($lang->zhoubao->viewHistory) . '</a></div>';
$pageHTML .= '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)$stat['progress'] . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc($stat['weekConsumed']) . '</span>';
$pageHTML .= '<span>剩余工时 ' . $esc($stat['totalLeft']) . '</span>';
$pageHTML .= '<span>完成 ' . (int)$stat['doneCount'] . '</span>';
$pageHTML .= '<span>逾期 ' . (int)$stat['overdueCount'] . '</span>';
$pageHTML .= '</div>';

$pageHTML .= '<h3>' . $esc($gantt['title']) . '</h3>';
$pageHTML .= '<div id="zbGanttChart" style="width:100%;height:280px"></div>';

/* 走查提示：中文标签 + 悬停浮层（规则说明）+ 点击弹框（该项目该规则的明细，复用 zoucha 自身 detail 接口） */
$hasZoucha = !empty($auto['zoucha']);
if($hasZoucha)
{
    $ruleList  = $lang->zhoubao->zouchaRuleList;
    $ruleDescT = $lang->zhoubao->zouchaRuleDesc;
    $zCfg      = isset($this->config->zoucha) ? $this->config->zoucha : null;
    $ruleColors= ($zCfg && isset($zCfg->ruleColors)) ? $zCfg->ruleColors : $lang->zhoubao->zouchaRuleColors;
    $staleDays = $zCfg ? (int)$zCfg->staleDays : 7;
    $overdueMin= $zCfg ? max(1, (int)$zCfg->overdueMin) : 1;
    $longDays  = $zCfg ? (int)$zCfg->longTaskDays : 14;
    $descMap = array(
        'noTask'      => $ruleDescT['noTask'],
        'stale'       => sprintf($ruleDescT['stale'], $staleDays, $staleDays),
        'overdue'     => sprintf($ruleDescT['overdue'], $overdueMin),
        'noExecution' => $ruleDescT['noExecution'],
        'longTask'    => sprintf($ruleDescT['longTask'], $longDays),
    );

    $pageHTML .= '<div class="zb-zoucha-tags">走查提示：';
    foreach($auto['zoucha'] as $rule)
    {
        $color = isset($ruleColors[$rule]) ? $ruleColors[$rule] : '#f0ad4e';
        $label = zget($ruleList, $rule, $rule);
        $tip   = $label . "\n" . (isset($descMap[$rule]) ? $descMap[$rule] : '');
        $pageHTML .= '<span class="zb-zoucha-tag" data-tip="' . $esc($tip) . '" data-pid="' . (int)$project->id
            . '" data-rule="' . $esc($rule) . '" data-label="' . $esc($label) . '" style="background:' . $esc($color) . '">'
            . $esc($label) . '</span> ';
    }
    $pageHTML .= '</div>';
}

$prevReport = $this->view->prevReport;
$pageHTML .= '<div class="zb-prev-plan"><h3>' . $esc($lang->zhoubao->prevPlan) . '</h3><p>' . ($prevReport ? nl2br($esc($prevReport->nextPlan)) : '上周暂无周报') . '</p></div>';

$pageHTML .= '<form id="zbEditForm">';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->summary) . '</label><textarea name="summary" class="form-control" rows="3">' . $esc($report ? $report->summary : '') . '</textarea></div>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->doneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>完成时间</th><th>工时</th></tr></thead><tbody>' . ($doneRowsHTML ?: '<tr><td colspan="4">本周暂无完成任务</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->undoneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>截止</th><th>状态</th></tr></thead><tbody>' . ($undoneRowsHTML ?: '<tr><td colspan="4">本周暂无未完成任务</td></tr>') . '</tbody></table>';

$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->nextPlan) . '</label><textarea name="nextPlan" class="form-control" rows="4">' . $esc($report ? $report->nextPlan : '') . '</textarea></div>';

$hasRisk = ($report && $report->hasRisk === 'yes') ? 'yes' : 'no';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->hasRiskQuestion) . '</label>';
$pageHTML .= '<label class="zb-radio-inline"><input type="radio" name="hasRisk" value="yes"' . ($hasRisk === 'yes' ? ' checked' : '') . '> ' . $esc($lang->zhoubao->hasRiskList['yes']) . '</label>';
$pageHTML .= '<label class="zb-radio-inline"><input type="radio" name="hasRisk" value="no"' . ($hasRisk === 'no' ? ' checked' : '') . '> ' . $esc($lang->zhoubao->hasRiskList['no']) . '</label>';
$pageHTML .= '</div>';
$pageHTML .= '<div class="zb-form-group" id="zbRiskDetail" style="' . ($hasRisk === 'yes' ? '' : 'display:none') . '"><label>' . $esc($lang->zhoubao->risk) . '</label><textarea name="risk" class="form-control" rows="4">' . $esc($report ? $report->risk : '') . '</textarea></div>';
$pageHTML .= '<div class="zb-form-actions">';
$pageHTML .= '<button type="button" class="btn btn-default" onclick="zbCopyLast()">' . $esc($lang->zhoubao->copyLast) . '</button> ';
$pageHTML .= '<button type="button" class="btn btn-default" onclick="zbSaveDraft()">' . $esc($lang->zhoubao->saveDraft) . '</button> ';
$pageHTML .= '<button type="button" class="btn btn-primary" onclick="zbSubmitReport()">' . $esc($lang->zhoubao->submit) . '</button>';
$pageHTML .= '</div>';
$pageHTML .= '</form>';

panel(
    set::title($this->view->title . ' · ' . $project->name . ' · ' . $weekStart),
    html($pageHTML)
);

jsVar('window.zbSaveURL', $saveURL);
jsVar('window.zbCopyURL', $copyURL);
if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));

jsVar('window.zbGanttItems', $gantt['items']);
jsVar('window.zbGanttEmptyText', $lang->zhoubao->ganttEmpty);
pageJS(<<<JS
(function()
{
    var el = document.getElementById('zbGanttChart');
    if(!el) return;
    var items = window.zbGanttItems || [];
    if(!items.length){ el.innerHTML = '<div class="zb-gantt-empty">' + (window.zbGanttEmptyText || '') + '</div>'; return; }
    if(el._chart) return;

    function loadEcharts(cb)
    {
        if(window.echarts){ cb(); return; }
        var src = (window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js';
        if(typeof $ != 'undefined' && $.getLib){ $.getLib({src: [src], root: false}, cb); }
        else { var s = document.createElement('script'); s.src = src; s.onload = cb; document.head.appendChild(s); }
    }

    loadEcharts(function()
    {
        if(!window.echarts || el._chart) return;
        var names = items.map(function(it){ return it.name; });
        var data  = items.map(function(it, idx){
            var s = (new Date(it.start)).getTime();
            var e = (new Date(it.end)).getTime();
            return {name: it.name, value: [idx, s, e, it.status], itemStyle: {color: it.color}};
        });
        var chart = echarts.init(el);
        el._chart = chart;
        var renderItem = function(params, api)
        {
            var y = api.coord([0, api.value(0)])[1];
            var start = api.coord([api.value(1), api.value(0)]);
            var end   = api.coord([api.value(2), api.value(0)]);
            var height = api.size([0, 1])[1] * 0.55;
            var rect = echarts.graphic.clipRectByRect(
                {x: start[0], y: y - height/2, width: Math.max(end[0]-start[0], 2), height: height},
                {x: params.coordSys.x, y: params.coordSys.y, width: params.coordSys.width, height: params.coordSys.height}
            );
            return rect && {type: 'rect', shape: rect, style: api.style()};
        };
        chart.setOption({
            tooltip: {formatter: function(p){
                var v = p.value;
                var fmt = function(ts){ var d = new Date(ts); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); };
                return '<b>' + p.name + '</b><br/>' + v[3] + '<br/>' + fmt(v[1]) + ' ~ ' + fmt(v[2]);
            }},
            grid: {left: 120, right: 30, top: 20, bottom: 40},
            xAxis: {type: 'time'},
            yAxis: {type: 'category', data: names, axisLabel: {interval: 0}},
            series: [{type: 'custom', renderItem: renderItem, encode: {x: [1,2], y: 0}, data: data}]
        });
        window.addEventListener('resize', function(){ chart.resize(); });
    });
})();
JS);

/* 实测验证（2026-07-02，真实 22.2 实例）：点击“写周报”走 SPA 内部导航进入本页时，updatePageWithHtml()
   会把裸 <script> 整体过滤掉（zbSaveDraft/zbSubmitReport/zbCopyLast 从未绑定，按钮点了没反应），
   必须通过 pageJS()/css()（对应专门的 zin-page-js/zin-page-css 占位符，SPA 用 replaceWith 重建执行）注入，
   而不是手写 echo "<script>"。jsVar() 本来就走这条通道，所以之前唯独 zbSaveURL 是好的，函数定义是坏的。 */
$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/common.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/zhoubao/js/zhoubao.js';
if(is_file($cssPath)) css(file_get_contents($cssPath));
if(is_file($jsPath))  pageJS(file_get_contents($jsPath));
