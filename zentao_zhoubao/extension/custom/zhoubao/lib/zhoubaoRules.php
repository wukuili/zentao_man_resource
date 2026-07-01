<?php
/**
 * 项目周报规则库 —— 无框架依赖，纯静态方法，可脱离禅道运行时单元测试。
 */
class zhoubaoRules
{
    /* 判断日期字符串是否为有效非脏日期 */
    public static function isValidDate($date)
    {
        if(empty($date)) return false;
        $d = substr((string)$date, 0, 10);
        if($d === '0000-00-00') return false;
        return strtotime($d) !== false;
    }

    /* 把任务分为本周完成 / 本周应完成未完成 / 逾期 */
    public static function classifyTasks(array $tasks, $weekStart, $weekEnd, $today)
    {
        $done = array(); $undone = array(); $overdue = array();
        $doneStatus = array('done', 'closed');
        $deadStatus = array('done', 'closed', 'cancel');

        foreach($tasks as $task)
        {
            $status   = isset($task['status']) ? $task['status'] : '';
            $deadline = isset($task['deadline']) ? substr((string)$task['deadline'], 0, 10) : '';
            $finished = isset($task['finishedDate']) ? substr((string)$task['finishedDate'], 0, 10) : '';

            // 本周完成：状态为 done/closed 且完成日落在本周内
            if(in_array($status, $doneStatus) && self::isValidDate($finished)
               && $finished >= $weekStart && $finished <= $weekEnd)
            {
                $done[] = $task;
                continue;
            }

            // 已完成/取消的不再计入未完成或逾期
            if(in_array($status, $deadStatus)) continue;
            if(!self::isValidDate($deadline)) continue;

            if($deadline < $today)
            {
                $daysOverdue = (int)floor((strtotime($today) - strtotime($deadline)) / 86400);
                $task['daysOverdue'] = $daysOverdue;
                $overdue[] = $task;
            }
            elseif($deadline >= $weekStart && $deadline <= $weekEnd)
            {
                $undone[] = $task;
            }
        }

        return array('done' => $done, 'undone' => $undone, 'overdue' => $overdue);
    }

    /* 组装企微群机器人 markdown 文本。已交=submitted；缺交=none+draft */
    public static function buildWecomMarkdown(array $rows, $year, $week)
    {
        $submitted = array(); $missing = array();
        foreach($rows as $r)
        {
            if(isset($r['status']) && $r['status'] === 'submitted') $submitted[] = $r;
            else $missing[] = $r;
        }

        $lines = array();
        $lines[] = "**【项目周报提醒 · 第{$week}周】**";
        $lines[] = "✅ 已交 " . count($submitted) . " / ❌ 缺交 " . count($missing);

        if(!empty($missing))
        {
            $names = array();
            foreach($missing as $r) $names[] = "{$r['project']}（{$r['pm']}）";
            $lines[] = "> 缺交：" . implode('、', $names);
        }
        if(!empty($submitted))
        {
            $lines[] = "> 已交摘要：";
            foreach($submitted as $r)
            {
                $lines[] = "> - {$r['project']} 完成{$r['doneCount']}任务 / 逾期{$r['overdueCount']}";
            }
        }
        return implode("\n", $lines);
    }
}
