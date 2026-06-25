<?php
/**
 * zouchaRules 独立单测——无需禅道环境，直接 `php tests/test_rules.php` 运行。
 */
require __DIR__ . '/../extension/custom/zoucha/lib/zouchaRules.php';

$fail = 0;
function check($name, $cond)
{
    global $fail;
    if($cond) { echo "PASS  $name\n"; }
    else      { echo "FAIL  $name\n"; $fail++; }
}

function task($status, $estStarted, $deadline, $openedDate, $lastEditedDate)
{
    return array(
        'status'         => $status,
        'estStarted'     => $estStarted,
        'deadline'       => $deadline,
        'openedDate'     => $openedDate,
        'lastEditedDate' => $lastEditedDate,
    );
}

$today = '2026-06-25';
$all   = array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');

/* R1：无任务 */
$r = zouchaRules::evaluate(array(), 3, $today, 7, 14, 1, $all);
check('R1 无任务命中', in_array('noTask', $r['hits'], true));
check('R1 无任务不误命中 stale/overdue/longTask',
    !in_array('stale', $r['hits'], true) && !in_array('overdue', $r['hits'], true) && !in_array('longTask', $r['hits'], true));
check('R1 taskCount=0', $r['taskCount'] === 0);

/* R4：无执行（即使有任务也独立判定） */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-20', '2026-06-30', '2026-06-24 10:00:00', '2026-06-24 10:00:00')), 0, $today, 7, 14, 1, $all);
check('R4 无执行命中', in_array('noExecution', $r['hits'], true));
check('R4 有执行不命中', !in_array('noExecution', zouchaRules::evaluate(array(), 2, $today, 7, 14, 1, $all)['hits'], true));

/* R3：逾期任务 */
$tasks = array(
    task('doing', '2026-06-01', '2026-06-10', '2026-06-01 09:00:00', '2026-06-20 09:00:00'), // 逾期
    task('done',  '2026-06-01', '2026-06-05', '2026-06-01 09:00:00', '2026-06-20 09:00:00'), // 已完成，不算逾期
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R3 逾期命中', in_array('overdue', $r['hits'], true));
check('R3 逾期数=1（done 不计）', $r['overdueCount'] === 1);

/* R3：overdueMin 门槛——1 个逾期但门槛=2 时不命中 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-20 09:00:00', '2026-06-20 09:00:00')), 1, $today, 7, 14, 2, $all);
check('R3 未达门槛不命中', !in_array('overdue', $r['hits'], true));

/* R5：任务跨度 > 14 天 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-20', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R5 超期命中(19天)', in_array('longTask', $r['hits'], true));
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R5 未超期不命中(9天)', !in_array('longTask', $r['hits'], true));

/* R2：全部未关闭任务都早于 7 天前 → 命中 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'), // 最近活动 6-10，早于 6-18
    task('wait',  '2026-05-01', '2026-07-01', '2026-06-05 09:00:00', '2026-06-05 09:00:00'),
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 近一周未更新命中', in_array('stale', $r['hits'], true));

/* R2：有一个任务近一周内有更新 → 不命中 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'),
    task('doing', '2026-05-01', '2026-07-01', '2026-06-24 09:00:00', '2026-06-24 09:00:00'), // 昨天更新
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 有近期更新不命中', !in_array('stale', $r['hits'], true));

/* R2：全部任务已关闭/取消 → 无未关闭任务，不命中（避免空集真空命中） */
$tasks = array(task('closed', '2026-05-01', '2026-06-01', '2026-05-01 09:00:00', '2026-05-10 09:00:00'));
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 全关闭任务不命中', !in_array('stale', $r['hits'], true));

/* lastTaskEdited 取全部任务最近活动日期 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'),
    task('doing', '2026-05-01', '2026-07-01', '2026-06-15 09:00:00', '2026-06-22 09:00:00'),
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('lastTaskEdited=2026-06-22', $r['lastTaskEdited'] === '2026-06-22');

/* 停用规则：rules 不含 overdue 时即使逾期也不命中 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-20 09:00:00', '2026-06-20 09:00:00')), 1, $today, 7, 14, 1, array('noTask'));
check('停用 overdue 后不命中', !in_array('overdue', $r['hits'], true));

/* 脏日期：0000-00-00 当作空，不参与逾期/跨度 */
$r = zouchaRules::evaluate(array(task('doing', '0000-00-00', '0000-00-00', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('脏日期不触发 overdue/longTask', !in_array('overdue', $r['hits'], true) && !in_array('longTask', $r['hits'], true));

/* R2 边界：最近活动恰好在 staleDays 天前(staleCutoff=2026-06-18)时不命中（实现用严格 <） */
$tasks = array(task('doing', '2026-05-01', '2026-07-01', '2026-06-18 09:00:00', '2026-06-18 09:00:00'));
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 恰好 staleDays 天前不命中', !in_array('stale', $r['hits'], true));

/* R5 边界：跨度恰好 14 天(06-01→06-15)时不命中（实现用严格 >） */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-15', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R5 恰好 14 天不命中', !in_array('longTask', $r['hits'], true));

/* R3 负向：已取消/已关闭的逾期任务不计入逾期 */
$tasks = array(
    task('cancel', '2026-06-01', '2026-06-10', '2026-06-01 09:00:00', '2026-06-01 09:00:00'),
    task('closed', '2026-06-01', '2026-06-10', '2026-06-01 09:00:00', '2026-06-01 09:00:00'),
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R3 取消/关闭的逾期任务不计逾期', $r['overdueCount'] === 0 && !in_array('overdue', $r['hits'], true));

/* R2 对称：全部任务已取消(cancel) → 无未关闭任务，不命中 */
$r = zouchaRules::evaluate(array(task('cancel', '2026-05-01', '2026-06-01', '2026-05-01 09:00:00', '2026-05-10 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R2 全取消任务不命中', !in_array('stale', $r['hits'], true));

echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILED\n";
exit($fail === 0 ? 0 : 1);
