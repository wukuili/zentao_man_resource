/* 项目走查 — 筛选提交/重置。用 GET 跳转，避免生 XHR POST 触发禅道应用壳 302。 */
function zcSubmitFilter()
{
    var rule = document.getElementById('zcFilterRule').value || 'all';
    var base = (typeof window.zouchaBrowseURL !== 'undefined') ? window.zouchaBrowseURL : '';
    if(!base) { location.reload(); return; }
    var sep = base.indexOf('?') === -1 ? '?' : '&';
    /* PATH_INFO 模式下 createLink 已生成不含参数的基址，这里统一用 query 串传递 */
    location.href = base + sep + 'rule=' + encodeURIComponent(rule) + '&pageID=1';
}

function zcResetFilter()
{
    var sel = document.getElementById('zcFilterRule');
    if(sel) sel.value = 'all';
    zcSubmitFilter();
}
