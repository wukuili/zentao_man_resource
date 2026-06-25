<?php
declare(strict_types=1);
/**
 * 项目走查 — 列表页 ZIN 模板
 *
 * 渲染：筛选栏 + 汇总条 + 数据表（彩色问题标签）+ 分页器。
 * CSS/JS 由本文件末尾服务端内联输出（extension/ 不在 www 下，不能用静态 URL）。
 */
namespace zin;

$results    = $this->view->results;
$rule       = (string)$this->view->rule;
$ruleCounts = $this->view->ruleCounts;
$pageID     = (int)$this->view->pageID;
$pageTotal  = (int)$this->view->pageTotal;
$recPerPage = (int)$this->view->recPerPage;
$total      = (int)$this->view->total;
$totalAll   = (int)$this->view->totalAll;

$ruleList   = $lang->zoucha->ruleList;
$ruleColors = $this->config->zoucha->ruleColors;

$exportURL = helper::createLink('zoucha', 'export', "rule={$rule}");
jsVar('zouchaBrowseURL', helper::createLink('zoucha', 'browse'));

/* 工具栏 */
toolbar
(
    item(set::text($lang->zoucha->exportEntry), set::icon('export'), set::type('ghost'), set::url($exportURL)),
);

/* ── 筛选栏 HTML ── */
$ruleParam = ($rule === '') ? 'all' : $rule;
$filterHTML  = '<div class="zc-filter-bar">';
$filterHTML .= '<label>' . htmlspecialchars($lang->zoucha->filterRule) . '</label>';
$filterHTML .= '<select id="zcFilterRule" class="form-control" style="max-width:180px">';
$filterHTML .= '<option value="all"' . ($rule === '' ? ' selected' : '') . '>' . htmlspecialchars($lang->zoucha->filterAll) . '（' . $totalAll . '）</option>';
foreach($ruleList as $key => $label)
{
    if(!in_array($key, $this->config->zoucha->rules, true)) continue;
    $cnt = isset($ruleCounts[$key]) ? (int)$ruleCounts[$key] : 0;
    $sel = ($rule === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '（' . $cnt . '）</option>';
}
$filterHTML .= '</select>';
$filterHTML .= '<button type="button" class="btn btn-primary btn-sm" onclick="zcSubmitFilter()" style="margin-left:8px">' . htmlspecialchars($lang->zoucha->filterSearch) . '</button>';
$filterHTML .= '<button type="button" class="btn btn-default btn-sm" onclick="zcResetFilter()">' . htmlspecialchars($lang->zoucha->filterReset) . '</button>';
$filterHTML .= '<span class="zc-summary">' . htmlspecialchars($lang->zoucha->totalFlagged) . '：<strong>' . $total . '</strong></span>';
$filterHTML .= '</div>';

/* ── 数据表 ── */
$tableHTML  = '<div class="zc-table-wrap"><table class="zc-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:48px">序号</th>';
$tableHTML .= '<th style="min-width:160px">' . htmlspecialchars($lang->zoucha->colProject) . '</th>';
$tableHTML .= '<th style="min-width:110px">' . htmlspecialchars($lang->zoucha->colProgram) . '</th>';
$tableHTML .= '<th style="width:90px">' . htmlspecialchars($lang->zoucha->colPM) . '</th>';
$tableHTML .= '<th style="width:80px">' . htmlspecialchars($lang->zoucha->colStatus) . '</th>';
$tableHTML .= '<th style="min-width:220px">' . htmlspecialchars($lang->zoucha->colHits) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colTaskCount) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colOverdue) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colExecCount) . '</th>';
$tableHTML .= '<th style="width:110px">' . htmlspecialchars($lang->zoucha->colLastEdited) . '</th>';
$tableHTML .= '</tr></thead><tbody>';

if(empty($results))
{
    $tableHTML .= '<tr><td colspan="10" class="zc-empty">' . htmlspecialchars($lang->zoucha->noData) . '</td></tr>';
}
else
{
    $i = ($pageID - 1) * $recPerPage + 1;
    foreach($results as $row)
    {
        $projectURL = helper::createLink('project', 'view', "projectID={$row->projectID}");
        $nameCell   = '<a href="' . $projectURL . '" target="_blank" class="zc-project-link">' . htmlspecialchars($row->projectName) . '</a>';

        /* 彩色标签 */
        $tags = '';
        foreach($row->hits as $h)
        {
            $color = isset($ruleColors[$h]) ? $ruleColors[$h] : '#888';
            $label = zget($ruleList, $h, $h);
            if($h === 'overdue' && $row->overdueCount > 0) $label .= ' ' . $row->overdueCount;
            $tags .= '<span class="zc-tag" style="background:' . htmlspecialchars($color) . '">' . htmlspecialchars($label) . '</span>';
        }

        /* 命中规则越多越醒目 */
        $rowClass = (count($row->hits) >= 3) ? ' class="zc-row-danger"' : '';

        $tableHTML .= '<tr' . $rowClass . '>';
        $tableHTML .= '<td class="td-center">' . $i++ . '</td>';
        $tableHTML .= '<td>' . $nameCell . '</td>';
        $tableHTML .= '<td>' . ($row->programName !== '' ? htmlspecialchars($row->programName) : '<span class="zc-na">-</span>') . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($row->pmName) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($row->statusName) . '</td>';
        $tableHTML .= '<td>' . $tags . '</td>';
        $tableHTML .= '<td class="td-center">' . (int)$row->taskCount . '</td>';
        $tableHTML .= '<td class="td-center' . ($row->overdueCount > 0 ? ' zc-num-danger' : '') . '">' . (int)$row->overdueCount . '</td>';
        $tableHTML .= '<td class="td-center">' . (int)$row->executionCount . '</td>';
        $tableHTML .= '<td class="td-center">' . ($row->lastTaskEdited !== null ? htmlspecialchars($row->lastTaskEdited) : '<span class="zc-na">-</span>') . '</td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody></table></div>';

/* ── 分页器 ── */
$buildPageURL = function($targetPage) use ($rule) {
    $ruleParam = ($rule === '') ? 'all' : $rule;
    return helper::createLink('zoucha', 'browse', "rule={$ruleParam}&pageID={$targetPage}");
};
$pagerHTML  = '<div class="zc-pager">';
$pagerHTML .= '<span class="zc-pager-info">共 ' . $total . ' 条，每页 ' . $recPerPage . ' 条，第 ' . $pageID . ' / ' . $pageTotal . ' 页</span>';
$pagerHTML .= '<div class="zc-pager-actions">';
if($pageID > 1)
{
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL(1) . '">首页</a>';
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageID - 1) . '">上一页</a>';
}
$startPage = max(1, $pageID - 2);
$endPage   = min($pageTotal, $pageID + 2);
for($p = $startPage; $p <= $endPage; $p++)
{
    if($p == $pageID) $pagerHTML .= '<span class="btn btn-primary btn-sm disabled">' . $p . '</span>';
    else              $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($p) . '">' . $p . '</a>';
}
if($pageID < $pageTotal)
{
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageID + 1) . '">下一页</a>';
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageTotal) . '">末页</a>';
}
$pagerHTML .= '</div></div>';

/* ── 渲染 ── */
panel
(
    setClass('zoucha-page'),
    html($filterHTML . $tableHTML . $pagerHTML)
);

/* ── 内联 CSS / JS ── */
$cssPath = $app->getAppRoot() . 'extension/custom/zoucha/css/zoucha.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/zoucha/js/zoucha.js';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
if(is_file($jsPath))  echo "<script>\n" . file_get_contents($jsPath)  . "\n</script>";
