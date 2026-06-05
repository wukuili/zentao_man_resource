<?php
/**
 * 外包标识插件 — 卸载脚本
 *
 * 卸载时执行。出于数据安全考虑，默认不删除 outsourced 字段。
 * 如需彻底清理，可手动执行 ALTER TABLE `zt_user` DROP COLUMN `outsourced`。
 */
class waibaoUninstall
{
    /**
     * 卸载入口
     *
     * @access public
     * @return bool
     */
    public function uninstall()
    {
        /* 不自动删除 outsourced 字段，保留数据。
         * 如需彻底清理，取消以下注释：
         *
         * $this->dbh->query("ALTER TABLE `zt_user` DROP COLUMN `outsourced`");
         */

        return true;
    }
}