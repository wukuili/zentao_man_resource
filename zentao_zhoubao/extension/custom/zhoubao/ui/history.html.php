<?php
declare(strict_types=1);
/**
 * 项目周报 — 单项目历史周报对比 ZIN 模板
 *
 * 按周倒序列出该项目历次周报（含草稿），用于对比"下周计划"与后续"本周总结"的连续性。
 * 完成数/逾期数只有已提交周报（有 snapshot）才有值，草稿显示 "-"（自动区数据只在提交时固化）。
 * 沿用 browse.html.php 的手写表格约定，不使用 dtable()。
 */
namespace zin;

$projectInfo = $this->view->projectInfo;
$weeks       = (string)$this->view->weeks;
$rows        = $this->view->rows;

$statusList  = $lang->zhoubao->statusList;
$hasRiskList = $lang->zhoubao->hasRiskList;
$weeksFilter = $lang->zhoubao->weeksFilter;

$esc = function($v){ return htmlspecialchars((string)$v); };

/* ── 顶部工具栏：N 周下拉，切换即跳转 ── */
$toolbarHTML  = '<div class="zb-hist-toolbar">';
$toolbarHTML .= '<label>' . $esc($lang->zhoubao->weeksFilterLabel) . '</label>';
$toolbarHTML .= '<select id="zbHistWeeks" class="form-control" style="max-width:140px">';
foreach($weeksFilter as $key => $label)
{
    $sel = ($weeks === $key) ? ' selected' : '';
    $toolbarHTML .= '<option value="' . $esc($key) . '"' . $sel . '>' . $esc($label) . '</option>';
}
$toolbarHTML .= '</select>';
$toolbarHTML .= '</div>';

/* ── 数据表 ── */
$tableHTML  = '<div class="zb-table-wrap"><table class="zb-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:110px">' . $esc($lang->zhoubao->week) . '</th>';
$tableHTML .= '<th style="width:90px">' . $esc($lang->zhoubao->fillStatus) . '</th>';
$tableHTML .= '<th>' . $esc($lang->zhoubao->summary) . '</th>';
$tableHTML .= '<th>' . $esc($lang->zhoubao->nextPlan) . '</th>';
$tableHTML .= '<th style="width:90px">' . $esc($lang->zhoubao->hasRiskQuestion) . '</th>';
$tableHTML .= '<th style="width:80px">' . $esc($lang->zhoubao->doneCount) . '</th>';
$tableHTML .= '<th style="width:80px">' . $esc($lang->zhoubao->overdueCount) . '</th>';
$tableHTML .= '</tr></thead><tbody>';

if(empty($rows))
{
    $tableHTML .= '<tr><td colspan="7" class="zb-empty">该项目暂无历史周报。</td></tr>';
}
else
{
    foreach($rows as $r)
    {
        $weekLabel = $r->year . '年第' . $r->week . '周';
        $statusLabel = zget($statusList, $r->status, $r->status);
        $hasRisk = ($r->hasRisk === 'yes') ? 'yes' : 'no';
        $riskLabel = $hasRiskList[$hasRisk];

        $stat = array();
        if($r->snapshot)
        {
            $snap = json_decode($r->snapshot, true);
            if(isset($snap['stat'])) $stat = $snap['stat'];
        }
        $doneText    = isset($stat['doneCount'])    ? (string)(int)$stat['doneCount']    : '-';
        $overdueText = isset($stat['overdueCount']) ? (string)(int)$stat['overdueCount'] : '-';

        $tableHTML .= '<tr>';
        $tableHTML .= '<td>' . $esc($weekLabel) . '</td>';
        $tableHTML .= '<td class="td-center">' . $esc($statusLabel) . '</td>';
        $tableHTML .= '<td class="zb-hist-cell" title="' . $esc($r->summary) . '">' . $esc($r->summary) . '</td>';
        $tableHTML .= '<td class="zb-hist-cell" title="' . $esc($r->nextPlan) . '">' . $esc($r->nextPlan) . '</td>';
        $tableHTML .= '<td class="td-center"><span class="zb-risk-badge zb-risk-' . $hasRisk . '">' . $esc($riskLabel) . '</span></td>';
        $tableHTML .= '<td class="td-center">' . $esc($doneText) . '</td>';
        $tableHTML .= '<td class="td-center">' . $esc($overdueText) . '</td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody></table></div>';

panel(
    set::title($this->view->title . ' · ' . $esc($projectInfo->name)),
    html($toolbarHTML . $tableHTML)
);

$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/common.css';
if(is_file($cssPath)) css(file_get_contents($cssPath));

jsVar('window.zhoubaoHistoryURL', helper::createLink('zhoubao', 'history', "project={$projectInfo->id}&weeks=__WEEKS__"));

pageJS(<<<JS
(function()
{
    if(window.zbHistBound) return;
    window.zbHistBound = true;
    var sel = document.getElementById('zbHistWeeks');
    if(sel) sel.addEventListener('change', function(){
        window.location.href = window.zhoubaoHistoryURL.replace('__WEEKS__', encodeURIComponent(sel.value));
    });
})();
JS);
