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

/* 规则键 => 中文标签 */
$lang->zoucha->ruleList = array(
    'noTask'      => '无任务',
    'stale'       => '近一周未更新',
    'overdue'     => '任务延期',
    'noExecution' => '无迭代',
    'longTask'    => '任务超期',
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
