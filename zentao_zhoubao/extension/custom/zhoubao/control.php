<?php
class zhoubao extends control
{
    /**
     * 周报看板（默认入口）。
     * @param string $week 周起始日（周一，格式 2026_06_29，'' 表示本周）
     * @param string $pm   项目经理筛选（'' 或 'all' 表示全部）
     * @param string $fill 填报状态筛选（'' / all / none / draft / submitted）
     */
    public function browse($week = '', $pm = '', $fill = '', $risk = '')
    {
        $week = isset($_GET['week']) ? (string)$_GET['week'] : (string)$week;
        $pm   = isset($_GET['pm'])   ? (string)$_GET['pm']   : (string)$pm;
        $fill = isset($_GET['fill']) ? (string)$_GET['fill'] : (string)$fill;
        $risk = isset($_GET['risk']) ? (string)$_GET['risk'] : (string)$risk;

        $weekStart = $this->zhoubao->resolveWeekStart($week);
        $rows      = $this->zhoubao->getBoardRows($weekStart, $pm, $fill, $risk);

        $this->view->title     = $this->lang->zhoubao->browseTitle;
        $this->view->weekStart = $weekStart;
        $this->view->rows      = $rows;
        $this->view->pm        = $pm;
        $this->view->fill      = $fill;
        $this->view->risk      = $risk;
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
        $this->view->prevReport  = $this->zhoubao->getPrevReport($project, $weekStart);
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
            'nextPlan' => $prev->nextPlan, 'risk' => $prev->risk, 'hasRisk' => $prev->hasRisk, 'summary' => $prev->summary,
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

        /* 编辑按钮可见性：manage 权限者或本项目 PM 才能编辑已提交周报，与 edit() 的权限判断一致 */
        $canEdit = common::hasPriv('zhoubao', 'manage') || ($projectInfo && $projectInfo->PM === $this->app->user->account);

        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->view->canEdit     = $canEdit;
        $this->view->prevReport  = $this->zhoubao->getPrevReport($report->project, $report->weekStart);
        $this->display();
    }

    /* CSV 公式注入防护：单元格以 = + - @ 开头时加前导单引号，阻止 Excel/Sheets 当公式执行 */
    private function csvSafe($value)
    {
        $value = (string)$value;
        if($value !== '' && strpos('=+-@', $value[0]) !== false) return "'" . $value;
        return $value;
    }

    /**
     * 导出 CSV。
     * @param string $type board=当周看板汇总 / one=单份周报
     * @param string $week 周起始日（board 用）
     * @param int    $id   周报 ID（one 用）
     */
    public function export($type = 'board', $week = '', $id = 0, $risk = '')
    {
        $filename = 'zhoubao_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM，Excel 正确识别中文

        $out = fopen('php://output', 'w');
        $hasRiskList = $this->lang->zhoubao->hasRiskList;
        if($type === 'one' && $id)
        {
            $r = $this->dao->select('*')->from('zt_zhoubao')->where('id')->eq((int)$id)->fetch();
            $p = $r ? $this->dao->select('name')->from(TABLE_PROJECT)->where('id')->eq($r->project)->fetch('name') : '';
            fputcsv($out, array('项目', '周次', '下周计划', '是否有风险', '风险', '本周小结'));
            if($r) fputcsv($out, array($this->csvSafe($p), '第' . $r->week . '周', $this->csvSafe($r->nextPlan), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $this->csvSafe($r->risk), $this->csvSafe($r->summary)));
        }
        else
        {
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '', $risk);
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务'));
            foreach($rows as $r) fputcsv($out, array($this->csvSafe($r->projectName), $this->csvSafe($r->pmName), zget($labels, $r->status, $r->status), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $r->doneCount, $r->overdueCount));
        }
        fclose($out);
        exit;
    }

    /**
     * 企微定时推送入口，供 cron/系统 crontab curl 调用。token 校验。
     * @param string $token 与 config->zhoubao->pushToken 比对
     * @param string $week  周起始日（'' 表示本周）
     */
    public function cronPush($token = '', $week = '')
    {
        $token = isset($_GET['token']) ? (string)$_GET['token'] : (string)$token;
        if($token === '' || $token !== $this->config->zhoubao->pushToken) return print('invalid token');
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        $res = $this->zhoubao->pushWecom($weekStart);
        return print($res['message']);
    }

    /**
     * 看板"立即推送"按钮触发。需 manage 权限。
     * @param string $week
     */
    public function pushNow($week = '')
    {
        if(!common::hasPriv('zhoubao', 'manage')) return $this->send(array('result' => 'fail', 'message' => '无推送权限'));
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        return $this->send($this->zhoubao->pushWecom($weekStart));
    }
}
