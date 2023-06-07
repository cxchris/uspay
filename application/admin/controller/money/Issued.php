<?php

namespace app\admin\controller\money;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use fast\Random;
use think\Log;
use app\admin\library\Paytm;

/**
 * 商户
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Issued extends Backend
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

        if (!in_array($this->group_id , [self::MERCHANT_GROUP]) && !in_array(request()->action(),['detail'])) {
            $this->error('商户才可以查看');
        }
    }

    /**
     * 查看
     */
    public function index()
    {

        //商家代收钱包
        $merchant_amount = $this->merchant['merchant_amount'];
        $where = [
            'merchant_number' => $this->merchant['merchant_number'],
            'is_billing' => 0,
            'status' => 1
        ];
        $check_amount = $this->model->where($where)->sum('account_money');
        $total_merchant_amount = $merchant_amount + $check_amount;

        //商家代付钱包
        $merchant_payment_amount = $this->merchant['merchant_payment_amount'];
        $check_pay_amount = 0;
        $total_payment_merchant_amount = $merchant_payment_amount + $check_pay_amount;

        $this->view->assign([
            'total_merchant_amount' => sprintfnum($total_merchant_amount), //总金额（可用金额 + 未结算金额）
            'merchant_amount' => sprintfnum($merchant_amount), //代收钱包可用金额（代收已结算金额）
            'check_amount' => sprintfnum($check_amount), //未结算金额（代收未结算金额）

            'total_payment_merchant_amount' => sprintfnum($total_payment_merchant_amount), //总金额（可用金额 + 预扣金额）
            'merchant_payment_amount' => sprintfnum($merchant_payment_amount), //可用金额（代付可用余额）
            'check_pay_amount' => sprintfnum($check_pay_amount), //冻结金额（代付冻结金额）


        ]);

        $this->assignconfig('merchant_amount', $merchant_amount);
        return $this->view->fetch();
    }

    /*
     * 列表
     */
    public function list(){
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = $this->request->get("filter", '');
            // dump($filter);exit;
            $op = $this->request->get("op", '', 'trim');

            $filter = (array)json_decode($filter, true);
            $op = (array)json_decode($op, true);

            $model = Db::name('issued_record');

            $timewhere = $merchantwhere =  [];
            $merchantwhere = [
                'merchant_id' => $this->merchant['id']
            ];
            $field = 'a.*,b.merchant_name';

            // dump($op);exit;
            //组装搜索
            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                // $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);
                $timewhere = ['a.create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];

                unset($filter['create_time']);
                unset($op['create_time']);
            }


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->alias('a')
                ->join('merchant b','a.merchant_id = b.id','LEFT')
                ->where($timewhere)
                ->where($merchantwhere)
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();
            $typelist = $this->typeslect(true);

            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['bef_money'] = sprintfnum($v['bef_money']);
                $items[$k]['money'] = sprintfnum($v['money']);
                $items[$k]['aft_money'] = sprintfnum($v['aft_money']);

                // //获取类型type
                $items[$k]['status'] = $typelist[$v['status']];
            }

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id == self::MERCHANT_GROUP){
                $extend = $this->getExtendData($timewhere);
            }

            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        return json([]);
    }

    /**
     * 申请下发
     */
    public function apply(){
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $params = $this->request->post();

            // dump(Random::getrandSn());exit;
            if(!$params['bank_id']){
                $this->error('请选择银行账户后再进行下发操作');
            }
            $checknum = $params['checknum'];
            if(!$params['checknum']){
                $this->error('请输入google验证码');
            }

            //验证google验证码
            if(!$this->checkValid($checknum)){
                $this->error('谷歌校验码错误');
            }

            //查询银行卡
            $bank = Db::name('merchant_bank')->where(['id'=>$params['bank_id']])->find();
            if(!$bank){
                $this->error('银行卡信息不存在');
            }

            if(!$params['amount']){
                $this->error('请输入下发金额');
            }

            //判断金额
            if($params['amount'] < 0){
                $this->error('下发金额不可小于0');
            }

            if($params['amount'] > $this->merchant['merchant_amount']){
                $this->error('不可超过当前可用余额');
            }
            $remark = $params['remark'];


            $model = Db::name('issued_record');
                                
            Db::startTrans();
            try {
                // 1.扣除当前可用余额
                 $res1 = Db::name('merchant')
                    ->where('id',$this->merchant['id'])
                    ->update([
                        'merchant_amount'=>['dec', $params['amount']]
                    ]);
                if(!$res1){
                    exception('扣除商户余额失败');
                }

                //2.添加下发记录
                $record = [
                    'merchant_id' => $this->merchant['id'],
                    'orderno' => Random::getrandSn(),
                    'bank_id' => $params['bank_id'],
                    'bef_money' => $this->merchant['merchant_amount'],
                    'money' => $params['amount'],
                    'aft_money' => $this->merchant['merchant_amount'] - $params['amount'],
                    'create_time' => time(),
                    'update_time' => time(),
                    'remark' => $remark,
                    'account' => $bank['account'],
                    'bankname' => $bank['bankname'],
                    'banknumber' => $bank['banknumber'],
                    'ifsccode' => $bank['ifsccode'],
                ];
                $res2 = Db::name('issued_record')->insert($record);
                if(!$res2){
                    exception('添加下发记录失败');
                }

                //3.添加账变记录
                $adddata = [
                    'merchant_number' => $this->merchant['merchant_number'],
                    'orderno' => $record['orderno'],
                    'type' => 4, //type = 4-商户下发
                    'bef_amount' => $record['bef_money'],
                    'change_amount' => -$record['money'],
                    'aft_amount' => $record['aft_money'],
                    'status' => 1,
                    'create_time' => time()
                ];
                // dump($adddata);
                $res3 = Db::name('amount_change_record')->insert($adddata);
                if(!$res3){
                    exception('添加账变记录失败');
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            $this->success('下发申请成功，请等待管理员审核');
        }
    }
    

    /**
     * 获取订单对应的银行列表
     */
    public function banklist(){
        $result = Db::name('merchant_bank')->field('id,account,banknumber,is_default')->where(['status'=>1,'merchant_id'=>$this->merchant['id']])->order('id desc')->select();

        return json($result);
    }


    /**
     * 获取订单对应的银行列表
     */
    public function detail($ids = null){
        $row = Db::name('issued_record')
                ->where(['id' => $ids])
                ->find();

                // dump($row);exit;
        if (!$row) {
            $this->error(__('No Results were found'));
        }


        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 获取所有账变类型
     */
    public function typeslect($is_array = false){
        $result = [
            0 => '未处理',
            1 => '成功',
            2 => '拒绝',
        ];
        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }

    /*
    * 公共方法，获取issued_record
    */
    protected function getissueddata($status = false, $timewhere = []){
        $field = 'COUNT(*) AS counts, sum(money) AS nums';

        if($this->group_id != self::MERCHANT_GROUP){
            $where = [];
        }else{
            $where['merchant_id'] = $this->merchant['id'];
        }

        if($status !== false){
            $where['status'] = $status;
        }

        $res = Db("issued_record")
            ->alias('a')
            ->where($where)
            ->where($timewhere)
            ->field($field)
            ->select();
            // echo Db("pay_order")->getlastsql();exit;

        return $res;
    }

    /**
     * 获取数据
     */
    protected function getExtendData($timewhere)
    {
        //申请下发数据
        $data = $this->getissueddata(false,$timewhere);
        // dump($data);exit;
        $issusd_total = $data[0]['counts']??0;
        $issusd_money = $data[0]['nums']??0;

        $data = $this->getissueddata(1,$timewhere);
        $issusd_success_total = $data[0]['counts']??0;
        $issusd_success_money = $data[0]['nums']??0;

        $data = $this->getissueddata(2,$timewhere);
        $issusd_fail_total = $data[0]['counts']??0;
        $issusd_fail_money = $data[0]['nums']??0;

        $extend = [
            'issusd_total' => $issusd_total,
            'issusd_money' => sprintfnum($issusd_money),
            
            'issusd_success_total' => $issusd_success_total,
            'issusd_success_money' => sprintfnum($issusd_success_money),

            'issusd_fail_total' => $issusd_fail_total,
            'issusd_fail_money' => sprintfnum($issusd_fail_money),
        ];

        return $extend;
    }
    
}
