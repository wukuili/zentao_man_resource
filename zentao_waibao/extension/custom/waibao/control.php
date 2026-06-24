<?php
/**
 * 外包标识插件 — 控制器
 *
 * 处理所有 HTTP 请求，包括人员标识管理、三个维度的工时统计页面。
 * 日期参数使用下划线分隔（如 2026_05_28），与禅道路由兼容。
 */
class waibao extends control
{
    /**
     * 默认跳转到人员标识管理页
     */
    public function browse($dept = 0, $outsourced = '', $orderBy = 'id')
    {
        /* POST：筛选表单提交，服务端重定向到带参数的 GET URL */
        if($this->server->request_method == 'POST')
        {
            $dept       = (int)$this->post->dept;
            $outsourced = $this->post->outsourced;
            $outsourced = in_array($outsourced, array('', '0', '1')) ? $outsourced : '';
            $this->locate(inlink('browse', "dept={$dept}&outsourced={$outsourced}"));
            return;
        }

        /* GET：正常渲染 */
        $filterDept       = isset($this->get->dept) ? (int)$this->get->dept : (int)$dept;
        $filterOutsourced = isset($this->get->outsourced) ? $this->get->outsourced : $outsourced;
        $filterOutsourced = in_array($filterOutsourced, array('', '0', '1')) ? $filterOutsourced : '';

        $users = $this->waibao->getUserListWithOutsourced($orderBy);
        $depts = $this->loadModel('dept')->getOptionMenu();

        /* 计算当前部门及所有子部门 ID，用于模板中的精准筛选 */
        $filterDeptIDs = array();
        if($filterDept > 0)
        {
            $childIDs      = $this->loadModel('dept')->getAllChildId($filterDept);
            $filterDeptIDs = array_merge(array($filterDept), (array)$childIDs);
        }

        $this->view->title            = $this->lang->waibao->browse;
        $this->view->users            = $users;
        $this->view->depts            = $depts;
        $this->view->orderBy          = $orderBy;
        $this->view->filterDept       = $filterDept;
        $this->view->filterDeptIDs    = $filterDeptIDs;
        $this->view->filterOutsourced = $filterOutsourced;

        $this->display();
    }

    /**
     * 判断当前用户是否为系统/公司管理员。
     *
     * @access protected
     * @return bool
     */
    protected function waibaoIsAdmin()
    {
        $user = $this->app->user;
        if(empty($user)) return false;
        if(!empty($user->admin)) return true;
        return strpos(",{$this->app->company->admins},", ",{$user->account},") !== false;
    }

    /**
     * 设置单个用户的外包标识
     */
    public function setUserOutsourced($userID = 0)
    {
        /* 标记用户为外包/自有属于组织级操作，仅管理员可执行。
         * navGroup 归入 project 后，项目成员经 isProjectAdmin 可访问 waibao 模块，
         * 此处显式拦截写操作，防止项目成员越权修改用户外包标识。*/
        if(!$this->waibaoIsAdmin()) return $this->send(array('result' => 'fail', 'message' => $this->lang->waibao->denyWrite));

        if($this->server->request_method == 'POST')
        {
            $userID    = (int)$this->post->userID;
            $outsourced = (int)$this->post->outsourced;

            $result = $this->waibao->updateUserOutsourced($userID, $outsourced);
            if($result)
            {
                return $this->send(array('result' => 'success', 'message' => $this->lang->waibao->setSuccess, 'userID' => $userID));
            }
            else
            {
                return $this->send(array('result' => 'fail', 'message' => $this->lang->waibao->setFail));
            }
        }

        /* GET 请求：显示设置页面 */
        $user = $this->loadModel('user')->getById($userID);
        if(!$user) $this->send(array('result' => 'fail', 'message' => '用户不存在'));

        $this->view->title   = $this->lang->waibao->setOutsourced;
        $this->view->user    = $user;
        $this->view->outsourcedList = $this->lang->waibao->outsourcedList;
        $this->display();
    }

    /**
     * 批量设置用户外包标识
     */
    public function batchSetOutsourced($outsourced = 0)
    {
        /* 同 setUserOutsourced：批量改外包标识为组织级写操作，仅管理员可执行。*/
        if(!$this->waibaoIsAdmin()) return $this->send(array('result' => 'fail', 'message' => $this->lang->waibao->denyWrite));

        if($this->server->request_method == 'POST')
        {
            $userIDs = $this->post->userIDs;
            /* outsourced 优先取 POST（兼容旧的独立页 $.post 调用），否则取路由参数（company 列表页 ZUI data-url 提交） */
            $outsourced = ($this->post->outsourced !== false && $this->post->outsourced !== null) ? (int)$this->post->outsourced : (int)$outsourced;
            $userIDs    = is_array($userIDs) ? $userIDs : explode(',', (string)$userIDs);
            $userIDs    = array_values(array_unique(array_filter(array_map('intval', $userIDs))));

            if(empty($userIDs))
            {
                return $this->send(array('result' => 'fail', 'message' => '请选择用户'));
            }

            $result = $this->waibao->batchUpdateOutsourced($userIDs, $outsourced);
            if($result)
            {
                /* load 让 ZUI（open-url + data-load=post）提交成功后刷新当前用户列表 */
                $locate = $this->session->userList ? $this->session->userList : helper::createLink('company', 'browse');
                return $this->send(array('result' => 'success', 'message' => $this->lang->waibao->setSuccess, 'load' => $locate));
            }
            else
            {
                $message = dao::isError() ? dao::getError() : $this->lang->waibao->setFail;
                return $this->send(array('result' => 'fail', 'message' => $message));
            }
        }
    }

    /**
     * 组织维度 — 按部门统计外包/自有工时
     *
     * @param string $date 日期（用下划线替代横杠）
     */
    public function orgdimension($date = '')
    {
        /* 处理 POST 筛选 */
        if($this->server->request_method == 'POST')
        {
            $date   = $this->post->date;
            $dept   = $this->post->dept;
            $outsourced = $this->post->outsourced;
            $status = $this->post->status;

            $date = str_replace('_', '-', $date);
            $this->locate(inlink('orgdimension', "date={$date}&dept={$dept}&outsourced={$outsourced}&status={$status}"));
        }

        /* 日期参数处理 */
        $date = str_replace('_', '-', $date);
        if(empty($date) || $date == '-') $date = date('Y-m-d');
        $begin = date('Y-m-01', strtotime($date));
        $end   = date('Y-m-t', strtotime($date));

        /* 获取筛选参数 */
        $dept       = isset($this->get->dept) ? (int)$this->get->dept : 0;
        $outsourced = isset($this->get->outsourced) ? $this->get->outsourced : '';
        $status     = isset($this->get->status) ? $this->get->status : 'todo';

        /* 获取部门列表 */
        $depts = $this->loadModel('dept')->getOptionMenu();

        /* 获取统计数据 */
        $data = $this->waibao->getOrgHoursByOutsourced($dept, $begin, $end, $status);

        /* 加载 ECharts */
        $this->app->loadConfig('waibao');

        $this->view->title       = $this->lang->waibao->orgdimension;
        $this->view->data        = $data;
        $this->view->depts       = $depts;
        $this->view->currentDept = $dept;
        $this->view->begin       = $begin;
        $this->view->end         = $end;
        $this->view->currentOutsourced = $outsourced;
        $this->view->currentStatus = $status;
        $this->view->date        = $date;
        $this->view->loadRangeColors = $this->config->waibao->loadRangeColors;

        $this->display();
    }

    /**
     * 项目维度 — 按项目统计外包/自有工时
     *
     * @param string $date 日期
     * @param int    $project 项目ID
     */
    public function projectdimension($date = '', $project = 0)
    {
        /* 处理 POST 筛选 */
        if($this->server->request_method == 'POST')
        {
            $date    = $this->post->date;
            $project = (int)$this->post->project;
            $outsourced = $this->post->outsourced;
            $status = $this->post->status;

            $date = str_replace('_', '-', $date);
            $this->locate(inlink('projectdimension', "date={$date}&project={$project}&outsourced={$outsourced}&status={$status}"));
        }

        /* 日期参数处理 */
        $date = str_replace('_', '-', $date);
        if(empty($date) || $date == '-') $date = date('Y-m-d');
        $begin = date('Y-m-01', strtotime($date));
        $end   = date('Y-m-t', strtotime($date));

        /* 获取筛选参数 */
        $outsourced = isset($this->get->outsourced) ? $this->get->outsourced : '';
        $status     = isset($this->get->status) ? $this->get->status : 'todo';

        /* 获取项目列表 */
        $projects = $this->loadModel('project')->getList('doing', '0', 'all');

        /* 获取统计数据 */
        $data = $this->waibao->getProjectHoursByOutsourced($project, $begin, $end, $status);

        $this->app->loadConfig('waibao');

        $this->view->title       = $this->lang->waibao->projectdimension;
        $this->view->data        = $data;
        $this->view->projects    = $projects;
        $this->view->currentProject = $project;
        $this->view->begin       = $begin;
        $this->view->end         = $end;
        $this->view->currentOutsourced = $outsourced;
        $this->view->currentStatus = $status;
        $this->view->date        = $date;
        $this->view->loadRangeColors = $this->config->waibao->loadRangeColors;

        $this->display();
    }

    /**
     * 成员维度 — 按成员统计外包/自有工时
     *
     * @param string $date 日期
     * @param string $userID 用户账号
     */
    public function memberdimension($date = '', $userID = '')
    {
        /* 处理 POST 筛选 */
        if($this->server->request_method == 'POST')
        {
            $date    = $this->post->date;
            $userID  = $this->post->userID;
            $outsourced = $this->post->outsourced;
            $status = $this->post->status;

            $date = str_replace('_', '-', $date);
            $this->locate(inlink('memberdimension', "date={$date}&userID={$userID}&outsourced={$outsourced}&status={$status}"));
        }

        /* 日期参数处理 */
        $date = str_replace('_', '-', $date);
        if(empty($date) || $date == '-') $date = date('Y-m-d');
        $begin = date('Y-m-01', strtotime($date));
        $end   = date('Y-m-t', strtotime($date));

        /* 获取筛选参数 */
        $outsourced = isset($this->get->outsourced) ? $this->get->outsourced : '';
        $status     = isset($this->get->status) ? $this->get->status : 'todo';

        /* 获取用户列表 */
        $users = $this->loadModel('user')->getPairs('nodeleted|noletter|useid');

        /* 获取统计数据 */
        $data = $this->waibao->getMemberHoursByOutsourced($userID, $begin, $end, $status);

        $this->app->loadConfig('waibao');

        $this->view->title       = $this->lang->waibao->memberdimension;
        $this->view->data        = $data;
        $this->view->users       = $users;
        $this->view->currentUser = $userID;
        $this->view->begin       = $begin;
        $this->view->end         = $end;
        $this->view->currentOutsourced = $outsourced;
        $this->view->currentStatus = $status;
        $this->view->date        = $date;
        $this->view->loadRangeColors = $this->config->waibao->loadRangeColors;

        $this->display();
    }

    /**
     * 多维外包工时汇总页（外包工时菜单首页）。
     *
     * 顶部筛选：时间范围 / 部门 / 项目 / 迭代；可切换分组维度。
     * 工时口径以「已消耗」为主，数据源为 zt_effort 工时日志。
     *
     * @param string $begin     开始日期（用下划线替代横杠）
     * @param string $end       结束日期
     * @param int    $dept      部门ID
     * @param int    $project   项目ID
     * @param int    $execution 迭代ID
     * @param string $groupBy   分组维度 member|project|execution|dept|month
     */
    public function summary($begin = '', $end = '', $dept = 0, $project = 0, $execution = 0, $groupBy = 'member')
    {
        /* POST：筛选表单提交，重定向到 GET */
        if($this->server->request_method == 'POST')
        {
            $begin     = str_replace('-', '_', $this->post->begin);
            $end       = str_replace('-', '_', $this->post->end);
            $dept      = (int)$this->post->dept;
            $project   = (int)$this->post->project;
            $execution = (int)$this->post->execution;
            $groupBy   = $this->post->groupBy;
            $this->locate(inlink('summary', "begin={$begin}&end={$end}&dept={$dept}&project={$project}&execution={$execution}&groupBy={$groupBy}"));
            return;
        }

        /* 日期处理：默认本月 */
        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);
        if(empty($begin) || $begin == '-') $begin = date('Y-m-01');
        if(empty($end)   || $end   == '-') $end   = date('Y-m-t');

        $data = $this->waibao->getOutsourcedSummary($begin, $end, (int)$dept, (int)$project, (int)$execution, $groupBy);

        /* 筛选下拉数据 */
        $depts      = $this->loadModel('dept')->getOptionMenu();
        $projects   = $this->waibao->getProjectPairs();
        $executions = $this->waibao->getExecutionPairs((int)$project);

        $this->app->loadConfig('waibao');

        $this->view->title            = $this->lang->waibao->summary;
        $this->view->data             = $data;
        $this->view->depts            = $depts;
        $this->view->projects         = $projects;
        $this->view->executions       = $executions;
        $this->view->begin            = $begin;
        $this->view->end              = $end;
        $this->view->currentDept      = (int)$dept;
        $this->view->currentProject   = (int)$project;
        $this->view->currentExecution = (int)$execution;
        $this->view->groupBy          = $data['groupBy'];

        $this->display();
    }

    /**
     * 导出外包工时汇总为 CSV（可直接用 Excel 打开）。
     *
     * @param string $begin     开始日期
     * @param string $end       结束日期
     * @param int    $dept      部门ID
     * @param int    $project   项目ID
     * @param int    $execution 迭代ID
     * @param string $groupBy   分组维度
     */
    public function exportSummary($begin = '', $end = '', $dept = 0, $project = 0, $execution = 0, $groupBy = 'member')
    {
        $begin   = str_replace('_', '-', $begin);
        $end     = str_replace('_', '-', $end);
        if(empty($begin) || $begin == '-') $begin = date('Y-m-01');
        if(empty($end)   || $end   == '-') $end   = date('Y-m-t');

        $allowed = array('member', 'project', 'execution', 'dept', 'month');
        if(!in_array($groupBy, $allowed)) $groupBy = 'member';

        $data          = $this->waibao->getOutsourcedSummary($begin, $end, (int)$dept, (int)$project, (int)$execution, $groupBy);
        $rows          = isset($data['rows']) ? $data['rows'] : array();
        $totalConsumed = isset($data['total']) ? $data['total'] : 0;

        $this->app->loadConfig('waibao');
        $firstColTitle = zget($this->lang->waibao->dimensionTitle, $groupBy, $this->lang->waibao->realname);

        $filename = rawurlencode("外包工时汇总_{$begin}_{$end}.csv");
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename*=UTF-8''{$filename}");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, array($firstColTitle, '已消耗工时(h)', '占比(%)', '记录数'));
        foreach($rows as $row)
        {
            fputcsv($out, array($row['name'], $row['consumed'], $row['percent'], $row['records']));
        }
        fputcsv($out, array('合计', $totalConsumed, 100, ''));
        fclose($out);
        exit;
    }

    /**
     * 项目级外包工时总览（项目左侧菜单页）。
     *
     * 展示当前项目团队内各外包人员的已消耗（主）/预计/剩余工时，支持日期范围筛选。
     *
     * @param int    $projectID 项目ID
     * @param string $begin     开始日期
     * @param string $end       结束日期
     */
    public function projectOverview($projectID = 0, $begin = '', $end = '')
    {
        $projectID = (int)$projectID;
        if($projectID <= 0 && $this->session->project) $projectID = (int)$this->session->project;

        /* POST：筛选表单提交，重定向到 GET */
        if($this->server->request_method == 'POST')
        {
            $projectID = (int)($this->post->projectID ?: $projectID);
            $begin     = str_replace('-', '_', $this->post->begin);
            $end       = str_replace('-', '_', $this->post->end);
            $this->locate(inlink('projectOverview', "projectID={$projectID}&begin={$begin}&end={$end}"));
            return;
        }

        /* 日期处理：默认本月 */
        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);
        if(empty($begin) || $begin == '-') $begin = date('Y-m-01');
        if(empty($end)   || $end   == '-') $end   = date('Y-m-t');

        /* 注意：不要在此手动调用 $this->project->setMenu($projectID)。
         * 项目应用壳层会按 URL 中的 projectID 自动构建项目菜单；手动 setMenu 会再次执行
         * checkAccess()/resetProjectPriv() 并改写 session->project，admin 在项目集(program)
         * 上下文下会因此与壳层项目上下文错位，被判非法而跳回「我的地盘」首页。
         * 参照同仓库可正常工作的 man_resource 项目页：同样不调用 setMenu。*/
        $project = $projectID > 0 ? $this->loadModel('project')->getByID($projectID) : null;

        $data = $this->waibao->getProjectOutsourcedMembers($projectID, $begin, $end);

        $this->view->title     = $this->lang->waibao->projectOverview;
        $this->view->project   = $project;
        $this->view->projectID = $projectID;
        $this->view->begin     = $begin;
        $this->view->end       = $end;
        $this->view->members   = isset($data['members']) ? $data['members'] : array();
        $this->view->totalRow  = isset($data['total']) ? $data['total'] : array('consumed' => 0, 'estimated' => 0, 'remain' => 0, 'taskCount' => 0);

        $this->display();
    }

    /**
     * 导出项目外包工时总览为 CSV（可直接用 Excel 打开）。
     *
     * @param int    $projectID 项目ID
     * @param string $begin     开始日期
     * @param string $end       结束日期
     */
    public function exportProjectOverview($projectID = 0, $begin = '', $end = '')
    {
        $projectID = (int)$projectID;
        if($projectID <= 0 && $this->session->project) $projectID = (int)$this->session->project;

        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);
        if(empty($begin) || $begin == '-') $begin = date('Y-m-01');
        if(empty($end)   || $end   == '-') $end   = date('Y-m-t');

        $data    = $this->waibao->getProjectOutsourcedMembers($projectID, $begin, $end);
        $members = isset($data['members']) ? $data['members'] : array();
        $total   = isset($data['total'])   ? $data['total']   : array('consumed' => 0, 'estimated' => 0, 'remain' => 0, 'taskCount' => 0);

        $project  = $projectID > 0 ? $this->loadModel('project')->getByID($projectID) : null;
        $projName = $project ? $project->name : $projectID;

        $filename = rawurlencode("项目外包工时_{$projName}_{$begin}_{$end}.csv");
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename*=UTF-8''{$filename}");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, array('姓名', '部门', '已消耗工时(h)', '预计工时(h)', '剩余工时(h)', '任务数'));
        foreach($members as $m)
        {
            fputcsv($out, array($m['realname'], $m['deptName'], $m['consumed'], $m['estimated'], $m['remain'], $m['taskCount']));
        }
        fputcsv($out, array('合计', '', $total['consumed'], $total['estimated'], $total['remain'], $total['taskCount']));
        fclose($out);
        exit;
    }
}
