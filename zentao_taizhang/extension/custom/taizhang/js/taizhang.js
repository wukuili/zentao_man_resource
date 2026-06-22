(function() {
    'use strict';

    /**
     * 台账筛选表单提交 — 收集三个下拉值，跳转到过滤后的 URL
     */
    window.tzSubmitFilter = function() {
        var fields = {
            phase: document.getElementById('tzFilterPhase'),
            category: document.getElementById('tzFilterCategory'),
            pm: document.getElementById('tzFilterPM'),
            projectStatus: document.getElementById('tzFilterStatus')
        };
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.tzBrowseURL || '';

        Object.keys(fields).forEach(function(name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name] ? fields[name].value : '';
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
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
     * 项目类别联动：分包合同状态/开工资料是否齐全/安保措施是否到位/是否涉及危险作业
     * 仅施工类、集成类项目展示；初始可见性已由服务端按当前值渲染，这里只处理切换。
     */
    window.tzToggleCategoryFields = function() {
        var sel = document.getElementById('projectCategory');
        if(!sel) return;
        var show = (sel.value === '集成类' || sel.value === '施工类');
        var fields = document.querySelectorAll('.tz-cond-field');
        for(var i = 0; i < fields.length; i++) {
            fields[i].style.display = show ? '' : 'none';
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        var sel = document.getElementById('projectCategory');
        if(sel) sel.addEventListener('change', window.tzToggleCategoryFields);
    });

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
