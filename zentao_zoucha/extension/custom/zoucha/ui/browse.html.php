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
$pm         = (string)$this->view->pm;
$ruleCounts = $this->view->ruleCounts;
$pmCounts   = $this->view->pmCounts;
$pmNames    = $this->view->pmNames;
$pageID     = (int)$this->view->pageID;
$pageTotal  = (int)$this->view->pageTotal;
$recPerPage = (int)$this->view->recPerPage;
$total      = (int)$this->view->total;
$totalAll   = (int)$this->view->totalAll;

$ruleList   = $lang->zoucha->ruleList;
$ruleColors = $this->config->zoucha->ruleColors;

/* 规则详细说明：用 config 实际阈值填充 lang 模板中的 %d 占位符，用于标签悬停提示 */
$cfg      = $this->config->zoucha;
$ruleDesc = $lang->zoucha->ruleDesc;
$descMap  = array(
    'noTask'      => $ruleDesc['noTask'],
    'stale'       => sprintf($ruleDesc['stale'], (int)$cfg->staleDays, (int)$cfg->staleDays, (int)$cfg->staleDays),
    'overdue'     => sprintf($ruleDesc['overdue'], max(1, (int)$cfg->overdueMin), max(1, (int)$cfg->overdueMin)),
    'noExecution' => $ruleDesc['noExecution'],
    'longTask'    => sprintf($ruleDesc['longTask'], (int)$cfg->longTaskDays, (int)$cfg->longTaskDays),
);

$ruleParam = ($rule === '') ? 'all' : $rule;
$pmParam   = ($pm   === '') ? 'all' : $pm;
$exportURL = helper::createLink('zoucha', 'export', "rule={$ruleParam}&pm={$pmParam}");
/* 筛选跳转 URL 模板（用 __RULE__ / __PM__ 占位，JS 替换为实际规则键 / PM 账号）。
 * 用 createLink 带参数生成，禅道 PATH_INFO 模式下路由正确解析位置参数，不依赖 $_GET。 */
jsVar('window.zouchaFilterURL', helper::createLink('zoucha', 'browse', "rule=__RULE__&pm=__PM__&pageID=1"));
/* 明细弹框 URL 模板（__PID__/__RULE__ 占位，JS 替换） */
jsVar('window.zouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));

/* 工具栏 */
toolbar
(
    item(set::text($lang->zoucha->exportEntry), set::icon('export'), set::type('ghost'), set::url($exportURL)),
);

/* ── 筛选栏 HTML ── */
$filterHTML  = '<div class="zc-filter-bar">';
$filterHTML .= '<label>' . htmlspecialchars($lang->zoucha->filterRule) . '</label>';
$filterHTML .= '<select id="zcFilterRule" class="form-control" style="max-width:180px">';
$filterHTML .= '<option value="all"' . ($rule === '' ? ' selected' : '') . '>' . htmlspecialchars($lang->zoucha->filterAll) . '（' . $totalAll . '）</option>';
foreach($ruleList as $key => $label)
{
    if(!in_array($key, $this->config->zoucha->rules, true)) continue;
    $cnt = isset($ruleCounts[$key]) ? (int)$ruleCounts[$key] : 0;
    $sel = ($rule === $key) ? ' selected' : '';
    /* 原生 title 提示：取规则说明，换行压成一行更适合 title 展示 */
    $optTip = isset($descMap[$key]) ? ' title="' . htmlspecialchars(str_replace("\n", '　', $descMap[$key])) . '"' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . $optTip . '>' . htmlspecialchars($label) . '（' . $cnt . '）</option>';
}
$filterHTML .= '</select>';

/* 项目经理筛选：选项展示「姓名（问题项目数）」，与问题类型筛选保持一致 */
$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zoucha->filterPM) . '</label>';
$filterHTML .= '<select id="zcFilterPM" class="form-control" style="max-width:180px">';
$filterHTML .= '<option value="all"' . ($pm === '' ? ' selected' : '') . '>' . htmlspecialchars($lang->zoucha->filterAll) . '（' . $totalAll . '）</option>';
foreach($pmCounts as $acc => $cnt)
{
    $acc  = (string)$acc;
    $val  = ($acc === '') ? '__none__' : $acc;
    $name = ($acc === '')
        ? $lang->zoucha->pmNone
        : ((isset($pmNames[$acc]) && $pmNames[$acc] !== '') ? $pmNames[$acc] : $acc);
    $sel  = ($pm === $val) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($name) . '（' . (int)$cnt . '）</option>';
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
            $name  = zget($ruleList, $h, $h);
            if($h === 'overdue' && $row->overdueCount > 0) $label .= ' ' . $row->overdueCount;
            /* data-tip：规则名 + 详细说明，JS 悬停时弹出浮层（white-space:pre-line 保留换行）
             * data-pid/data-rule：点击时拉取该项目该规则的明细弹框 */
            $tip      = $name . "\n" . (isset($descMap[$h]) ? $descMap[$h] : '');
            $clickable = in_array($h, array('overdue', 'longTask', 'stale'), true) ? ' zc-tag-clickable' : '';
            $tags .= '<span class="zc-tag' . $clickable . '" data-tip="' . htmlspecialchars($tip)
                . '" data-pid="' . (int)$row->projectID . '" data-rule="' . htmlspecialchars($h)
                . '" data-label="' . htmlspecialchars($name) . '" style="background:' . htmlspecialchars($color) . '">'
                . htmlspecialchars($label) . '</span>';
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
$buildPageURL = function($targetPage) use ($ruleParam, $pmParam) {
    return helper::createLink('zoucha', 'browse', "rule={$ruleParam}&pm={$pmParam}&pageID={$targetPage}");
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
