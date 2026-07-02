<?php
/**
 * 项目周报 — 模型层
 * 所有数据查询均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class zhoubaoModel extends model
{
    /* 把 week 参数（2026_06_29 或 ''）解析成本周一的 Y-m-d */
    public function resolveWeekStart($week = '')
    {
        if($week === '' || $week === false) return date('Y-m-d', strtotime('monday this week'));
        return str_replace('_', '-', $week);
    }

    /* 由周一日期得到本周范围与 ISO 年/周 */
    public function getWeekRange($weekStart)
    {
        $start = $weekStart;
        $end   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        return array(
            'start' => $start,
            'end'   => $end,
            'year'  => (int)date('o', strtotime($weekStart)),
            'week'  => (int)date('W', strtotime($weekStart)),
        );
    }

    /* 活跃项目：type=project、未删除、仅进行中（挂起/未开始/关闭均不纳入） */
    public function getActiveProjects()
    {
        return $this->dao->select('id, name, PM')->from(TABLE_PROJECT)
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->eq('doing')
            ->fetchAll('id');
    }

    /* 项目任务，排除已关闭执行下的任务（execution=0 保留） */
    public function getProjectTasks($projectIDs)
    {
        if(empty($projectIDs)) return array();

        $closedExec = $this->dao->select('id')->from(TABLE_EXECUTION)
            ->where('project')->in($projectIDs)
            ->andWhere('`type`')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->eq('closed')
            ->fetchPairs('id', 'id');

        $tasks = $this->dao->select('id, project, execution, name, assignedTo, status, deadline, finishedDate, consumed, `left`')
            ->from(TABLE_TASK)
            ->where('project')->in($projectIDs)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        $byProject = array();
        foreach($tasks as $task)
        {
            $execID = (int)$task->execution;
            if($execID !== 0 && isset($closedExec[$execID])) continue;
            $byProject[(int)$task->project][] = (array)$task;
        }
        return $byProject;
    }

    /* 某年某周所有已存周报，projectID => 行对象 */
    public function getReportMap($year, $week)
    {
        return $this->dao->select('*')->from('zt_zhoubao')
            ->where('year')->eq($year)
            ->andWhere('week')->eq($week)
            ->fetchAll('project');
    }

    /* 看板行 */
    public function getBoardRows($weekStart, $pm = '', $fill = '', $risk = '')
    {
        /* 加载规则引擎（无禅道依赖，可独立 php -l 校验） */
        if(!class_exists('zhoubaoRules'))
        {
            include_once __DIR__ . '/lib/zhoubaoRules.php';
        }

        $range    = $this->getWeekRange($weekStart);
        $today    = date('Y-m-d');
        $projects = $this->getActiveProjects();
        if(empty($projects)) return array();

        $tasksByProject = $this->getProjectTasks(array_keys($projects));
        $reportMap      = $this->getReportMap($range['year'], $range['week']);
        $pmNames        = $this->getPmNames($projects);

        $rows = array();
        foreach($projects as $pid => $project)
        {
            if($pm !== '' && $pm !== 'all' && $project->PM !== $pm) continue;

            $tasks = isset($tasksByProject[$pid]) ? $tasksByProject[$pid] : array();
            $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

            $report = isset($reportMap[$pid]) ? $reportMap[$pid] : null;
            $status = $report ? $report->status : 'none';

            if($fill !== '' && $fill !== 'all' && $status !== $fill) continue;

            /* 无周报的项目一律按"无风险"归类，供 risk 筛选使用 */
            $hasRisk = ($report && $report->hasRisk === 'yes') ? 'yes' : 'no';
            if($risk !== '' && $risk !== 'all' && $hasRisk !== $risk) continue;

            $rows[] = (object)array(
                'project'      => $pid,
                'projectName'  => $project->name,
                'pm'           => $project->PM,
                'pmName'       => isset($pmNames[$project->PM]) && $pmNames[$project->PM] !== '' ? $pmNames[$project->PM] : $project->PM,
                'status'       => $status,
                'hasRisk'      => $hasRisk,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
            );
        }
        return $rows;
    }

    /* 取项目 PM 账号对应的真实姓名，account => realname（与 zoucha 同款查询） */
    public function getPmNames($projects)
    {
        $pmAccounts = array();
        foreach($projects as $project) if(!empty($project->PM)) $pmAccounts[$project->PM] = $project->PM;
        if(empty($pmAccounts)) return array();

        $users = $this->dao->select('account, realname')
            ->from(TABLE_USER)
            ->where('account')->in(array_values($pmAccounts))
            ->fetchAll('account');

        $pmNames = array();
        foreach($users as $u) $pmNames[$u->account] = $u->realname;
        return $pmNames;
    }

    /* 账号 => 真实姓名，用于任务负责人展示（$accounts 为 account => account 的去重集合） */
    public function getUserNames($accounts)
    {
        if(empty($accounts)) return array();

        $users = $this->dao->select('account, realname')
            ->from(TABLE_USER)
            ->where('account')->in(array_values($accounts))
            ->fetchAll('account');

        $names = array();
        foreach($users as $u) $names[$u->account] = $u->realname;
        return $names;
    }

    /* 本周消耗工时：zt_effort.date 落在本周的 consumed 之和 */
    public function getWeekEffort($project, $start, $end)
    {
        return (float)$this->dao->select('SUM(consumed) AS v')->from(TABLE_EFFORT)
            ->where('project')->eq($project)
            ->andWhere('date')->ge($start)
            ->andWhere('date')->le($end)
            ->andWhere('deleted')->eq('0')
            ->fetch('v');
    }

    /* 组装自动区数据（实时） */
    public function buildAutoData($project, $weekStart)
    {
        if(!class_exists('zhoubaoRules'))
        {
            include_once __DIR__ . '/lib/zhoubaoRules.php';
        }

        $range = $this->getWeekRange($weekStart);
        $today = date('Y-m-d');
        $tasksByProject = $this->getProjectTasks(array($project));
        $tasks = isset($tasksByProject[$project]) ? $tasksByProject[$project] : array();
        $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

        /* 负责人账号 => 真实姓名，补进每条任务行，供模板展示（与 getPmNames 同款查询） */
        $accounts = array();
        foreach(array('done', 'undone', 'overdue') as $k)
        {
            foreach($cls[$k] as $t) if(!empty($t['assignedTo'])) $accounts[$t['assignedTo']] = $t['assignedTo'];
        }
        $userNames = $this->getUserNames($accounts);
        foreach(array('done', 'undone', 'overdue') as $k)
        {
            foreach($cls[$k] as &$t)
            {
                $t['assignedToName'] = isset($userNames[$t['assignedTo']]) && $userNames[$t['assignedTo']] !== ''
                    ? $userNames[$t['assignedTo']] : $t['assignedTo'];
            }
            unset($t);
        }

        $totalLeft = 0;
        foreach($cls['undone'] as $t)  $totalLeft += (float)(isset($t['left']) ? $t['left'] : 0);
        foreach($cls['overdue'] as $t) $totalLeft += (float)(isset($t['left']) ? $t['left'] : 0);

        $progress = (int)$this->dao->select('progress')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch('progress');

        $cls['stat'] = array(
            'progress'     => $progress,
            'weekConsumed' => $this->getWeekEffort($project, $range['start'], $range['end']),
            'totalLeft'    => $totalLeft,
            'doneCount'    => count($cls['done']),
            'overdueCount' => count($cls['overdue']),
        );
        $cls['zoucha'] = $this->getZouchaHits($project);
        return $cls;
    }

    /* 取该项目当前命中的走查失管规则名；zoucha 未安装/未命中则返回空数组 */
    public function getZouchaHits($project)
    {
        $libFile = $this->app->getAppRoot() . 'extension/custom/zoucha/lib/zouchaRules.php';
        if(!file_exists($libFile)) return array();

        try
        {
            $zouchaModel = $this->loadModel('zoucha');
            if(!method_exists($zouchaModel, 'inspect')) return array();

            $results = $zouchaModel->inspect();
            foreach($results as $r)
            {
                if(isset($r->projectID) && (int)$r->projectID === (int)$project) return (array)$r->hits;
            }
            return array();
        }
        catch(\Throwable $e)
        {
            return array();
        }
    }

    public function getReport($project, $weekStart)
    {
        $range = $this->getWeekRange($weekStart);
        return $this->dao->select('*')->from('zt_zhoubao')
            ->where('project')->eq($project)
            ->andWhere('year')->eq($range['year'])
            ->andWhere('week')->eq($range['week'])
            ->fetch();
    }

    /* 取上一自然周同项目周报 */
    public function getPrevReport($project, $weekStart)
    {
        $prevStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
        return $this->getReport($project, $prevStart);
    }

    /* 单项目迭代/阶段甘特图数据，用于周报页"项目态势"面板。
       TABLE_EXECUTION 就是 zt_project（迭代/阶段是 type=sprint/stage/kanban 的行，project 字段指向所属项目）。 */
    public function getProjectGantt($project)
    {
        $projectRow  = $this->dao->select('model, status, begin, end')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch();
        $modelLabels = array('waterfall' => '阶段甘特图', 'scrum' => '迭代甘特图', 'kanban' => '看板阶段');
        $modelKey    = $projectRow ? (string)$projectRow->model : '';
        $ganttTitle  = isset($modelLabels[$modelKey]) ? $modelLabels[$modelKey] : '项目进度甘特图';

        $executions = $this->dao->select('id, name, type, status, begin, end, realBegan, realEnd')
            ->from(TABLE_EXECUTION)
            ->where('project')->eq($project)
            ->andWhere('type')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->orderBy('begin_asc')
            ->fetchAll();

        $today = date('Y-m-d');
        $items = array();
        foreach($executions as $exec)
        {
            $start = (!empty($exec->realBegan) && $exec->realBegan !== '0000-00-00') ? $exec->realBegan : $exec->begin;
            $end   = (!empty($exec->realEnd)   && $exec->realEnd   !== '0000-00-00') ? $exec->realEnd   : $exec->end;
            if(empty($start) || $start === '0000-00-00') continue;
            if(empty($end) || $end === '0000-00-00') $end = date('Y-m-d', strtotime($start . ' +7 days'));

            $color = '#94a3b8';
            if($exec->status === 'doing')  $color = '#3b82f6';
            if($exec->status === 'closed') $color = '#10b981';
            if($end < $today && $exec->status !== 'closed') $color = '#ef4444';

            $items[] = array(
                'id'     => (int)$exec->id,
                'name'   => $exec->name,
                'type'   => $exec->type,
                'status' => $exec->status,
                'start'  => $start,
                'end'    => $end,
                'color'  => $color,
            );
        }

        return array('title' => $ganttTitle, 'items' => $items);
    }

    /* 保存草稿或提交；提交时固化 snapshot */
    public function saveReport($project, $weekStart, $post, $account, $submit)
    {
        $range = $this->getWeekRange($weekStart);
        $now   = helper::now();
        $exist = $this->getReport($project, $weekStart);

        /* 白名单校验，非 'yes' 一律按 'no' 处理 */
        $hasRisk = (isset($post['hasRisk']) && $post['hasRisk'] === 'yes') ? 'yes' : 'no';

        $data = new stdclass();
        $data->nextPlan  = isset($post['nextPlan']) ? $post['nextPlan'] : '';
        $data->hasRisk   = $hasRisk;
        /* 选"否"时服务端强制清空风险文本，防止前端隐藏后仍提交的残留值存进数据库/snapshot */
        $data->risk      = ($hasRisk === 'yes' && isset($post['risk'])) ? $post['risk'] : '';
        $data->summary   = isset($post['summary'])  ? $post['summary']  : '';
        $data->editedDate= $now;
        if($submit)
        {
            $data->status        = 'submitted';
            $data->submittedDate = $now;
            $data->snapshot      = json_encode($this->buildAutoData($project, $weekStart));
        }

        if($exist)
        {
            $this->dao->update('zt_zhoubao')->data($data)->where('id')->eq($exist->id)->exec();
            return dao::isError() ? false : $exist->id;
        }

        $data->project   = $project;
        $data->year      = $range['year'];
        $data->week      = $range['week'];
        $data->weekStart = $range['start'];
        $data->account   = $account;
        $data->status    = $submit ? 'submitted' : 'draft';
        $data->createdDate = $now;
        $this->dao->insert('zt_zhoubao')->data($data)->exec();
        return dao::isError() ? false : $this->dao->lastInsertID();
    }

    /* 组装当周填报 markdown 并 POST 到企微群机器人 */
    public function pushWecom($weekStart)
    {
        if(!class_exists('zhoubaoRules'))
        {
            include_once __DIR__ . '/lib/zhoubaoRules.php';
        }

        $webhook = isset($this->config->zhoubao->wecomWebhook) ? $this->config->zhoubao->wecomWebhook : '';
        if(empty($webhook)) return array('result' => 'fail', 'message' => '未配置企微 webhook');

        $range = $this->getWeekRange($weekStart);
        $rows  = $this->getBoardRows($weekStart, '', '');
        $mdRows = array();
        foreach($rows as $r) $mdRows[] = array('project'=>$r->projectName,'pm'=>$r->pm,'status'=>$r->status,'doneCount'=>$r->doneCount,'overdueCount'=>$r->overdueCount);
        $content = zhoubaoRules::buildWecomMarkdown($mdRows, $range['year'], $range['week']);

        $payload = json_encode(array('msgtype' => 'markdown', 'markdown' => array('content' => $content)));

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if($err) return array('result' => 'fail', 'message' => '推送失败：' . $err);
        return array('result' => 'success', 'message' => '推送成功', 'resp' => $resp);
    }

    /* 单项目历史周报，按周倒序；weeks='all' 不限制条数，否则 limit 最近 N 条（含草稿，用于看计划连续性） */
    public function getReportHistory($project, $weeks)
    {
        $query = $this->dao->select('*')->from('zt_zhoubao')
            ->where('project')->eq($project)
            ->orderBy('weekStart_desc');
        if($weeks !== 'all') $query->limit((int)$weeks);
        return $query->fetchAll();
    }
}
