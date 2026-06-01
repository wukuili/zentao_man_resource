<?php
declare(strict_types=1);
/**
 * The prediction view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

$rows = array();
if(!empty($predictions))
{
    foreach($predictions as $i => $row)
    {
        $rows[] = array(
            'id'             => $i + 1,
            'name'           => htmlspecialchars((string)$row['name'], ENT_QUOTES),
            'memberCount'    => $row['memberCount'],
            'remainingHours' => $row['remainingHours'],
            'velocity'       => $row['velocity'] > 0 ? $row['velocity'] : '-',
            'optimistic'     => !empty($row['optimistic'])  ? $row['optimistic']  : '<span class="text-muted">未估算</span>',
            'realistic'      => !empty($row['realistic'])   ? "<strong>{$row['realistic']}</strong>" : '<span class="text-muted">未估算</span>',
            'pessimistic'    => !empty($row['pessimistic']) ? $row['pessimistic'] : '<span class="text-muted">未估算</span>',
            'p50'            => !empty($row['p50']) ? $row['p50'] : '—',
            'p85'            => !empty($row['p85']) ? $row['p85'] : '—'
        );
    }
}

panel
(
    set::title($lang->man_resource->predictionTitle),
    div(setClass('px-3 pt-2 text-muted'), $lang->man_resource->predictionAlgo),
    div(setClass('px-3 pt-1 text-muted text-sm'), $lang->man_resource->predictionMC),
    dtable
    (
        setID('predictionList'),
        set::cols(array
        (
            array('name' => 'name',           'title' => $lang->man_resource->predictionProjectCol,    'width' => '220px', 'sortType' => true),
            array('name' => 'memberCount',    'title' => $lang->man_resource->predictionTeamCol,       'width' => '90px',  'sortType' => true),
            array('name' => 'remainingHours', 'title' => $lang->man_resource->predictionRemainCol,     'width' => '110px', 'sortType' => true),
            array('name' => 'velocity',       'title' => $lang->man_resource->predictionVelocityCol,   'width' => '110px', 'sortType' => true),
            array('name' => 'optimistic',     'title' => $lang->man_resource->predictionOptimistic,    'width' => '120px', 'html' => true),
            array('name' => 'realistic',      'title' => $lang->man_resource->predictionRealistic,     'width' => '120px', 'html' => true),
            array('name' => 'pessimistic',    'title' => $lang->man_resource->predictionPessimistic,   'width' => '120px', 'html' => true),
            array('name' => 'p50',            'title' => $lang->man_resource->predictionP50,           'width' => '110px'),
            array('name' => 'p85',            'title' => $lang->man_resource->predictionP85,           'width' => '110px')
        )),
        set::data($rows),
        set::footPager(usePager()),
        set::emptyTip($lang->man_resource->predictionEmpty)
    )
);

render();
