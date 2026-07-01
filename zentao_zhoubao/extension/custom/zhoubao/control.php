<?php
class zhoubao extends control
{
    /**
     * 周报看板（默认入口）。
     * @param string $week 周起始日（周一，格式 2026_06_29，'' 表示本周）
     * @param string $pm   项目经理筛选（'' 或 'all' 表示全部）
     * @param string $fill 填报状态筛选（'' / all / none / draft / submitted）
     */
    public function browse($week = '', $pm = '', $fill = '')
    {
        $week = isset($_GET['week']) ? (string)$_GET['week'] : (string)$week;
        $pm   = isset($_GET['pm'])   ? (string)$_GET['pm']   : (string)$pm;
        $fill = isset($_GET['fill']) ? (string)$_GET['fill'] : (string)$fill;

        $weekStart = $this->zhoubao->resolveWeekStart($week); // Task 4 提供
        $rows      = $this->zhoubao->getBoardRows($weekStart, $pm, $fill); // Task 4 提供

        $this->view->title     = $this->lang->zhoubao->browseTitle;
        $this->view->weekStart = $weekStart;
        $this->view->rows      = $rows;
        $this->view->pm        = $pm;
        $this->view->fill      = $fill;
        $this->display();
    }
}
