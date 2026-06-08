<?php
/**
 * 外包标识插件 — 模型
 *
 * 所有业务逻辑：用户外包标识管理、工时统计（按外包/自有分组）、
 * 工作日计算、负载率分析等。
 */
class waibaoModel extends model
{
    /* ──────────────────────────────────────────────
     *  静态缓存
     * ────────────────────────────────────────────── */
    private static $closedProjectIDs = null;

    /* ──────────────────────────────────────────────
     *  用户外包标识管理
     * ────────────────────────────────────────────── */

    /**
     * 获取带外包标识的用户列表。
     *
     * @param  string $orderBy 排序字段
     * @access public
     * @return array
     */
    public function getUserListWithOutsourced($orderBy = 'id')
    {
        $allowedOrderBy = array('id', 'account', 'realname', 'role', 'dept', 'outsourced');
        if(!in_array($orderBy, $allowedOrderBy)) $orderBy = 'id';

        return $this->dao->select('id, account, realname, role, dept, outsourced')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->orderBy($orderBy)
            ->fetchAll();
    }

    /**
     * 更新单个用户的外包标识。
     *
     * @param  int $userID      用户ID
     * @param  int $outsourced  0=自有 1=外包
     * @access public
     * @return bool
     */
    public function updateUserOutsourced($userID, $outsourced)
    {
        $this->dao->update(TABLE_USER)
            ->set('outsourced')->eq($outsourced)
            ->where('id')->eq($userID)
            ->exec();
        return !dao::isError();
    }

    /**
     * 批量更新用户外包标识。
     *
     * @param  array $userIDs    用户ID数组
     * @param  int   $outsourced 0=自有 1=外包
     * @access public
     * @return bool
     */
    public function batchUpdateOutsourced($userIDs, $outsourced)
    {
        $userIDs = is_array($userIDs) ? $userIDs : explode(',', (string)$userIDs);
        $userIDs = array_values(array_unique(array_filter(array_map('intval', $userIDs))));
        if(empty($userIDs)) return false;

        $this->dao->update(TABLE_USER)
            ->set('outsourced')->eq($outsourced)
            ->where('id')->in($userIDs)
            ->exec();
        return !dao::isError();
    }

    /**
     * 获取所有外包人员账号。
     *
     * @access public
     * @return array account => realname
     */
    public function getOutsourcedAccounts()
    {
        return $this->dao->select('account, realname')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->andWhere('outsourced')->eq('1')
            ->fetchPairs('account', 'realname');
    }

    /**
     * 获取所有自有人员账号。
     *
     * @access public
     * @return array account => realname
     */
    public function getInternalAccounts()
    {
        return $this->dao->select('account, realname')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->andWhere('outsourced')->eq('0')
            ->fetchPairs('account', 'realname');
    }

    /**
     * 获取用户的外包标识映射（account => outsourced）。
     *
     * @param  array $accounts 指定账号列表，为空则返回全部
     * @access public
     * @return array account => int (0 或 1)
     */
    public function getOutsourcedMap($accounts = array())
    {
        $query = $this->dao->select('account, outsourced')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0');

        if(!empty($accounts))
        {
            $query->andWhere('account')->in($accounts);
        }

        return $query->fetchPairs('account', 'outsourced');
    }

    /* ──────────────────────────────────────────────
     *  组织维度：按部门统计外包/自有工时
     * ────────────────────────────────────────────── */

    /**
     * 获取组织维度工时数据（按部门+外包标识分组）。
     *
     * @param  int    $deptID  部门ID（0=全部）
     * @param  string $begin   开始日期 Y-m-d
     * @param  string $end     结束日期 Y-m-d
     * @param  string $status  任务状态 todo/done
     * @access public
     * @return array
     */
    public function getOrgHoursByOutsourced($deptID, $begin, $end, $status = 'todo')
    {
        /* 获取部门下的用户（含外包标识） */
        $deptList = array();
        if($deptID > 0)
        {
            $deptList = $this->loadModel('dept')->getAllChildId($deptID);
            $deptList[] = $deptID;
        }

        $users = $this->dao->select('account, realname, dept, outsourced')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->beginIF(!empty($deptList))->andWhere('dept')->in($deptList)->fi()
            ->fetchAll('account');

        if(empty($users)) return array('depts' => array(), 'summary' => array());

        $accounts = array_keys($users);
        $closedIDs = $this->getClosedProjectIDs();

        /* 查询任务数据 */
        $tasks = $this->dao->select('t.assignedTo, t.estimate, t.consumed, t.`left`, t.project, t.execution')
            ->from(TABLE_TASK)->alias('t')
            ->where('t.deleted')->eq('0')
            ->andWhere('t.assignedTo')->in($accounts)
            ->andWhere('t.status')->ne('cancel')
            ->andWhere('t.status')->ne('closed')
            ->beginIF($status == 'done')->andWhere('t.status')->eq('done')->fi()
            ->beginIF($status == 'todo')->andWhere('t.status')->ne('done')->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))
            ->andWhere('t.project')->notIn($closedIDs)
            ->fi()
            ->fetchAll();

        /* 查询团队任务（多人任务） */
        $teamTasks = $this->getTeamTasksForUsers($accounts, $status, $closedIDs);

        /* 合并任务数据 */
        $allTasks = array_merge($tasks, $teamTasks);

        /* 按部门和外包标识汇总 */
        $deptModel = $this->loadModel('dept');
        $result = array();
        $totalInternal = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'count' => 0);
        $totalOutsourced = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'count' => 0);

        foreach($users as $account => $user)
        {
            $deptID   = $user->dept;
            $deptName = $deptID > 0 ? zget($this->loadModel('dept')->getOptionMenu(), $deptID, '未分配部门') : '未分配部门';
            $isOutsourced = (int)$user->outsourced;

            if(!isset($result[$deptID]))
            {
                $result[$deptID] = array(
                    'deptID'   => $deptID,
                    'deptName' => $deptName,
                    'internal' => array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0),
                    'outsourced' => array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0),
                );
            }

            $typeKey = $isOutsourced ? 'outsourced' : 'internal';
            $result[$deptID][$typeKey]['memberCount']++;

            /* 统计该用户的任务工时 */
            $userEstimated = 0;
            $userConsumed  = 0;
            $userRemain    = 0;

            foreach($allTasks as $task)
            {
                if($task->assignedTo != $account && !isset($task->teamAccounts[$account])) continue;

                if(isset($task->teamAccounts[$account]))
                {
                    /* 团队任务：使用团队成员的个人工时 */
                    $userEstimated += $task->teamAccounts[$account]['estimate'];
                    $userConsumed  += $task->teamAccounts[$account]['consumed'];
                    $userRemain    += $task->teamAccounts[$account]['left'];
                }
                else
                {
                    $userEstimated += $task->estimate;
                    $userConsumed  += $task->consumed;
                    $userRemain    += $task->left;
                }
            }

            $result[$deptID][$typeKey]['estimated'] += $userEstimated;
            $result[$deptID][$typeKey]['consumed']  += $userConsumed;
            $result[$deptID][$typeKey]['remain']    += $userRemain;
            $result[$deptID][$typeKey]['taskCount'] += count(array_filter($allTasks, function($t) use ($account) {
                return $t->assignedTo == $account || isset($t->teamAccounts[$account]);
            }));

            /* 累计总计 */
            if($isOutsourced)
            {
                $totalOutsourced['estimated'] += $userEstimated;
                $totalOutsourced['consumed']  += $userConsumed;
                $totalOutsourced['remain']    += $userRemain;
                $totalOutsourced['count']++;
            }
            else
            {
                $totalInternal['estimated'] += $userEstimated;
                $totalInternal['consumed']  += $userConsumed;
                $totalInternal['remain']    += $userRemain;
                $totalInternal['count']++;
            }
        }

        return array(
            'depts' => $result,
            'summary' => array(
                'internal' => $totalInternal,
                'outsourced' => $totalOutsourced,
                'total' => array(
                    'estimated' => $totalInternal['estimated'] + $totalOutsourced['estimated'],
                    'consumed'  => $totalInternal['consumed']  + $totalOutsourced['consumed'],
                    'remain'    => $totalInternal['remain']    + $totalOutsourced['remain'],
                    'count'     => $totalInternal['count']     + $totalOutsourced['count'],
                )
            )
        );
    }

    /* ──────────────────────────────────────────────
     *  项目维度：按项目统计外包/自有工时
     * ────────────────────────────────────────────── */

    /**
     * 获取项目维度工时数据（按项目+外包标识分组）。
     *
     * @param  int    $projectID 项目ID（0=全部）
     * @param  string $begin     开始日期
     * @param  string $end       结束日期
     * @param  string $status    任务状态
     * @access public
     * @return array
     */
    public function getProjectHoursByOutsourced($projectID, $begin, $end, $status = 'todo')
    {
        $closedIDs = $this->getClosedProjectIDs();

        /* 获取项目列表 */
        $projectModel = $this->loadModel('project');
        if($projectID > 0)
        {
            $projects = array($projectID => $projectModel->getByID($projectID));
        }
        else
        {
            $projects = $projectModel->getList('doing', '0', 'all');
        }

        if(empty($projects)) return array('projects' => array(), 'summary' => array());

        $result = array();
        $totalInternal = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0);
        $totalOutsourced = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0);

        foreach($projects as $proj)
        {
            $pid = $proj->id;

            /* 获取项目团队成员（含外包标识） */
            $teamMembers = $this->dao->select('t.account, u.realname, u.outsourced')
                ->from(TABLE_TEAM)->alias('t')
                ->leftJoin(TABLE_USER)->alias('u')->on('t.account = u.account')
                ->where('t.root')->eq($pid)
                ->andWhere('t.type')->eq('project')
                ->andWhere('u.deleted')->eq('0')
                ->fetchAll('account');

            if(empty($teamMembers)) continue;

            $accounts = array_keys($teamMembers);

            /* 查询项目下的任务 */
            $tasks = $this->dao->select('assignedTo, estimate, consumed, `left`')
                ->from(TABLE_TASK)
                ->where('deleted')->eq('0')
                ->andWhere('project')->eq($pid)
                ->andWhere('assignedTo')->in($accounts)
                ->andWhere('status')->ne('cancel')
                ->andWhere('status')->ne('closed')
                ->beginIF($status == 'done')->andWhere('status')->eq('done')->fi()
                ->beginIF($status == 'todo')->andWhere('status')->ne('done')->fi()
                ->fetchAll();

            /* 查询团队任务 */
            $teamTasks = $this->getTeamTasksForProject($pid, $accounts, $status);

            $projData = array(
                'projectID'   => $pid,
                'projectName' => $proj->name,
                'internal'   => array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0),
                'outsourced' => array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'memberCount' => 0, 'taskCount' => 0),
            );

            foreach($teamMembers as $account => $member)
            {
                $isOutsourced = (int)$member->outsourced;
                $typeKey = $isOutsourced ? 'outsourced' : 'internal';
                $projData[$typeKey]['memberCount']++;

                $userEstimated = 0;
                $userConsumed  = 0;
                $userRemain    = 0;
                $userTaskCount = 0;

                foreach($tasks as $task)
                {
                    if($task->assignedTo != $account) continue;
                    $userEstimated += (float)$task->estimate;
                    $userConsumed  += (float)$task->consumed;
                    $userRemain    += (float)$task->left;
                    $userTaskCount++;
                }

                foreach($teamTasks as $task)
                {
                    if(!isset($task->teamAccounts[$account])) continue;
                    $userEstimated += (float)$task->teamAccounts[$account]['estimate'];
                    $userConsumed  += (float)$task->teamAccounts[$account]['consumed'];
                    $userRemain    += (float)$task->teamAccounts[$account]['left'];
                    $userTaskCount++;
                }

                $projData[$typeKey]['estimated'] += $userEstimated;
                $projData[$typeKey]['consumed']  += $userConsumed;
                $projData[$typeKey]['remain']    += $userRemain;
                $projData[$typeKey]['taskCount'] += $userTaskCount;

                if($isOutsourced)
                {
                    $totalOutsourced['estimated'] += $userEstimated;
                    $totalOutsourced['consumed']  += $userConsumed;
                    $totalOutsourced['remain']    += $userRemain;
                    $totalOutsourced['memberCount']++;
                    $totalOutsourced['taskCount'] += $userTaskCount;
                }
                else
                {
                    $totalInternal['estimated'] += $userEstimated;
                    $totalInternal['consumed']  += $userConsumed;
                    $totalInternal['remain']    += $userRemain;
                    $totalInternal['memberCount']++;
                    $totalInternal['taskCount'] += $userTaskCount;
                }
            }

            $result[$pid] = $projData;
        }

        return array(
            'projects' => $result,
            'summary' => array(
                'internal' => $totalInternal,
                'outsourced' => $totalOutsourced,
                'total' => array(
                    'estimated' => $totalInternal['estimated'] + $totalOutsourced['estimated'],
                    'consumed'  => $totalInternal['consumed']  + $totalOutsourced['consumed'],
                    'remain'    => $totalInternal['remain']    + $totalOutsourced['remain'],
                    'memberCount' => $totalInternal['memberCount'] + $totalOutsourced['memberCount'],
                    'taskCount' => $totalInternal['taskCount'] + $totalOutsourced['taskCount'],
                )
            )
        );
    }

    /* ──────────────────────────────────────────────
     *  成员维度：按成员统计外包/自有工时
     * ────────────────────────────────────────────── */

    /**
     * 获取成员维度工时数据。
     *
     * @param  string $userID    指定用户账号（空=全部）
     * @param  string $begin     开始日期
     * @param  string $end       结束日期
     * @param  string $status    任务状态
     * @access public
     * @return array
     */
    public function getMemberHoursByOutsourced($userID, $begin, $end, $status = 'todo')
    {
        $closedIDs = $this->getClosedProjectIDs();

        /* 获取用户列表 */
        $userQuery = $this->dao->select('account, realname, dept, outsourced')
            ->from(TABLE_USER)
            ->where('deleted')->eq('0');

        if(!empty($userID))
        {
            $userQuery->andWhere('account')->eq($userID);
        }

        $users = $userQuery->fetchAll('account');
        if(empty($users)) return array('members' => array(), 'summary' => array());

        $accounts = array_keys($users);

        /* 查询任务 */
        $taskQuery = $this->dao->select('id, assignedTo, estimate, consumed, `left`, project, execution')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('assignedTo')->in($accounts)
            ->andWhere('status')->ne('cancel')
            ->andWhere('status')->ne('closed')
            ->beginIF($status == 'done')->andWhere('status')->eq('done')->fi()
            ->beginIF($status == 'todo')->andWhere('status')->ne('done')->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))
            ->andWhere('project')->notIn($closedIDs)
            ->fi();

        $tasks = $taskQuery->fetchAll();

        /* 团队任务 */
        $teamTasks = $this->getTeamTasksForUsers($accounts, $status, $closedIDs);

        /* 获取部门名称映射 */
        $depts = $this->loadModel('dept')->getOptionMenu();

        $result = array();
        $totalInternal = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'taskCount' => 0);
        $totalOutsourced = array('estimated' => 0, 'consumed' => 0, 'remain' => 0, 'taskCount' => 0);

        foreach($users as $account => $user)
        {
            $isOutsourced = (int)$user->outsourced;
            $deptName = $user->dept > 0 ? zget($depts, $user->dept, '未分配部门') : '未分配部门';

            $userEstimated = 0;
            $userConsumed  = 0;
            $userRemain    = 0;
            $userTaskCount = 0;

            /* 统计直属任务 */
            foreach($tasks as $task)
            {
                if($task->assignedTo != $account) continue;
                $userEstimated += (float)$task->estimate;
                $userConsumed  += (float)$task->consumed;
                $userRemain    += (float)$task->left;
                $userTaskCount++;
            }

            /* 统计团队任务 */
            foreach($teamTasks as $task)
            {
                if(!isset($task->teamAccounts[$account])) continue;
                $userEstimated += (float)$task->teamAccounts[$account]['estimate'];
                $userConsumed  += (float)$task->teamAccounts[$account]['consumed'];
                $userRemain    += (float)$task->teamAccounts[$account]['left'];
                $userTaskCount++;
            }

            /* 计算负载率 */
            $workDays = $this->collectWorkingDays($begin, $end);
            $stdHours = $this->config->waibao->workHoursPerDay;
            $totalCapacity = $stdHours * count($workDays);
            $loadRate = $totalCapacity > 0 ? round(($userRemain / $totalCapacity) * 100, 1) : 0;

            $result[$account] = array(
                'account'     => $account,
                'realname'    => $user->realname,
                'deptName'    => $deptName,
                'outsourced'  => $isOutsourced,
                'outsourcedLabel' => $isOutsourced ? '外包' : '自有',
                'estimated'   => round($userEstimated, 2),
                'consumed'    => round($userConsumed, 2),
                'remain'      => round($userRemain, 2),
                'taskCount'   => $userTaskCount,
                'loadRate'    => $loadRate,
                'loadStatus'  => $this->getLoadStatus($loadRate),
            );

            if($isOutsourced)
            {
                $totalOutsourced['estimated'] += $userEstimated;
                $totalOutsourced['consumed']  += $userConsumed;
                $totalOutsourced['remain']    += $userRemain;
                $totalOutsourced['taskCount'] += $userTaskCount;
            }
            else
            {
                $totalInternal['estimated'] += $userEstimated;
                $totalInternal['consumed']  += $userConsumed;
                $totalInternal['remain']    += $userRemain;
                $totalInternal['taskCount'] += $userTaskCount;
            }
        }

        return array(
            'members' => $result,
            'summary' => array(
                'internal' => $totalInternal,
                'outsourced' => $totalOutsourced,
                'total' => array(
                    'estimated' => $totalInternal['estimated'] + $totalOutsourced['estimated'],
                    'consumed'  => $totalInternal['consumed']  + $totalOutsourced['consumed'],
                    'remain'    => $totalInternal['remain']    + $totalOutsourced['remain'],
                    'taskCount' => $totalInternal['taskCount'] + $totalOutsourced['taskCount'],
                )
            )
        );
    }

    /* ──────────────────────────────────────────────
     *  辅助方法
     * ────────────────────────────────────────────── */

    /**
     * 获取团队任务及成员工时分摊。
     *
     * @param  array  $accounts  用户账号列表
     * @param  string $status    任务状态
     * @param  array  $closedIDs 已关闭项目ID列表
     * @access public
     * @return array
     */
    private function getTeamTasksForUsers($accounts, $status = 'todo', $closedIDs = array())
    {
        /* 获取包含这些用户的团队任务ID */
        $teamTaskIDs = $this->dao->select('DISTINCT root')
            ->from(TABLE_TEAM)
            ->where('account')->in($accounts)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');

        if(empty($teamTaskIDs)) return array();

        /* 获取团队任务详情 */
        $tasks = $this->dao->select('id, assignedTo, estimate, consumed, `left`, project, execution')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('id')->in($teamTaskIDs)
            ->andWhere('status')->ne('cancel')
            ->andWhere('status')->ne('closed')
            ->beginIF($status == 'done')->andWhere('status')->eq('done')->fi()
            ->beginIF($status == 'todo')->andWhere('status')->ne('done')->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))
            ->andWhere('project')->notIn($closedIDs)
            ->fi()
            ->fetchAll('id');

        if(empty($tasks)) return array();

        /* 获取每个任务的团队成员工时分摊 */
        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        foreach($tasks as $taskID => $task)
        {
            $task->teamAccounts = isset($teamMap[$taskID]) ? $teamMap[$taskID] : array();
        }

        return $tasks;
    }

    /**
     * 获取项目维度团队任务及成员工时分摊。
     *
     * @param  int    $projectID 项目ID
     * @param  array  $accounts  用户账号列表
     * @param  string $status    任务状态
     * @access private
     * @return array
     */
    private function getTeamTasksForProject($projectID, $accounts, $status = 'todo')
    {
        $teamTaskIDs = $this->dao->select('DISTINCT t.root')
            ->from(TABLE_TEAM)->alias('t')
            ->where('t.account')->in($accounts)
            ->andWhere('t.type')->eq('task')
            ->fetchPairs('root', 'root');

        if(empty($teamTaskIDs)) return array();

        $closedIDs = $this->getClosedProjectIDs();

        $tasks = $this->dao->select('id, assignedTo, estimate, consumed, `left`, project, execution')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('id')->in($teamTaskIDs)
            ->andWhere('project')->eq($projectID)
            ->andWhere('status')->ne('cancel')
            ->andWhere('status')->ne('closed')
            ->beginIF($status == 'done')->andWhere('status')->eq('done')->fi()
            ->beginIF($status == 'todo')->andWhere('status')->ne('done')->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))
            ->andWhere('project')->notIn($closedIDs)
            ->fi()
            ->fetchAll('id');

        if(empty($tasks)) return array();

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        foreach($tasks as $taskID => $task)
        {
            $task->teamAccounts = isset($teamMap[$taskID]) ? $teamMap[$taskID] : array();
        }

        return $tasks;
    }

    /**
     * 获取任务团队映射（taskID => [account => {estimate, consumed, left}]）。
     *
     * @param  array $taskIDs 任务ID列表
     * @access public
     * @return array
     */
    public function getTaskTeamMap($taskIDs)
    {
        if(empty($taskIDs)) return array();

        $teams = $this->dao->select('root, account, estimate, consumed, `left`')
            ->from(TABLE_TEAM)
            ->where('type')->eq('task')
            ->andWhere('root')->in($taskIDs)
            ->fetchAll();

        $map = array();
        foreach($teams as $team)
        {
            $taskID = $team->root;
            if(!isset($map[$taskID])) $map[$taskID] = array();
            $map[$taskID][$team->account] = array(
                'estimate' => (float)$team->estimate,
                'consumed'  => (float)$team->consumed,
                'left'      => (float)$team->left,
            );
        }
        return $map;
    }

    /**
     * 获取已关闭的项目ID列表。
     *
     * @access public
     * @return array
     */
    public function getClosedProjectIDs()
    {
        if(self::$closedProjectIDs !== null) return self::$closedProjectIDs;

        $closedProjects = $this->dao->select('id')
            ->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('status')->eq('closed')
            ->fetchPairs('id', 'id');

        $closedExecutions = $this->dao->select('id')
            ->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('status')->eq('closed')
            ->andWhere('type')->eq('sprint')
            ->fetchPairs('id', 'id');

        self::$closedProjectIDs = array_merge($closedProjects, $closedExecutions);
        return self::$closedProjectIDs;
    }

    /**
     * 收集指定日期范围内的工作日。
     *
     * @param  string $begin 开始日期
     * @param  string $end   结束日期
     * @access public
     * @return array 日期数组
     */
    public function collectWorkingDays($begin, $end)
    {
        $workingDays = array();
        $holidays    = $this->getHolidays($begin, $end);

        $current = strtotime($begin);
        $endTs   = strtotime($end);
        while($current <= $endTs)
        {
            $dateStr  = date('Y-m-d', $current);
            $dayOfWeek = date('N', $current); // 1=Mon, 7=Sun

            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = isset($holidays[$dateStr]) && $holidays[$dateStr]['type'] == 'holiday';
            $isMakeup  = isset($holidays[$dateStr]) && $holidays[$dateStr]['type'] == 'working';

            if($isMakeup || (!$isWeekend && !$isHoliday))
            {
                $workingDays[] = $dateStr;
            }

            $current = strtotime('+1 day', $current);
        }
        return $workingDays;
    }

    /**
     * 获取指定日期范围内的节假日配置。
     *
     * @param  string $begin 开始日期
     * @param  string $end   结束日期
     * @access public
     * @return array date => array('type' => 'holiday'|'working')
     */
    public function getHolidays($begin, $end)
    {
        $holidays = $this->dao->select('*')
            ->from(TABLE_HOLIDAY)
            ->where('year')->ge(substr($begin, 0, 4))
            ->andWhere('year')->le(substr($end, 0, 4))
            ->fetchAll('date');

        $result = array();
        foreach($holidays as $date => $holiday)
        {
            $result[$date] = array('type' => $holiday->type);
        }
        return $result;
    }

    /**
     * 根据负载率返回状态标签。
     *
     * @param  float $loadRate 负载率百分比
     * @access public
     * @return string 状态标签
     */
    public function getLoadStatus($loadRate)
    {
        $range = $this->config->waibao->loadRange;
        if($loadRate <= $range['relax'])  return 'relax';
        if($loadRate <= $range['spare'])  return 'spare';
        if($loadRate <= $range['normal']) return 'normal';
        if($loadRate <= $range['full'])   return 'full';
        if($loadRate <= $range['over'])   return 'over';
        return 'extreme';
    }

    /* ──────────────────────────────────────────────
     *  项目总览区块：本项目组外包成员工时
     * ────────────────────────────────────────────── */

    /**
     * 获取某项目团队内外包成员的工时（已消耗为主，预计/剩余为辅）。
     *
     * 已消耗取自 zt_effort 工时日志（按人按天记录，天然支持团队任务）；
     * 预计/剩余取自 zt_task 当前快照。
     *
     * @param  int $projectID 项目ID
     * @access public
     * @return array array('members' => array, 'total' => array)
     */
    public function getProjectOutsourcedMembers($projectID)
    {
        $projectID = (int)$projectID;
        if($projectID <= 0) return array('members' => array(), 'total' => array('consumed' => 0, 'estimated' => 0, 'remain' => 0, 'taskCount' => 0));

        /* 项目团队中的外包成员 */
        $members = $this->dao->select('t.account, u.realname, u.dept')
            ->from(TABLE_TEAM)->alias('t')
            ->leftJoin(TABLE_USER)->alias('u')->on('t.account = u.account')
            ->where('t.root')->eq($projectID)
            ->andWhere('t.type')->eq('project')
            ->andWhere('u.deleted')->eq('0')
            ->andWhere('u.outsourced')->eq('1')
            ->fetchAll('account');

        if(empty($members)) return array('members' => array(), 'total' => array('consumed' => 0, 'estimated' => 0, 'remain' => 0, 'taskCount' => 0));

        $accounts  = array_keys($members);
        $closedIDs = $this->getClosedProjectIDs();
        $depts     = $this->loadModel('dept')->getOptionMenu();

        /* 已消耗：zt_effort 按账号汇总 */
        $consumedMap = $this->dao->select('account, ROUND(SUM(consumed), 1) AS consumed, COUNT(*) AS records')
            ->from(TABLE_EFFORT)
            ->where('objectType')->eq('task')
            ->andWhere('deleted')->eq('0')
            ->andWhere('project')->eq($projectID)
            ->andWhere('account')->in($accounts)
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))->andWhere('execution')->notIn($closedIDs)->fi()
            ->groupBy('account')
            ->fetchAll('account');

        /* 预计/剩余：直属任务 + 团队任务 */
        $tasks = $this->dao->select('assignedTo, estimate, consumed, `left`')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('project')->eq($projectID)
            ->andWhere('assignedTo')->in($accounts)
            ->andWhere('status')->ne('cancel')
            ->andWhere('status')->ne('closed')
            ->fetchAll();

        $teamTasks = $this->getTeamTasksForProject($projectID, $accounts, 'all');

        $result = array();
        $total  = array('consumed' => 0, 'estimated' => 0, 'remain' => 0, 'taskCount' => 0);
        foreach($members as $account => $member)
        {
            $estimated = 0;
            $remain    = 0;
            $taskCount = 0;
            foreach($tasks as $task)
            {
                if($task->assignedTo != $account) continue;
                $estimated += (float)$task->estimate;
                $remain    += (float)$task->left;
                $taskCount++;
            }
            foreach($teamTasks as $task)
            {
                if(!isset($task->teamAccounts[$account])) continue;
                $estimated += (float)$task->teamAccounts[$account]['estimate'];
                $remain    += (float)$task->teamAccounts[$account]['left'];
                $taskCount++;
            }

            $consumed = isset($consumedMap[$account]) ? (float)$consumedMap[$account]->consumed : 0;

            $result[$account] = array(
                'account'   => $account,
                'realname'  => $member->realname ? $member->realname : $account,
                'deptName'  => $member->dept > 0 ? zget($depts, $member->dept, '未分配部门') : '未分配部门',
                'consumed'  => round($consumed, 1),
                'estimated' => round($estimated, 1),
                'remain'    => round($remain, 1),
                'taskCount' => $taskCount,
            );

            $total['consumed']  += $consumed;
            $total['estimated'] += $estimated;
            $total['remain']    += $remain;
            $total['taskCount'] += $taskCount;
        }

        /* 已消耗高的排前面 */
        uasort($result, function($a, $b){ return $b['consumed'] <=> $a['consumed']; });

        $total['consumed']  = round($total['consumed'], 1);
        $total['estimated'] = round($total['estimated'], 1);
        $total['remain']    = round($total['remain'], 1);

        return array('members' => $result, 'total' => $total);
    }

    /* ──────────────────────────────────────────────
     *  多维汇总：按 时间/项目/迭代/部门/人员 汇总外包已消耗工时
     * ────────────────────────────────────────────── */

    /**
     * 多维外包工时汇总（基于 zt_effort，已消耗口径）。
     *
     * @param  string $begin     开始日期 Y-m-d
     * @param  string $end       结束日期 Y-m-d
     * @param  int    $dept      部门ID（0=全部，含子部门）
     * @param  int    $project   项目ID（0=全部）
     * @param  int    $execution 迭代ID（0=全部）
     * @param  string $groupBy   分组维度 member|project|execution|dept|month
     * @access public
     * @return array array('rows' => array, 'total' => float, 'groupBy' => string)
     */
    public function getOutsourcedSummary($begin, $end, $dept = 0, $project = 0, $execution = 0, $groupBy = 'member')
    {
        $allowed = array('member', 'project', 'execution', 'dept', 'month');
        if(!in_array($groupBy, $allowed)) $groupBy = 'member';

        $closedIDs = $this->getClosedProjectIDs();

        /* 部门及其子部门 */
        $deptList = array();
        if($dept > 0)
        {
            $deptList   = $this->loadModel('dept')->getAllChildId($dept);
            $deptList[] = $dept;
        }

        $efforts = $this->dao->select('e.account, e.project, e.execution, e.date, e.consumed, u.realname, u.dept')
            ->from(TABLE_EFFORT)->alias('e')
            ->leftJoin(TABLE_USER)->alias('u')->on('e.account = u.account')
            ->where('e.objectType')->eq('task')
            ->andWhere('e.deleted')->eq('0')
            ->andWhere('u.deleted')->eq('0')
            ->andWhere('u.outsourced')->eq('1')
            ->andWhere('e.date')->ge($begin)
            ->andWhere('e.date')->le($end)
            ->beginIF($project > 0)->andWhere('e.project')->eq($project)->fi()
            ->beginIF($execution > 0)->andWhere('e.execution')->eq($execution)->fi()
            ->beginIF(!empty($deptList))->andWhere('u.dept')->in($deptList)->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))->andWhere('e.project')->notIn($closedIDs)->fi()
            ->beginIF($this->config->waibao->excludeClosedProjects && !empty($closedIDs))->andWhere('e.execution')->notIn($closedIDs)->fi()
            ->fetchAll();

        if(empty($efforts)) return array('rows' => array(), 'total' => 0, 'groupBy' => $groupBy);

        /* 名称映射 */
        $depts      = $this->loadModel('dept')->getOptionMenu();
        $projectMap = $this->dao->select('id, name')->from(TABLE_PROJECT)->where('type')->eq('project')->fetchPairs('id', 'name');
        $execMap    = $this->dao->select('id, name')->from(TABLE_PROJECT)->where('type')->in('sprint,stage,kanban')->fetchPairs('id', 'name');

        $rows  = array();
        $total = 0;
        foreach($efforts as $effort)
        {
            $consumed = (float)$effort->consumed;
            $total   += $consumed;

            switch($groupBy)
            {
                case 'project':   $key = (int)$effort->project;   $name = $key ? zget($projectMap, $key, "#{$key}") : '未关联项目'; break;
                case 'execution': $key = (int)$effort->execution; $name = $key ? zget($execMap, $key, "#{$key}") : '未关联迭代'; break;
                case 'dept':      $key = (int)$effort->dept;       $name = $key ? zget($depts, $key, '未分配部门') : '未分配部门'; break;
                case 'month':     $key = substr($effort->date, 0, 7); $name = $key; break;
                case 'member':
                default:          $key = $effort->account; $name = ($effort->realname ? $effort->realname : $effort->account) . " ({$effort->account})"; break;
            }

            if(!isset($rows[$key])) $rows[$key] = array('key' => $key, 'name' => $name, 'consumed' => 0, 'records' => 0);
            $rows[$key]['consumed'] += $consumed;
            $rows[$key]['records']++;
        }

        /* 占比 + 取整 + 排序（按月升序，其它按已消耗降序） */
        foreach($rows as &$row)
        {
            $row['percent']  = $total > 0 ? round($row['consumed'] / $total * 100, 1) : 0;
            $row['consumed'] = round($row['consumed'], 1);
        }
        unset($row);

        if($groupBy == 'month') ksort($rows);
        else uasort($rows, function($a, $b){ return $b['consumed'] <=> $a['consumed']; });

        return array('rows' => array_values($rows), 'total' => round($total, 1), 'groupBy' => $groupBy);
    }

    /**
     * 获取项目下拉列表（id => name）。
     *
     * @access public
     * @return array id => name
     */
    public function getProjectPairs()
    {
        return $this->dao->select('id, name')
            ->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('type')->eq('project')
            ->orderBy('id_desc')
            ->fetchPairs('id', 'name');
    }

    /**
     * 获取迭代（执行）下拉列表。
     *
     * @param  int $projectID 项目ID（>0 时仅返回该项目下的迭代）
     * @access public
     * @return array id => name
     */
    public function getExecutionPairs($projectID = 0)
    {
        return $this->dao->select('id, name')
            ->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('type')->in('sprint,stage')
            ->beginIF($projectID > 0)->andWhere('project')->eq($projectID)->fi()
            ->orderBy('id_desc')
            ->fetchPairs('id', 'name');
    }
}
