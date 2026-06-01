<?php
declare(strict_types=1);
/**
 * The setHours view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

formPanel
(
    set::title($lang->man_resource->setHours),
    formRow
    (
        formGroup
        (
            set::name('defaultWorkhours'),
            set::label($lang->custom->workingHours),
            set::value((string)$workhours),
            set::required(true),
            set::width('1/2')
        )
    ),
    formRow
    (
        formGroup
        (
            set::label($lang->custom->setWeekend),
            set::control('radioList'),
            set::name('weekend'),
            set::items($lang->custom->weekendList),
            set::value((string)$weekend),
            set::inline(true)
        )
    )
);
