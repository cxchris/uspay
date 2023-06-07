<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 充值列表
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Withdraw extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;

    protected $type = 1; //代收-1，代付-2
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('pay_order');
        if (!in_array($this->group_id , [self::MERCHANT_GROUP])) {
            $this->error('商户才可以查看');
        }
    }

    /**
     * 查看
     */
    public function index()
    {

        return $this->view->fetch();
    }
    
}
