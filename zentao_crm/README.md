# 禅道 CRM 对接插件（crmsync）

禅道与外部 CRM（神思综合管理平台 projectMgt）对接插件。CRM 商机**赢单**时回调禅道（或用户在 CRM 端手动触发），自动在禅道创建一个对应的**融合瀑布（waterfallplus）项目**，并把商机基础信息同步过来。仅单向 CRM → 禅道。

## 目录结构

```
zentao_crm/
├── doc/zh-cn.yaml                      # 插件清单
├── db/install.sql                      # 建表 zt_crmsync_map
├── config/ext/crmsync.php              # receive/products 注册为免登录接口
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

鉴权：请求头 `X-Resource-Token: <token>` 或 URL 参数 `?token=<token>`。

### 1. 获取产品列表（产品选择器）

```
GET  {禅道}/index.php?m=crmsync&f=products&token=<token>
```
响应：
```json
{ "result": "success", "data": [ { "id": 3, "name": "核心平台", "code": "CORE", "program": 1 } ] }
```

### 2. 赢单/手动建项目

```
POST {禅道}/index.php?m=crmsync&f=receive&token=<token>
Content-Type: application/json
```
请求体：
```json
{
  "opportunityId": "9001",          // 必填, CRM商机ID(幂等键)
  "name": "甲公司年度软件采购",       // 必填, 作为项目名
  "customerName": "甲公司",
  "contractAmount": 500000,
  "contractDate": "2026-08-01",
  "closeReason": "正式签约",
  "ownerName": "张三",               // 可选, 暂仅记录
  "productId": 3                    // 可空/0 = 单纯项目(禅道自动建产品)
}
```
响应：
```json
{ "result": "success", "projectID": 42, "projectLink": "http://禅道/index.php?m=project&f=view&projectID=42" }
```
- 同一 `opportunityId` 重复调用：返回 `{"result":"exists","projectID":42,...}`，不重复建项目（幂等，基于 `zt_project.market`）。
- 失败：返回 `{"result":"fail","message":"...","code":500}`，并在「同步记录」页留一条 failed 记录可手动重试。

## 管理页面

- **系统后台 → CRM对接 → 同步记录**：查看每条商机的同步状态、对应项目、失败原因，失败项可「重试」。
- **对接设置**：见上。

## CRM（projectMgt, Java）侧改造指引

> 本插件只负责禅道侧接收与建项目；CRM 侧需新增一个客户端调用上面两个接口。建议放在 `projectmgt-module-crm-biz`。

1. **配置**（`application-*.yml`）：
   ```yaml
   zentao:
     base-url: http://禅道地址
     token: <与禅道对接设置中一致的强随机token>
   ```
2. **客户端**（示意）：
   ```java
   @Component
   public class ZentaoSyncClient {
       private final RestTemplate rest = new RestTemplate();
       @Value("${zentao.base-url}") String baseUrl;
       @Value("${zentao.token}")    String token;

       public List<Map<String,Object>> getProducts() {
           String url = baseUrl + "/index.php?m=crmsync&f=products&token=" + token;
           return (List<Map<String,Object>>) rest.getForObject(url, Map.class).get("data");
       }

       public Map<String,Object> createProject(Map<String,Object> payload) {
           String url = baseUrl + "/index.php?m=crmsync&f=receive&token=" + token;
           HttpHeaders h = new HttpHeaders();
           h.setContentType(MediaType.APPLICATION_JSON);
           return rest.postForObject(url, new HttpEntity<>(payload, h), Map.class);
       }
   }
   ```
3. **赢单回调**：在 `OpportunityServiceImpl.closeOpportunity()` 的 `closeType="win"` 分支末尾，**异步**（`@Async` 或事件）调用 `createProject`，组装 `opportunityId/name/customerName/contractAmount/contractDate/closeReason`。失败仅记日志，依赖禅道幂等可重试，不影响赢单主流程。
4. **手动建项目**：商机详情页「创建禅道项目」按钮 → 调 `getProducts()` 渲染下拉（可留空=单纯项目）→ 提交调 `createProject`。

## 安全注意

- token 接口免登录，务必使用强随机 token，且只在内网 / HTTPS 暴露。
- token 只能含字母/数字/下划线/连字符（设置页有校验）。
- 项目名/客户名等文本入库前已做 `htmlspecialchars`，金额/日期均做类型与格式规整。
