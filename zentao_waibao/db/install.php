<?php
/**
 * 外包标识插件 — 安装脚本
 *
 * 安装时执行，为 zt_user 表添加 outsourced 字段。
 */
class waibaoInstall
{
    /**
     * 安装入口
     *
     * @access public
     * @return bool
     */
    public function install()
    {
        /* 检查 outsourced 字段是否已存在 */
        $fields = $this->dbh->query("SHOW COLUMNS FROM `zt_user` LIKE 'outsourced'")->fetchAll();
        if(empty($fields))
        {
            /* 添加 outsourced 字段 */
            $this->dbh->query("ALTER TABLE `zt_user` ADD COLUMN `outsourced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否外包(0:自有,1:外包)' AFTER `role`");
        }

        return true;
    }
}