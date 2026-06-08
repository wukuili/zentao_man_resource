<?php
/**
 * 外包标识插件 — 中文语言包
 */

/* 模块通用 */
$lang->waibao = new stdclass();
$lang->waibao->common = '外包工时';

/* 菜单 */
$lang->waibao->menu = new stdclass();
$lang->waibao->menu->summary           = array('link' => '工时汇总|waibao|summary', 'alias' => '');
$lang->waibao->menu->browse            = array('link' => '人员标识|waibao|browse', 'alias' => '');
$lang->waibao->menu->orgdimension      = array('link' => '组织维度|waibao|orgdimension', 'alias' => '');
$lang->waibao->menu->projectdimension  = array('link' => '项目维度|waibao|projectdimension', 'alias' => '');
$lang->waibao->menu->memberdimension   = array('link' => '成员维度|waibao|memberdimension', 'alias' => '');

/* 页面标题 */
$lang->waibao->summary           = '外包工时汇总';
$lang->waibao->projectOverview   = '项目外包工时';
$lang->waibao->browse            = '人员外包标识管理';
$lang->waibao->orgdimension     = '组织维度工时统计';
$lang->waibao->projectdimension = '项目维度工时统计';
$lang->waibao->memberdimension  = '成员维度工时统计';

/* 汇总页 */
$lang->waibao->groupBy         = '分组维度';
$lang->waibao->groupByList     = array(
    'member'    => '按人员',
    'project'   => '按项目',
    'execution' => '按迭代',
    'dept'      => '按部门',
    'month'     => '按月份',
);
$lang->waibao->dimensionTitle  = array(
    'member'    => '外包人员',
    'project'   => '项目',
    'execution' => '迭代',
    'dept'      => '部门',
    'month'     => '月份',
);
$lang->waibao->filterBegin     = '开始日期';
$lang->waibao->filterEnd       = '结束日期';
$lang->waibao->filterExecution = '选择迭代';
$lang->waibao->allExecution    = '全部迭代';
$lang->waibao->percent         = '占比';
$lang->waibao->records         = '工时记录数';
$lang->waibao->totalConsumed   = '已消耗合计';
$lang->waibao->chartTitle      = '外包已消耗工时分布';
$lang->waibao->search          = '查询';
$lang->waibao->exportExcel     = '导出Excel';

/* 外包标识 */
$lang->waibao->outsourced       = '是否外包';
$lang->waibao->outsourcedList  = array(0 => '自有人员', 1 => '外包人员');
$lang->waibao->internal        = '自有人员';
$lang->waibao->outsourcedLabel = '外包人员';

/* 统计表头 */
$lang->waibao->department      = '部门';
$lang->waibao->project         = '项目';
$lang->waibao->member          = '成员';
$lang->waibao->account         = '账号';
$lang->waibao->realname        = '姓名';
$lang->waibao->role            = '角色';
$lang->waibao->totalHours      = '总工时';
$lang->waibao->estimatedHours  = '预计工时';
$lang->waibao->consumedHours   = '已消耗工时';
$lang->waibao->remainHours    = '剩余工时';
$lang->waibao->internalHours  = '自有人员工时';
$lang->waibao->outsourcedHours= '外包人员工时';
$lang->waibao->internalEstimated = '自有预计';
$lang->waibao->outsourcedEstimated= '外包预计';
$lang->waibao->internalConsumed  = '自有消耗';
$lang->waibao->outsourcedConsumed= '外包消耗';
$lang->waibao->internalRemain    = '自有剩余';
$lang->waibao->outsourcedRemain  = '外包剩余';
$lang->waibao->internalCount   = '自有人数';
$lang->waibao->outsourcedCount = '外包人数';
$lang->waibao->loadRate        = '负载率';
$lang->waibao->loadStatus     = '负载状态';
$lang->waibao->taskCount      = '任务数';
$lang->waibao->workingDays    = '工作日';
$lang->waibao->dailyHours     = '每日工时';

/* 筛选 */
$lang->waibao->filterDept      = '选择部门';
$lang->waibao->filterProject   = '选择项目';
$lang->waibao->filterMember    = '选择成员';
$lang->waibao->filterOutsourced= '外包标识';
$lang->waibao->filterDate      = '日期范围';
$lang->waibao->filterStatus    = '任务状态';
$lang->waibao->allDept         = '全部部门';
$lang->waibao->allProject      = '全部项目';
$lang->waibao->allMember       = '全部成员';
$lang->waibao->allOutsourced   = '全部';
$lang->waibao->statusTodo      = '未完成';
$lang->waibao->statusDone      = '已完成';

/* 操作 */
$lang->waibao->setOutsourced   = '设置外包标识';
$lang->waibao->batchSet        = '批量设置';
$lang->waibao->confirmSet      = '确认设置';
$lang->waibao->cancelSet       = '取消';
$lang->waibao->viewDetail      = '查看详情';
$lang->waibao->export          = '导出';

/* 提示信息 */
$lang->waibao->noData          = '暂无数据';
$lang->waibao->setSuccess      = '设置成功';
$lang->waibao->setFail         = '设置失败';
$lang->waibao->dateRequired    = '请选择日期范围';

/* 权限资源 */
$lang->resource->waibao = new stdclass();
$lang->resource->waibao->summary            = 'summary';
$lang->resource->waibao->projectOverview    = 'projectOverview';
$lang->resource->waibao->browse             = 'browse';
$lang->resource->waibao->orgdimension       = 'orgdimension';
$lang->resource->waibao->projectdimension   = 'projectdimension';
$lang->resource->waibao->memberdimension    = 'memberdimension';
$lang->resource->waibao->setUserOutsourced  = 'setUserOutsourced';
$lang->resource->waibao->batchSetOutsourced  = 'batchSetOutsourced';