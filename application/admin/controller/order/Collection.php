<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;
use app\admin\library\PayGIntegration;

/**
 * 代收订单
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示代收订单
 */
class Collection extends Backend
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
    }

    /**
     * 查看
     */
    public function index()
    {

        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = $this->request->get("filter", '');
            $op = $this->request->get("op", '', 'trim');

            $filter = (array)json_decode($filter, true);
            $op = (array)json_decode($op, true);

            $model = Db::name('pay_order');

            // dump($op);exit;
            //组装搜索
            $timewhere = $statuswhere = $groupwhere = $merchant_where = [];
            $field = 'a.*,b.channel_name,c.merchant_name';
            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['a.merchant_number'=>$this->merchant['merchant_number']];
                $field = 'a.id,orderno,eshopno,tn,out_trade_no,a.status,a.merchant_number,money,rate_money,account_money,a.billing_around,billing_time,is_billing,notify_status,a.create_time,a.update_time,a.callback_time,b.channel_name,channel_id';
            }
            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                // $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);
                $timewhere = ['a.create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];

                // $filter['a.create_time'] = $filter['create_time'];
                unset($filter['create_time']);
            }

            if (isset($filter['callback_time'])) {
                $timearr = explode(' - ',$filter['callback_time']);
                $timewhere['a.callback_time'] = ['between',[strtotime($timearr[0]),strtotime($timearr[1])]];

                unset($filter['callback_time']);
            }


            if (isset($filter['status'])) {

                // $filter['a.status'] = $filter['status'];
                $statuswhere = ['a.status' => $filter['status']];
                unset($filter['status']);
            }

            if (isset($filter['merchant_number'])) {

                $merchant_where = ['a.merchant_number' => $filter['merchant_number']];
                unset($filter['merchant_number']);
            }


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->alias('a')
                ->where($merchant_where)
                ->where($groupwhere)
                ->where($timewhere)
                ->where($statuswhere)
                ->where($where)
                ->join('channel_list b','a.channel_id = b.id','LEFT')
                ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;

            $typelist = $this->model->typelist();
            $notifylist = $this->model->notifylist();
            
            $items = $list->items();
            foreach ($items as $k => $v) {
                $items[$k]['billing_time'] = datevtime($v['billing_time']);
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['callback_time'] = datevtime($v['callback_time']);

                $items[$k]['money'] = sprintfnum($v['money']);
                $items[$k]['account_money'] = sprintfnum($v['account_money']);
                $items[$k]['rate_money'] = sprintfnum($v['rate_money']);
                //获取对应的商品
                $goodlist =  Db::name('pay_product')
                                ->alias('a')
                                ->field('a.*,b.name')
                                ->join('product b','a.good_id = b.id','LEFT')
                                ->where(['orderid' => $v['id']])
                                ->select();

                $items[$k]['goodlist'] = $goodlist??[];

                $items[$k]['status_type'] = $typelist[$v['status']]??'未知';
                $items[$k]['notify_status_type'] = $notifylist[$v['notify_status']]??'未知';
            }

            
            // dump($rate);
            // echo $this->model->getLastsql();exit;

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id != self::MERCHANT_GROUP){
                $extend = $this->getExtendData($timewhere,$statuswhere,$where,$merchant_where);
            }
            
            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        if($this->group_id != self::MERCHANT_GROUP){
            return $this->view->fetch();
        }else{
            //商户组
            return $this->view->fetch('order/collection/merchant');
        }
    }


    /**
     * 结算
     */
    public function check()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $params = $this->request->post();
            // if(!$params['check_channel_id']){
            //     $this->error('请选择通道后再进行结算操作');
            // }
            if(!$params['check_merchant_id']){
                $this->error('请选择商户后再进行结算操作');
            }

            $check_create_time = $params['check_create_time'];
            if(!$params['check_create_time']){
                $this->error('请选择时间进行结算');
            }

            // 先查询channel
            // $channel = Db::name('channel_list')->where('id',$params['check_channel_id'])->find();
            // if(!$channel){
            //     $this->error('通道不存在');
            // }

            //查询商户
            $merchant = Db::name('merchant')->where('id',$params['check_merchant_id'])->find();
            if(!$merchant){
                $this->error('商户不存在');
            }

            // if($channel['channel_type'] != 'Payment'){
            //     $this->error('该通道结算功能暂未开通');
            // }

            $model = $this->model;

            //组装搜索
            if (isset($params['check_create_time'])) {
                $timearr = explode(' - ',$params['check_create_time']);
                $timewhere = ['create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];
            }

            //未结算、已成功的订单
            $where = [
                'is_billing' => 0,
                'status' => 1,
                'merchant_number' => $merchant['merchant_number']
            ];

            //需要处理的订单数据
            $record = $model->where($where)
                        ->where($timewhere)
                        ->select();

            // dump($record);exit;
            if($record){
                foreach ($record as $k => $v) {
                    //循环结算
                    $res = $model->check_pay_order($v);
                    // dump($res);exit;
                    // if(!$res) continue;
                }
            }else{
                $this->error('未查询到可结算订单');
            }

            $this->success('结算成功');
        }
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    unset($params['checksum']);
                    //默认为代收
                    $params['type'] = $this->type;
                    $result = $this->model->validate('Collection.add')->save($params);
                    if ($result === false) {
                        exception($this->model->getError());
                    }
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->alias('a')
                    ->field('a.*,b.merchant_key,d.account_name,d.account_number,d.ifsc')
                    ->join('merchant b','a.merchant_number = b.merchant_number','LEFT')
                    ->join('otc_list d','a.otc_id = d.id','LEFT')
                    ->where(['a.id' => $ids])
                    ->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row['create_time'] = datetime($row['create_time']);

        //查询paytm订单
        $channel = Db::name('channel_list')->where(array('id'=>$row['channel_id']))->find();

        $status = $row['status'];
        if(in_array($channel['channel_type'],['Payment','payg','cashfree','Kirin','fastpay','bzpay','dspay','wowpay'])){
            //查询三方状态
            $status = $this->model->get_order_detail($row,$channel);
            $row['status'] = $status;
        }
        

        $typelist = $this->model->typelist();
        $notifylist = $this->model->notifylist();

        $row['status_type'] = $typelist[$row['status']]??'未知';
        $row['notify_status_type'] = $notifylist[$row['notify_status']]??'未知';

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 商品详情
     */
    public function orderdetail($ids){
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row['money'] = sprintfnum($row['money']);
        //获取商品
        $res = Db::name('pay_product')
                ->alias('a')
                ->field('a.*,b.name')
                ->join('product b','a.good_id = b.id','LEFT')
                ->where(['orderid' => $ids])
                ->select();
        // echo Db::name('pay_product')->getLastsql();exit;
        if($res){
            foreach ($res as $k => $v) {
                $res[$k]['price'] = sprintfnum($v['price']);
            }
        }
        // dump($res);exit;
        $this->view->assign("row", $row);
        $this->view->assign("list", $res);
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    unset($params['checksum']);
                    $result = $row->validate('Collection.edit')->save($params);
                    if ($result === false) {
                        exception($row->getError());
                    }

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            // 查询，直接删除
            $channelList = $this->model->where('id', 'in', $ids)->select();
            if ($channelList) {
                $deleteIds = [];
                foreach ($channelList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                if ($deleteIds) {
                    Db::startTrans();
                    try {
                        $this->model->destroy($deleteIds);
                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                }
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('You have no permission'));
    }

    /**
     * 手动修改状态
     */
    public function updatestatus($id)
    {
        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }
        $id = $id ? $id : $this->request->post("id");
        $row = $this->model->get(['id' => $id]);
        
        $status = $this->request->post("status");
        // dump($id);exit;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $url = $row->notify_url;
        
        // if($row->status == 0){
        //     $this->error('上游处理中，不可回调');
        // }
        $row->status = $status;
        $result = $row->save();

        // echo $row->getlastsql();exit;
        $this->success('状态修改成功');
    }

    /**
     * 手动给下游回调
     */
    public function callback($id = "")
    {
        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }
        $id = $id ? $id : $this->request->post("id");
        $row = $this->model->get(['id' => $id]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $url = $row->notify_url;
        if(!$url){
            $this->error('回调地址不存在');
        }
        if($row->notify_status == 1){
            $this->error('通知已经完成');
        }
        // if($row->status == 0){
        //     $this->error('上游处理中，不可回调');
        // }

        //获取商户密钥回调
        $merchant = Db::name('merchant')->where(['merchant_number'=>$row->merchant_number])->find();
        if(!$merchant){
            $this->error('商户不存在');
        }
        $row->merchant_key = $merchant['merchant_key'];
        $data = $this->model->getCondItem($row,$row['status']);
        Log::record('notify:通知参数：'.json_encode($data),'notice');

        // echo json_encode($data);exit;

        //记录回调时间，回调次数
        try {
            $res = Http::post($url, $data, $options = []);
            // dump($res);exit;
            Log::record('notify:通知回答：'.json_encode($res),'notice');
            if(!$res){
                $this->model->update_pay_order($id,2);
                exception('通知失败');
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        if($res){
            if($res == 'success'){
                $this->model->update_pay_order($id,1);
            }else{
                $this->model->update_pay_order($id,2);
                $this->error('通知失败');
            }
        }else{
            $this->model->update_pay_order($id,2);
            $this->error('通知失败');
        }

        $this->success('通知成功');
    }

    /**
     * 获取所有代收通道
     */
    public function colselect()
    {
        $result = Db::name('channel_list')->where(['type'=>1])->select();
        $data = [];
        foreach ($result as $k => $v) {
            $data[$v['id']] = $v['channel_name'];
        }
        // dump($data);exit;
        return json($data);
    }

    /**
     * 获取所有在用商户
     */
    public function merchantlist()
    {
        $result = Db::name('merchant')
                    // ->where(['status'=>1])
                    ->select();
        $data = [];
        foreach ($result as $k => $v) {
            $data[$v['merchant_number']] = $v['merchant_name'];
        }
        // dump($data);exit;
        return json($data);
    }
    /**
     * 类型json
     */
    public function typeList(){
        $res = $this->model->typelist();
        // dump($res);exit;
        return json($res);
    }

    /**
     * 类型json
     */
    public function notifyList(){
        $res = $this->model->notifylist();
        return json($res);
    }

    /**
     * 获取所有代收通道
     */
    public function channelselect()
    {
        $channel = Db::name('channel_list')->field('id,channel_name')->where(['type'=>1])->order('id desc')->select();
        $merchant = Db::name('merchant')->field('id,merchant_name,merchant_number')->where(['status'=>1])->order('id desc')->select();

        $result = [
            'channel' => $channel,
            'merchant' => $merchant
        ];
        return json($result);
    }

     /**
     * 获取所有代收通道
     */
    protected function getExtendData($timewhere,$statuswhere,$where,$merchant_where)
    {
        $model = $this->model;
        $success_status = 1;
        //交易金额
        $money = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where($where)
                ->sum('money');

        //交易笔数
        $total = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where($where)
                ->count();

        //成功笔数
        $success_total = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where($where)
                ->where('status',$success_status)
                ->count();

        //成功金额
        $price = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where($where)
                ->where('status',$success_status)
                ->sum('money');

        //商户手续费
        $tax = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where('status',$success_status)
                ->where($where)
                ->sum('rate_money');

        //三方手续费
        $three_tax = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where('status',$success_status)
                ->where($where)
                ->sum('rate_t_money');

        //成功率
        $count = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                ->where($merchant_where)
                ->where('status',$success_status)
                ->where($where)
                ->count();

        if($total != 0){
            $rate = sprintf("%.2f", ($count / $total)*100 ).'%';
        }else{
            $rate = '0%';
        }

        $extend = [
            'money' => sprintfnum($money),
            'total' => $total,
            'price' => sprintfnum($price),
            'tax' => sprintfnum($tax),
            'rate' => $rate,
            'three_tax' => sprintfnum($three_tax),
            'success_total' => $success_total
        ];

        return $extend;
    }

    
}
