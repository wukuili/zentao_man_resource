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

    /* 活跃项目：type=project、未删除、非关闭 */
    public function getActiveProjects()
    {
        return $this->dao->select('id, name, PM')->from(TABLE_PROJECT)
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->ne('closed')
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
    public function getBoardRows($weekStart, $pm = '', $fill = '')
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

        $rows = array();
        foreach($projects as $pid => $project)
        {
            if($pm !== '' && $pm !== 'all' && $project->PM !== $pm) continue;

            $tasks = isset($tasksByProject[$pid]) ? $tasksByProject[$pid] : array();
            $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

            $report = isset($reportMap[$pid]) ? $reportMap[$pid] : null;
            $status = $report ? $report->status : 'none';

            if($fill !== '' && $fill !== 'all' && $status !== $fill) continue;

            $rows[] = (object)array(
                'project'      => $pid,
                'projectName'  => $project->name,
                'pm'           => $project->PM,
                'status'       => $status,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
            );
        }
        return $rows;
    }
}
