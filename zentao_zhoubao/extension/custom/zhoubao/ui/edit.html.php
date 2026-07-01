<?php
declare(strict_types=1);
/**
 * 项目周报 — 写/编辑周报 ZIN 模板
 *
 * 自动区（只读，实时计算）：本周完成任务 / 本周未完成+逾期任务 / 进度工时概览。
 * 手写区（表单）：下周计划 / 风险与需协调资源 / 本周小结，AJAX 保存草稿或提交。
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
        $html .= '<tr' . ($overdue ? ' class="zb-overdue"' : '') . '><td>' . $esc($t['name']) . '</td><td>' . $esc($t['assignedTo']) . '</td><td>' . $esc($when) . '</td><td>' . $esc($extra) . '</td></tr>';
    }
    return $html;
};

$doneRowsHTML   = $rowHTML($auto['done']);
$undoneRowsHTML = $rowHTML($auto['overdue'], true) . $rowHTML($auto['undone']);

$saveURL = helper::createLink('zhoubao', 'edit', "project={$project->id}&week=" . str_replace('-', '_', $weekStart));
$copyURL = helper::createLink('zhoubao', 'copyLast', "project={$project->id}&week=" . str_replace('-', '_', $weekStart));

$pageHTML  = '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)$stat['progress'] . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc($stat['weekConsumed']) . '</span>';
$pageHTML .= '<span>剩余工时 ' . $esc($stat['totalLeft']) . '</span>';
$pageHTML .= '<span>完成 ' . (int)$stat['doneCount'] . '</span>';
$pageHTML .= '<span>逾期 ' . (int)$stat['overdueCount'] . '</span>';
$pageHTML .= '</div>';

if(!empty($auto['zoucha']))
{
    $pageHTML .= '<div class="zb-zoucha-tags">走查提示：';
    foreach($auto['zoucha'] as $rule)
    {
        $pageHTML .= '<span class="label label-warning">' . $esc($rule) . '</span> ';
    }
    $pageHTML .= '</div>';
}

$pageHTML .= '<h3>' . $esc($lang->zhoubao->doneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>完成时间</th><th>工时</th></tr></thead><tbody>' . ($doneRowsHTML ?: '<tr><td colspan="4">本周暂无完成任务</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->undoneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>截止</th><th>状态</th></tr></thead><tbody>' . ($undoneRowsHTML ?: '<tr><td colspan="4">本周暂无未完成任务</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>手写内容</h3>';
$pageHTML .= '<form id="zbEditForm">';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->nextPlan) . '</label><textarea name="nextPlan" class="form-control" rows="4">' . $esc($report ? $report->nextPlan : '') . '</textarea></div>';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->risk) . '</label><textarea name="risk" class="form-control" rows="4">' . $esc($report ? $report->risk : '') . '</textarea></div>';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->summary) . '</label><textarea name="summary" class="form-control" rows="3">' . $esc($report ? $report->summary : '') . '</textarea></div>';
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

jsVar('zbSaveURL', $saveURL);
jsVar('zbCopyURL', $copyURL);

/* extension/ 不在 Web 根下，读取文件内容内联输出，避免静态资源 404/MIME 报错 */
$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/zhoubao.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/zhoubao/js/zhoubao.js';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
if(is_file($jsPath))  echo "<script>\n" . file_get_contents($jsPath)  . "\n</script>";
