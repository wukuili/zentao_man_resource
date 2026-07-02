<?php
declare(strict_types=1);
/**
 * 项目周报 — 查看周报 ZIN 模板（只读）
 *
 * 渲染已提交周报：读取控制器固化的 snapshot（json_decode 后的 auto 数组），
 * 不重新计算实时数据；手写三段（本周总结/下周计划/风险）直接来自 zt_zhoubao 行。
 * 沿用 edit.html.php / browse.html.php 的约定：手写 HTML 字符串拼接后通过 html()
 * 传入 panel()，不使用本仓库不存在的 dtable() 等 DSL 部件；lang 通过全局 $lang->zhoubao
 * 访问（与已提交的 edit.html.php / browse.html.php 一致）。
 * 走查提示标签有悬停/点击交互，需要 js/zhoubao.js 里的 zb-tip/zb-modal 逻辑，故一并 pageJS() 内联。
 */
namespace zin;

$auto    = $this->view->auto;
$report  = $this->view->report;
$project = $this->view->projectInfo;
$stat    = isset($auto['stat']) ? $auto['stat'] : array();

$esc = function($v){ return htmlspecialchars((string)$v); };

$rowHTML = function($rows, $overdue = false) use ($esc) {
    $html = '';
    foreach($rows as $t)
    {
        $when = $overdue ? substr($t['deadline'], 0, 10) : substr(isset($t['finishedDate']) ? $t['finishedDate'] : $t['deadline'], 0, 10);
        $assignee = isset($t['assignedToName']) ? $t['assignedToName'] : (isset($t['assignedTo']) ? $t['assignedTo'] : '');
        $html .= '<tr><td>' . $esc($t['name']) . '</td><td>' . $esc($assignee) . '</td><td>' . $esc($when) . '</td></tr>';
    }
    return $html;
};

$doneRows   = $rowHTML(isset($auto['done']) ? $auto['done'] : array());
$undoneRows = $rowHTML(isset($auto['overdue']) ? $auto['overdue'] : array(), true) . $rowHTML(isset($auto['undone']) ? $auto['undone'] : array());

/* 编辑入口：仅 manage 权限者或本项目 PM 可见（control::view() 已算好 canEdit），跳回 edit 页复用同一套表单 */
$canEdit = !empty($this->view->canEdit);
$editURL = helper::createLink('zhoubao', 'edit', "project={$project->id}&week=" . str_replace('-', '_', (string)$report->weekStart));

$pageHTML = '';
if($canEdit)
{
    $pageHTML .= '<div class="zb-view-actions"><a class="btn btn-primary btn-sm" href="' . $editURL . '">' . $esc($lang->zhoubao->editReport) . '</a></div>';
}
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)(isset($stat['progress']) ? $stat['progress'] : 0) . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc(isset($stat['weekConsumed']) ? $stat['weekConsumed'] : 0) . '</span>';
$pageHTML .= '<span>完成 ' . (int)(isset($stat['doneCount']) ? $stat['doneCount'] : 0) . '</span>';
$pageHTML .= '<span>逾期 ' . (int)(isset($stat['overdueCount']) ? $stat['overdueCount'] : 0) . '</span>';
$pageHTML .= '</div>';

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

$pageHTML .= '<h3>' . $esc($lang->zhoubao->summary) . '</h3><p>' . nl2br($esc($report->summary)) . '</p>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->doneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>时间</th></tr></thead><tbody>' . ($doneRows ?: '<tr><td colspan="3">无</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->undoneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>时间</th></tr></thead><tbody>' . ($undoneRows ?: '<tr><td colspan="3">无</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->nextPlan) . '</h3><p>' . nl2br($esc($report->nextPlan)) . '</p>';

$hasRisk      = ($report->hasRisk === 'yes') ? 'yes' : 'no';
$riskBadgeCls = 'zb-risk-badge zb-risk-' . $hasRisk;
$riskBadgeTxt = $lang->zhoubao->hasRiskList[$hasRisk];
$pageHTML .= '<h3>' . $esc($lang->zhoubao->risk) . '<span class="' . $riskBadgeCls . '">' . $esc($riskBadgeTxt) . '</span></h3>';
if($hasRisk === 'yes') $pageHTML .= '<p>' . nl2br($esc($report->risk)) . '</p>';

panel(
    set::title($this->view->title . ' · ' . $esc($project->name) . ' · 第' . (int)$report->week . '周'),
    html($pageHTML)
);

if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));

/* 实测验证（2026-07-02，真实 22.2 实例）：手写 echo "<style>"/"<script>" 在 SPA 内部导航（点链接进入本页）
   时会被 updatePageWithHtml() 过滤/不可靠，统一改用官方 css()/pageJS() 通道，与 edit/browse 页一致。 */
$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/common.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/zhoubao/js/zhoubao.js';
if(is_file($cssPath)) css(file_get_contents($cssPath));
if(is_file($jsPath))  pageJS(file_get_contents($jsPath));
