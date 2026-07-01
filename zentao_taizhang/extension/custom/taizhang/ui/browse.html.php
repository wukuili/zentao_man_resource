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
$totalSummary = $this->view->totalSummary;
$phase        = $this->view->phase;
$pm           = $this->view->pm;
$projectStatus = $this->view->projectStatus;
$category     = $this->view->category;
$statusOptions = $this->view->statusOptions;
$categoryOptions = $this->view->categoryOptions;
$pmOptions    = $this->view->pmOptions;
$pageID       = (int)$this->view->pageID;
$pageTotal    = (int)$this->view->pageTotal;
$recPerPage   = (int)$this->view->recPerPage;
$total        = (int)$this->view->total;
$browseURL    = $this->view->browseURL;
$warnRate     = (float)$this->view->warnRate;
$dangerRate   = (float)$this->view->dangerRate;

$addURL     = helper::createLink('taizhang', 'edit', 'id=0');
$exportURL  = helper::createLink('taizhang', 'export', "phase={$phase}&pm={$pm}&projectStatus={$projectStatus}&category={$category}");

/* 把语言串和 URL 传给 JS */
jsVar('tzBrowseURL',       $browseURL);
jsVar('tzLang',            array('confirmDelete' => $lang->taizhang->confirmDelete));

css(<<<CSS
/* 覆盖 ZenTao body 内边距，让表格更宽 */
.taizhang-page .panel-body { padding: 0 !important; }
.taizhang-page { overflow-x: auto; }
CSS);

/* ── 工具栏 ── */
toolbar
(
    item(set::text($lang->taizhang->addEntry),   set::icon('plus'),   set::type('primary'), set::url($addURL)),
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

/* 文本/日期类字段为空时显示占位符 */
$fmtPlain = function($val) {
    $val = (string)$val;
    return ($val !== '') ? htmlspecialchars($val) : '<span class="tz-na">-</span>';
};

/* 分包合同状态/开工资料是否齐全/安保措施是否到位/是否涉及危险作业仅施工类、集成类项目适用，
 * 软件类项目统一显示 "-"（与编辑表单中这几项仅施工/集成类可见保持一致） */
$fmtConstructionOnly = function($val, $category) use ($fmtPlain) {
    if($category === '软件类') return '<span class="tz-na">-</span>';
    return $fmtPlain($val);
};

/* 详情抽屉：单条 label/value 行（value 已是格式化后的 HTML） */
$dRow = function($label, $value) {
    return '<div class="tz-d-row"><div class="tz-d-label">' . htmlspecialchars($label) . '</div><div class="tz-d-value">' . $value . '</div></div>';
};
/* 详情抽屉：分组小节 */
$dSection = function($title, $rowsHtml) {
    return '<div class="tz-d-section"><div class="tz-d-section-title">' . htmlspecialchars($title) . '</div>' . $rowsHtml . '</div>';
};

/* 筛选区 HTML */
$filterHTML  = '<div class="tz-filter-bar">';
$filterHTML .= '<label>项目类别</label>' . $buildSelect('tzFilterCategory', 'category', $categoryOptions, $category);
$filterHTML .= '<label style="margin-left:10px">项目经理</label>' . $buildSelect('tzFilterPM', 'pm', $pmOptions, $pm);
$filterHTML .= '<label style="margin-left:10px">项目状态</label>' . $buildSelect('tzFilterStatus', 'projectStatus', $statusOptions, $projectStatus);
$filterHTML .= '<div class="tz-filter-actions">';
$filterHTML .= '<button type="button" class="btn btn-primary btn-sm" onclick="tzSubmitFilter()" style="font-size:13px">查询</button>';
$filterHTML .= '<button type="button" class="btn btn-default btn-sm" onclick="tzResetFilter()" style="font-size:13px">重置</button>';
$filterHTML .= '</div></div>';

/* 统计区 HTML（汇总当前筛选条件下的全部数据，不受分页影响） */
$statCard = function($label, $value, $extraClass = '') {
    return '<div class="tz-stat-card ' . $extraClass . '"><div class="tz-stat-label">' . htmlspecialchars($label) . '</div><div class="tz-stat-value">' . $value . '</div></div>';
};
$tsProfitDisplay = ($totalSummary['profitRate'] !== null) ? $totalSummary['profitRate'] . '%' : $lang->taizhang->profitRateNA;
$tsProfitClass   = '';
if($totalSummary['profitRate'] === null || $totalSummary['profitRate'] <= $dangerRate) $tsProfitClass = 'tz-stat-danger';
elseif($totalSummary['profitRate'] < $warnRate) $tsProfitClass = 'tz-stat-warn';

$statsHTML  = '<div class="tz-stats-bar">';
$statsHTML .= $statCard('项目总数', $total);
$statsHTML .= $statCard('初始预估人天', $totalSummary['initEstHours']);
$statsHTML .= $statCard('初始预估成本(万元)', $totalSummary['initBudget']);
$statsHTML .= $statCard('已投入人天', $totalSummary['investedHours']);
$statsHTML .= $statCard('已投入成本(万元)', $totalSummary['investedCost']);
$statsHTML .= $statCard('当前预估人天', $totalSummary['currentEstHours']);
$statsHTML .= $statCard('当前预估成本(万元)', $totalSummary['currentBudget']);
$statsHTML .= $statCard('合同金额(万元)', $totalSummary['revenue']);
$statsHTML .= $statCard('外采金额(万元)', $totalSummary['outsourcingAmount']);
$statsHTML .= $statCard('回款金额(万元)', $totalSummary['receivedAmount']);
$statsHTML .= $statCard('整体预估利润率', $tsProfitDisplay, $tsProfitClass);
$statsHTML .= '</div>';

/* 表头 */
$thStyle = '';
$tableHTML  = '<div class="tz-table-wrap"><table class="tz-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:44px">序号</th>';
$tableHTML .= '<th style="min-width:90px">项目集</th>';
$tableHTML .= '<th style="min-width:90px">项目简称</th>';
$tableHTML .= '<th style="width:80px">项目阶段</th>';
$tableHTML .= '<th style="width:80px">项目类别</th>';
$tableHTML .= '<th style="width:72px">项目经理</th>';
$tableHTML .= '<th style="width:72px">项目状态</th>';
$tableHTML .= '<th style="min-width:220px">当前项目情况</th>';
$tableHTML .= '<th style="min-width:160px">项目简介</th>';
$tableHTML .= '<th style="width:90px">合同签署<br>时间</th>';
$tableHTML .= '<th style="width:90px">计划开工<br>日期</th>';
$tableHTML .= '<th style="width:90px">实际开工<br>日期</th>';
$tableHTML .= '<th style="width:90px">计划完工<br>日期</th>';
$tableHTML .= '<th style="width:90px">实际完工<br>日期</th>';
$tableHTML .= '<th style="min-width:100px">验收情况</th>';
$tableHTML .= '<th style="width:70px">初始预估<br>人天</th>';
$tableHTML .= '<th style="width:85px">初始预估成本<br>(万元)</th>';
$tableHTML .= '<th style="width:70px">已投入<br>人天</th>';
$tableHTML .= '<th style="width:100px">已投入成本<br>(除外购和税/万元)</th>';
$tableHTML .= '<th style="width:70px">当前预估<br>人天</th>';
$tableHTML .= '<th style="width:85px">当前预估成本<br>(万元)</th>';
$tableHTML .= '<th style="width:85px">合同金额<br>(万元)</th>';
$tableHTML .= '<th style="width:85px">外采金额<br>(万元)</th>';
$tableHTML .= '<th style="width:85px">回款金额<br>(万元)</th>';
$tableHTML .= '<th style="width:80px">当前预估<br>利润率(%)</th>';
$tableHTML .= '<th style="min-width:140px">进度偏差说明</th>';
$tableHTML .= '<th style="min-width:120px">备注</th>';
$tableHTML .= '<th style="min-width:160px">近期项目成员</th>';
$tableHTML .= '<th class="tz-col-action" style="width:84px">操作</th>';
$tableHTML .= '</tr></thead>';

/* 数据行 */
$detailStore = '';   /* 各行详情内容，隐藏存放，供右侧抽屉展示 */
$tableHTML .= '<tbody>';
if(empty($entries))
{
    $tableHTML .= '<tr><td colspan="29" class="tz-empty">' . htmlspecialchars($lang->taizhang->noData) . '</td></tr>';
}
else
{
    /* 项目集列纵向合并：统计本页内同项目集连续行数（entries 已在模型层按项目集排序） */
    $groupSpans = array();
    $prevKey = null;
    $anchor  = 0;
    $idx     = 0;
    foreach($entries as $entry)
    {
        $key = (string)$entry->programName;
        if($key !== $prevKey)
        {
            $anchor = $idx;
            $groupSpans[$anchor] = 1;
            $prevKey = $key;
        }
        else
        {
            $groupSpans[$anchor]++;
        }
        $idx++;
    }

    $i   = 1;
    $idx = 0;
    foreach($entries as $entry)
    {
        $profitRate      = $entry->profitRate;
        $investedCost    = (float)$entry->investedCost;
        $initBudget      = (float)$entry->initBudget;
        $currentBudget   = (float)$entry->currentBudget;
        $revenue         = (float)$entry->revenue;
        $outsourcing     = (float)$entry->outsourcingAmount;
        $received        = (float)$entry->receivedAmount;

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

        $editURL   = helper::createLink('taizhang', 'edit',   "id={$entry->id}");
        $deleteURL = helper::createLink('taizhang', 'delete', "id={$entry->id}");

        $isGroupStart = isset($groupSpans[$idx]);
        $tableHTML .= '<tr' . ($isGroupStart ? ' class="tz-group-start"' : '') . '>';
        $tableHTML .= '<td class="td-center td-serial">' . $i++ . '</td>';
        if($isGroupStart)
        {
            $tableHTML .= '<td class="td-program" rowspan="' . $groupSpans[$idx] . '">' . ($entry->programName !== '' ? htmlspecialchars($entry->programName) : '<span class="tz-na">-</span>') . '</td>';
        }
        $idx++;
        /* 项目简称：同步行（projectID>0）做成超链接，点击跳转到禅道项目详情页；手动行无项目，纯文本 */
        $displayName = htmlspecialchars($entry->shortName ?: $entry->projectName);
        if((int)$entry->projectID > 0)
        {
            $projectURL = helper::createLink('project', 'view', "projectID={$entry->projectID}");
            $nameCell   = '<a href="' . $projectURL . '" target="_blank" class="tz-project-link">' . $displayName . '</a>';
        }
        else
        {
            $nameCell = $displayName;
        }
        $tableHTML .= '<td>' . $nameCell . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->phase) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->projectCategory) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->pmName) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($entry->projectStatusName) . '</td>';
        $tableHTML .= '<td class="td-status">' . nl2br(htmlspecialchars($entry->currentStatus)) . '</td>';
        $tableHTML .= '<td class="td-status">' . nl2br($fmtPlain($entry->projectIntro)) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->contractSignDate) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->planStartDate) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->actualStartDate) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->planEndDate) . '</td>';
        $tableHTML .= '<td class="td-center">' . $fmtPlain($entry->actualEndDate) . '</td>';
        $tableHTML .= '<td>' . $fmtPlain($entry->acceptanceStatus) . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->initEstHours . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->initBudget . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->investedHours . '</td>';
        $tableHTML .= '<td class="' . $investedClass . '">' . $investedCost . '</td>';
        $tableHTML .= '<td class="td-num">' . $entry->currentEstHours . '</td>';
        $tableHTML .= '<td class="td-num">' . $currentBudget . '</td>';
        $tableHTML .= '<td class="td-num">' . $revenue . '</td>';
        $tableHTML .= '<td class="td-num">' . $outsourcing . '</td>';
        $tableHTML .= '<td class="td-num">' . $received . '</td>';
        $tableHTML .= '<td class="' . $profitClass . '">' . $profitDisplay . '</td>';
        $tableHTML .= '<td class="td-status">' . nl2br($fmtPlain($entry->progressDeviation)) . '</td>';
        $tableHTML .= '<td class="td-status">' . nl2br($fmtPlain($entry->remark)) . '</td>';
        $tableHTML .= '<td class="td-members">' . htmlspecialchars($entry->recentMembers) . '</td>';
        $tableHTML .= '<td class="td-center tz-col-action"><div class="tz-actions">';
        $tableHTML .= '<a href="javascript:void(0)" class="text-info" onclick="tzShowDetail(' . (int)$entry->id . ')">详情</a>';
        $tableHTML .= '<a href="' . $editURL . '" class="text-primary">编辑</a>';
        $tableHTML .= '<a href="javascript:void(0)" class="text-danger" onclick="tzDeleteEntry(' . (int)$entry->id . ', \'' . $deleteURL . '\')">删除</a>';
        $tableHTML .= '</div></td>';
        $tableHTML .= '</tr>';

        /* ── 构建该行的详情内容，隐藏存放，供右侧抽屉展示 ── */
        $detailProfit = ($profitRate !== null) ? ($profitRate . '%') : $lang->taizhang->profitRateNA;
        $detailProfitClass = '';
        if($profitRate === null || $profitRate <= $dangerRate) $detailProfitClass = 'tz-warn';
        elseif($profitRate < $warnRate) $detailProfitClass = 'tz-warn';

        $secBase  = $dRow('项目集', $entry->programName !== '' ? htmlspecialchars($entry->programName) : '<span class="tz-na">-</span>');
        $secBase .= $dRow('项目简称', $nameCell);
        $secBase .= $dRow('项目阶段', htmlspecialchars($entry->phase));
        $secBase .= $dRow('项目类别', $fmtPlain($entry->projectCategory));
        $secBase .= $dRow('项目经理', htmlspecialchars($entry->pmName));
        $secBase .= $dRow('项目状态', htmlspecialchars($entry->projectStatusName));

        $secDesc  = $dRow('当前项目情况', nl2br($fmtPlain($entry->currentStatus)));
        $secDesc .= $dRow('项目简介', nl2br($fmtPlain($entry->projectIntro)));
        $secDesc .= $dRow('进度偏差说明', nl2br($fmtPlain($entry->progressDeviation)));
        $secDesc .= $dRow('备注', nl2br($fmtPlain($entry->remark)));
        $secDesc .= $dRow('近期项目成员', $fmtPlain($entry->recentMembers));

        $secEng  = $dRow('合同签署时间', $fmtPlain($entry->contractSignDate));
        $secEng .= $dRow('分包合同状态', $fmtConstructionOnly($entry->subcontractStatus, $entry->projectCategory));
        $secEng .= $dRow('工程状态', $fmtPlain($entry->engineeringStatus));
        $secEng .= $dRow('采购方式', $fmtPlain($entry->procurementMethod));
        $secEng .= $dRow('供货单位', $fmtPlain($entry->supplyUnit));
        $secEng .= $dRow('安装/施工单位', $fmtPlain($entry->constructionUnit));
        $secEng .= $dRow('验收情况', $fmtPlain($entry->acceptanceStatus));
        $secEng .= $dRow('安保措施是否到位', $fmtConstructionOnly($entry->securityMeasures, $entry->projectCategory));
        $secEng .= $dRow('是否涉及危险作业', $fmtConstructionOnly($entry->hazardousWork, $entry->projectCategory));
        $secEng .= $dRow('开工资料是否齐全', $fmtConstructionOnly($entry->startDocsComplete, $entry->projectCategory));

        $secDate  = $dRow('计划开工日期', $fmtPlain($entry->planStartDate));
        $secDate .= $dRow('实际开工日期', $fmtPlain($entry->actualStartDate));
        $secDate .= $dRow('计划完工日期', $fmtPlain($entry->planEndDate));
        $secDate .= $dRow('实际完工日期', $fmtPlain($entry->actualEndDate));

        $overTag  = ($initBudget > 0 && $investedCost > $initBudget) ? '<span class="tz-warn">' . $investedCost . '</span>' : (string)$investedCost;
        $secCost  = $dRow('初始预估人天', (string)$entry->initEstHours);
        $secCost .= $dRow('初始预估成本(万元)', (string)$entry->initBudget);
        $secCost .= $dRow('已投入人天', (string)$entry->investedHours);
        $secCost .= $dRow('已投入成本(除外购和税/万元)', $overTag);
        $secCost .= $dRow('当前预估人天', (string)$entry->currentEstHours);
        $secCost .= $dRow('当前预估成本(万元)', (string)$currentBudget);
        $secCost .= $dRow('合同金额(万元)', (string)$revenue);
        $secCost .= $dRow('外采金额(万元)', (string)$outsourcing);
        $secCost .= $dRow('回款金额(万元)', (string)$received);
        $secCost .= $dRow('当前预估利润率', '<span class="' . $detailProfitClass . '">' . $detailProfit . '</span>');

        $detailTitle = htmlspecialchars($entry->shortName ?: $entry->projectName);
        $detailStore .= '<div class="tz-detail-body" data-detail-id="' . (int)$entry->id . '" data-detail-title="' . $detailTitle . '">'
            . $dSection('基本信息', $secBase)
            . $dSection('项目情况', $secDesc)
            . $dSection('合同与工程', $secEng)
            . $dSection('关键日期', $secDate)
            . $dSection('成本与利润', $secCost)
            . '</div>';
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
    $tableHTML .= '<td colspan="15" class="td-center"><strong>合计</strong></td>';
    $tableHTML .= '<td class="td-right">' . $summary['initEstHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['initBudget'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['investedHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['investedCost'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['currentEstHours'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['currentBudget'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['revenue'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['outsourcingAmount'] . '</td>';
    $tableHTML .= '<td class="td-right">' . $summary['receivedAmount'] . '</td>';
    $tableHTML .= '<td class="' . $sProfitClass . '">' . $sProfitDisplay . '</td>';
    $tableHTML .= '<td></td><td></td><td></td><td class="tz-col-action"></td>';
    $tableHTML .= '</tr></tfoot>';
}

$tableHTML .= '</table></div>';

/* ── 右侧详情抽屉（默认隐藏，点击"详情"滑出） + 隐藏的详情数据仓库 ── */
$drawerHTML  = '<div id="tzDetailStore" style="display:none">' . $detailStore . '</div>';
$drawerHTML .= '<div id="tzDetailOverlay" class="tz-detail-overlay" onclick="tzCloseDetail()"></div>';
$drawerHTML .= '<div id="tzDetailDrawer" class="tz-detail-drawer">';
$drawerHTML .= '<div class="tz-detail-header"><span id="tzDetailTitle">项目详情</span>'
    . '<a href="javascript:void(0)" class="tz-detail-close" onclick="tzCloseDetail()" title="关闭">&times;</a></div>';
$drawerHTML .= '<div id="tzDetailContent" class="tz-detail-content"></div>';
$drawerHTML .= '</div>';

$buildPageURL = function($targetPage) use ($phase, $pm, $projectStatus, $category) {
    /* 「全部」状态用哨兵值 all，避免控制器把空值误判为首次进入而强制改回 doing */
    $statusParam = ($projectStatus === '') ? 'all' : $projectStatus;
    /* 注意：taizhang::browse() 按位置映射形参 ($phase,$pm,$projectStatus,$pageID,$category)，
     * category 必须排在 pageID 之后，否则会被错位映射到 pageID 形参。 */
    return helper::createLink('taizhang', 'browse', "phase={$phase}&pm={$pm}&projectStatus={$statusParam}&pageID={$targetPage}&category={$category}");
};

$pagerHTML  = '<div class="tz-pager">';
$pagerHTML .= '<span class="tz-pager-info">共 ' . $total . ' 条，每页 ' . $recPerPage . ' 条，第 ' . $pageID . ' / ' . $pageTotal . ' 页</span>';
$pagerHTML .= '<div class="tz-pager-actions">';
if($pageID > 1)
{
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL(1) . '">首页</a>';
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageID - 1) . '">上一页</a>';
}
else
{
    $pagerHTML .= '<span class="btn btn-default btn-sm disabled">首页</span>';
    $pagerHTML .= '<span class="btn btn-default btn-sm disabled">上一页</span>';
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
else
{
    $pagerHTML .= '<span class="btn btn-default btn-sm disabled">下一页</span>';
    $pagerHTML .= '<span class="btn btn-default btn-sm disabled">末页</span>';
}
$pagerHTML .= '</div></div>';

/* ── 渲染到 panel ── */
panel
(
    setClass('taizhang-page'),
    html($filterHTML . $statsHTML . $tableHTML . $pagerHTML . $drawerHTML)
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
