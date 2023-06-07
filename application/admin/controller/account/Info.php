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
 * 商户账户信息
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Info extends Backend
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
        $row = $this->merchant;
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row['collection_fee_rate'] = $this->transfer_rate($row['collection_fee_rate']);
        $row['payment_fee_rate'] = $this->transfer_rate($row['payment_fee_rate']);

        //查询今日代付总额
        $timearr = [
            strtotime(date('Y-m-d'). '00:00:00'),
            strtotime(date('Y-m-d'). '23:59:59'),
        ];
        $total = Db::name('pay_order')->where(['merchant_number'=>$row['merchant_number']])->where(['status'=>1])->where('create_time','between',[$timearr[0],$timearr[1]])->sum('money');
        $row['daily_collection_total'] = $total;

        $row['merchant_amount'] = sprintfnum($row['merchant_amount']);
        $row['merchant_payment_amount'] = sprintfnum($row['merchant_payment_amount']);
        $row['collection_limit'] = sprintfnum($row['collection_limit']);
        $row['daily_collection_total'] = sprintfnum($row['daily_collection_total']);
        $row['payment_limit'] = sprintfnum($row['payment_limit']);
        $this->view->assign("row", $row);
        
        //计算未结算资金
        $where = ['status'=>1,'is_billing'=>0,'merchant_number'=>$row['merchant_number']];
        $sum = Db::name('pay_order')->where($where)->sum('account_money');

        $sum = $sum ? sprintfnum($sum) : 0;
        $this->view->assign("sum", $sum);

        if($this->group_id != self::MERCHANT_GROUP){
            echo '商户才可以查看';
        }else{
            //商户组
            return $this->view->fetch();
        }
    }
    
}
