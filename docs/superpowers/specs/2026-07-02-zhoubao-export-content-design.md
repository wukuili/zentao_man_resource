# zhoubao 看板批量导出补充周报正文 — 设计

## 背景

`zentao_zhoubao` 插件的 CSV 导出（`control.php::export()`）有两种类型：

- `type=one`：单份周报导出，已包含正文字段（下周计划、风险、本周小结）。
- `type=board`：当周看板批量导出，目前只有统计列（项目、项目经理、填报状态、是否有风险、本周完成数、逾期任务数），**没有周报正文**，用户需要的内容还得逐个点开周报详情页看，批量导出失去了意义。

## 目标

`type=board` 导出在现有统计列基础上，追加周报正文三个字段：下周计划、风险说明、本周总结。字段口径与 `type=one` 导出一致；未提交周报的项目留空。

## 方案

### 1. `model.php` · `getBoardRows()`

方法内部已经通过 `getReportMap()`（`select('*')`）查出每个项目当周的周报记录 `$report`，只是构造返回行对象时没有把正文字段带出来。在现有 `(object)array(...)` 中追加：

```php
'nextPlan' => $report ? $report->nextPlan : '',
'risk'     => $report ? $report->risk     : '',
'summary'  => $report ? $report->summary  : '',
```

不新增查询，只是把已经取到的数据透出。

### 2. `control.php` · `export()` 的 `board` 分支

表头从：

```php
fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务'));
```

扩展为：

```php
fputcsv($out, array('项目', '项目经理', '填报状态', '是否有风险', '本周完成', '逾期任务', '下周计划', '风险', '本周总结'));
```

数据行在追加的三列上复用已有的 `csvSafe()`（CSV 公式注入防护）：

```php
fputcsv($out, array(
    $this->csvSafe($r->projectName), $this->csvSafe($r->pmName),
    zget($labels, $r->status, $r->status), zget($hasRiskList, $r->hasRisk, $r->hasRisk),
    $r->doneCount, $r->overdueCount,
    $this->csvSafe($r->nextPlan), $this->csvSafe($r->risk), $this->csvSafe($r->summary)
));
```

## 不改动的范围

- `type=one` 单份周报导出：已包含正文，不动。
- `browse.html.php` 看板列表页表格：仍只显示统计列，不在页面上展示正文（导出与页面展示解耦，避免表格过宽）。
- 不新增数据库查询、不改 `getReportMap()` 的 select 字段。

## 测试

- 无自动化测试覆盖 `control.php`/`model.php`（zhoubao 插件目前只有 `tests/test_rules.php` 覆盖纯规则引擎，导出逻辑依赖禅道运行时）。手工验证：部署后在看板页点"导出"，用 Excel/文本编辑器打开 CSV，确认新增三列存在且未提交周报的项目对应单元格为空，含逗号/换行/公式前缀（`=`/`+`/`-`/`@`）的正文能正确转义。
