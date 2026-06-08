# zentao_man_resource — 禅道二次开发插件集

参考禅道官方「人力资源日历」插件思路实现的一组禅道扩展插件，适配 **禅道 20.0 / 22.x 开源版**。

## 仓库内的插件

| 目录 | 插件 | 说明 |
|------|------|------|
| `man_resource/` | 人力资源日历 | 各人员负荷统计，帮助管理员掌握团队负荷、便于调度；支持团队任务与个人任务；可从项目菜单查看本项目内各人员负荷 |
| `zentao_taizhang/` | 项目台账 | 多维度筛选的项目台账管理，含超成本预警；**独立顶级主导航入口** |
| `zentao_waibao/` | 外包工时 | 外包工时导出与统计（组织/项目/成员维度） |
| `zentao_crm/` | CRM 对接 | 拉取产品列表、创建项目的 CRM 同步插件 |

> `source/`（git-ignored）是禅道官方源码，仅供分析参考，不是运行实例。

## 部署（重要）

仓库文件**不会自动生效**，必须拷到运行中的禅道实例并清缓存：

```
{plugin}\extension\*           →  {禅道根}\extension\*
{plugin}\config\ext\*.php       →  {禅道根}\config\ext\*.php
{plugin}\db\install.sql         →  导入数据库（仅首次建表）
```
然后**清空** `{禅道根}\tmp\cache\` 和 `{禅道根}\tmp\model\`（禅道会缓存合并后的 lang/config/model，不清不生效）。

## 关键经验：新增「独立顶级主导航」插件

禅道 22.x 最外层那排导航（我的地盘/项目集/…/组织/后台）由 `$lang->mainNav` 驱动，写法和 app 内的一级导航**完全不同**，踩过坑：

- 顶级主导航项的值必须是**字符串** `"标题|模块|方法|"`，写成 stdclass/数组会被 `explode('|')` 解析失败；
- **必须**登记 `$lang->mainNav->menuOrder[N] = '{key}'`，核心渲染只遍历 menuOrder，不进去就永不显示；
- `N` 别和核心项撞（组织=60、后台=65）；
- 落地页在 `config/ext` 设为 `logonMethod`（只读页）或在 `group/ext/config` 注册权限包，保证非管理员也能看到/被授权。

`zentao_taizhang` 即此模式的参考实现。完整机制与模板见 zentao-plugin skill 的 `references/menu-and-fields.md` 第 2 节。
