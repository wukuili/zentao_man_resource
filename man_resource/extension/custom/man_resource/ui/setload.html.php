<?php
declare(strict_types=1);
/**
 * The setLoad view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

$thresholdTypes = array('relax', 'spare', 'normal', 'full', 'over');

$rows = array();
foreach($thresholdTypes as $type)
{
    $loadName = zget($lang->man_resource->loadType, $type, $type);
    $rows[] = formRow
    (
        formGroup
        (
            set::name($type),
            set::label($loadName . ' (%)'),
            set::value((string)zget($loadRangeList, $type, '')),
            set::required(true),
            set::width('1/2')
        )
    );
}

$chainLabels = array();
foreach($lang->man_resource->loadType as $name) $chainLabels[] = $name;
$chainTip = implode(' < ', $chainLabels);

$rows[] = formRow
(
    formGroup
    (
        set::label(false),
        set::tip($chainTip)
    )
);

formPanel
(
    set::title($lang->man_resource->setLoad),
    ...$rows
);
