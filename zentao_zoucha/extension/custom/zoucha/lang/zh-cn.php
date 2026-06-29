<?php
if(!isset($lang->zoucha)) $lang->zoucha = new stdclass();
$lang->zoucha->common = '项目走查';
$lang->zoucha->browse = '项目走查';

/* 子菜单 */
$lang->zoucha->menu = new stdclass();
$lang->zoucha->menu->browse = array('link' => '走查列表|zoucha|browse');

/* 权限资源定义 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->zoucha = new stdclass();
$lang->resource->zoucha->browse = 'browse';
$lang->resource->zoucha->export = 'export';
$lang->resource->zoucha->detail = 'detail';

/* 规则键 => 中文标签 */
$lang->zoucha->ruleList = array(
    'noTask'      => '无任务',
    'stale'       => '近一周未更新',
    'overdue'     => '任务延期',
    'noExecution' => '无迭代',
    'longTask'    => '任务超期',
);

/* 规则键 => 详细规则说明（鼠标悬停标签时提示）。
 * 含 %d 的为可配置阈值占位符，由 browse.html.php 用 config 实际值填充。
 * 统计口径：均已排除已删除任务，以及"已关闭执行（迭代/阶段）"下的任务。 */
$lang->zoucha->ruleDesc = array(
    'noTask'      => "项目下没有创建任何任务。\n（已排除“已关闭执行”下的任务）",
    'stale'       => "项目存在未关闭任务，但全部未关闭任务的最近活动（创建或编辑）都早于 %d 天前，即超过 %d 天无人推进。\n阈值：staleDays = %d 天。",
    'overdue'     => "存在不少于 %d 个逾期任务。\n逾期＝任务截止日期早于今天，且任务状态不属于 已完成 / 已关闭 / 已取消 / 已挂起。\n阈值：overdueMin = %d 个。",
    'noExecution' => "项目下没有任何未删除的执行（迭代 / 阶段）。",
    'longTask'    => "存在计划工期超过 %d 天的任务。\n计划工期＝任务截止日期 − 预计开始日期，且任务未完成 / 关闭 / 取消。\n阈值：longTaskDays = %d 天。",
);

/* 表头 */
$lang->zoucha->colProject   = '项目';
$lang->zoucha->colProgram   = '所属项目集';
$lang->zoucha->colPM        = '负责人';
$lang->zoucha->colStatus    = '项目状态';
$lang->zoucha->colHits      = '走查结果';
$lang->zoucha->colTaskCount = '任务数';
$lang->zoucha->colOverdue   = '逾期数';
$lang->zoucha->colExecCount = '执行数';
$lang->zoucha->colLastEdited = '最后任务更新';

/* 按钮与提示 */
$lang->zoucha->filterRule   = '问题类型';
$lang->zoucha->filterPM     = '项目经理';
$lang->zoucha->pmNone       = '未指派';
$lang->zoucha->filterAll    = '全部';
$lang->zoucha->filterSearch = '查询';
$lang->zoucha->filterReset  = '重置';
$lang->zoucha->exportEntry  = '导出Excel';
$lang->zoucha->noData       = '太棒了！当前没有命中任何走查规则的项目。';
$lang->zoucha->totalFlagged = '问题项目数';

/* 项目状态中文名 */
$lang->zoucha->projectStatusList = array(
    'wait'      => '未开始',
    'doing'     => '进行中',
    'suspended' => '挂起',
    'closed'    => '关闭',
);
