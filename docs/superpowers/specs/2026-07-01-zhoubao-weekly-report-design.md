# 项目周报插件（zhoubao）设计文档

- 日期：2026-07-01
- 插件 code：`zhoubao`
- 插件名：项目周报
- 目标禅道版本：>=20.0（兼容 22.x）
- 作者：李永杰

## 1. 背景与目标

为禅道开源版增加一个**项目维度的周报**插件，供项目经理（PM）每周为自己负责的项目填写周报。核心价值：

- 写周报时**自动罗列该项目本周完成的任务**，供 PM 参考，减少手工整理。
- 自动汇总本周未完成/逾期任务、进度与工时。
- 提供**未完成提醒**：既提醒"周报未填报"（面向管理层/PM），也在周报内高亮"任务逾期/未完"（面向内容）。
- 提供**跨项目汇总看板**作为提醒中心，并通过**企业微信群机器人定时推送**当周填报情况与摘要。

一份周报 = 一个项目 × 一个自然周（周一 00:00 ~ 周日 23:59）。主体是项目经理。

## 2. 总体架构与约定

遵循本仓库既有插件约定（见 CLAUDE.md）：

- 顶级主导航入口"项目周报"，参照 `taizhang` 的 `$lang->mainNav` + `$lang->mainNav->menuOrder[N]` 注册方式（N 取不与核心组织=60/后台=65/台账=64 冲突的值，暂定 63）。
- 模块目录 `extension/custom/zhoubao/`，含 `control.php` / `model.php` / `lib/zhoubaoRules.php` / `config.php` / `lang/zh-cn.php` / `ui/` / `css/` / `js/`。
- **无框架依赖的 `lib/zhoubaoRules.php`**：任务分类（完成/未完成/逾期）与企微消息组装的纯逻辑放这里，配 `tests/test_rules.php` 脱离禅道运行时单测（沿用 zoucha 模式，这是本仓库强约定）。
- URL 日期参数用 `_` 传参（如 `2026_07_01`），控制器用 `str_replace('_','-',...)` 还原；分页/筛选参数按位置声明为控制器形参。
- install/uninstall SQL 注释内不得含 `;`。
- 生 XHR POST（保存草稿、复制上周）加 `X-Requested-With` 头；ZIN 脚本加防重复绑定守卫。
- 配置级联：plugin `config.php` → `common/ext/config` → `config/ext`；app 注册（apps/appsMenu/includedPriv）放 `common/ext/config`，权限包放 `group/ext`。

### 数据新鲜度策略（方案 A：实时 + 提交快照）

- **草稿态**：自动区（完成/未完成/逾期/工时）每次打开**实时**从禅道数据计算，保证 PM 周中写、周五提交都看到最新数据。
- **提交态**：点击"提交"时把自动区数据连同手写内容固化进 `snapshot` JSON 字段，之后查看只读渲染 `snapshot`，不再重算 —— 保证历史周报可存档、不因任务后续被改动而失真。

## 3. 数据表

### `zt_zhoubao`（周报主表，一行 = 一个项目一周）

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | mediumint unsigned AUTO PK | |
| `project` | mediumint | 项目 ID |
| `year` | smallint | ISO 年份 |
| `week` | tinyint unsigned | ISO 周序号（1–53） |
| `weekStart` | date | 周一日期（冗余，便于查询/展示/排序） |
| `account` | varchar(30) | 填报人（PM） |
| `nextPlan` | text | 手写：下周计划 |
| `risk` | text | 手写：风险与需协调资源 |
| `summary` | text | 手写：本周小结（可选） |
| `snapshot` | mediumtext | 提交时固化的自动数据 JSON |
| `status` | enum('draft','submitted') default 'draft' | 草稿 / 已提交 |
| `submittedDate` | datetime null | 提交时间 |
| `createdDate` | datetime | |
| `editedDate` | datetime | |

- 唯一键 `uk_project_week (project, year, week)` —— 一个项目一周只有一份周报。
- 索引 `idx_week (year, week)`、`idx_account (account)`。
- 卸载：`db/uninstall.sql` 仅 `DROP TABLE zt_zhoubao`（不动禅道共享表）。

### `snapshot` JSON 结构

```json
{
  "done":    [{"id":1,"name":"...","assignedTo":"张三","finishedDate":"2026-06-30","consumed":8}],
  "undone":  [{"id":2,"name":"...","assignedTo":"李四","deadline":"2026-07-03","status":"doing","left":6}],
  "overdue": [{"id":3,"name":"...","assignedTo":"王五","deadline":"2026-06-25","daysOverdue":5}],
  "stat":    {"progress":62,"weekConsumed":40,"totalLeft":120,"doneCount":8,"overdueCount":1},
  "zoucha":  {"hitRules":["overdue","stale"]}
}
```

企微 webhook URL、pushToken、推送时间等放 `config/ext/zhoubao.php`，不新增表。

## 4. 规则库 `lib/zhoubaoRules.php`（无框架依赖，可单测）

纯静态方法，输入普通数组/stdClass，输出分类结果，不触碰 DAO/全局。

- `classifyTasks(array $tasks, string $weekStart, string $weekEnd, string $today): array`
  返回 `['done'=>[], 'undone'=>[], 'overdue'=>[]]`。判定规则：
  - **done**：`status in (done, closed)` 且 `finishedDate` 落在 `[weekStart, weekEnd]` 内。
  - **overdue**：`status not in (done, closed, cancel)` 且 `deadline` 非空且 `deadline < today`；`daysOverdue = today - deadline`。
  - **undone**：`status not in (done, closed, cancel)` 且 `deadline` 落在 `[weekStart, weekEnd]` 内但未逾期（本周应完成而未完成）。
  - 脏日期（`0000-00-00`、空）按"无 deadline"处理，不计入 overdue/undone。
- `buildWecomMarkdown(array $summaryRows, int $year, int $week): string`
  输入各项目填报状态与摘要行，输出企微 markdown 文本（未交名单 + 已交摘要）。纯字符串组装，可单测。

`tests/test_rules.php`：零依赖 PHP харness，覆盖 5+ 场景（正常完成、跨周边界、逾期、脏日期、无 deadline、消息组装）。

## 5. 数据查询（model.php）

复用 zoucha 的活跃项目/任务查询模式：

- **活跃项目**：`TABLE_PROJECT where type='project' and deleted='0' and status != 'closed'`，取 `id, name, PM`。
- **任务**：`TABLE_TASK where project in (...) and deleted='0'`，取 `id, project, execution, name, assignedTo, status, deadline, finishedDate, consumed, left`；过滤掉属于已关闭执行的任务（`execution=0` 保留），逻辑同 zoucha。
- **团队任务**：多人任务经 `zt_team` 展开（若需按人显示，参照 man_resource 的 `getTaskTeamMap()`）。MVP 先按任务 `assignedTo` 显示，不拆多人。
- **工时**：本周消耗从 `zt_effort`（`date` 落在本周）汇总；项目进度取 `project.progress`。
- **走查联动（功能 3）**：调用 zoucha 的规则库（若已安装）或内联轻量判定，取该项目命中的失管规则名，存入 snapshot.zoucha。以"zoucha 未安装则跳过"的软依赖方式实现，不强耦合。

## 6. 页面与交互

顶级导航"项目周报"进入后共 3 个页面。

### 6.1 汇总看板 `zhoubao-browse`（默认页 / 提醒中心）

- 顶部：周切换器（上一周/本周/下一周，用 weekStart 传参）+ 项目经理筛选 + 填报状态筛选（全部/已交/草稿/未交）。
- 列表（dtable）：一行一个活跃项目 —— 项目名、PM、填报状态（🟢已交/🟡草稿/🔴未交）、本周完成任务数、逾期任务数、走查命中数、操作（写周报/查看）。
- 顶部统计条：本周应交 N / 已交 M / 未交 K；红黄绿高亮。
- 工具栏：导出（功能 2）、管理员可见"立即推送企微"按钮。

### 6.2 写/编辑周报 `zhoubao-edit&project=&week=`

- **自动区（只读，草稿态实时）**：
  - 本周完成任务表（名称/负责人/完成时间/工时）。
  - 本周未完成 + 逾期任务表（逾期行标红，显示逾期天数）。
  - 进度/工时概览卡片（进度%、本周消耗工时、剩余工时、完成任务数）。
  - 走查健康度提示条（功能 3）：命中的失管规则标签。
- **手写区**：下周计划、风险与需协调资源、本周小结。
  - "一键复制上周手写内容"按钮（功能 1）：读上一周同项目周报的三段手写文本填入。
- 底部：保存草稿（生 XHR，加 AJAX 头） / 提交（写 snapshot 冻结，状态置 submitted）。
- 权限：PM 仅能编辑自己负责项目（`project.PM == account`）；管理员可编辑全部。

### 6.3 查看周报 `zhoubao-view&id=`

- 只读渲染已提交周报，自动区读 `snapshot` 不重算；手写区原样展示。
- 顶部导出单份按钮（功能 2）。
- 历史时间线入口（功能 4）：同项目按周列出历史周报链接。

## 7. 企业微信定时推送

- **配置**（`config/ext/zhoubao.php`）：
  - `$config->zhoubao->wecomWebhook`：群机器人 URL。
  - `$config->zhoubao->pushToken`：防未授权调用的 token。
  - `$config->zhoubao->pushDay` / `pushTime`：默认周五 17:00（仅文档提示，实际由 cron 决定）。
- **推送入口**：`zhoubao-cronPush&token=xxx`，校验 token 后：
  1. 算出本周所有活跃项目的填报状态与摘要行；
  2. `zhoubaoRules::buildWecomMarkdown()` 组装 markdown（未交名单 + 已交摘要）；
  3. 通过禅道自带 HTTP 工具（`snoopy`/`fsockopen`/`curl`）POST 到 webhook。
- **触发**：禅道后台"定时任务"或系统 crontab 定时 `curl` 该 URL（部署文档写清）。手动"立即推送"按钮复用同一 model 方法。
- 组装逻辑在 `lib/`（可单测），实际 HTTP 发送在 model（不进单测）。

## 8. 功能范围与阶段划分

全部 6 个额外功能纳入设计。为可落地，按阶段实现：

**MVP（第一阶段）**
- 项目周报核心：3 个页面 + zt_zhoubao 表 + 实时/快照。
- 未完成提醒：看板填报状态 + 周报内逾期高亮。
- 功能 1：一键复制上周手写内容。
- 功能 2：导出（单份 + 整周汇总，复用 taizhang/zoucha 导出）。
- 功能 3：走查联动（软依赖，未装 zoucha 则跳过）。
- 企微定时推送。

**第二阶段**
- 功能 4：历史周报时间线（同项目按周回溯）。
- 功能 5：本周 vs 上周对比（完成任务数/工时环比）。
- 功能 6：人力联动（风险区"需协调资源"跳 man_resource 看成员负载，跨插件软链接）。

## 9. 目录结构

```
zentao_zhoubao/
├─ plugin.json                      # code=zhoubao, name=项目周报
├─ doc/zh-cn.yaml                   # 安装清单
├─ db/install.sql / uninstall.sql   # 建/删 zt_zhoubao
├─ config/ext/zhoubao.php           # 企微 webhook、pushToken、推送时间
├─ tests/test_rules.php             # 纯 PHP 单测（任务分类 + 企微消息组装）
└─ extension/custom/
   ├─ common/ext/config/zhoubao.php     # apps / appsMenu / includedPriv 注册
   ├─ common/ext/lang/zh-cn/zhoubao.php # 顶级主导航 mainNav + menuOrder[63]
   ├─ group/ext/config/zhoubao.php      # 权限包
   ├─ group/ext/lang/zh-cn/zhoubao.php  # 权限包语言
   └─ zhoubao/
      ├─ control.php   # browse / edit(save) / view / cronPush / export / copyLast
      ├─ model.php     # 数据查询、快照读写、企微 HTTP 发送、走查/人力联动
      ├─ lib/zhoubaoRules.php  # 无框架依赖：任务分类 + 消息组装（可单测）
      ├─ config.php  lang/zh-cn.php
      ├─ ui/browse.html.php  ui/edit.html.php  ui/view.html.php
      └─ css/zhoubao.css  js/zhoubao.js
```

## 10. 部署与打包

- 加入 `pack_plugins.ps1` 打包列表，产出 `dist/zhoubao-<version>.zip`。
- 手动部署：`extension/*`、`config/ext/*` 覆盖；首次装导入 `db/install.sql`；清 `tmp/cache/*` + `tmp/model/*`。
- 企微推送需在禅道后台定时任务或系统 crontab 配置定时 `curl zhoubao-cronPush?token=xxx`。

## 11. 测试

- `lib/zhoubaoRules.php` 单测：`cd zentao_zhoubao && php tests/test_rules.php`，覆盖任务分类边界与企微消息组装。
- 其余页面/推送需装入运行中的禅道 20+/22.x 实例逐页验证（企微推送需真实 webhook）。

## 12. 权限

- `$lang->resource->zhoubao`：browse/edit/view/export/cronPush/manage。
- PM 只能写自己负责项目；管理员（`manage` 权限）可看全部、触发推送。权限包放 `group/ext`。
