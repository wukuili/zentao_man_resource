<?php
/**
 * 项目走查 — 模型层
 * 所有数据查询均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class zouchaModel extends model
{
    /**
     * 主走查入口：批量取在研项目，逐项调用规则引擎，返回命中规则的项目结果数组。
     *
     * 返回每项结构：
     *   projectID, projectName, pm, status, path,
     *   hits (string[]), taskCount, overdueCount, executionCount, lastTaskEdited,
     *   pmName, statusName, programName
     *
     * @return array
     */
    public function inspect()
    {
        /* 加载规则引擎（无禅道依赖，可独立 php -l 校验） */
        if(!class_exists('zouchaRules'))
        {
            include_once __DIR__ . '/lib/zouchaRules.php';
        }

        /* ── 1. 取配置阈值 ── */
        $staleDays    = isset($this->config->zoucha->staleDays)    ? (int)$this->config->zoucha->staleDays    : 7;
        $longTaskDays = isset($this->config->zoucha->longTaskDays) ? (int)$this->config->zoucha->longTaskDays : 14;
        $overdueMin   = isset($this->config->zoucha->overdueMin)   ? (int)$this->config->zoucha->overdueMin   : 1;
        $enabledRules = isset($this->config->zoucha->rules)        ? $this->config->zoucha->rules              : array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');
        $today        = date('Y-m-d');

        /* ── 2. 取在研项目（type=project，未删除，非关闭） ── */
        $projects = $this->dao->select('id, name, PM, status, path')
            ->from(TABLE_PROJECT)
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->ne('closed')
            ->fetchAll('id');

        if(empty($projects)) return array();

        $projectIDs = array_keys($projects);

        /* ── 3. 取执行（迭代/阶段/看板），不限状态，用于计数与识别已关闭执行 ── */
        $executions = $this->dao->select('id, project, status')
            ->from(TABLE_EXECUTION)
            ->where('project')->in($projectIDs)
            ->andWhere('`type`')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->fetchAll('id');

        /* 构建辅助映射 */
        $closedExecutionIDs = array(); // id => true，已关闭执行
        $execCountByProject = array(); // projectID => 未关闭执行数
        foreach($executions as $exec)
        {
            $pid = (int)$exec->project;
            if($exec->status === 'closed')
            {
                $closedExecutionIDs[$exec->id] = true;
            }
            else
            {
                if(!isset($execCountByProject[$pid])) $execCountByProject[$pid] = 0;
                $execCountByProject[$pid]++;
            }
        }

        /* ── 4. 取任务（所有未删除任务，稍后按执行过滤） ── */
        $tasks = $this->dao->select('id, project, execution, status, estStarted, deadline, openedDate, lastEditedDate')
            ->from(TABLE_TASK)
            ->where('project')->in($projectIDs)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        /* 按项目分组任务，过滤掉属于已关闭执行的任务（execution=0 保留） */
        $tasksByProject = array();
        foreach($tasks as $task)
        {
            $pid  = (int)$task->project;
            $execID = (int)$task->execution;
            /* execution=0 表示直接挂在项目下，保留；已关闭执行下的任务跳过 */
            if($execID !== 0 && isset($closedExecutionIDs[$execID])) continue;
            if(!isset($tasksByProject[$pid])) $tasksByProject[$pid] = array();
            $tasksByProject[$pid][] = $task;
        }

        /* ── 5. 取 PM 真实姓名 ── */
        $pmAccounts = array();
        foreach($projects as $proj)
        {
            if(!empty($proj->PM)) $pmAccounts[$proj->PM] = $proj->PM;
        }
        $pmNames = array(); // account => realname
        if(!empty($pmAccounts))
        {
            $users = $this->dao->select('account, realname')
                ->from(TABLE_USER)
                ->where('account')->in(array_values($pmAccounts))
                ->fetchAll('account');
            foreach($users as $u) $pmNames[$u->account] = $u->realname;
        }

        /* ── 6. 取项目集（program）名称链 ── */
        /* path 格式如 ",1,5,12,"，去掉首尾逗号后各段为祖先 ID（含自身项目），
         * type='program' 的节点即项目集；取出所有可能的祖先 ID 一次性查询。 */
        $allPathIDs = array();
        foreach($projects as $proj)
        {
            $pathStr = trim((string)$proj->path, ',');
            if($pathStr === '') continue;
            foreach(explode(',', $pathStr) as $seg)
            {
                $seg = (int)$seg;
                if($seg > 0) $allPathIDs[$seg] = $seg;
            }
        }
        $programNames = array(); // id => name
        if(!empty($allPathIDs))
        {
            $programs = $this->dao->select('id, name')
                ->from(TABLE_PROJECT)
                ->where('id')->in(array_values($allPathIDs))
                ->andWhere('`type`')->eq('program')
                ->andWhere('deleted')->eq('0')
                ->fetchAll('id');
            foreach($programs as $p) $programNames[(int)$p->id] = $p->name;
        }

        /* 状态中文名映射（读 lang，兜底硬编码） */
        $statusList = isset($this->lang->zoucha->projectStatusList)
            ? (array)$this->lang->zoucha->projectStatusList
            : array('wait' => '未开始', 'doing' => '进行中', 'suspended' => '挂起', 'closed' => '关闭');

        /* ── 7. 逐项目调用规则引擎，组装结果 ── */
        $results = array();
        foreach($projects as $proj)
        {
            $pid          = (int)$proj->id;
            $projectTasks = isset($tasksByProject[$pid]) ? $tasksByProject[$pid] : array();
            $execCount    = isset($execCountByProject[$pid]) ? $execCountByProject[$pid] : 0;

            $evaluated = zouchaRules::evaluate(
                $projectTasks,
                $execCount,
                $today,
                $staleDays,
                $longTaskDays,
                $overdueMin,
                $enabledRules
            );

            /* 仅收录有命中的项目 */
            if(empty($evaluated['hits'])) continue;

            /* 组装项目集名称链（取 path 中所有 program 节点，从祖先到最近，逗号分隔） */
            $programChain = array();
            $pathStr = trim((string)$proj->path, ',');
            if($pathStr !== '')
            {
                foreach(explode(',', $pathStr) as $seg)
                {
                    $seg = (int)$seg;
                    if($seg > 0 && isset($programNames[$seg])) $programChain[] = $programNames[$seg];
                }
            }

            $pmAccount  = (string)$proj->PM;
            $results[] = array(
                'projectID'      => $pid,
                'projectName'    => (string)$proj->name,
                'pm'             => $pmAccount,
                'status'         => (string)$proj->status,
                'path'           => (string)$proj->path,
                'hits'           => $evaluated['hits'],
                'taskCount'      => $evaluated['taskCount'],
                'overdueCount'   => $evaluated['overdueCount'],
                'executionCount' => $evaluated['executionCount'],
                'lastTaskEdited' => $evaluated['lastTaskEdited'],
                /* 富化字段 */
                'pmName'         => isset($pmNames[$pmAccount]) ? $pmNames[$pmAccount] : $pmAccount,
                'statusName'     => isset($statusList[$proj->status]) ? $statusList[$proj->status] : (string)$proj->status,
                'programName'    => implode(' / ', $programChain),
            );
        }

        return $results;
    }

    /**
     * 诊断方法：返回单个项目的原始取数数据（执行列表、任务列表、evaluate 结果），
     * 供 Task 4 的 inspectData 控制器方法调用，无需禅道实例即可通过 php -l 校验。
     *
     * @param int $projectID
     * @return array ['project'=>obj|null, 'executions'=>[], 'tasks'=>[], 'evaluated'=>[]]
     */
    public function getInspectData($projectID)
    {
        if(!class_exists('zouchaRules'))
        {
            include_once __DIR__ . '/lib/zouchaRules.php';
        }

        $projectID = (int)$projectID;

        $staleDays    = isset($this->config->zoucha->staleDays)    ? (int)$this->config->zoucha->staleDays    : 7;
        $longTaskDays = isset($this->config->zoucha->longTaskDays) ? (int)$this->config->zoucha->longTaskDays : 14;
        $overdueMin   = isset($this->config->zoucha->overdueMin)   ? (int)$this->config->zoucha->overdueMin   : 1;
        $enabledRules = isset($this->config->zoucha->rules)        ? $this->config->zoucha->rules              : array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');
        $today        = date('Y-m-d');

        /* 取项目基本信息 */
        $project = $this->dao->select('id, name, PM, status, path')
            ->from(TABLE_PROJECT)
            ->where('id')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->fetch();

        if(!$project) return array('project' => null, 'executions' => array(), 'tasks' => array(), 'evaluated' => array());

        /* 取执行 */
        $executions = $this->dao->select('id, project, status, name, `type`')
            ->from(TABLE_EXECUTION)
            ->where('project')->eq($projectID)
            ->andWhere('`type`')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->fetchAll('id');

        $closedExecutionIDs = array();
        $openExecCount      = 0;
        foreach($executions as $exec)
        {
            if($exec->status === 'closed')
            {
                $closedExecutionIDs[$exec->id] = true;
            }
            else
            {
                $openExecCount++;
            }
        }

        /* 取任务（含过滤） */
        $rawTasks = $this->dao->select('id, project, execution, status, estStarted, deadline, openedDate, lastEditedDate')
            ->from(TABLE_TASK)
            ->where('project')->eq($projectID)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        $filteredTasks = array();
        foreach($rawTasks as $task)
        {
            $execID = (int)$task->execution;
            if($execID !== 0 && isset($closedExecutionIDs[$execID])) continue;
            $filteredTasks[] = $task;
        }

        $evaluated = zouchaRules::evaluate(
            $filteredTasks,
            $openExecCount,
            $today,
            $staleDays,
            $longTaskDays,
            $overdueMin,
            $enabledRules
        );

        return array(
            'project'    => $project,
            'executions' => $executions,
            'tasks'      => $filteredTasks,
            'evaluated'  => $evaluated,
        );
    }
}
