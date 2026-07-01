# 项目周报插件（zhoubao）MVP 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 交付一个可安装、可用的禅道"项目周报"插件 MVP：项目经理为其负责项目按自然周填周报，自动罗列本周完成/未完成/逾期任务，汇总看板做未填报提醒，并通过企微群机器人定时推送。

**Architecture:** 独立禅道扩展插件 `zhoubao`，顶级主导航入口。数据仅新增 `zt_zhoubao` 表；自动汇总数据草稿态实时算、提交时冻结为 `snapshot` JSON。任务分类与企微消息组装放无框架依赖的 `lib/zhoubaoRules.php`（真正单测），其余禅道集成靠装入实例逐页验证。

**Tech Stack:** PHP 8.1，禅道 20.0+/22.x 框架（DAO 查询构造器、ZIN `ui/` 模板、control/model 分层），零外部依赖单测 harness。

## Global Constraints

- 插件 code：`zhoubao`；插件名：`项目周报`；作者：`李永杰`；email：`wukuili@gmail.com`。
- 目标禅道版本：`>=20.0`（兼容 22.x）。
- 一份周报 = 一个项目 × 一个自然周（周一 00:00 ~ 周日 23:59，ISO 周）。
- 顶级主导航用 `$lang->mainNav->{code}` 字符串 `"标题|模块|方法|"` + `$lang->mainNav->menuOrder[N]`；N 取 `63` 之外的空闲位——**注意 zoucha 已占 63**，本插件用 `61`（避开 OA=62/台账=64/后台=65/走查=63）。
- URL 日期参数用 `_` 分隔（如 `2026_06_29`），控制器 `str_replace('_','-',...)` 还原。
- PATH_INFO 路由按位置映射形参：分页/筛选参数必须声明为控制器方法形参，并额外从 `$_GET`/`$_POST` 兜底读取。
- install/uninstall SQL 任何注释内不得出现 `;`。
- 生 XHR POST 必须带 `X-Requested-With: XMLHttpRequest` 头；ZIN 内联脚本加防重复绑定守卫（`if(window.__zhoubaoBound) return; window.__zhoubaoBound = true;`）。
- 活跃项目判定：`TABLE_PROJECT where type='project' and deleted='0' and status != 'closed'`。
- 已关闭执行下的任务排除（`execution=0` 保留），逻辑同 zoucha。
- 权限：PM 仅能编辑 `project.PM == 当前账号` 的项目；`manage` 权限者可看全部并触发推送。

---

### Task 1: 插件骨架与安装（可安装的空插件 + 顶级导航）

交付：把插件装入禅道后，清缓存即可在最外层主导航看到"项目周报"入口，点进去是一个空 browse 页（无报错），`zt_zhoubao` 表已建。

**Files:**
- Create: `zentao_zhoubao/plugin.json`
- Create: `zentao_zhoubao/doc/zh-cn.yaml`
- Create: `zentao_zhoubao/db/install.sql`
- Create: `zentao_zhoubao/db/uninstall.sql`
- Create: `zentao_zhoubao/extension/custom/common/ext/config/zhoubao.php`
- Create: `zentao_zhoubao/extension/custom/common/ext/lang/zh-cn/zhoubao.php`
- Create: `zentao_zhoubao/extension/custom/group/ext/config/zhoubao.php`
- Create: `zentao_zhoubao/extension/custom/group/ext/lang/zh-cn/zhoubao.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/config.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/lang/zh-cn.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/control.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`

**Interfaces:**
- Produces: 模块 `zhoubao`，方法 `browse`；表 `zt_zhoubao`；配置对象 `$config->zhoubao`（`wecomWebhook`/`pushToken`/`weekStartField`）。

- [ ] **Step 1: 写 plugin.json**

```json
{
    "code": "zhoubao",
    "name": "项目周报",
    "version": "1.0",
    "zentaoVersion": ">=20.0",
    "author": "李永杰",
    "email": "wukuili@gmail.com",
    "description": "项目维度周报插件：项目经理按自然周填写周报，自动罗列本周完成/未完成/逾期任务与进度工时，汇总看板做未填报提醒，支持一键复制上周、导出、走查联动，并通过企微群机器人定时推送填报情况。",
    "type": "extension",
    "depend": [],
    "incompatible": []
}
```

- [ ] **Step 2: 写 doc/zh-cn.yaml**

```yaml
# 基本信息。
name: 项目周报
code: zhoubao
type: extension
copyright: 神思电子技术股份有限公司 2026
site: https://www.sdses.com
author: 李永杰
abstract: 禅道项目周报（项目维度）插件
# 描述和安装文档
desc: |
  项目维度周报插件，项目经理按自然周为负责项目填写周报，自动罗列本周完成/未完成/逾期任务与进度工时。汇总看板做未填报提醒，支持一键复制上周手写内容、导出、走查联动，并通过企微群机器人定时推送当周填报情况与摘要。
install: |
  后台本地安装
# 版本信息。
releases:
  1.0:
    zentaoversion: 20.0+,biz10.0+,max5.0+,ipd2.0+
    charge: free
    changelog: 初始版本，项目周报填报、汇总看板提醒、企微定时推送。
    date: 2026-07-01
```

- [ ] **Step 3: 写 db/install.sql（注释内无分号）**

```sql
CREATE TABLE IF NOT EXISTS `zt_zhoubao` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `project` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `year` smallint(6) NOT NULL DEFAULT '0',
  `week` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `weekStart` date DEFAULT NULL,
  `account` varchar(30) NOT NULL DEFAULT '',
  `nextPlan` text,
  `risk` text,
  `summary` text,
  `snapshot` mediumtext,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'draft',
  `submittedDate` datetime DEFAULT NULL,
  `createdDate` datetime DEFAULT NULL,
  `editedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_week` (`project`,`year`,`week`),
  KEY `idx_week` (`year`,`week`),
  KEY `idx_account` (`account`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```

- [ ] **Step 4: 写 db/uninstall.sql（仅删本插件表）**

```sql
-- 卸载项目周报插件，仅删除本插件自有表，不动禅道共享表
DROP TABLE IF EXISTS `zt_zhoubao`;
```

- [ ] **Step 5: 写 common/ext/config/zhoubao.php（应用与权限注册）**

```php
<?php
if(!isset($config)) $config = new stdclass();
if(!isset($config->zhoubao)) $config->zhoubao = new stdclass();

/* 声明本模块包含的权限方法 */
$config->zhoubao->includedPriv['zhoubao'] = array('browse', 'edit', 'view', 'export', 'copyLast', 'cronPush', 'manage');

/* 注册新应用到系统 config */
if(!isset($config->apps)) $config->apps = new stdclass();
$config->apps->zhoubao = 'zhoubao';

if(!isset($config->appsMenu)) $config->appsMenu = new stdclass();
$config->appsMenu->zhoubao = 'zhoubao';
```

- [ ] **Step 6: 写 common/ext/lang/zh-cn/zhoubao.php（顶级主导航注册，menuOrder[61]）**

```php
<?php
/**
 * 顶级主导航注册 —— 在最外层主导航栏新增独立入口「项目周报」。
 * 机制见 module/common/model.php::getMainNavList：只遍历 menuOrder，
 * 每项为字符串 "标题|模块|方法|参数"，显示与否由 common::hasPriv 决定。
 */
if(!isset($lang->navIcons))     $lang->navIcons     = array();
if(!isset($lang->navIconNames)) $lang->navIconNames = array();
$lang->navIcons['zhoubao']     = "<i class='icon icon-calendar'></i>";
$lang->navIconNames['zhoubao'] = 'calendar';

/* 顶级主导航项。下标避开 OA=62/台账=64/后台=65/走查=63，用空闲的 61。 */
if(!isset($lang->mainNav)) $lang->mainNav = new stdclass();
$lang->mainNav->zhoubao        = "{$lang->navIcons['zhoubao']} 项目周报|zhoubao|browse|";
$lang->mainNav->menuOrder[61]  = 'zhoubao';

/* 进入 zhoubao 应用后的顶部一级导航标签 */
if(!isset($lang->zhoubao)) $lang->zhoubao = new stdclass();
if(!isset($lang->zhoubao->menu)) $lang->zhoubao->menu = new stdclass();
$lang->zhoubao->menu->browse = array('link' => '周报看板|zhoubao|browse', 'order' => 5);
$lang->zhoubao->menuOrder[5] = 'browse';

/* 导航高亮分组：自身归属自身 */
if(!isset($lang->navGroup)) $lang->navGroup = new stdclass();
$lang->navGroup->zhoubao = 'zhoubao';
```

- [ ] **Step 7: 写 group/ext/config/zhoubao.php 与 group/ext/lang/zh-cn/zhoubao.php（权限包）**

`group/ext/config/zhoubao.php`：
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
    $config->group->package->zhoubaoother->privs['zhoubao-cronPush'] = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 10, 'depend' => array());
    $config->group->package->zhoubaoother->privs['zhoubao-manage']   = array('edition' => 'open,biz,max,ipd', 'vision' => 'rnd', 'order' => 11, 'depend' => array());
}
```

`group/ext/lang/zh-cn/zhoubao.php`：
```php
<?php
/* 将周报作为独立的视图权限暴露出来 */
$lang->resource->zhoubao = new stdclass();
$lang->resource->zhoubao->browse   = '浏览周报看板';
$lang->resource->zhoubao->edit     = '填写/编辑周报';
$lang->resource->zhoubao->view     = '查看周报';
$lang->resource->zhoubao->export   = '导出周报';
$lang->resource->zhoubao->copyLast = '复制上周手写内容';
$lang->resource->zhoubao->cronPush = '企微定时推送';
$lang->resource->zhoubao->manage   = '周报管理（全局/推送）';
```

- [ ] **Step 8: 写 zhoubao/config.php（企微与默认参数）**

```php
<?php
if(!isset($config->zhoubao)) $config->zhoubao = new stdclass();

/* 企业微信群机器人 webhook（部署时在 config/ext 覆盖为真实地址） */
$config->zhoubao->wecomWebhook = '';
/* cronPush 防未授权调用的 token（部署时覆盖） */
$config->zhoubao->pushToken = 'CHANGE_ME';
/* 推送提示时间，仅文档展示，实际由 cron 决定 */
$config->zhoubao->pushDay  = 5;
$config->zhoubao->pushTime = '17:00';
```

- [ ] **Step 9: 写 zhoubao/lang/zh-cn.php（页面文案）**

```php
<?php
$lang->zhoubao = new stdclass();
$lang->zhoubao->common      = '项目周报';
$lang->zhoubao->browseTitle = '周报看板';
$lang->zhoubao->editTitle   = '填写周报';
$lang->zhoubao->viewTitle   = '查看周报';

$lang->zhoubao->project     = '项目';
$lang->zhoubao->pm          = '项目经理';
$lang->zhoubao->week        = '周次';
$lang->zhoubao->fillStatus  = '填报状态';
$lang->zhoubao->doneCount   = '本周完成';
$lang->zhoubao->overdueCount= '逾期任务';
$lang->zhoubao->actions     = '操作';

$lang->zhoubao->statusList  = array('none' => '缺交', 'draft' => '草稿', 'submitted' => '已交');

$lang->zhoubao->doneTasks   = '本周完成任务';
$lang->zhoubao->undoneTasks = '本周未完成/逾期任务';
$lang->zhoubao->statOverview= '进度/工时概览';
$lang->zhoubao->nextPlan    = '下周计划';
$lang->zhoubao->risk        = '风险与需协调资源';
$lang->zhoubao->summary     = '本周小结';

$lang->zhoubao->saveDraft   = '保存草稿';
$lang->zhoubao->submit      = '提交';
$lang->zhoubao->copyLast    = '复制上周手写内容';
$lang->zhoubao->writeReport = '写周报';
$lang->zhoubao->viewReport  = '查看';
$lang->zhoubao->pushNow     = '立即推送企微';
$lang->zhoubao->export      = '导出';

$lang->zhoubao->prevWeek    = '上一周';
$lang->zhoubao->thisWeek    = '本周';
$lang->zhoubao->nextWeek    = '下一周';
```

- [ ] **Step 10: 写最小 control.php（仅 browse，渲染空看板）**

```php
<?php
class zhoubao extends control
{
    /**
     * 周报看板（默认入口）。
     * @param string $week 周起始日（周一，格式 2026_06_29，'' 表示本周）
     * @param string $pm   项目经理筛选（'' 或 'all' 表示全部）
     * @param string $fill 填报状态筛选（'' / all / none / draft / submitted）
     */
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
}
```

- [ ] **Step 11: 写最小 model.php（占位方法，Task 4 填实）**

```php
<?php
class zhoubaoModel extends model
{
    /* 把 week 参数（2026_06_29 或 ''）解析成本周一的 Y-m-d，Task 4 会补全边界逻辑 */
    public function resolveWeekStart($week = '')
    {
        if($week === '' || $week === false) return date('Y-m-d', strtotime('monday this week'));
        return str_replace('_', '-', $week);
    }

    /* 看板行，Task 4 填实。此处返回空数组保证 browse 可渲染 */
    public function getBoardRows($weekStart, $pm = '', $fill = '')
    {
        return array();
    }
}
```

- [ ] **Step 12: 写最小 ui/browse.html.php（能渲染标题即可，Task 4 换成 dtable）**

```php
<?php
namespace zin;

$weekStart = $this->view->weekStart;
$rows      = $this->view->rows;

panel(
  set::title($this->lang->zhoubao->browseTitle . ' · ' . $weekStart),
  empty($rows) ? p('本周暂无活跃项目周报数据。') : div(json_encode($rows))
);
```

- [ ] **Step 13: 安装验证（无自动化测试，人工步骤）**

将 `zentao_zhoubao/extension/*`、`config/ext` 无、`db/install.sql` 导入目标禅道；清 `tmp/cache/*` 与 `tmp/model/*`。
预期：最外层主导航出现"项目周报"；点击进入 `zhoubao-browse` 显示"周报看板 · <日期>"标题，无 PHP 报错；数据库存在 `zt_zhoubao` 表。

- [ ] **Step 14: 提交**

```bash
git add zentao_zhoubao/
git commit -m "feat(zhoubao): 插件骨架、zt_zhoubao 表与顶级主导航入口"
```

---

### Task 2: 规则库 classifyTasks（TDD，真正单测）

交付：`lib/zhoubaoRules.php` 的 `classifyTasks()`，把任务数组分成 done/undone/overdue，并有通过的零依赖单测。

**Files:**
- Create: `zentao_zhoubao/extension/custom/zhoubao/lib/zhoubaoRules.php`
- Create: `zentao_zhoubao/tests/test_rules.php`

**Interfaces:**
- Produces: `class zhoubaoRules`，静态方法
  `zhoubaoRules::classifyTasks(array $tasks, string $weekStart, string $weekEnd, string $today): array`
  返回 `['done'=>array, 'undone'=>array, 'overdue'=>array]`。每个元素是原始任务数组，overdue 元素额外含 `daysOverdue` 整数。
  任务数组字段：`id, name, assignedTo, status, deadline, finishedDate, consumed, left`。

- [ ] **Step 1: 写失败测试 tests/test_rules.php**

```php
<?php
require __DIR__ . '/../extension/custom/zhoubao/lib/zhoubaoRules.php';

$fail = 0;
function check($cond, $msg){ global $fail; if($cond){ echo "PASS: $msg\n"; } else { echo "FAIL: $msg\n"; $fail++; } }

$weekStart = '2026-06-29'; // 周一
$weekEnd   = '2026-07-05'; // 周日
$today     = '2026-07-01';

$tasks = array(
    array('id'=>1,'name'=>'A','assignedTo'=>'zhang','status'=>'done',  'deadline'=>'2026-07-02','finishedDate'=>'2026-06-30 10:00:00','consumed'=>8,'left'=>0),
    array('id'=>2,'name'=>'B','assignedTo'=>'li',   'status'=>'doing', 'deadline'=>'2026-07-03','finishedDate'=>'0000-00-00 00:00:00','consumed'=>2,'left'=>6),
    array('id'=>3,'name'=>'C','assignedTo'=>'wang', 'status'=>'doing', 'deadline'=>'2026-06-25','finishedDate'=>'0000-00-00 00:00:00','consumed'=>1,'left'=>4),
    array('id'=>4,'name'=>'D','assignedTo'=>'zhao', 'status'=>'closed','deadline'=>'2026-05-01','finishedDate'=>'2026-05-01 09:00:00','consumed'=>3,'left'=>0),
    array('id'=>5,'name'=>'E','assignedTo'=>'sun',  'status'=>'cancel','deadline'=>'2026-06-20','finishedDate'=>'0000-00-00 00:00:00','consumed'=>0,'left'=>0),
    array('id'=>6,'name'=>'F','assignedTo'=>'qian', 'status'=>'wait',  'deadline'=>'0000-00-00','finishedDate'=>'0000-00-00 00:00:00','consumed'=>0,'left'=>5),
);

$r = zhoubaoRules::classifyTasks($tasks, $weekStart, $weekEnd, $today);
$ids = function($list){ return array_map(function($t){ return $t['id']; }, $list); };

check($ids($r['done'])    == array(1),  '本周完成含任务1（本周内 finishedDate）');
check(!in_array(4, $ids($r['done'])),   '任务4 完成于本周之外不计入 done');
check($ids($r['undone'])  == array(2),  '本周应完成未完成含任务2');
check($ids($r['overdue']) == array(3),  '逾期含任务3（deadline<today）');
check(!in_array(5, $ids($r['overdue'])),'已取消任务不计逾期');
check(!in_array(6, array_merge($ids($r['undone']),$ids($r['overdue']))),'脏/空 deadline 不计 undone/overdue');
$over3 = null; foreach($r['overdue'] as $t){ if($t['id']==3) $over3=$t; }
check($over3 && $over3['daysOverdue'] === 6, '任务3 逾期天数=6');

echo $fail === 0 ? "\nALL PASSED\n" : "\n$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd zentao_zhoubao && php tests/test_rules.php`
Expected: FAIL —— `Class "zhoubaoRules" not found`（文件尚未创建）。

- [ ] **Step 3: 写最小实现 lib/zhoubaoRules.php**

```php
<?php
/**
 * 项目周报规则库 —— 无框架依赖，纯静态方法，可脱离禅道运行时单元测试。
 */
class zhoubaoRules
{
    /* 判断日期字符串是否为有效非脏日期 */
    public static function isValidDate($date)
    {
        if(empty($date)) return false;
        $d = substr((string)$date, 0, 10);
        if($d === '0000-00-00') return false;
        return strtotime($d) !== false;
    }

    /* 把任务分为本周完成 / 本周应完成未完成 / 逾期 */
    public static function classifyTasks(array $tasks, $weekStart, $weekEnd, $today)
    {
        $done = array(); $undone = array(); $overdue = array();
        $doneStatus = array('done', 'closed');
        $deadStatus = array('done', 'closed', 'cancel');

        foreach($tasks as $task)
        {
            $status   = isset($task['status']) ? $task['status'] : '';
            $deadline = isset($task['deadline']) ? substr((string)$task['deadline'], 0, 10) : '';
            $finished = isset($task['finishedDate']) ? substr((string)$task['finishedDate'], 0, 10) : '';

            // 本周完成：状态为 done/closed 且完成日落在本周内
            if(in_array($status, $doneStatus) && self::isValidDate($finished)
               && $finished >= $weekStart && $finished <= $weekEnd)
            {
                $done[] = $task;
                continue;
            }

            // 已完成/取消的不再计入未完成或逾期
            if(in_array($status, $deadStatus)) continue;
            if(!self::isValidDate($deadline)) continue;

            if($deadline < $today)
            {
                $daysOverdue = (int)floor((strtotime($today) - strtotime($deadline)) / 86400);
                $task['daysOverdue'] = $daysOverdue;
                $overdue[] = $task;
            }
            elseif($deadline >= $weekStart && $deadline <= $weekEnd)
            {
                $undone[] = $task;
            }
        }

        return array('done' => $done, 'undone' => $undone, 'overdue' => $overdue);
    }
}
```

- [ ] **Step 4: 运行测试确认通过**

Run: `cd zentao_zhoubao && php tests/test_rules.php`
Expected: `ALL PASSED`，退出码 0。

- [ ] **Step 5: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/lib/zhoubaoRules.php zentao_zhoubao/tests/test_rules.php
git commit -m "feat(zhoubao): classifyTasks 任务分类规则库 + 单测"
```

---

### Task 3: 规则库 buildWecomMarkdown（TDD）

交付：`zhoubaoRules::buildWecomMarkdown()` 把看板摘要行组装成企微 markdown 文本，含单测。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/lib/zhoubaoRules.php`
- Modify: `zentao_zhoubao/tests/test_rules.php`

**Interfaces:**
- Consumes: `zhoubaoRules`（Task 2）。
- Produces: `zhoubaoRules::buildWecomMarkdown(array $rows, int $year, int $week): string`
  `rows` 每项：`['project'=>string,'pm'=>string,'status'=>'none'|'draft'|'submitted','doneCount'=>int,'overdueCount'=>int]`。
  返回含标题、已交/缺交统计、缺交名单、已交摘要的 markdown 字符串。

- [ ] **Step 1: 追加失败测试到 tests/test_rules.php（在 exit 前）**

```php
$rows = array(
    array('project'=>'AA','pm'=>'张三','status'=>'submitted','doneCount'=>8,'overdueCount'=>1),
    array('project'=>'BB','pm'=>'李四','status'=>'none',     'doneCount'=>0,'overdueCount'=>0),
    array('project'=>'CC','pm'=>'王五','status'=>'draft',    'doneCount'=>2,'overdueCount'=>0),
);
$md = zhoubaoRules::buildWecomMarkdown($rows, 2026, 27);
check(strpos($md, '第27周') !== false,       '含周次标题');
check(strpos($md, '已交 1') !== false,         '已交计数=1（仅 submitted）');
check(strpos($md, '缺交 2') !== false,         '缺交计数=2（none+draft）');
check(strpos($md, 'BB') !== false && strpos($md, '李四') !== false, '缺交名单含 BB/李四');
check(strpos($md, 'AA') !== false,             '已交摘要含 AA');
```

- [ ] **Step 2: 运行测试确认新用例失败**

Run: `cd zentao_zhoubao && php tests/test_rules.php`
Expected: FAIL —— `Call to undefined method zhoubaoRules::buildWecomMarkdown()`。

- [ ] **Step 3: 在 zhoubaoRules 内实现 buildWecomMarkdown**

```php
    /* 组装企微群机器人 markdown 文本。已交=submitted；缺交=none+draft */
    public static function buildWecomMarkdown(array $rows, $year, $week)
    {
        $submitted = array(); $missing = array();
        foreach($rows as $r)
        {
            if(isset($r['status']) && $r['status'] === 'submitted') $submitted[] = $r;
            else $missing[] = $r;
        }

        $lines = array();
        $lines[] = "**【项目周报提醒 · 第{$week}周】**";
        $lines[] = "✅ 已交 " . count($submitted) . " / ❌ 缺交 " . count($missing);

        if(!empty($missing))
        {
            $names = array();
            foreach($missing as $r) $names[] = "{$r['project']}（{$r['pm']}）";
            $lines[] = "> 缺交：" . implode('、', $names);
        }
        if(!empty($submitted))
        {
            $lines[] = "> 已交摘要：";
            foreach($submitted as $r)
            {
                $lines[] = "> - {$r['project']} 完成{$r['doneCount']}任务 / 逾期{$r['overdueCount']}";
            }
        }
        return implode("\n", $lines);
    }
```

- [ ] **Step 4: 运行测试确认全部通过**

Run: `cd zentao_zhoubao && php tests/test_rules.php`
Expected: `ALL PASSED`。

- [ ] **Step 5: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/lib/zhoubaoRules.php zentao_zhoubao/tests/test_rules.php
git commit -m "feat(zhoubao): buildWecomMarkdown 企微消息组装 + 单测"
```

---

### Task 4: 看板数据查询与页面（model + browse + ui）

交付：`zhoubao-browse` 显示本周所有活跃项目一行一条，含 PM、填报状态、完成数、逾期数，支持周切换/PM/状态筛选。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`

**Interfaces:**
- Consumes: `zhoubaoRules::classifyTasks`（Task 2）。
- Produces（model 公开方法，供 control/其它 task 复用）：
  - `getWeekRange(string $weekStart): array` → `['start'=>Y-m-d,'end'=>Y-m-d,'year'=>int,'week'=>int]`
  - `getActiveProjects(): array` → `id => {id,name,PM}`
  - `getProjectTasks(array $projectIDs): array` → `projectID => task[]`（已排除关闭执行下任务；task 为关联数组含 classifyTasks 所需字段）
  - `getReportMap(int $year, int $week): array` → `projectID => zt_zhoubao 行对象`
  - `getBoardRows(string $weekStart, string $pm, string $fill): array` → 每行 `{project,projectName,pm,status,doneCount,overdueCount,reportID}`

- [ ] **Step 1: 实现 model 数据方法（替换 Task 1 的占位 getBoardRows）**

```php
    /* 由周一日期得到本周范围与 ISO 年/周 */
    public function getWeekRange($weekStart)
    {
        $start = $weekStart;
        $end   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        return array(
            'start' => $start,
            'end'   => $end,
            'year'  => (int)date('o', strtotime($weekStart)),
            'week'  => (int)date('W', strtotime($weekStart)),
        );
    }

    /* 活跃项目：type=project、未删除、非关闭 */
    public function getActiveProjects()
    {
        return $this->dao->select('id, name, PM')->from(TABLE_PROJECT)
            ->where('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->ne('closed')
            ->fetchAll('id');
    }

    /* 项目任务，排除已关闭执行下的任务（execution=0 保留） */
    public function getProjectTasks($projectIDs)
    {
        if(empty($projectIDs)) return array();

        $closedExec = $this->dao->select('id')->from(TABLE_EXECUTION)
            ->where('project')->in($projectIDs)
            ->andWhere('`type`')->in('sprint,stage,kanban')
            ->andWhere('deleted')->eq('0')
            ->andWhere('status')->eq('closed')
            ->fetchPairs('id', 'id');

        $tasks = $this->dao->select('id, project, execution, name, assignedTo, status, deadline, finishedDate, consumed, `left`')
            ->from(TABLE_TASK)
            ->where('project')->in($projectIDs)
            ->andWhere('deleted')->eq('0')
            ->fetchAll();

        $byProject = array();
        foreach($tasks as $task)
        {
            $execID = (int)$task->execution;
            if($execID !== 0 && isset($closedExec[$execID])) continue;
            $byProject[(int)$task->project][] = (array)$task;
        }
        return $byProject;
    }

    /* 某年某周所有已存周报，projectID => 行对象 */
    public function getReportMap($year, $week)
    {
        return $this->dao->select('*')->from('zt_zhoubao')
            ->where('year')->eq($year)
            ->andWhere('week')->eq($week)
            ->fetchAll('project');
    }

    /* 看板行 */
    public function getBoardRows($weekStart, $pm = '', $fill = '')
    {
        $range    = $this->getWeekRange($weekStart);
        $today    = date('Y-m-d');
        $projects = $this->getActiveProjects();
        if(empty($projects)) return array();

        $tasksByProject = $this->getProjectTasks(array_keys($projects));
        $reportMap      = $this->getReportMap($range['year'], $range['week']);

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
                'status'       => $status,
                'doneCount'    => count($cls['done']),
                'overdueCount' => count($cls['overdue']),
                'reportID'     => $report ? $report->id : 0,
            );
        }
        return $rows;
    }
```

- [ ] **Step 2: 用 dtable 重写 ui/browse.html.php**

```php
<?php
namespace zin;

$statusLabel = $this->lang->zhoubao->statusList;
$data = array();
foreach($this->rows as $r)
{
    $label = zget($statusLabel, $r->status, $r->status);
    $action = $r->status === 'submitted'
        ? html("<a href='" . inlink('view', "id={$r->reportID}") . "'>{$this->lang->zhoubao->viewReport}</a>")
        : html("<a href='" . inlink('edit', "project={$r->project}&week=" . str_replace('-', '_', $this->weekStart)) . "'>{$this->lang->zhoubao->writeReport}</a>");
    $data[] = array(
        'projectName'  => $r->projectName,
        'pm'           => $r->pm,
        'fillStatus'   => $label,
        'doneCount'    => $r->doneCount,
        'overdueCount' => $r->overdueCount,
        'actions'      => $action,
    );
}

$cols = array(
    array('name' => 'projectName',  'title' => $this->lang->zhoubao->project),
    array('name' => 'pm',           'title' => $this->lang->zhoubao->pm),
    array('name' => 'fillStatus',   'title' => $this->lang->zhoubao->fillStatus),
    array('name' => 'doneCount',    'title' => $this->lang->zhoubao->doneCount),
    array('name' => 'overdueCount', 'title' => $this->lang->zhoubao->overdueCount),
    array('name' => 'actions',      'title' => $this->lang->zhoubao->actions),
);

panel(
  set::title($this->lang->zhoubao->browseTitle . ' · ' . $this->weekStart),
  dtable(set::cols($cols), set::data($data))
);
```

- [ ] **Step 3: 实例验证（人工）**

装入实例、清缓存后进入 `zhoubao-browse`：
预期：列表列出所有进行中项目，每行显示 PM、填报状态（缺交/草稿/已交）、本周完成数、逾期数；有周报的项目"操作"列为"查看"，否则"写周报"。改 URL `?pm=<某账号>` 与 `?fill=submitted` 应各自过滤。

- [ ] **Step 4: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/model.php zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php
git commit -m "feat(zhoubao): 看板数据查询与列表页（周切换/PM/状态筛选）"
```

---

### Task 5: 写/编辑周报（自动区实时 + 手写区 + 保存/提交快照）

交付：`zhoubao-edit&project=&week=` 显示自动区（完成/未完成/逾期/概览，实时）与手写区，保存草稿走 AJAX，提交时写 `snapshot` 并置 submitted。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/js/zhoubao.js`
- Create: `zentao_zhoubao/extension/custom/zhoubao/css/zhoubao.css`

**Interfaces:**
- Consumes: `getWeekRange`,`getProjectTasks`,`zhoubaoRules::classifyTasks`（Task 2/4）。
- Produces:
  - `zhoubaoModel::buildAutoData(int $project, string $weekStart): array` → `['done'=>[],'undone'=>[],'overdue'=>[],'stat'=>['progress'=>int,'weekConsumed'=>float,'totalLeft'=>float,'doneCount'=>int,'overdueCount'=>int]]`
  - `zhoubaoModel::getReport(int $project, string $weekStart): object|null`
  - `zhoubaoModel::saveReport(int $project, string $weekStart, array $post, string $account, bool $submit): int|false`
  - control：`edit($project, $week)`（GET 渲染 / POST 保存后 `send`）

- [ ] **Step 1: model 增加自动区与存取方法**

```php
    /* 本周消耗工时：zt_effort.date 落在本周的 consumed 之和 */
    public function getWeekEffort($project, $start, $end)
    {
        return (float)$this->dao->select('SUM(consumed) AS v')->from(TABLE_EFFORT)
            ->where('project')->eq($project)
            ->andWhere('date')->ge($start)
            ->andWhere('date')->le($end)
            ->andWhere('deleted')->eq('0')
            ->fetch('v');
    }

    /* 组装自动区数据（实时） */
    public function buildAutoData($project, $weekStart)
    {
        $range = $this->getWeekRange($weekStart);
        $today = date('Y-m-d');
        $tasksByProject = $this->getProjectTasks(array($project));
        $tasks = isset($tasksByProject[$project]) ? $tasksByProject[$project] : array();
        $cls   = zhoubaoRules::classifyTasks($tasks, $range['start'], $range['end'], $today);

        $totalLeft = 0;
        foreach($cls['undone'] as $t)  $totalLeft += (float)(isset($t['left']) ? $t['left'] : 0);
        foreach($cls['overdue'] as $t) $totalLeft += (float)(isset($t['left']) ? $t['left'] : 0);

        $progress = (int)$this->dao->select('progress')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch('progress');

        $cls['stat'] = array(
            'progress'     => $progress,
            'weekConsumed' => $this->getWeekEffort($project, $range['start'], $range['end']),
            'totalLeft'    => $totalLeft,
            'doneCount'    => count($cls['done']),
            'overdueCount' => count($cls['overdue']),
        );
        return $cls;
    }

    public function getReport($project, $weekStart)
    {
        $range = $this->getWeekRange($weekStart);
        return $this->dao->select('*')->from('zt_zhoubao')
            ->where('project')->eq($project)
            ->andWhere('year')->eq($range['year'])
            ->andWhere('week')->eq($range['week'])
            ->fetch();
    }

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
        if($submit)
        {
            $data->status        = 'submitted';
            $data->submittedDate = $now;
            $data->snapshot      = json_encode($this->buildAutoData($project, $weekStart));
        }

        if($exist)
        {
            $this->dao->update('zt_zhoubao')->data($data)->where('id')->eq($exist->id)->exec();
            return dao::isError() ? false : $exist->id;
        }

        $data->project   = $project;
        $data->year      = $range['year'];
        $data->week      = $range['week'];
        $data->weekStart = $range['start'];
        $data->account   = $account;
        $data->status    = $submit ? 'submitted' : 'draft';
        $data->createdDate = $now;
        $this->dao->insert('zt_zhoubao')->data($data)->exec();
        return dao::isError() ? false : $this->dao->lastInsertID();
    }
```

- [ ] **Step 2: control 增加 edit（GET 渲染 / POST 保存）**

```php
    /**
     * 填写/编辑周报。
     * @param int    $project 项目 ID
     * @param string $week    周起始日（2026_06_29，'' 表示本周）
     */
    public function edit($project, $week = '')
    {
        $project   = (int)$project;
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);

        $projectInfo = $this->dao->select('id, name, PM')->from(TABLE_PROJECT)->where('id')->eq($project)->fetch();
        if(!$projectInfo) return $this->send(array('result' => 'fail', 'message' => '项目不存在'));

        /* 权限：非 manage 权限者只能写自己负责的项目 */
        $canManage = common::hasPriv('zhoubao', 'manage');
        if(!$canManage && $projectInfo->PM !== $this->app->user->account)
        {
            return $this->send(array('result' => 'fail', 'message' => '仅项目经理可填写本项目周报'));
        }

        if($this->server->request_method == 'POST')
        {
            $submit = !empty($_POST['submit']);
            $id = $this->zhoubao->saveReport($project, $weekStart, $_POST, $this->app->user->account, $submit);
            if($id === false) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            $locate = $submit ? inlink('view', "id=$id") : inlink('edit', "project=$project&week=" . str_replace('-', '_', $weekStart));
            return $this->send(array('result' => 'success', 'locate' => $locate));
        }

        $this->view->title       = $this->lang->zhoubao->editTitle;
        $this->view->projectInfo = $projectInfo;
        $this->view->weekStart   = $weekStart;
        $this->view->auto        = $this->zhoubao->buildAutoData($project, $weekStart);
        $this->view->report      = $this->zhoubao->getReport($project, $weekStart);
        $this->display();
    }
```

- [ ] **Step 3: 写 ui/edit.html.php（自动区只读 + 手写区表单）**

```php
<?php
namespace zin;

$auto   = $this->auto;
$report = $this->report;
$weekParam = str_replace('-', '_', $this->weekStart);

$taskRows = function($list, $overdue = false){
    $rows = array();
    foreach($list as $t)
    {
        $rows[] = $overdue
          ? array('name'=>$t['name'],'assignedTo'=>$t['assignedTo'],'deadline'=>substr($t['deadline'],0,10),'extra'=>'逾期'.$t['daysOverdue'].'天')
          : array('name'=>$t['name'],'assignedTo'=>$t['assignedTo'],'deadline'=>substr(isset($t['finishedDate'])?$t['finishedDate']:$t['deadline'],0,10),'extra'=>isset($t['consumed'])?('工时'.$t['consumed']):'');
    }
    return $rows;
};

$doneCols = array(
  array('name'=>'name','title'=>'任务'), array('name'=>'assignedTo','title'=>'负责人'),
  array('name'=>'deadline','title'=>'完成时间'), array('name'=>'extra','title'=>'工时'),
);
$undoneCols = array(
  array('name'=>'name','title'=>'任务'), array('name'=>'assignedTo','title'=>'负责人'),
  array('name'=>'deadline','title'=>'截止'), array('name'=>'extra','title'=>'状态'),
);

$stat = $auto['stat'];

panel(
  set::title($this->lang->zhoubao->editTitle . ' · ' . $this->projectInfo->name . ' · ' . $this->weekStart),

  h(3, $this->lang->zhoubao->statOverview),
  div(setClass('stat-cards'),
    span("进度 {$stat['progress']}%"), span("本周工时 {$stat['weekConsumed']}"),
    span("剩余工时 {$stat['totalLeft']}"), span("完成 {$stat['doneCount']}"), span("逾期 {$stat['overdueCount']}")
  ),

  h(3, $this->lang->zhoubao->doneTasks),
  dtable(set::cols($doneCols), set::data($taskRows($auto['done']))),

  h(3, $this->lang->zhoubao->undoneTasks),
  dtable(set::cols($undoneCols), set::data(array_merge($taskRows($auto['overdue'], true), $taskRows($auto['undone'])))),

  h(3, '手写内容'),
  formPanel(
    set::id('zhoubaoForm'),
    textarea(set::name('nextPlan'), set::label($this->lang->zhoubao->nextPlan), set::value($report ? $report->nextPlan : '')),
    textarea(set::name('risk'),     set::label($this->lang->zhoubao->risk),     set::value($report ? $report->risk : '')),
    textarea(set::name('summary'),  set::label($this->lang->zhoubao->summary),  set::value($report ? $report->summary : '')),
    div(
      btn(set::id('btnCopyLast'), $this->lang->zhoubao->copyLast),
      btn(set::id('btnSaveDraft'), $this->lang->zhoubao->saveDraft),
      btn(set::id('btnSubmit'), set::type('primary'), $this->lang->zhoubao->submit)
    )
  )
);

jsVar('zhoubaoProject', $this->projectInfo->id);
jsVar('zhoubaoWeek', $weekParam);
jsVar('zhoubaoSaveUrl', inlink('edit', "project={$this->projectInfo->id}&week=$weekParam"));
jsVar('zhoubaoCopyUrl', inlink('copyLast', "project={$this->projectInfo->id}&week=$weekParam"));
```

- [ ] **Step 4: 写 js/zhoubao.js（AJAX 保存/提交，带 X-Requested-With 与防重复绑定）**

```javascript
(function(){
  if(window.__zhoubaoBound) return;
  window.__zhoubaoBound = true;

  function collect(){
    return {
      nextPlan: (document.querySelector('[name=nextPlan]')||{}).value || '',
      risk:     (document.querySelector('[name=risk]')||{}).value || '',
      summary:  (document.querySelector('[name=summary]')||{}).value || ''
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
  function save(submit){
    var data = collect(); if(submit) data.submit = 1;
    post(window.zhoubaoSaveUrl, data, function(res){
      if(res.result === 'success'){ if(res.locate) location.href = res.locate; else location.reload(); }
      else alert(res.message || '保存失败');
    });
  }
  var s = document.getElementById('btnSaveDraft'); if(s) s.addEventListener('click', function(){ save(false); });
  var b = document.getElementById('btnSubmit');    if(b) b.addEventListener('click', function(){ save(true); });
  var c = document.getElementById('btnCopyLast');  if(c) c.addEventListener('click', function(){
    post(window.zhoubaoCopyUrl, {}, function(res){
      if(res.result === 'success'){
        if(res.data){ document.querySelector('[name=nextPlan]').value = res.data.nextPlan||''; document.querySelector('[name=risk]').value = res.data.risk||''; document.querySelector('[name=summary]').value = res.data.summary||''; }
      } else alert(res.message || '无上周周报');
    });
  });
})();
```

- [ ] **Step 5: 写 css/zhoubao.css（概览卡片样式）**

```css
.stat-cards{display:flex;gap:12px;margin:8px 0}
.stat-cards span{padding:6px 12px;background:#f2f5fa;border-radius:6px;font-weight:600}
.dtable tr.overdue td{color:#d9534f}
```

- [ ] **Step 6: 实例验证（人工）**

进入 `zhoubao-edit&project=<某项目>&week=`：预期上方"本周完成任务"表出现本周完成的任务、"未完成/逾期"表出现逾期（标红）与本周应完成任务，概览卡片显示进度/工时；填写手写区点"保存草稿"→看板该项目变"草稿"；点"提交"→跳转查看页且看板变"已交"。用非 PM 账号访问应被拒。

- [ ] **Step 7: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/
git commit -m "feat(zhoubao): 写/编辑周报（自动区实时+手写区+草稿/提交快照）"
```

---

### Task 6: 复制上周（功能1）与查看周报页

交付：编辑页"复制上周"能带出上一周同项目手写内容；`zhoubao-view&id=` 只读渲染已提交周报（读 snapshot 不重算）。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Create: `zentao_zhoubao/extension/custom/zhoubao/ui/view.html.php`

**Interfaces:**
- Consumes: `getReport`,`getWeekRange`（Task 4/5）。
- Produces:
  - `zhoubaoModel::getPrevReport(int $project, string $weekStart): object|null`
  - control：`copyLast($project, $week)`（POST，`send` 出 `{result,data:{nextPlan,risk,summary}}`）
  - control：`view($id)`

- [ ] **Step 1: model 增加 getPrevReport**

```php
    /* 取上一自然周同项目周报 */
    public function getPrevReport($project, $weekStart)
    {
        $prevStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
        return $this->getReport($project, $prevStart);
    }
```

- [ ] **Step 2: control 增加 copyLast**

```php
    /**
     * 复制上周手写内容。POST，返回 JSON。
     * @param int    $project
     * @param string $week
     */
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

- [ ] **Step 3: control 增加 view**

```php
    /**
     * 查看已提交周报（只读，读 snapshot 不重算）。
     * @param int $id
     */
    public function view($id)
    {
        $report = $this->dao->select('*')->from('zt_zhoubao')->where('id')->eq((int)$id)->fetch();
        if(!$report) return print('周报不存在');
        $projectInfo = $this->dao->select('id, name, PM')->from(TABLE_PROJECT)->where('id')->eq($report->project)->fetch();

        $this->view->title       = $this->lang->zhoubao->viewTitle;
        $this->view->report      = $report;
        $this->view->projectInfo = $projectInfo;
        $this->view->auto        = $report->snapshot ? json_decode($report->snapshot, true) : array('done'=>array(),'undone'=>array(),'overdue'=>array(),'stat'=>array());
        $this->display();
    }
```

- [ ] **Step 4: 写 ui/view.html.php（只读渲染 snapshot）**

```php
<?php
namespace zin;

$auto   = $this->auto;
$report = $this->report;
$stat   = isset($auto['stat']) ? $auto['stat'] : array();

$taskRows = function($list){
    $rows = array();
    foreach($list as $t) $rows[] = array('name'=>$t['name'],'assignedTo'=>$t['assignedTo'],'when'=>substr(isset($t['finishedDate'])?$t['finishedDate']:$t['deadline'],0,10));
    return $rows;
};
$cols = array(array('name'=>'name','title'=>'任务'),array('name'=>'assignedTo','title'=>'负责人'),array('name'=>'when','title'=>'时间'));

panel(
  set::title($this->lang->zhoubao->viewTitle . ' · ' . $this->projectInfo->name . ' · 第' . $report->week . '周'),
  div(setClass('stat-cards'),
    span("进度 " . (isset($stat['progress'])?$stat['progress']:0) . "%"),
    span("本周工时 " . (isset($stat['weekConsumed'])?$stat['weekConsumed']:0)),
    span("完成 " . (isset($stat['doneCount'])?$stat['doneCount']:0)),
    span("逾期 " . (isset($stat['overdueCount'])?$stat['overdueCount']:0))
  ),
  h(3, $this->lang->zhoubao->doneTasks),
  dtable(set::cols($cols), set::data($taskRows(isset($auto['done'])?$auto['done']:array()))),
  h(3, $this->lang->zhoubao->undoneTasks),
  dtable(set::cols($cols), set::data($taskRows(array_merge(isset($auto['overdue'])?$auto['overdue']:array(), isset($auto['undone'])?$auto['undone']:array())))),
  h(3, $this->lang->zhoubao->nextPlan), p(nl2br(htmlspecialchars($report->nextPlan))),
  h(3, $this->lang->zhoubao->risk),     p(nl2br(htmlspecialchars($report->risk))),
  h(3, $this->lang->zhoubao->summary),  p(nl2br(htmlspecialchars($report->summary)))
);
```

- [ ] **Step 5: 实例验证（人工）**

在有上周周报的项目里进 edit 点"复制上周"→ 三段手写被填入。打开一份已提交周报的 `view`：预期展示的是提交那一刻的 snapshot 数据（之后改动任务不影响它），手写三段正确显示。

- [ ] **Step 6: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/
git commit -m "feat(zhoubao): 复制上周手写内容 + 查看周报页（读 snapshot）"
```

---

### Task 7: 导出（功能2）

交付：看板页导出当周汇总 CSV（项目/PM/状态/完成数/逾期数）；查看页导出单份周报 CSV。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`（加导出按钮链接）

**Interfaces:**
- Consumes: `getBoardRows`,`view` 数据（Task 4/6）。
- Produces: control `export($type='board', $week='', $id=0)` 直接输出 CSV（`text/csv`，带 UTF-8 BOM）。

- [ ] **Step 1: control 增加 export**

```php
    /**
     * 导出 CSV。
     * @param string $type board=当周看板汇总 / one=单份周报
     * @param string $week 周起始日（board 用）
     * @param int    $id   周报 ID（one 用）
     */
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
            if($r) fputcsv($out, array($p, '第' . $r->week . '周', $r->nextPlan, $r->risk, $r->summary));
        }
        else
        {
            $weekStart = $this->zhoubao->resolveWeekStart($week);
            $rows = $this->zhoubao->getBoardRows($weekStart, '', '');
            $labels = $this->lang->zhoubao->statusList;
            fputcsv($out, array('项目', '项目经理', '填报状态', '本周完成', '逾期任务'));
            foreach($rows as $r) fputcsv($out, array($r->projectName, $r->pm, zget($labels, $r->status, $r->status), $r->doneCount, $r->overdueCount));
        }
        fclose($out);
        exit;
    }
```

- [ ] **Step 2: 看板页加导出按钮**

在 `ui/browse.html.php` 的 `panel(...)` 标题后加一个链接（放在 `set::title` 之后）：
```php
  toolbar(html("<a class='btn' href='" . inlink('export', "type=board&week=" . str_replace('-', '_', $this->weekStart)) . "'>" . $this->lang->zhoubao->export . "</a>")),
```

- [ ] **Step 3: 实例验证（人工）**

看板页点导出：下载 CSV，用 Excel 打开中文不乱码，行数=活跃项目数。查看页可通过 `zhoubao-export&type=one&id=<id>` 下载单份。

- [ ] **Step 4: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/control.php zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php
git commit -m "feat(zhoubao): 看板汇总与单份周报 CSV 导出"
```

---

### Task 8: 走查联动（功能3，软依赖）

交付：编辑/查看页显示该项目命中的走查失管规则标签；未安装 zoucha 时静默跳过。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/edit.html.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`（buildAutoData 注入 zoucha 键）

**Interfaces:**
- Produces: `zhoubaoModel::getZouchaHits(int $project): array`（字符串规则名数组，zoucha 不可用时返回空数组）；`buildAutoData` 返回值新增 `zoucha => string[]`。

- [ ] **Step 1: model 增加 getZouchaHits（软依赖）**

```php
    /* 取该项目命中的走查失管规则名；zoucha 未安装则返回空数组 */
    public function getZouchaHits($project)
    {
        $libFile = $this->app->getModuleRoot() . 'zoucha/lib/zouchaRules.php';
        if(!file_exists($libFile)) return array();
        if(!class_exists('zoucha')) return array();
        try
        {
            $result = $this->loadModel('zoucha')->getProjectDiagnostic($project); // 若 zoucha 提供该接口
            if(empty($result) || empty($result->hitRules)) return array();
            return (array)$result->hitRules;
        }
        catch(\Throwable $e){ return array(); }
    }
```

> 说明：若 zoucha 未暴露 `getProjectDiagnostic`，本方法返回空数组，联动降级为"无标签"，不报错。第二阶段可与 zoucha 约定稳定接口。

- [ ] **Step 2: buildAutoData 注入 zoucha 键**

在 Task 5 的 `buildAutoData` 里 `$cls['stat'] = array(...)` 之后加一行：
```php
        $cls['zoucha'] = $this->getZouchaHits($project);
```

- [ ] **Step 3: edit/view 页显示标签**

在 `ui/edit.html.php` 概览卡片下方加：
```php
  (isset($auto['zoucha']) && !empty($auto['zoucha']))
    ? div(setClass('zoucha-tags'), html('走查提示：' . implode(' ', array_map(function($r){ return "<span class='label label-warning'>$r</span>"; }, $auto['zoucha']))))
    : null,
```

- [ ] **Step 4: 实例验证（人工）**

装了 zoucha 时，命中失管规则的项目在编辑页概览下出现橙色规则标签；卸载 zoucha 后同页不报错、无标签。

- [ ] **Step 5: 提交**

```bash
git add zentao_zhoubao/extension/custom/zhoubao/
git commit -m "feat(zhoubao): 走查联动（软依赖，命中规则标签）"
```

---

### Task 9: 企微定时推送（cronPush + 手动按钮）

交付：`zhoubao-cronPush&token=xxx` 校验 token 后组装当周填报 markdown 并 POST 到企微 webhook；看板"立即推送"按钮复用同一逻辑。

**Files:**
- Modify: `zentao_zhoubao/extension/custom/zhoubao/control.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/model.php`
- Modify: `zentao_zhoubao/extension/custom/zhoubao/ui/browse.html.php`（管理员可见推送按钮）

**Interfaces:**
- Consumes: `getBoardRows`（Task 4），`zhoubaoRules::buildWecomMarkdown`（Task 3）。
- Produces:
  - `zhoubaoModel::pushWecom(string $weekStart): array` → `['result'=>'success'|'fail','message'=>string]`
  - control `cronPush($token='', $week='')`、按钮触发的 `pushNow($week='')`

- [ ] **Step 1: model 增加 pushWecom**

```php
    /* 组装当周填报 markdown 并 POST 到企微群机器人 */
    public function pushWecom($weekStart)
    {
        $webhook = isset($this->config->zhoubao->wecomWebhook) ? $this->config->zhoubao->wecomWebhook : '';
        if(empty($webhook)) return array('result' => 'fail', 'message' => '未配置企微 webhook');

        $range = $this->getWeekRange($weekStart);
        $rows  = $this->getBoardRows($weekStart, '', '');
        $mdRows = array();
        foreach($rows as $r) $mdRows[] = array('project'=>$r->projectName,'pm'=>$r->pm,'status'=>$r->status,'doneCount'=>$r->doneCount,'overdueCount'=>$r->overdueCount);
        $content = zhoubaoRules::buildWecomMarkdown($mdRows, $range['year'], $range['week']);

        $payload = json_encode(array('msgtype' => 'markdown', 'markdown' => array('content' => $content)));

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if($err) return array('result' => 'fail', 'message' => '推送失败：' . $err);
        return array('result' => 'success', 'message' => '推送成功', 'resp' => $resp);
    }
```

- [ ] **Step 2: control 增加 cronPush（token 保护，供 cron/curl 调用）**

```php
    /**
     * 企微定时推送入口，供 cron/系统 crontab curl 调用。token 校验。
     * @param string $token 与 config->zhoubao->pushToken 比对
     * @param string $week  周起始日（'' 表示本周）
     */
    public function cronPush($token = '', $week = '')
    {
        $token = isset($_GET['token']) ? (string)$_GET['token'] : (string)$token;
        if($token === '' || $token !== $this->config->zhoubao->pushToken) return print('invalid token');
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        $res = $this->zhoubao->pushWecom($weekStart);
        return print($res['message']);
    }
```

- [ ] **Step 3: control 增加 pushNow（看板按钮，需 manage 权限）**

```php
    /**
     * 看板"立即推送"按钮触发。需 manage 权限。
     * @param string $week
     */
    public function pushNow($week = '')
    {
        if(!common::hasPriv('zhoubao', 'manage')) return $this->send(array('result' => 'fail', 'message' => '无推送权限'));
        $weekStart = $this->zhoubao->resolveWeekStart(isset($_GET['week']) ? $_GET['week'] : $week);
        return $this->send($this->zhoubao->pushWecom($weekStart));
    }
```

- [ ] **Step 4: 看板页加"立即推送"按钮（管理员可见）**

在 `ui/browse.html.php` 的 toolbar 内追加：
```php
    common::hasPriv('zhoubao', 'manage')
      ? html("<a class='btn btn-primary' href='javascript:;' id='btnPushNow' data-url='" . inlink('pushNow', 'week=' . str_replace('-', '_', $this->weekStart)) . "'>" . $this->lang->zhoubao->pushNow . "</a>")
      : null,
```
并在 `js/zhoubao.js` 末尾（`__zhoubaoBound` 守卫内）追加：
```javascript
  var pn = document.getElementById('btnPushNow');
  if(pn) pn.addEventListener('click', function(){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', pn.getAttribute('data-url'), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function(){ try{ var r = JSON.parse(xhr.responseText); alert(r.message || '完成'); }catch(e){ alert('推送返回解析失败'); } };
    xhr.send('');
  });
```

- [ ] **Step 5: 配置真实 webhook 并实例验证（人工）**

在目标禅道 `config/ext/zhoubao.php` 覆盖 `$config->zhoubao->wecomWebhook` 与 `pushToken` 为真实值。浏览器访问 `zhoubao-cronPush&token=<真实token>`：企微群收到当周填报 markdown。管理员在看板点"立即推送"：同样收到，非管理员无此按钮。错误 token 返回 `invalid token`。

- [ ] **Step 6: 记录 cron 配置到部署说明并提交**

在 `zentao_zhoubao/doc/zh-cn.yaml` 的 `install` 段补一句 cron 提示（系统 crontab 周五 17:00 `curl 'http(s)://<禅道地址>/zhoubao-cronPush-token-<token>.html'`）。
```bash
git add zentao_zhoubao/extension/custom/zhoubao/ zentao_zhoubao/doc/zh-cn.yaml
git commit -m "feat(zhoubao): 企微定时推送 cronPush 与看板一键推送"
```

---

### Task 10: 打包接入与收尾

交付：`pack_plugins.ps1` 能打出 `dist/zhoubao-1.0.zip`；单测在打包流程外仍可跑。

**Files:**
- Modify: `pack_plugins.ps1`

**Interfaces:**
- Consumes: 已完成的 `zentao_zhoubao/` 全量文件。

- [ ] **Step 1: 查看 pack_plugins.ps1 现有插件列表**

Run: `cat pack_plugins.ps1`
预期：看到 crmsync/taizhang/zoucha 的打包配置结构。

- [ ] **Step 2: 按同结构追加 zhoubao 条目**

参照现有条目，把 `zentao_zhoubao`（源目录）、`zhoubao`（code）、从 `plugin.json` 读版本，产出 `dist/zhoubao-<version>.zip` 加入列表。（具体字段名与现有条目保持一致——读文件后照抄结构改值。）

- [ ] **Step 3: 运行打包验证**

Run: `pwsh ./pack_plugins.ps1`
Expected: 生成 `dist/zhoubao-1.0.zip`，解压后含 `plugin.json`、`extension/`、`db/`、`doc/`。

- [ ] **Step 4: 跑一次全量单测确认未回归**

Run: `cd zentao_zhoubao && php tests/test_rules.php`
Expected: `ALL PASSED`。

- [ ] **Step 5: 提交**

```bash
git add pack_plugins.ps1
git commit -m "build(zhoubao): 接入 pack_plugins 打包流程"
```

---

## Self-Review

**Spec coverage：**
- 项目维度周报（项目×周）→ Task 1 表结构 uk_project_week、Task 5 编辑。✓
- 本周完成任务自动罗列 → Task 2 classifyTasks done、Task 5 自动区。✓
- 本周未完成/逾期自动 → Task 2 undone/overdue、Task 5。✓
- 下周计划/风险/小结手写 → Task 5 手写区。✓
- 进度/工时概览自动 → Task 5 buildAutoData stat。✓
- 实时+提交快照（方案 A）→ Task 5 saveReport 提交时 json_encode snapshot；Task 6 view 读 snapshot。✓
- 周报未填报提醒（看板）→ Task 4 getBoardRows status/筛选。✓
- 任务逾期高亮 → Task 5 undone 表 overdue 标红（css .overdue）。✓
- 企微定时推送 → Task 9 cronPush + pushWecom。✓
- 功能1 复制上周 → Task 6 copyLast。✓
- 功能2 导出 → Task 7 export。✓
- 功能3 走查联动 → Task 8 getZouchaHits 软依赖。✓
- 顶级主导航 → Task 1 mainNav menuOrder[61]。✓
- 可单测 lib → Task 2/3 TDD。✓
- 打包 → Task 10。✓
- 功能4/5/6（历史时间线/环比/人力联动）属第二阶段，本 MVP 计划不含，另立计划。（有意排除，见 spec §8）

**Placeholder scan：** 无 TBD/TODO/"add error handling" 之类；每个代码步骤均给出完整代码。Task 8 的 zoucha 接口存在"若 zoucha 提供该接口"的降级说明——已用 try/catch + 空数组降级明确处理，非占位。Task 10 Step 2 让实现者读现有 pack 脚本照抄结构，因该脚本结构未在本仓库上下文中展开，属合理的"follow existing pattern"，非逻辑占位。

**Type consistency：** `classifyTasks` 返回 `done/undone/overdue`（Task2）→ Task4/5 一致使用；`buildAutoData` 返回增加 `stat`（Task5）、`zoucha`（Task8）键，view/edit 均以 isset 兜底读取；`buildWecomMarkdown` 行字段 `project/pm/status/doneCount/overdueCount`（Task3）与 Task9 组装 `$mdRows` 字段一致；`resolveWeekStart`/`getWeekRange`/`getReport`/`getBoardRows` 签名跨任务一致。✓
