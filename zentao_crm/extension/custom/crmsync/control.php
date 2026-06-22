<?php
declare(strict_types=1);
/**
 * CRM对接插件 control。
 *
 * 对外接口(供 CRM 调用, token 鉴权, 免登录):
 *   - crmsync-products : GET  返回禅道产品列表, 供 CRM 产品选择器
 *   - crmsync-projects : GET  返回禅道项目列表, 供 CRM "关联现有项目"选择器
 *   - crmsync-programs : GET  返回禅道项目集列表, 供 CRM "关联现有项目集"选择器
 *   - crmsync-receive  : POST 接收赢单/手动建项目(集)/关联现有项目(集)请求, targetType=project|program
 * 管理页面(禅道会话鉴权):
 *   - crmsync-browse   : 同步记录列表
 *   - crmsync-settings : 对接设置
 *   - crmsync-retry    : 失败记录重试
 */
class crmsync extends control
{
    /**
     * REST 鉴权: 校验 X-Resource-Token 头或 ?token= 参数。
     * 命中返回操作者账号; 未带 token 返回 false; token 无效则直接输出 401 并终止。
     *
     * @access private
     * @return string|false
     */
    private function verifyApiToken()
    {
        $token = '';
        if(!empty($_SERVER['HTTP_X_RESOURCE_TOKEN'])) $token = (string)$_SERVER['HTTP_X_RESOURCE_TOKEN'];
        elseif(!empty($this->get->token))             $token = (string)$this->get->token;
        elseif(!empty($_GET['token']))                $token = (string)$_GET['token'];
        if($token === '') return false;

        $tokens = isset($this->config->crmsync->apiTokens) ? (array)$this->config->crmsync->apiTokens : array();
        if(!isset($tokens[$token]))
        {
            $this->send(array('result' => 'fail', 'message' => 'invalid token', 'code' => 401));
            return false; // unreachable, send() exits
        }
        return $tokens[$token];
    }

    /**
     * 解析请求体(优先 JSON, 回退表单)。
     *
     * @access private
     * @return array
     */
    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if($raw)
        {
            $json = json_decode($raw, true);
            if(is_array($json)) return $json;
        }
        return $_POST ? (array)fixer::input('post')->get() : array();
    }

    /**
     * 对外: 产品列表。
     *
     * @access public
     * @return void
     */
    public function products()
    {
        $operator = $this->verifyApiToken();
        if($operator === false && empty($this->app->user->account)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        $data = $this->crmsync->getProducts();
        return $this->send(array('result' => 'success', 'data' => $data));
    }

    /**
     * 对外: 项目列表(供 CRM "关联现有项目"选择器)。
     *
     * @access public
     * @return void
     */
    public function projects()
    {
        $operator = $this->verifyApiToken();
        if($operator === false && empty($this->app->user->account)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        $data = $this->crmsync->getZentaoProjects();
        return $this->send(array('result' => 'success', 'data' => $data));
    }

    /**
     * 对外: 项目集列表(供 CRM "关联现有项目集"选择器)。
     *
     * @access public
     * @return void
     */
    public function programs()
    {
        $operator = $this->verifyApiToken();
        if($operator === false && empty($this->app->user->account)) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));

        $data = $this->crmsync->getZentaoPrograms();
        return $this->send(array('result' => 'success', 'data' => $data));
    }

    /**
     * 对外: 接收赢单/手动建项目(集)/关联现有项目(集)请求。
     *
     * body.targetType = project(默认) | program(项目集);
     * body.mode = create(默认) | link(关联现有, project 需带 zentaoProjectId, program 需带 zentaoProgramId);
     * 商机已有映射时不再重复创建, 仅按映射的目标类型把最新合同金额/回款金额刷新到项目预算或项目集预算。
     * 项目集型商机的金额只写项目集预算, 不向子项目分摊。
     *
     * @access public
     * @return void
     */
    public function receive()
    {
        $operator = $this->verifyApiToken();
        if($operator === false) return $this->send(array('result' => 'fail', 'message' => 'auth required', 'code' => 401));
        if(!$this->crmsync->setOperator((string)$operator)) return $this->send(array('result' => 'fail', 'message' => "operator account '{$operator}' not found", 'code' => 500));

        $body       = $this->parseBody();
        $payload    = json_encode($body, JSON_UNESCAPED_UNICODE);
        $opp        = $this->crmsync->normalizeOpportunity($body);
        $productId  = (int)(zget($body, 'productId', 0));
        $mode       = (string)zget($body, 'mode', 'create');
        $targetType = (string)zget($body, 'targetType', 'project') === 'program' ? 'program' : 'project';
        $linkID     = (int)zget($body, 'zentaoProjectId', 0);
        $programLinkID = (int)zget($body, 'zentaoProgramId', 0);
        $programName   = trim((string)zget($body, 'programName', ''));

        /* 校验必填。 */
        if($opp->opportunityId === '' || $opp->name === '')
        {
            return $this->send(array('result' => 'fail', 'message' => 'opportunityId 和 name 不能为空', 'code' => 400));
        }

        /* 幂等: 商机已有映射则不重复创建, 按映射目标类型仅刷新金额。 */
        $exist = $this->crmsync->getMapByOpportunity($opp->opportunityId);
        if($exist && $exist->projectID > 0)
        {
            $existType = (isset($exist->targetType) && $exist->targetType === 'program') ? 'program' : 'project';
            if($existType === 'program')
            {
                $this->crmsync->syncAmountsToProgram((int)$exist->projectID, $opp);
                $this->crmsync->saveMap($opp, (int)$exist->projectID, 0, 'success', '', $payload, 'program');
                return $this->send(array(
                    'result'      => 'exists',
                    'message'     => '该商机已关联项目集, 已更新项目集预算/回款信息',
                    'targetType'  => 'program',
                    'programID'   => (int)$exist->projectID,
                    'programLink' => $this->buildProgramLink((int)$exist->projectID),
                ));
            }

            $this->crmsync->syncAmountsToProject((int)$exist->projectID, $opp);
            $this->crmsync->saveMap($opp, (int)$exist->projectID, (int)$exist->productId, 'success', '', $payload);
            return $this->send(array(
                'result'      => 'exists',
                'message'     => '该商机已关联项目, 已更新合同金额/回款金额',
                'targetType'  => 'project',
                'projectID'   => (int)$exist->projectID,
                'projectLink' => $this->buildProjectLink((int)$exist->projectID),
            ));
        }

        /* ===== 项目集目标 ===== */
        if($targetType === 'program')
        {
            /* 关联现有项目集。 */
            if($mode === 'link')
            {
                if($programLinkID <= 0) return $this->send(array('result' => 'fail', 'message' => '关联模式必须指定 zentaoProgramId', 'code' => 400));
                $program = $this->crmsync->getProgramById($programLinkID);
                if(empty($program))
                {
                    $this->crmsync->saveMap($opp, 0, 0, 'failed', "项目集#{$programLinkID} 不存在或已删除", $payload, 'program');
                    return $this->send(array('result' => 'fail', 'message' => "项目集#{$programLinkID} 不存在或已删除", 'code' => 400));
                }

                $this->crmsync->syncAmountsToProgram($programLinkID, $opp, true);
                $this->crmsync->saveMap($opp, $programLinkID, 0, 'success', '', $payload, 'program');
                return $this->send(array(
                    'result'      => 'success',
                    'message'     => '已关联到现有项目集并同步金额信息',
                    'targetType'  => 'program',
                    'programID'   => $programLinkID,
                    'programLink' => $this->buildProgramLink($programLinkID),
                ));
            }

            /* 创建项目集(不建子项目, 子项目由禅道侧后续创建)。 */
            $programID = $this->crmsync->createProgramFromOpportunity($opp, $programName);
            if(!$programID)
            {
                $error = dao::isError() ? implode('; ', array_map(fn($e) => is_array($e) ? implode(',', $e) : $e, dao::getError())) : '创建失败';
                $this->crmsync->saveMap($opp, 0, 0, 'failed', $error, $payload, 'program');
                return $this->send(array('result' => 'fail', 'message' => $error, 'code' => 500));
            }

            $this->crmsync->saveMap($opp, (int)$programID, 0, 'success', '', $payload, 'program');
            return $this->send(array(
                'result'      => 'success',
                'message'     => '项目集创建成功, 请在禅道中创建其下子项目',
                'targetType'  => 'program',
                'programID'   => (int)$programID,
                'programLink' => $this->buildProgramLink((int)$programID),
            ));
        }

        /* ===== 项目目标 ===== */

        /* 关联现有项目。 */
        if($mode === 'link')
        {
            if($linkID <= 0) return $this->send(array('result' => 'fail', 'message' => '关联模式必须指定 zentaoProjectId', 'code' => 400));
            $project = $this->crmsync->getProjectById($linkID);
            if(empty($project))
            {
                $this->crmsync->saveMap($opp, 0, 0, 'failed', "项目#{$linkID} 不存在或已删除", $payload);
                return $this->send(array('result' => 'fail', 'message' => "项目#{$linkID} 不存在或已删除", 'code' => 400));
            }

            $this->crmsync->syncAmountsToProject($linkID, $opp, true);
            $this->crmsync->saveMap($opp, $linkID, 0, 'success', '', $payload);
            return $this->send(array(
                'result'      => 'success',
                'message'     => '已关联到现有项目并同步金额信息',
                'targetType'  => 'project',
                'projectID'   => $linkID,
                'projectLink' => $this->buildProjectLink($linkID),
            ));
        }

        /* 创建项目。 */
        $projectID = $this->crmsync->createProjectFromOpportunity($opp, $productId);
        if(!$projectID)
        {
            $error = dao::isError() ? implode('; ', array_map(fn($e) => is_array($e) ? implode(',', $e) : $e, dao::getError())) : '创建失败';
            $this->crmsync->saveMap($opp, 0, $productId, 'failed', $error, $payload);
            return $this->send(array('result' => 'fail', 'message' => $error, 'code' => 500));
        }

        $this->crmsync->saveMap($opp, (int)$projectID, $productId, 'success', '', $payload);
        $this->crmsync->syncToTaizhang((int)$projectID, $opp);
        return $this->send(array(
            'result'      => 'success',
            'message'     => '项目创建成功',
            'targetType'  => 'project',
            'projectID'   => (int)$projectID,
            'projectLink' => $this->buildProjectLink((int)$projectID),
        ));
    }

    /**
     * 构造项目访问链接(绝对地址)。
     *
     * @param  int $projectID
     * @access private
     * @return string
     */
    private function buildProjectLink(int $projectID): string
    {
        return common::getSysURL() . $this->createLink('project', 'view', "projectID=$projectID", 'html');
    }

    /**
     * 构造项目集访问链接(绝对地址)。
     *
     * @param  int $programID
     * @access private
     * @return string
     */
    private function buildProgramLink(int $programID): string
    {
        return common::getSysURL() . $this->createLink('program', 'view', "programID=$programID", 'html');
    }

    /**
     * 管理页: 同步记录列表(客户端分页)。
     *
     * @access public
     * @return void
     */
    public function browse()
    {
        $this->view->title   = $this->lang->crmsync->browseTitle;
        $this->view->records = $this->crmsync->getMapList();
        $this->display();
    }

    /**
     * 管理页: 对接设置。
     *
     * @access public
     * @return void
     */
    public function settings()
    {
        if(!empty($_POST))
        {
            $this->loadModel('setting');
            $this->setting->setItem('system.crmsync.defaultPM',            (string)$this->post->defaultPM);
            $this->setting->setItem('system.crmsync.defaultProgram',       (int)$this->post->defaultProgram);
            $this->setting->setItem('system.crmsync.defaultProductName',   (string)$this->post->defaultProductName);
            $this->setting->setItem('system.crmsync.defaultDurationMonths', (int)$this->post->defaultDurationMonths);

            /* token 留空表示不变更。 */
            $token    = trim((string)$this->post->apiToken);
            $operator = trim((string)$this->post->apiTokenOperator);
            if($operator === '') $operator = trim((string)$this->post->defaultPM);
            if($token !== '')
            {
                if(!preg_match('/^[A-Za-z0-9_\-]+$/', $token)) return $this->send(array('result' => 'fail', 'message' => '令牌只能包含字母/数字/下划线/连字符'));
                if($operator === '') return $this->send(array('result' => 'fail', 'message' => '请填写操作者账号或默认项目经理'));
                /* 保存新 token 前先清除所有旧 token，防止旧 token 残留仍可通过鉴权。 */
                $this->dao->delete()->from('zt_config')
                    ->where('owner')->eq('system')
                    ->andWhere('module')->eq('crmsync')
                    ->andWhere('section')->eq('apiTokens')
                    ->exec();
                $this->setting->setItems('system.crmsync.apiTokens', array($token => $operator));
            }

            if(dao::isError()) return $this->send(array('result' => 'fail', 'message' => dao::getError()));
            return $this->send(array('result' => 'success', 'message' => $this->lang->crmsync->saveSuccess, 'load' => true));
        }

        $config = $this->config->crmsync;
        $this->view->title                = $this->lang->crmsync->settingsTitle;
        $this->view->defaultPM            = zget($config, 'defaultPM', 'admin');
        $this->view->defaultProgram       = (int)zget($config, 'defaultProgram', 0);
        $this->view->defaultProductName   = (string)zget($config, 'defaultProductName', '');
        $this->view->defaultDurationMonths = (int)zget($config, 'defaultDurationMonths', 6);
        $this->display();
    }

    /**
     * 管理页: 失败记录重试。
     *
     * @param  int $id  zt_crmsync_map.id
     * @access public
     * @return void
     */
    public function retry(int $id)
    {
        $record = $this->dao->select('*')->from(TABLE_CRMSYNC_MAP)->where('id')->eq($id)->fetch();
        if(empty($record)) return $this->send(array('result' => 'fail', 'message' => 'record not found'));

        if($record->projectID > 0) return $this->send(array('result' => 'success', 'message' => '该商机已有项目', 'load' => true));

        $body = $record->payload ? json_decode($record->payload, true) : array();
        if(!is_array($body)) $body = array();
        $opp       = $this->crmsync->normalizeOpportunity($body);
        if($opp->opportunityId === '') $opp->opportunityId = $record->opportunityId;
        if($opp->name === '')          $opp->name          = $record->oppName;
        $productId = (int)$record->productId;

        $projectID = $this->crmsync->createProjectFromOpportunity($opp, $productId);
        if(!$projectID)
        {
            $error = dao::isError() ? implode('; ', array_map(fn($e) => is_array($e) ? implode(',', $e) : $e, dao::getError())) : '创建失败';
            $this->crmsync->saveMap($opp, 0, $productId, 'failed', $error, (string)$record->payload);
            return $this->send(array('result' => 'fail', 'message' => $error));
        }

        $this->crmsync->saveMap($opp, (int)$projectID, $productId, 'success', '', (string)$record->payload);
        $this->crmsync->syncToTaizhang((int)$projectID, $opp);
        return $this->send(array('result' => 'success', 'message' => $this->lang->crmsync->retrySuccess, 'load' => true));
    }
}
