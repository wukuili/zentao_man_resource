<?php
namespace zin;

$weekStart = $this->view->weekStart;
$rows      = $this->view->rows;

panel(
  set::title($this->lang->zhoubao->browseTitle . ' · ' . $weekStart),
  empty($rows) ? p('本周暂无活跃项目周报数据。') : div(json_encode($rows))
);
