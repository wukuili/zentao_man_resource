<?php
/**
 * 项目台账 — 模型层
 * 所有数据查询与写入均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class taizhangModel extends model
{
    const TABLE = 'zt_taizhang';

    /* 存储为 date 类型的字段：表单留空时须转为 null，否则严格模式下 MySQL 拒绝空字符串 */
    private static $dateFields = array('contractSignDate', 'planStartDate', 'actualStartDate', 'planEndDate', 'actualEndDate');

    /**
     * 自动同步：使台账始终镜像禅道「项目列表」(type=project、未删除)。
     *
     * 规则（幂等，每次进入 browse 都会执行）：
     *   1) projectID 不在「当前有效项目集」里的同步行 → 软删（含历史误同步的
     *      program、以及禅道里已删除的项目）；projectID=0 的手动行不受影响。
     *   2) 当前有效项目若已有台账行但处于软删状态(deleted=1) → 复活(deleted=0)，
     *      仅翻转删除标记，保留用户已填写的成本/人天等字段。
     *   3) 当前有效项目若无任何台账行 → 新建。
     *
     * 注意：因为要求「始终镜像项目列表」，手动删除某个 synced 行后，下次
     * 进入页面会被规则 2 自动复活——这是预期行为。
     *
     * @return int 本次新建 + 复活的台账条数
     */
    public function syncFromProjects()
    {
        /* 与禅道「项目列表」(project-browse) 对齐：仅 type=project、未删除。
         * 不可包含 program(项目集)，否则台账会多出项目列表里没有的行。
         * 注意：不能按 vision 过滤——台账作为自定义模块，其上下文 config->vision
         * 并非项目列表的视图值，加上后会把项目几乎全部误滤掉。 */
        $projects = $this->dao->select('id, name')->from('zt_project')
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->orderBy('id asc')
            ->fetchAll('id');

        if(empty($projects)) return 0;

        $validIDs = array_keys($projects);

        /* 清理与项目列表口径不符的同步行：projectID 不在"当前有效 type=project 集合"里的
         * 关联行（含历史误同步的 program、以及禅道里已被删除的项目）一律软删；
         * projectID=0 的手动台账行不受影响。保证台账始终与项目列表一致。 */
        $this->dao->update(self::TABLE)->set('deleted')->eq(1)
            ->where('deleted')->eq(0)
            ->andWhere('projectID')->ne(0)
            ->andWhere('projectID')->notIN($validIDs)
            ->exec();

        /* 已有台账行（含软删除），按 projectID 取其删除状态：
         * 用于区分「需复活的软删行」与「已存在的正常行」，避免唯一键冲突。 */
        $existing = $this->dao->select('projectID, deleted')->from(self::TABLE)
            ->where('projectID')->ne(0)
            ->fetchPairs('projectID', 'deleted');

        $now   = date('Y-m-d H:i:s');
        $count = 0;
        foreach($projects as $id => $project)
        {
            if(isset($existing[$id]))
            {
                /* 已有正常行：跳过；已有软删行：复活（只翻转删除标记，保留用户填写的字段） */
                if($existing[$id] == '1')
                {
                    $this->dao->update(self::TABLE)->set('deleted')->eq(0)->set('updatedAt')->eq($now)
                        ->where('projectID')->eq($id)->exec();
                    if(!dao::isError()) $count++;
                }
                continue;
            }

            $data = new stdclass();
            $data->projectID = $id;
            $data->shortName = $project->name;
            $data->createdAt = $now;
            $data->updatedAt = $now;
            $data->deleted   = 0;
            $this->dao->insert(self::TABLE)->data($data)->exec();
            if(!dao::isError()) $count++;
        }
        return $count;
    }

    /**
     * 获取台账列表（带关联项目信息和计算字段）
     *
     * @param string $phase      项目阶段过滤
     * @param string $pm         项目经理账号过滤
     * @param string $projectStatus 项目状态过滤
     * @param string $category   项目类别过滤
     * @return array
     */
    public function getList($phase = '', $pm = '', $projectStatus = '', $category = '')
    {
        $phase     = ($phase === false || $phase === null) ? '' : (string)$phase;
        $pm        = ($pm === false || $pm === null) ? '' : (string)$pm;
        $projectStatus = ($projectStatus === false || $projectStatus === null) ? '' : (string)$projectStatus;
        $category  = ($category === false || $category === null) ? '' : (string)$category;

        /* 台账表内容由 syncFromProjects() 维护：仅含 type=project 行，program 等已软删。
         * 故此处只需按 t.deleted 过滤，无需再 join 过滤 p.type（早期的 OR 括号写法在部分
         * 禅道版本下会生成异常 SQL，把结果误收窄，已移除）。 */
        $query = $this->dao->select('t.*, p.name AS projectName, p.pm AS pmAccount, p.status AS projectStatus, p.begin AS projectBegin, p.end AS projectEnd, p.path AS projectPath')
            ->from(self::TABLE . ' t')
            ->leftJoin('zt_project p')->on('t.projectID = p.id')
            ->where('t.deleted')->eq(0);

        if($phase !== '')     $query->andWhere('t.phase')->eq($phase);
        if($pm !== '')        $query->andWhere('p.pm')->eq($pm);
        if($projectStatus !== '') $query->andWhere('p.status')->eq($projectStatus);
        if($category !== '') $query->andWhere('t.projectCategory')->eq($category);

        $entries = $query->orderBy('t.sortOrder asc, t.id asc')->fetchAll();

        $userPairs = $this->loadModel('user')->getPairs('noletter');

        /* 收集关联项目ID，批量取实时数据，避免逐行查询 */
        $projectIDs = array();
        foreach($entries as $entry) if($entry->projectID) $projectIDs[$entry->projectID] = $entry->projectID;

        $phaseMap  = $this->getCurrentPhaseMap($projectIDs);
        $taskMap   = $this->getTaskStatMap($projectIDs);
        $memberMap = $this->getProjectMemberMap($projectIDs, $userPairs);

        $this->appendProgramNames($entries);

        foreach($entries as $entry)
        {
            $entry->pmName        = zget($userPairs, $entry->pmAccount, $entry->pmAccount);
            $entry->projectStatusName = $this->formatProjectStatus($entry->projectStatus);
            /* 展示用名称优先取 shortName，否则取项目名 */
            if(empty($entry->shortName) && !empty($entry->projectName)) $entry->shortName = $entry->projectName;

            $pid = (int)$entry->projectID;

            /* 项目阶段：取项目当前执行中的迭代/阶段名（无则保留已存值） */
            if(isset($phaseMap[$pid])) $entry->phase = $phaseMap[$pid];

            /* 当前项目情况：项目任务汇总（总数 / 已完成 / 未完成） */
            if(isset($taskMap[$pid]))
            {
                $total  = $taskMap[$pid]['total'];
                $done   = $taskMap[$pid]['done'];
                $undone = $total - $done;
                $entry->currentStatus = "总任务 {$total}　已完成 {$done}　未完成 {$undone}";

                if((float)$entry->investedHours <= 0) $entry->investedHours = round($this->hoursToManDays($taskMap[$pid]['consumed']), 2);
            }

            /* 近期项目成员：项目团队成员真实姓名 */
            $memberCount = 0;
            if(isset($memberMap[$pid]))
            {
                $entry->recentMembers = $memberMap[$pid]['names'];
                $memberCount = $memberMap[$pid]['count'];
            }

            if((float)$entry->initEstHours <= 0) $entry->initEstHours = $this->calcInitialManDays($entry->projectBegin, $entry->projectEnd, $memberCount);
            if((float)$entry->initBudget <= 0 && (float)$entry->initEstHours > 0) $entry->initBudget = $this->calcCostByManDays($entry->initEstHours);
            if((float)$entry->investedCost <= 0 && (float)$entry->investedHours > 0) $entry->investedCost = $this->calcCostByManDays($entry->investedHours);
            if((float)$entry->currentBudget <= 0 && (float)$entry->currentEstHours > 0) $entry->currentBudget = $this->calcCostByManDays($entry->currentEstHours);

            if(!isset($entry->outsourcingAmount)) $entry->outsourcingAmount = 0;
            if(!isset($entry->receivedAmount)) $entry->receivedAmount = 0;
            $entry->profitRate = $this->calcProfitRate((float)$entry->revenue, (float)$entry->currentBudget, (float)$entry->outsourcingAmount);
        }

        /* 同一项目集的项目归集到一起：按项目集链名分组排序，组内保持原 sortOrder/id 顺序；
         * 无项目集的行统一排到最后。 */
        usort($entries, function($a, $b)
        {
            $pa = (string)$a->programName;
            $pb = (string)$b->programName;
            if($pa !== $pb)
            {
                if($pa === '') return 1;
                if($pb === '') return -1;
                return strcmp($pa, $pb);
            }
            if((int)$a->sortOrder != (int)$b->sortOrder) return (int)$a->sortOrder - (int)$b->sortOrder;
            return (int)$a->id - (int)$b->id;
        });

        return $entries;
    }

    /**
     * 获取项目状态中文名称。
     *
     * @param string|null $status
     * @return string
     */
    public function formatProjectStatus($status)
    {
        $status = ($status === null || $status === false) ? '' : (string)$status;
        if($status === '') return '';

        $statusList = isset($this->lang->taizhang->projectStatusList) ? $this->lang->taizhang->projectStatusList : array();
        return zget($statusList, $status, $status);
    }

    /**
     * 批量获取每个项目「当前执行中」的迭代/阶段名。
     * 同一项目有多个执行中阶段时，取最近开始的一个。
     *
     * @param array $projectIDs projectID 列表
     * @return array projectID => 阶段名
     */
    public function getCurrentPhaseMap($projectIDs)
    {
        if(empty($projectIDs)) return array();

        /* 注意：begin 是 MySQL 保留字，这里用 id desc 取最近创建的执行中阶段，避免引用保留字列 */
        $rows = $this->dao->select('id, project, name')->from('zt_project')
            ->where('project')->in($projectIDs)
            ->andWhere('type')->in('sprint,stage')
            ->andWhere('status')->eq('doing')
            ->andWhere('deleted')->eq('0')
            ->orderBy('id desc')
            ->fetchGroup('project');

        $map = array();
        foreach($rows as $pid => $list) $map[(int)$pid] = $list[0]->name;
        return $map;
    }

    /**
     * 批量获取每个项目的任务统计（总数 / 已完成）。
     * 总数排除「已取消」任务；已完成 = status in (done, closed)。
     *
     * @param array $projectIDs projectID 列表
     * @return array projectID => ['total'=>int, 'done'=>int]
     */
    public function getTaskStatMap($projectIDs)
    {
        if(empty($projectIDs)) return array();

        $rows = $this->dao->select('project, status, count(*) as cnt, sum(consumed) as consumed')->from('zt_task')
            ->where('project')->in($projectIDs)
            ->andWhere('deleted')->eq('0')
            ->groupBy('project, status')
            ->fetchAll();

        $map = array();
        foreach($rows as $row)
        {
            $pid = (int)$row->project;
            if(!isset($map[$pid])) $map[$pid] = array('total' => 0, 'done' => 0, 'consumed' => 0);
            $map[$pid]['consumed'] += (float)$row->consumed;
            if($row->status == 'cancel') continue;           // 已取消不计入总数
            $map[$pid]['total'] += (int)$row->cnt;
            if($row->status == 'done' || $row->status == 'closed') $map[$pid]['done'] += (int)$row->cnt;
        }
        return $map;
    }

    /**
     * 批量获取每个项目的团队成员真实姓名（顿号分隔）。
     *
     * @param array $projectIDs projectID 列表
     * @param array $userPairs   account => 真实姓名 映射
     * @return array projectID => ['names' => string, 'count' => int]
     */
    public function getProjectMemberMap($projectIDs, $userPairs)
    {
        if(empty($projectIDs)) return array();

        $rows = $this->dao->select('root, account')->from('zt_team')
            ->where('root')->in($projectIDs)
            ->andWhere('type')->eq('project')
            ->fetchGroup('root');

        $map = array();
        foreach($rows as $root => $list)
        {
            $names = array();
            foreach($list as $r) $names[zget($userPairs, $r->account, $r->account)] = true;
            $map[(int)$root] = array('names' => implode('、', array_keys($names)), 'count' => count($names));
        }
        return $map;
    }

    /**
     * 批量解析每条台账所属的项目集链，写入 $entry->programName。
     * 多级项目集用 / 连接（如「集团交付/华东区」）；无项目集或手动行为空串。
     * zt_project.path 形如 ",1,5,12,"，末位是项目自身，前面是祖先项目集。
     *
     * @param array $entries 已含 projectID / projectPath 属性的台账行
     * @return void
     */
    public function appendProgramNames(array $entries)
    {
        $programIDs = array();
        foreach($entries as $entry)
        {
            $entry->programName = '';
            if(empty($entry->projectPath)) continue;
            foreach(explode(',', trim((string)$entry->projectPath, ',')) as $pid)
            {
                $pid = (int)$pid;
                if($pid && $pid != (int)$entry->projectID) $programIDs[$pid] = $pid;
            }
        }
        if(empty($programIDs)) return;

        $programNames = $this->dao->select('id, name')->from('zt_project')
            ->where('id')->in($programIDs)
            ->andWhere('type')->eq('program')
            ->andWhere('deleted')->eq('0')
            ->fetchPairs('id', 'name');

        foreach($entries as $entry)
        {
            if(empty($entry->projectPath)) continue;
            $chain = array();
            foreach(explode(',', trim((string)$entry->projectPath, ',')) as $pid)
            {
                $pid = (int)$pid;
                if($pid == (int)$entry->projectID) continue;
                if(isset($programNames[$pid])) $chain[] = $programNames[$pid];
            }
            $entry->programName = implode('/', $chain);
        }
    }

    /**
     * 根据项目起止日期和团队人数估算初始预估人天。
     */
    public function calcInitialManDays($begin, $end, $memberCount)
    {
        $memberCount = (int)$memberCount;
        if($memberCount <= 0) return 0;

        $workingDays = $this->countWeekdays($begin, $end);
        return round($workingDays * $memberCount, 2);
    }

    /**
     * 统计起止日期内的工作日数（周一至周五，含首尾日）。
     */
    public function countWeekdays($begin, $end)
    {
        $begin = (string)$begin;
        $end   = (string)$end;
        if($begin == '' || $end == '' || $begin == '0000-00-00' || $end == '0000-00-00') return 0;

        $start = strtotime($begin);
        $stop  = strtotime($end);
        if($start === false || $stop === false || $stop < $start) return 0;

        $days = 0;
        for($time = $start; $time <= $stop; $time = strtotime('+1 day', $time))
        {
            $weekday = (int)date('N', $time);
            if($weekday <= 5) $days++;
        }
        return $days;
    }

    /**
     * 工时换算成人天。
     */
    public function hoursToManDays($hours)
    {
        $hoursPerDay = isset($this->config->taizhang->hoursPerDay) ? (float)$this->config->taizhang->hoursPerDay : 8;
        if($hoursPerDay <= 0) $hoursPerDay = 8;
        return (float)$hours / $hoursPerDay;
    }

    /**
     * 按人天计算成本，返回万元。
     */
    public function calcCostByManDays($manDays)
    {
        $costPerDay = isset($this->config->taizhang->costPerDay) ? (float)$this->config->taizhang->costPerDay : 1100;
        return round((float)$manDays * $costPerDay / 10000, 2);
    }

    /**
     * 根据 ID 获取单条台账记录
     */
    public function getByID($id)
    {
        return $this->dao->select('*')->from(self::TABLE)->where('id')->eq((int)$id)->andWhere('deleted')->eq(0)->fetch();
    }

    /**
     * 新增台账
     */
    public function createEntry($data)
    {
        $data = $this->normalizeDateFields($data);
        $data->createdAt = date('Y-m-d H:i:s');
        $data->updatedAt = date('Y-m-d H:i:s');
        $data->deleted   = 0;
        $this->dao->insert(self::TABLE)->data($data, 'id')->exec();
        return !dao::isError();
    }

    /**
     * 更新台账
     */
    public function updateEntry($id, $data)
    {
        $data = $this->normalizeDateFields($data);
        unset($data->id, $data->createdAt, $data->deleted);
        $data->updatedAt = date('Y-m-d H:i:s');
        $this->dao->update(self::TABLE)->data($data)->where('id')->eq((int)$id)->exec();
        return !dao::isError();
    }

    /**
     * 表单留空的日期字段转为 null，避免严格模式下 MySQL 拒绝空字符串写入 date 列。
     */
    private function normalizeDateFields($data)
    {
        foreach(self::$dateFields as $field)
        {
            if(isset($data->$field) && $data->$field === '') $data->$field = null;
        }
        return $data;
    }

    /**
     * 软删除台账
     */
    public function deleteEntry($id)
    {
        $this->dao->update(self::TABLE)->set('deleted')->eq(1)->where('id')->eq((int)$id)->exec();
        return !dao::isError();
    }

    /**
     * 计算利润率（%）
     * @param float      $revenue 收入/合同金额
     * @param float      $cost    当前预估成本
     * @param float      $outsourcing 外采金额
     * @return float|null null 表示无法计算（revenue=0）
     */
    public function calcProfitRate($revenue, $cost, $outsourcing = 0)
    {
        if($revenue <= 0) return null;
        return round(($revenue - $cost - $outsourcing) / $revenue * 100, 2);
    }

    /**
     * 获取台账里出现过的项目经理账号（用于筛选下拉）
     */
    public function getPMOptions()
    {
        $rows = $this->dao->select('DISTINCT p.pm AS pmAccount')
            ->from(self::TABLE . ' t')
            ->leftJoin('zt_project p')->on('t.projectID = p.id')
            ->where('t.deleted')->eq(0)
            ->andWhere('p.pm')->ne('')
            ->fetchAll();

        $userPairs = $this->loadModel('user')->getPairs('noletter');
        $result    = array('' => '全部');
        foreach($rows as $row)
        {
            $acc = $row->pmAccount;
            if($acc) $result[$acc] = zget($userPairs, $acc, $acc);
        }
        return $result;
    }

    /**
     * 计算汇总行数据
     */
    public function calcSummary(array $entries)
    {
        $s = array(
            'initEstHours'    => 0,
            'initBudget'      => 0,
            'investedHours'   => 0,
            'investedCost'    => 0,
            'currentEstHours' => 0,
            'currentBudget'   => 0,
            'revenue'         => 0,
            'outsourcingAmount' => 0,
            'receivedAmount'   => 0,
        );
        foreach($entries as $e)
        {
            $s['initEstHours']    += (float)$e->initEstHours;
            $s['initBudget']      += (float)$e->initBudget;
            $s['investedHours']   += (float)$e->investedHours;
            $s['investedCost']    += (float)$e->investedCost;
            $s['currentEstHours'] += (float)$e->currentEstHours;
            $s['currentBudget']   += (float)$e->currentBudget;
            $s['revenue']         += (float)$e->revenue;
            $s['outsourcingAmount'] += (float)$e->outsourcingAmount;
            $s['receivedAmount']   += (float)$e->receivedAmount;
        }
        $s['profitRate'] = $this->calcProfitRate($s['revenue'], $s['currentBudget'], $s['outsourcingAmount']);
        foreach($s as $k => $v)
        {
            if(is_float($v)) $s[$k] = round($v, 2);
        }
        return $s;
    }
}
