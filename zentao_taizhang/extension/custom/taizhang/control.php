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
     * 注意：禅道 PATH_INFO 路由按「位置」把 URL 参数映射到方法形参，
     * 不会产生 $_GET 键值对，因此分页参数 $pageID 必须声明为形参，
     * 否则翻页链接永远落在第 1 页。
     *
     * @param string $phase         项目阶段过滤
     * @param string $pm            项目经理账号过滤
     * @param string $projectStatus 项目状态过滤（'all' 表示全部，区别于首次进入的默认 doing）
     * @param int    $pageID        页码
     * @param string $category      项目类别过滤
     */
    public function browse($phase = '', $pm = '', $projectStatus = '', $pageID = 1, $category = '')
    {
        $phase     = ($phase === false || $phase === null) ? '' : (string)$phase;
        $pm        = ($pm === false || $pm === null) ? '' : (string)$pm;
        $projectStatus = ($projectStatus === false || $projectStatus === null) ? '' : (string)$projectStatus;
        $category  = ($category === false || $category === null) ? '' : (string)$category;

        if($this->server->request_method == 'POST')
        {
            $phase         = isset($_POST['phase']) ? $_POST['phase'] : '';
            $pm            = isset($_POST['pm']) ? $_POST['pm'] : '';
            $projectStatus = isset($_POST['projectStatus']) ? $_POST['projectStatus'] : '';
            $category      = isset($_POST['category']) ? $_POST['category'] : '';
            $pageID        = 1;
        }
        else
        {
            $phase         = isset($_GET['phase']) ? $_GET['phase'] : $phase;
            $pm            = isset($_GET['pm']) ? $_GET['pm'] : $pm;
            $projectStatus = isset($_GET['projectStatus']) ? $_GET['projectStatus'] : $projectStatus;
            $category      = isset($_GET['category']) ? $_GET['category'] : $category;
            $pageID        = isset($_GET['pageID']) ? (int)$_GET['pageID'] : (int)$pageID;
        }
        $phase     = ($phase === false || $phase === null) ? '' : (string)$phase;
        $pm        = ($pm === false || $pm === null) ? '' : (string)$pm;
        $projectStatus = ($projectStatus === false || $projectStatus === null) ? '' : (string)$projectStatus;
        $category  = ($category === false || $category === null) ? '' : (string)$category;

        /* 首次进入（无任何参数）默认只看进行中；翻页链接里「全部」用哨兵值 all 表示，
         * 避免被误判为首次进入而强制改回 doing。判断完成后再解码。 */
        if($projectStatus === '' && empty($_GET) && $this->server->request_method != 'POST') $projectStatus = 'doing';
        if($projectStatus === 'all') $projectStatus = '';

        /* 自动从现有项目列表补建台账：保证新项目无需手动添加即出现在列表中 */
        $this->taizhang->syncFromProjects();

        $allEntries = $this->taizhang->getList($phase, $pm, $projectStatus, $category);
        $total      = count($allEntries);
        $recPerPage = isset($this->config->taizhang->recPerPage) ? (int)$this->config->taizhang->recPerPage : 15;
        if($recPerPage <= 0) $recPerPage = 15;

        $pageTotal = max(1, (int)ceil($total / $recPerPage));
        $pageID    = max(1, min($pageID, $pageTotal));
        $entries   = array_slice($allEntries, ($pageID - 1) * $recPerPage, $recPerPage);
        $summary   = $this->taizhang->calcSummary($entries);
        $totalSummary = $this->taizhang->calcSummary($allEntries);
        $pmOptions = $this->taizhang->getPMOptions();

        $phaseOptions    = array('' => '全部') + $this->config->taizhang->phaseList;
        $statusOptions   = array('' => '全部') + $this->lang->taizhang->projectStatusList;
        $categoryOptions = array('' => '全部') + $this->config->taizhang->projectCategoryList;

        $this->view->title        = $this->lang->taizhang->browse;
        $this->view->entries      = $entries;
        $this->view->summary      = $summary;
        $this->view->totalSummary = $totalSummary;
        $this->view->phase        = $phase;
        $this->view->pm           = $pm;
        $this->view->projectStatus = $projectStatus;
        $this->view->category     = $category;
        $this->view->phaseOptions = $phaseOptions;
        $this->view->statusOptions = $statusOptions;
        $this->view->categoryOptions = $categoryOptions;
        $this->view->pmOptions    = $pmOptions;
        $this->view->pageID       = $pageID;
        $this->view->pageTotal    = $pageTotal;
        $this->view->recPerPage   = $recPerPage;
        $this->view->total        = $total;
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
                ->setFloat('outsourcingAmount')
                ->setFloat('receivedAmount')
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
        $phaseList    = $this->config->taizhang->phaseList;
        $categoryList = $this->config->taizhang->projectCategoryList;
        $yesNoList    = $this->config->taizhang->yesNoList;
        $userList    = $this->loadModel('user')->getPairs('noletter');

        $this->view->title       = ($id > 0) ? $this->lang->taizhang->editEntry : $this->lang->taizhang->addEntry;
        $this->view->entry       = $entry;
        $this->view->id          = $id;
        $this->view->projectList = $projectList;
        $this->view->categoryList = $categoryList;
        $this->view->yesNoList    = $yesNoList;
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
     * @param string $projectStatus
     * @param string $category
     */
    public function export($phase = '', $pm = '', $projectStatus = '', $category = '')
    {
        $phase         = isset($_GET['phase']) ? $_GET['phase'] : $phase;
        $pm            = isset($_GET['pm']) ? $_GET['pm'] : $pm;
        $projectStatus = isset($_GET['projectStatus']) ? $_GET['projectStatus'] : $projectStatus;
        $category      = isset($_GET['category']) ? $_GET['category'] : $category;
        $phase     = ($phase === false || $phase === null) ? '' : (string)$phase;
        $pm        = ($pm === false || $pm === null) ? '' : (string)$pm;
        $projectStatus = ($projectStatus === false || $projectStatus === null) ? '' : (string)$projectStatus;
        $category  = ($category === false || $category === null) ? '' : (string)$category;

        $entries  = $this->taizhang->getList($phase, $pm, $projectStatus, $category);
        $warnRate = $this->config->taizhang->profitRateWarn;

        $filename = '项目台账_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        /* UTF-8 BOM，避免 Excel 乱码 */
        fputs($out, "\xEF\xBB\xBF");

        $headers = array(
            '序号', '项目集', '项目简称', '项目阶段', '项目类别', '项目经理', '项目状态', '当前项目情况',
            '项目简介', '合同签署时间', '分包合同状态', '工程状态', '采购方式', '供货单位', '安装/施工单位',
            '计划开工日期', '实际开工日期', '计划完工日期', '实际完工日期', '验收情况',
            '安保措施是否到位', '是否涉及危险作业', '开工资料是否齐全',
            '初始预估人天', '初始预估成本(万元)', '已投入人天', '已投入成本(除外购和税/万元)',
            '当前预估人天', '当前预估成本(万元)', '合同金额(万元)', '外采金额(万元)',
            '回款金额(万元)', '当前预估利润率(%)', '进度偏差说明', '备注', '近期项目成员',
        );
        fputcsv($out, $headers);

        $i = 1;
        foreach($entries as $e)
        {
            $profitRate = ($e->profitRate !== null) ? $e->profitRate . '%' : '-';
            fputcsv($out, array(
                $i++,
                $e->programName !== '' ? $e->programName : '-',
                $e->shortName,
                $e->phase,
                $e->projectCategory,
                $e->pmName,
                $e->projectStatusName,
                strip_tags($e->currentStatus),
                strip_tags((string)$e->projectIntro),
                $e->contractSignDate,
                $e->subcontractStatus,
                $e->engineeringStatus,
                $e->procurementMethod,
                $e->supplyUnit,
                $e->constructionUnit,
                $e->planStartDate,
                $e->actualStartDate,
                $e->planEndDate,
                $e->actualEndDate,
                $e->acceptanceStatus,
                $e->securityMeasures,
                $e->hazardousWork,
                $e->startDocsComplete,
                $e->initEstHours,
                $e->initBudget,
                $e->investedHours,
                $e->investedCost,
                $e->currentEstHours,
                $e->currentBudget,
                $e->revenue,
                $e->outsourcingAmount,
                $e->receivedAmount,
                $profitRate,
                strip_tags((string)$e->progressDeviation),
                strip_tags((string)$e->remark),
                $e->recentMembers,
            ));
        }
        fclose($out);
        exit;
    }
}
