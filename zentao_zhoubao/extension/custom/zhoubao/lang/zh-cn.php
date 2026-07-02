<?php
$lang->zhoubao = new stdclass();
$lang->zhoubao->common      = '项目周报';
$lang->zhoubao->browseTitle = '周报看板';
$lang->zhoubao->editTitle   = '填写周报';
$lang->zhoubao->viewTitle   = '查看周报';

$lang->zhoubao->project     = '项目';
$lang->zhoubao->pm          = '项目经理';
$lang->zhoubao->week        = '周次';
$lang->zhoubao->fillStatus  = '填报状态';
$lang->zhoubao->doneCount   = '本周完成';
$lang->zhoubao->overdueCount= '逾期任务';
$lang->zhoubao->actions     = '操作';

$lang->zhoubao->statusList  = array('none' => '缺交', 'draft' => '草稿', 'submitted' => '已交');

$lang->zhoubao->doneTasks   = '本周完成任务';
$lang->zhoubao->undoneTasks = '本周未完成/逾期任务';
$lang->zhoubao->statOverview= '进度/工时概览';
$lang->zhoubao->nextPlan    = '下周计划';
$lang->zhoubao->risk        = '风险与需协调资源';
$lang->zhoubao->summary     = '本周总结';

/* 走查提示标签：账号维护独立于 zoucha 插件的中文文案，避免依赖 zoucha 的 lang（单语言部署下不保证自动加载） */
$lang->zhoubao->zouchaRuleList = array(
    'noTask'      => '无任务',
    'stale'       => '近一周未更新',
    'overdue'     => '任务延期',
    'noExecution' => '无迭代',
    'longTask'    => '任务超期',
);
$lang->zhoubao->zouchaRuleDesc = array(
    'noTask'      => '项目下没有创建任何任务（已排除已关闭执行下的任务）。',
    'stale'       => '项目存在未关闭任务，但最近活动（创建或编辑）都早于 %d 天前，即超过 %d 天无人推进。',
    'overdue'     => '存在不少于 %d 个逾期任务（截止日期早于今天，且状态不属于已完成/已关闭/已取消/已挂起）。',
    'noExecution' => '项目下没有任何未删除的执行（迭代/阶段）。',
    'longTask'    => '存在计划工期超过 %d 天的任务。',
);
/* 与 zoucha 配置默认色一致的兜底色表：view 页读只读 snapshot、不会触发 zoucha model 加载，
   $this->config->zoucha 不一定可用，用这份自带默认色保证与 edit 页视觉一致 */
$lang->zhoubao->zouchaRuleColors = array(
    'noTask'      => '#e74c3c',
    'stale'       => '#e67e22',
    'overdue'     => '#c0392b',
    'noExecution' => '#8e44ad',
    'longTask'    => '#d35400',
);

$lang->zhoubao->saveDraft   = '保存草稿';
$lang->zhoubao->submit      = '提交';
$lang->zhoubao->copyLast    = '复制上周手写内容';
$lang->zhoubao->writeReport = '写周报';
$lang->zhoubao->viewReport  = '查看';
$lang->zhoubao->editReport  = '编辑';
$lang->zhoubao->pushNow     = '立即推送企微';
$lang->zhoubao->export      = '导出';

$lang->zhoubao->prevWeek    = '上一周';
$lang->zhoubao->thisWeek    = '本周';
$lang->zhoubao->nextWeek    = '下一周';
