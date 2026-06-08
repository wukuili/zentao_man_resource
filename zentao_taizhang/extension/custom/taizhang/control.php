<?php
/**
 * 项目台账 — 控制器
 *
 * 提供台账列表浏览、新增/编辑、删除、导出功能。
 * 日期无日期路由问题，所有参数通过 GET/POST 传递。
 */
class taizhang extends control
{
    /**
     * 台账列表页（默认入口）
     *
     * @param string $phase     项目阶段过滤
     * @param string $pm        项目经理账号过滤
     * @param string $rdManager 研发经理账号过滤
     */
    public function browse($phase = '', $pm = '', $rdManager = '')
    {
        if($this->server->request_method == 'POST')
        {
            $phase     = $this->post->phase     ?? '';
            $pm        = $this->post->pm        ?? '';
            $rdManager = $this->post->rdManager ?? '';
            $this->locate(inlink('browse', "phase={$phase}&pm={$pm}&rdManager={$rdManager}"));
            return;
        }

        $phase     = $this->get->phase     ?? $phase;
        $pm        = $this->get->pm        ?? $pm;
        $rdManager = $this->get->rdManager ?? $rdManager;

        $entries   = $this->taizhang->getList($phase, $pm, $rdManager);
        $summary   = $this->taizhang->calcSummary($entries);
        $pmOptions = $this->taizhang->getPMOptions();
        $rdOptions = $this->taizhang->getRDManagerOptions();

        $phaseOptions = array('' => '全部') + $this->config->taizhang->phaseList;

        $this->view->title        = $this->lang->taizhang->browse;
        $this->view->entries      = $entries;
        $this->view->summary      = $summary;
        $this->view->phase        = $phase;
        $this->view->pm           = $pm;
        $this->view->rdManager    = $rdManager;
        $this->view->phaseOptions = $phaseOptions;
        $this->view->pmOptions    = $pmOptions;
        $this->view->rdOptions    = $rdOptions;
        $this->view->browseURL    = inlink('browse');
        $this->view->warnRate     = $this->config->taizhang->profitRateWarn;
        $this->view->dangerRate   = $this->config->taizhang->profitRateDanger;

        $this->display();
    }

    /**
     * 新增/编辑台账
     *
     * GET  $id=0  显示新增表单
     * GET  $id>0  显示编辑表单
     * POST        保存，返回 JSON
     *
     * @param int $id 台账记录 ID，0 表示新增
     */
    public function edit($id = 0)
    {
        $id = (int)$id;

        if($this->server->request_method == 'POST')
        {
            $data = fixer::input('post')
                ->setInt('projectID')
                ->setFloat('initEstHours')
                ->setFloat('initBudget')
                ->setFloat('investedHours')
                ->setFloat('investedCost')
                ->setFloat('currentEstHours')
                ->setFloat('currentBudget')
                ->setFloat('revenue')
                ->setInt('sortOrder')
                ->get();

            $result = ($id > 0)
                ? $this->taizhang->updateEntry($id, $data)
                : $this->taizhang->createEntry($data);

            if($result)
            {
                return $this->send(array(
                    'result'  => 'success',
                    'message' => $this->lang->taizhang->saveSuccess,
                    'locate'  => inlink('browse'),
                ));
            }
            return $this->send(array('result' => 'fail', 'message' => $this->lang->taizhang->saveFail));
        }

        $entry       = ($id > 0) ? $this->taizhang->getByID($id) : null;
        $projectList = array(0 => $this->lang->taizhang->selectProject)
            + $this->loadModel('project')->getPairs();
        $phaseList   = $this->config->taizhang->phaseList;
        $userList    = $this->loadModel('user')->getPairs('noletter');

        $this->view->title       = ($id > 0) ? $this->lang->taizhang->editEntry : $this->lang->taizhang->addEntry;
        $this->view->entry       = $entry;
        $this->view->id          = $id;
        $this->view->projectList = $projectList;
        $this->view->phaseList   = $phaseList;
        $this->view->userList    = $userList;
        $this->view->saveURL     = inlink('edit', "id={$id}");

        $this->display();
    }

    /**
     * 删除台账记录（AJAX POST）
     *
     * @param int $id 台账记录 ID
     */
    public function delete($id = 0)
    {
        $id = (int)$id;
        if(!$id) return $this->send(array('result' => 'fail', 'message' => '无效ID'));

        $result = $this->taizhang->deleteEntry($id);
        if($result)
        {
            return $this->send(array(
                'result'  => 'success',
                'message' => $this->lang->taizhang->deleteSuccess,
                'locate'  => inlink('browse'),
            ));
        }
        return $this->send(array('result' => 'fail', 'message' => $this->lang->taizhang->deleteFail));
    }

    /**
     * 导出台账为 CSV
     *
     * @param string $phase
     * @param string $pm
     * @param string $rdManager
     */
    public function export($phase = '', $pm = '', $rdManager = '')
    {
        $phase     = $this->get->phase     ?? $phase;
        $pm        = $this->get->pm        ?? $pm;
        $rdManager = $this->get->rdManager ?? $rdManager;

        $entries  = $this->taizhang->getList($phase, $pm, $rdManager);
        $warnRate = $this->config->taizhang->profitRateWarn;

        $filename = '项目台账_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        /* UTF-8 BOM，避免 Excel 乱码 */
        fputs($out, "\xEF\xBB\xBF");

        $headers = array(
            '序号', '项目简称', '项目阶段', '项目经理', '研发经理', '当前项目情况',
            '初始预估人月', '初始预估成本(万元)', '已投入人月', '已投入成本(除外购和税)',
            '当前预估人月', '当前预估成本(万元)', '当前预估利润率(%)', '近期项目成员',
        );
        fputcsv($out, $headers);

        $i = 1;
        foreach($entries as $e)
        {
            $profitRate = ($e->profitRate !== null) ? $e->profitRate . '%' : '-';
            fputcsv($out, array(
                $i++,
                $e->shortName,
                $e->phase,
                $e->pmName,
                $e->rdManagerName,
                strip_tags($e->currentStatus),
                $e->initEstHours,
                $e->initBudget,
                $e->investedHours,
                $e->investedCost,
                $e->currentEstHours,
                $e->currentBudget,
                $profitRate,
                $e->recentMembers,
            ));
        }
        fclose($out);
        exit;
    }
}
