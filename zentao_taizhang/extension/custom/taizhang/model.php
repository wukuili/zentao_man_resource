<?php
/**
 * 项目台账 — 模型层
 * 所有数据查询与写入均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class taizhang extends model
{
    const TABLE = 'zt_taizhang';

    /**
     * 获取台账列表（带关联项目信息和计算字段）
     *
     * @param string $phase      项目阶段过滤
     * @param string $pm         项目经理账号过滤
     * @param string $rdManager  研发经理账号过滤
     * @return array
     */
    public function getList($phase = '', $pm = '', $rdManager = '')
    {
        $query = $this->dao->select('t.*, p.name AS projectName, p.pm AS pmAccount, p.status AS projectStatus')
            ->from(self::TABLE . ' t')
            ->leftJoin('zt_project p')->on('t.projectID = p.id')
            ->where('t.deleted')->eq(0);

        if($phase !== '')     $query->andWhere('t.phase')->eq($phase);
        if($pm !== '')        $query->andWhere('p.pm')->eq($pm);
        if($rdManager !== '') $query->andWhere('t.rdManager')->eq($rdManager);

        $entries = $query->orderBy('t.sortOrder asc, t.id asc')->fetchAll();

        $userPairs = $this->loadModel('user')->getPairs('noletter');
        foreach($entries as $entry)
        {
            $entry->pmName        = zget($userPairs, $entry->pmAccount, $entry->pmAccount);
            $entry->rdManagerName = zget($userPairs, $entry->rdManager, $entry->rdManager);
            $entry->profitRate    = $this->calcProfitRate((float)$entry->revenue, (float)$entry->currentBudget);
            /* 展示用名称优先取 shortName，否则取项目名 */
            if(empty($entry->shortName) && !empty($entry->projectName)) $entry->shortName = $entry->projectName;
        }

        return $entries;
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
        unset($data->id, $data->createdAt, $data->deleted);
        $data->updatedAt = date('Y-m-d H:i:s');
        $this->dao->update(self::TABLE)->data($data)->where('id')->eq((int)$id)->exec();
        return !dao::isError();
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
     * @return float|null null 表示无法计算（revenue=0）
     */
    public function calcProfitRate($revenue, $cost)
    {
        if($revenue <= 0) return null;
        return round(($revenue - $cost) / $revenue * 100, 2);
    }

    /**
     * 获取所有已用到的研发经理账号 → 真实姓名映射（用于筛选下拉）
     */
    public function getRDManagerOptions()
    {
        $accounts = $this->dao->select('DISTINCT rdManager')
            ->from(self::TABLE)
            ->where('deleted')->eq(0)
            ->andWhere('rdManager')->ne('')
            ->fetchPairs('rdManager', 'rdManager');

        $userPairs = $this->loadModel('user')->getPairs('noletter');
        $result    = array('' => '全部');
        foreach($accounts as $acc)
        {
            $result[$acc] = zget($userPairs, $acc, $acc);
        }
        return $result;
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
        }
        $s['profitRate'] = $this->calcProfitRate($s['revenue'], $s['currentBudget']);
        foreach($s as $k => $v)
        {
            if(is_float($v)) $s[$k] = round($v, 2);
        }
        return $s;
    }
}
