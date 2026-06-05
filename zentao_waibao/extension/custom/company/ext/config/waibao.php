<?php
/**
 * 外包标识插件 — 公司/用户列表配置扩展
 *
 * 在用户浏览列表和搜索条件中加入 outsourced 字段。
 */

/* 用户列表 dtable 增加 outsourced 列 */
if(isset($config->company->dtable))
{
    /* dtable 列配置在 table.php 中定义，此处不做覆盖 */
}

/* 搜索字段增加 outsourced */
if(isset($config->company->browse->searchFields))
{
    $config->company->browse->searchFields .= ',outsourced';
}