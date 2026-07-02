# 项目周报插件（zhoubao）增强设计：风险单选筛选 / 项目甘特图 / 历史周报对比 / 上周计划前置

- 日期：2026-07-02
- 插件 code：`zhoubao`
- 关联文档：[2026-07-01-zhoubao-weekly-report-design.md](2026-07-01-zhoubao-weekly-report-design.md)（初版设计，本文档只描述在其之上的增量变更）
- 作者：李永杰

## 1. 背景与目标

初版 zhoubao 插件（周报看板 + 单项目填报/查看）上线后，提出 4 项增强需求：

1. "风险与需协调资源"改为单选（是/否），选"是"才需要填写风险详情；周报看板筛选栏增加"是否有风险及需协调资源"筛选项。
2. 单项目周报页新增一个区块，基于项目类型（`model` 字段：瀑布/敏捷/看板）展示项目甘特图/迭代/计划，全局呈现项目所处阶段及后期计划。
3. 单项目周报页新增按钮，一键按最近 N 周查询该项目历次周报及计划，用列表展示，便于逐周对比。
4. 编辑页"本周总结"上方显示"上周计划"（即上周周报的下周计划字段）。

4 项均落在同一插件、同一批文件内，互不依赖，合并为一份设计与一轮实现。

## 2. 数据表变更

`zt_zhoubao` 新增一列：

| 字段 | 类型 | 说明 |
|------|------|------|
| `hasRisk` | enum('yes','no') NOT NULL DEFAULT 'no' | 是否存在风险/需协调资源；'no' 时 `risk` 文本应为空 |

- `db/install.sql`：直接在 `risk` 列后加 `hasRisk`（新装环境无需迁移）。
- 新增 `db/upgrade_hasrisk_column.sql`（仿 `zentao_taizhang/db/upgrade_amount_columns.sql` 的写法）：
  ```sql
  ALTER TABLE `zt_zhoubao`
    ADD COLUMN IF NOT EXISTS `hasRisk` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '是否有风险/需协调资源' AFTER `risk`;
  UPDATE `zt_zhoubao` SET `hasRisk` = 'yes' WHERE `risk` IS NOT NULL AND `risk` != '';
  ```
  （MariaDB 支持 `ADD COLUMN IF NOT EXISTS`；原生 MySQL 报错则去掉该子句单独执行。注释内不含 `;`。）
- 不新增表：项目甘特图数据直接查 `TABLE_EXECUTION`（即 `zt_project`）；历史周报复用 `zt_zhoubao` 按项目多行查询。
- `plugin.json` / `doc/zh-cn.yaml` 版本号 1.0 → 1.1，changelog 补充本次 4 项变更。

## 3. 功能一：风险单选 + 筛选栏

### model.php

- `saveReport($project, $weekStart, $post, $account, $submit)`：
  - 读取 `$post['hasRisk']`，仅接受 `'yes'`，否则一律存 `'no'`（白名单校验，防止枚举外值导致 DAO 报错）。
  - 当 `hasRisk !== 'yes'` 时，强制 `$data->risk = ''`（即使前端隐藏文本框后仍提交了残留值，服务端兜底清空，避免脏数据存进 snapshot/CSV）。
- `getBoardRows($weekStart, $pm = '', $fill = '', $risk = '')` 新增第 4 个参数：
  - `$risk` 取值 `''/all/yes/no`。
  - 判定：`$report` 存在时用 `$report->hasRisk`；`$report` 不存在（status=none）一律视为 `'no'`。
  - `$risk === 'yes'` 时只保留 `hasRisk === 'yes'` 的行；`$risk === 'no'` 时保留其余所有行（含无报告）。
  - 行对象新增字段 `hasRisk`，供 browse 表格/CSV 使用。

### control.php

- `browse($week = '', $pm = '', $fill = '', $risk = '')`：新增 `$risk` 形参（PATH_INFO 位置参数，务必按顺序声明），从 `$_GET['risk']` 覆盖，传给 `getBoardRows()`，视图变量新增 `$this->view->risk`。
- `export($type = 'board', $week = '', $id = 0, $risk = '')`：board 类型导出时把 `$risk` 传给 `getBoardRows()`，CSV 表头加"是否有风险"列；one 类型导出表头加"是否有风险"列，取 `$r->hasRisk`。

### lang/zh-cn.php

```php
$lang->zhoubao->hasRisk     = '是否有风险及需协调资源';
$lang->zhoubao->hasRiskList = array('yes' => '是', 'no' => '否');
```

### ui/browse.html.php

- 筛选栏在"填报状态"下拉后新增"是否有风险"下拉（同款 `<select id="zbFilterRisk">`，选项：全部/是/否），URL 参数模板 `zhoubaoFilterURL` 增加 `__RISK__` 占位符，`zbSubmitFilter()`/`zbResetFilter()` 同步读取/重置该下拉。
- 周切换链接（`prevURL`/`curURL`/`nextURL`）、导出链接也要带上当前 `risk` 参数，保持筛选状态在翻页/导出时不丢失（与现有 `pm`/`fill` 参数处理方式一致）。

### ui/edit.html.php

- `风险与需协调资源` 表单组改为：
  ```html
  <div class="zb-form-group">
    <label>是否有风险及需协调资源</label>
    <label><input type="radio" name="hasRisk" value="yes" ...> 是</label>
    <label><input type="radio" name="hasRisk" value="no" ...> 否</label>
  </div>
  <div class="zb-form-group" id="zbRiskDetail" style="display:none">
    <label>风险与需协调资源</label>
    <textarea name="risk" class="form-control" rows="4">...</textarea>
  </div>
  ```
  初始 `checked`/`style="display:"` 根据 `$report->hasRisk`（无报告默认 'no'）渲染。

### ui/view.html.php

- "风险与需协调资源"标题旁加"是/否"标签（`hasRiskList` 映射）；`hasRisk === 'no'` 时不展示（本应为空的）正文段落，只显示"否"标签。

### js/zhoubao.js

- `collect()` 增加读取 `form.hasRisk`（单选组取 checked 值）一并 POST。
- 新增 toggle 逻辑：监听两个 radio 的 `change` 事件，切换 `#zbRiskDetail` 的 `display`；选"否"时清空文本框（前端体验层面同步，服务端仍兜底二次清空）。
- `zbCopyLast()` 复制上周内容时，同时回填上周的 `hasRisk` 单选状态（`copyLast` 控制器方法需要一并返回 `hasRisk` 字段，`model.php::getPrevReport()` 已经 `select('*')`，控制器 `copyLast()` 的返回数组补上 `'hasRisk' => $prev->hasRisk` 即可）。

## 4. 功能二：项目甘特图区块

### model.php

新增 `getProjectGantt($project)`：

1. 查项目自身：`$this->dao->select('model, status, begin, end')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch()`，取 `model` 决定面板标题文案。
2. 查迭代/阶段：
   ```php
   $this->dao->select('id, name, type, status, begin, end, realBegan, realEnd')
       ->from(TABLE_EXECUTION)
       ->where('project')->eq($project)
       ->andWhere('type')->in('sprint,stage,kanban')
       ->andWhere('deleted')->eq('0')
       ->orderBy('begin_asc')
       ->fetchAll();
   ```
3. 每条记录组装为甘特条目：`start = realBegan ?: begin`，`end = realEnd ?: end`（`end` 为空时兜底 `start + 7 天`，避免零宽条），`statusColor` 由状态映射（`wait`=灰 `#94a3b8`、`doing`=蓝 `#3b82f6`、`closed`=绿 `#10b981`、`end < today && status !== 'closed'`=红 `#ef4444` 逾期未关闭覆盖前三色）。
4. 返回 `array('modelLabel' => ..., 'items' => [...])`；`items` 为空时前端渲染空态。

面板标题按 `model` 映射（未知值兜底通用文案）：

| `model` 值 | 标题 |
|---|---|
| `waterfall` | 阶段甘特图 |
| `scrum`（或含 sprint 的敏捷型） | 迭代甘特图 |
| `kanban` | 看板阶段 |
| 其他/空 | 项目进度甘特图 |

### control.php

- `edit()`、`view()` 都追加 `$this->view->gantt = $this->zhoubao->getProjectGantt($project)`（`view()` 里用 `$report->project`）。

### ui/edit.html.php、ui/view.html.php

- 在"进度/工时概览"卡片（`zb-stat-cards`）之后、走查提示标签之前，插入：
  ```php
  panel(set::title($ganttTitle), div(setID('zbGanttChart'), setStyle(['width'=>'100%','height'=>'280px'])));
  ```
  （沿用 ZIN `panel()`/`div()` 部件，与本页其余手写 HTML 拼接方式并列即可，面板本身用部件、内容区留空 div 给 ECharts 挂载。）
- 通过 `jsVar('window.zbGanttData', json_encode($gantt))` 注入数据，`pageJS()` 内联渲染函数：照搬 `man_resource/ui/projectdimension.html.php` 里 `loadEcharts()` + `initResourceGantt()` 的写法改造成单项目版本（`renderItem` 画横条、`xAxis: {type:'time'}`、`yAxis: {type:'category', data: 迭代名称列表}`，加一条 `markLine` 标"今天"），空数据时 `el.innerHTML` 显示"该项目暂无迭代/阶段数据"。
- 必须走 `pageJS()`/`css()` 通道而非裸 `<script>`（本插件已有两条实测记录说明 SPA 内部导航会吞掉裸 `<script>`）。

## 5. 功能三：历史周报对比列表

### control.php

新增方法：

```php
public function history($project, $weeks = '8')
{
    $project = (int)$project;
    $weeks   = in_array($weeks, array('4','8','12','all'), true) ? $weeks : '8';
    $projectInfo = ...; // 同 edit()/view() 的项目查询 + 存在性校验
    $this->view->title       = '历史周报对比';
    $this->view->projectInfo = $projectInfo;
    $this->view->weeks       = $weeks;
    $this->view->rows        = $this->zhoubao->getReportHistory($project, $weeks);
    $this->display();
}
```

### model.php

```php
public function getReportHistory($project, $weeks)
{
    $query = $this->dao->select('*')->from('zt_zhoubao')
        ->where('project')->eq($project)
        ->orderBy('weekStart_desc');
    if($weeks !== 'all') $query->limit((int)$weeks);
    return $query->fetchAll();
}
```

（`weeks='all'` 不加 `limit`；仅按 `project` 过滤，草稿/已提交都纳入，因为对比目的是看"计划连续性"而非只看已交的。）

### ui/history.html.php（新文件）

- 顶部：项目名 + N 周下拉（4/8/12/全部，切换即 `location.href` 跳转，参照 browse 页周次切换的处理方式）。
- 主体：表格，一行一周，列为 `周次(年-W周) / 填报状态 / 本周总结 / 下周计划 / 风险(是/否 + 详情) / 完成数 / 逾期数`，按周倒序；文本类字段做截断+悬停展示全文（复用 `zb-tip` 悬浮层交互，或简单用 `title` 属性即可，取决于实现时的复杂度权衡——若追求一致性优先复用现有 `zb-tip-pop`）。
- 空数据（无任何周报）显示空态提示。

### ui/edit.html.php、ui/view.html.php

- 新增按钮"查看历史周报"，`href="{helper::createLink('zhoubao','history',"project={$project->id}")}"`，新标签页打开（`target="_blank"`，与项目链接一致）。

### lang/zh-cn.php

```php
$lang->zhoubao->historyTitle = '历史周报对比';
$lang->zhoubao->viewHistory  = '查看历史周报';
```

### 权限

- `history` 方法读权限与 `view()` 一致：只要能看到该项目的周报入口即可查看历史（不额外限制，因为看板本身已经是项目范围内可见的信息汇总，不涉及跨项目越权）。

## 6. 功能四：编辑/查看页显示"上周计划"

### control.php

- `edit()`：已有 `$weekStart`，追加 `$this->view->prevReport = $this->zhoubao->getPrevReport($project, $weekStart)`。
- `view()`：追加 `$this->view->prevReport = $this->zhoubao->getPrevReport($report->project, $report->weekStart)`。

（`getPrevReport()` 已存在，直接复用，无需改 model。）

### ui/edit.html.php

- "本周总结"表单组之前插入只读区块：
  ```php
  $prev = $this->view->prevReport;
  $pageHTML .= '<div class="zb-prev-plan"><h3>上周计划</h3><p>' . ($prev ? nl2br($esc($prev->nextPlan)) : '上周暂无周报') . '</p></div>';
  ```

### ui/view.html.php

- 同样在"本周总结"标题（`<h3>' . $esc($lang->zhoubao->summary) . '</h3>`）之前插入相同只读区块，取 `$this->view->prevReport`。

### lang/zh-cn.php

```php
$lang->zhoubao->prevPlan = '上周计划';
```

## 7. 边界情况与测试

- **风险单选**：`saveReport()` 对非法 `hasRisk` 值兜底为 `'no'`；`hasRisk='no'` 且提交了非空 `risk` 文本时服务端强制清空——这两条属于纯逻辑，若仓库要求可测试性，可评估是否值得抽到 `lib/zhoubaoRules.php`（当前判断足够简单，倾向直接留在 model 里，不强行拆分增加复杂度）。
- **筛选组合**：`pm` + `fill` + `risk` 三个筛选参数两两独立生效（AND 关系），沿用现有 `getBoardRows()` 的过滤顺序（先 pm，再计算 status，再 fill，再 risk）。
- **甘特图**：项目下无迭代/阶段（`items` 为空）时不报错，展示空态文案；`begin`/`end` 均为空的历史脏数据要有兜底（不产生 NaN 或负宽度条）。
- **历史列表**：`weeks` 参数做白名单校验（4/8/12/all），非法值兜底 8，防止意外的 `limit` 注入或类型错误。
- **上周计划**：跨自然年边界（如 2026 年第 1 周的上周是 2025 年第 52/53 周）由 `getPrevReport()` 内部用日期 `-7 days` 计算，已自然正确处理，不需要额外年份换算逻辑。
- **回归**：`zhoubao/tests/test_rules.php` 覆盖的是任务分类规则，本次改动不涉及 `lib/zhoubaoRules.php`，无需改测试；人工验证清单：填报页单选切换 + 保存/提交、看板筛选三个维度组合、单项目甘特图渲染（含无迭代空态）、历史列表 4/8/12/全部切换、上周计划展示（含无上周周报兜底）。

## 8. 变更文件清单

- `db/install.sql`（加列）、`db/upgrade_hasrisk_column.sql`（新增）
- `extension/custom/zhoubao/control.php`（browse/export/history/edit/view 签名与视图变量）
- `extension/custom/zhoubao/model.php`（getBoardRows/saveReport/copyLast 返回值/getProjectGantt/getReportHistory）
- `extension/custom/zhoubao/lang/zh-cn.php`（新增文案）
- `extension/custom/zhoubao/ui/browse.html.php`（风险筛选下拉）
- `extension/custom/zhoubao/ui/edit.html.php`（单选+条件文本框、甘特图面板、历史按钮、上周计划区块）
- `extension/custom/zhoubao/ui/view.html.php`（风险标签、甘特图面板、历史按钮、上周计划区块）
- `extension/custom/zhoubao/ui/history.html.php`（新增）
- `extension/custom/zhoubao/js/zhoubao.js`（hasRisk toggle/collect/copyLast 回填、甘特图渲染 JS 内联在页面 pageJS 里，不放进这个共享文件——避免非甘特图页面也加载 echarts 逻辑）
- `plugin.json` / `doc/zh-cn.yaml`（版本号 + changelog）
