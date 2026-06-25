# Task 3 Report: 模型批量取数并组装走查结果

## 实现内容

替换了占位 `model.php`，实现两个方法：

### `inspect()`

主走查入口，执行以下步骤：

1. **加载规则引擎**：`class_exists` 守卫 + `include_once __DIR__ . '/lib/zouchaRules.php'`
2. **读取配置阈值**：从 `$this->config->zoucha` 取 `staleDays / longTaskDays / overdueMin / rules`，配置缺失时回退到默认值
3. **批量取在研项目**：`TABLE_PROJECT` where `type='project'`, `deleted='0'`, `status != 'closed'`，无项目直接返回空数组
4. **取执行列表**：`TABLE_EXECUTION`（= `zt_project`）where `project IN (...)`, `type IN ('sprint','stage','kanban')`, `deleted='0'`
   - 构建 `closedExecutionIDs`（已关闭执行 ID 集合）
   - 构建 `execCountByProject`（未关闭执行计数，传入 `evaluate()` 的 `executionCount` 参数）
5. **取任务**：`TABLE_TASK` where `project IN (...)`, `deleted='0'`
   - 过滤：`execution != 0 && isset(closedExecutionIDs[execution])` 的任务跳过；`execution=0` 任务保留
   - 按 `project` 分组为 `tasksByProject`
6. **取 PM 真实姓名**：`TABLE_USER` where `account IN (pm_accounts)`，批量一次查询
7. **取项目集名称**：从所有项目的 `path` 字段解析祖先 ID → `TABLE_PROJECT` where `id IN (...)`, `type='program'`, `deleted='0'`，批量一次查询
8. **逐项目调用 `zouchaRules::evaluate()`**：7 参数与 Task 2 签名完全吻合（positional order: tasks, executionCount, today, staleDays, longTaskDays, overdueMin, enabledRules）
9. **组装结果行**：仅收录 `hits` 非空的项目，返回字段集合包含：
   - 原始字段：`projectID, projectName, pm, status, path, hits, taskCount, overdueCount, executionCount, lastTaskEdited`
   - 富化字段：`pmName, statusName, programName`

### `getInspectData($projectID)`

诊断方法，取单项目的原始数据：
- 返回 `['project'=>obj|null, 'executions'=>[], 'tasks'=>[], 'evaluated'=>[]]`
- 同样过滤已关闭执行下的任务
- 供 Task 4 诊断端点调用

## 验证结果

### php -l 语法检查
```
No syntax errors detected in .../extension/custom/zoucha/model.php
```

### 规则回归测试（tests/test_rules.php）
20 个用例全部通过，末行输出 `ALL PASS`（未修改 zouchaRules.php，引擎契约完整保留）

## 修改的文件

- `zentao_zoucha/extension/custom/zoucha/model.php`（完全替换占位实现）

## 自查结论

| 检查项 | 结论 |
|---|---|
| `evaluate()` 7 参数顺序与 Task 2 签名一致 | 是 |
| 结果行字段名与任务描述完全匹配（10 原始 + 3 富化） | 是 |
| 已关闭执行下的任务被过滤，execution=0 任务保留 | 是 |
| 所有 DB 操作均通过 DAO，无原始 SQL 拼接 | 是 |
| `TABLE_EXECUTION` 用于执行查询（与 `TABLE_PROJECT` 等价，均为 zt_project） | 是 |
| `type IN ('sprint','stage','kanban')` 区分执行与项目/项目集 | 是 |

## 无法在本地验证的事项（无禅道实例）

- `TABLE_PROJECT / TABLE_EXECUTION / TABLE_TASK / TABLE_USER` 常量在运行时是否已定义（依赖禅道框架启动）
- DAO `->andWhere('type')->in('sprint,stage,kanban')` 与 DAO 的 `in()` 接受逗号分隔字符串的兼容性（参考 man_resource 中 `->where('root')->in($ids)` 用法，该形式在 ZenTao DAO 中合法）
- `$this->lang->zoucha->projectStatusList` 运行时是否已加载（已做 `isset` 兜底硬编码）
- 大项目批量任务查询的内存消耗（已用 `IN` 一次性批量，未做分批）——留待 Task 6 压测
