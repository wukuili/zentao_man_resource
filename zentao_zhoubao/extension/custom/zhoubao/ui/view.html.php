<?php
declare(strict_types=1);
/**
 * 项目周报 — 查看周报 ZIN 模板（只读）
 *
 * 渲染已提交周报：读取控制器固化的 snapshot（json_decode 后的 auto 数组），
 * 不重新计算实时数据；手写三段（下周计划/风险/小结）直接来自 zt_zhoubao 行。
 * 沿用 edit.html.php / browse.html.php 的约定：手写 HTML 字符串拼接后通过 html()
 * 传入 panel()，不使用本仓库不存在的 dtable() 等 DSL 部件；lang 通过全局 $lang->zhoubao
 * 访问（与已提交的 edit.html.php / browse.html.php 一致）。
 * 本页为纯只读展示，没有表单/按钮，故不内联 js/zhoubao.js（避免 __zhoubaoBound 空跑绑定）。
 */
namespace zin;

$auto    = $this->view->auto;
$report  = $this->view->report;
$project = $this->view->projectInfo;
$stat    = isset($auto['stat']) ? $auto['stat'] : array();

$esc = function($v){ return htmlspecialchars((string)$v); };

$rowHTML = function($rows) use ($esc) {
    $html = '';
    foreach($rows as $t)
    {
        $when = substr(isset($t['finishedDate']) ? $t['finishedDate'] : $t['deadline'], 0, 10);
        $html .= '<tr><td>' . $esc($t['name']) . '</td><td>' . $esc($t['assignedTo']) . '</td><td>' . $esc($when) . '</td></tr>';
    }
    return $html;
};

$doneRows   = $rowHTML(isset($auto['done']) ? $auto['done'] : array());
$undoneRows = $rowHTML(array_merge(isset($auto['overdue']) ? $auto['overdue'] : array(), isset($auto['undone']) ? $auto['undone'] : array()));

$pageHTML  = '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)(isset($stat['progress']) ? $stat['progress'] : 0) . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc(isset($stat['weekConsumed']) ? $stat['weekConsumed'] : 0) . '</span>';
$pageHTML .= '<span>完成 ' . (int)(isset($stat['doneCount']) ? $stat['doneCount'] : 0) . '</span>';
$pageHTML .= '<span>逾期 ' . (int)(isset($stat['overdueCount']) ? $stat['overdueCount'] : 0) . '</span>';
$pageHTML .= '</div>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->doneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>时间</th></tr></thead><tbody>' . ($doneRows ?: '<tr><td colspan="3">无</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->undoneTasks) . '</h3>';
$pageHTML .= '<table class="zb-table"><thead><tr><th>任务</th><th>负责人</th><th>时间</th></tr></thead><tbody>' . ($undoneRows ?: '<tr><td colspan="3">无</td></tr>') . '</tbody></table>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->nextPlan) . '</h3><p>' . nl2br($esc($report->nextPlan)) . '</p>';
$pageHTML .= '<h3>' . $esc($lang->zhoubao->risk) . '</h3><p>' . nl2br($esc($report->risk)) . '</p>';
$pageHTML .= '<h3>' . $esc($lang->zhoubao->summary) . '</h3><p>' . nl2br($esc($report->summary)) . '</p>';

panel(
    set::title($this->view->title . ' · ' . $esc($project->name) . ' · 第' . (int)$report->week . '周'),
    html($pageHTML)
);

/* extension/ 不在 Web 根下，读取文件内容内联输出，避免静态资源 404/MIME 报错。
   本页只读、无表单/按钮，不内联 js/zhoubao.js（无需绑定 zbSaveDraft 等交互）。 */
$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/zhoubao.css';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
