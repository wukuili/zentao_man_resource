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
