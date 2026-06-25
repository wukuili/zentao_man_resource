# 禅道走查插件（zentao_zoucha）实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 交付一个禅道 20+ 扩展插件 `zoucha`，扫描所有进行中项目，按 5 条规则把"失管"项目（无任务/近一周未更新/任务延期/无迭代/任务超期）筛出来，在一个带筛选、分页、导出的列表页里展示。

**Architecture:** 与同仓库 `zentao_taizhang` 同构的禅道扩展插件。核心规则判定抽成**无禅道依赖的纯类** `zouchaRules`（可用 `php` 独立单测）；`model.php` 负责批量取数（项目/执行/任务各一次查询，PHP 内分组）并调用纯类；`control.php` 处理筛选/分页/导出；`ui/browse.html.php` 用 ZIN DSL 渲染。全部实时查询，不新建业务表。

**Tech Stack:** PHP 8.1、禅道 ZenTao 20+ 扩展框架（ZIN DSL、DAO 查询构造器、PATH_INFO 路由）、MySQL（仅读 `zt_project`/`zt_task`）。

## Global Constraints

- 插件代号固定为 `zoucha`，所有模块名/表前缀替换/菜单 key 均用此值。
- 目标平台：禅道 `>=20.0`（open/biz/max/ipd 版本均兼容）。
- 所有数据访问必须走 ZenTao DAO（`$this->dao->select()...`），禁止拼接原始 SQL。
- 所有回复与代码注释用中文；面向用户文案用中文。
- 字段口径（已在设计文档锁定，全部排除 `deleted='1'` 任务与"已关闭执行"下的任务）：
  - R1 `noTask`：项目下任务总数 = 0
  - R2 `stale`：项目有任务，且全部"未关闭状态(status ∉ closed,cancel)"任务的最近活动日期都早于 `staleDays`（默认 7）天前；要求至少有 1 个未关闭任务
  - R3 `overdue`：存在 `deadline` < 今天 且 status ∉ (done,closed,cancel) 的任务，逾期数 ≥ `overdueMin`（默认 1）
  - R4 `noExecution`：项目下没有任何执行（type ∈ sprint/stage/kanban，未删除）
  - R5 `longTask`：存在任务 `deadline − estStarted > longTaskDays`（默认 14，两端日期均有效）
- 项目范围：`zt_project` 中 `type='project'`、`deleted='0'`、`status != 'closed'`。
- 阈值与启用规则只在 `config.php` 配置，不做页面运行时可调。
- 部署根：插件解压后落在禅道根目录；`extension/` 不在 web 根（www）下，CSS/JS 必须由模板服务端内联输出（不能用静态 URL，否则 404/MIME 报错）。

---

## File Structure

```
zentao_zoucha/
├── doc/zh-cn.yaml                              # 插件清单（安装包识别）
├── plugin.json                                 # 插件元信息
├── db/install.sql                              # 无业务表，仅占位注释
├── db/uninstall.sql                            # 清理 grouppriv 残留授权
├── config/ext/zoucha.php                       # logonMethods（菜单对登录用户可见）
├── extension/custom/
│   ├── common/ext/config/zoucha.php            # apps/appsMenu/includedPriv 注册
│   ├── common/ext/lang/zh-cn/zoucha.php        # 顶级主导航注册
│   ├── group/ext/config/zoucha.php             # 权限包登记
│   ├── group/ext/lang/zh-cn/zoucha.php         # 视图级权限资源
│   └── zoucha/
│       ├── config.php                          # 阈值/启用规则/颜色/分页
│       ├── lang/zh-cn.php                       # 文案 + 规则标签 + 权限资源
│       ├── lib/zouchaRules.php                  # 纯规则引擎（无禅道依赖，可单测）
│       ├── model.php                            # 取数 + 调用规则引擎
│       ├── control.php                          # browse / export / diagnostic
│       ├── css/zoucha.css                       # 列表/标签/筛选样式
│       ├── js/zoucha.js                         # 筛选提交/重置
│       └── ui/browse.html.php                   # ZIN 列表模板
└── tests/test_rules.php                        # zouchaRules 独立单测（php 直接跑）
```

每个文件单一职责：注册类文件只做框架接线；`zouchaRules.php` 只做纯判定；`model.php` 只做取数与组装；`control.php` 只做 HTTP 参数与分页；`browse.html.php` 只做渲染。

---

## Task 1: 插件骨架与注册（可安装的空插件）

让插件能被禅道识别、安装，顶级菜单"项目走查"可见，点进去渲染一个占位空页面。此任务完成后即可在禅道里走通"安装→看到菜单→打开页面"全链路，规则逻辑留待后续任务。

**Files:**
- Create: `zentao_zoucha/doc/zh-cn.yaml`
- Create: `zentao_zoucha/plugin.json`
- Create: `zentao_zoucha/db/install.sql`
- Create: `zentao_zoucha/db/uninstall.sql`
- Create: `zentao_zoucha/config/ext/zoucha.php`
- Create: `zentao_zoucha/extension/custom/common/ext/config/zoucha.php`
- Create: `zentao_zoucha/extension/custom/common/ext/lang/zh-cn/zoucha.php`
- Create: `zentao_zoucha/extension/custom/group/ext/config/zoucha.php`
- Create: `zentao_zoucha/extension/custom/group/ext/lang/zh-cn/zoucha.php`
- Create: `zentao_zoucha/extension/custom/zoucha/config.php`
- Create: `zentao_zoucha/extension/custom/zoucha/lang/zh-cn.php`
- Create: `zentao_zoucha/extension/custom/zoucha/control.php`
- Create: `zentao_zoucha/extension/custom/zoucha/model.php`
- Create: `zentao_zoucha/extension/custom/zoucha/ui/browse.html.php`
- Create: `zentao_zoucha/extension/custom/zoucha/css/zoucha.css`（空占位）
- Create: `zentao_zoucha/extension/custom/zoucha/js/zoucha.js`（空占位）

**Interfaces:**
- Produces:
  - 控制器类 `zoucha extends control`，方法 `browse(string $rule = '', int $pageID = 1)`
  - 模型类 `zouchaModel extends model`，方法 `inspect(): array`（本任务返回空数组占位）
  - 配置：`$config->zoucha->staleDays|longTaskDays|overdueMin|rules|recPerPage|ruleColors`
  - 语言：`$lang->zoucha->ruleList`（规则键 => 中文标签）、`$lang->zoucha->common/browse`

- [ ] **Step 1: 写插件清单 `doc/zh-cn.yaml`**

```yaml
# 基本信息。
name: 项目走查
code: zoucha
type: extension
copyright: 神思电子技术股份有限公司 2026
site: https://www.sdses.com
author: 李永杰
abstract: 禅道项目走查（健康度体检）插件
# 描述和安装文档
desc: |
  禅道项目走查插件，自动扫描所有进行中项目，按 5 条规则筛出"失管"项目：无任务、任务近一周未更新、任务延期、无迭代/里程碑、任务持续时间超 2 周。一屏定位需要关注的项目，支持按问题类型筛选与导出。
install: |
  后台本地安装

# 版本信息。
releases:
  1.0:
    zentaoversion: 20.0+,biz10.0+,max5.0+,ipd2.0+
    charge: free
    changelog: 初始版本，支持 5 条走查规则、问题类型筛选、CSV 导出。
    date: 2026-06-25
```

- [ ] **Step 2: 写 `plugin.json`**

```json
{
    "code": "zoucha",
    "name": "项目走查",
    "version": "1.0",
    "zentaoVersion": ">=20.0",
    "author": "李永杰",
    "email": "wukuili@gmail.com",
    "description": "项目走查（健康度体检）插件：扫描所有进行中项目，按 5 条规则筛出无任务、近一周未更新、任务延期、无迭代、任务超期的失管项目，支持按问题类型筛选与导出。",
    "type": "extension",
    "depend": [],
    "incompatible": []
}
```

- [ ] **Step 3: 写 `db/install.sql`（无业务表，仅占位）**

```sql
-- 项目走查插件无需业务数据表：所有走查结果均为实时查询，不落库。
-- 本文件仅作占位，禅道 extensionModel::executeDB() 会按 ';' 切分执行，空内容无副作用。
```

- [ ] **Step 4: 写 `db/uninstall.sql`（清理权限残留）**

```sql
-- 项目走查插件卸载 SQL —— 由禅道 extensionModel::executeDB($code, 'uninstall') 自动执行。
-- 框架按 ';' 切分逐句执行，并把 'zt_' 自动替换为实际表前缀。
-- 作用：清掉用户组里残留的走查权限授权行（无业务表可删）。

DELETE FROM `zt_grouppriv` WHERE `module` = 'zoucha';
```

- [ ] **Step 5: 写 `config/ext/zoucha.php`（菜单对登录用户可见）**

```php
<?php
/**
 * 走查模块路由/权限相关全局配置。
 * 部署后位于禅道根目录 config/ext/zoucha.php，由 config/config.php 的 glob('ext/*.php') 全局加载。
 *
 * browse 设为 logonMethod：任何登录用户都可浏览走查列表，保证顶级菜单「项目走查」对所有登录用户可见。
 * export/diagnostic 不放行——export 已在 group 权限包中登记需授权；diagnostic 在控制器内限管理员。
 */
$config->logonMethods[] = 'zoucha.browse';
```

- [ ] **Step 6: 写 `extension/custom/common/ext/config/zoucha.php`（应用注册）**

```php
<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->zoucha)) $config->zoucha = new stdclass();

/* 声明本模块包含的权限方法 */
$config->zoucha->includedPriv['zoucha'] = array('browse', 'export', 'diagnostic');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->zoucha = 'zoucha';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->zoucha = 'zoucha';
```

- [ ] **Step 7: 写 `extension/custom/common/ext/lang/zh-cn/zoucha.php`（顶级主导航）**

```php
<?php
/**
 * 顶级主导航注册 —— 在最外层主导航栏新增独立入口「项目走查」。
 *
 * 机制（见 module/common/model.php::getMainNavList）：
 *   1) 只遍历 $lang->mainNav->menuOrder 里登记的项；
 *   2) 每个 $lang->mainNav->{key} 为字符串 "标题|模块|方法|参数"，按 '|' 解析；
 *   3) 显示与否由 common::hasPriv(模块,方法) 决定（zoucha.browse 已注册为 logonMethod，登录即可访问）。
 */

/* 顶部主导航图标 */
if(!isset($lang->navIcons))     $lang->navIcons     = array();
if(!isset($lang->navIconNames)) $lang->navIconNames = array();
$lang->navIcons['zoucha']     = "<i class='icon icon-search'></i>";
$lang->navIconNames['zoucha'] = 'search';

/* 1) 顶级主导航项。下标避开已被占用的 62(OA)、64(台账)、65(后台)，用空闲的 63。 */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->zoucha        = "{$lang->navIcons['zoucha']} 项目走查|zoucha|browse|";
$lang->mainNav->menuOrder[63] = 'zoucha';

/* 2) 进入 zoucha 应用后的顶部一级导航标签 */
if(!isset($lang->zoucha)) $lang->zoucha = new stdclass();
if(!isset($lang->zoucha->menu)) $lang->zoucha->menu = new stdclass();
$lang->zoucha->menu->browse = array('link' => '走查列表|zoucha|browse', 'order' => 5);
$lang->zoucha->menuOrder[5] = 'browse';

/* 3) 导航高亮分组：自身归属自身 */
$lang->navGroup->zoucha = 'zoucha';
```

- [ ] **Step 8: 写 `extension/custom/group/ext/config/zoucha.php`（权限包登记）**

```php
<?php
if(isset($config->group->package))
{
    $config->group->package->zouchaother = new stdclass();
    $config->group->package->zouchaother->subset = 'zoucha';
    $config->group->package->zouchaother->privs  = array();
    $config->group->package->zouchaother->privs['zoucha-browse'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 5, 'depend' => array());
    $config->group->package->zouchaother->privs['zoucha-export'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 6, 'depend' => array());
}
```

- [ ] **Step 9: 写 `extension/custom/group/ext/lang/zh-cn/zoucha.php`（权限资源名）**

```php
<?php
/* 将走查作为独立的视图权限暴露出来 */
$lang->resource->zoucha = new stdclass();
$lang->resource->zoucha->browse = '浏览走查列表';
$lang->resource->zoucha->export = '导出走查结果';
```

- [ ] **Step 10: 写 `extension/custom/zoucha/config.php`（阈值与规则）**

```php
<?php
if(!isset($config->zoucha)) $config->zoucha = new stdclass();

/* ── 走查阈值（管理员可改本文件调整）── */
$config->zoucha->staleDays    = 7;   // R2：近一周未更新（天）
$config->zoucha->longTaskDays = 14;  // R5：任务持续时间超 2 周（天）
$config->zoucha->overdueMin   = 1;   // R3：命中所需最少逾期任务数

/* 启用哪些规则（按此顺序展示）。可删项以停用某条规则。 */
$config->zoucha->rules = array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');

/* 列表默认分页条数 */
$config->zoucha->recPerPage = 20;

/* 规则标签颜色 */
$config->zoucha->ruleColors = array(
    'noTask'      => '#e74c3c',
    'stale'       => '#e67e22',
    'overdue'     => '#c0392b',
    'noExecution' => '#8e44ad',
    'longTask'    => '#d35400',
);
```

- [ ] **Step 11: 写 `extension/custom/zoucha/lang/zh-cn.php`（文案与标签）**

```php
<?php
if(!isset($lang->zoucha)) $lang->zoucha = new stdclass();
$lang->zoucha->common = '项目走查';
$lang->zoucha->browse = '项目走查';

/* 子菜单 */
$lang->zoucha->menu = new stdclass();
$lang->zoucha->menu->browse = array('link' => '走查列表|zoucha|browse');

/* 权限资源定义 */
if(!isset($lang->resource)) $lang->resource = new stdclass();
$lang->resource->zoucha = new stdclass();
$lang->resource->zoucha->browse = 'browse';
$lang->resource->zoucha->export = 'export';

/* 规则键 => 中文标签 */
$lang->zoucha->ruleList = array(
    'noTask'      => '无任务',
    'stale'       => '近一周未更新',
    'overdue'     => '任务延期',
    'noExecution' => '无迭代',
    'longTask'    => '任务超期',
);

/* 表头 */
$lang->zoucha->colProject   = '项目';
$lang->zoucha->colProgram   = '所属项目集';
$lang->zoucha->colPM        = '负责人';
$lang->zoucha->colStatus    = '项目状态';
$lang->zoucha->colHits      = '走查结果';
$lang->zoucha->colTaskCount = '任务数';
$lang->zoucha->colOverdue   = '逾期数';
$lang->zoucha->colExecCount = '执行数';
$lang->zoucha->colLastEdited = '最后任务更新';

/* 按钮与提示 */
$lang->zoucha->filterRule   = '问题类型';
$lang->zoucha->filterAll    = '全部';
$lang->zoucha->filterSearch = '查询';
$lang->zoucha->filterReset  = '重置';
$lang->zoucha->exportEntry  = '导出Excel';
$lang->zoucha->noData       = '太棒了！当前没有命中任何走查规则的项目。';
$lang->zoucha->totalFlagged = '问题项目数';

/* 项目状态中文名 */
$lang->zoucha->projectStatusList = array(
    'wait'      => '未开始',
    'doing'     => '进行中',
    'suspended' => '挂起',
    'closed'    => '关闭',
);
```

- [ ] **Step 12: 写 `extension/custom/zoucha/model.php`（本任务为占位）**

```php
<?php
/**
 * 项目走查 — 模型层
 * 所有数据查询均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class zouchaModel extends model
{
    /**
     * 主走查入口：返回命中规则的项目结果数组（本任务先返回空，逻辑在 Task 3 实现）。
     *
     * @return array
     */
    public function inspect()
    {
        return array();
    }
}
```

- [ ] **Step 13: 写 `extension/custom/zoucha/control.php`（最小可渲染）**

```php
<?php
/**
 * 项目走查 — 控制器
 * 提供走查列表浏览、导出、单项目诊断。
 */
class zoucha extends control
{
    /**
     * 走查列表页（默认入口）。
     *
     * 注意：禅道 PATH_INFO 路由按「位置」把 URL 参数映射到方法形参，
     * 分页参数 $pageID 必须声明为形参，否则翻页链接永远落在第 1 页。
     *
     * @param string $rule   问题类型过滤（规则键；'' 或 'all' 表示全部）
     * @param int    $pageID 页码
     */
    public function browse($rule = '', $pageID = 1)
    {
        $rule   = ($rule === false || $rule === null) ? '' : (string)$rule;
        $pageID = (int)$pageID;
        if($rule === 'all') $rule = '';

        $results = $this->zoucha->inspect();

        $this->view->title    = $this->lang->zoucha->browse;
        $this->view->results  = $results;
        $this->view->rule     = $rule;
        $this->view->pageID   = max(1, $pageID);
        $this->view->pageTotal = 1;
        $this->view->recPerPage = isset($this->config->zoucha->recPerPage) ? (int)$this->config->zoucha->recPerPage : 20;
        $this->view->total    = count($results);
        $this->view->ruleCounts = array();

        $this->display();
    }
}
```

- [ ] **Step 14: 写 `extension/custom/zoucha/ui/browse.html.php`（占位面板）**

```php
<?php
declare(strict_types=1);
/**
 * 项目走查 — 列表页 ZIN 模板（Task 1 占位版，Task 5 完善）
 */
namespace zin;

$results = $this->view->results;
$total   = (int)$this->view->total;

panel
(
    setClass('zoucha-page'),
    html('<div style="padding:16px">项目走查页面占位，命中项目数：' . $total . '</div>')
);
```

- [ ] **Step 15: 建空 CSS/JS 占位文件**

`css/zoucha.css`：
```css
/* 项目走查样式（Task 5 填充） */
```

`js/zoucha.js`：
```javascript
/* 项目走查脚本（Task 5 填充） */
```

- [ ] **Step 16: 语法校验所有 PHP 文件**

Run:
```bash
find zentao_zoucha -name "*.php" -print0 | xargs -0 -n1 php -l
```
Expected: 每个文件输出 `No syntax errors detected in ...`，无 `Parse error`。

- [ ] **Step 17: 手动安装验证（在运行中的禅道 20+ 实例）**

打包 `zentao_zoucha` 目录为 zip → 禅道后台「应用」→ 本地安装 → 安装成功无报错；顶部主导航出现「项目走查」入口；点击进入显示占位面板"命中项目数：0"（model 占位返回空）。

> 若无可用禅道实例，本步记录为"待人工验证"，以 Step 16 的语法校验作为本任务自动门禁。

- [ ] **Step 18: 提交**

```bash
git add zentao_zoucha
git commit -m "feat(zoucha): 插件骨架与注册，可安装并显示空走查页"
```

---

## Task 2: 纯规则引擎 `zouchaRules` + 独立单测（TDD）

把 5 条规则判定写成无禅道依赖的纯类，用 `php` 直接跑的测试驱动。这是本插件唯一有逻辑风险的部分，必须先有失败测试再实现。

**Files:**
- Create: `zentao_zoucha/tests/test_rules.php`
- Create: `zentao_zoucha/extension/custom/zoucha/lib/zouchaRules.php`

**Interfaces:**
- Produces: `zouchaRules::evaluate($tasks, $executionCount, $today, $staleDays, $longTaskDays, $overdueMin, $enabledRules): array`
  - `$tasks`: 数组，元素为对象或关联数组，含键 `status, estStarted, deadline, openedDate, lastEditedDate`（调用方已过滤掉已删除任务及已关闭执行下的任务）
  - `$executionCount`: int，该项目未删除执行数量
  - `$today`: `'Y-m-d'`
  - 返回 `['hits'=>string[], 'taskCount'=>int, 'overdueCount'=>int, 'executionCount'=>int, 'lastTaskEdited'=>?string]`
- Consumes（被 Task 3 的 `model.php` 调用）。

- [ ] **Step 1: 写失败测试 `tests/test_rules.php`**

```php
<?php
/**
 * zouchaRules 独立单测——无需禅道环境，直接 `php tests/test_rules.php` 运行。
 */
require __DIR__ . '/../extension/custom/zoucha/lib/zouchaRules.php';

$fail = 0;
function check($name, $cond)
{
    global $fail;
    if($cond) { echo "PASS  $name\n"; }
    else      { echo "FAIL  $name\n"; $fail++; }
}

function task($status, $estStarted, $deadline, $openedDate, $lastEditedDate)
{
    return array(
        'status'         => $status,
        'estStarted'     => $estStarted,
        'deadline'       => $deadline,
        'openedDate'     => $openedDate,
        'lastEditedDate' => $lastEditedDate,
    );
}

$today = '2026-06-25';
$all   = array('noTask', 'stale', 'overdue', 'noExecution', 'longTask');

/* R1：无任务 */
$r = zouchaRules::evaluate(array(), 3, $today, 7, 14, 1, $all);
check('R1 无任务命中', in_array('noTask', $r['hits'], true));
check('R1 无任务不误命中 stale/overdue/longTask',
    !in_array('stale', $r['hits'], true) && !in_array('overdue', $r['hits'], true) && !in_array('longTask', $r['hits'], true));
check('R1 taskCount=0', $r['taskCount'] === 0);

/* R4：无执行（即使有任务也独立判定） */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-20', '2026-06-30', '2026-06-24 10:00:00', '2026-06-24 10:00:00')), 0, $today, 7, 14, 1, $all);
check('R4 无执行命中', in_array('noExecution', $r['hits'], true));
check('R4 有执行不命中', !in_array('noExecution', zouchaRules::evaluate(array(), 2, $today, 7, 14, 1, $all)['hits'], true));

/* R3：逾期任务 */
$tasks = array(
    task('doing', '2026-06-01', '2026-06-10', '2026-06-01 09:00:00', '2026-06-20 09:00:00'), // 逾期
    task('done',  '2026-06-01', '2026-06-05', '2026-06-01 09:00:00', '2026-06-20 09:00:00'), // 已完成，不算逾期
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R3 逾期命中', in_array('overdue', $r['hits'], true));
check('R3 逾期数=1（done 不计）', $r['overdueCount'] === 1);

/* R3：overdueMin 门槛——1 个逾期但门槛=2 时不命中 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-20 09:00:00', '2026-06-20 09:00:00')), 1, $today, 7, 14, 2, $all);
check('R3 未达门槛不命中', !in_array('overdue', $r['hits'], true));

/* R5：任务跨度 > 14 天 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-20', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R5 超期命中(19天)', in_array('longTask', $r['hits'], true));
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('R5 未超期不命中(9天)', !in_array('longTask', $r['hits'], true));

/* R2：全部未关闭任务都早于 7 天前 → 命中 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'), // 最近活动 6-10，早于 6-18
    task('wait',  '2026-05-01', '2026-07-01', '2026-06-05 09:00:00', '2026-06-05 09:00:00'),
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 近一周未更新命中', in_array('stale', $r['hits'], true));

/* R2：有一个任务近一周内有更新 → 不命中 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'),
    task('doing', '2026-05-01', '2026-07-01', '2026-06-24 09:00:00', '2026-06-24 09:00:00'), // 昨天更新
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 有近期更新不命中', !in_array('stale', $r['hits'], true));

/* R2：全部任务已关闭/取消 → 无未关闭任务，不命中（避免空集真空命中） */
$tasks = array(task('closed', '2026-05-01', '2026-06-01', '2026-05-01 09:00:00', '2026-05-10 09:00:00'));
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('R2 全关闭任务不命中', !in_array('stale', $r['hits'], true));

/* lastTaskEdited 取全部任务最近活动日期 */
$tasks = array(
    task('doing', '2026-05-01', '2026-07-01', '2026-06-01 09:00:00', '2026-06-10 09:00:00'),
    task('doing', '2026-05-01', '2026-07-01', '2026-06-15 09:00:00', '2026-06-22 09:00:00'),
);
$r = zouchaRules::evaluate($tasks, 1, $today, 7, 14, 1, $all);
check('lastTaskEdited=2026-06-22', $r['lastTaskEdited'] === '2026-06-22');

/* 停用规则：rules 不含 overdue 时即使逾期也不命中 */
$r = zouchaRules::evaluate(array(task('doing', '2026-06-01', '2026-06-10', '2026-06-20 09:00:00', '2026-06-20 09:00:00')), 1, $today, 7, 14, 1, array('noTask'));
check('停用 overdue 后不命中', !in_array('overdue', $r['hits'], true));

/* 脏日期：0000-00-00 当作空，不参与逾期/跨度 */
$r = zouchaRules::evaluate(array(task('doing', '0000-00-00', '0000-00-00', '2026-06-24 09:00:00', '2026-06-24 09:00:00')), 1, $today, 7, 14, 1, $all);
check('脏日期不触发 overdue/longTask', !in_array('overdue', $r['hits'], true) && !in_array('longTask', $r['hits'], true));

echo $fail === 0 ? "\nALL PASS\n" : "\n{$fail} FAILED\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: 运行测试，确认全部失败**

Run: `php zentao_zoucha/tests/test_rules.php`
Expected: 失败并报错（`zouchaRules.php` 尚不存在 → `require` 致命错误，或类未定义）。

- [ ] **Step 3: 写实现 `extension/custom/zoucha/lib/zouchaRules.php`**

```php
<?php
/**
 * 走查规则引擎（纯函数式，无禅道依赖，可独立单测）。
 *
 * 输入已由调用方过滤：$tasks 仅含未删除、且不属于"已关闭执行"的任务。
 */
class zouchaRules
{
    /**
     * @param array  $tasks          任务数组，元素为对象或关联数组，含键
     *                               status, estStarted, deadline, openedDate, lastEditedDate
     * @param int    $executionCount 该项目未删除执行(迭代/阶段)数量
     * @param string $today          今天 'Y-m-d'
     * @param int    $staleDays      R2 阈值（天）
     * @param int    $longTaskDays   R5 阈值（天）
     * @param int    $overdueMin     R3 命中所需最少逾期任务数
     * @param array  $enabledRules   启用的规则键
     * @return array ['hits'=>string[], 'taskCount'=>int, 'overdueCount'=>int,
     *                'executionCount'=>int, 'lastTaskEdited'=>?string]
     */
    public static function evaluate($tasks, $executionCount, $today, $staleDays, $longTaskDays, $overdueMin, $enabledRules)
    {
        $doneStatuses   = array('done', 'closed', 'cancel'); // 不算逾期
        $closedStatuses = array('closed', 'cancel');         // R2 视为"已关闭状态"

        $taskCount   = count($tasks);
        $todayTs     = strtotime($today);
        $staleCutoff = date('Y-m-d', strtotime("-{$staleDays} days", $todayTs)); // 早于此日期=超过 staleDays 天未动

        $overdueCount      = 0;
        $hasLongTask       = false;
        $openCount         = 0;     // 未关闭状态任务数
        $openActivityMax   = null;  // 未关闭任务的最近活动日期（'Y-m-d'）
        $globalActivityMax = null;  // 全部任务的最近活动日期时间（用于展示列）

        foreach($tasks as $t)
        {
            $status   = (string)self::val($t, 'status');
            $deadline = self::cleanDate(self::val($t, 'deadline'));
            $estStart = self::cleanDate(self::val($t, 'estStarted'));

            /* 最近活动 = openedDate 与 lastEditedDate 中较晚的有效值（新建未编辑的任务用 openedDate） */
            $activity = self::maxStr(self::cleanDateTime(self::val($t, 'openedDate')),
                                     self::cleanDateTime(self::val($t, 'lastEditedDate')));
            if($activity !== null) $globalActivityMax = self::maxStr($globalActivityMax, $activity);

            /* R3 逾期 */
            if($deadline !== null && strtotime($deadline) < $todayTs && !in_array($status, $doneStatuses, true))
            {
                $overdueCount++;
            }

            /* R5 任务跨度 */
            if($deadline !== null && $estStart !== null)
            {
                $spanDays = (strtotime($deadline) - strtotime($estStart)) / 86400;
                if($spanDays > $longTaskDays) $hasLongTask = true;
            }

            /* R2 收集未关闭任务的最近活动（按日期粒度比较） */
            if(!in_array($status, $closedStatuses, true))
            {
                $openCount++;
                $actDate = ($activity !== null) ? substr($activity, 0, 10) : null;
                $openActivityMax = self::maxStr($openActivityMax, $actDate);
            }
        }

        $hits = array();

        if(in_array('noTask', $enabledRules, true) && $taskCount === 0) $hits[] = 'noTask';

        if(in_array('stale', $enabledRules, true) && $taskCount > 0 && $openCount > 0
            && ($openActivityMax === null || $openActivityMax < $staleCutoff))
        {
            $hits[] = 'stale';
        }

        if(in_array('overdue', $enabledRules, true) && $overdueCount >= max(1, (int)$overdueMin)) $hits[] = 'overdue';

        if(in_array('noExecution', $enabledRules, true) && (int)$executionCount === 0) $hits[] = 'noExecution';

        if(in_array('longTask', $enabledRules, true) && $hasLongTask) $hits[] = 'longTask';

        return array(
            'hits'           => $hits,
            'taskCount'      => $taskCount,
            'overdueCount'   => $overdueCount,
            'executionCount' => (int)$executionCount,
            'lastTaskEdited' => $globalActivityMax !== null ? substr($globalActivityMax, 0, 10) : null,
        );
    }

    /* 读取对象或数组的字段 */
    private static function val($t, $key)
    {
        if(is_array($t)) return isset($t[$key]) ? $t[$key] : null;
        return (is_object($t) && isset($t->$key)) ? $t->$key : null;
    }

    /* date 列规整：空/全零返回 null，否则返回 'Y-m-d' */
    private static function cleanDate($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return substr($v, 0, 10);
    }

    /* datetime 列规整：空/全零返回 null，否则原样（'Y-m-d H:i:s'） */
    private static function cleanDateTime($v)
    {
        $v = (string)$v;
        if($v === '' || strpos($v, '0000-00-00') === 0) return null;
        return $v;
    }

    /* 两个字符串取字典序较大者（日期字符串字典序即时间序），null 视为最小 */
    private static function maxStr($a, $b)
    {
        if($a === null) return $b;
        if($b === null) return $a;
        return ($a >= $b) ? $a : $b;
    }
}
```

- [ ] **Step 4: 运行测试，确认全部通过**

Run: `php zentao_zoucha/tests/test_rules.php`
Expected: 每行 `PASS ...`，结尾 `ALL PASS`，退出码 0。

- [ ] **Step 5: 提交**

```bash
git add zentao_zoucha/tests/test_rules.php zentao_zoucha/extension/custom/zoucha/lib/zouchaRules.php
git commit -m "feat(zoucha): 纯规则引擎 zouchaRules 及独立单测"
```

---

## Task 3: 模型取数与走查组装

实现真正的 `inspect()`：批量取进行中项目、执行、任务，过滤已关闭执行下的任务，逐项目调用 `zouchaRules::evaluate()`，组装含项目集名/负责人姓名的结果。

**Files:**
- Modify: `zentao_zoucha/extension/custom/zoucha/model.php`

**Interfaces:**
- Consumes: `zouchaRules::evaluate(...)`（Task 2）
- Produces:
  - `zouchaModel::inspect(): array` —— 元素为 stdclass，含
    `projectID(int), projectName, programName, pm, pmName, status, statusName, hits(string[]), taskCount(int), overdueCount(int), executionCount(int), lastTaskEdited(?string)`
  - `zouchaModel::getInspectData(int $projectID): array` —— 诊断用，返回单项目的原始任务/执行/判定明细（供 Task 4 的 diagnostic）

- [ ] **Step 1: 重写 `model.php`**

```php
<?php
/**
 * 项目走查 — 模型层
 * 所有数据查询均通过 ZenTao DAO 进行，禁止拼接原始 SQL。
 */
class zouchaModel extends model
{
    /* 载入无禅道依赖的纯规则引擎 */
    private function loadRules()
    {
        if(!class_exists('zouchaRules')) include_once __DIR__ . '/lib/zouchaRules.php';
    }

    /**
     * 取进行中（type=project、未删除、未关闭）的顶级项目。
     *
     * @return array id => 项目对象(id,name,pm,status,path)
     */
    public function getOpenProjects()
    {
        return $this->dao->select('id, name, pm, status, path')->from('zt_project')
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->ne('closed')
            ->orderBy('id desc')
            ->fetchAll('id');
    }

    /**
     * 主走查入口：返回命中规则的项目结果数组。
     *
     * @return array stdclass[]
     */
    public function inspect()
    {
        $this->loadRules();

        $projects = $this->getOpenProjects();
        if(empty($projects)) return array();
        $projectIDs = array_keys($projects);

        /* 一次取出这些项目下的全部执行(迭代/阶段/看板)，算执行数 + 收集已关闭执行ID */
        $execRows = $this->dao->select('id, project, status')->from('zt_project')
            ->where('project')->in($projectIDs)
            ->andWhere('type')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        $execCountMap  = array();   // projectID => 执行数
        $closedExecIDs = array();   // 已关闭执行ID => true（用于过滤任务）
        foreach($projectIDs as $pid) $execCountMap[$pid] = 0;
        foreach($execRows as $ex)
        {
            $pid = (int)$ex->project;
            if(isset($execCountMap[$pid])) $execCountMap[$pid]++;
            if($ex->status == 'closed') $closedExecIDs[(int)$ex->id] = true;
        }

        /* 一次取出这些项目下的全部未删除任务，按 project 分组 */
        $taskGroups = $this->dao->select('id, project, execution, status, estStarted, deadline, openedDate, lastEditedDate')
            ->from('zt_task')
            ->where('project')->in($projectIDs)
            ->andWhere('deleted')->eq('0')
            ->orderBy('id')
            ->fetchGroup('project');

        $today = date('Y-m-d');
        $results = array();
        foreach($projects as $pid => $project)
        {
            $rawTasks = isset($taskGroups[$pid]) ? $taskGroups[$pid] : array();
            /* 过滤掉已关闭执行下的任务 */
            $tasks = array();
            foreach($rawTasks as $t)
            {
                if(isset($closedExecIDs[(int)$t->execution])) continue;
                $tasks[] = $t;
            }

            $eval = zouchaRules::evaluate(
                $tasks,
                isset($execCountMap[$pid]) ? $execCountMap[$pid] : 0,
                $today,
                (int)$this->config->zoucha->staleDays,
                (int)$this->config->zoucha->longTaskDays,
                (int)$this->config->zoucha->overdueMin,
                $this->config->zoucha->rules
            );

            if(empty($eval['hits'])) continue;

            $row = new stdclass();
            $row->projectID      = (int)$pid;
            $row->projectName    = $project->name;
            $row->pm             = $project->pm;
            $row->status         = $project->status;
            $row->path           = $project->path;
            $row->hits           = $eval['hits'];
            $row->taskCount      = $eval['taskCount'];
            $row->overdueCount   = $eval['overdueCount'];
            $row->executionCount = $eval['executionCount'];
            $row->lastTaskEdited = $eval['lastTaskEdited'];
            $results[] = $row;
        }

        $this->enrich($results);
        return $results;
    }

    /**
     * 补充展示字段：负责人真实姓名、项目状态中文名、所属项目集链名。
     *
     * @param array $results inspect() 产出的结果（按引用就地补充）
     */
    private function enrich(array $results)
    {
        $userPairs = $this->loadModel('user')->getPairs('noletter');

        /* 收集所有 path 上的祖先项目ID，批量取项目集名 */
        $programIDs = array();
        foreach($results as $row)
        {
            if(empty($row->path)) continue;
            foreach(explode(',', trim((string)$row->path, ',')) as $pid)
            {
                $pid = (int)$pid;
                if($pid && $pid != $row->projectID) $programIDs[$pid] = $pid;
            }
        }
        $programNames = array();
        if(!empty($programIDs))
        {
            $programNames = $this->dao->select('id, name')->from('zt_project')
                ->where('id')->in($programIDs)
                ->andWhere('type')->eq('program')
                ->andWhere('deleted')->eq('0')
                ->fetchPairs('id', 'name');
        }

        $statusList = $this->lang->zoucha->projectStatusList;
        foreach($results as $row)
        {
            $row->pmName     = zget($userPairs, $row->pm, $row->pm);
            $row->statusName = zget($statusList, $row->status, $row->status);

            $chain = array();
            if(!empty($row->path))
            {
                foreach(explode(',', trim((string)$row->path, ',')) as $pid)
                {
                    $pid = (int)$pid;
                    if($pid == $row->projectID) continue;
                    if(isset($programNames[$pid])) $chain[] = $programNames[$pid];
                }
            }
            $row->programName = implode('/', $chain);
        }
    }

    /**
     * 诊断：返回单个项目的走查原始输入与判定结果（管理员排查用）。
     *
     * @param int $projectID
     * @return array ['project'=>?obj, 'executionCount'=>int, 'tasks'=>array, 'eval'=>array, 'config'=>array]
     */
    public function getInspectData($projectID)
    {
        $this->loadRules();
        $projectID = (int)$projectID;

        $project = $this->dao->select('id, name, pm, status, path, type, deleted')->from('zt_project')
            ->where('id')->eq($projectID)->fetch();

        $execRows = $this->dao->select('id, status')->from('zt_project')
            ->where('project')->eq($projectID)
            ->andWhere('type')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->fetchAll('id');

        $closedExecIDs = array();
        foreach($execRows as $id => $ex) if($ex->status == 'closed') $closedExecIDs[(int)$id] = true;

        $rawTasks = $this->dao->select('id, execution, status, estStarted, deadline, openedDate, lastEditedDate')
            ->from('zt_task')
            ->where('project')->eq($projectID)
            ->andWhere('deleted')->eq('0')
            ->orderBy('id')
            ->fetchAll();

        $tasks = array();
        foreach($rawTasks as $t)
        {
            if(isset($closedExecIDs[(int)$t->execution])) continue;
            $tasks[] = $t;
        }

        $eval = zouchaRules::evaluate(
            $tasks, count($execRows), date('Y-m-d'),
            (int)$this->config->zoucha->staleDays,
            (int)$this->config->zoucha->longTaskDays,
            (int)$this->config->zoucha->overdueMin,
            $this->config->zoucha->rules
        );

        return array(
            'project'        => $project,
            'executionCount' => count($execRows),
            'tasks'          => $tasks,
            'eval'           => $eval,
            'config'         => array(
                'staleDays'    => (int)$this->config->zoucha->staleDays,
                'longTaskDays' => (int)$this->config->zoucha->longTaskDays,
                'overdueMin'   => (int)$this->config->zoucha->overdueMin,
                'rules'        => $this->config->zoucha->rules,
            ),
        );
    }
}
```

- [ ] **Step 2: 语法校验**

Run: `php -l zentao_zoucha/extension/custom/zoucha/model.php`
Expected: `No syntax errors detected`。

- [ ] **Step 3: 回归纯逻辑单测（确保未破坏引擎契约）**

Run: `php zentao_zoucha/tests/test_rules.php`
Expected: `ALL PASS`。

- [ ] **Step 4: 提交**

```bash
git add zentao_zoucha/extension/custom/zoucha/model.php
git commit -m "feat(zoucha): 模型批量取数并组装走查结果"
```

> 数据库联调验证留待 Task 4 的 diagnostic 端点与 Task 6 的整体人工验证。

---

## Task 4: 控制器（筛选 / 分页 / 导出 / 诊断）

**Files:**
- Modify: `zentao_zoucha/extension/custom/zoucha/control.php`

**Interfaces:**
- Consumes: `zouchaModel::inspect()`、`zouchaModel::getInspectData(int)`（Task 3）
- Produces: 视图变量
  `results(已分页), rule, ruleCounts(规则键=>命中数), pageID, pageTotal, recPerPage, total`

- [ ] **Step 1: 重写 `control.php`**

```php
<?php
/**
 * 项目走查 — 控制器
 * 提供走查列表浏览、CSV 导出、单项目诊断。
 */
class zoucha extends control
{
    /**
     * 走查列表页（默认入口）。
     *
     * 注意：禅道 PATH_INFO 路由按「位置」映射形参，分页参数 $pageID 必须声明为形参，
     * 否则翻页链接永远落在第 1 页。
     *
     * @param string $rule   问题类型过滤（规则键；'' 或 'all' 表示全部）
     * @param int    $pageID 页码
     */
    public function browse($rule = '', $pageID = 1)
    {
        if($this->server->request_method == 'POST')
        {
            $rule   = isset($_POST['rule']) ? (string)$_POST['rule'] : '';
            $pageID = 1;
        }
        else
        {
            $rule   = isset($_GET['rule']) ? (string)$_GET['rule'] : (string)$rule;
            $pageID = isset($_GET['pageID']) ? (int)$_GET['pageID'] : (int)$pageID;
        }
        if($rule === 'all') $rule = '';

        $all = $this->zoucha->inspect();

        /* 各规则命中数（基于全集，不受当前筛选影响），用于筛选下拉标签 */
        $ruleCounts = array();
        foreach($this->config->zoucha->rules as $key) $ruleCounts[$key] = 0;
        foreach($all as $row)
        {
            foreach($row->hits as $h) if(isset($ruleCounts[$h])) $ruleCounts[$h]++;
        }

        /* 按问题类型筛选 */
        $filtered = $all;
        if($rule !== '')
        {
            $filtered = array();
            foreach($all as $row) if(in_array($rule, $row->hits, true)) $filtered[] = $row;
        }

        $total      = count($filtered);
        $recPerPage = isset($this->config->zoucha->recPerPage) ? (int)$this->config->zoucha->recPerPage : 20;
        if($recPerPage <= 0) $recPerPage = 20;
        $pageTotal  = max(1, (int)ceil($total / $recPerPage));
        $pageID     = max(1, min((int)$pageID, $pageTotal));
        $results    = array_slice($filtered, ($pageID - 1) * $recPerPage, $recPerPage);

        $this->view->title      = $this->lang->zoucha->browse;
        $this->view->results    = $results;
        $this->view->rule       = $rule;
        $this->view->ruleCounts  = $ruleCounts;
        $this->view->pageID     = $pageID;
        $this->view->pageTotal  = $pageTotal;
        $this->view->recPerPage = $recPerPage;
        $this->view->total      = $total;
        $this->view->totalAll   = count($all);

        $this->display();
    }

    /**
     * 导出当前筛选结果为 CSV。
     *
     * @param string $rule 问题类型过滤
     */
    public function export($rule = '')
    {
        $rule = isset($_GET['rule']) ? (string)$_GET['rule'] : (string)$rule;
        if($rule === 'all') $rule = '';

        $all = $this->zoucha->inspect();
        $rows = $all;
        if($rule !== '')
        {
            $rows = array();
            foreach($all as $row) if(in_array($rule, $row->hits, true)) $rows[] = $row;
        }

        $ruleList = $this->lang->zoucha->ruleList;

        $filename = '项目走查_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM，避免 Excel 乱码

        fputcsv($out, array('序号', '项目', '所属项目集', '负责人', '项目状态', '走查结果', '任务数', '逾期数', '执行数', '最后任务更新'));

        $i = 1;
        foreach($rows as $row)
        {
            $hitLabels = array();
            foreach($row->hits as $h) $hitLabels[] = zget($ruleList, $h, $h);
            fputcsv($out, array(
                $i++,
                $row->projectName,
                $row->programName !== '' ? $row->programName : '-',
                $row->pmName,
                $row->statusName,
                implode('、', $hitLabels),
                $row->taskCount,
                $row->overdueCount,
                $row->executionCount,
                $row->lastTaskEdited !== null ? $row->lastTaskEdited : '-',
            ));
        }
        fclose($out);
        exit;
    }

    /**
     * 诊断单个项目的走查判定明细（仅管理员）。
     *
     * @param int $projectID
     */
    public function diagnostic($projectID = 0)
    {
        if(empty($this->app->user->admin)) die('仅管理员可访问。');

        $data = $this->zoucha->getInspectData((int)$projectID);
        header('Content-Type: text/plain; charset=utf-8');
        echo "== 项目走查诊断 #{$projectID} ==\n\n";
        echo "项目: " . ($data['project'] ? $data['project']->name : '（不存在）') . "\n";
        echo "执行数: {$data['executionCount']}\n";
        echo "参与判定任务数: " . count($data['tasks']) . "\n";
        echo "阈值: " . json_encode($data['config'], JSON_UNESCAPED_UNICODE) . "\n\n";
        echo "判定结果:\n" . json_encode($data['eval'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        echo "任务明细:\n";
        foreach($data['tasks'] as $t)
        {
            echo "  #{$t->id} status={$t->status} est={$t->estStarted} deadline={$t->deadline} opened={$t->openedDate} edited={$t->lastEditedDate}\n";
        }
        exit;
    }
}
```

- [ ] **Step 2: 语法校验**

Run: `php -l zentao_zoucha/extension/custom/zoucha/control.php`
Expected: `No syntax errors detected`。

- [ ] **Step 3: 提交**

```bash
git add zentao_zoucha/extension/custom/zoucha/control.php
git commit -m "feat(zoucha): 控制器支持筛选、分页、CSV 导出与诊断端点"
```

---

## Task 5: ZIN 列表模板与样式

把占位面板换成完整列表：筛选栏（问题类型下拉 + 查询/重置 + 导出）、汇总条（问题项目数 + 各规则命中数）、数据表（彩色标签）、分页器。CSS/JS 由模板服务端内联输出。

**Files:**
- Modify: `zentao_zoucha/extension/custom/zoucha/ui/browse.html.php`
- Modify: `zentao_zoucha/extension/custom/zoucha/css/zoucha.css`
- Modify: `zentao_zoucha/extension/custom/zoucha/js/zoucha.js`

**Interfaces:**
- Consumes: 视图变量（Task 4）：`results, rule, ruleCounts, pageID, pageTotal, recPerPage, total, totalAll`；`$lang->zoucha->ruleList`、`$config->zoucha->ruleColors`

- [ ] **Step 1: 重写 `ui/browse.html.php`**

```php
<?php
declare(strict_types=1);
/**
 * 项目走查 — 列表页 ZIN 模板
 *
 * 渲染：筛选栏 + 汇总条 + 数据表（彩色问题标签）+ 分页器。
 * CSS/JS 由本文件末尾服务端内联输出（extension/ 不在 www 下，不能用静态 URL）。
 */
namespace zin;

$results    = $this->view->results;
$rule       = (string)$this->view->rule;
$ruleCounts = $this->view->ruleCounts;
$pageID     = (int)$this->view->pageID;
$pageTotal  = (int)$this->view->pageTotal;
$recPerPage = (int)$this->view->recPerPage;
$total      = (int)$this->view->total;
$totalAll   = (int)$this->view->totalAll;

$ruleList   = $lang->zoucha->ruleList;
$ruleColors = $this->config->zoucha->ruleColors;

$exportURL = helper::createLink('zoucha', 'export', "rule={$rule}");
jsVar('zouchaBrowseURL', helper::createLink('zoucha', 'browse'));

/* 工具栏 */
toolbar
(
    item(set::text($lang->zoucha->exportEntry), set::icon('export'), set::type('ghost'), set::url($exportURL)),
);

/* ── 筛选栏 HTML ── */
$ruleParam = ($rule === '') ? 'all' : $rule;
$filterHTML  = '<div class="zc-filter-bar">';
$filterHTML .= '<label>' . htmlspecialchars($lang->zoucha->filterRule) . '</label>';
$filterHTML .= '<select id="zcFilterRule" class="form-control" style="max-width:180px">';
$filterHTML .= '<option value="all"' . ($rule === '' ? ' selected' : '') . '>' . htmlspecialchars($lang->zoucha->filterAll) . '（' . $totalAll . '）</option>';
foreach($ruleList as $key => $label)
{
    if(!in_array($key, $this->config->zoucha->rules, true)) continue;
    $cnt = isset($ruleCounts[$key]) ? (int)$ruleCounts[$key] : 0;
    $sel = ($rule === $key) ? ' selected' : '';
    $filterHTML .= '<option value="' . htmlspecialchars((string)$key) . '"' . $sel . '>' . htmlspecialchars($label) . '（' . $cnt . '）</option>';
}
$filterHTML .= '</select>';
$filterHTML .= '<button type="button" class="btn btn-primary btn-sm" onclick="zcSubmitFilter()" style="margin-left:8px">' . htmlspecialchars($lang->zoucha->filterSearch) . '</button>';
$filterHTML .= '<button type="button" class="btn btn-default btn-sm" onclick="zcResetFilter()">' . htmlspecialchars($lang->zoucha->filterReset) . '</button>';
$filterHTML .= '<span class="zc-summary">' . htmlspecialchars($lang->zoucha->totalFlagged) . '：<strong>' . $total . '</strong></span>';
$filterHTML .= '</div>';

/* ── 数据表 ── */
$tableHTML  = '<div class="zc-table-wrap"><table class="zc-table">';
$tableHTML .= '<thead><tr>';
$tableHTML .= '<th style="width:48px">序号</th>';
$tableHTML .= '<th style="min-width:160px">' . htmlspecialchars($lang->zoucha->colProject) . '</th>';
$tableHTML .= '<th style="min-width:110px">' . htmlspecialchars($lang->zoucha->colProgram) . '</th>';
$tableHTML .= '<th style="width:90px">' . htmlspecialchars($lang->zoucha->colPM) . '</th>';
$tableHTML .= '<th style="width:80px">' . htmlspecialchars($lang->zoucha->colStatus) . '</th>';
$tableHTML .= '<th style="min-width:220px">' . htmlspecialchars($lang->zoucha->colHits) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colTaskCount) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colOverdue) . '</th>';
$tableHTML .= '<th style="width:72px">' . htmlspecialchars($lang->zoucha->colExecCount) . '</th>';
$tableHTML .= '<th style="width:110px">' . htmlspecialchars($lang->zoucha->colLastEdited) . '</th>';
$tableHTML .= '</tr></thead><tbody>';

if(empty($results))
{
    $tableHTML .= '<tr><td colspan="10" class="zc-empty">' . htmlspecialchars($lang->zoucha->noData) . '</td></tr>';
}
else
{
    $i = ($pageID - 1) * $recPerPage + 1;
    foreach($results as $row)
    {
        $projectURL = helper::createLink('project', 'view', "projectID={$row->projectID}");
        $nameCell   = '<a href="' . $projectURL . '" target="_blank" class="zc-project-link">' . htmlspecialchars($row->projectName) . '</a>';

        /* 彩色标签 */
        $tags = '';
        foreach($row->hits as $h)
        {
            $color = isset($ruleColors[$h]) ? $ruleColors[$h] : '#888';
            $label = zget($ruleList, $h, $h);
            if($h === 'overdue' && $row->overdueCount > 0) $label .= ' ' . $row->overdueCount;
            $tags .= '<span class="zc-tag" style="background:' . htmlspecialchars($color) . '">' . htmlspecialchars($label) . '</span>';
        }

        /* 命中规则越多越醒目 */
        $rowClass = (count($row->hits) >= 3) ? ' class="zc-row-danger"' : '';

        $tableHTML .= '<tr' . $rowClass . '>';
        $tableHTML .= '<td class="td-center">' . $i++ . '</td>';
        $tableHTML .= '<td>' . $nameCell . '</td>';
        $tableHTML .= '<td>' . ($row->programName !== '' ? htmlspecialchars($row->programName) : '<span class="zc-na">-</span>') . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($row->pmName) . '</td>';
        $tableHTML .= '<td class="td-center">' . htmlspecialchars($row->statusName) . '</td>';
        $tableHTML .= '<td>' . $tags . '</td>';
        $tableHTML .= '<td class="td-center">' . (int)$row->taskCount . '</td>';
        $tableHTML .= '<td class="td-center' . ($row->overdueCount > 0 ? ' zc-num-danger' : '') . '">' . (int)$row->overdueCount . '</td>';
        $tableHTML .= '<td class="td-center">' . (int)$row->executionCount . '</td>';
        $tableHTML .= '<td class="td-center">' . ($row->lastTaskEdited !== null ? htmlspecialchars($row->lastTaskEdited) : '<span class="zc-na">-</span>') . '</td>';
        $tableHTML .= '</tr>';
    }
}
$tableHTML .= '</tbody></table></div>';

/* ── 分页器 ── */
$buildPageURL = function($targetPage) use ($rule) {
    $ruleParam = ($rule === '') ? 'all' : $rule;
    return helper::createLink('zoucha', 'browse', "rule={$ruleParam}&pageID={$targetPage}");
};
$pagerHTML  = '<div class="zc-pager">';
$pagerHTML .= '<span class="zc-pager-info">共 ' . $total . ' 条，每页 ' . $recPerPage . ' 条，第 ' . $pageID . ' / ' . $pageTotal . ' 页</span>';
$pagerHTML .= '<div class="zc-pager-actions">';
if($pageID > 1)
{
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL(1) . '">首页</a>';
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageID - 1) . '">上一页</a>';
}
$startPage = max(1, $pageID - 2);
$endPage   = min($pageTotal, $pageID + 2);
for($p = $startPage; $p <= $endPage; $p++)
{
    if($p == $pageID) $pagerHTML .= '<span class="btn btn-primary btn-sm disabled">' . $p . '</span>';
    else              $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($p) . '">' . $p . '</a>';
}
if($pageID < $pageTotal)
{
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageID + 1) . '">下一页</a>';
    $pagerHTML .= '<a class="btn btn-default btn-sm" href="' . $buildPageURL($pageTotal) . '">末页</a>';
}
$pagerHTML .= '</div></div>';

/* ── 渲染 ── */
panel
(
    setClass('zoucha-page'),
    html($filterHTML . $tableHTML . $pagerHTML)
);

/* ── 内联 CSS / JS ── */
$cssPath = $app->getAppRoot() . 'extension/custom/zoucha/css/zoucha.css';
$jsPath  = $app->getAppRoot() . 'extension/custom/zoucha/js/zoucha.js';
if(is_file($cssPath)) echo "<style>\n"  . file_get_contents($cssPath) . "\n</style>";
if(is_file($jsPath))  echo "<script>\n" . file_get_contents($jsPath)  . "\n</script>";
```

- [ ] **Step 2: 写 `css/zoucha.css`**

```css
.zoucha-page .panel-body { padding: 0 !important; }
.zoucha-page { overflow-x: auto; }

.zc-filter-bar { display:flex; align-items:center; gap:6px; padding:10px 14px; border-bottom:1px solid #eee; flex-wrap:wrap; }
.zc-filter-bar label { margin:0; font-size:13px; color:#555; }
.zc-summary { margin-left:auto; font-size:13px; color:#333; }
.zc-summary strong { color:#c0392b; font-size:15px; }

.zc-table-wrap { width:100%; }
.zc-table { width:100%; border-collapse:collapse; font-size:13px; }
.zc-table th, .zc-table td { border:1px solid #eaeaea; padding:7px 9px; vertical-align:middle; }
.zc-table th { background:#fafafa; font-weight:600; color:#444; white-space:nowrap; }
.zc-table .td-center { text-align:center; }
.zc-table tbody tr:hover { background:#f7fbff; }
.zc-row-danger { background:#fff5f5; }
.zc-row-danger:hover { background:#ffecec; }

.zc-tag { display:inline-block; color:#fff; font-size:12px; line-height:18px; padding:1px 8px; border-radius:10px; margin:2px 4px 2px 0; white-space:nowrap; }
.zc-num-danger { color:#c0392b; font-weight:600; }
.zc-na { color:#bbb; }
.zc-project-link { color:#2680eb; }
.zc-empty { text-align:center; color:#999; padding:30px 0 !important; }

.zc-pager { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-top:1px solid #eee; flex-wrap:wrap; gap:8px; }
.zc-pager-info { font-size:13px; color:#666; }
.zc-pager-actions .btn { margin-left:4px; }
```

- [ ] **Step 3: 写 `js/zoucha.js`**

```javascript
/* 项目走查 — 筛选提交/重置。用 GET 跳转，避免生 XHR POST 触发禅道应用壳 302。 */
function zcSubmitFilter()
{
    var rule = document.getElementById('zcFilterRule').value || 'all';
    var base = (typeof window.zouchaBrowseURL !== 'undefined') ? window.zouchaBrowseURL : '';
    if(!base) { location.reload(); return; }
    var sep = base.indexOf('?') === -1 ? '?' : '&';
    /* PATH_INFO 模式下 createLink 已生成不含参数的基址，这里统一用 query 串传递 */
    location.href = base + sep + 'rule=' + encodeURIComponent(rule) + '&pageID=1';
}

function zcResetFilter()
{
    var sel = document.getElementById('zcFilterRule');
    if(sel) sel.value = 'all';
    zcSubmitFilter();
}
```

- [ ] **Step 4: 语法校验模板**

Run: `php -l zentao_zoucha/extension/custom/zoucha/ui/browse.html.php`
Expected: `No syntax errors detected`。

- [ ] **Step 5: 提交**

```bash
git add zentao_zoucha/extension/custom/zoucha/ui/browse.html.php zentao_zoucha/extension/custom/zoucha/css/zoucha.css zentao_zoucha/extension/custom/zoucha/js/zoucha.js
git commit -m "feat(zoucha): 完善列表模板、彩色标签、筛选与分页样式"
```

---

## Task 6: 整体人工验证与打包

在运行中的禅道实例端到端验证 5 条规则，修复联调问题，产出可分发 zip。

**Files:**
- 无新增代码文件（如发现 bug 在对应文件修复并补测）
- Modify（可选）: 仓库根 `pack_plugins.ps1` 增加 zoucha 打包条目（若该脚本驱动打包）

- [ ] **Step 1: 全量语法校验 + 单测**

Run:
```bash
find zentao_zoucha -name "*.php" -print0 | xargs -0 -n1 php -l && php zentao_zoucha/tests/test_rules.php
```
Expected: 全部 `No syntax errors detected`，单测 `ALL PASS`。

- [ ] **Step 2: 打包并安装**

把 `zentao_zoucha` 目录打成 zip（结构与 `zentao_taizhang.zip` 一致：插件内容在压缩包内），禅道后台「应用 → 本地安装」。预期：安装成功，无 SQL/权限报错。

- [ ] **Step 3: 逐规则造数据验证**

在禅道里准备样本，打开「项目走查」逐项核对（必要时用 `zoucha-diagnostic?projectID=xxx` 看判定明细）：
- 建一个无任何执行的项目 → 命中「无迭代」；
- 建一个有执行但执行下无任务的项目 → 命中「无任务」；
- 某项目所有任务最近编辑都在 8 天前 → 命中「近一周未更新」；当其中一个任务今天编辑 → 该标签消失；
- 某项目含一个 deadline 早于今天且未完成的任务 → 命中「任务延期」，标签显示逾期数；
- 某项目含一个 estStarted→deadline 跨度 16 天的任务 → 命中「任务超期」；
- 已关闭项目不出现在列表；已关闭执行里的遗留逾期任务不触发「任务延期」。

- [ ] **Step 4: 验证筛选 / 分页 / 导出**

- 顶部下拉切换问题类型，列表与「问题项目数」随之变化，翻页保持筛选；
- 点「导出Excel」下载 CSV，用 Excel 打开无乱码，列与页面一致。

- [ ] **Step 5: 验证卸载**

后台卸载插件；确认菜单「项目走查」消失（如菜单残留，按既有约定清 `tmp/cache`）；`zt_grouppriv` 中 `module='zoucha'` 行被清除。

- [ ] **Step 6: 记录结果并提交（如有修复）**

```bash
git add -A
git commit -m "test(zoucha): 端到端验证 5 条走查规则并修复联调问题"
```

> 若无可用禅道实例，Step 2–5 记为"待人工验证"，并在交付说明中标注；Step 1 作为可自动执行的最低门禁。

---

## Self-Review

**1. Spec coverage（设计文档 → 任务映射）**
- 5 条规则（第二节）→ Task 2 纯引擎 + 单测；阈值配置（第三节）→ Task 1 Step 10；实时查询无表（第四节）→ Task 3；文件结构（第五节）→ Task 1 全量；列表页列与筛选/导出（第六节）→ Task 4 + Task 5；权限与导航（第七节）→ Task 1 Step 5–9；测试与 diagnostic（第八节）→ Task 4 diagnostic + Task 6；非目标（第九节，不落库/不运行时调阈值/不做执行维度/不做 API）→ 计划未引入，符合。
- 排除已关闭执行下的任务 → Task 2 由调用方过滤 + Task 3 `closedExecIDs` 过滤实现，单测覆盖脏数据。

**2. Placeholder scan**：Task 1 的 model/ui 为显式声明的"占位骨架"，后续任务替换；除此之外无 TODO/TBD/"add error handling"等空泛步骤，每个代码步骤均含完整代码。

**3. Type consistency**：
- `zouchaRules::evaluate(...)` 七参签名在 Task 2 定义、Task 3（inspect/getInspectData）一致调用。
- `inspect()` 产出字段（projectID/projectName/programName/pm/pmName/status/statusName/hits/taskCount/overdueCount/executionCount/lastTaskEdited）在 Task 3 组装、Task 4 导出、Task 5 渲染中名称一致。
- 视图变量集合（results/rule/ruleCounts/pageID/pageTotal/recPerPage/total/totalAll）Task 4 赋值、Task 5 读取一致；Task 1 占位控制器未设 `totalAll`/`ruleCounts` 完整值，但其占位模板也不读取它们，无冲突（Task 4/5 同时升级）。
- 规则键集合（noTask/stale/overdue/noExecution/longTask）在 config、lang、引擎、模板中全一致。
