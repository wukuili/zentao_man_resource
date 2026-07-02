# zhoubao 看板批量导出补充周报正文 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `zentao_zhoubao` 插件的看板批量导出（`export?type=board`）在现有统计列后追加周报正文三列（下周计划/风险/本周总结），未提交周报的项目留空。

**Architecture:** `getBoardRows()` 已经通过 `getReportMap()`（`select('*')`）查出每个项目当周的 `$report`，只需把 `nextPlan`/`risk`/`summary` 透出到返回的行对象；`export()` 的 `board` CSV 分支追加对应表头和数据列，复用已有的 `csvSafe()` 做公式注入防护。

**Tech Stack:** PHP 8.1，禅道 20/22.x 扩展模块，无框架依赖的纯改动（两处编辑）。

## Global Constraints

- 未提交周报的项目，三个新增字段导出为空字符串（不是 `null`，不是占位符文本）。
- 新增文本列必须经过 `csvSafe()` 转义，防止 CSV 公式注入（与现有 `projectName`/`pmName`/`one` 分支写法一致）。
- 不新增数据库查询；不改 `getReportMap()` 的 `select` 字段（已经是 `select('*')`）。
- 不改动 `type=one` 导出分支、不改 `browse.html.php` 页面表格。
- 本仓库无可跑的禅道运行时实例，`control.php`/`model.php` 无自动化测试（`CLAUDE.md` 明确：仅 `zoucha` 有自动化测试）；验证方式是 `php -l` 语法检查 + 人工走查代码逻辑。

---

### Task 1: 看板导出追加周报正文三列

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php` — `getBoardRows()` 方法内的行对象构造（约第 134-144 行 `$rows[] = (object)array(...)`）
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php` — `export()` 方法的 `board` 分支（约第 144-151 行）

**Interfaces:**
- Consumes: `getBoardRows()` 内部已有的局部变量 `$report`（`getReportMap()` 返回的当周周报记录，字段包含 `nextPlan`、`risk`、`summary`，无周报时为 `null`）
- Produces: `getBoardRows()` 返回的每个行 object 新增只读属性 `nextPlan: string`、`risk: string`、`summary: string`（无周报时为空字符串 `''`），供 `control.php::export()` 的 `board` 分支读取

- [ ] **Step 1: 修改 `model.php`，在 `getBoardRows()` 返回的行对象中追加三个字段**

打开 `zentao_zhoubao/extension/custom/zhoubao/model.php`，找到：

```php
            $rows[] = (object)array(
                'project'      => $pid,
                'projectName'  => $project->name,
                'pm'           => $project->PM,
                'pmName'       => isset($pmNames[$project->PM]) && $pmNames[$project->PM] !== '' ? $pmNames[$project->PM] : $project->PM,
                'status'       => $status,
                'hasRisk'      => $hasRisk,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
            );
```

替换为（追加 `nextPlan`/`risk`/`summary` 三行）：

```php
            $rows[] = (object)array(
                'project'      => $pid,
                'projectName'  => $project->name,
                'pm'           => $project->PM,
                'pmName'       => isset($pmNames[$project->PM]) && $pmNames[$project->PM] !== '' ? $pmNames[$project->PM] : $project->PM,
                'status'       => $status,
                'hasRisk'      => $hasRisk,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
                'nextPlan'     => $report ? $report->nextPlan : '',
                'risk'         => $report ? $report->risk : '',
                'summary'      => $report ? $report->summary : '',
            );
```

- [ ] **Step 2: 修改 `control.php`，`export()` 的 `board` 分支追加表头和数据列**

打开 `zentao_zhoubao/extension/custom/zhoubao/control.php`，找到：

```php
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '', $risk);
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务'));
            foreach($rows as $r) fputcsv($out, array($this->csvSafe($r->projectName), $this->csvSafe($r->pmName), zget($labels, $r->status, $r->status), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $r->doneCount, $r->overdueCount));
```

替换为：

```php
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '', $risk);
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务', '下周计划', '风险', '本周总结'));
            foreach($rows as $r) fputcsv($out, array($this->csvSafe($r->projectName), $this->csvSafe($r->pmName), zget($labels, $r->status, $r->status), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $r->doneCount, $r->overdueCount, $this->csvSafe($r->nextPlan), $this->csvSafe($r->risk), $this->csvSafe($r->summary)));
```

- [ ] **Step 3: PHP 语法检查**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/model.php && php -l zentao_zhoubao/extension/custom/zhoubao/control.php`
Expected: 两个文件均输出 `No syntax errors detected`

- [ ] **Step 4: 人工走查确认逻辑正确**

对照 `zt_zhoubao` 表结构（`zentao_zhoubao/db/install.sql`）确认字段名 `nextPlan`/`risk`/`summary` 与表结构一致（这三个字段已在 `type=one` 分支使用过，命名应已确认无误，此处仅需比对新增代码是否拼写一致）。确认 `board` 分支的表头列数（9 列）与每行数据的数组元素数（9 个）一一对应，避免 CSV 错位。

- [ ] **Step 5: Commit**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/model.php zentao_zhoubao/extension/custom/zhoubao/control.php
git commit -m "feat(zhoubao): 看板批量导出补充下周计划/风险/本周总结正文"
```

---

## Manual Verification (post-deploy，非本计划任务，供部署后人工验证参考)

本仓库无可跑的禅道实例，以下步骤留给用户在真实环境部署后自行验证，不作为本计划的完成条件：

1. 部署改动到测试环境（清 `tmp/cache`、`tmp/model`）。
2. 打开看板列表页，点击"导出"，用 Excel 或文本编辑器打开下载的 CSV。
3. 确认新增的"下周计划/风险/本周总结"三列存在，已提交周报的项目有对应文本，未提交的项目对应单元格为空。
4. 找一份 `risk` 或 `summary` 内容以 `=`/`+`/`-`/`@` 开头的周报（或临时编辑一条测试数据），确认导出的 CSV 中该单元格前面被加了一个 `'` 前导单引号（防公式注入），Excel 打开后显示为文本而非被当公式执行。
