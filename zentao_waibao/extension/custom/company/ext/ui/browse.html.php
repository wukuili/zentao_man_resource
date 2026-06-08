<?php
declare(strict_types=1);
/**
 * 外包标识插件 — 覆盖 company/browse 视图
 *
 * 在原有用户列表页基础上，在底部批量操作栏新增
 * "设为自有人员" / "设为外包人员" 两个批量设置按钮，
 * 支持先勾选用户（或按左侧部门筛选后全选）再批量操作。
 *
 * 注：此文件覆盖 module/company/ui/browse.html.php，
 * 升级禅道时需对照核心文件检查差异。
 */
namespace zin;

/* ── 顶部 featureBar（原版） ── */
featureBar
(
    set::current($browseType),
    set::linkParams("browseType={key}"),
    li(searchToggle(set::open($type == 'bysearch'), set::module('user')))
);

/* ── 顶部工具栏：在原有按钮后追加"批量设置外包标识"入口 ── */
$waibaoURL = createLink('waibao', 'browse');
toolbar
(
    btn
    (
        set::icon('cog-outline'),
        setClass('btn ghost'),
        set::url(createLink('custom', 'set', 'module=user&field=roleList')),
        setData('app', 'admin'),
        $lang->company->manageRole
    ),
    btn
    (
        set::icon('people'),
        setClass('btn ghost'),
        set::url($waibaoURL),
        '批量设置外包标识'
    ),
    btnGroup
    (
        btn
        (
            setClass('btn btn-primary create-user-btn'),
            set::icon('plus'),
            set::url(createLink('user', 'create', "deptID={$deptID}&type={$browseType}")),
            $lang->user->create
        ),
        dropdown
        (
            btn(setClass('btn primary dropdown-toggle'), setStyle(array('padding' => '6px', 'border-radius' => '0 2px 2px 0'))),
            set::items
            (
                array
                (
                    array('text' => $lang->user->create,      'url' => createLink('user', 'create', "deptID={$deptID}&type={$browseType}"), 'className' => '.create-user-btn'),
                    array('text' => $lang->user->batchCreate, 'url' => createLink('user', 'batchCreate', "deptID={$deptID}&type={$browseType}")),
                )
            ),
            set::placement('bottom-end')
        )
    )
);

/* ── 左侧部门树（原版） ── */
$settingLink = createLink('dept', 'browse');
$closeLink   = createLink('company', 'browse', "browseType={$browseType}&param=0&type={$type}");
sidebar
(
    moduleMenu(set(array
    (
        'modules'     => $deptTree,
        'activeKey'   => $type == 'bydept' ? $param : 0,
        'settingLink' => $settingLink,
        'closeLink'   => $closeLink,
        'showDisplay' => false,
        'settingText' => $lang->dept->manage
    )))
);

/* ── 底部批量操作栏：原有"编辑"+ 新增"设为自有/外包" ── */
$batchEditURL = createLink('user', 'batchEdit', "deptID={$deptID}&type={$browseType}");

$footItems = array();
if(common::hasPriv('user', 'batchEdit'))
{
    $footItems[] = array(
        'text'          => $lang->edit,
        'className'     => 'secondary open-url',
        'data-load'     => 'post',
        'data-url'      => $batchEditURL,
        'data-data-map' => 'userIdList[]: #userList~checkedIDList',
    );
}

/* 批量设置外包标识按钮（需先勾选）
 * 复用 ZUI 原生 open-url + data-load=post 批量提交（与上方"编辑"按钮同套路）：
 *   - data-data-map 自动把表格勾选行的 ID 收集为 userIDs[] 提交；
 *   - outsourced 固定值放进 data-url 路由参数，控制器从方法参数读取；
 *   - 控制器返回 {result:success, load:列表URL}，ZUI 自动 AJAX 刷新列表。
 * 不再自己写 XHR，避免与 ZUI 的 data-url 默认行为冲突导致整页跳转空白页。 */
$footItems[] = array(
    'text'          => '选中 → 自有人员',
    'className'     => 'open-url btn-outline-success',
    'data-load'     => 'post',
    'data-url'      => createLink('waibao', 'batchSetOutsourced', 'outsourced=0'),
    'data-data-map' => 'userIDs[]: #userList~checkedIDList',
    'data-confirm'  => '确认将选中的用户设为「自有人员」？',
);
$footItems[] = array(
    'text'          => '选中 → 外包人员',
    'className'     => 'open-url btn-outline-warning',
    'data-load'     => 'post',
    'data-url'      => createLink('waibao', 'batchSetOutsourced', 'outsourced=1'),
    'data-data-map' => 'userIDs[]: #userList~checkedIDList',
    'data-confirm'  => '确认将选中的用户设为「外包人员」？',
);

$footToolbar = array(
    'items'    => $footItems,
    'btnProps'  => array('size' => 'sm', 'btnType' => 'secondary'),
);

/* 有批量操作时始终显示复选框列 */
$this->config->company->user->dtable->fieldList['id']['type'] = 'checkID';

foreach($users as $user)
{
    if(!$user->last) $user->last = '';
}

$tableData = initTableData($users, $this->config->company->user->dtable->fieldList, $this->loadModel('user'));
dtable
(
    setID('userList'),
    set::orderBy($orderBy),
    set::sortLink(createLink('company', 'browse', "browseType={$browseType}&param={$param}&type={$type}&orderBy={name}_{sortType}&recTotal={$pager->recTotal}&recPerPage={$pager->recPerPage}&pageID={$pager->pageID}")),
    set::cols($this->config->company->user->dtable->fieldList),
    set::data($tableData),
    set::checkable(true),
    set::fixedLeftWidth('0.2'),
    set::footToolbar($footToolbar),
    set::footPager(usePager())
);

render();
