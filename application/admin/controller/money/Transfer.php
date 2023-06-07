<?php

namespace app\admin\controller\money;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 商户
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Transfer extends Backend
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
        $this->model = model('payment_change_record');

        if (!in_array($this->group_id , [self::MERCHANT_GROUP])) {
            $this->error('商户才可以查看');
        }
    }

    /**
     * 查看
     */
    public function index()
    {

        //商家代收钱包
        $merchant_amount = sprintfnum($this->merchant['merchant_amount']);
        //商家代付钱包
        $merchant_payment_amount = sprintfnum($this->merchant['merchant_payment_amount']);
        
        $amount = $merchant_amount + $merchant_payment_amount;

        $this->view->assign([
            'merchant_amount' => $merchant_amount, //代收钱包可用金额（代收已结算金额）
            'merchant_payment_amount' => $merchant_payment_amount, //可用金额（代付可用余额）
            'amount' => $amount, //总计
        ]);

        $this->assignconfig('merchant_amount', $merchant_amount);
        $this->assignconfig('merchant_payment_amount', $merchant_payment_amount);
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

            $model = $this->model;

            $timewhere = $merchantwhere =  [];
            $merchantwhere = [
                'merchant_id' => $this->merchant['id'],
            ];

            $typewhere = [
                'type' => 3,
            ];

            $field = 'a.*,b.merchant_name,b.merchant_number';

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
                ->where($typewhere)
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();
            // $typelist = $this->typeslect(true);

            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['bef_amount'] = sprintfnum($v['bef_amount']);
                $items[$k]['change_amount'] = sprintfnum($v['change_amount']);
                $items[$k]['aft_amount'] = sprintfnum($v['aft_amount']);

                // //获取类型type
                // $items[$k]['status'] = $typelist[$v['status']];
            }

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id == self::MERCHANT_GROUP){
                // $extend = $this->getExtendData($timewhere);
            }

            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        return json([]);
    }

    /**
     * 申请下发
     */
    public function transfer(){
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);

        if ($this->request->isAjax()) {
            $params = $this->request->post();

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
            // $remark = $params['remark'];

            $model = $this->model;
                                
            Db::startTrans();
            try {
                // 1.扣除当前可用代收余额，并加在代付余额
                $res1 = Db::name('merchant')
                    ->where('id',$this->merchant['id'])
                    ->update([
                        'merchant_amount'=>['dec', $params['amount']],
                        'merchant_payment_amount'=>['inc', $params['amount']],
                    ]);
                if(!$res1){
                    exception('转入失败');
                }

                //2.添加代收账变记录
                $adddata = [
                    'merchant_number' => $this->merchant['merchant_number'],
                    'type' => 3, //type = 3-转移
                    'bef_amount' => $this->merchant['merchant_amount'],
                    'change_amount' => -$params['amount'],
                    'aft_amount' => $this->merchant['merchant_amount'] - $params['amount'],
                    'status' => 1,
                    'create_time' => time()
                ];
                // dump($adddata);
                $res2 = Db::name('amount_change_record')->insertGetId($adddata);
                if(!$res2){
                    exception('添加账变记录失败');
                }

                //3.添加代付账变记录
                $adddata = [
                    'merchant_id' => $this->merchant['id'],
                    'type' => 3, //type = 3-转移
                    'bef_amount' => $this->merchant['merchant_payment_amount'],
                    'change_amount' => $params['amount'],
                    'aft_amount' => $this->merchant['merchant_payment_amount'] + $params['amount'],
                    'status' => 1,
                    'create_time' => time(),
                    'relation_id' => $res2
                ];
                // dump($adddata);
                $res3 = model('payment_change_record')->addrecord($adddata);
                if(!$res3){
                    exception('添加账变记录失败');
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            $this->success('转移成功');
        }
    }
}
