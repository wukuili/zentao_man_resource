/* 项目走查 — 筛选提交/重置。
 * 用 createLink 生成带位置参数的 ZenTao URL 模板，JS 替换 __RULE__ 占位符，
 * 避免 PATH_INFO 模式下 $_GET 不可靠导致的筛选失效。 */
function zcSubmitFilter()
{
    var rule = document.getElementById('zcFilterRule').value || 'all';
    var pmEl = document.getElementById('zcFilterPM');
    var pm   = (pmEl && pmEl.value) ? pmEl.value : 'all';
    var url = (typeof window.zouchaFilterURL !== 'undefined') ? window.zouchaFilterURL : '';
    if(!url) { location.reload(); return; }
    location.href = url.replace('__RULE__', encodeURIComponent(rule)).replace('__PM__', encodeURIComponent(pm));
}

function zcResetFilter()
{
    var ruleEl = document.getElementById('zcFilterRule');
    if(ruleEl) ruleEl.value = 'all';
    var pmEl = document.getElementById('zcFilterPM');
    if(pmEl) pmEl.value = 'all';
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

/* ── 点击规则标签 → 弹框展示明细列表 ── */
(function()
{
    if(window.zcModalBound) return;
    window.zcModalBound = true;

    function esc(s)
    {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function closeModal()
    {
        var m = document.getElementById('zcModalMask');
        if(m) m.parentNode.removeChild(m);
    }

    function openModal(innerHTML)
    {
        closeModal();
        var mask = document.createElement('div');
        mask.id = 'zcModalMask';
        mask.className = 'zc-modal-mask';
        mask.innerHTML = '<div class="zc-modal">' + innerHTML + '</div>';
        mask.addEventListener('click', function(e){ if(e.target === mask) closeModal(); });
        document.body.appendChild(mask);
        var btn = mask.querySelector('.zc-modal-close');
        if(btn) btn.addEventListener('click', closeModal);
    }

    function headHTML(title, color, countText)
    {
        var badge = color ? '<span class="zc-modal-badge" style="background:' + esc(color) + '">' + esc(title) + '</span>' : '';
        return '<div class="zc-modal-head">' + badge +
               '<h3>' + esc(title) + ' 明细</h3>' +
               '<span class="zc-modal-count">' + esc(countText || '') + '</span>' +
               '<button type="button" class="zc-modal-close" title="关闭">&times;</button></div>';
    }

    function renderTable(data)
    {
        var cols  = data.columns || {};
        var items = data.items || [];
        var keys  = Object.keys(cols);

        if(!keys.length || !items.length)
        {
            var msg = data.note || '没有可展示的明细。';
            return '<div class="zc-modal-empty">' + esc(msg) + '</div>';
        }

        var th = keys.map(function(k){ return '<th>' + esc(cols[k]) + '</th>'; }).join('');
        var rows = items.map(function(it){
            var tds = keys.map(function(k){
                var v = it[k];
                if(k === 'id')
                {
                    var text = '#' + esc(it.id) + ' ' + esc(it.name);
                    return it.url
                        ? '<td><a class="zc-task-link" href="' + esc(it.url) + '" target="_blank">' + text + '</a></td>'
                        : '<td>' + text + '</td>';
                }
                if(v == null || v === '') v = '-';
                return '<td>' + esc(v) + '</td>';
            }).join('');
            return '<tr>' + tds + '</tr>';
        }).join('');

        return '<div class="zc-modal-body"><table><thead><tr>' + th + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function loadDetail(tag)
    {
        var pid   = tag.getAttribute('data-pid');
        var rule  = tag.getAttribute('data-rule');
        var label = tag.getAttribute('data-label') || '';
        var color = tag.style.background || '';
        var tpl   = window.zouchaDetailURL || '';
        if(!pid || !rule || !tpl) return;

        var url = tpl.replace('__PID__', encodeURIComponent(pid)).replace('__RULE__', encodeURIComponent(rule));

        openModal(headHTML(label, color, '加载中…') + '<div class="zc-modal-loading">正在加载明细…</div>');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); // 禅道20：生 XHR 需此头，否则 302 跳应用壳
        xhr.onreadystatechange = function(){
            if(xhr.readyState !== 4) return;
            var data;
            try { data = JSON.parse(xhr.responseText); }
            catch(err) { openModal(headHTML(label, color, '') + '<div class="zc-modal-empty">明细加载失败，请重试。</div>'); return; }

            var countText = (data.items && data.items.length) ? ('共 ' + data.items.length + ' 条') : '';
            openModal(headHTML(label, color, countText) + renderTable(data));
        };
        xhr.send();
    }

    document.addEventListener('click', function(e){
        var tag = e.target.closest && e.target.closest('.zc-tag-clickable[data-rule]');
        if(tag) { e.preventDefault(); loadDetail(tag); }
    });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });
})();
