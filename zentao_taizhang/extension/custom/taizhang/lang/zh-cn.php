<?php
if(!isset($lang->taizhang)) $lang->taizhang = new stdclass();
$lang->taizhang->common = '项目台账';
$lang->taizhang->browse = '项目台账';

/* 子菜单 */
$lang->taizhang->menu = new stdclass();
$lang->taizhang->menu->browse = array('link' => '台账列表|taizhang|browse');

/* 权限资源定义 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->taizhang = new stdclass();
$lang->resource->taizhang->browse = 'browse';
$lang->resource->taizhang->edit   = 'edit';
$lang->resource->taizhang->delete = 'delete';
$lang->resource->taizhang->export = 'export';

/* 表格字段标题 */
$lang->taizhang->serialNo        = '序号';
$lang->taizhang->projectName     = '项目简称';
$lang->taizhang->phase           = '项目阶段';
$lang->taizhang->projectCategory = '项目类别';
$lang->taizhang->pm              = '项目经理';
$lang->taizhang->projectStatus   = '项目状态';
$lang->taizhang->currentStatus   = '当前项目情况';
$lang->taizhang->projectIntro    = '项目简介';
$lang->taizhang->contractSignDate  = '合同签署时间';
$lang->taizhang->subcontractStatus = '分包合同状态';
$lang->taizhang->engineeringStatus = '工程状态';
$lang->taizhang->procurementMethod = '采购方式';
$lang->taizhang->supplyUnit        = '供货单位';
$lang->taizhang->constructionUnit  = '安装/施工单位';
$lang->taizhang->planStartDate     = '计划开工日期';
$lang->taizhang->actualStartDate   = '实际开工日期';
$lang->taizhang->planEndDate       = '计划完工日期';
$lang->taizhang->actualEndDate     = '实际完工日期';
$lang->taizhang->acceptanceStatus  = '验收情况';
$lang->taizhang->securityMeasures  = '安保措施是否到位';
$lang->taizhang->hazardousWork     = '是否涉及危险作业';
$lang->taizhang->startDocsComplete = '开工资料是否齐全';
$lang->taizhang->progressDeviation = '进度偏差说明';
$lang->taizhang->remark            = '备注';
$lang->taizhang->initEstHours    = '初始预估人天';
$lang->taizhang->initBudget      = '初始预估成本(万元)';
$lang->taizhang->investedHours   = '已投入人天';
$lang->taizhang->investedCost    = '已投入成本(除外购和税/万元)';
$lang->taizhang->currentEstHours = '当前预估人天';
$lang->taizhang->currentBudget   = '当前预估成本(万元)';
$lang->taizhang->profitRate      = '当前预估利润率(%)';
$lang->taizhang->recentMembers   = '近期项目成员';
$lang->taizhang->revenue         = '合同金额(万元)';
$lang->taizhang->outsourcingAmount = '外采金额(万元)';
$lang->taizhang->receivedAmount  = '回款金额(万元)';
$lang->taizhang->actions         = '操作';
$lang->taizhang->sortOrder       = '排序';

/* 表单标签 */
$lang->taizhang->projectID       = '关联项目';
$lang->taizhang->shortName       = '项目简称';
$lang->taizhang->selectProject   = '-- 请选择项目 --';

/* 按钮 & 消息 */
$lang->taizhang->addEntry       = '新增台账';
$lang->taizhang->editEntry      = '编辑台账';
$lang->taizhang->saveEntry      = '保存';
$lang->taizhang->deleteEntry    = '删除';
$lang->taizhang->exportEntry    = '导出Excel';
$lang->taizhang->cancelEntry    = '取消';
$lang->taizhang->confirmDelete  = '确认删除该台账记录？删除后无法恢复。';
$lang->taizhang->saveSuccess    = '保存成功';
$lang->taizhang->saveFail       = '保存失败，请检查输入';
$lang->taizhang->deleteSuccess  = '删除成功';
$lang->taizhang->deleteFail     = '删除失败';
$lang->taizhang->noData         = '暂无台账数据，请点击「新增台账」添加。';
$lang->taizhang->profitRateNA   = '-';

/* 筛选栏 */
$lang->taizhang->filterPhase    = '项目阶段';
$lang->taizhang->filterCategory = '项目类别';
$lang->taizhang->filterPM       = '项目经理';
$lang->taizhang->filterStatus   = '项目状态';
$lang->taizhang->filterAll      = '全部';
$lang->taizhang->filterSearch   = '查询';
$lang->taizhang->filterReset    = '重置';

/* 汇总行 */
$lang->taizhang->summary        = '合计';

/* 提示 */
$lang->taizhang->investedCostTip  = '除外购和税之外的已实际投入成本';
$lang->taizhang->profitRateFormula = '利润率 = (合同金额 - 当前预估成本 - 外采金额) ÷ 合同金额 × 100%';

/* 项目状态 */
$lang->taizhang->projectStatusList = array(
    'wait'      => '未开始',
    'doing'     => '进行中',
    'suspended' => '挂起',
    'closed'    => '关闭',
);
