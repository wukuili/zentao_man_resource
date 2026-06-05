# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Project Overview

**man_resource** is a ZenTao 20+ extension plugin (人力资源日历 — Human Resource Calendar) that provides resource workload visibility across org, project, and member dimensions. It includes load rate analysis, conflict detection, Monte Carlo completion prediction, load simulation, and REST API endpoints.

The plugin lives in `man_resource/` at repo root. A reference implementation (resourcecalendar v2.6) exists in `plan/m_6a166e4635ce2resourcecalendar2.6/`. The full ZenTao PMS 20.0 source is in `plan/ZenTaoPMS-20.0-php8.1/` for framework reference.

## Plugin Structure

```
man_resource/
├── db/install.sql                          # Database schema (zt_resource_calendar, zt_load_simulation)
├── doc/zh-cn.yaml                         # Plugin manifest (name, version, compatibility)
├── config/ext/man_resource.php             # Route/filter config (openMethods, logonMethods, programPriv)
├── www/js/zui/resourcecalendar/            # Frontend assets (min.js, min.css)
├── extension/custom/
│   ├── common/ext/config/man_resource.php  # App registration (apps, appsMenu, includedPriv)
│   ├── common/ext/lang/zh-cn/man_resource.php  # Nav menu integration (system/scrum/waterfall)
│   ├── group/ext/config/man_resource.php   # Privilege package registration
│   ├── group/ext/lang/zh-cn/man_resource.php   # View-level permission resource
│   ├── project/ext/config/man_resource.php # Project-scope privilege wiring
│   └── man_resource/                      # Main module
│       ├── config.php                      # Load range config, colors, table constants
│       ├── control.php                    # Controller (all HTTP methods)
│       ├── model.php                      # Model (all business logic)
│       ├── lang/zh-cn.php                # Chinese language strings + permission resource defs
│       ├── css/man_resource.css           # Minimal CSS overrides
│       ├── js/man_resource.js             # Client-side JS (currently empty init)
│       ├── ui/                             # ZIN DSL templates (ZenTao 20+ native UI)
│       │   ├── orgdimension.html.php
│       │   ├── projectdimension.html.php
│       │   ├── memberdimension.html.php
│       │   ├── sethours.html.php
│       │   ├── setload.html.php
│       │   ├── setpredicthours.html.php
│       │   └── browse.html.php
│       └── view/                           # Legacy PHP templates (pre-ZIN fallback)
│           ├── orgdimension.html.php
│           ├── projectdimension.html.php
│           ├── memberdimension.html.php
│           ├── sethours.html.php
│           ├── setload.html.php
│           ├── setpredicthours.html.php
│           ├── simulate.html.php
│           ├── snapshot.html.php
│           ├── prediction.html.php
│           ├── export.html.php
│           └── browse.html.php
```

## Key Architecture Patterns

### ZenTao 20+ Extension Convention
- **ZIN templates (`ui/`) take priority** over legacy views (`view/`) when both exist. ZIN uses `namespace zin;` with DSL functions like `dtable()`, `panel()`, `featureBar()`, `toolbar()`, `picker()`, `select()`, `datePicker()`.
- **Date params in URLs** use `_` instead of `-` (e.g. `2026_05_28`) due to ZenTao routing, then converted back in controller via `str_replace('_', '-', ...)`.
- **Extension hooks** are wired via `extension/custom/{module}/ext/{type}/man_resource.php` files that inject into existing ZenTao modules (common, group, project) without modifying core files.
- **Config cascade**: Plugin `config.php` → `extension/custom/common/ext/config/` → `config/ext/` — later entries override earlier ones.

### Controller → Model Flow
- `control.php` methods handle HTTP params, POST processing, date normalization, and delegate to `model.php` for data
- `model.php` contains all business logic: load calculation, daily series, Gantt data, conflict detection, Monte Carlo simulation, snapshot
- Both single-person tasks and multi-person (team) tasks are handled — team tasks use `zt_team` table via `getTaskTeamMap()`

### Working Day Calculation
- Uses `zt_holiday` table to override weekend/holiday classifications (type `holiday` = non-working, type `working` = make-up workday)
- `collectWorkingDays()` and `shiftWorkingDays()` in model.php are the core date utilities

### Load Rate Algorithm
- Thresholds are configurable: relax(50%), spare(70%), normal(90%), full(100%), over(120%), extreme(>120%)
- Status modes: `todo` (active tasks, future dates) vs `done` (completed tasks, past dates)
- Task hour prediction: when `taskHourPredict` is enabled and `left ≤ 0`, falls back to `predictHours` per-task default
- Non-task todos: when `notTaskHourPredict` is enabled, counts from `zt_todo` table

### REST API
- Three endpoints: `apiCalendar` (GET), `apiUserDetail` (GET), `apiSimulation` (POST)
- Auth: either ZenTao session or `X-Resource-Token` header / `?token=` parameter, configured in `$config->man_resource->apiTokens`
- Routes: `/api/resource/calendar`, `/api/resource/user/detail`, `/api/resource/simulation`

### Database Tables
- `zt_resource_calendar`: snapshots (user_id, project_id, task_id, work_date, estimated_hours, consumed_hours, remain_hours, load_rate, status)
- `zt_load_simulation`: simulation results (simulation_name, operator, start_date, end_date, result_json, created_at)
- Both use `zt_` prefix (configurable via `$config->db->prefix`)

## Development Notes

### Adding a New View/Page
1. Add method to `control.php` following existing patterns (POST handling → locate/redirect, then view data preparation)
2. Add language strings to `lang/zh-cn.php`
3. Add privilege resource to `$lang->resource->man_resource` in `lang/zh-cn.php`
4. Create ZIN template in `ui/{methodname}.html.php` (preferred) and optionally a legacy `view/{methodname}.html.php`
5. If the page needs nav integration, add to `$lang->man_resource->menu` and the appropriate vision-specific menus in `extension/custom/common/ext/lang/zh-cn/man_resource.php`

### Working with ZenTao Framework
- `$this->loadModel('module')` loads other ZenTao models (e.g. `dept`, `project`, `user`)
- `$this->dao->select()->from()->where()->fetchAll()` is the ZenTao DAO query builder
- `dao::isError()` / `dao::getError()` for DB error checking
- `$this->send()` returns JSON; `$this->locate()` redirects; `$this->display()` renders the view
- `helper::isAjaxRequest()` detects AJAX calls
- `zget()` is ZenTao's null-safe array/object accessor
- `common::hasPriv()` checks user permissions; `createLink()` / `inlink()` build URLs

### Charts
- ECharts is loaded dynamically via `loadEcharts()` in ZIN templates
- Chart data is passed via `jsVar()` in ZIN and inline `<script>` blocks (ZIN's `js()` helper does not execute at render time)

### Testing
- No automated test suite exists. Test by installing the plugin into a running ZenTao 20+ instance and exercising each dimension page.
- The `debugTeam()` method in control.php provides a diagnostic endpoint: `man_resource-debugTeam?userID=xxx`