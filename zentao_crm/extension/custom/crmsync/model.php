<?php
declare(strict_types=1);
/**
 * CRM对接插件 model: 商机赢单 → 创建禅道融合瀑布项目, 映射记录维护, 产品列表。
 */
class crmsyncModel extends model
{
    /**
     * 对外: 获取可供 CRM 选择的产品列表。
     *
     * @access public
     * @return array  [{id, name, code, program}]
     */
    public function getProducts(): array
    {
        $products = $this->dao->select('id,name,code,program,status')->from(TABLE_PRODUCT)
            ->where('deleted')->eq('0')
            ->andWhere('status')->ne('closed')
            ->orderBy('program,id')
            ->fetchAll('id');

        $list = array();
        foreach($products as $product)
        {
            $list[] = array(
                'id'      => (int)$product->id,
                'name'    => $product->name,
                'code'    => $product->code,
                'program' => (int)$product->program,
            );
        }
        return $list;
    }

    /**
     * 对外: 获取可供 CRM "关联现有项目"选择的项目列表。
     *
     * parent 为所属项目集ID(0=顶层), 供 CRM 侧按项目集级联过滤。
     *
     * @access public
     * @return array  [{id, name, code, status, PM, begin, end, parent}]
     */
    public function getZentaoProjects(): array
    {
        $projects = $this->dao->select('id,name,code,status,PM,begin,end,parent')->from(TABLE_PROJECT)
            ->where('deleted')->eq('0')
            ->andWhere('type')->eq('project')
            ->orderBy('id_desc')
            ->fetchAll('id');

        $list = array();
        foreach($projects as $project)
        {
            $list[] = array(
                'id'     => (int)$project->id,
                'name'   => $project->name,
                'code'   => $project->code,
                'status' => $project->status,
                'PM'     => $project->PM,
                'begin'  => $project->begin,
                'end'    => $project->end,
                'parent' => (int)$project->parent,
            );
        }
        return $list;
    }

    /**
     * 对外: 获取可供 CRM "关联现有项目集"选择的项目集列表。
     *
     * @access public
     * @return array  [{id, name, parent, grade, status, budget, begin, end}]
     */
    public function getZentaoPrograms(): array
    {
        $programs = $this->dao->select('id,name,parent,grade,status,budget,begin,end')->from(TABLE_PROGRAM)
            ->where('deleted')->eq('0')
            ->andWhere('type')->eq('program')
            ->andWhere('status')->ne('closed')
            ->orderBy('grade,`order`')
            ->fetchAll('id');

        $list = array();
        foreach($programs as $program)
        {
            $list[] = array(
                'id'     => (int)$program->id,
                'name'   => $program->name,
                'parent' => (int)$program->parent,
                'grade'  => (int)$program->grade,
                'status' => $program->status,
                'budget' => (float)$program->budget,
                'begin'  => $program->begin,
                'end'    => $program->end,
            );
        }
        return $list;
    }

    /**
     * 按ID查询未删除的项目集。
     *
     * @param  int $programID
     * @access public
     * @return object|false
     */
    public function getProgramById(int $programID)
    {
        return $this->dao->select('*')->from(TABLE_PROGRAM)
            ->where('id')->eq($programID)
            ->andWhere('type')->eq('program')
            ->andWhere('deleted')->eq('0')
            ->fetch();
    }

    /**
     * 按ID查询未删除的项目。
     *
     * @param  int $projectID
     * @access public
     * @return object|false
     */
    public function getProjectById(int $projectID)
    {
        return $this->dao->select('*')->from(TABLE_PROJECT)
            ->where('id')->eq($projectID)
            ->andWhere('type')->eq('project')
            ->andWhere('deleted')->eq('0')
            ->fetch();
    }

    /**
     * 把商机金额刷新到禅道项目: 合同金额 → 项目预算, 并以动态留痕(含回款金额)。
     *
     * 禅道项目没有"回款金额"原生字段, 故回款金额仅记入项目动态备注;
     * 原始数值同时保存在 zt_crmsync_map.payload, 可追溯。
     *
     * @param  int    $projectID
     * @param  object $opp      规整后的商机对象
     * @param  bool   $isLink   true=关联现有项目场景, 仅影响动态文案
     * @access public
     * @return void
     */
    public function syncAmountsToProject(int $projectID, object $opp, bool $isLink = false): void
    {
        $data = new stdclass();
        if($opp->contractAmount > 0)
        {
            $data->budget     = round($opp->contractAmount, 2);
            $data->budgetUnit = 'CNY';
        }
        $data->market         = (int)$opp->opportunityId;
        $data->lastEditedBy   = $this->app->user->account;
        $data->lastEditedDate = helper::now();
        $this->dao->update(TABLE_PROJECT)->data($data)->where('id')->eq($projectID)->exec();

        $parts   = array();
        $parts[] = ($isLink ? '关联' : '同步') . 'CRM商机#' . $opp->opportunityId . ($opp->name !== '' ? '(' . $opp->name . ')' : '');
        if($opp->customerName !== '') $parts[] = '客户: ' . $opp->customerName;
        if($opp->contractAmount > 0)      $parts[] = '合同金额: ' . round($opp->contractAmount, 2) . ' 元(已写入项目预算)';
        elseif($opp->estimatedAmount > 0) $parts[] = '预计金额: ' . round($opp->estimatedAmount, 2) . ' 元(合同金额为空, 已写入台账合同金额)';
        if($opp->receivedAmount > 0)  $parts[] = '回款金额: ' . round($opp->receivedAmount, 2) . ' 元';
        $comment = htmlspecialchars(implode('；', $parts), ENT_QUOTES, 'UTF-8');
        $this->loadModel('action')->create('project', $projectID, 'commented', $comment);

        $this->syncToTaizhang($projectID, $opp);
    }

    /**
     * 把商机金额刷新到禅道项目集: 合同金额 → 项目集预算, 并以动态留痕(含回款金额)。
     *
     * 设计约定: 项目集型商机的金额只写项目集预算, 不向其下子项目分摊(分摊由禅道侧项目经理决策);
     * 台账(zt_taizhang)按项目维度记账, 项目集不写台账。
     *
     * @param  int    $programID
     * @param  object $opp      规整后的商机对象
     * @param  bool   $isLink   true=关联现有项目集场景, 仅影响动态文案
     * @access public
     * @return void
     */
    public function syncAmountsToProgram(int $programID, object $opp, bool $isLink = false): void
    {
        $data = new stdclass();
        if($opp->contractAmount > 0)
        {
            $data->budget     = round($opp->contractAmount, 2);
            $data->budgetUnit = 'CNY';
        }
        $data->market         = (int)$opp->opportunityId;
        $data->lastEditedBy   = $this->app->user->account;
        $data->lastEditedDate = helper::now();
        $this->dao->update(TABLE_PROGRAM)->data($data)->where('id')->eq($programID)->exec();

        $parts   = array();
        $parts[] = ($isLink ? '关联' : '同步') . 'CRM商机#' . $opp->opportunityId . ($opp->name !== '' ? '(' . $opp->name . ')' : '');
        if($opp->customerName !== '') $parts[] = '客户: ' . $opp->customerName;
        if($opp->contractAmount > 0)  $parts[] = '合同金额: ' . round($opp->contractAmount, 2) . ' 元(已写入项目集预算, 子项目预算请在禅道内分配)';
        if($opp->receivedAmount > 0)  $parts[] = '回款金额: ' . round($opp->receivedAmount, 2) . ' 元';
        $comment = htmlspecialchars(implode('；', $parts), ENT_QUOTES, 'UTF-8');
        $this->loadModel('action')->create('program', $programID, 'commented', $comment);
    }

    /**
     * 把商机金额写入二开「项目台账」(zt_taizhang, 按 projectID 唯一)。
     *
     * CRM 金额单位为元, 台账为万元(除以 10000);
     * 合同金额 → revenue(合同金额为空时回退用预计金额), 回款金额 → receivedAmount;
     * CRM 未填(<=0)的金额不覆盖台账中人工维护的既有值;
     * 台账插件未安装(无表)或写入失败时静默跳过, 不阻断同步主流程。
     *
     * @param  int    $projectID
     * @param  object $opp       规整后的商机对象
     * @access public
     * @return void
     */
    public function syncToTaizhang(int $projectID, object $opp): void
    {
        if($projectID <= 0) return;
        try
        {
            if(!$this->dao->query("SHOW TABLES LIKE 'zt_taizhang'")->fetch()) return;

            $amount   = $opp->contractAmount > 0 ? $opp->contractAmount : $opp->estimatedAmount;
            $revenue  = $amount > 0 ? round($amount / 10000, 2) : 0;
            $received = $opp->receivedAmount > 0 ? round($opp->receivedAmount / 10000, 2) : 0;

            $row = $this->dao->select('id')->from('zt_taizhang')->where('projectID')->eq($projectID)->fetch();
            $now = helper::now();

            $data = new stdclass();
            if($revenue  > 0) $data->revenue        = $revenue;
            if($received > 0) $data->receivedAmount = $received;
            $data->updatedAt = $now;

            if($row)
            {
                $this->dao->update('zt_taizhang')->data($data)->where('id')->eq($row->id)->exec();
            }
            else
            {
                $data->projectID = $projectID;
                $data->shortName = mb_substr($opp->name, 0, 100);
                $data->createdAt = $now;
                $data->deleted   = 0;
                $this->dao->insert('zt_taizhang')->data($data)->exec();
            }
        }
        catch(Exception $e)
        {
            /* 台账同步失败不影响项目同步结果。 */
        }
    }

    /**
     * 按 CRM 商机ID 查询映射记录。
     *
     * @param  string $opportunityId
     * @access public
     * @return object|false
     */
    public function getMapByOpportunity(string $opportunityId)
    {
        return $this->dao->select('*')->from(TABLE_CRMSYNC_MAP)
            ->where('opportunityId')->eq($opportunityId)
            ->fetch();
    }

    /**
     * 同步记录列表(用于管理页)。
     *
     * @param  object $pager
     * @access public
     * @return array
     */
    public function getMapList($pager = null): array
    {
        return $this->dao->select('*')->from(TABLE_CRMSYNC_MAP)
            ->orderBy('id_desc')
            ->page($pager)
            ->fetchAll('id');
    }

    /**
     * 把 webhook 传入的商机数据规整为标准对象。
     *
     * @param  object|array $input
     * @access public
     * @return object
     */
    public function normalizeOpportunity($input): object
    {
        $input = (object)$input;
        $opp = new stdclass();
        $opp->opportunityId = trim((string)(zget($input, 'opportunityId', '')));
        $opp->name          = trim((string)(zget($input, 'name', '')));
        $opp->customerName  = trim((string)(zget($input, 'customerName', '')));
        $opp->closeReason   = trim((string)(zget($input, 'closeReason', '')));
        $opp->ownerName     = trim((string)(zget($input, 'ownerName', '')));
        $opp->contractAmount  = (float)(zget($input, 'contractAmount', 0));
        $opp->estimatedAmount = (float)(zget($input, 'estimatedAmount', 0));
        $opp->receivedAmount  = (float)(zget($input, 'receivedAmount', 0));
        $opp->contractDate  = $this->normalizeDate((string)(zget($input, 'contractDate', '')));
        $opp->closeTime     = (string)(zget($input, 'closeTime', ''));
        return $opp;
    }

    /**
     * 规整日期为 Y-m-d; 非法返回空串。
     *
     * @param  string $date
     * @access public
     * @return string
     */
    public function normalizeDate(string $date): string
    {
        $date = trim($date);
        if($date === '') return '';
        $ts = strtotime($date);
        if($ts === false) return '';
        return date('Y-m-d', $ts);
    }

    /**
     * 核心: 由商机数据创建一个融合瀑布项目。
     *
     * 复用禅道原生 projectModel::create(), 以保证团队/文档库/产品/权限初始化与手工建项目一致;
     * 因原生 create() 的产品关联依赖 $_POST, 故在此受控地预置 $_POST['products']/$_POST['branch']。
     * 幂等由 zt_project.market = 商机ID 保证, 重复调用前请先 getMapByOpportunity 查重。
     *
     * @param  object $opp        规整后的商机对象
     * @param  int    $productId  关联产品ID; 0 表示"单纯项目"(禅道自动建产品)
     * @access public
     * @return int|false          成功返回 projectID, 失败返回 false (dao::getError() 可取错误)
     */
    public function createProjectFromOpportunity(object $opp, int $productId = 0)
    {
        $this->loadModel('project');

        $config = $this->config->crmsync;

        $project = new stdclass();
        $project->name           = $opp->name;
        $project->model          = zget($config, 'projectModel', 'waterfallplus');
        $project->type           = 'project';
        $project->status         = 'wait';
        $project->begin          = helper::today();
        $project->end            = $opp->contractDate ? $opp->contractDate : date('Y-m-d', strtotime('+' . (int)zget($config, 'defaultDurationMonths', 6) . ' months'));
        $project->days           = 0;
        $project->PM             = zget($config, 'defaultPM', 'admin');
        $project->acl            = 'open';
        $project->whitelist      = '';
        $project->budget         = $opp->contractAmount > 0 ? round($opp->contractAmount, 2) : 0;
        $project->budgetUnit     = 'CNY';
        $project->multiple       = '1';
        $project->market         = (int)$opp->opportunityId;
        $project->team           = mb_substr($opp->name, 0, 30);
        $project->desc           = $this->buildDesc($opp);
        $project->openedBy       = $this->app->user->account;
        $project->openedDate     = helper::now();
        $project->lastEditedBy   = $this->app->user->account;
        $project->lastEditedDate = helper::now();

        $postData = new stdclass();
        $postData->rawdata = new stdclass();

        /* 备份并按建项目需要重置相关 $_POST 字段。 */
        $bakProducts      = isset($_POST['products'])      ? $_POST['products']      : null;
        $bakBranch        = isset($_POST['branch'])        ? $_POST['branch']        : null;
        $bakOtherProducts = isset($_POST['otherProducts']) ? $_POST['otherProducts'] : null;
        unset($_POST['otherProducts']);

        if($productId > 0)
        {
            $product = $this->loadModel('product')->getByID($productId);
            if(empty($product))
            {
                dao::$errors['product'] = "产品#{$productId} 不存在";
                return false;
            }
            $project->parent     = (int)$product->program;
            $project->hasProduct = 1;

            $_POST['products']        = array($productId);
            $_POST['branch']          = array(array(0));
            $postData->rawdata->products = array($productId);
            $postData->rawdata->branch   = array(array(0));
            $postData->rawdata->parent   = $project->parent;
        }
        else
        {
            /* 单纯项目: 不选已有产品, 由 create() 自动建一个产品。 */
            $project->parent     = (int)zget($config, 'defaultProgram', 0);
            $project->hasProduct = 0;
            unset($_POST['products'], $_POST['branch']);

            $postData->rawdata->parent      = $project->parent;
            $postData->rawdata->newProduct  = 1;
            $postData->rawdata->productName = trim((string)zget($config, 'defaultProductName', '')) ?: $opp->name;
        }

        $projectID = $this->project->create($project, $postData);

        /* 还原 $_POST。 */
        if($bakProducts === null)      unset($_POST['products']);      else $_POST['products']      = $bakProducts;
        if($bakBranch === null)        unset($_POST['branch']);        else $_POST['branch']        = $bakBranch;
        if($bakOtherProducts === null) unset($_POST['otherProducts']); else $_POST['otherProducts'] = $bakOtherProducts;

        if(!$projectID || dao::isError()) return false;

        /* 记录动态。 */
        $this->loadModel('action')->create('project', (int)$projectID, 'opened');

        return (int)$projectID;
    }

    /**
     * 核心: 由商机数据创建一个项目集(顶层)。
     *
     * 复用禅道原生 programModel::create(), 以保证树路径(path/grade)/白名单/权限视图初始化与手工建项目集一致。
     * 项目集下的子项目由禅道侧后续创建或由 CRM 二期关联, 此处不建子项目。
     *
     * @param  object $opp          规整后的商机对象
     * @param  string $programName  项目集名称; 空串则用商机名
     * @access public
     * @return int|false            成功返回 programID, 失败返回 false (dao::getError() 可取错误)
     */
    public function createProgramFromOpportunity(object $opp, string $programName = '')
    {
        $this->loadModel('program');

        $config = $this->config->crmsync;

        $program = new stdclass();
        $program->name           = $programName !== '' ? $programName : $opp->name;
        $program->type           = 'program';
        $program->parent         = 0;
        $program->status         = 'wait';
        $program->begin          = helper::today();
        $program->end            = $opp->contractDate ? $opp->contractDate : date('Y-m-d', strtotime('+' . (int)zget($config, 'defaultDurationMonths', 6) . ' months'));
        $program->days           = 0;
        $program->PM             = zget($config, 'defaultPM', 'admin');
        $program->acl            = 'open';
        $program->whitelist      = '';
        $program->budget         = $opp->contractAmount > 0 ? round($opp->contractAmount, 2) : 0;
        $program->budgetUnit     = 'CNY';
        $program->market         = (int)$opp->opportunityId;
        $program->desc           = $this->buildDesc($opp);
        $program->openedBy       = $this->app->user->account;
        $program->openedDate     = helper::now();
        $program->lastEditedBy   = $this->app->user->account;
        $program->lastEditedDate = helper::now();

        $programID = $this->program->create($program);
        if(!$programID || dao::isError()) return false;

        /* 记录动态。 */
        $this->loadModel('action')->create('program', (int)$programID, 'opened');

        return (int)$programID;
    }

    /**
     * 写入/更新一条同步映射记录。
     *
     * @param  object $opp
     * @param  int    $projectID  禅道目标ID(项目ID 或 项目集ID, 由 targetType 区分)
     * @param  int    $productId
     * @param  string $status     success|failed
     * @param  string $errorMsg
     * @param  string $payload    原始JSON
     * @param  string $targetType project|program
     * @access public
     * @return void
     */
    public function saveMap(object $opp, int $projectID, int $productId, string $status, string $errorMsg = '', string $payload = '', string $targetType = 'project'): void
    {
        $data = new stdclass();
        $data->opportunityId = $opp->opportunityId;
        $data->projectID     = $projectID;
        $data->targetType    = $targetType === 'program' ? 'program' : 'project';
        $data->productId     = $productId;
        $data->customerName  = $opp->customerName;
        $data->oppName       = $opp->name;
        $data->payload       = $payload;
        $data->syncStatus    = $status;
        $data->errorMsg      = mb_substr($errorMsg, 0, 480);
        $data->createdBy     = $this->app->user->account;
        $data->createdDate   = helper::now();

        $exist = $this->getMapByOpportunity($opp->opportunityId);
        if($exist)
        {
            $this->dao->update(TABLE_CRMSYNC_MAP)->data($data)->where('id')->eq($exist->id)->exec();
        }
        else
        {
            $this->dao->insert(TABLE_CRMSYNC_MAP)->data($data)->exec();
        }
    }

    /**
     * 构造项目描述(溯源信息)。
     *
     * @param  object $opp
     * @access protected
     * @return string
     */
    protected function buildDesc(object $opp): string
    {
        $parts = array();
        $parts[] = '来源CRM商机#' . $opp->opportunityId;
        if($opp->customerName !== '') $parts[] = '客户: ' . $opp->customerName;
        if($opp->contractAmount > 0)  $parts[] = '合同金额: ' . $opp->contractAmount;
        if($opp->receivedAmount > 0)  $parts[] = '回款金额: ' . $opp->receivedAmount;
        if($opp->closeReason !== '')  $parts[] = '赢单说明: ' . $opp->closeReason;
        return htmlspecialchars(implode('；', $parts), ENT_QUOTES, 'UTF-8');
    }

    /**
     * 以指定禅道账号身份运行(供 webhook 免登录场景设置操作者)。
     *
     * @param  string $account
     * @access public
     * @return bool
     */
    public function setOperator(string $account): bool
    {
        $user = $this->loadModel('user')->getById($account);
        if(empty($user)) return false;
        $this->app->user = $user;
        return true;
    }
}
