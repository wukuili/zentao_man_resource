<?php
/**
 * 外包标识插件 — 人员标识管理页面
 *
 * 提供按部门/标识筛选、统计卡片、单行切换和批量操作。
 */
namespace zin;

$depts            = $this->view->depts;
$users            = $this->view->users;
$orderBy          = $this->view->orderBy;
$filterDept       = $this->view->filterDept;
$filterOutsourced = $this->view->filterOutsourced;

/* ── 统计与客户端筛选 ── */
$totalInternal   = 0;
$totalOutsourced = 0;
$data = array();

foreach($users as $user)
{
    $isOutsourced = (int)$user->outsourced;
    if($isOutsourced) $totalOutsourced++; else $totalInternal++;

    if($filterDept > 0 && $user->dept != $filterDept) continue;
    if($filterOutsourced !== '' && $isOutsourced != (int)$filterOutsourced) continue;

    $deptName = $user->dept > 0 ? zget($depts, $user->dept, '未分配') : '未分配';
    $data[] = array(
        'id'         => $user->id,
        'realname'   => $user->realname,
        'account'    => $user->account,
        'deptName'   => $deptName,
        'role'       => zget($lang->user->roleList, $user->role, $user->role),
        'outsourced' => $isOutsourced,
    );
}

$filteredCount = count($data);
$filteredIDs   = array_column($data, 'id');

/* PHP 端生成 URL 和数据，稍后直接拼入 <script> 块（jsVar 与 html('<script>') 是不同脚本块，有时序问题） */
$browseURL    = helper::createLink('waibao', 'browse');
$setURL       = helper::createLink('waibao', 'setUserOutsourced');
$batchURL     = helper::createLink('waibao', 'batchSetOutsourced');

/* ── 筛选栏 ── */
$deptOptions       = array(0 => '全部部门') + (array)$depts;
$outsourcedOptions = array('' => '全部', '0' => '自有人员', '1' => '外包人员');

featureBar
(
    div
    (
        setClass('flex items-center gap-3 flex-wrap p-1'),
        label('部门'),
        picker
        (
            set::name('dept'),
            set::items($deptOptions),
            set::value($filterDept)
        ),
        label('标识'),
        picker
        (
            set::name('outsourced_filter'),
            set::items($outsourcedOptions),
            set::value($filterOutsourced === '' ? '' : (string)$filterOutsourced)
        ),
        btn(set::type('primary'), set::onClick('applyFilter()'), '查询'),
        btn(set::onClick('resetFilter()'), '重置')
    )
);

/* ── 统计卡片 ── */
div
(
    setClass('flex gap-4 mb-4'),
    div
    (
        setClass('panel flex-1 text-center py-3'),
        span(setClass('text-2xl font-bold text-green-600'), $totalInternal),
        br(),
        span(setClass('text-gray-500 text-sm'), '自有人员（全部）')
    ),
    div
    (
        setClass('panel flex-1 text-center py-3'),
        span(setClass('text-2xl font-bold text-orange-500'), $totalOutsourced),
        br(),
        span(setClass('text-gray-500 text-sm'), '外包人员（全部）')
    ),
    div
    (
        setClass('panel flex-1 text-center py-3'),
        span(setClass('text-2xl font-bold text-blue-600'), $filteredCount),
        br(),
        span(setClass('text-gray-500 text-sm'), '当前筛选结果')
    )
);

/* ── 批量操作栏 ── */
div
(
    setClass('flex items-center justify-between mb-2'),
    span(setClass('text-sm text-gray-500'), "当前显示 {$filteredCount} 人，对全部筛选结果批量操作："),
    div
    (
        setClass('flex gap-2'),
        btn
        (
            setClass('btn btn-success btn-sm'),
            set::onClick('batchSet(0)'),
            '全部设为自有人员'
        ),
        btn
        (
            setClass('btn btn-warning btn-sm'),
            set::onClick('batchSet(1)'),
            '全部设为外包人员'
        )
    )
);

/* ── 列配置 ── */
$cols = array(
    'realname'   => array('title' => $lang->waibao->realname,   'width' => '120px'),
    'account'    => array('title' => $lang->waibao->account,    'width' => '120px'),
    'deptName'   => array('title' => $lang->waibao->department, 'width' => '160px'),
    'role'       => array('title' => $lang->waibao->role,       'width' => '100px'),
    'outsourced' => array(
        'title'   => $lang->waibao->outsourced,
        'width'   => '210px',
        'type'    => 'tpl',
        'tplFunc' => 'renderOutsourced',
    ),
);

panel
(
    dtable
    (
        set::cols($cols),
        set::data($data),
        set::primaryKey('id'),
        set::hover(true),
        set::striped(true)
    )
);

/* ── JavaScript：把 PHP 数据直接拼入同一 <script> 块，避免跨块时序问题 ── */
$jsHead = 'var waibaoBrowseURL='   . json_encode($browseURL)    . ';'
        . 'var waibaoSetURL='      . json_encode($setURL)       . ';'
        . 'var waibaoBatchURL='    . json_encode($batchURL)     . ';'
        . 'var filteredUserIDs='   . json_encode($filteredIDs)  . ';';

$jsBody = <<<'JS'
/* 渲染外包标识列：状态标签 + 单行切换链接 */
window.renderOutsourced = function(result, col, row)
{
    var val      = row.outsourced;
    var badgeCls = val == 1 ? 'label label-warning' : 'label label-success';
    var badge    = val == 1 ? '外包人员' : '自有人员';
    var linkText = val == 1 ? '改为自有' : '改为外包';
    return '<span class="' + badgeCls + '">' + badge + '</span>'
         + ' <a href="javascript:void(0)" class="text-link text-sm ml-2"'
         + ' onclick="toggleOne(' + row.id + ',' + val + ')">' + linkText + '</a>';
};

/* 单行切换 */
window.toggleOne = function(userID, currentVal)
{
    var newVal = currentVal == 1 ? 0 : 1;
    $.post(waibaoSetURL, {userID: userID, outsourced: newVal}, function(resp)
    {
        if(typeof resp === 'string')
        {
            try { resp = JSON.parse(resp); } catch(e) {}
        }
        if(resp && resp.result == 'success') window.location.reload();
        else alert(resp && resp.message ? resp.message : '设置失败');
    }, 'json').fail(handleRequestFailure);
};

window.handleRequestFailure = function(xhr)
{
    var resp = xhr.responseJSON || xhr.responseText;
    if(typeof resp === 'string')
    {
        try { resp = JSON.parse(resp); } catch(e) {}
    }
    if(resp && resp.result == 'success') return window.location.reload();
    alert(resp && resp.message ? resp.message : '设置请求失败');
};

/* 批量操作——作用于当前筛选结果的所有用户 */
window.batchSet = function(val)
{
    var ids   = filteredUserIDs || [];
    var label = val == 1 ? '外包人员' : '自有人员';
    if(ids.length == 0) { alert('当前筛选结果为空'); return; }
    if(!confirm('确认将当前筛选的 ' + ids.length + ' 位用户全部设为「' + label + '」？')) return;
    $.post(waibaoBatchURL, {userIDs: ids.join(','), outsourced: val}, function(resp)
    {
        if(typeof resp === 'string')
        {
            try { resp = JSON.parse(resp); } catch(e) {}
        }
        if(resp && resp.result == 'success') window.location.reload();
        else alert(resp && resp.message ? resp.message : '设置失败');
    }, 'json').fail(handleRequestFailure);
};

/* 筛选：原生 form POST，服务端 locate 重定向后浏览器跳转 */
window.applyFilter = function()
{
    var deptEl       = document.querySelector('[name="dept"]');
    var outsourcedEl = document.querySelector('[name="outsourced_filter"]');
    var dept         = deptEl       ? (deptEl.value       || '0') : '0';
    var outsourced   = outsourcedEl ? (outsourcedEl.value || '')  : '';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = waibaoBrowseURL;
    form.innerHTML = '<input type="hidden" name="dept" value="' + dept + '">'
                   + '<input type="hidden" name="outsourced" value="' + outsourced + '">';
    document.body.appendChild(form);
    form.submit();
};

window.resetFilter = function()
{
    window.location.href = waibaoBrowseURL;
};
JS;

html('<script>' . $jsHead . $jsBody . '</script>');
