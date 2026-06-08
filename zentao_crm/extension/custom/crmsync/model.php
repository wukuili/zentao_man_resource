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
        $opp->contractAmount = (float)(zget($input, 'contractAmount', 0));
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
     * 写入/更新一条同步映射记录。
     *
     * @param  object $opp
     * @param  int    $projectID
     * @param  int    $productId
     * @param  string $status     success|failed
     * @param  string $errorMsg
     * @param  string $payload    原始JSON
     * @access public
     * @return void
     */
    public function saveMap(object $opp, int $projectID, int $productId, string $status, string $errorMsg = '', string $payload = ''): void
    {
        $data = new stdclass();
        $data->opportunityId = $opp->opportunityId;
        $data->projectID     = $projectID;
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
