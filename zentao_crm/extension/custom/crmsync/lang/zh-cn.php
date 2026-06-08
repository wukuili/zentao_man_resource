<?php
if(!isset($lang->crmsync)) $lang->crmsync = new stdclass();
$lang->crmsync->common = 'CRM对接';

/* 菜单。 */
$lang->crmsync->menu = new stdclass();
$lang->crmsync->menu->browse   = array('link' => '同步记录|crmsync|browse');
$lang->crmsync->menu->settings = array('link' => '对接设置|crmsync|settings');

/* 权限资源定义。 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->crmsync = new stdclass();
$lang->resource->crmsync->browse   = 'browse';
$lang->resource->crmsync->settings = 'settings';
$lang->resource->crmsync->retry    = 'retry';
$lang->resource->crmsync->receive  = 'receive';
$lang->resource->crmsync->products = 'products';

/* 页面标题。 */
$lang->crmsync->browseTitle   = 'CRM同步记录';
$lang->crmsync->settingsTitle = 'CRM对接设置';

/* 同步记录列表字段。 */
$lang->crmsync->id            = '编号';
$lang->crmsync->opportunityId = '商机ID';
$lang->crmsync->oppName       = '商机名称';
$lang->crmsync->customerName  = '客户';
$lang->crmsync->project       = '禅道项目';
$lang->crmsync->productId     = '关联产品';
$lang->crmsync->syncStatus    = '状态';
$lang->crmsync->errorMsg      = '失败原因';
$lang->crmsync->createdBy     = '操作者';
$lang->crmsync->createdDate   = '同步时间';
$lang->crmsync->actions       = '操作';
$lang->crmsync->retry         = '重试';
$lang->crmsync->pureProject   = '单纯项目';

$lang->crmsync->statusList = array(
    'success' => '成功',
    'failed'  => '失败',
);

/* 对接设置表单。 */
$lang->crmsync->defaultPM          = '默认项目经理';
$lang->crmsync->defaultPMTip       = '自动建项目时统一指定的项目经理账号, 建完可人工改派。';
$lang->crmsync->defaultProgram     = '默认项目集ID';
$lang->crmsync->defaultProgramTip  = '"单纯项目"(未选产品)归属的项目集ID, 0 表示创建为顶层项目。';
$lang->crmsync->defaultProductName = '默认产品名';
$lang->crmsync->defaultProductNameTip = '"单纯项目"自动创建产品时的产品名, 留空则用项目名。';
$lang->crmsync->defaultDurationMonths = '默认工期(月)';
$lang->crmsync->apiToken           = '对接令牌(Token)';
$lang->crmsync->apiTokenTip        = 'CRM 调用接口时使用的令牌, 请用强随机字符串; 留空表示不变更。';
$lang->crmsync->apiTokenOperator   = '令牌对应账号';
$lang->crmsync->apiTokenOperatorTip = '命中令牌后以该禅道账号身份建项目(记录 openedBy)。';

/* 提示信息。 */
$lang->crmsync->saveSuccess  = '保存成功';
$lang->crmsync->retrySuccess = '重试成功';
$lang->crmsync->noRecord     = '暂无同步记录';
