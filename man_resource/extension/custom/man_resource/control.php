<?php
class man_resource extends control
{
    /**
     * 资源日历总览：直接跳转到组织维度，不再保留独立总览页。
     */
    public function browse($status = 'todo', $date = '')
    {
        $this->locate($this->createLink('man_resource', 'orgdimension'));
    }

    /**
     * 组织资源日历 (Org Resource Calendar)
     */
    public function orgdimension($status = 'todo', $begin = '', $end = '', $depts = '', $roles = '', $users = '', $project = 0, $showHoliday = 0)
    {
        if(!empty($_POST))
        {
            $status      = $this->post->status;
            $begin       = $this->post->begin;
            $end         = $this->post->end;
            $depts       = is_array($this->post->departmentID) ? implode(',', $this->post->departmentID) : $this->post->departmentID;
            $roles       = is_array($this->post->roleID) ? implode(',', $this->post->roleID) : $this->post->roleID;
            $users       = is_array($this->post->users) ? implode(',', $this->post->users) : $this->post->users;
            $project     = (int)$this->post->project;
            $showHoliday = $this->post->showHoliday ? 1 : 0;
            
            $begin = str_replace('-', '_', $begin);
            $end   = str_replace('-', '_', $end);
            
            $link = $this->createLink('man_resource', 'orgdimension', "status=$status&begin=$begin&end=$end&depts=$depts&roles=$roles&users=$users&project=$project&showHoliday=$showHoliday");
            if(helper::isAjaxRequest()) return $this->send(array('result' => 'success', 'locate' => $link));
            $this->locate($link);
        }

        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);

        $this->view->title  = $this->lang->man_resource->company;
        $this->view->status = $status;
        list($defaultBegin, $defaultEnd) = $this->man_resource->getDefaultDateRange($status);
        $begin = empty($begin) ? $defaultBegin : $begin;
        $end   = empty($end)   ? $defaultEnd   : $end;
        $this->view->begin  = $begin;
        $this->view->end    = $end;

        $this->view->depts       = is_array($depts) ? implode(',', $depts) : $depts;
        $this->view->roles       = is_array($roles) ? implode(',', $roles) : $roles;
        $this->view->users       = is_array($users) ? implode(',', $users) : $users;
        $this->view->project     = $project;
        $this->view->showHoliday = $showHoliday;

        $this->view->departments  = $this->loadModel('dept')->getOptionMenu();
        $this->view->rolePairs    = $this->lang->user->roleList;
        $this->view->projectList  = array(0 => '') + $this->loadModel('project')->getPairs();
        $this->view->userList     = $this->loadModel('user')->getPairs('noletter');
        
        $this->view->departmentID = $depts;
        $this->view->roleID       = $roles;

        $this->view->calendarData = $this->man_resource->getOrgCalendarData($this->view->begin, $this->view->end, $status, $this->view->depts, $this->view->roles, $this->view->users, $this->view->project, $this->view->showHoliday);
        $this->view->dailySeries  = $this->man_resource->getDailyLoadSeries(array_keys($this->view->calendarData), $this->view->begin, $this->view->end, $status);

        /* TEMP DEBUG: dump team task data for ALL users */
        $debugLines = array();
        $calendarAccounts = array_keys($this->view->calendarData);
        foreach($calendarAccounts as $acc)
        {
            $d = $this->view->calendarData[$acc];
            if($d['estimated_hours'] == 0 && $d['consumed_hours'] == 0 && $d['remain_hours'] == 0)
            {
                $debugLines[] = $this->man_resource->debugUserTeamData($acc, $this->view->begin, $this->view->end, $status);
            }
        }
        $this->view->debugInfo = implode("\n", $debugLines);
        
        /* Variables for ZIN UI */
        $this->view->today            = date('Y-m-d');
        $this->view->accounts         = $this->view->users;
        $this->view->defaultWorkhours = isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8;
        $this->view->paramList        = array();
        $this->view->isSimulate       = false;
        $this->view->actionURL        = $this->createLink('man_resource', 'orgdimension');
        $this->view->type             = 'bysearch';

        $this->display();
    }

    /**
     * 项目资源日历 (Project Resource Calendar)
     */
    public function projectdimension($projectID = 0, $status = 'todo', $begin = '', $end = '', $users = '', $showHoliday = 0)
    {
        if(!empty($_POST))
        {
            $projectID   = (int)$this->post->projectID;
            $status      = $this->post->status;
            $begin       = $this->post->begin;
            $end         = $this->post->end;
            $users       = is_array($this->post->users) ? implode(',', $this->post->users) : $this->post->users;
            $showHoliday = $this->post->showHoliday ? 1 : 0;
            
            $begin = str_replace('-', '_', $begin);
            $end   = str_replace('-', '_', $end);
            
            $link = $this->createLink('man_resource', 'projectdimension', "projectID=$projectID&status=$status&begin=$begin&end=$end&users=$users&showHoliday=$showHoliday");
            if(helper::isAjaxRequest()) return $this->send(array('result' => 'success', 'locate' => $link));
            $this->locate($link);
        }

        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);

        $this->view->title       = $this->lang->man_resource->projectCalendar;
        $this->view->projectID   = $projectID;
        $this->view->status      = $status;
        list($defaultBegin, $defaultEnd) = $this->man_resource->getDefaultDateRange($status);
        $begin = empty($begin) ? $defaultBegin : $begin;
        $end   = empty($end)   ? $defaultEnd   : $end;
        $this->view->begin       = $begin;
        $this->view->end         = $end;
        $this->view->users       = is_array($users) ? implode(',', $users) : $users;
        $this->view->showHoliday = $showHoliday;

        $this->view->projects = $this->loadModel('project')->getPairs();
        if($projectID == 0 && !empty($this->view->projects)) $projectID = key($this->view->projects);
        $this->view->projectID = $projectID;

        $this->view->userList = $this->loadModel('user')->getPairs('noletter');
        
        $this->view->calendarData = $this->man_resource->getProjectCalendarData($projectID, $this->view->begin, $this->view->end, $status, $this->view->users, $this->view->showHoliday);
        $this->view->conflicts    = $this->man_resource->getProjectConflicts($projectID, $this->view->begin, $this->view->end, $this->view->users);
        $this->view->dailySeries  = $this->man_resource->getDailyLoadSeries(array_keys($this->view->calendarData), $this->view->begin, $this->view->end, $status);
        $this->view->ganttData    = $this->man_resource->getProjectGanttData($projectID, $this->view->begin, $this->view->end, $this->view->users);
        
        /* Variables for ZIN UI */
        $this->view->today            = date('Y-m-d');
        $this->view->accounts         = $this->view->users;
        $this->view->defaultWorkhours = isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8;
        $this->view->userList         = $this->loadModel('user')->getPairs('noletter');

        $this->display();
    }

    /**
     * 个人资源日历 (Member Resource Calendar)
     */
    public function memberdimension($userID = '', $status = 'todo', $begin = '', $end = '', $showHoliday = 0)
    {
        if(!empty($_POST))
        {
            $userID      = $this->post->userID;
            $status      = $this->post->status;
            $begin       = $this->post->begin;
            $end         = $this->post->end;
            $showHoliday = $this->post->showHoliday ? 1 : 0;
            
            $begin = str_replace('-', '_', $begin);
            $end   = str_replace('-', '_', $end);
            
            $link = $this->createLink('man_resource', 'memberdimension', "userID=$userID&status=$status&begin=$begin&end=$end&showHoliday=$showHoliday");
            if(helper::isAjaxRequest()) return $this->send(array('result' => 'success', 'locate' => $link));
            $this->locate($link);
        }

        $begin = str_replace('_', '-', $begin);
        $end   = str_replace('_', '-', $end);

        $this->view->title       = $this->lang->man_resource->person;
        $this->view->status      = $status;
        list($defaultBegin, $defaultEnd) = $this->man_resource->getDefaultDateRange($status);
        $begin = empty($begin) ? $defaultBegin : $begin;
        $end   = empty($end)   ? $defaultEnd   : $end;
        $this->view->begin       = $begin;
        $this->view->end         = $end;
        $this->view->showHoliday = $showHoliday;

        $this->view->userList = $this->loadModel('user')->getPairs('noletter');
        if(empty($userID)) $userID = key($this->view->userList);
        $this->view->userID = $userID;
        
        $this->view->calendarData = $this->man_resource->getUserCalendarData($userID, $this->view->begin, $this->view->end, $status, $this->view->showHoliday);
        $this->view->taskList     = $this->man_resource->getUserTasks($userID, $this->view->begin, $this->view->end, $status);
        
        /* Variables for ZIN UI */
        $this->view->today            = date('Y-m-d');
        $this->view->defaultWorkhours = isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8;

        $this->display();
    }

    /**
     * 负载模拟 (Load Simulation)
     */
    public function simulate()
    {
        if(!empty($_POST))
        {
            $result = $this->man_resource->simulateLoad($_POST);
            if(!$result || dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array(
                'result'  => 'success',
                'message' => $this->lang->saveSuccess,
                'data'    => $result,
                'locate'  => inlink('simulate', "simulation_id={$result['simulation_id']}")
            ));
        }

        $this->view->title      = $this->lang->man_resource->simulatedLoad;
        $this->view->projects   = $this->loadModel('project')->getPairs();
        $this->view->today      = date('Y-m-d');
        $this->view->plus5      = date('Y-m-d', strtotime('+7 days'));
        $this->view->lastResult = null;
        $simID = (int)$this->get->simulation_id;
        if($simID > 0)
        {
            $this->view->lastResult = $this->dao->select('*')->from(TABLE_LOAD_SIMULATION)->where('id')->eq($simID)->fetch();
        }
        $this->display();
    }

    /**
     * 列出指定项目的任务，供负载模拟编辑器使用。
     */
    public function simulateTasks()
    {
        $projectID = (int)$this->get->projectID;
        if($projectID <= 0) return $this->send(array('result' => 'fail', 'message' => 'projectID required'));

        $userPairs = $this->loadModel('user')->getPairs('noletter');

        $tasks = $this->dao->select('id, name, assignedTo, estStarted, deadline, estimate, mode')
            ->from(TABLE_TASK)
            ->where('deleted')->eq('0')
            ->andWhere('status')->notIN('cancel,closed')
            ->andWhere('project')->eq($projectID)
            ->orderBy('id_asc')
            ->fetchAll('id');

        /* Fetch team data for multi-person tasks. */
        $taskIDs = array_keys($tasks);
        $teamMap = array();
        if(!empty($taskIDs))
        {
            $teamMap = $this->man_resource->getTaskTeamMap($taskIDs);
        }

        $rows = array();
        foreach($tasks as $task)
        {
            $taskTeam = isset($teamMap[$task->id]) ? $teamMap[$task->id] : array();
            $isMulti  = !empty($taskTeam);

            $row = array(
                'id'               => (int)$task->id,
                'name'             => $task->name,
                'assignedTo'       => $task->assignedTo,
                'assignedRealname' => isset($userPairs[$task->assignedTo]) ? $userPairs[$task->assignedTo] : $task->assignedTo,
                'estStarted'       => $task->estStarted,
                'deadline'         => $task->deadline,
                'estimate'         => (float)$task->estimate,
                'mode'             => $task->mode,
                'team'             => array(),
            );

            if($isMulti)
            {
                foreach($taskTeam as $account => $memberInfo)
                {
                    $row['team'][] = array(
                        'account'  => $account,
                        'realname' => zget($userPairs, $account, $account),
                        'estimate' => $memberInfo['estimate'],
                        'consumed'  => $memberInfo['consumed'],
                        'left'      => $memberInfo['left'],
                        'hours'     => $memberInfo['hours'],
                        'days'      => $memberInfo['days'],
                    );
                }
            }

            $rows[] = $row;
        }

        return $this->send(array('result' => 'success', 'tasks' => $rows));
    }

    /**
     * 资源日历快照 (Resource Calendar Snapshot, plan §8.1).
     */
    public function snapshot()
    {
        if(!empty($_POST))
        {
            $begin = (string)$this->post->begin;
            $end   = (string)$this->post->end;
            if(empty($begin) || empty($end)) return $this->send(array('result' => 'fail', 'message' => 'begin/end required'));

            $count = $this->man_resource->snapshotResourceCalendar($begin, $end);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array(
                'result'  => 'success',
                'message' => sprintf($this->lang->man_resource->snapshotDone, $count),
                'locate'  => inlink('snapshot')
            ));
        }
        $this->view->title = $this->lang->man_resource->snapshotTitle;
        $this->view->begin = date('Y-m-d', strtotime('-30 days'));
        $this->view->end   = date('Y-m-d');
        $this->display();
    }

    /**
     * Debug: show team task data for a user.
     * Usage: man_resource-debugTeam?userID=liushuai
     */
    public function debugTeam($userID = '')
    {
        if(empty($userID)) { echo 'Usage: man_resource-debugTeam?userID=xxx'; exit; }

        header('Content-Type: text/plain; charset=utf-8');
        echo "=== Debug for user: {$userID} ===\n\n";

        try {
            /* 1. Show team task IDs from zt_team */
            $teamTaskIDs = $this->dao->select('root')->from(TABLE_TEAM)
                ->where('account')->eq($userID)
                ->andWhere('type')->eq('task')
                ->fetchPairs('root', 'root');
            echo "--- zt_team task IDs ---\n";
            echo count($teamTaskIDs) . " tasks found\n";
            print_r($teamTaskIDs);
        } catch(Exception $e) { echo "Error1: " . $e->getMessage() . "\n"; }

        try {
            /* 2. Show assignedTo tasks */
            $assignedTasks = $this->dao->select('id, name, assignedTo, status, project, estStarted, deadline, estimate, consumed, `left`, mode')->from(TABLE_TASK)
                ->where('assignedTo')->eq($userID)
                ->andWhere('deleted')->eq('0')
                ->fetchAll('id');
            echo "\n--- Tasks where assignedTo = {$userID} ---\n";
            echo count($assignedTasks) . " tasks found\n";
            print_r($assignedTasks);
        } catch(Exception $e) { echo "Error2: " . $e->getMessage() . "\n"; }

        try {
            /* 3. Show team member detail */
            if(!empty($teamTaskIDs))
            {
                $teamRows = $this->dao->select('root, account, estimate, consumed, `left`, hours, days')->from(TABLE_TEAM)
                    ->where('root')->in($teamTaskIDs)
                    ->andWhere('type')->eq('task')
                    ->fetchAll();
                echo "\n--- zt_team rows ---\n";
                echo count($teamRows) . " rows found\n";
                print_r($teamRows);
            }
        } catch(Exception $e) { echo "Error3: " . $e->getMessage() . "\n"; }

        try {
            /* 4. Show getUserLoadRate result */
            $load = $this->man_resource->getUserLoadRate($userID, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'todo', 0);
            echo "\n--- getUserLoadRate (todo mode) ---\n";
            print_r($load);
        } catch(Exception $e) { echo "Error4: " . $e->getMessage() . "\n"; }

        try {
            $loadDone = $this->man_resource->getUserLoadRate($userID, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), 'done', 0);
            echo "\n--- getUserLoadRate (done mode) ---\n";
            print_r($loadDone);
        } catch(Exception $e) { echo "Error5: " . $e->getMessage() . "\n"; }

        exit;
    }
    
    /**
     * 工期预测 (Duration Prediction)
     */
    public function prediction()
    {
        $this->view->title       = $this->lang->man_resource->predictionTitle;
        $this->view->predictions = $this->man_resource->getProjectPredictions();
        $this->display();
    }

    /**
     * 设置工作时间
     */
    public function setHours()
    {
        if(!empty($_POST))
        {
            $this->loadModel('setting')->setItem('system.' . $this->moduleName . '.workHoursPerDay', $this->post->defaultWorkhours);
            $this->loadModel('setting')->setItem('system.' . $this->moduleName . '.weekend', $this->post->weekend);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'locate' => 'parent'));
        }
        $this->view->title = $this->lang->man_resource->setHours;
        $this->view->workhours = isset($this->config->man_resource->workHoursPerDay) ? $this->config->man_resource->workHoursPerDay : 8;
        $this->view->weekend = isset($this->config->man_resource->weekend) ? $this->config->man_resource->weekend : 2;
        $this->display();
    }

    /**
     * 设置负载区间
     */
    public function setLoad()
    {
        if(!empty($_POST))
        {
            $loadRange = array();
            $fields = array('relax', 'spare', 'normal', 'full', 'over');
            foreach($fields as $field)
            {
                if(isset($this->post->$field)) $loadRange[$field] = (float)$this->post->$field;
            }
            if(count($loadRange) === 5)
            {
                if($loadRange['relax'] >= $loadRange['spare'] || $loadRange['spare'] >= $loadRange['normal'] || $loadRange['normal'] >= $loadRange['full'] || $loadRange['full'] >= $loadRange['over'])
                {
                    return $this->send(array('result' => 'fail', 'message' => $this->lang->man_resource->invalidLoadRange));
                }
            }
            $this->loadModel('setting')->setItems('system.' . $this->moduleName . '.loadRange', $loadRange);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'locate' => 'parent'));
        }
        $this->view->title = $this->lang->man_resource->setLoad;
        $this->view->loadRangeList = isset($this->config->man_resource->loadRange) ? $this->config->man_resource->loadRange : array();
        $this->view->isPost = 'no';
        $this->display();
    }

    /**
     * 设置预测工时
     */
    public function setPredictHours()
    {
        if($_POST)
        {
            $this->loadModel('setting')->setItem('system.' . $this->moduleName . '.taskHourPredict', $this->post->taskHourPredict);
            $this->loadModel('setting')->setItem('system.' . $this->moduleName . '.notTaskHourPredict', $this->post->notTaskHourPredict);
            $this->loadModel('setting')->setItem('system.' . $this->moduleName . '.predictHours', $this->post->predictHours);
            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array('result' => 'success', 'message' => $this->lang->saveSuccess, 'locate' => 'parent'));
        }
        $this->view->title = $this->lang->man_resource->setPredictHours;
        $this->view->taskHourPredict = isset($this->config->man_resource->taskHourPredict) ? $this->config->man_resource->taskHourPredict : 0;
        $this->view->notTaskHourPredict = isset($this->config->man_resource->notTaskHourPredict) ? $this->config->man_resource->notTaskHourPredict : 0;
        $this->view->predictHours = isset($this->config->man_resource->predictHours) ? $this->config->man_resource->predictHours : 0;
        $this->display();
    }

    /**
     * 导出组织资源日历
     */
    public function exportCompany($begin, $end, $mode)
    {
        $begin = (int)$begin;
        $end   = (int)$end;
        if($begin <= 0 || $end <= 0) return;

        if($_POST)
        {
            $calendarData = $this->man_resource->getOrgCalendarData(date('Y-m-d', $begin), date('Y-m-d', $end), $mode);
            $fields = array(
                'realname'        => $this->lang->man_resource->user,
                'estimated_hours' => $this->lang->man_resource->estimatedHoursCol,
                'consumed_hours'  => $this->lang->man_resource->consumeHoursCol,
                'remain_hours'    => $this->lang->man_resource->totalEstimatedHoursCol,
                'load_rate'       => $this->lang->man_resource->loadRateCol,
                'parallel_tasks'  => $this->lang->man_resource->taskCountCol,
            );
            
            $rows = array();
            foreach($calendarData as $data)
            {
                $row = new stdclass();
                $row->realname        = $data['realname'];
                $row->estimated_hours = $data['estimated_hours'];
                $row->consumed_hours  = $data['consumed_hours'];
                $row->remain_hours    = $data['remain_hours'];
                $row->load_rate       = $data['load_rate'] . '%';
                $row->parallel_tasks  = $data['parallel_tasks'];
                $rows[] = $row;
            }
            
            $this->post->set('fields', $fields);
            $this->post->set('rows', $rows);
            $this->post->set('kind', 'man_resource');
            $this->fetch('file', 'export2' . $this->post->fileType, $_POST);
        }
        
        $this->view->fileName = $this->lang->man_resource->company;
        $this->display($this->moduleName, 'export');
    }

    /**
     * 导出个人资源日历
     */
    public function exportPerson($begin, $end, $mode)
    {
        $begin = (int)$begin;
        $end   = (int)$end;
        if($begin <= 0 || $end <= 0) return;

        if($_POST)
        {
            $userID = $this->app->user->account;
            $data = $this->man_resource->getUserCalendarData($userID, date('Y-m-d', $begin), date('Y-m-d', $end), $mode);
            
            $fields = array('label' => '', 'value' => '');
            $rows = array();
            
            $row = new stdclass(); $row->label = $this->lang->man_resource->estimatedHoursCol;    $row->value = $data['estimated_hours']; $rows[] = $row;
            $row = new stdclass(); $row->label = $this->lang->man_resource->consumeHoursCol;      $row->value = $data['consumed_hours'];  $rows[] = $row;
            $row = new stdclass(); $row->label = $this->lang->man_resource->totalEstimatedHoursCol; $row->value = $data['remain_hours'];    $rows[] = $row;
            $row = new stdclass(); $row->label = $this->lang->man_resource->loadRateCol;         $row->value = $data['load_rate'] . '%'; $rows[] = $row;
            $row = new stdclass(); $row->label = $this->lang->man_resource->taskCountCol;        $row->value = $data['parallel_tasks'];  $rows[] = $row;

            $this->post->set('fields', $fields);
            $this->post->set('rows', $rows);
            $this->post->set('kind', 'man_resource');
            $this->fetch('file', 'export2' . $this->post->fileType, $_POST);
        }
        
        $this->view->fileName = $this->lang->man_resource->person;
        $this->display($this->moduleName, 'export');
    }

    /**
     * 导出项目资源日历
     */
    public function exportProject($projectID, $begin, $end, $mode)
    {
        $begin = (int)$begin;
        $end   = (int)$end;
        if($begin <= 0 || $end <= 0) return;

        if($_POST)
        {
            $calendarData = $this->man_resource->getProjectCalendarData($projectID, date('Y-m-d', $begin), date('Y-m-d', $end), $mode);
            $fields = array(
                'realname'        => $this->lang->man_resource->user,
                'estimated_hours' => $this->lang->man_resource->estimatedHoursCol,
                'consumed_hours'  => $this->lang->man_resource->consumeHoursCol,
                'remain_hours'    => $this->lang->man_resource->totalEstimatedHoursCol,
                'load_rate'       => $this->lang->man_resource->loadRateCol,
                'parallel_tasks'  => $this->lang->man_resource->taskCountCol,
            );
            
            $rows = array();
            foreach($calendarData as $data)
            {
                $row = new stdclass();
                $row->realname        = $data['realname'];
                $row->estimated_hours = $data['estimated_hours'];
                $row->consumed_hours  = $data['consumed_hours'];
                $row->remain_hours    = $data['remain_hours'];
                $row->load_rate       = $data['load_rate'] . '%';
                $row->parallel_tasks  = $data['parallel_tasks'];
                $rows[] = $row;
            }
            
            $this->post->set('fields', $fields);
            $this->post->set('rows', $rows);
            $this->post->set('kind', 'man_resource');
            $this->fetch('file', 'export2' . $this->post->fileType, $_POST);
        }
        
        $this->view->fileName = $this->lang->man_resource->projectCalendar;
        $this->display($this->moduleName, 'export');
    }

    /**
     * REST 鉴权：优先校验 X-Resource-Token 头或 ?token= 参数；
     * 命中则注入 operator，缺失或无效则回退到 ZenTao 会话鉴权。
     *
     * @return string|false 命中 token 时返回操作者账号；fall through 时返回 false。
     */
    private function verifyApiToken()
    {
        $token = '';
        if(!empty($_SERVER['HTTP_X_RESOURCE_TOKEN'])) $token = $_SERVER['HTTP_X_RESOURCE_TOKEN'];
        elseif(!empty($this->get->token))             $token = (string)$this->get->token;
        elseif(!empty($_GET['token']))                $token = (string)$_GET['token'];
        if($token === '') return false;

        $tokens = isset($this->config->man_resource->apiTokens) ? (array)$this->config->man_resource->apiTokens : array();
        if(!isset($tokens[$token]))
        {
            $this->send(array('result' => 'fail', 'message' => 'invalid token', 'code' => 401));
            return false; // unreachable, send() exits
        }
        return $tokens[$token];
    }

    /**
     * REST: GET /api/resource/calendar (plan §9.1)
     * Org/project calendar with daily load series.
     */
    public function apiCalendar()
    {
        $tokenOperator = $this->verifyApiToken();
        if($tokenOperator === false && empty($this->app->user)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        $startDate = isset($this->get->startDate) ? $this->get->startDate : (isset($_GET['startDate']) ? $_GET['startDate'] : '');
        $endDate   = isset($this->get->endDate)   ? $this->get->endDate   : (isset($_GET['endDate'])   ? $_GET['endDate']   : '');
        $userId    = isset($this->get->userId)    ? $this->get->userId    : (isset($_GET['userId'])    ? $_GET['userId']    : '');
        $projectId = isset($this->get->projectId) ? (int)$this->get->projectId : (isset($_GET['projectId']) ? (int)$_GET['projectId'] : 0);
        $status    = isset($this->get->status)    ? $this->get->status    : (isset($_GET['status'])    ? $_GET['status']    : 'todo');

        if(empty($startDate) || empty($endDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))
        {
            return $this->send(array('result' => 'fail', 'message' => 'startDate/endDate required'));
        }
        if(!in_array($status, array('todo', 'done'), true)) $status = 'todo';

        if($projectId > 0)
        {
            $data = $this->man_resource->getProjectCalendarData($projectId, $startDate, $endDate, $status, $userId);
        }
        else
        {
            $data = $this->man_resource->getOrgCalendarData($startDate, $endDate, $status, '', '', $userId, 0);
        }

        $series = $this->man_resource->getDailyLoadSeries(array_keys($data), $startDate, $endDate, $status);

        return $this->send(array('result' => 'success', 'data' => $data, 'series' => $series));
    }

    /**
     * REST: GET /api/resource/user/detail (plan §9.2)
     * Per-user summary plus task list.
     */
    public function apiUserDetail()
    {
        $tokenOperator = $this->verifyApiToken();
        if($tokenOperator === false && empty($this->app->user)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        $userId    = isset($this->get->userId)    ? $this->get->userId    : (isset($_GET['userId'])    ? $_GET['userId']    : '');
        $startDate = isset($this->get->startDate) ? $this->get->startDate : (isset($_GET['startDate']) ? $_GET['startDate'] : '');
        $endDate   = isset($this->get->endDate)   ? $this->get->endDate   : (isset($_GET['endDate'])   ? $_GET['endDate']   : '');
        $status    = isset($this->get->status)    ? $this->get->status    : (isset($_GET['status'])    ? $_GET['status']    : 'todo');

        if(empty($userId) || empty($startDate) || empty($endDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))
        {
            return $this->send(array('result' => 'fail', 'message' => 'userId/startDate/endDate required'));
        }
        if(!in_array($status, array('todo', 'done'), true)) $status = 'todo';

        $summary = $this->man_resource->getUserCalendarData($userId, $startDate, $endDate, $status);
        $tasks   = $this->man_resource->getUserTasks($userId, $startDate, $endDate, $status);

        return $this->send(array('result' => 'success', 'summary' => $summary, 'tasks' => array_values($tasks)));
    }

    /**
     * REST: POST /api/resource/simulation (plan §9.3)
     * Run load simulation from POST payload.
     */
    public function apiSimulation()
    {
        $tokenOperator = $this->verifyApiToken();
        if($tokenOperator === false && empty($this->app->user)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        if(empty($_POST))
        {
            return $this->send(array('result' => 'fail', 'message' => 'POST required'));
        }

        $result = $this->man_resource->simulateLoad();
        if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));

        return $this->send(array('result' => 'success', 'simulation' => $result));
    }
}
