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

    /**
     * 填写/编辑周报。
     * @param int    $project 项目 ID
     * @param string $week    周起始日（2026_06_29，'' 表示本周）
     */
    public function edit($project, $week = '')
    {
        $project   = (int)$project;
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);

        $projectInfo = $this->dao->select('id, name, PM')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch();
        if(!$projectInfo) return $this->send(array('result' => 'fail', 'message' => '项目不存在'));

        /* 权限：非 manage 权限者只能写自己负责的项目 */
        $canManage = common::hasPriv('zhoubao', 'manage');
        if(!$canManage && $projectInfo->PM !== $this->app->user->account)
        {
            return $this->send(array('result' => 'fail', 'message' => '仅项目经理可填写本项目周报'));
        }

        if($this->server->request_method == 'POST')
        {
            $submit = !empty($_POST['submit']);
            $id = $this->zhoubao->saveReport($project, $weekStart, $_POST, $this->app->user->account, $submit);
            if($id === false) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $locate = $submit ? inlink('view', "id=$id") : inlink('edit', "project=$project&week=" . str_replace('-', '_', $weekStart));
            return $this->send(array('result' => 'success', 'locate' => $locate));
        }

        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->display();
    }
}
