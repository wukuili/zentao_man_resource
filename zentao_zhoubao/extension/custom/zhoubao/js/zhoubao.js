/**
 * 项目周报 — 写/编辑周报页 AJAX 交互
 * 读取 jsVar() 通过 window. 前缀暴露的 zbSaveURL / zbCopyURL，
 * 原生 XHR POST 并带 X-Requested-With，避免禅道 20 对非 AJAX POST 的 302 跳转空白页。
 */
(function(){
  if(window.__zhoubaoBound) return;
  window.__zhoubaoBound = true;

  function collect(){
    var form = document.getElementById('zbEditForm');
    return {
      nextPlan: form.nextPlan.value,
      risk:     form.risk.value,
      summary:  form.summary.value
    };
  }
  function post(url, data, cb){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function(){ try{ cb(JSON.parse(xhr.responseText)); }catch(e){ cb({result:'fail',message:'返回解析失败'}); } };
    var body = Object.keys(data).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(data[k]); }).join('&');
    xhr.send(body);
  }
  window.zbSaveDraft = function(){
    post(window.zbSaveURL, collect(), function(res){
      if(res.result === 'success'){ if(res.locate) location.href = res.locate; else location.reload(); }
      else alert(res.message || '保存失败');
    });
  };
  window.zbSubmitReport = function(){
    var data = collect(); data.submit = 1;
    post(window.zbSaveURL, data, function(res){
      if(res.result === 'success'){ if(res.locate) location.href = res.locate; else location.reload(); }
      else alert(res.message || '提交失败');
    });
  };
  window.zbCopyLast = function(){
    post(window.zbCopyURL, {}, function(res){
      if(res.result === 'success' && res.data){
        var form = document.getElementById('zbEditForm');
        form.nextPlan.value = res.data.nextPlan || '';
        form.risk.value = res.data.risk || '';
        form.summary.value = res.data.summary || '';
      } else alert(res.message || '上周暂无周报');
    });
  };
})();

/* 走查提示标签 — 悬停浮层（说明规则），复用 zoucha 列表页同款交互，zb- 前缀避免与 zoucha.js 同页冲突 */
(function(){
  if(window.zbTipBound) return;
  window.zbTipBound = true;

  var pop = null;
  function getPop(){
    if(!pop){ pop = document.createElement('div'); pop.className = 'zb-tip-pop'; document.body.appendChild(pop); }
    return pop;
  }
  function showTip(tag){
    var tip = tag.getAttribute('data-tip');
    if(!tip) return;
    var p = getPop();
    var lines = tip.split('\n');
    var title = lines.shift();
    p.innerHTML = '';
    var t = document.createElement('span');
    t.className = 'zb-tip-title';
    t.textContent = title;
    p.appendChild(t);
    p.appendChild(document.createTextNode(lines.join('\n')));
    p.classList.remove('below');

    p.style.left = '-9999px';
    p.style.top = '0';
    p.classList.add('show');

    var r = tag.getBoundingClientRect();
    var pw = p.offsetWidth, ph = p.offsetHeight;
    var sx = window.pageXOffset, sy = window.pageYOffset;

    var left = r.left + sx;
    if(left + pw > sx + document.documentElement.clientWidth - 8) left = sx + document.documentElement.clientWidth - pw - 8;
    if(left < sx + 8) left = sx + 8;

    var top = r.top + sy - ph - 8;
    var below = false;
    if(r.top - ph - 8 < 0){ top = r.bottom + sy + 8; below = true; }
    p.classList.toggle('below', below);

    var arrowX = Math.max(8, Math.min(pw - 14, (r.left + sx) - left + r.width / 2 - 6));
    p.style.setProperty('--arrow-x', arrowX + 'px');

    p.style.left = left + 'px';
    p.style.top = top + 'px';
  }
  function hideTip(){ if(pop) pop.classList.remove('show'); }

  document.addEventListener('mouseover', function(e){
    var tag = e.target.closest && e.target.closest('.zb-zoucha-tag[data-tip]');
    if(tag) showTip(tag);
  });
  document.addEventListener('mouseout', function(e){
    var tag = e.target.closest && e.target.closest('.zb-zoucha-tag[data-tip]');
    if(tag) hideTip();
  });
  window.addEventListener('scroll', hideTip, true);
})();

/* 走查提示标签 — 点击弹框展示明细（复用 zoucha 模块自身的 detail 接口，走查命中数据就来自 zoucha） */
(function(){
  if(window.zbModalBound) return;
  window.zbModalBound = true;

  function esc(s){
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function closeModal(){
    var m = document.getElementById('zbModalMask');
    if(m) m.parentNode.removeChild(m);
  }
  function openModal(innerHTML){
    closeModal();
    var mask = document.createElement('div');
    mask.id = 'zbModalMask';
    mask.className = 'zb-modal-mask';
    mask.innerHTML = '<div class="zb-modal">' + innerHTML + '</div>';
    mask.addEventListener('click', function(e){ if(e.target === mask) closeModal(); });
    document.body.appendChild(mask);
    var btn = mask.querySelector('.zb-modal-close');
    if(btn) btn.addEventListener('click', closeModal);
  }
  function headHTML(title, color, countText){
    var badge = color ? '<span class="zb-modal-badge" style="background:' + esc(color) + '">' + esc(title) + '</span>' : '';
    return '<div class="zb-modal-head">' + badge +
      '<h3>' + esc(title) + ' 明细</h3>' +
      '<span class="zb-modal-count">' + esc(countText || '') + '</span>' +
      '<button type="button" class="zb-modal-close" title="关闭">&times;</button></div>';
  }
  function renderTable(data){
    var cols = data.columns || {};
    var items = data.items || [];
    var keys = Object.keys(cols);

    if(!keys.length || !items.length){
      var msg = data.note || '没有可展示的明细。';
      return '<div class="zb-modal-empty">' + esc(msg) + '</div>';
    }

    var th = keys.map(function(k){ return '<th>' + esc(cols[k]) + '</th>'; }).join('');
    var rows = items.map(function(it){
      var tds = keys.map(function(k){
        var v = it[k];
        if(k === 'id'){
          var text = '#' + esc(it.id) + ' ' + esc(it.name);
          return it.url ? '<td><a class="zb-task-link" href="' + esc(it.url) + '" target="_blank">' + text + '</a></td>' : '<td>' + text + '</td>';
        }
        if(v == null || v === '') v = '-';
        return '<td>' + esc(v) + '</td>';
      }).join('');
      return '<tr>' + tds + '</tr>';
    }).join('');

    return '<div class="zb-modal-body"><table><thead><tr>' + th + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
  }
  function loadDetail(tag){
    var pid = tag.getAttribute('data-pid');
    var rule = tag.getAttribute('data-rule');
    var label = tag.getAttribute('data-label') || '';
    var color = tag.style.background || '';
    var tpl = window.zhoubaoZouchaDetailURL || '';
    if(!pid || !rule || !tpl) return;

    var url = tpl.replace('__PID__', encodeURIComponent(pid)).replace('__RULE__', encodeURIComponent(rule));

    openModal(headHTML(label, color, '加载中…') + '<div class="zb-modal-loading">正在加载明细…</div>');

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      var data;
      try{ data = JSON.parse(xhr.responseText); }
      catch(err){ openModal(headHTML(label, color, '') + '<div class="zb-modal-empty">明细加载失败，请重试。</div>'); return; }

      var countText = (data.items && data.items.length) ? ('共 ' + data.items.length + ' 条') : '';
      openModal(headHTML(label, color, countText) + renderTable(data));
    };
    xhr.send();
  }

  document.addEventListener('click', function(e){
    var tag = e.target.closest && e.target.closest('.zb-zoucha-tag[data-rule]');
    if(tag){ e.preventDefault(); loadDetail(tag); }
  });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });
})();
