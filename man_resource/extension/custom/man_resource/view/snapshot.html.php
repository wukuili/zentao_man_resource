<?php
declare(strict_types=1);
/**
 * The snapshot view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

panel
(
    set::title($lang->man_resource->snapshotTitle),
    div(setClass('px-3 pt-2 text-muted'), $lang->man_resource->snapshotTip),
    form
    (
        set::method('post'),
        set::action(createLink('man_resource', 'snapshot')),
        formRow(formGroup(set::label($lang->man_resource->beginTime), set::control('datePicker'), set::name('begin'), set::value($begin))),
        formRow(formGroup(set::label($lang->man_resource->endTime),   set::control('datePicker'), set::name('end'),   set::value($end))),
        formRow(div(setClass('text-center'),
            input(set::type('submit'), set::className('btn btn-primary'), set::value($lang->man_resource->snapshotRun))
        ))
    )
);

render();
