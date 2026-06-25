<?php
/**
 * 项目走查 — 控制器
 * 提供走查列表浏览、导出、单项目诊断。
 */
class zoucha extends control
{
    /**
     * 走查列表页（默认入口）。
     *
     * 注意：禅道 PATH_INFO 路由按「位置」把 URL 参数映射到方法形参，
     * 分页参数 $pageID 必须声明为形参，否则翻页链接永远落在第 1 页。
     *
     * @param string $rule   问题类型过滤（规则键；'' 或 'all' 表示全部）
     * @param int    $pageID 页码
     */
    public function browse($rule = '', $pageID = 1)
    {
        $rule   = ($rule === false || $rule === null) ? '' : (string)$rule;
        $pageID = (int)$pageID;
        if($rule === 'all') $rule = '';

        $results = $this->zoucha->inspect();

        $this->view->title    = $this->lang->zoucha->browse;
        $this->view->results  = $results;
        $this->view->rule     = $rule;
        $this->view->pageID   = max(1, $pageID);
        $this->view->pageTotal = 1;
        $this->view->recPerPage = isset($this->config->zoucha->recPerPage) ? (int)$this->config->zoucha->recPerPage : 20;
        $this->view->total    = count($results);
        $this->view->ruleCounts = array();

        $this->display();
    }
}
