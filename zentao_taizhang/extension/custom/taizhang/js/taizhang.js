(function() {
    'use strict';

    /**
     * 台账筛选表单提交 — 收集三个下拉值，跳转到过滤后的 URL
     */
    window.tzSubmitFilter = function() {
        var phase     = document.getElementById('tzFilterPhase');
        var pm        = document.getElementById('tzFilterPM');
        var rdManager = document.getElementById('tzFilterRD');
        var baseURL   = window.tzBrowseURL || '';

        var params = [];
        if(phase && phase.value)     params.push('phase='     + encodeURIComponent(phase.value));
        if(pm && pm.value)           params.push('pm='        + encodeURIComponent(pm.value));
        if(rdManager && rdManager.value) params.push('rdManager=' + encodeURIComponent(rdManager.value));

        window.location.href = baseURL + (params.length ? '?' + params.join('&') : '');
    };

    /**
     * 重置筛选，跳到无参 URL
     */
    window.tzResetFilter = function() {
        window.location.href = window.tzBrowseURL || '';
    };

    /**
     * 删除确认
     */
    window.tzDeleteEntry = function(id, deleteURL) {
        if(!confirm(window.tzLang && window.tzLang.confirmDelete || '确认删除？')) return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', deleteURL, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if(xhr.readyState !== 4) return;
            try {
                var resp = JSON.parse(xhr.responseText);
                if(resp.result === 'success') {
                    if(resp.locate) { window.location.href = resp.locate; }
                    else            { window.location.reload(); }
                } else {
                    alert(resp.message || '删除失败');
                }
            } catch(e) {
                window.location.reload();
            }
        };
        xhr.send('');
    };

    /**
     * 编辑表单 AJAX 提交
     */
    window.tzSubmitForm = function(formID, saveURL) {
        var form = document.getElementById(formID);
        if(!form) return;

        var data = new FormData(form);
        var encoded = [];
        for(var pair of data.entries()) {
            encoded.push(encodeURIComponent(pair[0]) + '=' + encodeURIComponent(pair[1]));
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', saveURL, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if(xhr.readyState !== 4) return;
            try {
                var resp = JSON.parse(xhr.responseText);
                if(resp.result === 'success') {
                    if(resp.locate) { window.location.href = resp.locate; }
                    else            { window.history.back(); }
                } else {
                    alert(resp.message || '保存失败');
                }
            } catch(e) {
                window.history.back();
            }
        };
        xhr.send(encoded.join('&'));
    };

})();
