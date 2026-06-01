<?php
declare(strict_types=1);
/**
 * The setPredictHours view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

$tipKey  = $this->config->edition == 'ipd' ? 'maxSetpredicthoursTip' : $this->config->edition . 'SetpredicthoursTip';
$tipText = isset($lang->man_resource->$tipKey) ? $lang->man_resource->$tipKey : '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';

$prefix   = common::checkNotCN() ? ', ' : '，';
$urCommon = isset($lang->URCommon) ? $lang->URCommon : '';
$erCommon = isset($lang->ERCommon) ? $lang->ERCommon : '';
if(!isset($config->URAndSR) || !$config->URAndSR) $tipText = str_replace($urCommon . $prefix, '', $tipText);
if(isset($lang->ERCommon) && (!isset($config->enableER) || !$config->enableER)) $tipText = str_replace($erCommon . $prefix, '', $tipText);

formPanel
(
    set::title($lang->man_resource->setPredictHours),
    formRow
    (
        formGroup
        (
            set::label($lang->man_resource->taskHourPredict),
            set::control('radioList'),
            set::name('taskHourPredict'),
            set::items($lang->man_resource->setHoursList),
            set::value((string)$taskHourPredict),
            set::inline(true)
        )
    ),
    formRow
    (
        formGroup
        (
            set::label($lang->man_resource->notTaskHourPredict),
            set::control('radioList'),
            set::name('notTaskHourPredict'),
            set::items($lang->man_resource->setHoursList),
            set::value((string)$notTaskHourPredict),
            set::inline(true)
        )
    ),
    formRow
    (
        formGroup
        (
            set::name('predictHours'),
            set::label($lang->man_resource->predictHoursTitle),
            set::value((string)$predictHours),
            set::hidden(empty($notTaskHourPredict)),
            set::disabled(empty($notTaskHourPredict)),
            set::width('1/2')
        )
    ),
    formRow
    (
        formGroup
        (
            set::label(false),
            set::tip($tipText)
        )
    )
);
