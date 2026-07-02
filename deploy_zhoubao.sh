#!/bin/bash
# 部署 zhoubao 插件到 zbox 禅道 (在 WSL root 下执行)
set -e

SRC=/mnt/c/Users/liyon/Documents/code/zentao_man_resource/zentao_zhoubao
ZT=/opt/zbox/app/zentao
MYSQL="/opt/zbox/bin/mysql -uroot -p123456 zentao"

cp "$SRC/config/ext/zhoubao.php" "$ZT/config/ext/zhoubao.php"

mkdir -p "$ZT/extension/custom/common/ext/config" "$ZT/extension/custom/common/ext/lang/zh-cn"
cp "$SRC/extension/custom/common/ext/config/zhoubao.php" "$ZT/extension/custom/common/ext/config/"
cp "$SRC/extension/custom/common/ext/lang/zh-cn/zhoubao.php" "$ZT/extension/custom/common/ext/lang/zh-cn/"

mkdir -p "$ZT/extension/custom/group/ext/config" "$ZT/extension/custom/group/ext/lang/zh-cn"
cp "$SRC/extension/custom/group/ext/config/zhoubao.php" "$ZT/extension/custom/group/ext/config/"
cp "$SRC/extension/custom/group/ext/lang/zh-cn/zhoubao.php" "$ZT/extension/custom/group/ext/lang/zh-cn/"

rm -rf "$ZT/extension/custom/zhoubao"
cp -r "$SRC/extension/custom/zhoubao" "$ZT/extension/custom/"

# 建表 + 存量升级。install.sql 只建新表，旧版表结构需补 hasRisk 列。
$MYSQL < "$SRC/db/install.sql"
$MYSQL < "$SRC/db/upgrade_hasrisk_column.sql"

# 清缓存，避免 ZenTao 继续使用旧的合并语言包/模型。
rm -rf "$ZT/tmp/cache/"* "$ZT/tmp/model/"* 2>/dev/null || true

echo '=== deployed zhoubao files ==='
ls "$ZT/extension/custom/zhoubao/"
echo '=== zt_zhoubao columns ==='
$MYSQL -e 'SHOW COLUMNS FROM zt_zhoubao;'
