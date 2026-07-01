<?php
class zhoubaoModel extends model
{
    /* 把 week 参数（2026_06_29 或 ''）解析成本周一的 Y-m-d，Task 4 会补全边界逻辑 */
    public function resolveWeekStart($week = '')
    {
        if($week === '' || $week === false) return date('Y-m-d', strtotime('monday this week'));
        return str_replace('_', '-', $week);
    }

    /* 看板行，Task 4 填实。此处返回空数组保证 browse 可渲染 */
    public function getBoardRows($weekStart, $pm = '', $fill = '')
    {
        return array();
    }
}
