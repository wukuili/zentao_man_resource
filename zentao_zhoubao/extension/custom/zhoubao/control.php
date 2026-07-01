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

    /**
     * 复制上周手写内容。POST，返回 JSON。
     * @param int    $project
     * @param string $week
     */
    public function copyLast($project, $week = '')
    {
        $project   = (int)$project;
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        $prev = $this->zhoubao->getPrevReport($project, $weekStart);
        if(!$prev) return $this->send(array('result' => 'fail', 'message' => '上周暂无周报'));
        return $this->send(array('result' => 'success', 'data' => array(
            'nextPlan' => $prev->nextPlan, 'risk' => $prev->risk, 'summary' => $prev->summary,
        )));
    }

    /**
     * 查看已提交周报（只读，读 snapshot 不重算）。
     * @param int $id
     */
    public function view($id)
    {
        $report = $this->dao->select('*')->from('zt_zhoubao')->where('id')->eq((int)$id)->fetch();
        if(!$report) return print('周报不存在');
        $projectInfo = $this->dao->select('id, name, PM')->from(TABLE_PROJECT)->where('id')->eq($report->project)->fetch();

        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->display();
    }
}
