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
     * @param int    $pageID 页码
     */
    public function browse($rule = '', $pageID = 1)
    {
        $rule   = ($rule === false || $rule === null) ? '' : (string)$rule;
        $pageID = (int)$pageID;

        /* 禅道 PATH_INFO 模式下查询串参数不会自动绑定到形参，故 POST 读 $_POST、GET 读 $_GET 兜底，
         * 保证 Task5 的 JS 以 ?rule=...&pageID=... 跳转时筛选/翻页仍生效。 */
        if($this->server->request_method == 'POST')
        {
            $rule   = isset($_POST['rule']) ? (string)$_POST['rule'] : '';
            $pageID = 1;
        }
        else
        {
            $rule   = isset($_GET['rule'])   ? (string)$_GET['rule'] : $rule;
            $pageID = isset($_GET['pageID']) ? (int)$_GET['pageID']  : $pageID;
        }
        if($rule === 'all') $rule = '';

        /* $this->zoucha 是禅道自动加载的 zouchaModel 实例（与控制器同名模型自动注入）。 */
        $allResults = $this->zoucha->inspect();

        /* 统计每条规则命中的项目数（基于全集，不受当前筛选影响） */
        $ruleCounts = array();
        foreach($allResults as $row)
        {
            foreach($row->hits as $h)
            {
                if(!isset($ruleCounts[$h])) $ruleCounts[$h] = 0;
                $ruleCounts[$h]++;
            }
        }

        /* 按规则过滤 */
        if($rule !== '')
        {
            $filtered = array();
            foreach($allResults as $row)
            {
                if(in_array($rule, $row->hits, true)) $filtered[] = $row;
            }
        }
        else
        {
            $filtered = $allResults;
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
        $this->view->pageID     = $pageID;
        $this->view->pageTotal  = $pageTotal;
        $this->view->recPerPage = $recPerPage;
        $this->view->total      = $total;
        $this->view->totalAll   = count($allResults);
        $this->view->ruleCounts = $ruleCounts;

        $this->display();
    }

    /**
     * 导出走查结果为 CSV 文件。
     *
     * @param string $rule 同 browse()，空串表示全部
     */
    public function export($rule = '')
    {
        $rule = isset($_GET['rule']) ? (string)$_GET['rule'] : (string)$rule;
        if($rule === 'all') $rule = '';

        /* $this->zoucha 是禅道自动加载的 zouchaModel 实例。 */
        $allResults = $this->zoucha->inspect();

        /* 按规则过滤 */
        if($rule !== '')
        {
            $rows = array();
            foreach($allResults as $row)
            {
                if(in_array($rule, $row->hits, true)) $rows[] = $row;
            }
        }
        else
        {
            $rows = $allResults;
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
