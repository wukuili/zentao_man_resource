/* 项目走查 — 筛选提交/重置。
 * 用 createLink 生成带位置参数的 ZenTao URL 模板，JS 替换 __RULE__ 占位符，
 * 避免 PATH_INFO 模式下 $_GET 不可靠导致的筛选失效。 */
function zcSubmitFilter()
{
    var rule = document.getElementById('zcFilterRule').value || 'all';
    var url = (typeof window.zouchaFilterURL !== 'undefined') ? window.zouchaFilterURL : '';
    if(!url) { location.reload(); return; }
    location.href = url.replace('__RULE__', encodeURIComponent(rule));
}

function zcResetFilter()
{
    var sel = document.getElementById('zcFilterRule');
    if(sel) sel.value = 'all';
    zcSubmitFilter();
}
