# 周报风险单选/项目甘特图/历史对比/上周计划 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 4 increments to the existing `zhoubao`（项目周报）ZenTao plugin: (1) risk-yes/no radio + conditional detail field + board filter, (2) a per-project ECharts Gantt panel on the report page, (3) a per-project historical-report comparison list, (4) a "last week's plan" readout above this week's summary.

**Architecture:** All 4 increments live inside the existing `zentao_zhoubao/` plugin, following its established control→model split (`extension/custom/zhoubao/control.php` / `model.php`), ZIN hand-written-HTML templates (`ui/*.html.php`), and `jsVar()`/`pageJS()`/`css()` injection channels (raw `<script>` is filtered by ZenTao 22.2's SPA on internal navigation — already proven in this plugin, see `edit.html.php` comments). No new plugin, no new top-level nav entry.

**Tech Stack:** PHP 8.1 (ZenTao 20+/22.x extension conventions), MySQL/MariaDB, vanilla JS (no framework), ECharts (`echarts.common.min.js`, loaded lazily via the `loadEcharts()` pattern already used in `man_resource`).

## Global Constraints

- Repo has no compiler/build step and no PHPUnit; the only automated test in this plugin is `zentao_zhoubao/tests/test_rules.php` (framework-free, tests `lib/zhoubaoRules.php` only — untouched by this plan). Verification for everything else = `php -l` per file + a manual checklist against a live ZenTao instance (final task).
- URL date/week params use `_` not `-`; PATH_INFO maps controller args by **position**, so new params must be appended (not inserted) to existing method signatures, and `$_GET['name']` overrides are read defensively (existing pattern in `control.php`, keep it).
- `db/install.sql` / `db/uninstall.sql` must contain **no `;` inside comments** (ZenTao's `executeDB` splits on literal `;`). Manually-run upgrade SQL (piped via `mysql <`, not parsed by ZenTao) is exempt — `zentao_taizhang/db/upgrade_amount_columns.sql` already has a `;` inside a comment and works fine because of this.
- ZIN pages here build one big HTML string (`$pageHTML .= '...'`) rendered through a single `panel(... html($pageHTML))` call per template — don't introduce a second `panel()`/widget-composition style; stay consistent with the existing hand-written-HTML convention in `edit.html.php` / `browse.html.php` / `view.html.php`.
- `jsVar('window.xxx', $value)` JSON-encodes PHP arrays automatically (confirmed via `zin\core\js.class.php::value()` → `jsonEncode($data, JSON_UNESCAPED_UNICODE)`) — no need to `json_encode()` manually before passing to `jsVar()`.
- Chinese-language responses/commit messages per user preference; UI copy in this plugin is Simplified Chinese throughout.

---

## File Map

| File | Change |
|---|---|
| `zentao_zhoubao/db/install.sql` | add `hasRisk` column |
| `zentao_zhoubao/db/upgrade_hasrisk_column.sql` | **new** — upgrade path for already-deployed instances |
| `zentao_zhoubao/plugin.json` | version bump |
| `zentao_zhoubao/doc/zh-cn.yaml` | changelog entry |
| `zentao_zhoubao/extension/custom/zhoubao/model.php` | `getBoardRows()`, `saveReport()`, `getProjectGantt()` (new), `getReportHistory()` (new) |
| `zentao_zhoubao/extension/custom/zhoubao/control.php` | `browse()`, `export()`, `copyLast()`, `edit()`, `view()`, `history()` (new) |
| `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php` | new strings |
| `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php` | risk filter dropdown |
| `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php` | risk radio, prev-plan block, gantt panel, history button |
| `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php` | risk badge, prev-plan block, gantt panel, history button |
| `zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php` | **new** — history comparison list |
| `zentao_zhoubao/extension/custom/zhoubao/js/zhoubao.js` | risk radio toggle/collect/copyLast |
| `zentao_zhoubao/extension/custom/zhoubao/css/common.css` | new classes for all 4 features |
| `zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php` | register `history` in `includedPriv` |
| `zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php` | register `zhoubao-history` priv |
| `zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php` | `history` priv label |

---

### Task 1: 数据库变更 + 版本号

**Files:**
- Modify: `zentao_zhoubao/db/install.sql`
- Create: `zentao_zhoubao/db/upgrade_hasrisk_column.sql`
- Modify: `zentao_zhoubao/plugin.json`
- Modify: `zentao_zhoubao/doc/zh-cn.yaml`

**Interfaces:**
- Produces: `zt_zhoubao.hasRisk` enum('yes','no') DEFAULT 'no' column, consumed by every later task's model/control code.

- [ ] **Step 1: 给 install.sql 加 hasRisk 列**

在 `risk` 列之后插入（保持列顺序：`nextPlan` → `risk` → `hasRisk` → `summary`）：

```diff
   `nextPlan` text,
   `risk` text,
+  `hasRisk` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '是否有风险/需协调资源',
   `summary` text,
   `snapshot` mediumtext,
```

完整替换 `zentao_zhoubao/db/install.sql` 第 8-11 行为：

```sql
  `nextPlan` text,
  `risk` text,
  `hasRisk` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '是否有风险/需协调资源',
  `summary` text,
  `snapshot` mediumtext,
```

- [ ] **Step 2: 新建升级脚本（仿 zentao_taizhang/db/upgrade_amount_columns.sql 写法）**

创建 `zentao_zhoubao/db/upgrade_hasrisk_column.sql`：

```sql
-- 升级: 为已部署的旧版 zt_zhoubao 补充 hasRisk 列(新装环境 install.sql 已包含, 无需执行)。
-- MariaDB 支持 IF NOT EXISTS; 若为原生 MySQL 报错, 去掉 IF NOT EXISTS 后单独执行。
ALTER TABLE `zt_zhoubao`
  ADD COLUMN IF NOT EXISTS `hasRisk` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '是否有风险/需协调资源' AFTER `risk`;

UPDATE `zt_zhoubao` SET `hasRisk` = 'yes' WHERE `risk` IS NOT NULL AND `risk` != '';
```

（此文件由部署脚本 `mysql < file.sql` 直接执行，不经过 ZenTao 的 `executeDB` 分号切分逻辑，注释里的 `;` 不受"install/uninstall SQL 注释不得含 `;`"约束——`install.sql`/`uninstall.sql` 才受约束，本文件不属于这两者。）

- [ ] **Step 3: 校验 install.sql / uninstall.sql 注释中没有裸分号**

Run:
```bash
grep -n -- '--.*;' zentao_zhoubao/db/install.sql zentao_zhoubao/db/uninstall.sql
```
Expected: 无输出（两个文件里没有任何 `--` 注释行包含 `;`）。

- [ ] **Step 4: plugin.json 版本号 1.0 → 1.1**

Modify `zentao_zhoubao/plugin.json` line 3：

```diff
-    "version": "1.0",
+    "version": "1.1",
```

- [ ] **Step 5: doc/zh-cn.yaml 加 1.1 changelog**

在 `zentao_zhoubao/doc/zh-cn.yaml` 的 `releases:` 下、`1.0:` 条目之前插入新版本块：

```diff
 releases:
+  1.1:
+    zentaoversion: 20.0+,biz10.0+,max5.0+,ipd2.0+
+    charge: free
+    changelog: 风险改为单选(是/否)+看板风险筛选；单项目周报页新增迭代/阶段甘特图；新增历史周报对比列表；编辑页展示上周计划。
+    date: 2026-07-02
   1.0:
     zentaoversion: 20.0+,biz10.0+,max5.0+,ipd2.0+
     charge: free
     changelog: 初始版本，项目周报填报、汇总看板提醒、企微定时推送。
     date: 2026-07-01
```

- [ ] **Step 6: Commit**

```bash
git add zentao_zhoubao/db/install.sql zentao_zhoubao/db/upgrade_hasrisk_column.sql zentao_zhoubao/plugin.json zentao_zhoubao/doc/zh-cn.yaml
git commit -m "feat(zhoubao): 数据库新增 hasRisk 列，版本号升至 1.1"
```

---

### Task 2: 功能一 — 风险单选 + 看板筛选

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php` (`getBoardRows()` ~L76-118, `saveReport()` ~L253-286)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php` (`browse()` ~L10-25, `copyLast()` ~L69-78, `export()` ~L115-140)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php` (~L90)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php` (~L93)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/js/zhoubao.js`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/css/common.css`

**Interfaces:**
- Consumes: `zt_zhoubao.hasRisk` column (Task 1).
- Produces: `model::getBoardRows($weekStart, $pm='', $fill='', $risk='')` returns rows with a `hasRisk` (`'yes'|'no'`) property; `model::saveReport()` persists `hasRisk`; `lang->zhoubao->hasRiskQuestion` / `hasRiskList` used by later tasks (history list in Task 5).

- [ ] **Step 1: model.php — getBoardRows() 加 risk 筛选**

Replace the whole method (currently lines ~76-118):

```php
    /* 看板行 */
    public function getBoardRows($weekStart, $pm = '', $fill = '')
    {
        /* 加载规则引擎（无禅道依赖，可独立 php -l 校验） */
        if(!class_exists('zhoubaoRules'))
        {
            include_once __DIR__ . '/lib/zhoubaoRules.php';
        }

        $range    = $this->getWeekRange($weekStart);
        $today    = date('Y-m-d');
        $projects = $this->getActiveProjects();
        if(empty($projects)) return array();

        $tasksByProject = $this->getProjectTasks(array_keys($projects));
        $reportMap      = $this->getReportMap($range['year'], $range['week']);
        $pmNames        = $this->getPmNames($projects);

        $rows = array();
        foreach($projects as $pid => $project)
        {
            if($pm !== '' && $pm !== 'all' && $project->PM !== $pm) continue;

            $tasks = isset($tasksByProject[$pid]) ? $tasksByProject[$pid] : array();
            $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

            $report = isset($reportMap[$pid]) ? $reportMap[$pid] : null;
            $status = $report ? $report->status : 'none';

            if($fill !== '' && $fill !== 'all' && $status !== $fill) continue;

            $rows[] = (object)array(
                'project'      => $pid,
                'projectName'  => $project->name,
                'pm'           => $project->PM,
                'pmName'       => isset($pmNames[$project->PM]) && $pmNames[$project->PM] !== '' ? $pmNames[$project->PM] : $project->PM,
                'status'       => $status,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
            );
        }
        return $rows;
    }
```

with:

```php
    /* 看板行 */
    public function getBoardRows($weekStart, $pm = '', $fill = '', $risk = '')
    {
        /* 加载规则引擎（无禅道依赖，可独立 php -l 校验） */
        if(!class_exists('zhoubaoRules'))
        {
            include_once __DIR__ . '/lib/zhoubaoRules.php';
        }

        $range    = $this->getWeekRange($weekStart);
        $today    = date('Y-m-d');
        $projects = $this->getActiveProjects();
        if(empty($projects)) return array();

        $tasksByProject = $this->getProjectTasks(array_keys($projects));
        $reportMap      = $this->getReportMap($range['year'], $range['week']);
        $pmNames        = $this->getPmNames($projects);

        $rows = array();
        foreach($projects as $pid => $project)
        {
            if($pm !== '' && $pm !== 'all' && $project->PM !== $pm) continue;

            $tasks = isset($tasksByProject[$pid]) ? $tasksByProject[$pid] : array();
            $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

            $report = isset($reportMap[$pid]) ? $reportMap[$pid] : null;
            $status = $report ? $report->status : 'none';

            if($fill !== '' && $fill !== 'all' && $status !== $fill) continue;

            /* 无周报的项目一律按"无风险"归类，供 risk 筛选使用 */
            $hasRisk = ($report && $report->hasRisk === 'yes') ? 'yes' : 'no';
            if($risk !== '' && $risk !== 'all' && $hasRisk !== $risk) continue;

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
        }
        return $rows;
    }
```

- [ ] **Step 2: model.php — saveReport() 处理 hasRisk**

Replace (currently lines ~253-263, just the top of the method body up through `$data->editedDate= $now;`):

```php
    /* 保存草稿或提交；提交时固化 snapshot */
    public function saveReport($project, $weekStart, $post, $account, $submit)
    {
        $range = $this->getWeekRange($weekStart);
        $now   = helper::now();
        $exist = $this->getReport($project, $weekStart);

        $data = new stdclass();
        $data->nextPlan  = isset($post['nextPlan']) ? $post['nextPlan'] : '';
        $data->risk      = isset($post['risk'])     ? $post['risk']     : '';
        $data->summary   = isset($post['summary'])  ? $post['summary']  : '';
        $data->editedDate= $now;
```

with:

```php
    /* 保存草稿或提交；提交时固化 snapshot */
    public function saveReport($project, $weekStart, $post, $account, $submit)
    {
        $range = $this->getWeekRange($weekStart);
        $now   = helper::now();
        $exist = $this->getReport($project, $weekStart);

        /* 白名单校验，非 'yes' 一律按 'no' 处理 */
        $hasRisk = (isset($post['hasRisk']) && $post['hasRisk'] === 'yes') ? 'yes' : 'no';

        $data = new stdclass();
        $data->nextPlan  = isset($post['nextPlan']) ? $post['nextPlan'] : '';
        $data->hasRisk   = $hasRisk;
        /* 选"否"时服务端强制清空风险文本，防止前端隐藏后仍提交的残留值存进数据库/snapshot */
        $data->risk      = ($hasRisk === 'yes' && isset($post['risk'])) ? $post['risk'] : '';
        $data->summary   = isset($post['summary'])  ? $post['summary']  : '';
        $data->editedDate= $now;
```

（其余方法体不变。）

- [ ] **Step 3: php -l 校验 model.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/model.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: control.php — browse() 加 risk 参数**

Replace (currently lines ~10-25):

```php
    public function browse($week = '', $pm = '', $fill = '')
    {
        $week = isset($_GET['week']) ? (string)$_GET['week'] : (string)$week;
        $pm   = isset($_GET['pm'])   ? (string)$_GET['pm']   : (string)$pm;
        $fill = isset($_GET['fill']) ? (string)$_GET['fill'] : (string)$fill;

        $weekStart = $this->zhoubao->resolveWeekStart($week); // Task 4 提供
        $rows      = $this->zhoubao->getBoardRows($weekStart, $pm, $fill); // Task 4 提供

        $this->view->title     = $this->lang->zhoubao->browseTitle;
        $this->view->weekStart = $weekStart;
        $this->view->rows      = $rows;
        $this->view->pm        = $pm;
        $this->view->fill      = $fill;
        $this->display();
    }
```

with:

```php
    public function browse($week = '', $pm = '', $fill = '', $risk = '')
    {
        $week = isset($_GET['week']) ? (string)$_GET['week'] : (string)$week;
        $pm   = isset($_GET['pm'])   ? (string)$_GET['pm']   : (string)$pm;
        $fill = isset($_GET['fill']) ? (string)$_GET['fill'] : (string)$fill;
        $risk = isset($_GET['risk']) ? (string)$_GET['risk'] : (string)$risk;

        $weekStart = $this->zhoubao->resolveWeekStart($week);
        $rows      = $this->zhoubao->getBoardRows($weekStart, $pm, $fill, $risk);

        $this->view->title     = $this->lang->zhoubao->browseTitle;
        $this->view->weekStart = $weekStart;
        $this->view->rows      = $rows;
        $this->view->pm        = $pm;
        $this->view->fill      = $fill;
        $this->view->risk      = $risk;
        $this->display();
    }
```

- [ ] **Step 5: control.php — copyLast() 回传 hasRisk**

Replace (currently lines ~69-78):

```php
    public function copyLast($project, $week = '')
    {
        $project   = (int)$project;
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        $prev = $this->zhoubao->getPrevReport($project, $weekStart);
        if(!$prev) return $this->send(array('result' => 'fail', 'message' => '上周暂无周报'));
        return $this->send(array('result' => 'success', 'data' => array(
            'nextPlan' => $prev->nextPlan, 'risk' => $prev->risk, 'summary' => $prev->summary,
        )));
    }
```

with:

```php
    public function copyLast($project, $week = '')
    {
        $project   = (int)$project;
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        $prev = $this->zhoubao->getPrevReport($project, $weekStart);
        if(!$prev) return $this->send(array('result' => 'fail', 'message' => '上周暂无周报'));
        return $this->send(array('result' => 'success', 'data' => array(
            'nextPlan' => $prev->nextPlan, 'risk' => $prev->risk, 'hasRisk' => $prev->hasRisk, 'summary' => $prev->summary,
        )));
    }
```

- [ ] **Step 6: control.php — export() 加 risk 参数 + CSV 列**

Replace (currently lines ~115-140):

```php
    public function export($type = 'board', $week = '', $id = 0)
    {
        $filename = 'zhoubao_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM，Excel 正确识别中文

        $out = fopen('php://output', 'w');
        if($type === 'one' && $id)
        {
            $r = $this->dao->select('*')->from('zt_zhoubao')->where('id')->eq((int)$id)->fetch();
            $p = $r ? $this->dao->select('name')->from(TABLE_PROJECT)->where('id')->eq($r->project)->fetch('name') : '';
            fputcsv($out, array('项目', '周次', '下周计划', '风险', '本周小结'));
            if($r) fputcsv($out, array($this->csvSafe($p), '第' . $r->week . '周', $this->csvSafe($r->nextPlan), $this->csvSafe($r->risk), $this->csvSafe($r->summary)));
        }
        else
        {
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '');
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '本周完成', '逾期任务'));
            foreach($rows as $r) fputcsv($out, array($this->csvSafe($r->projectName), $this->csvSafe($r->pmName), zget($labels, $r->status, $r->status), $r->doneCount, $r->overdueCount));
        }
        fclose($out);
        exit;
    }
```

with:

```php
    public function export($type = 'board', $week = '', $id = 0, $risk = '')
    {
        $filename = 'zhoubao_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM，Excel 正确识别中文

        $out = fopen('php://output', 'w');
        $hasRiskList = $this->lang->zhoubao->hasRiskList;
        if($type === 'one' && $id)
        {
            $r = $this->dao->select('*')->from('zt_zhoubao')->where('id')->eq((int)$id)->fetch();
            $p = $r ? $this->dao->select('name')->from(TABLE_PROJECT)->where('id')->eq($r->project)->fetch('name') : '';
            fputcsv($out, array('项目', '周次', '下周计划', '是否有风险', '风险', '本周小结'));
            if($r) fputcsv($out, array($this->csvSafe($p), '第' . $r->week . '周', $this->csvSafe($r->nextPlan), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $this->csvSafe($r->risk), $this->csvSafe($r->summary)));
        }
        else
        {
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '', $risk);
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务'));
            foreach($rows as $r) fputcsv($out, array($this->csvSafe($r->projectName), $this->csvSafe($r->pmName), zget($labels, $r->status, $r->status), zget($hasRiskList, $r->hasRisk, $r->hasRisk), $r->doneCount, $r->overdueCount));
        }
        fclose($out);
        exit;
    }
```

- [ ] **Step 7: php -l 校验 control.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/control.php`
Expected: `No syntax errors detected`

- [ ] **Step 8: lang/zh-cn.php — 新增文案**

在第 22 行 `$lang->zhoubao->risk = '风险与需协调资源';` 之后插入：

```php
$lang->zhoubao->hasRiskQuestion = '是否有风险及需协调资源';
$lang->zhoubao->hasRiskList     = array('yes' => '是', 'no' => '否');
```

- [ ] **Step 9: php -l 校验 lang/zh-cn.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
Expected: `No syntax errors detected`

- [ ] **Step 10: browse.html.php — 顶部变量 + risk 参数**

Replace (currently lines ~13-22):

```php
$weekStart = (string)$this->view->weekStart;
$rows      = $this->view->rows;
$pm        = (string)$this->view->pm;
$fill      = (string)$this->view->fill;

$statusList = $lang->zhoubao->statusList;

$range    = $weekStart . ' ~ ' . date('Y-m-d', strtotime($weekStart . ' +6 days'));
$pmParam   = ($pm   === '') ? 'all' : $pm;
$fillParam = ($fill === '') ? 'all' : $fill;
```

with:

```php
$weekStart = (string)$this->view->weekStart;
$rows      = $this->view->rows;
$pm        = (string)$this->view->pm;
$fill      = (string)$this->view->fill;
$risk      = (string)$this->view->risk;

$statusList  = $lang->zhoubao->statusList;
$hasRiskList = $lang->zhoubao->hasRiskList;

$range    = $weekStart . ' ~ ' . date('Y-m-d', strtotime($weekStart . ' +6 days'));
$pmParam   = ($pm   === '') ? 'all' : $pm;
$fillParam = ($fill === '') ? 'all' : $fill;
$riskParam = ($risk === '') ? 'all' : $risk;
```

- [ ] **Step 11: browse.html.php — 周切换链接带上 risk 参数**

Replace (currently lines ~24-31):

```php
$prevWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' -7 days')));
$nextWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' +7 days')));
$curWeek  = str_replace('-', '_', $weekStart);

$prevURL = helper::createLink('zhoubao', 'browse', "week={$prevWeek}&pm={$pmParam}&fill={$fillParam}");
$curURL  = helper::createLink('zhoubao', 'browse', "week=&pm={$pmParam}&fill={$fillParam}");
$nextURL = helper::createLink('zhoubao', 'browse', "week={$nextWeek}&pm={$pmParam}&fill={$fillParam}");
```

with:

```php
$prevWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' -7 days')));
$nextWeek = str_replace('-', '_', date('Y-m-d', strtotime($weekStart . ' +7 days')));
$curWeek  = str_replace('-', '_', $weekStart);

$prevURL = helper::createLink('zhoubao', 'browse', "week={$prevWeek}&pm={$pmParam}&fill={$fillParam}&risk={$riskParam}");
$curURL  = helper::createLink('zhoubao', 'browse', "week=&pm={$pmParam}&fill={$fillParam}&risk={$riskParam}");
$nextURL = helper::createLink('zhoubao', 'browse', "week={$nextWeek}&pm={$pmParam}&fill={$fillParam}&risk={$riskParam}");
```

- [ ] **Step 12: browse.html.php — 新增风险筛选下拉**

Replace (currently lines ~55-63, the fill-status `<select>` block):

```php
$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zhoubao->fillStatus) . '</label>';
$filterHTML .= '<select id="zbFilterFill" class="form-control" style="max-width:140px">';
$filterHTML .= '<option value="all"' . ($fill === '' || $fill === 'all' ? ' selected' : '') . '>全部</option>';
foreach($statusList as $key => $label)
{
    $sel = ($fill === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
}
$filterHTML .= '</select>';
```

with:

```php
$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zhoubao->fillStatus) . '</label>';
$filterHTML .= '<select id="zbFilterFill" class="form-control" style="max-width:140px">';
$filterHTML .= '<option value="all"' . ($fill === '' || $fill === 'all' ? ' selected' : '') . '>全部</option>';
foreach($statusList as $key => $label)
{
    $sel = ($fill === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
}
$filterHTML .= '</select>';

$filterHTML .= '<label style="margin-left:12px">' . htmlspecialchars($lang->zhoubao->hasRiskQuestion) . '</label>';
$filterHTML .= '<select id="zbFilterRisk" class="form-control" style="max-width:140px">';
$filterHTML .= '<option value="all"' . ($risk === '' || $risk === 'all' ? ' selected' : '') . '>全部</option>';
foreach($hasRiskList as $key => $label)
{
    $sel = ($risk === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
}
$filterHTML .= '</select>';
```

- [ ] **Step 13: browse.html.php — 导出链接带上 risk 参数**

Replace (currently line ~67):

```php
$filterHTML .= '<a class="btn btn-default btn-sm" href="' . helper::createLink('zhoubao', 'export', "type=board&week={$curWeek}") . '" style="margin-left:8px">' . htmlspecialchars($lang->zhoubao->export) . '</a>';
```

with:

```php
$filterHTML .= '<a class="btn btn-default btn-sm" href="' . helper::createLink('zhoubao', 'export', "type=board&week={$curWeek}&risk={$riskParam}") . '" style="margin-left:8px">' . htmlspecialchars($lang->zhoubao->export) . '</a>';
```

- [ ] **Step 14: browse.html.php — filterURL 模板 + pageJS 读取新下拉**

Replace (currently line ~134):

```php
jsVar('window.zhoubaoFilterURL', helper::createLink('zhoubao', 'browse', "week={$curWeek}&pm=__PM__&fill=__FILL__"));
```

with:

```php
jsVar('window.zhoubaoFilterURL', helper::createLink('zhoubao', 'browse', "week={$curWeek}&pm=__PM__&fill=__FILL__&risk=__RISK__"));
```

Replace (currently lines ~154-165, inside the `pageJS(<<<JS ... JS)` block):

```js
    window.zbSubmitFilter = function()
    {
        var pm   = document.getElementById('zbFilterPM').value;
        var fill = document.getElementById('zbFilterFill').value;
        var url  = window.zhoubaoFilterURL.replace('__PM__', encodeURIComponent(pm)).replace('__FILL__', encodeURIComponent(fill));
        window.location.href = url;
    };
    window.zbResetFilter = function()
    {
        var url = window.zhoubaoFilterURL.replace('__PM__', 'all').replace('__FILL__', 'all');
        window.location.href = url;
    };
```

with:

```js
    window.zbSubmitFilter = function()
    {
        var pm   = document.getElementById('zbFilterPM').value;
        var fill = document.getElementById('zbFilterFill').value;
        var risk = document.getElementById('zbFilterRisk').value;
        var url  = window.zhoubaoFilterURL.replace('__PM__', encodeURIComponent(pm)).replace('__FILL__', encodeURIComponent(fill)).replace('__RISK__', encodeURIComponent(risk));
        window.location.href = url;
    };
    window.zbResetFilter = function()
    {
        var url = window.zhoubaoFilterURL.replace('__PM__', 'all').replace('__FILL__', 'all').replace('__RISK__', 'all');
        window.location.href = url;
    };
```

- [ ] **Step 15: php -l 校验 browse.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 16: edit.html.php — 风险单选 + 条件文本框**

Replace (currently lines ~89-90):

```php
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->nextPlan) . '</label><textarea name="nextPlan" class="form-control" rows="4">' . $esc($report ? $report->nextPlan : '') . '</textarea></div>';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->risk) . '</label><textarea name="risk" class="form-control" rows="4">' . $esc($report ? $report->risk : '') . '</textarea></div>';
```

with:

```php
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->nextPlan) . '</label><textarea name="nextPlan" class="form-control" rows="4">' . $esc($report ? $report->nextPlan : '') . '</textarea></div>';

$hasRisk = ($report && $report->hasRisk === 'yes') ? 'yes' : 'no';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->hasRiskQuestion) . '</label>';
$pageHTML .= '<label class="zb-radio-inline"><input type="radio" name="hasRisk" value="yes"' . ($hasRisk === 'yes' ? ' checked' : '') . '> ' . $esc($lang->zhoubao->hasRiskList['yes']) . '</label>';
$pageHTML .= '<label class="zb-radio-inline"><input type="radio" name="hasRisk" value="no"' . ($hasRisk === 'no' ? ' checked' : '') . '> ' . $esc($lang->zhoubao->hasRiskList['no']) . '</label>';
$pageHTML .= '</div>';
$pageHTML .= '<div class="zb-form-group" id="zbRiskDetail" style="' . ($hasRisk === 'yes' ? '' : 'display:none') . '"><label>' . $esc($lang->zhoubao->risk) . '</label><textarea name="risk" class="form-control" rows="4">' . $esc($report ? $report->risk : '') . '</textarea></div>';
```

- [ ] **Step 17: php -l 校验 edit.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 18: view.html.php — 风险标签**

Replace (currently lines ~92-93):

```php
$pageHTML .= '<h3>' . $esc($lang->zhoubao->nextPlan) . '</h3><p>' . nl2br($esc($report->nextPlan)) . '</p>';
$pageHTML .= '<h3>' . $esc($lang->zhoubao->risk) . '</h3><p>' . nl2br($esc($report->risk)) . '</p>';
```

with:

```php
$pageHTML .= '<h3>' . $esc($lang->zhoubao->nextPlan) . '</h3><p>' . nl2br($esc($report->nextPlan)) . '</p>';

$hasRisk      = ($report->hasRisk === 'yes') ? 'yes' : 'no';
$riskBadgeCls = 'zb-risk-badge zb-risk-' . $hasRisk;
$riskBadgeTxt = $lang->zhoubao->hasRiskList[$hasRisk];
$pageHTML .= '<h3>' . $esc($lang->zhoubao->risk) . '<span class="' . $riskBadgeCls . '">' . $esc($riskBadgeTxt) . '</span></h3>';
if($hasRisk === 'yes') $pageHTML .= '<p>' . nl2br($esc($report->risk)) . '</p>';
```

- [ ] **Step 19: php -l 校验 view.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 20: zhoubao.js — collect() 读取 hasRisk，加 toggle 逻辑，copyLast 回填**

Replace the whole first IIFE (currently lines 6-50):

```js
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
```

with:

```js
(function(){
  if(window.__zhoubaoBound) return;
  window.__zhoubaoBound = true;

  function collect(){
    var form = document.getElementById('zbEditForm');
    var hasRiskEl = form.querySelector('input[name="hasRisk"]:checked');
    return {
      nextPlan: form.nextPlan.value,
      hasRisk:  hasRiskEl ? hasRiskEl.value : 'no',
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
  function toggleRiskDetail(){
    var form = document.getElementById('zbEditForm');
    if(!form) return;
    var checked = form.querySelector('input[name="hasRisk"]:checked');
    var detail = document.getElementById('zbRiskDetail');
    if(!detail) return;
    var isYes = checked && checked.value === 'yes';
    detail.style.display = isYes ? '' : 'none';
    if(!isYes) form.risk.value = '';
  }
  var riskRadios = document.querySelectorAll('input[name="hasRisk"]');
  for(var i = 0; i < riskRadios.length; i++) riskRadios[i].addEventListener('change', toggleRiskDetail);

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
        var wantYes = res.data.hasRisk === 'yes';
        var radio = form.querySelector('input[name="hasRisk"][value="' + (wantYes ? 'yes' : 'no') + '"]');
        if(radio){ radio.checked = true; toggleRiskDetail(); }
      } else alert(res.message || '上周暂无周报');
    });
  };
})();
```

- [ ] **Step 21: common.css — 新增单选/徽章样式**

Replace (currently the single line):

```css
.zb-form-group label{display:block;font-weight:600;margin-bottom:4px}
```

with:

```css
.zb-form-group label{display:block;font-weight:600;margin-bottom:4px}
.zb-radio-inline{display:inline-block;font-weight:400;margin:0 16px 0 0;cursor:pointer}
.zb-risk-badge{display:inline-block;font-size:12px;padding:1px 8px;border-radius:10px;color:#fff;margin-left:8px;vertical-align:middle}
.zb-risk-badge.zb-risk-yes{background:#e74c3c}
.zb-risk-badge.zb-risk-no{background:#95a5a6}
```

- [ ] **Step 22: 人工验证清单（无自动化测试可跑，记录待实例验证）**

- 部署到测试实例后：填报页单选切换"是/否"能显示/隐藏风险文本框；选"否"保存后数据库 `risk` 字段应为空。
- 看板筛选栏"是否有风险"下拉切全部/是/否，行数正确变化；导出 CSV 含"是否有风险"列。
- 复制上周内容按钮能正确回填上周的单选状态。

- [ ] **Step 23: Commit**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/model.php zentao_zhoubao/extension/custom/zhoubao/control.php zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php zentao_zhoubao/extension/custom/zhoubao/js/zhoubao.js zentao_zhoubao/extension/custom/zhoubao/css/common.css
git commit -m "feat(zhoubao): 风险改为单选(是/否)，看板筛选栏增加风险维度"
```

---

### Task 3: 功能四 — 编辑/查看页显示"上周计划"

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php` (`edit()` ~L32-62, `view()` ~L84-99)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php` (~L80)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php` (~L84)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/css/common.css`

**Interfaces:**
- Consumes: `model::getPrevReport($project, $weekStart)` (already exists, unchanged).
- Produces: `$this->view->prevReport` (object|null) available in both `edit.html.php` and `view.html.php`, reused by no later task.

- [ ] **Step 1: control.php — edit() 传 prevReport**

Replace (currently lines ~56-61, the tail of `edit()`):

```php
        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->display();
```

with:

```php
        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->view->prevReport  = $this->zhoubao->getPrevReport($project, $weekStart);
        $this->display();
```

- [ ] **Step 2: control.php — view() 传 prevReport**

Replace (currently lines ~93-98, the tail of `view()`):

```php
        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->view->canEdit     = $canEdit;
        $this->display();
```

with:

```php
        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->view->canEdit     = $canEdit;
        $this->view->prevReport  = $this->zhoubao->getPrevReport($report->project, $report->weekStart);
        $this->display();
```

- [ ] **Step 3: php -l 校验 control.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/control.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: lang/zh-cn.php — 新增"上周计划"文案**

在第 23 行 `$lang->zhoubao->summary = '本周总结';` 之后插入：

```php
$lang->zhoubao->prevPlan = '上周计划';
```

- [ ] **Step 5: edit.html.php — 本周总结上方插入上周计划区块**

Replace (currently lines ~80-81):

```php
$pageHTML .= '<form id="zbEditForm">';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->summary) . '</label><textarea name="summary" class="form-control" rows="3">' . $esc($report ? $report->summary : '') . '</textarea></div>';
```

with:

```php
$prevReport = $this->view->prevReport;
$pageHTML .= '<div class="zb-prev-plan"><h3>' . $esc($lang->zhoubao->prevPlan) . '</h3><p>' . ($prevReport ? nl2br($esc($prevReport->nextPlan)) : '上周暂无周报') . '</p></div>';

$pageHTML .= '<form id="zbEditForm">';
$pageHTML .= '<div class="zb-form-group"><label>' . $esc($lang->zhoubao->summary) . '</label><textarea name="summary" class="form-control" rows="3">' . $esc($report ? $report->summary : '') . '</textarea></div>';
```

- [ ] **Step 6: view.html.php — 本周总结上方插入上周计划区块**

Replace (currently line ~84):

```php
$pageHTML .= '<h3>' . $esc($lang->zhoubao->summary) . '</h3><p>' . nl2br($esc($report->summary)) . '</p>';
```

with:

```php
$prevReport = $this->view->prevReport;
$pageHTML .= '<div class="zb-prev-plan"><h3>' . $esc($lang->zhoubao->prevPlan) . '</h3><p>' . ($prevReport ? nl2br($esc($prevReport->nextPlan)) : '上周暂无周报') . '</p></div>';

$pageHTML .= '<h3>' . $esc($lang->zhoubao->summary) . '</h3><p>' . nl2br($esc($report->summary)) . '</p>';
```

- [ ] **Step 7: php -l 校验 edit.html.php / view.html.php**

Run:
```bash
php -l zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php
php -l zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php
```
Expected: both `No syntax errors detected`

- [ ] **Step 8: common.css — 上周计划区块样式**

Replace (currently the single line):

```css
.zb-view-actions{margin-bottom:12px}
```

with:

```css
.zb-view-actions{margin-bottom:12px}
.zb-prev-plan{background:#f7f9fc;border:1px solid #e3e8ee;border-radius:6px;padding:10px 14px;margin-bottom:16px}
.zb-prev-plan h3{margin:0 0 6px 0 !important}
```

- [ ] **Step 9: Commit**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/control.php zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php zentao_zhoubao/extension/custom/zhoubao/css/common.css
git commit -m "feat(zhoubao): 编辑/查看页在本周总结上方展示上周计划"
```

---

### Task 4: 功能二 — 项目甘特图区块

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php` (new `getProjectGantt()`, insert after `getPrevReport()`)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php` (`edit()`, `view()`, post-Task-3 state)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/css/common.css`

**Interfaces:**
- Consumes: `TABLE_PROJECT` (alias `TABLE_EXECUTION`) rows where `project` = current project id and `type` in `sprint,stage,kanban` — ZenTao stores iterations/stages as rows in `zt_project` itself (`TABLE_EXECUTION` is literally `` `zt_project` ``, confirmed via `zentaopms/config/zentaopms.php:383`).
- Produces: `model::getProjectGantt($project)` → `array('title' => string, 'items' => array(['id','name','type','status','start','end','color'], ...))`; `$this->view->gantt` in both `edit.html.php`/`view.html.php`; `window.zbGanttItems` / `window.zbGanttEmptyText` JS globals consumed only within this task's own inline `pageJS()`.

- [ ] **Step 1: model.php — 新增 getProjectGantt()**

Insert a new method between `getPrevReport()` and `saveReport()` — anchor on this exact boundary (currently unaffected by Task 2's edits, which only changed `saveReport()`'s body, not this docblock/signature line):

```php
    /* 取上一自然周同项目周报 */
    public function getPrevReport($project, $weekStart)
    {
        $prevStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
        return $this->getReport($project, $prevStart);
    }

    /* 保存草稿或提交；提交时固化 snapshot */
    public function saveReport($project, $weekStart, $post, $account, $submit)
```

Replace with:

```php
    /* 取上一自然周同项目周报 */
    public function getPrevReport($project, $weekStart)
    {
        $prevStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
        return $this->getReport($project, $prevStart);
    }

    /* 单项目迭代/阶段甘特图数据，用于周报页"项目态势"面板。
       TABLE_EXECUTION 就是 zt_project（迭代/阶段是 type=sprint/stage/kanban 的行，project 字段指向所属项目）。 */
    public function getProjectGantt($project)
    {
        $projectRow  = $this->dao->select('model, status, begin, end')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch();
        $modelLabels = array('waterfall' => '阶段甘特图', 'scrum' => '迭代甘特图', 'kanban' => '看板阶段');
        $modelKey    = $projectRow ? (string)$projectRow->model : '';
        $ganttTitle  = isset($modelLabels[$modelKey]) ? $modelLabels[$modelKey] : '项目进度甘特图';

        $executions = $this->dao->select('id, name, type, status, begin, end, realBegan, realEnd')
            ->from(TABLE_EXECUTION)
            ->where('project')->eq($project)
            ->andWhere('type')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->orderBy('begin_asc')
            ->fetchAll();

        $today = date('Y-m-d');
        $items = array();
        foreach($executions as $exec)
        {
            $start = (!empty($exec->realBegan) && $exec->realBegan !== '0000-00-00') ? $exec->realBegan : $exec->begin;
            $end   = (!empty($exec->realEnd)   && $exec->realEnd   !== '0000-00-00') ? $exec->realEnd   : $exec->end;
            if(empty($start) || $start === '0000-00-00') continue;
            if(empty($end) || $end === '0000-00-00') $end = date('Y-m-d', strtotime($start . ' +7 days'));

            $color = '#94a3b8';
            if($exec->status === 'doing')  $color = '#3b82f6';
            if($exec->status === 'closed') $color = '#10b981';
            if($end < $today && $exec->status !== 'closed') $color = '#ef4444';

            $items[] = array(
                'id'     => (int)$exec->id,
                'name'   => $exec->name,
                'type'   => $exec->type,
                'status' => $exec->status,
                'start'  => $start,
                'end'    => $end,
                'color'  => $color,
            );
        }

        return array('title' => $ganttTitle, 'items' => $items);
    }

    /* 保存草稿或提交；提交时固化 snapshot */
    public function saveReport($project, $weekStart, $post, $account, $submit)
```

- [ ] **Step 2: php -l 校验 model.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/model.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: control.php — edit()/view() 传 gantt 数据**

Replace (this is the **post-Task-3** state of the tail of `edit()`):

```php
        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->view->prevReport  = $this->zhoubao->getPrevReport($project, $weekStart);
        $this->display();
```

with:

```php
        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->view->prevReport  = $this->zhoubao->getPrevReport($project, $weekStart);
        $this->view->gantt       = $this->zhoubao->getProjectGantt($project);
        $this->display();
```

Replace (this is the **post-Task-3** state of the tail of `view()`):

```php
        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->view->canEdit     = $canEdit;
        $this->view->prevReport  = $this->zhoubao->getPrevReport($report->project, $report->weekStart);
        $this->display();
```

with:

```php
        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->view->canEdit     = $canEdit;
        $this->view->prevReport  = $this->zhoubao->getPrevReport($report->project, $report->weekStart);
        $this->view->gantt       = $this->zhoubao->getProjectGantt($report->project);
        $this->display();
```

- [ ] **Step 4: php -l 校验 control.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/control.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: lang/zh-cn.php — 空态文案**

在第 20 行 `$lang->zhoubao->statOverview= '进度/工时概览';` 之后插入：

```php
$lang->zhoubao->ganttEmpty = '该项目暂无迭代/阶段数据';
```

- [ ] **Step 6: edit.html.php — 插入甘特图容器（进度概览之后、走查提示之前）**

Replace (currently lines ~39-46, the stat-cards block — anchor is the exact unaffected `overdueCount` span + closing div):

```php
$pageHTML  = '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)$stat['progress'] . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc($stat['weekConsumed']) . '</span>';
$pageHTML .= '<span>剩余工时 ' . $esc($stat['totalLeft']) . '</span>';
$pageHTML .= '<span>完成 ' . (int)$stat['doneCount'] . '</span>';
$pageHTML .= '<span>逾期 ' . (int)$stat['overdueCount'] . '</span>';
$pageHTML .= '</div>';
```

with:

```php
$gantt = $this->view->gantt;

$pageHTML  = '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)$stat['progress'] . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc($stat['weekConsumed']) . '</span>';
$pageHTML .= '<span>剩余工时 ' . $esc($stat['totalLeft']) . '</span>';
$pageHTML .= '<span>完成 ' . (int)$stat['doneCount'] . '</span>';
$pageHTML .= '<span>逾期 ' . (int)$stat['overdueCount'] . '</span>';
$pageHTML .= '</div>';

$pageHTML .= '<h3>' . $esc($gantt['title']) . '</h3>';
$pageHTML .= '<div id="zbGanttChart" style="width:100%;height:280px"></div>';
```

- [ ] **Step 7: edit.html.php — 注入甘特图数据 + 渲染脚本**

Replace (currently lines ~103-105):

```php
jsVar('window.zbSaveURL', $saveURL);
jsVar('window.zbCopyURL', $copyURL);
if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));
```

with:

```php
jsVar('window.zbSaveURL', $saveURL);
jsVar('window.zbCopyURL', $copyURL);
if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));

jsVar('window.zbGanttItems', $gantt['items']);
jsVar('window.zbGanttEmptyText', $lang->zhoubao->ganttEmpty);
pageJS(<<<JS
(function()
{
    var el = document.getElementById('zbGanttChart');
    if(!el) return;
    var items = window.zbGanttItems || [];
    if(!items.length){ el.innerHTML = '<div class="zb-gantt-empty">' + (window.zbGanttEmptyText || '') + '</div>'; return; }
    if(el._chart) return;

    function loadEcharts(cb)
    {
        if(window.echarts){ cb(); return; }
        var src = (window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js';
        if(typeof $ != 'undefined' && $.getLib){ $.getLib({src: [src], root: false}, cb); }
        else { var s = document.createElement('script'); s.src = src; s.onload = cb; document.head.appendChild(s); }
    }

    loadEcharts(function()
    {
        if(!window.echarts || el._chart) return;
        var names = items.map(function(it){ return it.name; });
        var data  = items.map(function(it, idx){
            var s = (new Date(it.start)).getTime();
            var e = (new Date(it.end)).getTime();
            return {name: it.name, value: [idx, s, e, it.status], itemStyle: {color: it.color}};
        });
        var chart = echarts.init(el);
        el._chart = chart;
        var renderItem = function(params, api)
        {
            var y = api.coord([0, api.value(0)])[1];
            var start = api.coord([api.value(1), api.value(0)]);
            var end   = api.coord([api.value(2), api.value(0)]);
            var height = api.size([0, 1])[1] * 0.55;
            var rect = echarts.graphic.clipRectByRect(
                {x: start[0], y: y - height/2, width: Math.max(end[0]-start[0], 2), height: height},
                {x: params.coordSys.x, y: params.coordSys.y, width: params.coordSys.width, height: params.coordSys.height}
            );
            return rect && {type: 'rect', shape: rect, style: api.style()};
        };
        chart.setOption({
            tooltip: {formatter: function(p){
                var v = p.value;
                var fmt = function(ts){ var d = new Date(ts); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); };
                return '<b>' + p.name + '</b><br/>' + v[3] + '<br/>' + fmt(v[1]) + ' ~ ' + fmt(v[2]);
            }},
            grid: {left: 120, right: 30, top: 20, bottom: 40},
            xAxis: {type: 'time'},
            yAxis: {type: 'category', data: names, axisLabel: {interval: 0}},
            series: [{type: 'custom', renderItem: renderItem, encode: {x: [1,2], y: 0}, data: data}]
        });
        window.addEventListener('resize', function(){ chart.resize(); });
    });
})();
JS);
```

- [ ] **Step 8: php -l 校验 edit.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 9: view.html.php — 同样插入甘特图容器**

Replace (currently lines ~45-50, the stat-cards block):

```php
$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)(isset($stat['progress']) ? $stat['progress'] : 0) . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc(isset($stat['weekConsumed']) ? $stat['weekConsumed'] : 0) . '</span>';
$pageHTML .= '<span>完成 ' . (int)(isset($stat['doneCount']) ? $stat['doneCount'] : 0) . '</span>';
$pageHTML .= '<span>逾期 ' . (int)(isset($stat['overdueCount']) ? $stat['overdueCount'] : 0) . '</span>';
$pageHTML .= '</div>';
```

with:

```php
$gantt = $this->view->gantt;

$pageHTML .= '<div class="zb-stat-cards">';
$pageHTML .= '<span>进度 ' . (int)(isset($stat['progress']) ? $stat['progress'] : 0) . '%</span>';
$pageHTML .= '<span>本周工时 ' . $esc(isset($stat['weekConsumed']) ? $stat['weekConsumed'] : 0) . '</span>';
$pageHTML .= '<span>完成 ' . (int)(isset($stat['doneCount']) ? $stat['doneCount'] : 0) . '</span>';
$pageHTML .= '<span>逾期 ' . (int)(isset($stat['overdueCount']) ? $stat['overdueCount'] : 0) . '</span>';
$pageHTML .= '</div>';

$pageHTML .= '<h3>' . $esc($gantt['title']) . '</h3>';
$pageHTML .= '<div id="zbGanttChart" style="width:100%;height:280px"></div>';
```

- [ ] **Step 10: view.html.php — 注入甘特图数据 + 渲染脚本**

Replace (currently line ~100):

```php
if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));
```

with:

```php
if($hasZoucha) jsVar('window.zhoubaoZouchaDetailURL', helper::createLink('zoucha', 'detail', "projectID=__PID__&rule=__RULE__"));

jsVar('window.zbGanttItems', $gantt['items']);
jsVar('window.zbGanttEmptyText', $lang->zhoubao->ganttEmpty);
pageJS(<<<JS
(function()
{
    var el = document.getElementById('zbGanttChart');
    if(!el) return;
    var items = window.zbGanttItems || [];
    if(!items.length){ el.innerHTML = '<div class="zb-gantt-empty">' + (window.zbGanttEmptyText || '') + '</div>'; return; }
    if(el._chart) return;

    function loadEcharts(cb)
    {
        if(window.echarts){ cb(); return; }
        var src = (window.config && config.webRoot ? config.webRoot : '/') + 'js/echarts/echarts.common.min.js';
        if(typeof $ != 'undefined' && $.getLib){ $.getLib({src: [src], root: false}, cb); }
        else { var s = document.createElement('script'); s.src = src; s.onload = cb; document.head.appendChild(s); }
    }

    loadEcharts(function()
    {
        if(!window.echarts || el._chart) return;
        var names = items.map(function(it){ return it.name; });
        var data  = items.map(function(it, idx){
            var s = (new Date(it.start)).getTime();
            var e = (new Date(it.end)).getTime();
            return {name: it.name, value: [idx, s, e, it.status], itemStyle: {color: it.color}};
        });
        var chart = echarts.init(el);
        el._chart = chart;
        var renderItem = function(params, api)
        {
            var y = api.coord([0, api.value(0)])[1];
            var start = api.coord([api.value(1), api.value(0)]);
            var end   = api.coord([api.value(2), api.value(0)]);
            var height = api.size([0, 1])[1] * 0.55;
            var rect = echarts.graphic.clipRectByRect(
                {x: start[0], y: y - height/2, width: Math.max(end[0]-start[0], 2), height: height},
                {x: params.coordSys.x, y: params.coordSys.y, width: params.coordSys.width, height: params.coordSys.height}
            );
            return rect && {type: 'rect', shape: rect, style: api.style()};
        };
        chart.setOption({
            tooltip: {formatter: function(p){
                var v = p.value;
                var fmt = function(ts){ var d = new Date(ts); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); };
                return '<b>' + p.name + '</b><br/>' + v[3] + '<br/>' + fmt(v[1]) + ' ~ ' + fmt(v[2]);
            }},
            grid: {left: 120, right: 30, top: 20, bottom: 40},
            xAxis: {type: 'time'},
            yAxis: {type: 'category', data: names, axisLabel: {interval: 0}},
            series: [{type: 'custom', renderItem: renderItem, encode: {x: [1,2], y: 0}, data: data}]
        });
        window.addEventListener('resize', function(){ chart.resize(); });
    });
})();
JS);
```

- [ ] **Step 11: php -l 校验 view.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 12: common.css — 甘特图空态样式**

Replace (currently the single line):

```css
.zb-stat-cards span{padding:6px 12px;background:#f2f5fa;border-radius:6px;font-weight:600}
```

with:

```css
.zb-stat-cards span{padding:6px 12px;background:#f2f5fa;border-radius:6px;font-weight:600}
.zb-gantt-empty{padding:40px;text-align:center;color:#94a3b8}
```

- [ ] **Step 13: 人工验证清单**

- 有迭代/阶段数据的项目：面板标题按项目 `model` 字段正确切换（瀑布→"阶段甘特图" / scrum→"迭代甘特图" / kanban→"看板阶段" / 其他→"项目进度甘特图"），甘特条按状态着色、逾期未关闭显红。
- 无迭代/阶段数据的项目：显示"该项目暂无迭代/阶段数据"空态，不报 JS 错误。
- 用浏览器开发者工具确认走 SPA 内部导航（点"写周报"链接进入）时甘特图仍能渲染（验证 `pageJS()` 通道生效）。

- [ ] **Step 14: Commit**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/model.php zentao_zhoubao/extension/custom/zhoubao/control.php zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php zentao_zhoubao/extension/custom/zhoubao/css/common.css
git commit -m "feat(zhoubao): 单项目周报页新增迭代/阶段甘特图面板"
```

---

### Task 5: 功能三 — 历史周报对比列表

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php` (new `getReportHistory()`, append before class closing brace)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php` (new `history()` method, `edit()`/`view()` button)
- Modify: `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/css/common.css`
- Modify: `zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php`
- Modify: `zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php`
- Modify: `zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php`

**Interfaces:**
- Consumes: `zt_zhoubao` rows (all columns via `select('*')`), `TABLE_PROJECT` for project existence check.
- Produces: `control::history($project, $weeks='8')` (privilege-gated like `view()`), `model::getReportHistory($project, $weeks)` → array of `zt_zhoubao` row objects ordered `weekStart` desc, optionally `limit`-ed.

- [ ] **Step 1: model.php — 新增 getReportHistory()**

Insert a new method right before the final closing `}` of the class — anchor on the exact unaffected tail of `pushWecom()`:

```php
        if($err) return array('result' => 'fail', 'message' => '推送失败：' . $err);
        return array('result' => 'success', 'message' => '推送成功', 'resp' => $resp);
    }
}
```

Replace with:

```php
        if($err) return array('result' => 'fail', 'message' => '推送失败：' . $err);
        return array('result' => 'success', 'message' => '推送成功', 'resp' => $resp);
    }

    /* 单项目历史周报，按周倒序；weeks='all' 不限制条数，否则 limit 最近 N 条（含草稿，用于看计划连续性） */
    public function getReportHistory($project, $weeks)
    {
        $query = $this->dao->select('*')->from('zt_zhoubao')
            ->where('project')->eq($project)
            ->orderBy('weekStart_desc');
        if($weeks !== 'all') $query->limit((int)$weeks);
        return $query->fetchAll();
    }
}
```

- [ ] **Step 2: php -l 校验 model.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/model.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: control.php — 新增 history() 方法**

Insert a new method right after `export()`'s closing brace and before the `cronPush()` docblock — anchor on the exact unaffected `export()` tail (unaffected by Task 2's edits to the body, since `fclose/exit/}` are untouched):

```php
        fclose($out);
        exit;
    }

    /**
     * 企微定时推送入口，供 cron/系统 crontab curl 调用。token 校验。
```

Replace with:

```php
        fclose($out);
        exit;
    }

    /**
     * 单项目历史周报对比列表。
     * @param int    $project
     * @param string $weeks 4/8/12/all，非法值兜底 8
     */
    public function history($project, $weeks = '8')
    {
        $project = (int)$project;
        $weeks   = isset($_GET['weeks']) ? (string)$_GET['weeks'] : (string)$weeks;
        if(!in_array($weeks, array('4', '8', '12', 'all'), true)) $weeks = '8';

        $projectInfo = $this->dao->select('id, name, PM')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch();
        if(!$projectInfo) return print('项目不存在');

        $this->view->title       = $this->lang->zhoubao->historyTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weeks       = $weeks;
        $this->view->rows        = $this->zhoubao->getReportHistory($project, $weeks);
        $this->display();
    }

    /**
     * 企微定时推送入口，供 cron/系统 crontab curl 调用。token 校验。
```

- [ ] **Step 4: control.php — edit()/view() 加"查看历史周报"按钮**

`edit.html.php`/`view.html.php` need a `historyURL`; the button markup itself lives in the templates (Step 7/8), but `helper::createLink()` is already callable from the templates directly (as `edit.html.php`/`view.html.php` already do for `saveURL`/`copyURL`/`editURL`), so **no control.php change is needed for the button itself** — skip to Step 5. (No `$this->view->historyURL` is introduced; templates build the link inline exactly like they already do for `editURL` in `view.html.php`.)

- [ ] **Step 5: php -l 校验 control.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/control.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: lang/zh-cn.php — 新增历史列表文案**

在第 57 行 `$lang->zhoubao->export = '导出';` 之后插入：

```php
$lang->zhoubao->historyTitle    = '历史周报对比';
$lang->zhoubao->viewHistory     = '查看历史周报';
$lang->zhoubao->weeksFilterLabel= '时间范围';
$lang->zhoubao->weeksFilter     = array('4' => '最近4周', '8' => '最近8周', '12' => '最近12周', 'all' => '全部');
```

- [ ] **Step 7: php -l 校验 lang/zh-cn.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
Expected: `No syntax errors detected`

- [ ] **Step 8: 创建 ui/history.html.php**

Create `zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php`:

```php
<?php
declare(strict_types=1);
/**
 * 项目周报 — 单项目历史周报对比 ZIN 模板
 *
 * 按周倒序列出该项目历次周报（含草稿），用于对比"下周计划"与后续"本周总结"的连续性。
 * 完成数/逾期数只有已提交周报（有 snapshot）才有值，草稿显示 "-"（自动区数据只在提交时固化）。
 * 沿用 browse.html.php 的手写表格约定，不使用 dtable()。
 */
namespace zin;

$projectInfo = $this->view->projectInfo;
$weeks       = (string)$this->view->weeks;
$rows        = $this->view->rows;

$statusList  = $lang->zhoubao->statusList;
$hasRiskList = $lang->zhoubao->hasRiskList;
$weeksFilter = $lang->zhoubao->weeksFilter;

$esc = function($v){ return htmlspecialchars((string)$v); };

/* ── 顶部工具栏：N 周下拉，切换即跳转 ── */
$toolbarHTML  = '<div class="zb-hist-toolbar">';
$toolbarHTML .= '<label>' . $esc($lang->zhoubao->weeksFilterLabel) . '</label>';
$toolbarHTML .= '<select id="zbHistWeeks" class="form-control" style="max-width:140px">';
foreach($weeksFilter as $key => $label)
{
    $sel = ($weeks === $key) ? ' selected' : '';
    $toolbarHTML .= '<option value="' . $esc($key) . '"' . $sel . '>' . $esc($label) . '</option>';
}
$toolbarHTML .= '</select>';
$toolbarHTML .= '</div>';

/* ── 数据表 ── */
$tableHTML  = '<div class="zb-table-wrap"><table class="zb-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:110px">' . $esc($lang->zhoubao->week) . '</th>';
$tableHTML .= '<th style="width:90px">' . $esc($lang->zhoubao->fillStatus) . '</th>';
$tableHTML .= '<th>' . $esc($lang->zhoubao->summary) . '</th>';
$tableHTML .= '<th>' . $esc($lang->zhoubao->nextPlan) . '</th>';
$tableHTML .= '<th style="width:90px">' . $esc($lang->zhoubao->hasRiskQuestion) . '</th>';
$tableHTML .= '<th style="width:80px">' . $esc($lang->zhoubao->doneCount) . '</th>';
$tableHTML .= '<th style="width:80px">' . $esc($lang->zhoubao->overdueCount) . '</th>';
$tableHTML .= '</tr></thead><tbody>';

if(empty($rows))
{
    $tableHTML .= '<tr><td colspan="7" class="zb-empty">该项目暂无历史周报。</td></tr>';
}
else
{
    foreach($rows as $r)
    {
        $weekLabel = $r->year . '年第' . $r->week . '周';
        $statusLabel = zget($statusList, $r->status, $r->status);
        $hasRisk = ($r->hasRisk === 'yes') ? 'yes' : 'no';
        $riskLabel = $hasRiskList[$hasRisk];

        $stat = array();
        if($r->snapshot)
        {
            $snap = json_decode($r->snapshot, true);
            if(isset($snap['stat'])) $stat = $snap['stat'];
        }
        $doneText    = isset($stat['doneCount'])    ? (string)(int)$stat['doneCount']    : '-';
        $overdueText = isset($stat['overdueCount']) ? (string)(int)$stat['overdueCount'] : '-';

        $tableHTML .= '<tr>';
        $tableHTML .= '<td>' . $esc($weekLabel) . '</td>';
        $tableHTML .= '<td class="td-center">' . $esc($statusLabel) . '</td>';
        $tableHTML .= '<td class="zb-hist-cell" title="' . $esc($r->summary) . '">' . $esc($r->summary) . '</td>';
        $tableHTML .= '<td class="zb-hist-cell" title="' . $esc($r->nextPlan) . '">' . $esc($r->nextPlan) . '</td>';
        $tableHTML .= '<td class="td-center"><span class="zb-risk-badge zb-risk-' . $hasRisk . '">' . $esc($riskLabel) . '</span></td>';
        $tableHTML .= '<td class="td-center">' . $esc($doneText) . '</td>';
        $tableHTML .= '<td class="td-center">' . $esc($overdueText) . '</td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody></table></div>';

panel(
    set::title($this->view->title . ' · ' . $esc($projectInfo->name)),
    html($toolbarHTML . $tableHTML)
);

$cssPath = $app->getAppRoot() . 'extension/custom/zhoubao/css/common.css';
if(is_file($cssPath)) css(file_get_contents($cssPath));

jsVar('window.zhoubaoHistoryURL', helper::createLink('zhoubao', 'history', "project={$projectInfo->id}&weeks=__WEEKS__"));

pageJS(<<<JS
(function()
{
    if(window.zbHistBound) return;
    window.zbHistBound = true;
    var sel = document.getElementById('zbHistWeeks');
    if(sel) sel.addEventListener('change', function(){
        window.location.href = window.zhoubaoHistoryURL.replace('__WEEKS__', encodeURIComponent(sel.value));
    });
})();
JS);
```

- [ ] **Step 9: php -l 校验 history.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 10: edit.html.php — "查看历史周报"按钮**

Replace (currently the very first line of the `$pageHTML` build, lines ~39-40 pre-Task-4 — this is the same anchor Task 4 used for its OWN insertion further down; this step's anchor is only the first assignment line, distinct from Task 4's `overdueCount`/closing-div anchor, so both edits land independently regardless of order):

```php
$pageHTML  = '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
```

with:

```php
$historyURL = helper::createLink('zhoubao', 'history', "project={$project->id}");
$pageHTML  = '<div class="zb-view-actions"><a class="btn btn-default btn-sm" href="' . $historyURL . '" target="_blank">' . $esc($lang->zhoubao->viewHistory) . '</a></div>';
$pageHTML .= '<h3>' . $esc($lang->zhoubao->statOverview) . '</h3>';
```

- [ ] **Step 11: php -l 校验 edit.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 12: view.html.php — "查看历史周报"按钮（与已有编辑按钮合并到同一行）**

Replace (currently lines ~40-44):

```php
$pageHTML = '';
if($canEdit)
{
    $pageHTML .= '<div class="zb-view-actions"><a class="btn btn-primary btn-sm" href="' . $editURL . '">' . $esc($lang->zhoubao->editReport) . '</a></div>';
}
```

with:

```php
$historyURL = helper::createLink('zhoubao', 'history', "project={$project->id}");
$pageHTML  = '<div class="zb-view-actions"><a class="btn btn-default btn-sm" href="' . $historyURL . '" target="_blank">' . $esc($lang->zhoubao->viewHistory) . '</a>';
if($canEdit)
{
    $pageHTML .= ' <a class="btn btn-primary btn-sm" href="' . $editURL . '">' . $esc($lang->zhoubao->editReport) . '</a>';
}
$pageHTML .= '</div>';
```

- [ ] **Step 13: php -l 校验 view.html.php**

Run: `php -l zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`
Expected: `No syntax errors detected`

- [ ] **Step 14: common.css — 历史列表样式**

Replace (currently the single line):

```css
.zb-table-wrap{overflow-x:auto}
```

with:

```css
.zb-table-wrap{overflow-x:auto}
.zb-hist-toolbar{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.zb-hist-cell{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
```

- [ ] **Step 15: 权限注册 — includedPriv**

Replace `zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php` line 6:

```diff
-$config->zhoubao->includedPriv['zhoubao'] = array('browse', 'edit', 'view', 'export', 'copyLast', 'manage');
+$config->zhoubao->includedPriv['zhoubao'] = array('browse', 'edit', 'view', 'export', 'copyLast', 'manage', 'history');
```

- [ ] **Step 16: 权限注册 — group package**

Replace `zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php` (currently lines 2-13):

```php
<?php
if(isset($config->group->package))
{
    $config->group->package->zhoubaoother = new stdclass();
    $config->group->package->zhoubaoother->subset = 'zhoubao';
    $config->group->package->zhoubaoother->privs  = array();
    $config->group->package->zhoubaoother->privs['zhoubao-browse']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-edit']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-view']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 7,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-export']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 8,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-copyLast'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 9,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-manage']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 11, 'depend' => array());
}
```

with:

```php
<?php
if(isset($config->group->package))
{
    $config->group->package->zhoubaoother = new stdclass();
    $config->group->package->zhoubaoother->subset = 'zhoubao';
    $config->group->package->zhoubaoother->privs  = array();
    $config->group->package->zhoubaoother->privs['zhoubao-browse']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-edit']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-view']     = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 7,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-export']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 8,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-copyLast'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 9,  'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-history']  = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 10, 'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-manage']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 11, 'depend' => array());
}
```

- [ ] **Step 17: 权限注册 — 资源标签**

Replace `zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php` (currently lines 2-9):

```php
<?php
/* 将周报作为独立的视图权限暴露出来 */
$lang->resource->zhoubao = new stdclass();
$lang->resource->zhoubao->browse   = '浏览周报看板';
$lang->resource->zhoubao->edit     = '填写/编辑周报';
$lang->resource->zhoubao->view     = '查看周报';
$lang->resource->zhoubao->export   = '导出周报';
$lang->resource->zhoubao->copyLast = '复制上周手写内容';
$lang->resource->zhoubao->manage   = '周报管理（全局/推送）';
```

with:

```php
<?php
/* 将周报作为独立的视图权限暴露出来 */
$lang->resource->zhoubao = new stdclass();
$lang->resource->zhoubao->browse   = '浏览周报看板';
$lang->resource->zhoubao->edit     = '填写/编辑周报';
$lang->resource->zhoubao->view     = '查看周报';
$lang->resource->zhoubao->export   = '导出周报';
$lang->resource->zhoubao->copyLast = '复制上周手写内容';
$lang->resource->zhoubao->history  = '查看历史周报对比';
$lang->resource->zhoubao->manage   = '周报管理（全局/推送）';
```

- [ ] **Step 18: php -l 校验所有本任务改动过的 PHP 配置/语言文件**

Run:
```bash
php -l zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php
php -l zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php
php -l zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php
```
Expected: all three `No syntax errors detected`

- [ ] **Step 19: 人工验证清单**

- 部署后，已有 zhoubao 权限的用户组能看到"查看历史周报"按钮并成功打开列表（无权限用户应被 `checkPriv()` 拦截，与 `view` 一致）。
- 切换"最近4/8/12周/全部"下拉，列表条数与排序（按周倒序）正确；无历史周报的新项目显示空态。
- 草稿周报（未提交，无 snapshot）在完成数/逾期数列显示"-"，不报错、不显示 0（区分"未计算"与"计算结果为0"）。

- [ ] **Step 20: Commit**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/model.php zentao_zhoubao/extension/custom/zhoubao/control.php zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php zentao_zhoubao/extension/custom/zhoubao/css/common.css zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php
git commit -m "feat(zhoubao): 新增单项目历史周报对比列表"
```

---

### Task 6: 收尾回归

**Files:** none created/modified (verification only)

**Interfaces:**
- Consumes: every file touched in Tasks 1-5.

- [ ] **Step 1: 对全部改动过的 PHP 文件跑一遍 php -l**

Run:
```bash
for f in \
  zentao_zhoubao/extension/custom/zhoubao/model.php \
  zentao_zhoubao/extension/custom/zhoubao/control.php \
  zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php \
  zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php \
  zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php \
  zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php \
  zentao_zhoubao/extension/custom/zhoubao/ui/history.html.php \
  zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php \
  zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php \
  zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php \
; do php -l "$f" || exit 1; done
```
Expected: `No syntax errors detected` for every file, exit code 0.

- [ ] **Step 2: 跑现有规则引擎回归测试（确认未被误伤）**

Run: `php zentao_zhoubao/tests/test_rules.php`
Expected: every line starts with `PASS:` (this test only covers `lib/zhoubaoRules.php`, untouched by this plan — a `FAIL:` here means an unrelated regression, stop and investigate).

- [ ] **Step 3: 重新核对 install.sql / uninstall.sql 注释无裸分号（收尾复查）**

Run:
```bash
grep -n -- '--.*;' zentao_zhoubao/db/install.sql zentao_zhoubao/db/uninstall.sql
```
Expected: 无输出。

- [ ] **Step 4: 部署到测试实例并跑完整人工验证清单**

按 CLAUDE.md 的手动部署流程（复制 `extension/*`、`config/ext/*.php`，导入 `db/install.sql`/新装环境或运行 `db/upgrade_hasrisk_column.sql`/已部署环境，然后清 `tmp/cache/*` 与 `tmp/model/*`），依次验证：

1. Task 2 清单（风险单选/筛选/CSV/复制上周）
2. Task 3（上周计划在本周总结上方正确展示，含无上周周报兜底文案）
3. Task 4 清单（甘特图渲染、空态、SPA 内部导航）
4. Task 5 清单（历史列表权限、周数切换、草稿"-"展示）

- [ ] **Step 5: 更新计划文档状态（可选，若使用 executing-plans 追踪）**

无代码改动，仅确认以上 4 步全部通过后关闭本轮工作。
