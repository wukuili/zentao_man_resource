# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is a **monorepo of independent ZenTao 20.0 / 22.x open-edition extension plugins** (神思电子 secondary development). Each top-level `zentao_*` / `man_resource` directory is a self-contained plugin that installs separately into a ZenTao PMS instance. There is no shared build — each plugin ships its own `doc/zh-cn.yaml` manifest, `plugin.json`, `db/*.sql`, `config/ext/`, and `extension/custom/` tree.

| Directory | code | Plugin | Summary |
|-----------|------|--------|---------|
| `man_resource/` | `man_resource` | 人力资源日历 | Per-member workload/load-rate across org/project/member dimensions; conflict detection, Monte Carlo prediction, load simulation, REST API |
| `zentao_taizhang/` | `taizhang` | 项目台账 | Multi-filter project ledger with cost/profit metrics and over-budget warnings; **own top-level main-nav entry** |
| `zentao_waibao/` | `waibao` | 外包工时 | "Is outsourced" person tag + separate outsourced/in-house hour statistics |
| `zentao_crm/` | `crmsync` | CRM对接 | Webhook/REST sync with external CRM; auto-creates projects on deal-won, syncs amounts into project + `zt_taizhang` |
| `zentao_zoucha/` | `zoucha` | 项目走查 | Scans in-progress projects against 5 "neglected project" rules; pure rule engine is unit-tested |

`plan/` (git-ignored) holds reference-only material: the resourcecalendar v2.6 reference plugin and the full ZenTao PMS 20.0 PHP 8.1 source for framework lookups — **not** a running instance.

## Build, Pack & Deploy

There is no compiler or package manager. "Building" = packing a plugin directory into a distributable zip; "running" = copying files into a live ZenTao instance and clearing its cache.

```powershell
# Pack crmsync / taizhang / zoucha into dist/<code>-<version>.zip (version from plugin.json)
pwsh ./pack_plugins.ps1
```

```bash
# Deploy into a local zbox ZenTao under WSL (run as root). These scripts copy files,
# seed tokens, run db/*.sql, then clear tmp/cache + tmp/model.
bash deploy_crmsync.sh
bash deploy_taizhang_sync.sh   # adds amount columns, redeploys taizhang, then calls deploy_crmsync.sh
```

**Manual deploy of any plugin** (the part the scripts automate):
```
{plugin}/extension/*       → {zentao}/extension/*
{plugin}/config/ext/*.php  → {zentao}/config/ext/*.php
{plugin}/db/install.sql    → import into DB (first install only)
```
Then **clear `{zentao}/tmp/cache/*` and `{zentao}/tmp/model/*`** — ZenTao caches the merged lang/config/model and changes will not take effect otherwise. Stale menu 404s and "edits not applying" are almost always an uncleared cache.

## Testing

- **zoucha** has the only automated test — a zero-dependency PHP harness for the rule engine:
  ```bash
  cd zentao_zoucha && php tests/test_rules.php
  ```
  It loads `extension/custom/zoucha/lib/zouchaRules.php` directly (no ZenTao runtime) and asserts each of the 5 rules plus boundary/dirty-date cases. **Keep business rules in a framework-free `lib/` class so they stay unit-testable** — this is the pattern to follow for new rule-style logic.
- Everything else has no automated tests: install into a running ZenTao 20+/22.x instance and exercise each page. `man_resource` exposes a diagnostic endpoint `man_resource-debugTeam?userID=xxx`.

## ZenTao Extension Conventions (apply to every plugin)

- **`extension/custom/{module}/ext/{type}/{code}.php` hooks** inject into existing ZenTao modules (`common`, `group`, `project`) without editing core files. `{type}` is `config` or `lang/zh-cn`. The plugin's own module lives at `extension/custom/{code}/` with `control.php`, `model.php`, `config.php`, `lang/zh-cn.php`, `ui/`, optional `view/`, `css/`, `js/`, `lib/`.
- **Config cascade**: plugin `config.php` → `extension/custom/common/ext/config/` → `config/ext/` — later entries override earlier ones. App registration (`apps`, `appsMenu`, `includedPriv`) goes in `common/ext/config`; privilege packages in `group/ext/config` + `group/ext/lang`.
- **ZIN templates (`ui/`) take priority** over legacy `view/` PHP templates when both exist. ZIN uses `namespace zin;` with DSL functions (`dtable()`, `panel()`, `featureBar()`, `toolbar()`, `picker()`, `select()`, `datePicker()`).
- **ZIN `jsVar()` creates a scoped const** — inline `<script>` blocks must read it via a `window.` prefix; ZIN's `js()` helper does not execute at render time, so chart/init data is passed through `jsVar()` + inline script.
- **Date params in URLs use `_` not `-`** (e.g. `2026_05_28`) because of ZenTao routing; convert back in the controller with `str_replace('_', '-', ...)`.
- **PATH_INFO routing maps args by position, not name** — `$_GET` won't see named params. Pagination/filter params must be declared as controller method parameters in order.
- **install/uninstall SQL must not contain `;` inside comments** — ZenTao's `executeDB` splits statements on literal semicolons, so a `;` in a comment yields a 1064 error. Uninstall via `db/uninstall.sql`; it generally drops the plugin's own tables only (do not drop shared ZenTao tables).
- **Raw XHR POSTs need `X-Requested-With`** — ZenTao 20 redirects (302) non-AJAX POSTs to the app shell (blank page). Add a guard to avoid double-binding when a ZIN script re-runs.
- **Top-level main-nav** (the outermost 我的地盘/项目集/…/组织/后台 bar) is driven by `$lang->mainNav` and works differently from in-app nav: each item must be the **string** `"标题|模块|方法|"`, and it **must** be registered in `$lang->mainNav->menuOrder[N]` (the renderer only iterates `menuOrder`; pick an `N` that doesn't collide with core 组织=60/后台=65). `zentao_taizhang` is the reference implementation.

### ZenTao framework API quick reference
- `$this->loadModel('module')` loads other ZenTao models (`dept`, `project`, `user`, …)
- `$this->dao->select()->from()->where()->fetchAll()` — DAO query builder; `dao::isError()` / `dao::getError()` for errors
- `$this->send()` returns JSON; `$this->locate()` redirects; `$this->display()` renders the view
- `helper::isAjaxRequest()`; `zget()` null-safe accessor; `common::hasPriv()` permission check; `createLink()` / `inlink()` URL builders
- ECharts is loaded dynamically via `loadEcharts()` in ZIN templates

## man_resource Specifics (the largest plugin)

- **Controller → Model split**: `control.php` handles HTTP params, POST processing, date normalization, and delegates to `model.php`, which holds all business logic (load calculation, daily series, Gantt, conflict detection, Monte Carlo simulation, snapshot). Team (multi-person) tasks use `zt_team` via `getTaskTeamMap()`.
- **Working days** come from `zt_holiday` (type `holiday` = non-working, type `working` = make-up day); `collectWorkingDays()` / `shiftWorkingDays()` are the core utilities.
- **Load-rate thresholds** are configurable: relax 50% / spare 70% / normal 90% / full 100% / over 120% / extreme >120%. Modes: `todo` (active tasks, future dates) vs `done` (completed, past). When `taskHourPredict` is on and `left ≤ 0` it falls back to per-task `predictHours`; `notTaskHourPredict` counts non-task todos from `zt_todo`.
- **Closed projects/executions are excluded** from all statistics.
- **REST API**: `apiCalendar` (GET), `apiUserDetail` (GET), `apiSimulation` (POST) at `/api/resource/{calendar,user/detail,simulation}`. Auth = ZenTao session OR `X-Resource-Token` header / `?token=`, configured in `$config->man_resource->apiTokens`.
- **Tables**: `zt_resource_calendar` (snapshots), `zt_load_simulation` (simulation results).

### Adding a new view/page (man_resource and similar)
1. Add a method to `control.php` (POST handling → `locate`/redirect, then prepare view data).
2. Add language strings to `lang/zh-cn.php` and a privilege resource to `$lang->resource->{code}`.
3. Create `ui/{method}.html.php` (preferred); optionally a legacy `view/{method}.html.php`.
4. For nav integration, add to `$lang->{code}->menu` and the vision-specific menus in `extension/custom/common/ext/lang/zh-cn/{code}.php`.
