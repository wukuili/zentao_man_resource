<?php
/**
 * 外包标识插件 — 公司/用户列表配置扩展
 *
 * 1. 在用户浏览列表 dtable 中增加 outsourced（是否外包）列。
 * 2. 在高级搜索条件中加入 outsourced 过滤项。
 */

/* ── dtable 增加"是否外包"列 ── */
if(isset($config->company->user->dtable))
{
    $config->company->user->dtable->fieldList['outsourced']['name']     = 'outsourced';
    $config->company->user->dtable->fieldList['outsourced']['title']    = isset($lang->user->outsourced) ? $lang->user->outsourced : '是否外包';
    $config->company->user->dtable->fieldList['outsourced']['type']     = 'category';
    $config->company->user->dtable->fieldList['outsourced']['map']      = isset($lang->user->outsourcedList) ? $lang->user->outsourcedList : array(0 => '自有人员', 1 => '外包人员');
    $config->company->user->dtable->fieldList['outsourced']['sortType'] = true;
    $config->company->user->dtable->fieldList['outsourced']['show']     = true;
    $config->company->user->dtable->fieldList['outsourced']['group']    = '3';
    $config->company->user->dtable->fieldList['outsourced']['width']    = 100;
}

/* ── 高级搜索增加 outsourced 过滤 ── */
if(isset($config->company->browse->search))
{
    $config->company->browse->search['fields']['outsourced'] = isset($lang->user->outsourced) ? $lang->user->outsourced : '是否外包';
    $config->company->browse->search['params']['outsourced'] = array(
        'operator' => '=',
        'control'  => 'select',
        'values'   => isset($lang->user->outsourcedOptions) ? $lang->user->outsourcedOptions : array('' => '全部', 0 => '自有人员', 1 => '外包人员'),
    );
}
