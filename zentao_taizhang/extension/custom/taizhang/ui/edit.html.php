<?php
declare(strict_types=1);
/**
 * 项目台账 — 新增/编辑页 ZIN 模板
 */
namespace zin;

$entry        = $this->view->entry;
$id           = (int)$this->view->id;
$projectList  = $this->view->projectList;
$phaseList    = $this->view->phaseList;
$categoryList = $this->view->categoryList;
$yesNoList    = $this->view->yesNoList;
$userList     = $this->view->userList;
$saveURL      = $this->view->saveURL;
$browseURL    = helper::createLink('taizhang', 'browse');

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

$categoryOptions = array('' => '-- 请选择 --') + $categoryList;
$yesNoOptions     = array('' => '-- 请选择 --') + $yesNoList;
$categorySelect          = $buildSelect('projectCategory',   $categoryOptions, $v('projectCategory'),   'form-control');
$securityMeasuresSelect  = $buildSelect('securityMeasures',  $yesNoOptions,    $v('securityMeasures'),  'form-control');
$hazardousWorkSelect     = $buildSelect('hazardousWork',     $yesNoOptions,    $v('hazardousWork'),     'form-control');
$startDocsCompleteSelect = $buildSelect('startDocsComplete', $yesNoOptions,    $v('startDocsComplete'), 'form-control');

/* 分包合同状态/开工资料是否齐全/安保措施是否到位/是否涉及危险作业仅适用于施工类/集成类项目，
 * 软件类项目不展示这几项；初始可见性由当前已选项目类别决定，后续由 JS 监听 select 变化切换。 */
$showConstructionFields = in_array($v('projectCategory'), array('集成类', '施工类'), true);
$condFieldStyle = $showConstructionFields ? '' : ' style="display:none"';

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

/* 项目阶段 + 项目类别 */
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>项目阶段</label>' . $phaseSelect . '</div>';
$formHTML .= '<div class="tz-form-group"><label>项目类别</label>' . $categorySelect . '</div>';
$formHTML .= '</div>';

/* 当前项目情况 */
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>当前项目情况</label>';
$formHTML .= '<textarea name="currentStatus" id="currentStatus" class="form-control" rows="5" placeholder="描述当前项目进展、风险等情况">' . $v('currentStatus') . '</textarea>';
$formHTML .= '</div>';

/* 项目简介 */
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>项目简介</label>';
$formHTML .= '<textarea name="projectIntro" id="projectIntro" class="form-control" rows="3" placeholder="项目背景、范围等简要介绍">' . $v('projectIntro') . '</textarea>';
$formHTML .= '</div>';

/* 合同与工程信息 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">合同与工程信息</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>合同签署时间</label><input type="date" name="contractSignDate" class="form-control" value="' . $v('contractSignDate') . '"></div>';
$formHTML .= '<div class="tz-form-group tz-cond-field" id="tz-field-subcontractStatus"' . $condFieldStyle . '><label>分包合同状态</label><input type="text" name="subcontractStatus" class="form-control" value="' . $v('subcontractStatus') . '"></div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>工程状态</label><input type="text" name="engineeringStatus" class="form-control" value="' . $v('engineeringStatus') . '"></div>';
$formHTML .= '<div class="tz-form-group"><label>采购方式</label><input type="text" name="procurementMethod" class="form-control" value="' . $v('procurementMethod') . '"></div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>供货单位</label><input type="text" name="supplyUnit" class="form-control" value="' . $v('supplyUnit') . '"></div>';
$formHTML .= '<div class="tz-form-group"><label>安装/施工单位</label><input type="text" name="constructionUnit" class="form-control" value="' . $v('constructionUnit') . '"></div>';
$formHTML .= '</div>';

/* 计划与实际进度 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">计划与实际进度</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>计划开工日期</label><input type="date" name="planStartDate" class="form-control" value="' . $v('planStartDate') . '"></div>';
$formHTML .= '<div class="tz-form-group"><label>实际开工日期</label><input type="date" name="actualStartDate" class="form-control" value="' . $v('actualStartDate') . '"></div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>计划完工日期</label><input type="date" name="planEndDate" class="form-control" value="' . $v('planEndDate') . '"></div>';
$formHTML .= '<div class="tz-form-group"><label>实际完工日期</label><input type="date" name="actualEndDate" class="form-control" value="' . $v('actualEndDate') . '"></div>';
$formHTML .= '</div>';

/* 验收与安全管理 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">验收与安全管理</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>验收情况</label><input type="text" name="acceptanceStatus" class="form-control" value="' . $v('acceptanceStatus') . '"></div>';
$formHTML .= '<div class="tz-form-group tz-cond-field" id="tz-field-startDocsComplete"' . $condFieldStyle . '><label>开工资料是否齐全</label>' . $startDocsCompleteSelect . '</div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-row tz-cond-field" id="tz-row-security"' . $condFieldStyle . '>';
$formHTML .= '<div class="tz-form-group"><label>安保措施是否到位</label>' . $securityMeasuresSelect . '</div>';
$formHTML .= '<div class="tz-form-group"><label>是否涉及危险作业</label>' . $hazardousWorkSelect . '</div>';
$formHTML .= '</div>';

/* 数值字段 — 初始预估 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">初始预估</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>初始预估人天</label><input type="number" name="initEstHours" class="form-control" value="' . $vNum('initEstHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>初始预估成本 (万元)</label><input type="number" name="initBudget" class="form-control" value="' . $vNum('initBudget') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '</div>';

/* 已投入 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">已投入（实际）</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>已投入人天</label><input type="number" name="investedHours" class="form-control" value="' . $vNum('investedHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>已投入成本 — 除外购和税 (万元)</label><input type="number" name="investedCost" class="form-control" value="' . $vNum('investedCost') . '" step="0.01" min="0" placeholder="0.00"><div class="tz-form-tip">不含外购硬件及税费的实际人力成本</div></div>';
$formHTML .= '</div>';

/* 当前预估 */
$formHTML .= '<div style="font-weight:600;font-size:13px;color:#4a9ed7;margin:14px 0 8px;padding-bottom:4px;border-bottom:2px solid #4a9ed7">当前预估</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>当前预估人天</label><input type="number" name="currentEstHours" class="form-control" value="' . $vNum('currentEstHours') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>当前预估成本 (万元)</label><input type="number" name="currentBudget" class="form-control" value="' . $vNum('currentBudget') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '</div>';

/* 金额信息 */
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>合同金额 (万元)</label><input type="number" name="revenue" class="form-control" value="' . $vNum('revenue') . '" step="0.01" min="0" placeholder="0.00"><div class="tz-form-tip">用于计算预估利润率：(合同金额-当前预估成本-外采金额)÷合同金额×100%</div></div>';
$formHTML .= '<div class="tz-form-group"><label>外采金额 (万元)</label><input type="number" name="outsourcingAmount" class="form-control" value="' . $vNum('outsourcingAmount') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-row">';
$formHTML .= '<div class="tz-form-group"><label>回款金额 (万元)</label><input type="number" name="receivedAmount" class="form-control" value="' . $vNum('receivedAmount') . '" step="0.01" min="0" placeholder="0.00"></div>';
$formHTML .= '<div class="tz-form-group"><label>排列顺序</label><input type="number" name="sortOrder" class="form-control" value="' . $vNum('sortOrder', 0) . '" step="1" min="0" placeholder="0"><div class="tz-form-tip">数字越小越靠前</div></div>';
$formHTML .= '</div>';

/* 进度偏差说明 + 备注 */
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>进度偏差说明</label>';
$formHTML .= '<textarea name="progressDeviation" id="progressDeviation" class="form-control" rows="3" placeholder="计划与实际进度有偏差时的说明">' . $v('progressDeviation') . '</textarea>';
$formHTML .= '</div>';
$formHTML .= '<div class="tz-form-group">';
$formHTML .= '<label>备注</label>';
$formHTML .= '<textarea name="remark" id="remark" class="form-control" rows="3">' . $v('remark') . '</textarea>';
$formHTML .= '</div>';

/* 近期项目成员在列表页由项目团队成员自动汇总展示，编辑表单无需手动填写 */

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
