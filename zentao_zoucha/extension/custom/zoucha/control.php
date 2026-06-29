<?php
/**
 * 项目走查 — 控制器
 * 提供走查列表浏览、CSV 导出、单项目诊断。
 */
class zoucha extends control
{
    /**
     * 走查列表页（默认入口）。
     *
     * 注意：禅道 PATH_INFO 路由按「位置」把 URL 参数映射到方法形参，
     * 分页参数 $pageID 必须声明为形参，否则翻页链接永远落在第 1 页。
     *
     * @param string $rule   问题类型过滤（规则键；'' 或 'all' 表示全部）
     * @param string $pm     项目经理过滤（PM 账号；'' 或 'all' 表示全部，'__none__' 表示未指派）
     * @param int    $pageID 页码
     */
    public function browse($rule = '', $pm = '', $pageID = 1)
    {
        $rule   = ($rule === false || $rule === null) ? '' : (string)$rule;
        $pm     = ($pm   === false || $pm   === null) ? '' : (string)$pm;
        $pageID = (int)$pageID;

        /* 禅道 PATH_INFO 模式下查询串参数不会自动绑定到形参，故 POST 读 $_POST、GET 读 $_GET 兜底，
         * 保证 Task5 的 JS 以 ?rule=...&pm=...&pageID=... 跳转时筛选/翻页仍生效。 */
        if($this->server->request_method == 'POST')
        {
            $rule   = isset($_POST['rule']) ? (string)$_POST['rule'] : '';
            $pm     = isset($_POST['pm'])   ? (string)$_POST['pm']   : '';
            $pageID = 1;
        }
        else
        {
            $rule   = isset($_GET['rule'])   ? (string)$_GET['rule'] : $rule;
            $pm     = isset($_GET['pm'])     ? (string)$_GET['pm']   : $pm;
            $pageID = isset($_GET['pageID']) ? (int)$_GET['pageID']  : $pageID;
        }
        if($rule === 'all') $rule = '';
        if($pm   === 'all') $pm   = '';

        /* $this->zoucha 是禅道自动加载的 zouchaModel 实例（与控制器同名模型自动注入）。 */
        $allResults = $this->zoucha->inspect();

        /* 统计每条规则命中的项目数 + 每个项目经理的问题项目数（均基于全集，不受当前筛选影响）。
         * pmCounts 以 PM 账号为键（空账号即未指派）；pmNames 维护 账号=>真实姓名 供下拉展示。 */
        $ruleCounts = array();
        $pmCounts   = array();
        $pmNames    = array();
        foreach($allResults as $row)
        {
            foreach($row->hits as $h)
            {
                if(!isset($ruleCounts[$h])) $ruleCounts[$h] = 0;
                $ruleCounts[$h]++;
            }
            $pmKey = (string)$row->pm;
            if(!isset($pmCounts[$pmKey])) $pmCounts[$pmKey] = 0;
            $pmCounts[$pmKey]++;
            if(!isset($pmNames[$pmKey])) $pmNames[$pmKey] = (string)$row->pmName;
        }
        /* 下拉项按问题项目数降序，问题多的项目经理排在前面 */
        arsort($pmCounts);

        /* 按规则 + 项目经理过滤（'__none__' 匹配空账号的未指派项目） */
        $pmMatch  = ($pm === '__none__') ? '' : $pm;
        $filtered = array();
        foreach($allResults as $row)
        {
            if($rule !== '' && !in_array($rule, $row->hits, true)) continue;
            if($pm   !== '' && (string)$row->pm !== $pmMatch)      continue;
            $filtered[] = $row;
        }

        /* 分页 */
        $recPerPage = isset($this->config->zoucha->recPerPage) ? (int)$this->config->zoucha->recPerPage : 20;
        if($recPerPage <= 0) $recPerPage = 20;
        $total      = count($filtered);
        $pageTotal  = $total > 0 ? (int)ceil($total / $recPerPage) : 1;
        $pageID     = max(1, min($pageID, $pageTotal));
        $offset     = ($pageID - 1) * $recPerPage;
        $results    = array_slice($filtered, $offset, $recPerPage);

        $this->view->title      = $this->lang->zoucha->browse;
        $this->view->results    = $results;
        $this->view->rule       = $rule;
        $this->view->pm         = $pm;
        $this->view->pageID     = $pageID;
        $this->view->pageTotal  = $pageTotal;
        $this->view->recPerPage = $recPerPage;
        $this->view->total      = $total;
        $this->view->totalAll   = count($allResults);
        $this->view->ruleCounts = $ruleCounts;
        $this->view->pmCounts   = $pmCounts;
        $this->view->pmNames    = $pmNames;

        $this->display();
    }

    /**
     * 导出走查结果为 CSV 文件。
     *
     * @param string $rule 同 browse()，空串表示全部
     * @param string $pm   同 browse()，空串表示全部，'__none__' 表示未指派
     */
    public function export($rule = '', $pm = '')
    {
        $rule = isset($_GET['rule']) ? (string)$_GET['rule'] : (string)$rule;
        $pm   = isset($_GET['pm'])   ? (string)$_GET['pm']   : (string)$pm;
        if($rule === 'all') $rule = '';
        if($pm   === 'all') $pm   = '';

        /* $this->zoucha 是禅道自动加载的 zouchaModel 实例。 */
        $allResults = $this->zoucha->inspect();

        /* 按规则 + 项目经理过滤（与 browse() 口径一致） */
        $pmMatch = ($pm === '__none__') ? '' : $pm;
        $rows    = array();
        foreach($allResults as $row)
        {
            if($rule !== '' && !in_array($rule, $row->hits, true)) continue;
            if($pm   !== '' && (string)$row->pm !== $pmMatch)      continue;
            $rows[] = $row;
        }

        /* 规则标签映射 */
        $ruleList = isset($this->lang->zoucha->ruleList) ? (array)$this->lang->zoucha->ruleList : array();

        /* CSV 公式注入防护：单元格首字符为 = + - @ 时前置单引号，避免 Excel 当作公式执行 */
        $csvSafe = function($v) {
            $v = (string)$v;
            if($v !== '' && strpos('=+-@', $v[0]) !== false) $v = "'" . $v;
            return $v;
        };

        /* 输出 CSV */
        $filename = 'zoucha-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        /* UTF-8 BOM，使 Excel 正确识别中文 */
        fwrite($out, "\xEF\xBB\xBF");

        /* 表头 */
        fputcsv($out, array('序号', '项目', '所属项目集', '负责人', '项目状态', '走查结果', '任务数', '逾期数', '执行数', '最后任务更新'));

        /* 数据行 */
        $i = 1;
        foreach($rows as $row)
        {
            $hitLabels = array();
            foreach($row->hits as $h)
            {
                $hitLabels[] = isset($ruleList[$h]) ? $ruleList[$h] : $h;
            }

            fputcsv($out, array(
                $i++,
                $csvSafe($row->projectName),
                $row->programName !== '' ? $csvSafe($row->programName) : '-',
                $csvSafe($row->pmName),
                $csvSafe($row->statusName),
                implode('、', $hitLabels),
                $row->taskCount,
                $row->overdueCount,
                $row->executionCount,
                $row->lastTaskEdited !== null ? $row->lastTaskEdited : '-',
            ));
        }

        fclose($out);
        exit;
    }

    /**
     * 列表页点击「走查结果」标签时，返回该项目命中该规则的明细列表（JSON）。
     * 供前端弹框渲染。任务行带禅道任务详情链接。
     *
     * @param int    $projectID
     * @param string $rule overdue|longTask|stale|noTask|noExecution
     */
    public function detail($projectID = 0, $rule = '')
    {
        /* 禅道 PATH_INFO 模式下命名参数不可靠，统一从 $_GET 兜底 */
        $projectID = isset($_GET['projectID']) ? (int)$_GET['projectID'] : (int)$projectID;
        $rule      = isset($_GET['rule'])      ? (string)$_GET['rule']   : (string)$rule;

        $data  = $this->zoucha->getRuleItems($projectID, $rule);
        $items = $data['items'];

        /* 每条规则展示的列（key 对应 item 字段） */
        $columnDefs = array(
            'overdue'  => array('id' => '任务', 'statusName' => '状态', 'ownerName' => '指派给', 'deadline' => '截止日期'),
            'longTask' => array('id' => '任务', 'statusName' => '状态', 'ownerName' => '指派给', 'estStarted' => '预计开始', 'deadline' => '截止日期', 'spanDays' => '工期(天)'),
            'stale'    => array('id' => '任务', 'statusName' => '状态', 'ownerName' => '指派给', 'lastActivity' => '最近活动'),
        );
        $columns = isset($columnDefs[$rule]) ? $columnDefs[$rule] : array();

        /* 给任务行补任务详情链接 */
        foreach($items as $it)
        {
            $it->url = helper::createLink('task', 'view', "taskID={$it->id}");
        }

        $ruleList  = isset($this->lang->zoucha->ruleList) ? (array)$this->lang->zoucha->ruleList : array();
        $ruleLabel = isset($ruleList[$rule]) ? $ruleList[$rule] : $rule;

        /* 无明细列表的规则给出说明文案 */
        $emptyNote = '';
        if(empty($columns))
        {
            $noteMap = array(
                'noTask'      => '该项目下没有任何任务（已排除已关闭执行下的任务）。',
                'noExecution' => '该项目下没有任何未删除的执行（迭代 / 阶段）。',
            );
            $emptyNote = isset($noteMap[$rule]) ? $noteMap[$rule] : '该规则无可展开的明细。';
        }

        $response = array(
            'result'    => 'success',
            'project'   => $data['project'],
            'rule'      => $rule,
            'ruleLabel' => $ruleLabel,
            'columns'   => $columns,
            'items'     => $items,
            'note'      => $emptyNote,
        );

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 诊断单个项目的走查判定明细（仅管理员）。
     *
     * @param int $projectID
     */
    public function diagnostic($projectID = 0)
    {
        if(empty($this->app->user->admin)) die('仅管理员可访问。');

        $data    = $this->zoucha->getInspectData((int)$projectID);
        $project = $data['project'];
        $eval    = isset($data['evaluated']) ? $data['evaluated'] : array();

        header('Content-Type: text/plain; charset=utf-8');
        echo "== 项目走查诊断 #{$projectID} ==\n\n";
        echo "项目: " . ($project ? $project->name : '（不存在或非进行中项目）') . "\n";
        echo "执行数(未删除): " . count($data['executions']) . "\n";
        echo "参与判定任务数: " . count($data['tasks']) . "\n";
        echo "阈值: staleDays=" . (int)$this->config->zoucha->staleDays
            . " longTaskDays=" . (int)$this->config->zoucha->longTaskDays
            . " overdueMin=" . (int)$this->config->zoucha->overdueMin . "\n";
        echo "启用规则: " . implode(',', $this->config->zoucha->rules) . "\n\n";
        echo "判定结果:\n" . json_encode($eval, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        echo "任务明细:\n";
        foreach($data['tasks'] as $t)
        {
            echo "  #{$t->id} status={$t->status} est={$t->estStarted} deadline={$t->deadline} opened={$t->openedDate} edited={$t->lastEditedDate}\n";
        }
        exit;
    }
}
