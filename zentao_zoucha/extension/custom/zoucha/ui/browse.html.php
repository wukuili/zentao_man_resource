<?php
declare(strict_types=1);
/**
 * 项目走查 — 列表页 ZIN 模板（Task 1 占位版，Task 5 完善）
 */
namespace zin;

$results = $this->view->results;
$total   = (int)$this->view->total;

panel
(
    setClass('zoucha-page'),
    html('<div style="padding:16px">项目走查页面占位，命中项目数：' . $total . '</div>')
);
