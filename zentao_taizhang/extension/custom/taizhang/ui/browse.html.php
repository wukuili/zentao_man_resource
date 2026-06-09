<?php
declare(strict_types=1);
/**
 * 项目台账 — 列表页 ZIN 模板
 *
 * 红色规则：
 *   1. 当前预估利润率 <  $warnRate%   → 红色文字 (.tz-warn)
 *   2. 当前预估利润率 <= $dangerRate% → 红色背景 (.tz-danger)
 *   3. 已投入成本      >  初始预估成本 → 红色文字 (.tz-over)
 */
namespace zin;

$entries      = $this->view->entries;
$summary      = $this->view->summary;
$phase        = $this->view->phase;
$pm           = $this->view->pm;
$rdManager    = $this->view->rdManager;
$phaseOptions = $this->view->phaseOptions;
$pmOptions    = $this->view->pmOptions;
$rdOptions    = $this->view->rdOptions;
$browseURL    = $this->view->browseURL;
$warnRate     = (float)$this->view->warnRate;
$dangerRate   = (float)$this->view->dangerRate;

$editBase   = helper::createLink('taizhang', 'edit', 'id=');
$deleteBase = helper::createLink('taizhang', 'delete', 'id=');
$exportURL  = helper::createLink('taizhang', 'export', "phase={$phase}&pm={$pm}&rdManager={$rdManager}");

/* 把语言串和 URL 传给 JS */
jsVar('tzBrowseURL',       $browseURL);
jsVar('tzLang',            array('confirmDelete' => $lang->taizhang->confirmDelete));
jsVar('tzDeleteBaseURL',   $deleteBase);

css(<<<CSS
/* 覆盖 ZenTao body 内边距，让表格更宽 */
.taizhang-page .panel-body { padding: 0 !important; }
CSS);

/* ── 工具栏 ── */
toolbar
(
    item(set::text($lang->taizhang->addEntry),   set::icon('plus'),   set::type('primary'), set::url($editBase . '0')),
    item(set::text($lang->taizhang->exportEntry), set::icon('export'), set::type('ghost'),   set::url($exportURL)),
);

/* ── 筛选栏 + 数据表 ── */
$buildSelect = function($id, $name, $options, $current) {
    $html  = "<select id=\"{$id}\" name=\"{$name}\" class=\"form-control\" style=\"max-width:110px;padding:4px 8px;font-size:13px;\">";
    foreach($options as $val => $label) {
        $selected = ((string)$val === (string)$current) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars((string)$val) . "\"{$selected}>" . htmlspecialchars($label) . "</option>";
    }
    $html .= '</select>';
    return $html;
};

/* 筛选区 HTML */
$filterHTML  = '<div class="tz-filter-bar">';
$filterHTML .= '<label>项目阶段</label>' . $buildSelect('tzFilterPhase', 'phase', $phaseOptions, $phase);
$filterHTML .= '<label style="margin-left:10px">项目经理</label>' . $buildSelect('tzFilterPM', 'pm', $pmOptions, $pm);
$filterHTML .= '<label style="margin-left:10px">研发经理</label>' . $buildSelect('tzFilterRD', 'rdManager', $rdOptions, $rdManager);
$filterHTML .= '<div class="tz-filter-actions">';
$filterHTML .= '<button type="button" class="btn btn-primary btn-sm" onclick="tzSubmitFilter()" style="font-size:13px">查询</button>';
$filterHTML .= '<button type="button" class="btn btn-default btn-sm" onclick="tzResetFilter()" style="font-size:13px">重置</button>';
$filterHTML .= '</div></div>';

/* 表头 */
$thStyle = '';
$tableHTML  = '<div class="tz-table-wrap"><table class="tz-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:44px">序号</th>';
$tableHTML .= '<th style="min-width:90px">项目简称</th>';
$tableHTML .= '<th style="width:80px">项目阶段</th>';
$tableHTML .= '<th style="width:72px">项目经理</th>';
$tableHTML .= '<th style="width:72px">研发经理</th>';
$tableHTML .= '<th style="min-width:220px">当前项目情况</th>';
$tableHTML .= '<th style="width:70px">初始预估<br>人月</th>';
$tableHTML .= '<th style="width:85px">初始预估成本<br>(万元)</th>';
$tableHTML .= '<th style="width:70px">已投入<br>人月</th>';
$tableHTML .= '<th style="width:100px">已投入成本<br>(除外购和税)</th>';
$tableHTML .= '<th style="width:70px">当前预估<br>人月</th>';
$tableHTML .= '<th style="width:85px">当前预估成本<br>(万元)</th>';
$tableHTML .= '<th style="width:80px">当前预估<br>利润率(%)</th>';
$tableHTML .= '<th style="min-width:160px">近期项目成员</th>';
$tableHTML .= '<th style="width:84px">操作</th>';
$tableHTML .= '</tr></thead>';

/* 数据行 */
$tableHTML .= '<tbody>';
if(empty($entries))
{
    $tableHTML .= '<tr><td colspan="15" class="tz-empty">' . htmlspecialchars($lang->taizhang->noData) . '</td></tr>';
}
else
{
    $i = 1;
    foreach($entries as $entry)
    {
        $profitRate      = $entry->profitRate;
        $investedCost    = (float)$entry->investedCost;
        $initBudget      = (float)$entry->initBudget;
        $currentBudget   = (float)$entry->currentBudget;

        /* 利润率单元格样式 */
        $profitClass = 'td-num';
        $profitBg    = '';
        if($profitRate === null)
        {
            $profitClass .= ' tz-danger';
        }
        elseif($profitRate <= $dangerRate)
        {
            $profitClass .= ' tz-danger';
        }
        elseif($profitRate < $warnRate)
        {
            $profitClass .= ' tz-warn';
        }

        /* 已投入成本超出初始预估 */
        $investedClass = 'td-num';
        if($initBudget > 0 && $investedCost > $initBudget) $investedClass .= ' tz-over';

        $profitDisplay = ($profitRate !== null) ? $profitRate : $lang->taizhang->profitRateNA;

        $editURL   = $editBase   . $entry->id;
        $deleteURL = $deleteBase . $entry->id;

        $tableHTML .= '<tr>';
        $tableHTML .= '<td class="td-center td-serial">' . $i++ . '</td>';
        $tableHTML .= '<td>' . htmlspecialchars($entry->shortName ?: $entry->projectName) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->phase) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->pmName) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->rdManagerName) . '</td>';
        $tableHTML .= '<td class="td-status">' . nl2br(htmlspecialchars($entry->currentStatus)) . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->initEstHours . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->initBudget . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->investedHours . '</td>';
        $tableHTML .= '<td class="' . $investedClass . '">' . $investedCost . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->currentEstHours . '</td>';
        $tableHTML .= '<td class="td-num">' . $currentBudget . '</td>';
        $tableHTML .= '<td class="' . $profitClass . '">' . $profitDisplay . '</td>';
        $tableHTML .= '<td class="td-members">' . htmlspecialchars($entry->recentMembers) . '</td>';
        $tableHTML .= '<td class="td-center"><div class="tz-actions">';
        $tableHTML .= '<a href="' . $editURL . '" class="text-primary">编辑</a>';
        $tableHTML .= '<a href="javascript:void(0)" class="text-danger" onclick="tzDeleteEntry(' . (int)$entry->id . ', \'' . $deleteURL . '\')">删除</a>';
        $tableHTML .= '</div></td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody>';

/* 合计行 */
if(!empty($entries))
{
    $sProfitClass   = 'td-num';
    $sProfitDisplay = ($summary['profitRate'] !== null) ? $summary['profitRate'] : '-';
    if($summary['profitRate'] !== null && $summary['profitRate'] < $warnRate) $sProfitClass .= ' tz-warn';

    $tableHTML .= '<tfoot><tr>';
    $tableHTML .= '<td colspan="6" class="td-center"><strong>合计</strong></td>';
    $tableHTML .= '<td class="td-right">' . $summary['initEstHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['initBudget'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['investedHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['investedCost'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['currentEstHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['currentBudget'] . '</td>';
    $tableHTML .= '<td class="' . $sProfitClass . '">' . $sProfitDisplay . '</td>';
    $tableHTML .= '<td></td><td></td>';
    $tableHTML .= '</tr></tfoot>';
}

$tableHTML .= '</table></div>';

/* ── 渲染到 panel ── */
panel
(
    setClass('taizhang-page'),
    html($filterHTML . $tableHTML)
);

/* ── 内联 CSS / JS ──
 * extension/ 目录不在禅道 Web 根(www)下，无法作为静态资源直接 URL 访问，
 * 否则浏览器会拿到 404 的 HTML 页面而报 MIME 类型错误。
 * 这里服务端读取文件内容内联输出，文件仍是唯一来源。
 */
$cssPath = $app->getAppRoot() . 'extension/custom/taizhang/css/taizhang.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/taizhang/js/taizhang.js';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
if(is_file($jsPath))  echo "<script>\n" . file_get_contents($jsPath)  . "\n</script>";
