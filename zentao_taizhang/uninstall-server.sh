#!/usr/bin/env bash
# =============================================================================
# 项目台账插件 —— 服务器端一键全卸载脚本
# 在运行禅道的 Linux 主机上执行（如 zbox：/opt/zbox/app/zentao）。
#
# 用法：
#   ZT_ROOT=/opt/zbox/app/zentao \
#   DB_NAME=zentao DB_USER=root DB_PASS=你的密码 DB_PREFIX=zt_ \
#   bash uninstall-server.sh
#
# 不传 DB_* 则只删文件与缓存，跳过删表/删权限（之后可手动执行末尾打印的 SQL）。
# =============================================================================
set -euo pipefail

ZT_ROOT="${ZT_ROOT:-/opt/zbox/app/zentao}"
DB_PREFIX="${DB_PREFIX:-zt_}"

if [ ! -d "$ZT_ROOT" ]; then
  echo "✗ 禅道根目录不存在：$ZT_ROOT （用 ZT_ROOT=... 指定）" >&2
  exit 1
fi
echo "禅道根目录：$ZT_ROOT"

# ---- 1) 删除部署文件 --------------------------------------------------------
TARGETS=(
  "extension/custom/taizhang"
  "extension/custom/common/ext/lang/zh-cn/taizhang.php"
  "extension/custom/common/ext/config/taizhang.php"
  "extension/custom/group/ext/lang/zh-cn/taizhang.php"
  "extension/custom/group/ext/config/taizhang.php"
  "config/ext/taizhang.php"
)
for rel in "${TARGETS[@]}"; do
  path="$ZT_ROOT/$rel"
  if [ -e "$path" ]; then
    rm -rf "$path"
    echo "  ✓ 已删除 $rel"
  else
    echo "  - 不存在 $rel（跳过）"
  fi
done

# ---- 2) 清缓存（菜单/权限合并缓存，不清则顶级菜单「项目台账」残留并 404）-----
for d in tmp/cache tmp/model; do
  if [ -d "$ZT_ROOT/$d" ]; then
    find "$ZT_ROOT/$d" -mindepth 1 -delete 2>/dev/null || true
    echo "  ✓ 已清空 $d"
  fi
done

# ---- 3) 删表与权限行（提供了 DB_* 才执行）-----------------------------------
DROP_SQL="DROP TABLE IF EXISTS \`${DB_PREFIX}taizhang\`;
DELETE FROM \`${DB_PREFIX}grouppriv\` WHERE \`module\` = 'taizhang';"

if [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ]; then
  echo "执行数据库清理..."
  mysql -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" <<SQL
$DROP_SQL
SQL
  echo "  ✓ 已删表 ${DB_PREFIX}taizhang 及 module='taizhang' 权限行"
else
  echo "⚠ 未提供 DB_NAME/DB_USER，跳过数据库清理。请手动执行："
  echo "------------------------------------------------------------"
  echo "$DROP_SQL"
  echo "------------------------------------------------------------"
fi

echo "✅ 台账插件已全部移除。请强刷浏览器（Ctrl+F5）确认顶级菜单「项目台账」消失。"
