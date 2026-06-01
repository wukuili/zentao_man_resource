<?php
class man_resourceModel extends model
{
    /**
     * Fetch per-member task team data from zt_team (type='task').
     * Returns [taskID => [account => ['estimate'=>..., 'consumed'=>..., 'left'=>..., 'hours'=>..., 'days'=>...]]]
     */
    /**
     * Debug helper: dump team task data for a user.
     */
    public function debugUserTeamData($userID, $begin, $end, $status)
    {
        $out = "=== DEBUG for account: {$userID} | begin={$begin} | end={$end} | status={$status} ===\n\n";

        /* 1. Team task IDs */
        $teamTaskIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->eq($userID)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $out .= "--- zt_team: " . count($teamTaskIDs) . " task IDs ---\n";
        $out .= implode(', ', $teamTaskIDs) . "\n\n";

        /* 2. AssignedTo tasks */
        $assignedTasks = $this->dao->select('id, name, assignedTo, status, project, estStarted, deadline, estimate, consumed, `left`, mode')->from(TABLE_TASK)
            ->where('assignedTo')->eq($userID)
            ->andWhere('deleted')->eq('0')
            ->fetchAll('id');
        $out .= "--- assignedTo tasks: " . count($assignedTasks) . " ---\n";
        foreach($assignedTasks as $t)
        {
            $out .= "  #{$t->id} status={$t->status} project={$t->project} est={$t->estimate} consumed={$t->consumed} left={$t->left} mode={$t->mode} estStarted={$t->estStarted} deadline={$t->deadline}\n";
        }
        $out .= "\n";

        /* 3. Team rows for teamTaskIDs */
        if(!empty($teamTaskIDs))
        {
            $teamRows = $this->dao->select('root, account, estimate, consumed, `left`, hours, days')->from(TABLE_TEAM)
                ->where('root')->in($teamTaskIDs)
                ->andWhere('type')->eq('task')
                ->fetchAll();
            $out .= "--- zt_team rows: " . count($teamRows) . " ---\n";
            foreach($teamRows as $r)
            {
                $out .= "  task#{$r->root} account={$r->account} est={$r->estimate} consumed={$r->consumed} left={$r->left}\n";
            }
            $out .= "\n";

            /* 4. Extra tasks (team but not assignedTo) */
            $additionalIDs = array_diff($teamTaskIDs, array_keys($assignedTasks));
            if(!empty($additionalIDs))
            {
                $extraTasks = $this->dao->select('id, name, assignedTo, status, project, estStarted, deadline, estimate, consumed, `left`, mode')->from(TABLE_TASK)
                    ->where('id')->in($additionalIDs)
                    ->andWhere('deleted')->eq('0')
                    ->fetchAll('id');
                $out .= "--- extra team tasks (not assignedTo): " . count($extraTasks) . " ---\n";
                foreach($extraTasks as $t)
                {
                    $out .= "  #{$t->id} status={$t->status} project={$t->project} est={$t->estimate} consumed={$t->consumed} left={$t->left} mode={$t->mode} estStarted={$t->estStarted} deadline={$t->deadline}\n";
                }
            }
            else
            {
                $out .= "--- no extra team tasks ---\n";
            }
            $out .= "\n";
        }

        /* 5. Final load rate result */
        $load = $this->getUserLoadRate($userID, $begin, $end, $status, 0);
        $out .= "--- getUserLoadRate result ---\n";
        $out .= "  estimated_hours={$load['estimated_hours']} consumed_hours={$load['consumed_hours']} remain_hours={$load['remain_hours']} parallel_tasks={$load['parallel_tasks']} load_rate={$load['load_rate']}%\n";

        return $out;
    }

    public function getTaskTeamMap($taskIDs)
    {
        if(empty($taskIDs)) return array();
        $rows = $this->dao->select('root, account, estimate, consumed, `left`, hours, days')
            ->from(TABLE_TEAM)
            ->where('root')->in($taskIDs)
            ->andWhere('type')->eq('task')
            ->fetchAll();
        $map = array();
        foreach($rows as $row)
        {
            $taskID  = (int)$row->root;
            $account = $row->account;
            if(!isset($map[$taskID])) $map[$taskID] = array();
            $map[$taskID][$account] = array(
                'estimate' => (float)$row->estimate,
                'consumed' => (float)$row->consumed,
                'left'     => (float)$row->left,
                'hours'    => (float)$row->hours,
                'days'     => (int)$row->days,
            );
        }
        return $map;
    }

    /**
     * Get org calendar data
     */
    public function getOrgCalendarData($begin, $end, $status, $depts = '', $roles = '', $users = '', $project = 0, $showHoliday = 0)
    {
        $deptList = !empty($depts) ? explode(',', (string)$depts) : array();
        $roleList = !empty($roles) ? explode(',', (string)$roles) : array();
        $userList = !empty($users) ? explode(',', (string)$users) : array();

        $accounts = $this->dao->select('account, realname')->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->beginIF($deptList)->andWhere('dept')->in($deptList)->fi()
            ->beginIF($roleList)->andWhere('role')->in($roleList)->fi()
            ->beginIF($userList)->andWhere('account')->in($userList)->fi();

        if($project)
        {
            $teamUsers = $this->dao->select('account')->from(TABLE_TEAM)->where('root')->eq($project)->andWhere('type')->eq('project')->fetchPairs('account', 'account');
            if(!empty($teamUsers)) $accounts->andWhere('account')->in($teamUsers);
        }

        $usersFound = $accounts->fetchAll('account');
        
        $data = array();
        foreach($usersFound as $account => $user)
        {
            $data[$account] = $this->getUserLoadRate($account, $begin, $end, $status, $showHoliday, $project);
            $data[$account]['realname'] = $user->realname;
        }

        uasort($data, function($a, $b){
            if($a['load_rate'] == $b['load_rate']) return 0;
            return $a['load_rate'] < $b['load_rate'] ? 1 : -1;
        });

        return $data;
    }

    /**
     * Get project calendar data
     */
    public function getProjectCalendarData($projectID, $begin, $end, $status, $users = '', $showHoliday = 0)
    {
        $teamMembers = $this->dao->select('account')->from(TABLE_TEAM)
            ->where('root')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->beginIF($users)->andWhere('account')->in($users)->fi()
            ->fetchAll('account');
        $userPairs = $this->loadModel('user')->getPairs('noletter');

        $data = array();
        foreach($teamMembers as $account => $member)
        {
            $data[$account] = $this->getUserLoadRate($account, $begin, $end, $status, $showHoliday, $projectID);
            $data[$account]['realname'] = zget($userPairs, $account, $account);
        }

        uasort($data, function($a, $b){
            if($a['load_rate'] == $b['load_rate']) return 0;
            return $a['load_rate'] < $b['load_rate'] ? 1 : -1;
        });

        return $data;
    }
    
    /**
     * Get user calendar data
     */
    public function getUserCalendarData($userID, $begin, $end, $status, $showHoliday = 0)
    {
        return $this->getUserLoadRate($userID, $begin, $end, $status, $showHoliday);
    }
    
    /**
     * Calculate user load rate over a period
     */
    public function getUserLoadRate($userID, $begin, $end, $status, $showHoliday = 0, $projectID = 0)
    {
        $stdHours = $this->config->man_resource->workHoursPerDay;

        /* Calculate working days in the period */
        $workDays = 0;
        $current = strtotime($begin);
        $last    = strtotime($end);
        
        $holidays = array();
        if($showHoliday)
        {
            $holidays = $this->dao->select('*')->from(TABLE_HOLIDAY)
                ->where('begin')->ge($begin)
                ->andWhere('end')->le($end)
                ->fetchAll('begin');
        }

        while($current <= $last)
        {
            $date = date('Y-m-d', $current);
            $day  = date('w', $current);
            
            $isWorkDay = ($day != 0 && $day != 6); // Default: not weekend
            if($showHoliday && isset($holidays[$date]))
            {
                $holiday = $holidays[$date];
                if($holiday->type == 'holiday') $isWorkDay = false;
                if($holiday->type == 'working') $isWorkDay = true;
            }
            
            if($isWorkDay) $workDays++;
            $current += 86400;
        }
        if($workDays == 0) $workDays = 1;

        $tasks = $this->dao->select('*')->from(TABLE_TASK)
            ->where('assignedTo')->eq($userID)
            ->andWhere('deleted')->eq('0')
            ->beginIF($begin)->andWhere("(deadline >= '$begin' OR deadline = '0000-00-00' OR deadline IS NULL OR deadline = '')")->fi()
            ->beginIF($end)->andWhere("(estStarted <= '$end' OR estStarted = '0000-00-00' OR estStarted IS NULL OR estStarted = '')")->fi()
            ->fetchAll('id');

        /* Also fetch multi-person tasks where user is a team member but not assignedTo. */
        /* For team tasks, do not apply date filters — members' committed hours
           in zt_team should always be counted regardless of task date range. */
        $teamTaskIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->eq($userID)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $additionalIDs = array_diff($teamTaskIDs, array_keys($tasks));
        if(!empty($additionalIDs))
        {
            $extraTasks = $this->dao->select('*')->from(TABLE_TASK)
                ->where('id')->in($additionalIDs)
                ->andWhere('deleted')->eq('0')
                ->fetchAll('id');
            $tasks = $tasks + $extraTasks;
        }

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        $estimated = 0;
        $consumed  = 0;
        $remain    = 0;
        $parallelTasks = 0;

        $taskPredictOn = !empty($this->config->man_resource->taskHourPredict);
        $predictPerTask = (float)(isset($this->config->man_resource->predictHours) ? $this->config->man_resource->predictHours : 0);

        foreach($tasks as $task) {
            /* Multi-person task: use per-member hours from zt_team. */
            $taskTeam = isset($teamMap[$task->id]) ? $teamMap[$task->id] : array();
            $isTeamTask = !empty($taskTeam) && isset($taskTeam[$userID]);

            if($isTeamTask)
            {
                $memberInfo     = $taskTeam[$userID];
                $memberEst      = $memberInfo['estimate'];
                $memberConsumed = $memberInfo['consumed'];
                $memberLeft     = $memberInfo['left'];

                /* For team tasks: include the member's hours whenever they participated.
                 * - 'todo' mode: task is active AND (member has remaining OR has estimate)
                 * - 'done' mode: member has consumed work OR task is fully done
                 */
                $taskActive    = ($task->status != 'done' && $task->status != 'closed' && $task->status != 'cancel');
                $memberHasData = ($memberEst > 0 || $memberConsumed > 0 || $memberLeft > 0);

                $isActive = ($status == 'todo' && $taskActive && $memberHasData)
                         || ($status == 'done' && ($memberConsumed > 0 || in_array($task->status, array('done', 'closed'))));
                if(!$isActive) continue;

                if($taskPredictOn && $memberLeft <= 0 && $predictPerTask > 0 && $status == 'todo') $memberLeft = $predictPerTask;
                $estimated += $memberEst;
                $consumed  += $memberConsumed;
                $remain    += $memberLeft;
                $parallelTasks++;
            }
            else
            {
                $isActive = ($status == 'todo' && $task->status != 'done' && $task->status != 'closed' && $task->status != 'cancel')
                         || ($status == 'done' && ($task->status == 'done' || $task->status == 'closed'));
                if(!$isActive) continue;

                if($task->assignedTo == $userID)
                {
                    $estimated += (float)$task->estimate;
                    $consumed  += (float)$task->consumed;
                    $left = (float)$task->left;
                    if($taskPredictOn && $left <= 0 && $predictPerTask > 0 && $status == 'todo') $left = $predictPerTask;
                    $remain    += $left;
                    $parallelTasks++;
                }
            }
        }

        /* Non-task todos: count only when notTaskHourPredict is enabled. */
        $notTaskOn = !empty($this->config->man_resource->notTaskHourPredict);
        if($notTaskOn && $predictPerTask > 0)
        {
            $todoCondStatus = $status == 'todo' ? "status IN ('wait','doing')" : "status IN ('done','closed')";
            $todoCount = (int)$this->dao->select('COUNT(*) AS c')->from(TABLE_TODO)
                ->where('deleted')->eq('0')
                ->andWhere('assignedTo')->eq($userID)
                ->andWhere("type != 'task'")
                ->andWhere($todoCondStatus)
                ->andWhere('date')->between($begin, $end)
                ->fetch('c');
            if($todoCount > 0)
            {
                $todoHours = $todoCount * $predictPerTask;
                if($status == 'todo')
                {
                    $remain        += $todoHours;
                    $estimated     += $todoHours;
                    $parallelTasks += $todoCount;
                }
                else
                {
                    $consumed      += $todoHours;
                    $estimated     += $todoHours;
                    $parallelTasks += $todoCount;
                }
            }
        }
        
        /* Calculate load rate based on total available hours in the period */
        $totalAvailableHours = $stdHours * $workDays;
        $loadRate = $totalAvailableHours > 0 ? round(($remain / $totalAvailableHours) * 100, 2) : 0;
        
        return array(
            'userID' => $userID,
            'estimated_hours' => round($estimated, 1),
            'consumed_hours'  => round($consumed, 1),
            'remain_hours'    => round($remain, 1),
            'load_rate'       => $loadRate,
            'parallel_tasks'  => $parallelTasks,
            'load_status'     => $this->getLoadStatus($loadRate)
        );
    }
    
    /**
     * Determine load status string based on configured ranges
     */
    public function getLoadStatus($loadRate)
    {
        $loadRange = isset($this->config->man_resource->loadRange) ? $this->config->man_resource->loadRange : array();
        
        $relax  = isset($loadRange['relax']) ? (float)$loadRange['relax'] : 50;
        $spare  = isset($loadRange['spare']) ? (float)$loadRange['spare'] : 70;
        $normal = isset($loadRange['normal']) ? (float)$loadRange['normal'] : 90;
        $full   = isset($loadRange['full']) ? (float)$loadRange['full'] : 100;
        $over   = isset($loadRange['over']) ? (float)$loadRange['over'] : 120;
        
        if($loadRate < $relax)  return 'relax';
        if($loadRate < $spare)  return 'spare';
        if($loadRate < $normal) return 'normal';
        if($loadRate < $full)   return 'full';
        if($loadRate < $over)   return 'over';
        return 'extreme';
    }

    /**
     * Build a per-day load series for a set of accounts.
     * Returns:
     *   array(
     *     'dates'   => ['Y-m-d', ...],
     *     'series'  => [account => ['realname' => ..., 'rates' => [%, %, ...], 'hours' => [h, h, ...]]],
     *     'overall' => [%, %, ...]   // average across all accounts per day
     *   )
     *
     * todo mode spreads task->estimate, done mode spreads task->consumed.
     */
    public function getDailyLoadSeries($accounts, $begin, $end, $status)
    {
        if(!is_array($accounts)) $accounts = array();
        $stdHours = (float)(isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8);
        if($stdHours <= 0) $stdHours = 8;

        $holidays = $this->dao->select('begin, type')->from(TABLE_HOLIDAY)->fetchPairs('begin', 'type');
        $dates    = $this->collectWorkingDays(strtotime($begin), strtotime($end), $holidays);

        $userPairs = $this->loadModel('user')->getPairs('noletter');
        $series    = array();
        foreach($accounts as $account)
        {
            $series[$account] = array(
                'realname' => zget($userPairs, $account, $account),
                'hours'    => array_fill(0, count($dates), 0.0)
            );
        }
        if(empty($accounts) || empty($dates)) return array('dates' => $dates, 'series' => $series, 'overall' => array());

        $tasks = $this->dao->select('id, assignedTo, estimate, consumed, status, estStarted, deadline, finishedDate, mode')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('assignedTo')->in($accounts)
            ->beginIF($status == 'todo')->andWhere('status')->notIN('done,closed,cancel')->fi()
            ->beginIF($status == 'done')->andWhere('status')->in('done,closed')->fi()
            ->beginIF($begin)->andWhere("(deadline >= '$begin' OR deadline = '0000-00-00' OR deadline IS NULL OR deadline = '')")->fi()
            ->beginIF($end)->andWhere("(estStarted <= '$end' OR estStarted = '0000-00-00' OR estStarted IS NULL OR estStarted = '')")->fi()
            ->fetchAll('id');

        /* Also fetch multi-person tasks where team members are in the account list. */
        $teamTaskIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->in($accounts)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $additionalIDs = array_diff($teamTaskIDs, array_keys($tasks));
        if(!empty($additionalIDs))
        {
            $extraTasks = $this->dao->select('id, assignedTo, estimate, consumed, status, estStarted, deadline, finishedDate, mode')
                ->from(TABLE_TASK)
                ->where('id')->in($additionalIDs)
                ->andWhere('deleted')->eq('0')
                ->beginIF($status == 'todo')->andWhere('status')->notIN('done,closed,cancel')->fi()
                ->beginIF($status == 'done')->andWhere('status')->in('done,closed')->fi()
                ->beginIF($begin)->andWhere("(deadline >= '$begin' OR deadline = '0000-00-00' OR deadline IS NULL OR deadline = '')")->fi()
                ->beginIF($end)->andWhere("(estStarted <= '$end' OR estStarted = '0000-00-00' OR estStarted IS NULL OR estStarted = '')")->fi()
                ->fetchAll('id');
            $tasks = $tasks + $extraTasks;
        }

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        $beginTs   = strtotime($begin);
        $endTs     = strtotime($end);
        $dateIndex = array_flip($dates);

        $taskPredictOn  = !empty($this->config->man_resource->taskHourPredict);
        $predictPerTask = (float)(isset($this->config->man_resource->predictHours) ? $this->config->man_resource->predictHours : 0);

        foreach($tasks as $task)
        {
            $taskTeam = isset($teamMap[$task->id]) ? $teamMap[$task->id] : array();
            $isMulti  = !empty($taskTeam);

            $taskBeginTs = ($task->estStarted && $task->estStarted != '0000-00-00') ? strtotime($task->estStarted) : $beginTs;
            $taskEndTs   = ($task->deadline   && $task->deadline   != '0000-00-00') ? strtotime($task->deadline)   : $endTs;
            $clipBegin   = max($taskBeginTs, $beginTs);
            $clipEnd     = min($taskEndTs,   $endTs);
            if($clipBegin > $clipEnd) continue;

            $taskDays = $this->collectWorkingDays($clipBegin, $clipEnd, $holidays);
            if(empty($taskDays)) continue;

            if($isMulti)
            {
                /* Multi-person task: spread each member's hours across the task days. */
                foreach($taskTeam as $account => $memberInfo)
                {
                    if(!isset($series[$account])) continue;
                    $totalHours = $status == 'done' ? $memberInfo['consumed'] : $memberInfo['estimate'];
                    if($status == 'todo' && $totalHours <= 0 && $taskPredictOn && $predictPerTask > 0) $totalHours = $predictPerTask;
                    if($totalHours <= 0) continue;
                    $perDay = $totalHours / count($taskDays);
                    foreach($taskDays as $date)
                    {
                        if(!isset($dateIndex[$date])) continue;
                        $series[$account]['hours'][$dateIndex[$date]] += $perDay;
                    }
                }
            }
            else
            {
                /* Single-person task: original logic. */
                if(!isset($series[$task->assignedTo])) continue;

                $totalHours = $status == 'done' ? (float)$task->consumed : (float)$task->estimate;
                if($status == 'todo' && $totalHours <= 0 && $taskPredictOn && $predictPerTask > 0) $totalHours = $predictPerTask;
                if($totalHours <= 0) continue;

                $perDay = $totalHours / count($taskDays);
                foreach($taskDays as $date)
                {
                    if(!isset($dateIndex[$date])) continue;
                    $series[$task->assignedTo]['hours'][$dateIndex[$date]] += $perDay;
                }
            }
        }

        $overall = array_fill(0, count($dates), 0.0);
        foreach($series as $account => &$row)
        {
            $row['rates'] = array();
            foreach($row['hours'] as $i => $h)
            {
                $rate = $stdHours > 0 ? round(($h / $stdHours) * 100, 1) : 0;
                $row['rates'][$i] = $rate;
                $overall[$i]     += $rate;
            }
            $row['hours'] = array_map(function($v){ return round($v, 2); }, $row['hours']);
        }
        unset($row);

        $count = max(1, count($accounts));
        foreach($overall as $i => $sum) $overall[$i] = round($sum / $count, 1);

        return array('dates' => $dates, 'series' => $series, 'overall' => $overall);
    }

    /**
     * Build gantt-ready dataset for the project dimension page.
     *
     * Returns one swim-lane per project member, each with the tasks assigned to them
     * whose [estStarted, deadline] window intersects [begin, end]. Tasks missing
     * either estStarted or deadline (or holding the '0000-00-00' placeholder) are
     * skipped because they cannot be plotted on a time axis.
     *
     * @return array {
     *   lanes: array<account, array{realname:string, tasks:array<int, array>}>
     * }
     */
    public function getProjectGanttData($projectID, $begin, $end, $users = '')
    {
        $teamMembers = $this->dao->select('account')->from(TABLE_TEAM)
            ->where('root')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->beginIF($users)->andWhere('account')->in($users)->fi()
            ->fetchPairs('account', 'account');
        if(empty($teamMembers)) return array('lanes' => array());

        $userPairs = $this->loadModel('user')->getPairs('noletter');

        $tasks = $this->dao->select('t1.id, t1.name, t1.assignedTo, t1.status, t1.estimate, t1.estStarted, t1.deadline, t1.project, t1.execution, t1.mode, t2.name AS projectName, t3.name AS executionName')
            ->from(TABLE_TASK)->alias('t1')
            ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project = t2.id')
            ->leftJoin(TABLE_PROJECT)->alias('t3')->on('t1.execution = t3.id')
            ->where('t1.deleted')->eq('0')
            ->andWhere('t1.assignedTo')->in($teamMembers)
            ->andWhere('t1.status')->notIN('cancel,closed')
            ->andWhere('t1.estStarted')->ne('')
            ->andWhere('t1.estStarted')->ne('0000-00-00')
            ->andWhere('t1.deadline')->ne('')
            ->andWhere('t1.deadline')->ne('0000-00-00')
            ->andWhere("t1.estStarted <= '$end'")
            ->andWhere("t1.deadline >= '$begin'")
            ->orderBy('t1.estStarted_asc, t1.deadline_asc, t1.id_asc')
            ->fetchAll('id');

        /* Also fetch multi-person tasks where team members are in the project team. */
        $taskTeamIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->in($teamMembers)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $additionalIDs = array_diff($taskTeamIDs, array_keys($tasks));
        if(!empty($additionalIDs))
        {
            $extraTasks = $this->dao->select('t1.id, t1.name, t1.assignedTo, t1.status, t1.estimate, t1.estStarted, t1.deadline, t1.project, t1.execution, t1.mode, t2.name AS projectName, t3.name AS executionName')
                ->from(TABLE_TASK)->alias('t1')
                ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project = t2.id')
                ->leftJoin(TABLE_PROJECT)->alias('t3')->on('t1.execution = t3.id')
                ->where('t1.id')->in($additionalIDs)
                ->andWhere('t1.deleted')->eq('0')
                ->andWhere('t1.status')->notIN('cancel,closed')
                ->andWhere('t1.estStarted')->ne('')
                ->andWhere('t1.estStarted')->ne('0000-00-00')
                ->andWhere('t1.deadline')->ne('')
                ->andWhere('t1.deadline')->ne('0000-00-00')
                ->andWhere("t1.estStarted <= '$end'")
                ->andWhere("t1.deadline >= '$begin'")
                ->orderBy('t1.estStarted_asc, t1.deadline_asc, t1.id_asc')
                ->fetchAll('id');
            $tasks = $tasks + $extraTasks;
        }

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        $lanes = array();
        foreach($tasks as $task)
        {
            $taskTeam = isset($teamMap[$task->id]) ? $teamMap[$task->id] : array();
            $isMulti  = !empty($taskTeam);

            if($isMulti)
            {
                /* Multi-person task: create a bar for each team member in the project team. */
                foreach($taskTeam as $account => $memberInfo)
                {
                    if(!isset($teamMembers[$account])) continue;
                    if(!isset($lanes[$account]))
                    {
                        $lanes[$account] = array(
                            'realname' => zget($userPairs, $account, $account),
                            'tasks'    => array()
                        );
                    }
                    $lanes[$account]['tasks'][] = array(
                        'id'            => (int)$task->id,
                        'name'          => $task->name,
                        'assignedTo'    => $account,
                        'realname'      => zget($userPairs, $account, $account),
                        'project'       => (int)$task->project,
                        'projectName'   => $task->projectName,
                        'execution'     => (int)$task->execution,
                        'executionName' => $task->executionName,
                        'status'        => $task->status,
                        'estStarted'    => $task->estStarted,
                        'deadline'      => $task->deadline,
                        'estimate'      => $memberInfo['estimate']
                    );
                }
            }
            else
            {
                /* Single-person task: original logic. */
                $account = $task->assignedTo;
                if(!isset($lanes[$account]))
                {
                    $lanes[$account] = array(
                        'realname' => zget($userPairs, $account, $account),
                        'tasks'    => array()
                    );
                }
                $lanes[$account]['tasks'][] = array(
                    'id'            => (int)$task->id,
                    'name'          => $task->name,
                    'assignedTo'    => $account,
                    'realname'      => zget($userPairs, $account, $account),
                    'project'       => (int)$task->project,
                    'projectName'   => $task->projectName,
                    'execution'     => (int)$task->execution,
                    'executionName' => $task->executionName,
                    'status'        => $task->status,
                    'estStarted'    => $task->estStarted,
                    'deadline'      => $task->deadline,
                    'estimate'      => (float)$task->estimate
                );
            }
        }

        return array('lanes' => $lanes);
    }

    /**
     * Monte Carlo completion prediction.
     *
     * Runs $trials independent simulations. Each simulation draws daily velocity samples
     * from a Box-Muller transformed Gaussian (mean = $velocity, stddev = $velocity * 0.25,
     * floored at 0.1 h/day) and counts the working days needed to drain $remainingHours.
     *
     * Day counts are streamed into a flat array (one int per trial, ~8 KB at 1000 trials)
     * and sorted once at the end to extract percentiles. No per-day samples are retained.
     *
     * @return array{p50: ?string, p85: ?string} Y-m-d strings, or null when inputs make
     *                                           prediction meaningless (no work or no velocity).
     */
    public function runMonteCarloPrediction($remainingHours, $velocity, $trials = 1000)
    {
        if($velocity <= 0 || $remainingHours <= 0) return array('p50' => null, 'p85' => null);

        $stddev   = $velocity * 0.25;
        $trials   = max(1, (int)$trials);
        $dayCounts = array();

        for($i = 0; $i < $trials; $i++)
        {
            $cumulative = 0.0;
            $days       = 0;
            /* Hard cap to avoid runaway loops if a draw chain stays near the floor. */
            $maxDays = (int)ceil(($remainingHours / max(0.1, $velocity)) * 20) + 100;
            while($cumulative < $remainingHours && $days < $maxDays)
            {
                $u1 = mt_rand(1, 1000000) / 1000000;
                $u2 = mt_rand(1, 1000000) / 1000000;
                $z  = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
                $sample = $velocity + $z * $stddev;
                if($sample < 0.1) $sample = 0.1;
                $cumulative += $sample;
                $days++;
            }
            $dayCounts[] = $days;
        }

        sort($dayCounts);

        $p50Idx = (int)round($trials * 0.50) - 1;
        $p85Idx = (int)round($trials * 0.85) - 1;
        if($p50Idx < 0) $p50Idx = 0;
        if($p85Idx < 0) $p85Idx = 0;
        if($p50Idx >= $trials) $p50Idx = $trials - 1;
        if($p85Idx >= $trials) $p85Idx = $trials - 1;

        $today = strtotime(date('Y-m-d'));
        return array(
            'p50' => date('Y-m-d', $this->shiftWorkingDays($today, $dayCounts[$p50Idx])),
            'p85' => date('Y-m-d', $this->shiftWorkingDays($today, $dayCounts[$p85Idx]))
        );
    }

    /**
     * Predict completion dates for in-progress projects based on past-14-day team velocity.
     *
     * Algorithm (per project):
     *   remainingHours      = sum(left or estimate-consumed) of open tasks
     *   velocityHoursPerDay = sum(effort.consumed last 14 calendar days for project team) / 10
     *   predictedDays       = ceil(remainingHours / velocityHoursPerDay)
     *   realisticDate       = shiftWorkingDays(today, predictedDays)
     *   optimisticDate      = shiftWorkingDays(today, ceil(remainingHours / (velocity * 1.2)))
     *   pessimisticDate     = shiftWorkingDays(today, ceil(remainingHours / (velocity * 0.8)))
     *
     * Edge cases:
     *   - Project with no team members: skipped.
     *   - velocityHoursPerDay = 0: dates left empty, note recorded.
     *   - remainingHours = 0: all three dates = today.
     *
     * @param  int $limit max projects to return
     * @return array list sorted by remainingHours desc.
     */
    public function getProjectPredictions($limit = 20)
    {
        $today    = strtotime(date('Y-m-d'));
        $sinceTs  = strtotime('-14 days', $today);
        $sinceStr = date('Y-m-d', $sinceTs);

        /* Step 1: pull in-progress projects. */
        $projects = $this->dao->select('id, name')->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('status')->eq('doing')
            ->orderBy('id_desc')
            ->limit((int)$limit)
            ->fetchAll('id');
        if(empty($projects)) return array();

        $projectIDs = array_keys($projects);

        /* Step 2: team members per project (one query). */
        $teamRows = $this->dao->select('root, account')->from(TABLE_TEAM)
            ->where('type')->eq('project')
            ->andWhere('root')->in($projectIDs)
            ->fetchAll();
        $teamByProject = array();
        foreach($teamRows as $row)
        {
            $teamByProject[(int)$row->root][$row->account] = $row->account;
        }

        /* Step 3: remaining hours per project (one query). */
        $taskAgg = $this->dao->select("project, SUM(IF(`left` > 0, `left`, GREATEST(estimate - consumed, 0))) AS remain")
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('project')->in($projectIDs)
            ->andWhere('status')->notIN('done,closed,cancel')
            ->groupBy('project')
            ->fetchPairs('project', 'remain');

        /* Step 4: past-14-day consumed hours per project (one query, project-scoped). */
        $effortAgg = $this->dao->select('project, SUM(consumed) AS hours')
            ->from(TABLE_EFFORT)
            ->where('deleted')->eq('0')
            ->andWhere('project')->in($projectIDs)
            ->andWhere('date')->ge($sinceStr)
            ->groupBy('project')
            ->fetchPairs('project', 'hours');

        /* Step 5: assemble rows. */
        $denominator = 10; /* working days inside the past 14 calendar days, simplified. */
        $rows = array();
        foreach($projects as $pid => $project)
        {
            $members = isset($teamByProject[$pid]) ? $teamByProject[$pid] : array();
            if(empty($members)) continue;

            $remain   = isset($taskAgg[$pid])   ? (float)$taskAgg[$pid]   : 0.0;
            $consumed = isset($effortAgg[$pid]) ? (float)$effortAgg[$pid] : 0.0;
            $velocity = round($consumed / $denominator, 2);

            $optimistic = $realistic = $pessimistic = '';
            if($remain <= 0)
            {
                $optimistic = $realistic = $pessimistic = date('Y-m-d', $today);
            }
            elseif($velocity > 0)
            {
                $optDays = (int)ceil($remain / ($velocity * 1.2));
                $relDays = (int)ceil($remain / $velocity);
                $pesDays = (int)ceil($remain / ($velocity * 0.8));
                $optimistic   = date('Y-m-d', $this->shiftWorkingDays($today, $optDays));
                $realistic    = date('Y-m-d', $this->shiftWorkingDays($today, $relDays));
                $pessimistic  = date('Y-m-d', $this->shiftWorkingDays($today, $pesDays));
            }

            /* Monte Carlo: 500 trials per project to keep the request fast. */
            $mc = $this->runMonteCarloPrediction($remain, $velocity, 500);
            $p50 = $mc['p50'];
            $p85 = $mc['p85'];

            $rows[] = array(
                'id'             => (int)$pid,
                'name'           => $project->name,
                'remainingHours' => round($remain, 1),
                'velocity'       => $velocity,
                'optimistic'     => $optimistic,
                'realistic'      => $realistic,
                'pessimistic'    => $pessimistic,
                'p50'            => $p50,
                'p85'            => $p85,
                'memberCount'    => count($members),
            );
        }

        /* Sort by remainingHours desc. */
        usort($rows, function($a, $b){
            if($a['remainingHours'] == $b['remainingHours']) return 0;
            return $a['remainingHours'] < $b['remainingHours'] ? 1 : -1;
        });

        return $rows;
    }

    /**
     * Detect conflicts (over-allocation per member per day) inside a project window.
     *
     * Spreads each task's estimate across its working-day span [estStarted, deadline]
     * (clipped to [begin, end]). For tasks with no estStarted/deadline, falls back to
     * the whole window. Returns rows with member, date, hours, taskCount, ratio, level.
     *
     * @return array list of conflict rows sorted by ratio desc.
     */
    public function getProjectConflicts($projectID, $begin, $end, $users = '', $threshold = 1.0)
    {
        $stdHours = (float)(isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8);
        if($stdHours <= 0) $stdHours = 8;

        $teamMembers = $this->dao->select('account')->from(TABLE_TEAM)
            ->where('root')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->beginIF($users)->andWhere('account')->in($users)->fi()
            ->fetchPairs('account', 'account');
        if(empty($teamMembers)) return array();

        $userPairs = $this->loadModel('user')->getPairs('noletter');

        $tasks = $this->dao->select('id, name, assignedTo, estimate, estStarted, deadline, mode')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('assignedTo')->in($teamMembers)
            ->andWhere('status')->notIN('done,closed,cancel')
            ->beginIF($begin)->andWhere("(deadline >= '$begin' OR deadline = '0000-00-00' OR deadline IS NULL OR deadline = '')")->fi()
            ->beginIF($end)->andWhere("(estStarted <= '$end' OR estStarted = '0000-00-00' OR estStarted IS NULL OR estStarted = '')")->fi()
            ->fetchAll('id');

        /* Also fetch multi-person tasks where team members are in the project team. */
        $taskTeamIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->in($teamMembers)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $additionalIDs = array_diff($taskTeamIDs, array_keys($tasks));
        if(!empty($additionalIDs))
        {
            $extraTasks = $this->dao->select('id, name, assignedTo, estimate, estStarted, deadline, mode')
                ->from(TABLE_TASK)
                ->where('id')->in($additionalIDs)
                ->andWhere('deleted')->eq('0')
                ->andWhere('status')->notIN('done,closed,cancel')
                ->beginIF($begin)->andWhere("(deadline >= '$begin' OR deadline = '0000-00-00' OR deadline IS NULL OR deadline = '')")->fi()
                ->beginIF($end)->andWhere("(estStarted <= '$end' OR estStarted = '0000-00-00' OR estStarted IS NULL OR estStarted = '')")->fi()
                ->fetchAll('id');
            $tasks = $tasks + $extraTasks;
        }

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        $taskPredictOn  = !empty($this->config->man_resource->taskHourPredict);
        $predictPerTask = (float)(isset($this->config->man_resource->predictHours) ? $this->config->man_resource->predictHours : 0);

        $holidays = $this->dao->select('begin, type')->from(TABLE_HOLIDAY)->fetchPairs('begin', 'type');

        $beginTs = strtotime($begin);
        $endTs   = strtotime($end);

        /* dailyHours[account][date] = hours; dailyTasks[account][date] = task count. */
        $dailyHours = array();
        $dailyTasks = array();

        foreach($tasks as $task)
        {
            $taskTeam = isset($teamMap[$task->id]) ? $teamMap[$task->id] : array();
            $isMulti  = !empty($taskTeam);

            $taskBeginTs = ($task->estStarted && $task->estStarted != '0000-00-00') ? strtotime($task->estStarted) : $beginTs;
            $taskEndTs   = ($task->deadline   && $task->deadline   != '0000-00-00') ? strtotime($task->deadline)   : $endTs;

            $clipBegin = max($taskBeginTs, $beginTs);
            $clipEnd   = min($taskEndTs,   $endTs);
            if($clipBegin > $clipEnd) continue;

            $workDays = $this->collectWorkingDays($clipBegin, $clipEnd, $holidays);
            if(empty($workDays)) continue;

            if($isMulti)
            {
                /* Multi-person task: spread each member's estimate across the task days. */
                foreach($taskTeam as $account => $memberInfo)
                {
                    if(!isset($teamMembers[$account])) continue;
                    $estimate = $memberInfo['estimate'];
                    if($estimate <= 0)
                    {
                        if($taskPredictOn && $predictPerTask > 0) $estimate = $predictPerTask; else continue;
                    }
                    $perDay = $estimate / count($workDays);
                    foreach($workDays as $date)
                    {
                        if(!isset($dailyHours[$account])) { $dailyHours[$account] = array(); $dailyTasks[$account] = array(); }
                        if(!isset($dailyHours[$account][$date])) { $dailyHours[$account][$date] = 0; $dailyTasks[$account][$date] = 0; }
                        $dailyHours[$account][$date] += $perDay;
                        $dailyTasks[$account][$date] += 1;
                    }
                }
            }
            else
            {
                /* Single-person task: original logic. */
                $estimate = (float)$task->estimate;
                if($estimate <= 0)
                {
                    if($taskPredictOn && $predictPerTask > 0) $estimate = $predictPerTask; else continue;
                }

                $perDay = $estimate / count($workDays);
                foreach($workDays as $date)
                {
                    if(!isset($dailyHours[$task->assignedTo])) { $dailyHours[$task->assignedTo] = array(); $dailyTasks[$task->assignedTo] = array(); }
                    if(!isset($dailyHours[$task->assignedTo][$date])) { $dailyHours[$task->assignedTo][$date] = 0; $dailyTasks[$task->assignedTo][$date] = 0; }
                    $dailyHours[$task->assignedTo][$date] += $perDay;
                    $dailyTasks[$task->assignedTo][$date] += 1;
                }
            }
        }

        $conflicts = array();
        foreach($dailyHours as $account => $byDate)
        {
            foreach($byDate as $date => $hours)
            {
                $ratio = $hours / $stdHours;
                if($ratio < $threshold) continue;
                $conflicts[] = array(
                    'account'   => $account,
                    'realname'  => zget($userPairs, $account, $account),
                    'date'      => $date,
                    'hours'     => round($hours, 2),
                    'taskCount' => $dailyTasks[$account][$date],
                    'ratio'     => round($ratio * 100, 1),
                    'level'     => $ratio >= 1.2 ? 'over' : 'full'
                );
            }
        }

        usort($conflicts, function($a, $b) {
            if($a['ratio'] == $b['ratio']) return strcmp($a['date'], $b['date']);
            return $a['ratio'] < $b['ratio'] ? 1 : -1;
        });

        return $conflicts;
    }

    /**
     * Enumerate working days (Y-m-d strings) between two timestamps inclusive,
     * skipping weekends and configured holidays (working days override).
     */
    public function collectWorkingDays($beginTs, $endTs, $holidays = null)
    {
        if($holidays === null) $holidays = $this->dao->select('begin, type')->from(TABLE_HOLIDAY)->fetchPairs('begin', 'type');
        $days   = array();
        $cursor = $beginTs;
        while($cursor <= $endTs)
        {
            $date = date('Y-m-d', $cursor);
            $w    = (int)date('w', $cursor);
            $isWorkDay = ($w != 0 && $w != 6);
            if(isset($holidays[$date]))
            {
                if($holidays[$date] == 'holiday') $isWorkDay = false;
                if($holidays[$date] == 'working') $isWorkDay = true;
            }
            if($isWorkDay) $days[] = $date;
            $cursor += 86400;
        }
        return $days;
    }

    /**
     * Get tasks assigned to a user that intersect [begin, end].
     * Filters by status mode the same way as load aggregation:
     *   todo: status NOT IN (done, closed, cancel)
     *   done: status IN (done, closed)
     */
    public function getUserTasks($userID, $begin, $end, $status)
    {
        $tasks = $this->dao->select('t1.id, t1.name, t1.status, t1.estimate, t1.consumed, t1.left, t1.deadline, t1.estStarted, t1.project, t1.execution, t1.assignedTo, t1.mode, t2.name AS projectName, t3.name AS executionName')
            ->from(TABLE_TASK)->alias('t1')
            ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project = t2.id')
            ->leftJoin(TABLE_PROJECT)->alias('t3')->on('t1.execution = t3.id')
            ->where('t1.assignedTo')->eq($userID)
            ->andWhere('t1.deleted')->eq('0')
            ->beginIF($begin)->andWhere("(t1.deadline >= '$begin' OR t1.deadline = '0000-00-00' OR t1.deadline IS NULL OR t1.deadline = '')")->fi()
            ->beginIF($end)->andWhere("(t1.estStarted <= '$end' OR t1.estStarted = '0000-00-00' OR t1.estStarted IS NULL OR t1.estStarted = '')")->fi()
            ->beginIF($status == 'todo')->andWhere('t1.status')->notIN('done,closed,cancel')->fi()
            ->beginIF($status == 'done')->andWhere('t1.status')->in('done,closed')->fi()
            ->orderBy('t1.deadline_asc, t1.id_desc')
            ->fetchAll('id');

        /* Also fetch multi-person tasks where user is a team member but not assignedTo. */
        $teamTaskIDs = $this->dao->select('root')->from(TABLE_TEAM)
            ->where('account')->eq($userID)
            ->andWhere('type')->eq('task')
            ->fetchPairs('root', 'root');
        $additionalIDs = array_diff($teamTaskIDs, array_keys($tasks));
        if(!empty($additionalIDs))
        {
            $extraTasks = $this->dao->select('t1.id, t1.name, t1.status, t1.estimate, t1.consumed, t1.left, t1.deadline, t1.estStarted, t1.project, t1.execution, t1.assignedTo, t1.mode, t2.name AS projectName, t3.name AS executionName')
                ->from(TABLE_TASK)->alias('t1')
                ->leftJoin(TABLE_PROJECT)->alias('t2')->on('t1.project = t2.id')
                ->leftJoin(TABLE_PROJECT)->alias('t3')->on('t1.execution = t3.id')
                ->where('t1.id')->in($additionalIDs)
                ->andWhere('t1.deleted')->eq('0')
                ->beginIF($status == 'todo')->andWhere('t1.status')->notIN('done,closed,cancel')->fi()
                ->beginIF($status == 'done')->andWhere('t1.status')->in('done,closed')->fi()
                ->orderBy('t1.deadline_asc, t1.id_desc')
                ->fetchAll('id');
            $tasks = $tasks + $extraTasks;
        }

        /* For multi-person tasks, replace task-level hours with per-member hours. */
        $teamMap = $this->getTaskTeamMap(array_keys($tasks));
        foreach($tasks as $id => $task)
        {
            $taskTeam = isset($teamMap[$id]) ? $teamMap[$id] : array();
            if(!empty($taskTeam) && isset($taskTeam[$userID]))
            {
                $memberInfo      = $taskTeam[$userID];
                $task->estimate  = $memberInfo['estimate'];
                $task->consumed   = $memberInfo['consumed'];
                $task->left       = $memberInfo['left'];
            }
        }

        return $tasks;
    }

    /**
     * Clamp a [begin, end] window so it stays inside the half-line allowed by status mode.
     * todo allows only today and future; done allows only today and past.
     * Returns [clampedBegin, clampedEnd] as Y-m-d strings.
     */
    public function clampRangeByStatus($status, $begin, $end)
    {
        $today = date('Y-m-d');
        if($status == 'done')
        {
            if($end   > $today) $end   = $today;
            if($begin > $today) $begin = $today;
        }
        else
        {
            if($begin < $today) $begin = $today;
            if($end   < $today) $end   = $today;
        }
        if($begin > $end) $end = $begin;
        return array($begin, $end);
    }

    /**
     * Get default date range based on status mode.
     * todo: today ~ today + N working days
     * done: today - N working days ~ today
     */
    public function getDefaultDateRange($status, $workDays = 5)
    {
        $today = strtotime(date('Y-m-d'));
        if($status == 'done')
        {
            $start = $this->shiftWorkingDays($today, -$workDays);
            return array(date('Y-m-d', $start), date('Y-m-d', $today));
        }
        $end = $this->shiftWorkingDays($today, $workDays);
        return array(date('Y-m-d', $today), date('Y-m-d', $end));
    }

    /**
     * Shift a timestamp forward (positive) or backward (negative) by N working days,
     * skipping weekends and configured holidays.
     */
    public function shiftWorkingDays($timestamp, $days)
    {
        if($days == 0) return $timestamp;
        $step      = $days > 0 ? 86400 : -86400;
        $remaining = abs($days);
        $cursor    = $timestamp;

        $holidays = $this->dao->select('begin, type')->from(TABLE_HOLIDAY)
            ->fetchPairs('begin', 'type');

        while($remaining > 0)
        {
            $cursor += $step;
            $date = date('Y-m-d', $cursor);
            $w    = (int)date('w', $cursor);
            $isWorkDay = ($w != 0 && $w != 6);
            if(isset($holidays[$date]))
            {
                if($holidays[$date] == 'holiday') $isWorkDay = false;
                if($holidays[$date] == 'working') $isWorkDay = true;
            }
            if($isWorkDay) $remaining--;
        }
        return $cursor;
    }

    /**
     * Simulate load with per-task adjustments. See plan §3.4.
     *
     * Expected $post keys:
     *   simulation_name, project_id, start_date, end_date,
     *   adjustments => [{task_id, est_started, deadline, estimate, assigned_to}, ...]
     *
     * Returns:
     *   array(simulation_id, series, conflicts, verdict, adjustedTasks, maxLoadRate)
     */
    public function simulateLoad($post = null)
    {
        if($post === null) $post = $_POST;
        if(is_object($post)) $post = (array)$post;

        $simulationName = isset($post['simulation_name']) ? trim((string)$post['simulation_name']) : '';
        $projectID      = isset($post['project_id'])      ? (int)$post['project_id']               : 0;
        $begin          = isset($post['start_date'])      ? (string)$post['start_date']            : '';
        $end            = isset($post['end_date'])        ? (string)$post['end_date']              : '';
        $rawAdjust      = isset($post['adjustments'])     ? $post['adjustments']                   : array();

        /* Adjustments may arrive as a JSON string from a textarea. */
        if(is_string($rawAdjust))
        {
            $rawAdjust = trim($rawAdjust);
            if($rawAdjust === '') $rawAdjust = array();
            else
            {
                $decoded = json_decode($rawAdjust, true);
                $rawAdjust = is_array($decoded) ? $decoded : array();
            }
        }
        if(!is_array($rawAdjust)) $rawAdjust = array();

        /* Index adjustments by task_id for quick overlay lookup. */
        $adjustments = array();
        foreach($rawAdjust as $adj)
        {
            if(is_object($adj)) $adj = (array)$adj;
            if(!isset($adj['task_id'])) continue;
            $adjustments[(int)$adj['task_id']] = $adj;
        }

        if(empty($simulationName) || empty($begin) || empty($end))
        {
            dao::$errors[] = 'simulation_name / start_date / end_date are required.';
            return false;
        }

        $stdHours = (float)(isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8);
        if($stdHours <= 0) $stdHours = 8;

        /* Resolve team members for the project (or fall back to assignees). */
        $teamMembers = array();
        if($projectID > 0)
        {
            $teamMembers = $this->dao->select('account')->from(TABLE_TEAM)
                ->where('root')->eq($projectID)
                ->andWhere('type')->eq('project')
                ->fetchPairs('account', 'account');
        }

        $taskQuery = $this->dao->select('id, name, assignedTo, estimate, estStarted, deadline, mode')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('status')->notIN('done,closed,cancel')
            ->beginIF($projectID > 0)->andWhere('project')->eq($projectID)->fi()
            ->beginIF(!empty($teamMembers))->andWhere('assignedTo')->in($teamMembers)->fi();
        $tasks = $taskQuery->fetchAll('id');

        /* Make sure adjustment-only tasks (e.g. a task outside the project window) are still applied. */
        $missingIds = array();
        foreach(array_keys($adjustments) as $tid) if(!isset($tasks[$tid])) $missingIds[] = $tid;
        if(!empty($missingIds))
        {
            $extraTasks = $this->dao->select('id, name, assignedTo, estimate, estStarted, deadline, mode')
                ->from(TABLE_TASK)
                ->where('deleted')->eq('0')
                ->andWhere('id')->in($missingIds)
                ->fetchAll('id');
            foreach($extraTasks as $tid => $task) $tasks[$tid] = $task;
        }

        /* Also include multi-person tasks where team members are in the project team. */
        if(!empty($teamMembers))
        {
            $taskTeamIDs = $this->dao->select('root')->from(TABLE_TEAM)
                ->where('account')->in($teamMembers)
                ->andWhere('type')->eq('task')
                ->fetchPairs('root', 'root');
            $teamAdditionalIDs = array_diff($taskTeamIDs, array_keys($tasks));
            if(!empty($teamAdditionalIDs))
            {
                $teamExtraTasks = $this->dao->select('id, name, assignedTo, estimate, estStarted, deadline, mode')
                    ->from(TABLE_TASK)
                    ->where('id')->in($teamAdditionalIDs)
                    ->andWhere('deleted')->eq('0')
                    ->andWhere('status')->notIN('done,closed,cancel')
                    ->fetchAll('id');
                foreach($teamExtraTasks as $tid => $task) $tasks[$tid] = $task;
            }
        }

        $teamMap = $this->getTaskTeamMap(array_keys($tasks));

        $taskPredictOn  = !empty($this->config->man_resource->taskHourPredict);
        $predictPerTask = (float)(isset($this->config->man_resource->predictHours) ? $this->config->man_resource->predictHours : 0);

        $holidays = $this->dao->select('begin, type')->from(TABLE_HOLIDAY)->fetchPairs('begin', 'type');
        $beginTs  = strtotime($begin);
        $endTs    = strtotime($end);
        $dates    = $this->collectWorkingDays($beginTs, $endTs, $holidays);

        /* Apply adjustments and accumulate per-day hours per account. */
        $dailyHours = array();
        $dailyTasks = array();
        $accountSet = array();
        foreach($tasks as $taskID => $task)
        {
            $assignedTo = $task->assignedTo;
            $estimate   = (float)$task->estimate;
            $taskBegin  = ($task->estStarted && $task->estStarted != '0000-00-00') ? $task->estStarted : '';
            $taskEnd    = ($task->deadline   && $task->deadline   != '0000-00-00') ? $task->deadline   : '';

            if(isset($adjustments[$taskID]))
            {
                $adj = $adjustments[$taskID];
                if(isset($adj['estimate'])    && $adj['estimate']    !== '') $estimate   = (float)$adj['estimate'];
                if(isset($adj['est_started']) && $adj['est_started'] !== '') $taskBegin  = (string)$adj['est_started'];
                if(isset($adj['deadline'])    && $adj['deadline']    !== '') $taskEnd    = (string)$adj['deadline'];
                if(isset($adj['assigned_to']) && $adj['assigned_to'] !== '') $assignedTo = (string)$adj['assigned_to'];
            }

            if(empty($assignedTo)) continue;

            $taskTeam = isset($teamMap[$taskID]) ? $teamMap[$taskID] : array();
            $isMulti  = !empty($taskTeam);

            $taskBeginTs = !empty($taskBegin) ? strtotime($taskBegin) : $beginTs;
            $taskEndTs   = !empty($taskEnd)   ? strtotime($taskEnd)   : $endTs;
            $clipBegin   = max($taskBeginTs, $beginTs);
            $clipEnd     = min($taskEndTs,   $endTs);
            if($clipBegin > $clipEnd) continue;

            $taskDays = $this->collectWorkingDays($clipBegin, $clipEnd, $holidays);
            if(empty($taskDays)) continue;

            if($isMulti)
            {
                /* Multi-person task: spread each member's estimate across the task days. */
                foreach($taskTeam as $account => $memberInfo)
                {
                    $memberEst = $memberInfo['estimate'];
                    /* Allow simulation adjustments to override per-member estimate. */
                    if(isset($adjustments[$taskID]) && isset($adj['estimate']) && $adj['estimate'] !== '')
                    {
                        /* When adjusted, distribute total estimate equally among members. */
                        $memberEst = (float)$adj['estimate'] / max(1, count($taskTeam));
                    }
                    if($memberEst <= 0)
                    {
                        if($taskPredictOn && $predictPerTask > 0) $memberEst = $predictPerTask; else continue;
                    }
                    $accountSet[$account] = $account;
                    $perDay = $memberEst / count($taskDays);
                    foreach($taskDays as $date)
                    {
                        if(!isset($dailyHours[$account])) { $dailyHours[$account] = array(); $dailyTasks[$account] = array(); }
                        if(!isset($dailyHours[$account][$date])) { $dailyHours[$account][$date] = 0.0; $dailyTasks[$account][$date] = 0; }
                        $dailyHours[$account][$date] += $perDay;
                        $dailyTasks[$account][$date] += 1;
                    }
                }
            }
            else
            {
                /* Single-person task: original logic. */
                $accountSet[$assignedTo] = $assignedTo;

                if($estimate <= 0)
                {
                    if($taskPredictOn && $predictPerTask > 0) $estimate = $predictPerTask; else continue;
                }

                $perDay = $estimate / count($taskDays);
                foreach($taskDays as $date)
                {
                    if(!isset($dailyHours[$assignedTo]))
                    {
                        $dailyHours[$assignedTo] = array();
                        $dailyTasks[$assignedTo] = array();
                    }
                    if(!isset($dailyHours[$assignedTo][$date]))
                    {
                        $dailyHours[$assignedTo][$date] = 0.0;
                        $dailyTasks[$assignedTo][$date] = 0;
                    }
                    $dailyHours[$assignedTo][$date] += $perDay;
                    $dailyTasks[$assignedTo][$date] += 1;
                }
            }
        }

        /* Make sure team members with zero hours still appear as a flat lane. */
        foreach($teamMembers as $account) $accountSet[$account] = $account;

        $userPairs = $this->loadModel('user')->getPairs('noletter');
        $accounts  = array_keys($accountSet);
        $dateIndex = array_flip($dates);

        $series = array();
        foreach($accounts as $account)
        {
            $series[$account] = array(
                'realname' => zget($userPairs, $account, $account),
                'hours'    => array_fill(0, count($dates), 0.0),
                'rates'    => array_fill(0, count($dates), 0.0)
            );
        }

        $maxLoadRate = 0.0;
        $overall     = array_fill(0, count($dates), 0.0);
        foreach($dailyHours as $account => $byDate)
        {
            if(!isset($series[$account])) continue;
            foreach($byDate as $date => $hours)
            {
                if(!isset($dateIndex[$date])) continue;
                $idx  = $dateIndex[$date];
                $rate = $stdHours > 0 ? round(($hours / $stdHours) * 100, 1) : 0;
                $series[$account]['hours'][$idx] = round($hours, 2);
                $series[$account]['rates'][$idx] = $rate;
                $overall[$idx] += $rate;
                if($rate > $maxLoadRate) $maxLoadRate = $rate;
            }
        }
        $accountCount = max(1, count($accounts));
        foreach($overall as $i => $sum) $overall[$i] = round($sum / $accountCount, 1);

        /* Build conflicts list (>=100% utilization). */
        $conflicts = array();
        foreach($dailyHours as $account => $byDate)
        {
            foreach($byDate as $date => $hours)
            {
                $ratio = $stdHours > 0 ? ($hours / $stdHours) : 0;
                if($ratio < 1.0) continue;
                $conflicts[] = array(
                    'account'   => $account,
                    'realname'  => zget($userPairs, $account, $account),
                    'date'      => $date,
                    'hours'     => round($hours, 2),
                    'taskCount' => $dailyTasks[$account][$date],
                    'ratio'     => round($ratio * 100, 1),
                    'level'     => $ratio >= 1.2 ? 'over' : 'full'
                );
            }
        }
        usort($conflicts, function($a, $b) {
            if($a['ratio'] == $b['ratio']) return strcmp($a['date'], $b['date']);
            return $a['ratio'] < $b['ratio'] ? 1 : -1;
        });

        if($maxLoadRate < 100)      $verdict = 'green';
        else if($maxLoadRate < 120) $verdict = 'yellow';
        else                        $verdict = 'red';

        $result = array(
            'simulation_id' => 0,
            'series'        => array('dates' => $dates, 'series' => $series, 'overall' => $overall),
            'conflicts'     => $conflicts,
            'verdict'       => $verdict,
            'adjustedTasks' => count($adjustments),
            'maxLoadRate'   => $maxLoadRate
        );

        /* Persist. */
        $row = new stdclass();
        $row->simulation_name = $simulationName;
        $row->operator        = isset($this->app->user->id) ? $this->app->user->id : 0;
        $row->start_date      = $begin;
        $row->end_date        = $end;
        $row->result_json     = json_encode($result);
        $row->created_at      = date('Y-m-d H:i:s');

        $this->dao->insert(TABLE_LOAD_SIMULATION)
            ->data($row)
            ->batchCheck('simulation_name, start_date, end_date', 'notempty')
            ->exec();
        if(dao::isError()) return false;

        $result['simulation_id'] = (int)$this->dao->lastInsertID();
        return $result;
    }

    /**
     * Snapshot the org-wide resource calendar for [begin, end] into TABLE_RESOURCE_CALENDAR.
     * Idempotent: deletes existing org-level rows in the window first. Plan §8.1.
     */
    public function snapshotResourceCalendar($begin, $end)
    {
        $stdHours = (float)(isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8);
        if($stdHours <= 0) $stdHours = 8;

        $users = $this->dao->select('account')->from(TABLE_USER)
            ->where('deleted')->eq('0')
            ->fetchPairs('account', 'account');
        $accounts = array_keys($users);
        if(empty($accounts)) return 0;

        $todoSeries = $this->getDailyLoadSeries($accounts, $begin, $end, 'todo');
        $doneSeries = $this->getDailyLoadSeries($accounts, $begin, $end, 'done');

        $dates = isset($todoSeries['dates']) ? $todoSeries['dates'] : array();
        if(empty($dates)) return 0;

        /* Wipe existing org-level snapshot rows in the window for idempotency. */
        $this->dao->delete()->from(TABLE_RESOURCE_CALENDAR)
            ->where('work_date')->between($begin, $end)
            ->andWhere('project_id')->eq(0)
            ->andWhere('task_id')->eq(0)
            ->exec();

        $count    = 0;
        $now      = date('Y-m-d H:i:s');
        foreach($accounts as $account)
        {
            $todoHours = isset($todoSeries['series'][$account]['hours']) ? $todoSeries['series'][$account]['hours'] : array();
            $doneHours = isset($doneSeries['series'][$account]['hours']) ? $doneSeries['series'][$account]['hours'] : array();
            $todoRates = isset($todoSeries['series'][$account]['rates']) ? $todoSeries['series'][$account]['rates'] : array();

            foreach($dates as $i => $date)
            {
                $est  = isset($todoHours[$i]) ? (float)$todoHours[$i] : 0.0;
                $cons = isset($doneHours[$i]) ? (float)$doneHours[$i] : 0.0;
                $rate = isset($todoRates[$i]) ? (float)$todoRates[$i] : 0.0;

                $row = new stdclass();
                $row->user_id         = $account;
                $row->project_id      = 0;
                $row->task_id         = 0;
                $row->work_date       = $date;
                $row->estimated_hours = round($est, 2);
                $row->consumed_hours  = round($cons, 2);
                $row->remain_hours    = round($est, 2);
                $row->load_rate       = round($rate, 2);
                $row->status          = $this->getLoadStatus($rate);

                $this->dao->insert(TABLE_RESOURCE_CALENDAR)->data($row)->exec();
                if(!dao::isError()) $count++;
            }
        }
        return $count;
    }
}
