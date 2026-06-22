#!/bin/bash
# 部署 crmsync 插件到 zbox 禅道 (在 WSL root 下执行)
set -e
SRC=/mnt/c/Users/liyon/Documents/code/zentao_man_resource/zentao_crm
ZT=/opt/zbox/app/zentao

cp "$SRC/config/ext/crmsync.php" "$ZT/config/ext/crmsync.php"
mkdir -p "$ZT/extension/custom/common/ext/config" "$ZT/extension/custom/common/ext/lang/zh-cn"
cp "$SRC/extension/custom/common/ext/config/crmsync.php" "$ZT/extension/custom/common/ext/config/"
cp "$SRC/extension/custom/common/ext/lang/zh-cn/crmsync.php" "$ZT/extension/custom/common/ext/lang/zh-cn/"
cp -r "$SRC/extension/custom/crmsync" "$ZT/extension/custom/"
mkdir -p "$ZT/extension/custom/group/ext/config" "$ZT/extension/custom/group/ext/lang/zh-cn"
cp "$SRC/extension/custom/group/ext/config/crmsync.php" "$ZT/extension/custom/group/ext/config/"
cp "$SRC/extension/custom/group/ext/lang/zh-cn/crmsync.php" "$ZT/extension/custom/group/ext/lang/zh-cn/"

# 配置对接令牌(与 CRM application-dev.yml 中 zentao.token 一致)
sed -i "s|^\$config->crmsync->apiTokens = array(|\$config->crmsync->apiTokens = array(\n    'please-change-this-token' => 'admin',|" "$ZT/extension/custom/crmsync/config.php"

# 建表 + 存量升级(targetType 列已存在时报错可忽略)
/opt/zbox/bin/mysql -uroot -p123456 zentao < "$SRC/db/install.sql"
/opt/zbox/bin/mysql -uroot -p123456 zentao < "$SRC/db/upgrade_program.sql" 2>/dev/null || true

# 清缓存
rm -rf "$ZT/tmp/cache/"* "$ZT/tmp/model/"* 2>/dev/null || true

echo '=== deployed files ==='
ls "$ZT/extension/custom/crmsync/"
echo '=== token config ==='
grep -A2 'apiTokens = array' "$ZT/extension/custom/crmsync/config.php"
echo '=== table ==='
/opt/zbox/bin/mysql -uroot -p123456 zentao -e 'SHOW TABLES LIKE "zt_crmsync_map";'
