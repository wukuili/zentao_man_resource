<?php
declare(strict_types=1);
/**
 * The browse view file of man_resource module of ZenTaoPMS.
 */
namespace zin;

featureBar
(
    set::current('browse'),
    set::items(array
    (
        array('text' => $lang->man_resource->company,          'url' => createLink('man_resource', 'orgdimension')),
        array('text' => $lang->man_resource->projectCalendar,   'url' => createLink('man_resource', 'projectdimension')),
        array('text' => $lang->man_resource->person,            'url' => createLink('man_resource', 'memberdimension'))
    ))
);

panel
(
    set::title($lang->man_resource->browseTitle),
    setClass('mt-4'),
    setClass('p-4'),
    div
    (
        h::p($lang->man_resource->browseTip),
        h::ul
        (
            h::li(html::a(createLink('man_resource', 'orgdimension'), $lang->man_resource->company)),
            h::li(html::a(createLink('man_resource', 'projectdimension'), $lang->man_resource->projectCalendar)),
            h::li(html::a(createLink('man_resource', 'memberdimension'), $lang->man_resource->person)),
            h::li(html::a(createLink('man_resource', 'simulate'), $lang->man_resource->simulatedLoad))
        )
    )
);

render();