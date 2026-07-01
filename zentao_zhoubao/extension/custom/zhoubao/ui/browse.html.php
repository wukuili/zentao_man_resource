<?php
declare(strict_types=1);
/**
 * 项目周报 — 看板列表页 ZIN 模板
 *
 * 渲染：周切换 + PM/填报状态筛选栏 + 数据表（项目/PM/填报状态/完成数/逾期数/操作）。
 * 沿用 zoucha 列表页的约定：控制器已把数据挂到 $this->view->*，模板顶部取成局部变量再渲染；
 * 表格用手写 HTML（而非 dtable() 部件），避免依赖 dtable 的 config->fieldList/onRenderCell 机制
 * （本仓库没有可跑实例来验证 dtable 的运行时行为，手写表格与 zoucha 的真实、已评审实现一致，风险更低）。
 */
namespace zin;

$weekStart = (string)$this->view->weekStart;
$rows      = $this->view->rows;
$pm        = (string)$this->view->pm;
$fill      = (string)$this->view->fill;

$statusList = $lang->zhoubao->statusList;

$range    = $weekStart . ' ~ ' . date('Y-m-d', strtotime($weekStart . ' +6 days'));
$pmParam   = ($pm   === '') ? 'all' : $pm;
$fillParam = ($fill === '') ? 'all' : $fill;

/* 周切换：以本周一为基准前后移动 7 天，URL 中日期用下划线（禅道 PATH_INFO 路由约定） */
$prevWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' -7 days')));
$nextWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' +7 days')));
$curWeek  = str_replace('-', '_', $weekStart);

$prevURL = helper::createLink('zhoubao', 'browse', "week={$prevWeek}&pm={$pmParam}&fill={$fillParam}");
$curURL  = helper::createLink('zhoubao', 'browse', "week=&pm={$pmParam}&fill={$fillParam}");
$nextURL = helper::createLink('zhoubao', 'browse', "week={$nextWeek}&pm={$pmParam}&fill={$fillParam}");

/* PM 下拉：按当前行集合去重（仅列出本周活跃项目里出现过的 PM，与 zoucha 的 PM 筛选风格一致） */
$pmOptions = array();
foreach($rows as $r) if($r->pm !== '') $pmOptions[$r->pm] = $r->pm;

/* ── 筛选栏 ── */
$filterHTML  = '<div class="zb-filter-bar">';
$filterHTML .= '<span class="zb-week-nav">';
$filterHTML .= '<a class="btn btn-default btn-sm" href="' . $prevURL . '">' . htmlspecialchars($lang->zhoubao->prevWeek) . '</a> ';
$filterHTML .= '<a class="btn btn-default btn-sm" href="' . $curURL  . '">' . htmlspecialchars($lang->zhoubao->thisWeek) . '</a> ';
$filterHTML .= '<a class="btn btn-default btn-sm" href="' . $nextURL . '">' . htmlspecialchars($lang->zhoubao->nextWeek) . '</a>';
$filterHTML .= '</span>';

$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zhoubao->pm) . '</label>';
$filterHTML .= '<select id="zbFilterPM" class="form-control" style="max-width:160px">';
$filterHTML .= '<option value="all"' . ($pm === '' || $pm === 'all' ? ' selected' : '') . '>全部</option>';
foreach($pmOptions as $acc)
{
    $sel = ($pm === $acc) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars($acc) . '"' . $sel . '>' . htmlspecialchars($acc) . '</option>';
}
$filterHTML .= '</select>';

$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zhoubao->fillStatus) . '</label>';
$filterHTML .= '<select id="zbFilterFill" class="form-control" style="max-width:140px">';
$filterHTML .= '<option value="all"' . ($fill === '' || $fill === 'all' ? ' selected' : '') . '>全部</option>';
foreach($statusList as $key => $label)
{
    $sel = ($fill === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
}
$filterHTML .= '</select>';

$filterHTML .= '<button type="button" class="btn btn-primary btn-sm" onclick="zbSubmitFilter()" style="margin-left:8px">筛选</button>';
$filterHTML .= '<button type="button" class="btn btn-default btn-sm" onclick="zbResetFilter()">重置</button>';
$filterHTML .= '</div>';

/* ── 数据表 ── */
$tableHTML  = '<div class="zb-table-wrap"><table class="zb-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="min-width:160px">' . htmlspecialchars($lang->zhoubao->project) . '</th>';
$tableHTML .= '<th style="width:100px">' . htmlspecialchars($lang->zhoubao->pm) . '</th>';
$tableHTML .= '<th style="width:90px">' . htmlspecialchars($lang->zhoubao->fillStatus) . '</th>';
$tableHTML .= '<th style="width:90px">' . htmlspecialchars($lang->zhoubao->doneCount) . '</th>';
$tableHTML .= '<th style="width:90px">' . htmlspecialchars($lang->zhoubao->overdueCount) . '</th>';
$tableHTML .= '<th style="width:100px">' . htmlspecialchars($lang->zhoubao->actions) . '</th>';
$tableHTML .= '</tr></thead><tbody>';

if(empty($rows))
{
    $tableHTML .= '<tr><td colspan="6" class="zb-empty">本周暂无活跃项目周报数据。</td></tr>';
}
else
{
    foreach($rows as $r)
    {
        $projectURL = helper::createLink('project', 'view', "projectID={$r->project}");
        $nameCell   = '<a href="' . $projectURL . '" target="_blank" class="zb-project-link">' . htmlspecialchars($r->projectName) . '</a>';

        $statusLabel = zget($statusList, $r->status, $r->status);
        $statusClass = 'zb-status-' . htmlspecialchars((string)$r->status);

        if($r->status === 'submitted')
        {
            $actionURL  = helper::createLink('zhoubao', 'view', "id={$r->reportID}");
            $actionText = $lang->zhoubao->viewReport;
        }
        else
        {
            $actionURL  = helper::createLink('zhoubao', 'edit', "project={$r->project}&week={$curWeek}");
            $actionText = $lang->zhoubao->writeReport;
        }
        $actionCell = '<a class="btn btn-default btn-sm" href="' . $actionURL . '">' . htmlspecialchars($actionText) . '</a>';

        $tableHTML .= '<tr>';
        $tableHTML .= '<td>' . $nameCell . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars((string)$r->pm) . '</td>';
        $tableHTML .= '<td class="td-center"><span class="' . $statusClass . '">' . htmlspecialchars((string)$statusLabel) . '</span></td>';
        $tableHTML .= '<td class="td-center">' . (int)$r->doneCount . '</td>';
        $tableHTML .= '<td class="td-center' . ($r->overdueCount > 0 ? ' zb-num-danger' : '') . '">' . (int)$r->overdueCount . '</td>';
        $tableHTML .= '<td class="td-center">' . $actionCell . '</td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody></table></div>';

/* 筛选跳转：切换 PM/填报状态下拉后原地提交，周次维持当前 weekStart 不变 */
jsVar('window.zhoubaoFilterURL', helper::createLink('zhoubao', 'browse', "week={$curWeek}&pm=__PM__&fill=__FILL__"));

panel(
    set::title($lang->zhoubao->browseTitle . ' · ' . $range),
    html($filterHTML . $tableHTML)
);

/* ── 内联 JS：读取下拉值拼回筛选 URL 并跳转 ── */
echo <<<JS
<script>
(function()
{
    if(window.zbSubmitFilter) return; // 守卫：ZIN 脚本重复执行时不重复绑定
    window.zbSubmitFilter = function()
    {
        var pm   = document.getElementById('zbFilterPM').value;
        var fill = document.getElementById('zbFilterFill').value;
        var url  = window.zhoubaoFilterURL.replace('__PM__', encodeURIComponent(pm)).replace('__FILL__', encodeURIComponent(fill));
        window.location.href = url;
    };
    window.zbResetFilter = function()
    {
        var url = window.zhoubaoFilterURL.replace('__PM__', 'all').replace('__FILL__', 'all');
        window.location.href = url;
    };
})();
</script>
JS;
