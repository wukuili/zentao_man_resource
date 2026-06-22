<?php
if(!isset($lang->man_resource)) $lang->man_resource = new stdclass();
$lang->man_resource->common = '人力资源日历';

/* Sub-menus */
$lang->man_resource->menu = new stdclass();
$lang->man_resource->menu->orgdimension     = array('link' => '组织维度|man_resource|orgdimension');
$lang->man_resource->menu->projectdimension = array('link' => '项目维度|man_resource|projectdimension');
$lang->man_resource->menu->memberdimension  = array('link' => '个人维度|man_resource|memberdimension');
$lang->man_resource->menu->simulate         = array('link' => '负载模拟|man_resource|simulate');
$lang->man_resource->menu->prediction       = array('link' => '工期预测|man_resource|prediction');
$lang->man_resource->menu->snapshot         = array('link' => '资源快照|man_resource|snapshot');

/* 权限资源定义 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->man_resource = new stdclass();
$lang->resource->man_resource->browse           = 'browse';
$lang->resource->man_resource->orgdimension     = 'orgdimension';
$lang->resource->man_resource->projectdimension = 'projectdimension';
$lang->resource->man_resource->memberdimension  = 'memberdimension';
$lang->resource->man_resource->simulate         = 'simulate';
$lang->resource->man_resource->simulateTasks    = 'simulateTasks';
$lang->resource->man_resource->prediction       = 'prediction';
$lang->resource->man_resource->snapshot         = 'snapshot';
$lang->resource->man_resource->setHours         = 'setHours';
$lang->resource->man_resource->setLoad          = 'setLoad';
$lang->resource->man_resource->setPredictHours  = 'setPredictHours';
$lang->resource->man_resource->exportCompany    = 'exportCompany';
$lang->resource->man_resource->exportPerson     = 'exportPerson';
$lang->resource->man_resource->exportProject    = 'exportProject';
$lang->resource->man_resource->editSimulate     = 'editSimulate';     // 编辑模拟负载（组织/项目通用）
$lang->resource->man_resource->editProjectSim   = 'editProjectSim';   // 编辑项目模拟
$lang->resource->man_resource->viewMemberDetail = 'viewMemberDetail'; // 查看成员详情
$lang->resource->man_resource->apiCalendar      = 'apiCalendar';      // REST: 资源日历
$lang->resource->man_resource->apiUserDetail    = 'apiUserDetail';    // REST: 个人资源详情
$lang->resource->man_resource->apiSimulation    = 'apiSimulation';    // REST: 负载模拟

/* Field. */
$lang->man_resource->dept                   = '部门';
$lang->man_resource->departmentCol          = '部门';
$lang->man_resource->roleCol                = '职位';
$lang->man_resource->user                   = '用户';
$lang->man_resource->role                   = '职位';
$lang->man_resource->projectTeam            = '项目团队';
$lang->man_resource->date                   = '日期';
$lang->man_resource->today                  = '今天';
$lang->man_resource->to                     = '至';
$lang->man_resource->search                 = '查询';
$lang->man_resource->consumeHoursPerDay     = '消耗工时';
$lang->man_resource->loadRate               = '负载率';
$lang->man_resource->loadRateCol            = '负载率(%)';
$lang->man_resource->totalLoadRate          = '总负载率';
$lang->man_resource->leave                  = '请假';
$lang->man_resource->waitItem               = '待处理条目';
$lang->man_resource->doneItem               = '记日志条目';
$lang->man_resource->waitCountCol           = '待处理条目数';
$lang->man_resource->doneCountCol           = '记日志条目数';
$lang->man_resource->totalConsumeHoursCol   = '消耗总工时(h)';
$lang->man_resource->totalEstimatedHours    = '未完成工作量';
$lang->man_resource->totalEstimatedHoursCol = '未完成工作量(h)';
$lang->man_resource->consumeHoursCol        = '消耗工时(h)';
$lang->man_resource->estimatedHoursCol      = '预计剩余(h)';
$lang->man_resource->estimateHoursPerDay    = '每日平均预计工时';
$lang->man_resource->status                 = '状态';
$lang->man_resource->projectCol             = '项目';
$lang->man_resource->executionCol            = '迭代';
$lang->man_resource->deadlineCol            = '截止时间';
$lang->man_resource->finishedDateCol        = '完成时间';
$lang->man_resource->company                = '组织资源日历';
$lang->man_resource->exportCompany          = '导出组织资源日历';
$lang->man_resource->exportPerson           = '导出个人资源日历';
$lang->man_resource->exportProject          = '导出项目资源日历';
$lang->man_resource->exporting              = '导出';
$lang->man_resource->exportFail             = '导出失败';
$lang->man_resource->person                 = '个人资源日历';
$lang->man_resource->showAll                = '查看所有人资源日历';
$lang->man_resource->setting                = '资源日历设置';
$lang->man_resource->wait                   = '待处理';
$lang->man_resource->done                   = '已处理';
$lang->man_resource->showHoliday            = '包含节假日';
$lang->man_resource->holidayLabel           = '休息';
$lang->man_resource->viewType               = '查看方式';
$lang->man_resource->setHours               = '设置工作时间';
$lang->man_resource->setHoliday             = '设置节假日';
$lang->man_resource->setLoad                = '设置负载区间';
$lang->man_resource->setLoadAB              = '负载区间设置';
$lang->man_resource->setPredictHours        = '设置每项预测工时';
$lang->man_resource->future                 = '待定';
$lang->man_resource->longTime               = '长期';
$lang->man_resource->monthRange             = '{0} ~ {1}';
$lang->man_resource->countUnit              = '{0}次';
$lang->man_resource->dayCount               = '{0}天';
$lang->man_resource->fullDate               = 'yyyy年MM月dd日';
$lang->man_resource->yearDate               = 'yyyy年';
$lang->man_resource->monthDate              = 'MM月dd日';
$lang->man_resource->waitSummary            = '待处理条目数总计 <strong>%s</strong> 条，任务数总计 <strong>%s</strong> 项，未完成工作量 <strong>%s</strong> h。';
$lang->man_resource->doneSummary            = '记日志条目数总计 <strong>%s</strong> 条，任务数总计 <strong>%s</strong> 项，消耗总工时 <strong>%s</strong> h。';
$lang->man_resource->simulatedLoad          = '负载模拟';
$lang->man_resource->simulatedExit          = '退出模拟';
$lang->man_resource->simulateProject        = '项目';
$lang->man_resource->simulateAdjust         = '任务调整 (JSON)';
$lang->man_resource->simulateAdjustTip      = '可选。粘贴 JSON 数组，例如 [{"task_id":12,"est_started":"2026-06-01","deadline":"2026-06-10","estimate":24}]';
$lang->man_resource->simulateRun            = '运行模拟';
$lang->man_resource->simulateVerdictGreen   = '负载合理';
$lang->man_resource->simulateVerdictYellow  = '轻度超载';
$lang->man_resource->simulateVerdictRed     = '严重超载';
$lang->man_resource->simulateAdjustedTasks  = '调整任务数';
$lang->man_resource->simulateMaxLoad        = '峰值负载率';
$lang->man_resource->simulateNoResult       = '尚未运行模拟。';
$lang->man_resource->simulateLoadTasks      = '加载任务';
$lang->man_resource->simulatePickProject    = '请先选择项目';
$lang->man_resource->simulateAdjustHint     = '加载后可调整每个任务的开始/截止/工时，提交时只发送有变化的行。';
$lang->man_resource->simulateTaskCol        = '任务';
$lang->man_resource->simulateAssigneeCol    = '负责人';
$lang->man_resource->simulateEstStartedCol  = '预计开始';
$lang->man_resource->simulateDeadlineCol    = '截止日期';
$lang->man_resource->simulateEstimateCol    = '预计工时(h)';
$lang->man_resource->snapshotTitle          = '资源日历快照';
$lang->man_resource->snapshotTip            = '把当前组织资源日历冻结为历史快照，便于趋势分析。';
$lang->man_resource->snapshotRange          = '快照区间';
$lang->man_resource->snapshotRun            = '生成快照';
$lang->man_resource->snapshotDone           = '快照完成，共写入 {0} 条记录。';
$lang->man_resource->taskHourPredict        = '任务类工时系统预测';
$lang->man_resource->notTaskHourPredict     = '非任务类待办项工时预测';
$lang->man_resource->predictHoursTitle      = '每项预测工时';
$lang->man_resource->totalAvailableHours    = '总可用工时';
$lang->man_resource->day                    = '天';
$lang->man_resource->totalWorkDays          = '总工作日';
$lang->man_resource->defaultWorkhours       = '每天可用工时';
$lang->man_resource->batchAdjust            = '整体调整';
$lang->man_resource->dynamicType            = '动态类别';
$lang->man_resource->actionTimes            = '操作次数';
$lang->man_resource->clear                  = '清空';
$lang->man_resource->workHours              = '人工预测工时';
$lang->man_resource->predictHours           = '系统预测工时';
$lang->man_resource->totalHours             = '预计剩余工时总计';
$lang->man_resource->workHoursPerDay        = '平均未完成工作量';
$lang->man_resource->simulateConfirmTip     = '当前拖动包含 {0} 天节假日，期望加班';
$lang->man_resource->makeupOverTimeTip      = '此数值超出节假日天数';
$lang->man_resource->increaseWorkDays       = '增加{0}个工作日';
$lang->man_resource->decreaseWorkDays       = '减少{0}个工作日';
$lang->man_resource->deleted                = '已删除';
$lang->man_resource->projectCalendar        = '项目资源日历';
$lang->man_resource->createTask             = '创建任务';
$lang->man_resource->browseTitle            = '资源日历总览';
$lang->man_resource->browseTip              = '请选择维度查看资源日历。';
$lang->man_resource->simulateName           = '模拟名称';
$lang->man_resource->beginTime              = '开始时间';
$lang->man_resource->endTime                = '结束时间';
$lang->man_resource->predictionTitle        = '智能工期预测';
$lang->man_resource->predictionUnderDev     = '功能建设中，敬请期待...';
$lang->man_resource->predictionTip          = '该功能基于资源负载日历的大数据，利用高级估算和预测算法（如蒙特卡洛模拟和关键路径分析），帮助项目经理更精确地预测未来的项目工期和排期风险。';
$lang->man_resource->predictionAlgo         = '基于近 10 个工作日的团队消耗速率推算完工日期，乐观/悲观各 20% 偏差。';
$lang->man_resource->predictionEmpty        = '暂无进行中的项目可供预测。';
$lang->man_resource->predictionProjectCol   = '项目';
$lang->man_resource->predictionTeamCol      = '团队规模';
$lang->man_resource->predictionRemainCol    = '剩余工时';
$lang->man_resource->predictionVelocityCol  = '速率(h/天)';
$lang->man_resource->predictionOptimistic   = '乐观完工';
$lang->man_resource->predictionRealistic    = '实际预测';
$lang->man_resource->predictionPessimistic  = '悲观完工';
$lang->man_resource->predictionP50          = 'P50 完工';
$lang->man_resource->predictionP85          = 'P85 完工';
$lang->man_resource->predictionMC           = 'Monte Carlo 模拟 (500 次): P50=有 50% 概率早于此日期完工，P85=有 85% 概率早于此日期完工';

/* Member task list. */
$lang->man_resource->memberTaskList   = '相关任务';
$lang->man_resource->memberWorkItemEmpty = '该区间内没有相关事项。';
$lang->man_resource->itemTypeCol      = '类型';
$lang->man_resource->itemNameCol      = '事项名称';
$lang->man_resource->taskNameCol      = '任务名称';
$lang->man_resource->projectNameCol   = '所属项目';
$lang->man_resource->executionNameCol = '所属执行';
$lang->man_resource->taskStatusCol    = '当前阶段';
$lang->man_resource->deadlineCol      = '截止日期';
$lang->man_resource->memberTaskEmpty  = '该区间内没有任务。';

/* Project conflicts. */
$lang->man_resource->conflictTitle    = '资源冲突告警';
$lang->man_resource->conflictTip      = '按工作日均摊任务预计工时，超过标准工时即视为冲突。';
$lang->man_resource->conflictDateCol  = '日期';
$lang->man_resource->conflictHoursCol = '当日预估工时';
$lang->man_resource->conflictTaskCol  = '并行任务数';
$lang->man_resource->conflictRatioCol = '负载率';
$lang->man_resource->conflictLevelCol = '等级';
$lang->man_resource->conflictEmpty    = '当前条件下未发现资源冲突。';

/* Daily load chart. */
$lang->man_resource->loadTrendTitle   = '负载率趋势';
$lang->man_resource->loadTrendY       = '负载率(%)';
$lang->man_resource->loadTrendOverall = '平均负载率';
$lang->man_resource->loadTrendEmpty   = '当前条件下没有可用于绘图的数据。';
$lang->man_resource->heatmapTitle     = '工时热力图';
$lang->man_resource->heatmapEmpty     = '当前条件下没有可用于绘图的数据。';
$lang->man_resource->heatmapHourUnit  = '小时';
$lang->man_resource->ganttTitle       = '资源甘特图';
$lang->man_resource->ganttEmpty       = '当前条件下没有可用于绘制甘特的任务。';
$lang->man_resource->ganttTaskCol     = '任务';
$lang->man_resource->ganttMemberCol   = '成员';

if(!isset($lang->man_resource->loadType)) $lang->man_resource->loadType = array();
$lang->man_resource->loadType['relax']   = '轻松';
$lang->man_resource->loadType['spare']   = '有余力';
$lang->man_resource->loadType['normal']  = '正常';
$lang->man_resource->loadType['full']    = '满载';
$lang->man_resource->loadType['over']    = '超载';
$lang->man_resource->loadType['extreme'] = '过载';

if(!isset($lang->man_resource->searchType)) $lang->man_resource->searchType = array();
$lang->man_resource->searchType['day']   = '按天查看';
$lang->man_resource->searchType['week']  = '按周查看';
$lang->man_resource->searchType['month'] = '按月查看';

$lang->man_resource->setHoursList[1] = '开启';
$lang->man_resource->setHoursList[0] = '关闭';

$lang->man_resource->taskCountCol      = '并行任务数';
$lang->man_resource->bugCountCol       = 'Bug数';
$lang->man_resource->bugFixDaysCol     = '修复时长(天)';
$lang->man_resource->bugReopenCountCol = 'Reopen次数';
$lang->man_resource->invalidLoadRange = '负载区间数值设置不合理（必须满足：轻松 < 有余力 < 正常 < 满载 < 超载）';

$lang->man_resource->openSetpredicthoursTip = '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';
$lang->man_resource->bizSetpredicthoursTip  = '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';
$lang->man_resource->maxSetpredicthoursTip  = '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';
$lang->man_resource->ipdSetpredicthoursTip  = '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';

if(!isset($lang->custom)) $lang->custom = new stdClass();
if(!isset($lang->custom->workingHours)) $lang->custom->workingHours = '工作时间';
if(!isset($lang->custom->setWeekend))    $lang->custom->setWeekend = '设置周末';
if(!isset($lang->custom->weekendList))  $lang->custom->weekendList = array(
    '2' => '双休',
    '1' => '单休',
);

$lang->man_resource->apiTokenInvalid = '无效的 API 令牌。';
$lang->man_resource->apiAuthRequired = '该接口需要登录或 X-Resource-Token 头。';
$lang->man_resource->apiTokenHint    = '在插件 config.php 中配置 $config->man_resource->apiTokens 即可启用 token 鉴权。';
