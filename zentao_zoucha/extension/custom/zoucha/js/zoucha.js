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

/* ── 规则标签悬停提示 ──
 * 浮层追加到 body，避免被表格 overflow:auto 裁剪；事件委托到 document，
 * 兼容 ZIN 重渲染。zcTipBound 守卫避免脚本重复执行时重复绑定。 */
(function()
{
    if(window.zcTipBound) return;
    window.zcTipBound = true;

    var pop = null;

    function getPop()
    {
        if(!pop)
        {
            pop = document.createElement('div');
            pop.className = 'zc-tip-pop';
            document.body.appendChild(pop);
        }
        return pop;
    }

    function showTip(tag)
    {
        var tip = tag.getAttribute('data-tip');
        if(!tip) return;
        var p     = getPop();
        var lines = tip.split('\n');
        var title = lines.shift();
        p.innerHTML = '';
        var t = document.createElement('span');
        t.className   = 'zc-tip-title';
        t.textContent = title;
        p.appendChild(t);
        p.appendChild(document.createTextNode(lines.join('\n')));
        p.classList.remove('below');

        /* 先显示以测量尺寸 */
        p.style.left = '-9999px';
        p.style.top  = '0';
        p.classList.add('show');

        var r  = tag.getBoundingClientRect();
        var pw = p.offsetWidth, ph = p.offsetHeight;
        var sx = window.pageXOffset, sy = window.pageYOffset;

        var left = r.left + sx;
        if(left + pw > sx + document.documentElement.clientWidth - 8)
            left = sx + document.documentElement.clientWidth - pw - 8;
        if(left < sx + 8) left = sx + 8;

        var top   = r.top + sy - ph - 8;            // 默认置于标签上方
        var below = false;
        if(r.top - ph - 8 < 0) { top = r.bottom + sy + 8; below = true; }   // 上方放不下则置于下方
        p.classList.toggle('below', below);

        /* 箭头对准标签中心 */
        var arrowX = Math.max(8, Math.min(pw - 14, (r.left + sx) - left + r.width / 2 - 6));
        p.style.setProperty('--arrow-x', arrowX + 'px');

        p.style.left = left + 'px';
        p.style.top  = top + 'px';
    }

    function hideTip()
    {
        if(pop) pop.classList.remove('show');
    }

    document.addEventListener('mouseover', function(e){
        var tag = e.target.closest && e.target.closest('.zc-tag[data-tip]');
        if(tag) showTip(tag);
    });
    document.addEventListener('mouseout', function(e){
        var tag = e.target.closest && e.target.closest('.zc-tag[data-tip]');
        if(tag) hideTip();
    });
    window.addEventListener('scroll', hideTip, true);
})();
