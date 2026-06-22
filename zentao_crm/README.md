# 禅道 CRM 对接插件（crmsync）

禅道与外部 CRM（神思综合管理平台 projectMgt）对接插件。CRM 商机**赢单**时回调禅道（或用户在 CRM 端手动触发），自动在禅道创建一个对应的**融合瀑布（waterfallplus）项目**，或**关联到禅道现有项目**，并把商机的合同金额、回款金额等信息同步过来（含联动写入项目台账插件 zt_taizhang）。仅单向 CRM → 禅道。

## 目录结构

```
zentao_crm/
├── plugin.json                         # 插件元信息
├── doc/zh-cn.yaml                      # 插件清单
├── db/install.sql                      # 建表 zt_crmsync_map
├── config/ext/crmsync.php              # receive/products/projects 注册为免登录接口
└── extension/custom/
    ├── common/ext/config/crmsync.php   # 应用注册
    ├── common/ext/lang/zh-cn/crmsync.php  # 菜单(系统后台 → CRM对接)
    ├── group/ext/config/crmsync.php    # 权限包
    ├── group/ext/lang/zh-cn/crmsync.php   # 权限资源
    └── crmsync/                        # 主模块(config/control/model/lang/ui)
```

## 安装

1. 将 `zentao_crm/` 内各目录合并到禅道根目录（或打包 zip 通过「后台 → 二次开发 → 插件」本地安装）。
2. 执行 `db/install.sql` 建表。
3. 进入「系统后台 → CRM对接 → 对接设置」配置：
   - **默认项目经理**：自动建项目统一挂的 PM 账号（建完人工改派）。
   - **默认项目集ID**：单纯项目归属的项目集（0=顶层）。
   - **默认产品名 / 默认工期(月)**。
   - **对接令牌 + 令牌对应账号**：CRM 调用接口用的 token（强随机），命中后以该账号身份建项目。

> 也可直接在 `extension/custom/crmsync/config.php` 写死 `apiTokens`、`defaultPM` 等默认值。

## 对外 API 契约（供 CRM 端调用）

鉴权：请求头 `X-Resource-Token: <token>`（推荐）或 URL 参数 `?token=<token>`。

> ⚠️ **URL 格式**：禅道默认 `requestType=PATH_INFO`，此模式下 `index.php?m=crmsync&f=xxx` 形式的 GET 参数路由会被忽略（落到登录页），且 `?token=` 取不到——必须用路径式 URL `crmsync-{method}.json` 并以请求头传 token。`.json` 后缀保证返回原始 JSON。仅当禅道配置为 `requestType=GET` 时才可用 `index.php?m=&f=` 形式。

### 1. 获取产品列表（产品选择器）

```
GET  {禅道}/crmsync-products.json
X-Resource-Token: <token>
```
响应：
```json
{ "result": "success", "data": [ { "id": 3, "name": "核心平台", "code": "CORE", "program": 1 } ] }
```

### 2. 获取项目列表（"关联现有项目"选择器）

```
GET  {禅道}/crmsync-projects.json
X-Resource-Token: <token>
```
响应：
```json
{ "result": "success", "data": [ { "id": 42, "name": "某项目", "code": "P42", "status": "doing", "PM": "zhangsan", "begin": "2026-01-01", "end": "2026-12-31" } ] }
```

### 3. 赢单/手动建项目/关联现有项目

```
POST {禅道}/crmsync-receive.json
X-Resource-Token: <token>
Content-Type: application/json
```
请求体：
```json
{
  "opportunityId": "9001",          // 必填, CRM商机ID(幂等键)
  "name": "甲公司年度软件采购",       // 必填, 作为项目名
  "customerName": "甲公司",
  "contractAmount": 500000,         // 合同金额(元) → 项目预算 + 台账revenue
  "estimatedAmount": 600000,        // 预计金额(元), 合同金额为空时回退写台账revenue
  "receivedAmount": 200000,         // 回款金额(元) → 台账receivedAmount
  "contractDate": "2026-08-01",
  "closeReason": "正式签约",
  "ownerName": "张三",               // 可选, 暂仅记录
  "mode": "create",                 // create=新建项目(默认) | link=关联现有项目
  "productId": 3,                   // create模式: 可空/0 = 单纯项目(禅道自动建产品)
  "zentaoProjectId": 42             // link模式必填: 要关联的禅道项目ID
}
```
响应：
```json
{ "result": "success", "projectID": 42, "projectLink": "http://禅道/zentao/project-view-42.json" }
```
- 同一 `opportunityId` 重复调用：返回 `{"result":"exists","projectID":42,...}`，不重复建项目/改关联，但会**刷新**已映射项目的预算与台账金额。
- 失败：返回 `{"result":"fail","message":"...","code":500}`，并在「同步记录」页留一条 failed 记录可手动重试。

## 项目台账联动（可选）

若同环境部署了项目台账插件（`zt_taizhang` 表存在），同步/关联/刷新时会按 `projectID` upsert 台账行：

- 合同金额(元)÷10000 → `revenue`(万元)；**合同金额为空时回退用预计金额**
- 回款金额(元)÷10000 → `receivedAmount`(万元)
- CRM 未填(≤0)的金额不覆盖台账中人工维护的既有值
- 台账插件未安装时自动跳过，互不依赖

旧版台账表缺金额列时，先执行台账插件的 `db/upgrade_amount_columns.sql`。

## 管理页面

- **系统后台 → CRM对接 → 同步记录**：查看每条商机的同步状态、对应项目、失败原因，失败项可「重试」。
- **对接设置**：见上。

## CRM（projectMgt, Java）侧实现

CRM 侧客户端已在 `sdses-crm` 仓库实现：`projectmgt-module-crm-biz` 下的
`integration/zentao/ZentaoSyncClient.java`（getProducts / getProjects / createProject，
URL 为 `{base-url}/crmsync-{method}.json`，token 经 `X-Resource-Token` 头传递）。

1. **配置**（`application-*.yml`）：
   ```yaml
   zentao:
     base-url: http://禅道地址/zentao   # 注意带禅道应用路径
     token: <与禅道对接设置中一致的强随机token>
   ```
2. **赢单回调**：`OpportunityServiceImpl.closeOpportunity()` 的 `closeType="win"` 分支在事务提交后调用 `createProject`，失败仅记日志，依赖禅道幂等可重试，不影响赢单主流程。
3. **手动同步**：商机列表「同步到禅道」弹窗——选择"新建禅道项目"（可选关联产品）或"关联现有项目"（调 `getProjects()` 渲染项目下拉）。

## 安全注意

- token 接口免登录，务必使用强随机 token，且只在内网 / HTTPS 暴露。
- token 只能含字母/数字/下划线/连字符（设置页有校验）。
- 项目名/客户名等文本入库前已做 `htmlspecialchars`，金额/日期均做类型与格式规整。
