<?php
require __DIR__ . '/../extension/custom/zhoubao/lib/zhoubaoRules.php';

$fail = 0;
function check($cond, $msg){ global $fail; if($cond){ echo "PASS: $msg\n"; } else { echo "FAIL: $msg\n"; $fail++; } }

$weekStart = '2026-06-29'; // 周一
$weekEnd   = '2026-07-05'; // 周日
$today     = '2026-07-01';

$tasks = array(
    array('id'=>1,'name'=>'A','assignedTo'=>'zhang','status'=>'done',  'deadline'=>'2026-07-02','finishedDate'=>'2026-06-30 10:00:00','consumed'=>8,'left'=>0),
    array('id'=>2,'name'=>'B','assignedTo'=>'li',   'status'=>'doing', 'deadline'=>'2026-07-03','finishedDate'=>'0000-00-00 00:00:00','consumed'=>2,'left'=>6),
    array('id'=>3,'name'=>'C','assignedTo'=>'wang', 'status'=>'doing', 'deadline'=>'2026-06-25','finishedDate'=>'0000-00-00 00:00:00','consumed'=>1,'left'=>4),
    array('id'=>4,'name'=>'D','assignedTo'=>'zhao', 'status'=>'closed','deadline'=>'2026-05-01','finishedDate'=>'2026-05-01 09:00:00','consumed'=>3,'left'=>0),
    array('id'=>5,'name'=>'E','assignedTo'=>'sun',  'status'=>'cancel','deadline'=>'2026-06-20','finishedDate'=>'0000-00-00 00:00:00','consumed'=>0,'left'=>0),
    array('id'=>6,'name'=>'F','assignedTo'=>'qian', 'status'=>'wait',  'deadline'=>'0000-00-00','finishedDate'=>'0000-00-00 00:00:00','consumed'=>0,'left'=>5),
);

$r = zhoubaoRules::classifyTasks($tasks, $weekStart, $weekEnd, $today);
$ids = function($list){ return array_map(function($t){ return $t['id']; }, $list); };

check($ids($r['done'])    == array(1),  '本周完成含任务1（本周内 finishedDate）');
check(!in_array(4, $ids($r['done'])),   '任务4 完成于本周之外不计入 done');
check($ids($r['undone'])  == array(2),  '本周应完成未完成含任务2');
check($ids($r['overdue']) == array(3),  '逾期含任务3（deadline<today）');
check(!in_array(5, $ids($r['overdue'])),'已取消任务不计逾期');
check(!in_array(6, array_merge($ids($r['undone']),$ids($r['overdue']))),'脏/空 deadline 不计 undone/overdue');
$over3 = null; foreach($r['overdue'] as $t){ if($t['id']==3) $over3=$t; }
check($over3 && $over3['daysOverdue'] === 6, '任务3 逾期天数=6');

$rows = array(
    array('project'=>'AA','pm'=>'张三','status'=>'submitted','doneCount'=>8,'overdueCount'=>1),
    array('project'=>'BB','pm'=>'李四','status'=>'none',     'doneCount'=>0,'overdueCount'=>0),
    array('project'=>'CC','pm'=>'王五','status'=>'draft',    'doneCount'=>2,'overdueCount'=>0),
);
$md = zhoubaoRules::buildWecomMarkdown($rows, 2026, 27);
check(strpos($md, '第27周') !== false,       '含周次标题');
check(strpos($md, '已交 1') !== false,         '已交计数=1（仅 submitted）');
check(strpos($md, '未交 2') !== false,         '未交计数=2（none+draft）');
check(strpos($md, 'BB') !== false && strpos($md, '李四') !== false, '未交名单含 BB/李四');
check(strpos($md, 'AA') !== false,             '已交摘要含 AA');

echo $fail === 0 ? "\nALL PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
