# 禅道走查插件（zentao_zoucha）设计文档

- 日期：2026-06-25
- 代号：`zoucha`
- 类型：禅道 20+ 扩展插件（与 `zentao_taizhang` 同构）

## 一、目标与定位

提供一个**项目健康度体检（走查）**列表页：扫描所有进行中的项目，对每个项目运行一组规则，把命中任意规则的项目筛选出来，一行一个，用彩色标签标出命中的问题类型。顶部可按问题类型筛选，支持导出。

帮助项目管理者一屏发现“失管”项目——没建任务、长期没动静、任务延期、没建迭代、任务排期过长。

## 二、走查规则

判定均在**项目维度**进行，统计时**排除已删除任务**与**已关闭执行下的任务**（复用 man_resource 中已有的“已关闭项目/执行 ID 过滤”逻辑）。

| # | 规则键 | 标签 | 判定口径 |
|---|---|---|---|
| R1 | `noTask` | 无任务 | 项目下（未关闭执行内、未删除）任务总数 = 0 |
| R2 | `stale` | 近一周未更新 | 项目**有**任务，但全部**未关闭状态**任务的 `lastEditedDate` 均早于 N 天前（默认 N=7） |
| R3 | `overdue` | 任务延期 | 存在 `deadline` < 今天 且状态非 `done`/`closed`/`cancel` 的任务，逾期任务数 ≥ `overdueMin`（默认 1）。标签附带逾期任务数。 |
| R4 | `noExecution` | 无迭代 | 项目下没有任何执行（迭代/阶段，type ∈ sprint/stage/kanban，未删除）。等同“没有里程碑”。 |
| R5 | `longTask` | 任务超期(>2周) | 存在任务满足 `deadline − estStarted > M 天`（默认 M=14，两端日期均有效） |

判定关系说明：
- R1 命中时，R2/R3/R5 因无任务自然不命中。
- R4 与任务无关，独立判定，可与其它规则同时命中。
- R2 仅在项目“有任务”时才可能命中（无任务归 R1）。
- R2 的“未关闭状态任务”指状态非 `closed`/`cancel`；用于避免历史归档任务把整项目误判为活跃。

## 三、配置（`extension/custom/zoucha/config.php`，管理员可改文件调整）

```php
$config->zoucha = new stdclass();
$config->zoucha->staleDays    = 7;   // R2 阈值：近一周未更新
$config->zoucha->longTaskDays = 14;  // R5 阈值：任务持续时间超 2 周
$config->zoucha->overdueMin   = 1;   // R3 命中所需最少逾期任务数
// 启用哪些规则（按此顺序展示）
$config->zoucha->rules = array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');
```

阈值与启用规则均不在页面上调整，由管理员改配置文件后生效（YAGNI：先不做运行时可调）。

## 四、数据流（实时查询，无新建业务表）

走查是“即时视图”，不落库快照。每次访问页面实时计算。

1. `model->getOpenProjects()`：取 `type='project'`、`status` ∉ {`closed`}、`deleted='0'` 的项目，附带项目集名、PM、status。
2. `model->getClosedExecutionIDs()`：复用 man_resource 模式，取已关闭/已删除执行 ID 集合，用于过滤任务。
3. 批量查询：
   - 这些项目下的全部执行（type ∈ sprint/stage/kanban，未删除）→ 用于 R4，并收集“未关闭执行 ID”。
   - 这些项目下、属于未关闭执行、未删除的任务（字段：`project, execution, status, estStarted, deadline, lastEditedDate`）。
4. `model->inspect()`：在 PHP 内按 `project` 分组，逐项目跑 5 条规则，产出每项目结果对象：
   ```
   { projectID, projectName, program, PM, status,
     hits: ['noTask','overdue',...],   // 命中的规则键
     taskCount, overdueCount, executionCount, lastTaskEdited }
   ```
   仅保留 `hits` 非空的项目。
5. `control->browse($rule, $recTotal, $recPerPage, $pageID)`：接收问题类型筛选 `rule` 与分页参数（按 PATH_INFO 位置参数声明形参），交给 ZIN `dtable` 渲染；导出走 `browse` 的导出分支或独立 `export` 方法。

性能：项目/执行/任务各一次批量查询 + PHP 内分组，避免 N+1。

## 五、文件结构（遵循 taizhang 约定）

```
zentao_zoucha/
├── doc/zh-cn.yaml                          # 清单 name=项目走查 code=zoucha
├── plugin.json                             # 插件元信息
├── db/
│   ├── install.sql                         # 仅权限/菜单相关（无业务表）
│   └── uninstall.sql                       # 卸载清理
├── config/ext/zoucha.php                   # openMethods 等路由/过滤
├── extension/custom/
│   ├── common/ext/config/zoucha.php        # apps / appsMenu / includedPriv 注册
│   ├── common/ext/lang/zh-cn/zoucha.php     # 导航菜单接入（顶级菜单）
│   ├── group/ext/config/zoucha.php          # 权限包登记
│   ├── group/ext/lang/zh-cn/zoucha.php      # 视图级权限资源
│   └── zoucha/
│       ├── config.php                       # 走查阈值/规则/标签颜色常量
│       ├── control.php                      # browse / export / diagnostic
│       ├── model.php                        # 取数 + 规则判定
│       ├── lang/zh-cn.php                    # 文案 + 权限资源定义
│       ├── css/zoucha.css
│       ├── js/zoucha.js
│       └── ui/browse.html.php                # ZIN dtable 列表页
```

## 六、页面（`zoucha-browse`）

- 顶部 featureBar：问题类型筛选（全部 / 无任务 / 近一周未更新 / 任务延期 / 无迭代 / 任务超期）+ 导出按钮。
- dtable 列：
  - 项目名（链接到禅道项目）
  - 所属项目集
  - 负责人（PM）
  - 项目状态
  - **走查结果**（彩色标签，可多个；延期标签带逾期任务数）
  - 任务数
  - 逾期数
  - 执行数
  - 最后任务更新时间
- 高亮：命中规则数较多的项目行用红色/警示色提示。
- 日期类 URL 参数遵循禅道 `_` 替换约定（如有）；分页参数声明为控制器形参（PATH_INFO 位置参数）。

## 七、权限与导航

- 顶级菜单注册：菜单项 `zoucha` → 默认方法 `zoucha-browse`。
- 权限资源登记到 group 模块；`browse`（及 `export`）方法纳入 `includedPriv`，管理员直接放行；非管理员依赖权限包授权。
- 生 XHR（如导出/AJAX）须带 `X-Requested-With` 头，避免 302 跳应用壳空白页（参考既有约定）。

## 八、测试

无自动化测试套件。装到运行中的禅道 20+ 实例，逐条规则人工验证：
- 造一个无任务项目（R1）、一个全部任务一周没动的项目（R2）、含逾期任务项目（R3）、无执行项目（R4）、含跨度 >14 天任务项目（R5）。
- 提供 `diagnostic` 方法打印指定项目的逐规则判定明细，便于排查（如 `zoucha-diagnostic?projectID=xxx`）。

## 九、非目标（YAGNI）

- 不做走查结果落库/历史趋势。
- 不做页面上运行时调阈值。
- 不做执行/迭代维度的走查（仅顶级项目维度）。
- 不做 REST API（先满足页面诉求）。
