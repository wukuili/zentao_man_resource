<?php
declare(strict_types=1);
/**
 * The browse view file of crmsync module of ZenTaoPMS.
 */
namespace zin;

featureBar
(
    set::current('browse'),
    set::items(array
    (
        array('text' => $lang->crmsync->browseTitle,   'url' => createLink('crmsync', 'browse')),
        array('text' => $lang->crmsync->settingsTitle, 'url' => createLink('crmsync', 'settings'))
    ))
);

$toolbarItems = array();
if(common::hasPriv('crmsync', 'settings'))
{
    $toolbarItems[] = btn
    (
        set::type('ghost'),
        set::icon('cog-outline'),
        set::text($lang->crmsync->settingsTitle),
        set::url(helper::createLink('crmsync', 'settings'))
    );
}
toolbar($toolbarItems);

$statusColors = array('success' => '#10b981', 'failed' => '#ef4444');

$tableData = array();
foreach($records as $record)
{
    $statusKey  = $record->syncStatus;
    $statusName = zget($lang->crmsync->statusList, $statusKey, $statusKey);
    $statusCol  = zget($statusColors, $statusKey, '#64748b');

    $projectCell = '-';
    if($record->projectID > 0)
    {
        $projectCell = html::a(createLink('project', 'view', "projectID={$record->projectID}"), '#' . $record->projectID, '', "target='_blank'");
    }

    $productCell = $record->productId > 0 ? ('#' . $record->productId) : $lang->crmsync->pureProject;

    $actionCell = '';
    if($record->syncStatus == 'failed' && $record->projectID == 0 && common::hasPriv('crmsync', 'retry'))
    {
        $actionCell = html::a(helper::createLink('crmsync', 'retry', "id={$record->id}"), $lang->crmsync->retry, '', "class='btn btn-primary btn-sm' data-toggle='ajaxbtn' data-loading='1'");
    }

    $row = new \stdClass();
    $row->id            = $record->id;
    $row->opportunityId = htmlspecialchars($record->opportunityId);
    $row->oppName       = htmlspecialchars($record->oppName);
    $row->customerName  = htmlspecialchars($record->customerName);
    $row->project       = $projectCell;
    $row->productId     = $productCell;
    $row->syncStatus    = "<span style='color:{$statusCol};font-weight:600'>{$statusName}</span>";
    $row->errorMsg      = htmlspecialchars((string)$record->errorMsg);
    $row->createdBy     = htmlspecialchars((string)$record->createdBy);
    $row->createdDate   = $record->createdDate;
    $row->actions       = $actionCell;
    $tableData[] = $row;
}

dtable
(
    setID('crmsyncList'),
    set::cols(array
    (
        array('name' => 'id',            'title' => $lang->crmsync->id,            'width' => '60px',  'sortType' => true),
        array('name' => 'opportunityId', 'title' => $lang->crmsync->opportunityId, 'width' => '100px', 'sortType' => true),
        array('name' => 'oppName',       'title' => $lang->crmsync->oppName,       'width' => '200px'),
        array('name' => 'customerName',  'title' => $lang->crmsync->customerName,  'width' => '160px'),
        array('name' => 'project',       'title' => $lang->crmsync->project,       'width' => '90px',  'html' => true),
        array('name' => 'productId',     'title' => $lang->crmsync->productId,     'width' => '90px'),
        array('name' => 'syncStatus',    'title' => $lang->crmsync->syncStatus,    'width' => '80px',  'html' => true),
        array('name' => 'errorMsg',      'title' => $lang->crmsync->errorMsg,      'width' => '220px'),
        array('name' => 'createdBy',     'title' => $lang->crmsync->createdBy,     'width' => '90px'),
        array('name' => 'createdDate',   'title' => $lang->crmsync->createdDate,   'width' => '150px', 'sortType' => true),
        array('name' => 'actions',       'title' => $lang->crmsync->actions,       'width' => '90px',  'html' => true)
    )),
    set::data($tableData),
    set::footPager(usePager()),
    set::emptyTip($lang->crmsync->noRecord)
);

render();
