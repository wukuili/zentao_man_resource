#!/bin/bash
# 部署: zt_taizhang 补金额列 + 重部 taizhang/crmsync 插件 (WSL root 下执行)
set -e
TZ_SRC=/mnt/c/Users/liyon/Documents/code/zentao_man_resource/zentao_taizhang
ZT=/opt/zbox/app/zentao
MYSQL="/opt/zbox/bin/mysql -uroot -p123456 zentao"

# 1. 补列(已存在则跳过)
$MYSQL < "$TZ_SRC/db/upgrade_amount_columns.sql"

# 2. 重部 taizhang 模块文件(源码已支持回款/外采金额列展示)
cp -r "$TZ_SRC/extension/custom/taizhang" "$ZT/extension/custom/"
cp "$TZ_SRC/config/ext/taizhang.php" "$ZT/config/ext/taizhang.php"
cp "$TZ_SRC/extension/custom/common/ext/config/taizhang.php" "$ZT/extension/custom/common/ext/config/" 2>/dev/null || true
cp "$TZ_SRC/extension/custom/common/ext/lang/zh-cn/taizhang.php" "$ZT/extension/custom/common/ext/lang/zh-cn/" 2>/dev/null || true
cp "$TZ_SRC/extension/custom/group/ext/config/taizhang.php" "$ZT/extension/custom/group/ext/config/" 2>/dev/null || true
cp "$TZ_SRC/extension/custom/group/ext/lang/zh-cn/taizhang.php" "$ZT/extension/custom/group/ext/lang/zh-cn/" 2>/dev/null || true

# 3. 重部 crmsync (复用既有脚本: 拷文件+token+建表+清缓存)
bash /mnt/c/Users/liyon/Documents/code/zentao_man_resource/deploy_crmsync.sh

echo '=== zt_taizhang columns ==='
$MYSQL -e "SHOW COLUMNS FROM zt_taizhang LIKE '%Amount%';"
echo '=== syncToTaizhang deployed ==='
grep -c 'syncToTaizhang' "$ZT/extension/custom/crmsync/model.php" "$ZT/extension/custom/crmsync/control.php"
