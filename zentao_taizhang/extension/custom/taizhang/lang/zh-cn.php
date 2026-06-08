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
$lang->taizhang->pm              = '项目经理';
$lang->taizhang->rdManager       = '研发经理';
$lang->taizhang->currentStatus   = '当前项目情况';
$lang->taizhang->initEstHours    = '初始预估人月';
$lang->taizhang->initBudget      = '初始预估成本(万元)';
$lang->taizhang->investedHours   = '已投入人月';
$lang->taizhang->investedCost    = '已投入成本(除外购和税)';
$lang->taizhang->currentEstHours = '当前预估人月';
$lang->taizhang->currentBudget   = '当前预估成本(万元)';
$lang->taizhang->profitRate      = '当前预估利润率(%)';
$lang->taizhang->recentMembers   = '近期项目成员';
$lang->taizhang->revenue         = '合同金额(万元)';
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
$lang->taizhang->filterPM       = '项目经理';
$lang->taizhang->filterRD       = '研发经理';
$lang->taizhang->filterAll      = '全部';
$lang->taizhang->filterSearch   = '查询';
$lang->taizhang->filterReset    = '重置';

/* 汇总行 */
$lang->taizhang->summary        = '合计';

/* 提示 */
$lang->taizhang->investedCostTip  = '除外购和税之外的已实际投入成本';
$lang->taizhang->profitRateFormula = '利润率 = (合同金额 - 当前预估成本) ÷ 合同金额 × 100%';
