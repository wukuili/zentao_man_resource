<?php
declare(strict_types=1);
/**
 * The memberdimension view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

jsVar('options', $calendarData);
jsVar('today', $today);
jsVar('mode', $status);
jsVar('begin', strtotime($begin));
jsVar('end', strtotime($end));
jsVar('method', $app->rawMethod);
jsVar('userAccount', $userID);
jsVar('title', $title);
jsVar('workHours', $defaultWorkhours);
jsVar('module', 'man_resource');

css
(<<<CSS
.load-rate-bar { width: 100%; background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px; }
.load-rate-fill { height: 100%; border-radius: 4px; }
.search-form-row { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 12px; }
.search-form-row .form-group { display: flex; align-items: center; gap: 8px; }
.search-form-row .form-group label { white-space: nowrap; font-weight: 600; }
.search-form-row .picker-box { width: 220px; }
.search-form-row .date-box { width: 140px; }
.search-form-row .status-box { width: 100px; }
CSS
);

/* Nav tabs for dimension switching. */
$navItems = array();
if(common::hasPriv('man_resource', 'orgdimension'))    $navItems[] = array('text' => $lang->man_resource->company,          'url' => createLink('man_resource', 'orgdimension'),    'active' => false);
if(common::hasPriv('man_resource', 'projectdimension')) $navItems[] = array('text' => $lang->man_resource->projectCalendar, 'url' => createLink('man_resource', 'projectdimension'), 'active' => false);
if(common::hasPriv('man_resource', 'memberdimension'))  $navItems[] = array('text' => $lang->man_resource->person,           'url' => createLink('man_resource', 'memberdimension'),  'active' => true);

featureBar
(
    set::current($status),
    set::linkParams("userID={$userID}&status={key}&begin=" . str_replace('-', '_', $begin) . "&end=" . str_replace('-', '_', $end) . "&showHoliday={$showHoliday}&execution={$execution}&projectID={$projectID}"),
    to::item($navItems)
);

/* Toolbar. */
$toolbarItems = array();

if(common::hasPriv('man_resource', 'exportPerson'))
{
    $toolbarItems[] = btn
    (
        set::type('ghost'),
        set::icon('export'),
        set::text($lang->export),
        set::url(helper::createLink('man_resource', 'exportPerson', "begin=" . strtotime($begin) . "&end=" . strtotime($end) . "&mode={$status}&userID={$userID}"))
    );
}

/* Settings dropdown. */
$settingsItems = array();
if(common::hasPriv('man_resource', 'setHours'))       $settingsItems[] = array('text' => $lang->man_resource->setHours, 'url' => helper::createLink('man_resource', 'setHours', '', '', true), 'data-toggle' => 'modal', 'data-width' => '500px');
if(common::hasPriv('holiday', 'browse'))               $settingsItems[] = array('text' => $lang->man_resource->setHoliday, 'url' => helper::createLink('holiday', 'browse'));
if(common::hasPriv('man_resource', 'setLoad'))          $settingsItems[] = array('text' => $lang->man_resource->setLoad, 'url' => helper::createLink('man_resource', 'setLoad', '', '', true), 'data-toggle' => 'modal', 'data-width' => '600px');
if(common::hasPriv('man_resource', 'setPredictHours')) $settingsItems[] = array('text' => $lang->man_resource->setPredictHours, 'url' => helper::createLink('man_resource', 'setPredictHours', '', '', true), 'data-toggle' => 'modal', 'data-width' => common::checkNotCN() ? '900px' : '820px');

if(!empty($settingsItems))
{
    $toolbarItems[] = dropdown
    (
        btn(set::type('ghost'), set::icon('cog-outline'), set::square(true)),
        set::items($settingsItems),
        set::placement('bottom-end')
    );
}

toolbar($toolbarItems);

/* Search form. */
panel
(
    setClass('mb-4'),
    form
    (
        set::method('post'),
        set::action(createLink('man_resource', 'memberdimension')),
        div
        (
            setClass('search-form-row'),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->user),
                div(setClass('picker-box'), picker(set::name('userID'), set::items($userList), set::value($userID)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->projectCol),
                div(setClass('picker-box'), picker(set::name('projectID'), set::items($projectList), set::value($projectID), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->executionCol),
                div(setClass('picker-box'), picker(set::name('execution'), set::items($executionList), set::value($execution), set::placeholder($lang->all)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->date),
                div(setClass('date-box'), datePicker(set::name('begin'), set::value($begin))),
                span($lang->man_resource->to),
                div(setClass('date-box'), datePicker(set::name('end'), set::value($end)))
            ),
            div
            (
                setClass('form-group'),
                h::label($lang->man_resource->status),
                div(setClass('status-box'), select(set::name('status'), set::items(array('todo' => $lang->man_resource->wait, 'done' => $lang->man_resource->done)), set::value($status)))
            ),
            div
            (
                setClass('form-group'),
                checkbox(set::name('showHoliday'), set::value('1'), set::checked(!empty($showHoliday)), set::text($lang->man_resource->showHoliday))
            ),
            input(set::type('submit'), set::className('btn btn-primary'), set::value($lang->man_resource->search))
        )
    )
);

/* Build detail data for single user. */
$loadStatus = $calendarData['load_status'];
$colorObj   = zget($config->man_resource->loadRangeColors, $loadStatus, new stdClass());
$textColor  = isset($colorObj->text) ? $colorObj->text : '#000';
$loadName   = zget($lang->man_resource->loadType, $loadStatus, $loadStatus);
$loadRate   = min(100, $calendarData['load_rate']);

panel
(
    set::title(zget($userList, $userID) . ' - ' . $lang->man_resource->person),
    setClass('mt-4'),
    dtable
    (
        setID('memberList'),
        set::cols(array
        (
            array('name' => 'label', 'title' => '', 'width' => '200px'),
            array('name' => 'value', 'title' => '', 'width' => 'auto', 'html' => true)
        )),
        set::data(array
        (
            array('id' => 1, 'label' => $lang->man_resource->estimatedHoursCol, 'value' => $calendarData['estimated_hours']),
            array('id' => 2, 'label' => $lang->man_resource->consumeHoursCol, 'value' => $calendarData['consumed_hours']),
            array('id' => 3, 'label' => $lang->man_resource->totalEstimatedHoursCol, 'value' => $calendarData['remain_hours']),
            array('id' => 4, 'label' => $lang->man_resource->taskCountCol, 'value' => $calendarData['parallel_tasks']),
            array('id' => 5, 'label' => $lang->man_resource->loadRateCol, 'value' => "<span style='color:{$textColor}'>{$calendarData['load_rate']}%</span><div class='load-rate-bar'><div class='load-rate-fill' style='width:{$loadRate}%;background:{$textColor}'></div></div>"),
            array('id' => 6, 'label' => $lang->man_resource->status, 'value' => $loadName),
            array('id' => 7, 'label' => $lang->man_resource->bugCountCol,       'value' => isset($calendarData['bug_count'])        ? $calendarData['bug_count']        : 0),
            array('id' => 8, 'label' => $lang->man_resource->bugFixDaysCol,     'value' => isset($calendarData['bug_fix_days'])     ? $calendarData['bug_fix_days']     : 0),
            array('id' => 9, 'label' => $lang->man_resource->bugReopenCountCol, 'value' => isset($calendarData['bug_reopen_count']) ? $calendarData['bug_reopen_count'] : 0),
        )),
        set::emptyTip($lang->man_resource->browseTip)
    )
);

/* Tasks panel. */
$taskRows = array();
if(!empty($taskList))
{
    global $app;
    $statusList = isset($app->lang->task->statusList) ? $app->lang->task->statusList : array();
    foreach($taskList as $task)
    {
        $taskRows[] = array(
            'id'            => (int)$task->id,
            'name'          => common::hasPriv('man_resource', 'viewMemberDetail')
                ? html::a(helper::createLink('task', 'view', "taskID={$task->id}"), htmlspecialchars((string)$task->name))
                : htmlspecialchars((string)$task->name),
            'projectName'   => $task->projectName,
            'executionName' => $task->executionName,
            'status'        => zget($statusList, $task->status, $task->status),
            'left'          => $task->left,
            'consumed'      => $task->consumed,
            'deadline'      => $status == 'done' ? substr((string)$task->finishedDate, 0, 10) : (($task->deadline && $task->deadline != '0000-00-00') ? $task->deadline : '')
        );
    }
}

panel
(
    set::title($lang->man_resource->memberTaskList),
    setClass('mt-4'),
    dtable
    (
        setID('memberTaskList'),
        set::cols(array
        (
            array('name' => 'name',          'title' => $lang->man_resource->taskNameCol,      'width' => '260px', 'html' => true),
            array('name' => 'projectName',   'title' => $lang->man_resource->projectNameCol,   'width' => '160px'),
            array('name' => 'executionName', 'title' => $lang->man_resource->executionNameCol, 'width' => '160px'),
            array('name' => 'status',        'title' => $lang->man_resource->taskStatusCol,    'width' => '90px'),
            array('name' => 'consumed',      'title' => $lang->man_resource->consumeHoursCol,  'width' => '100px', 'sortType' => true),
            array('name' => 'left',          'title' => $lang->man_resource->totalEstimatedHoursCol, 'width' => '110px', 'sortType' => true),
            array('name' => 'deadline',      'title' => $status == 'done' ? $lang->man_resource->finishedDateCol : $lang->man_resource->deadlineCol, 'width' => '120px', 'sortType' => true)
        )),
        set::data($taskRows),
        set::footPager(usePager()),
        set::emptyTip($lang->man_resource->memberTaskEmpty)
    )
);

/* Work item panel. */
$itemRows = array();
if(!empty($workItemList))
{
    foreach($workItemList as $item)
    {
        $itemRows[] = array(
            'id'        => $item['type'] . '-' . $item['id'],
            'typeName'  => $item['typeName'],
            'name'      => !empty($item['url']) ? html::a($item['url'], htmlspecialchars((string)$item['name'])) : htmlspecialchars((string)$item['name']),
            'status'    => $item['status'],
            'project'   => $item['project'],
            'execution' => $item['execution'],
            'date'      => $item['date'],
            'hours'     => $item['hours']
        );
    }
}

panel
(
    set::title($status == 'done' ? $lang->man_resource->doneItem : $lang->man_resource->waitItem),
    setClass('mt-4'),
    dtable
    (
        setID('memberWorkItemList'),
        set::cols(array
        (
            array('name' => 'typeName',  'title' => $lang->man_resource->itemTypeCol,      'width' => '90px'),
            array('name' => 'name',      'title' => $lang->man_resource->itemNameCol,      'width' => '260px', 'html' => true),
            array('name' => 'status',    'title' => $lang->man_resource->taskStatusCol,    'width' => '90px'),
            array('name' => 'project',   'title' => $lang->man_resource->projectNameCol,   'width' => '160px'),
            array('name' => 'execution', 'title' => $lang->man_resource->executionNameCol, 'width' => '160px'),
            array('name' => 'date',      'title' => $lang->man_resource->deadlineCol,      'width' => '120px', 'sortType' => true),
            array('name' => 'hours',     'title' => $status == 'done' ? $lang->man_resource->consumeHoursCol : $lang->man_resource->estimatedHoursCol, 'width' => '120px', 'sortType' => true)
        )),
        set::data($itemRows),
        set::footPager(usePager()),
        set::emptyTip($lang->man_resource->memberWorkItemEmpty)
    )
);

render();
