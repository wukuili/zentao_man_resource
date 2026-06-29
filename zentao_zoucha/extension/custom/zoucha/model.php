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
        $execCountByProject = array(); // projectID => 未删除执行总数（含已关闭）——R4 需全量
        foreach($executions as $exec)
        {
            $pid = (int)$exec->project;
            if(!isset($execCountByProject[$pid])) $execCountByProject[$pid] = 0;
            $execCountByProject[$pid]++;                               // 全量计数
            if($exec->status === 'closed') $closedExecutionIDs[$exec->id] = true;
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
            $results[] = (object) array(
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
                'programName'    => implode('/', $programChain),
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
        foreach($executions as $exec)
        {
            if($exec->status === 'closed') $closedExecutionIDs[$exec->id] = true;
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
            count($executions),
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

    /**
     * 取某项目命中某条规则的「明细列表」，供列表页点击标签弹框展示。
     * 口径与 zouchaRules::evaluate 完全一致（同样排除已关闭执行下的任务）。
     *
     * @param int    $projectID
     * @param string $rule overdue|longTask|stale|noTask|noExecution
     * @return array ['project'=>string|null, 'rule'=>string, 'items'=>object[]]
     *               item 字段：id, name, statusName, ownerName, estStarted, deadline, lastActivity, spanDays
     */
    public function getRuleItems($projectID, $rule)
    {
        $projectID = (int)$projectID;
        $rule      = (string)$rule;

        $doneStatuses   = array('done', 'closed', 'cancel');           // R5 跨度排除
        $overdueExclude = array('done', 'closed', 'cancel', 'pause');  // R3 逾期排除
        $closedStatuses = array('closed', 'cancel');                   // R2 视为已关闭状态

        $longTaskDays = isset($this->config->zoucha->longTaskDays) ? (int)$this->config->zoucha->longTaskDays : 14;
        $today        = date('Y-m-d');
        $todayTs      = strtotime($today);

        /* 项目基本信息（仅在研、未删除项目） */
        $project = $this->dao->select('id, name')
            ->from(TABLE_PROJECT)
            ->where('id')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->fetch();
        if(!$project) return array('project' => null, 'rule' => $rule, 'items' => array());

        /* 已关闭执行（用于过滤其下任务） */
        $executions = $this->dao->select('id, status')
            ->from(TABLE_EXECUTION)
            ->where('project')->eq($projectID)
            ->andWhere('`type`')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->fetchAll('id');
        $closedExecutionIDs = array();
        foreach($executions as $exec)
        {
            if($exec->status === 'closed') $closedExecutionIDs[$exec->id] = true;
        }

        /* 取任务（含名称、指派人），过滤已关闭执行下的任务 */
        $rawTasks = $this->dao->select('id, name, execution, status, assignedTo, estStarted, deadline, openedDate, lastEditedDate')
            ->from(TABLE_TASK)
            ->where('project')->eq($projectID)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        $tasks = array();
        foreach($rawTasks as $t)
        {
            $execID = (int)$t->execution;
            if($execID !== 0 && isset($closedExecutionIDs[$execID])) continue;
            $tasks[] = $t;
        }

        /* 任务状态中文名 */
        $this->app->loadLang('task');
        $statusList = (isset($this->lang->task->statusList) && is_array($this->lang->task->statusList))
            ? $this->lang->task->statusList
            : array('wait' => '未开始', 'doing' => '进行中', 'done' => '已完成', 'pause' => '已暂停', 'cancel' => '已取消', 'closed' => '已关闭');

        /* 指派人真实姓名 */
        $accounts = array();
        foreach($tasks as $t) if(!empty($t->assignedTo)) $accounts[$t->assignedTo] = $t->assignedTo;
        $ownerNames = array();
        if(!empty($accounts))
        {
            $users = $this->dao->select('account, realname')->from(TABLE_USER)
                ->where('account')->in(array_values($accounts))->fetchAll('account');
            foreach($users as $u) $ownerNames[$u->account] = $u->realname;
        }

        /* 组装一行明细 */
        $makeItem = function($t) use ($statusList, $ownerNames)
        {
            $est  = zouchaModel::pickDate($t->estStarted);
            $dead = zouchaModel::pickDate($t->deadline);
            $act  = self::maxDate(zouchaModel::pickDateTime($t->openedDate), zouchaModel::pickDateTime($t->lastEditedDate));
            $span = ($est !== null && $dead !== null) ? (int)round((strtotime($dead) - strtotime($est)) / 86400) : null;
            return (object) array(
                'id'           => (int)$t->id,
                'name'         => (string)$t->name,
                'status'       => (string)$t->status,
                'statusName'   => isset($statusList[$t->status]) ? $statusList[$t->status] : (string)$t->status,
                'ownerName'    => (!empty($t->assignedTo) && isset($ownerNames[$t->assignedTo])) ? $ownerNames[$t->assignedTo] : (string)$t->assignedTo,
                'estStarted'   => $est !== null ? $est : '',
                'deadline'     => $dead !== null ? $dead : '',
                'lastActivity' => $act !== null ? substr($act, 0, 10) : '',
                'spanDays'     => $span,
            );
        };

        $items = array();
        switch($rule)
        {
            case 'overdue':  // 逾期任务：截止日早于今天，且状态非 完成/关闭/取消/暂停
                foreach($tasks as $t)
                {
                    $dead = self::pickDate($t->deadline);
                    if($dead !== null && strtotime($dead) < $todayTs && !in_array($t->status, $overdueExclude, true))
                        $items[] = $makeItem($t);
                }
                break;

            case 'longTask':  // 任务超期：计划工期 > longTaskDays，且任务非 完成/关闭/取消
                foreach($tasks as $t)
                {
                    $dead = self::pickDate($t->deadline);
                    $est  = self::pickDate($t->estStarted);
                    if($dead !== null && $est !== null && !in_array($t->status, $doneStatuses, true)
                        && ((strtotime($dead) - strtotime($est)) / 86400) > $longTaskDays)
                        $items[] = $makeItem($t);
                }
                break;

            case 'stale':  // 近一周未更新：列出全部未关闭任务及其最近活动，按活动时间升序（最久未动在前）
                foreach($tasks as $t)
                {
                    if(in_array($t->status, $closedStatuses, true)) continue;
                    $items[] = $makeItem($t);
                }
                usort($items, function($a, $b){ return strcmp((string)$a->lastActivity, (string)$b->lastActivity); });
                break;

            default:  // noTask / noExecution 等无明细列表，返回空集（前端展示规则说明）
                break;
        }

        return array('project' => (string)$project->name, 'rule' => $rule, 'items' => $items);
    }

    /* date 列规整：空/全零返回 null，否则 'Y-m-d' */
    public static function pickDate($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return substr($v, 0, 10);
    }

    /* datetime 列规整：空/全零返回 null，否则原值 */
    public static function pickDateTime($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return $v;
    }

    /* 两个日期字符串取较晚者，null 视为最小 */
    private static function maxDate($a, $b)
    {
        if($a === null) return $b;
        if($b === null) return $a;
        return ($a >= $b) ? $a : $b;
    }
}
