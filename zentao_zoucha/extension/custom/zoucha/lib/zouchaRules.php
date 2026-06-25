<?php
/**
 * 走查规则引擎（纯函数式，无禅道依赖，可独立单测）。
 *
 * 输入已由调用方过滤：$tasks 仅含未删除、且不属于"已关闭执行"的任务。
 */
class zouchaRules
{
    /**
     * @param array  $tasks          任务数组，元素为对象或关联数组，含键
     *                               status, estStarted, deadline, openedDate, lastEditedDate
     * @param int    $executionCount 该项目未删除执行(迭代/阶段)数量
     * @param string $today          今天 'Y-m-d'
     * @param int    $staleDays      R2 阈值（天）
     * @param int    $longTaskDays   R5 阈值（天）
     * @param int    $overdueMin     R3 命中所需最少逾期任务数
     * @param array  $enabledRules   启用的规则键
     * @return array ['hits'=>string[], 'taskCount'=>int, 'overdueCount'=>int,
     *                'executionCount'=>int, 'lastTaskEdited'=>?string]
     */
    public static function evaluate($tasks, $executionCount, $today, $staleDays, $longTaskDays, $overdueMin, $enabledRules)
    {
        $doneStatuses   = array('done', 'closed', 'cancel'); // 不算逾期
        $closedStatuses = array('closed', 'cancel');         // R2 视为"已关闭状态"

        $taskCount   = count($tasks);
        $todayTs     = strtotime($today);
        $staleCutoff = date('Y-m-d', strtotime("-{$staleDays} days", $todayTs)); // 早于此日期=超过 staleDays 天未动

        $overdueCount      = 0;
        $hasLongTask       = false;
        $openCount         = 0;     // 未关闭状态任务数
        $openActivityMax   = null;  // 未关闭任务的最近活动日期（'Y-m-d'）
        $globalActivityMax = null;  // 全部任务的最近活动日期时间（用于展示列）

        foreach($tasks as $t)
        {
            $status   = (string)self::val($t, 'status');
            $deadline = self::cleanDate(self::val($t, 'deadline'));
            $estStart = self::cleanDate(self::val($t, 'estStarted'));

            /* 最近活动 = openedDate 与 lastEditedDate 中较晚的有效值（新建未编辑的任务用 openedDate） */
            $activity = self::maxStr(self::cleanDateTime(self::val($t, 'openedDate')),
                                     self::cleanDateTime(self::val($t, 'lastEditedDate')));
            if($activity !== null) $globalActivityMax = self::maxStr($globalActivityMax, $activity);

            /* R3 逾期 */
            if($deadline !== null && strtotime($deadline) < $todayTs && !in_array($status, $doneStatuses, true))
            {
                $overdueCount++;
            }

            /* R5 任务跨度 */
            if($deadline !== null && $estStart !== null)
            {
                $spanDays = (strtotime($deadline) - strtotime($estStart)) / 86400;
                if($spanDays > $longTaskDays) $hasLongTask = true;
            }

            /* R2 收集未关闭任务的最近活动（按日期粒度比较） */
            if(!in_array($status, $closedStatuses, true))
            {
                $openCount++;
                $actDate = ($activity !== null) ? substr($activity, 0, 10) : null;
                $openActivityMax = self::maxStr($openActivityMax, $actDate);
            }
        }

        $hits = array();

        if(in_array('noTask', $enabledRules, true) && $taskCount === 0) $hits[] = 'noTask';

        if(in_array('stale', $enabledRules, true) && $taskCount > 0 && $openCount > 0
            && ($openActivityMax === null || $openActivityMax < $staleCutoff))
        {
            $hits[] = 'stale';
        }

        if(in_array('overdue', $enabledRules, true) && $overdueCount >= max(1, (int)$overdueMin)) $hits[] = 'overdue';

        if(in_array('noExecution', $enabledRules, true) && (int)$executionCount === 0) $hits[] = 'noExecution';

        if(in_array('longTask', $enabledRules, true) && $hasLongTask) $hits[] = 'longTask';

        return array(
            'hits'           => $hits,
            'taskCount'      => $taskCount,
            'overdueCount'   => $overdueCount,
            'executionCount' => (int)$executionCount,
            'lastTaskEdited' => $globalActivityMax !== null ? substr($globalActivityMax, 0, 10) : null,
        );
    }

    /* 读取对象或数组的字段 */
    private static function val($t, $key)
    {
        if(is_array($t)) return isset($t[$key]) ? $t[$key] : null;
        return (is_object($t) && isset($t->$key)) ? $t->$key : null;
    }

    /* date 列规整：空/全零返回 null，否则返回 'Y-m-d' */
    private static function cleanDate($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return substr($v, 0, 10);
    }

    /* datetime 列规整：空/全零返回 null，否则原样（'Y-m-d H:i:s'） */
    private static function cleanDateTime($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return $v;
    }

    /* 两个字符串取字典序较大者（日期字符串字典序即时间序），null 视为最小 */
    private static function maxStr($a, $b)
    {
        if($a === null) return $b;
        if($b === null) return $a;
        return ($a >= $b) ? $a : $b;
    }
}
