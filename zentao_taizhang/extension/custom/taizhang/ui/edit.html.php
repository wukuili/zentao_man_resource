<?php
declare(strict_types=1);
/**
 * 项目台账 — 新增/编辑页 ZIN 模板
 */
namespace zin;

$entry       = $this->view->entry;
$id          = (int)$this->view->id;
$projectList = $this->view->projectList;
$phaseList   = $this->view->phaseList;
$userList    = $this->view->userList;
$saveURL     = $this->view->saveURL;
$browseURL   = helper::createLink('taizhang', 'browse');

/* 当前字段值（编辑时取已有，新增时为空/默认） */
$v = function($field, $default = '') use ($entry) {
    if($entry && isset($entry->$field)) return htmlspecialchars((string)$entry->$field);
    return htmlspecialchars((string)$default);
};
$vNum = function($field, $default = '0.00') use ($entry) {
    if($entry && isset($entry->$field)) return (float)$entry->$field;
    return (float)$default;
};

/* 构建 select HTML */
$buildSelect = function($name, $options, $current, $class = '') {
    $html = "<select name=\"{$name}\" id=\"{$name}\" class=\"{$class}\">";
    foreach($options as $val => $label) {
        $sel   = ((string)$val === (string)$current) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars((string)$val) . "\"{$sel}>" . htmlspecialchars((string)$label) . "</option>";
    }
    $html .= '</select>';
    return $html;
};

$phaseSelect   = $buildSelect('phase',     $phaseList,   $v('phase'),     'form-control');
$projectSelect = $buildSelect('projectID', $projectList, $v('projectID'), 'form-control');
$pmSelect      = array(0 => '-- 请选择研发经理 --') + (array)$userList;
$rdSelect      = $buildSelect('rdManager', $pmSelect,    $v('rdManager'), 'form-control');

/* 构建表单 HTML */
$formHTML  = '<form id="tzEditForm" method="post" action="' . $saveURL . '">';
$formHTML .= '<div class="tz-form-panel">';

/* 第一行：关联项目 + 项目简称 */
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>关联禅道项目 <span class="text-danger">*</span></label>';
$formHTML .= $projectSelect;
$formHTML .= '<div class="tz-form-tip">选择已有禅道项目，可自动获取项目经理信息</div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>项目简称</label>';
$formHTML .= '<input type="text" name="shortName" id="shortName" class="form-control" value="' . $v('shortName') . '" placeholder="简称，不填则使用项目名">';
$formHTML .= '</div>';
$formHTML .= '</div>';

/* 第二行：项目阶段 + 研发经理 */
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>项目阶段</label>' . $phaseSelect . '</div>';
$formHTML .= '<div class="tz-form-group"><label>研发经理</label>' . $rdSelect . '</div>';
$formHTML .= '</div>';

/* 当前项目情况 */
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>当前项目情况</label>';
$formHTML .= '<textarea name="currentStatus" id="currentStatus" class="form-control" rows="5" placeholder="描述当前项目进展、风险等情况">' . $v('currentStatus') . '</textarea>';
$formHTML .= '</div>';

/* 数值字段 — 初始预估 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">初始预估</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>初始预估人月</label><input type="number" name="initEstHours" class="form-control" value="' . $vNum('initEstHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>初始预估成本 (万元)</label><input type="number" name="initBudget" class="form-control" value="' . $vNum('initBudget') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '</div>';

/* 已投入 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">已投入（实际）</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>已投入人月</label><input type="number" name="investedHours" class="form-control" value="' . $vNum('investedHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>已投入成本 — 除外购和税 (万元)</label><input type="number" name="investedCost" class="form-control" value="' . $vNum('investedCost') . '" step="0.01" min="0" placeholder="0.00"><div class="tz-form-tip">不含外购硬件及税费的实际人力成本</div></div>';
$formHTML .= '</div>';

/* 当前预估 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">当前预估</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>当前预估人月</label><input type="number" name="currentEstHours" class="form-control" value="' . $vNum('currentEstHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>当前预估成本 (万元)</label><input type="number" name="currentBudget" class="form-control" value="' . $vNum('currentBudget') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '</div>';

/* 合同金额 + 近期成员 + 排序 */
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>合同金额/收入 (万元)</label><input type="number" name="revenue" class="form-control" value="' . $vNum('revenue') . '" step="0.01" min="0" placeholder="0.00"><div class="tz-form-tip">用于计算预估利润率：(合同金额-当前预估成本)÷合同金额×100%</div></div>';
$formHTML .= '<div class="tz-form-group"><label>排列顺序</label><input type="number" name="sortOrder" class="form-control" value="' . $vNum('sortOrder', 0) . '" step="1" min="0" placeholder="0"><div class="tz-form-tip">数字越小越靠前</div></div>';
$formHTML .= '</div>';

$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>近期项目成员</label>';
$formHTML .= '<input type="text" name="recentMembers" class="form-control" value="' . $v('recentMembers') . '" placeholder="例如：张三、李四、王五（手动填写）">';
$formHTML .= '</div>';

/* 按钮区 */
$formHTML .= '<div class="tz-form-actions">';
$formHTML .= '<button type="button" class="btn btn-primary" onclick="tzSubmitForm(\'tzEditForm\', \'' . $saveURL . '\')">保存</button>';
$formHTML .= '<a href="' . $browseURL . '" class="btn btn-default">取消</a>';
$formHTML .= '</div>';

$formHTML .= '</div></form>';

/* ── 渲染 ── */
panel
(
    set::title($this->view->title),
    html($formHTML)
);

/* extension/ 不在 Web 根下，读取文件内容内联输出，避免静态资源 404/MIME 报错 */
$cssPath = $app->getAppRoot() . 'extension/custom/taizhang/css/taizhang.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/taizhang/js/taizhang.js';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
if(is_file($jsPath))  echo "<script>\n" . file_get_contents($jsPath)  . "\n</script>";
