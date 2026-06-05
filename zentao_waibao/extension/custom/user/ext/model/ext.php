<?php
/**
 * 外包标识插件 — 用户模型扩展
 *
 * 扩展 userModel，在用户列表查询中自动包含 outsourced 字段，
 * 并提供按外包标识获取用户的方法。
 *
 * 注意：ZenTao 20+ 的 ext/model/ 文件中只能包含方法定义，
 * 不能声明 class。方法会被合并到 userModel 中。
 */

/**
 * 获取所有外包人员账号列表。
 *
 * @param  string $params 查询参数（同 getList 的参数）
 * @access public
 * @return array account => realname
 */
public function getOutsourcedPairs($params = 'nodeleted')
{
    return $this->dao->select('account, realname')
        ->from(TABLE_USER)
        ->where('deleted')->eq('0')
        ->andWhere('outsourced')->eq('1')
        ->beginIF(strpos($params, 'all') === false)->andWhere('visions')->like("%{$this->config->vision}%")->fi()
        ->orderBy('account')
        ->fetchPairs('account', 'realname');
}

/**
 * 获取所有自有人员账号列表。
 *
 * @param  string $params 查询参数
 * @access public
 * @return array account => realname
 */
public function getInternalPairs($params = 'nodeleted')
{
    return $this->dao->select('account, realname')
        ->from(TABLE_USER)
        ->where('deleted')->eq('0')
        ->andWhere('outsourced')->eq('0')
        ->beginIF(strpos($params, 'all') === false)->andWhere('visions')->like("%{$this->config->vision}%")->fi()
        ->orderBy('account')
        ->fetchPairs('account', 'realname');
}

/**
 * 获取带外包标识的完整用户列表。
 *
 * @param  string $params 查询参数
 * @access public
 * @return array
 */
public function getListWithOutsourced($params = 'nodeleted')
{
    return $this->dao->select('id, account, realname, role, dept, outsourced')
        ->from(TABLE_USER)
        ->where('deleted')->eq('0')
        ->beginIF(strpos($params, 'all') === false)->andWhere('visions')->like("%{$this->config->vision}%")->fi()
        ->orderBy('account')
        ->fetchAll('account');
}