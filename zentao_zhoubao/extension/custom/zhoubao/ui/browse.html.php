<?php
namespace zin;
panel(
  set::title($this->lang->zhoubao->browseTitle . ' · ' . $this->weekStart),
  isEmpty($this->rows) ? p('本周暂无活跃项目周报数据。') : div(json_encode($this->rows))
);
