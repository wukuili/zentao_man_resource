<?php
/**
 * 外包标识插件 — 用户模块配置扩展
 *
 * 将 outsourced 字段添加到用户创建/编辑表单的可接受字段列表中，
 * 以便 ZenTao 的 DAO 在保存时能正确接收该字段。
 */

/* 仅在用户模块表单配置已加载时扩展 */
if(isset($config->user->form))
{
    /* 创建用户时允许 outsourced 字段 */
    $config->user->form->create['outsourced'] = array('type' => 'int', 'required' => false, 'default' => 0);

    /* 编辑用户时允许 outsourced 字段 */
    $config->user->form->edit['outsourced'] = array('type' => 'int', 'required' => false, 'default' => 0);
}

/* 批量编辑时允许 outsourced 字段 */
if(isset($config->user->availableBatchEditFields))
{
    $config->user->availableBatchEditFields .= ',outsourced';
}

/* 用户列表自定义字段中增加 outsourced */
if(isset($config->user->list->customBatchEditFields))
{
    $config->user->list->customBatchEditFields .= ',outsourced';
}