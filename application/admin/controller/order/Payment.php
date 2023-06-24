<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 代付订单
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示代付
 */
class Payment extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;

    protected $searchFields = 'id,filename,url';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('payment_order');
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
            // dump($filter);exit;
            $op = $this->request->get("op", '', 'trim');

            $filter = (array)json_decode($filter, true);
            $op = (array)json_decode($op, true);

            $model = $this->model;

            // dump($op);exit;
            //组装搜索
            $timewhere = $statuswhere = $groupwhere = [];
            $field = 'a.*,b.channel_name,c.merchant_number,c.merchant_name,d.account_name';
            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['a.merchant_id'=>$this->merchant['id']];
                $field = 'a.id,orderno,tn,out_trade_no,a.status,c.merchant_number,c.merchant_name,money,rate_money,reduce_money,notify_status,a.create_time,a.update_time,a.callback_time,b.channel_name,a.channel_id';
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


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->alias('a')
                ->where($groupwhere)
                ->where($timewhere)
                ->where($statuswhere)
                ->where($where)
                ->join('channel_list b','a.channel_id = b.id','LEFT')
                ->join('merchant c','a.merchant_id = c.id','LEFT')
                ->join('otc_list d','a.channel_id = d.id','LEFT')
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;

            $typelist = $this->model->typelist();
            $notifylist = $this->model->notifylist();

            $items = $list->items();
            foreach ($items as $k => $v) {
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['callback_time'] = datevtime($v['callback_time']);

                $items[$k]['money'] = sprintfnum($v['money']);
                $items[$k]['reduce_money'] = sprintfnum($v['reduce_money']);
                $items[$k]['rate_money'] = sprintfnum($v['rate_money']);

                $items[$k]['status_type'] = $typelist[$v['status']]??'未知';
                $items[$k]['notify_status_type'] = $notifylist[$v['notify_status']]??'未知';
            }

            
            // dump($rate);
            // echo $this->model->getLastsql();exit;

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id != self::MERCHANT_GROUP){
                $extend = $this->getExtendData($timewhere,$statuswhere,$where);
            }
            
            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        if($this->group_id != self::MERCHANT_GROUP){
            return $this->view->fetch();
        }else{
            //商户组
            return $this->view->fetch('order/payment/merchant');
        }
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
        $merchant = Db::name('merchant')->where(['id'=>$row->merchant_id])->find();
        if(!$merchant){
            $this->error('商户不存在');
        }

        
        $row->merchant_number = $merchant['merchant_number'];
        $row->merchant_key = $merchant['merchant_key'];
        $data = $this->model->getCondItem($row,$row['status']);

        // dump($data);exit;

        //记录回调时间，回调次数
        try {
            $res = Http::post($url, $data, $options = []);
            Log::record('apply notify:通知参数'.json_encode($data),'notice');
            Log::record('apply notify:通知回答：'.json_encode($res),'notice');
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
        
        if($row->status == 3 && $status == 2){
            $this->error('订单已驳回，不可修改为成功');
        }

        if($row->status == 3 && $status == 3){
            $this->error('订单已驳回');
        }

        if($status == 3){
            // 1.回退
            $res = $this->model->rollback_order($row);
            if(!$res){
                $this->error('驳回失败');
            }
        }

        $row->status = $status;
        $result = $row->save();

        //直接通知
        if($url){
            //4,交易成功，则回调给下游
            $this->model->notifyShop($row->orderno, $status);
        }

        // echo $row->getlastsql();exit;
        $this->success('状态修改成功');
    }

    /**
     * 驳回订单
     */
    public function reject($id = ""){
        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }
        $id = $id ? $id : $this->request->post("id");
        $row = $this->model->get(['id' => $id]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if($row->status == 2){
            // $this->error('成功订单不可驳回');
        }

        if($row->status == 3){
            $this->error('失败订单不可驳回');
        }

        if($row->status == -1){
            $this->error('失败订单不可驳回');
        }

        // 1.回退
        $res = $this->model->rollback_order($row);
        if(!$res){
            $this->success('驳回失败');
        }

        // 2.修改订单状态
        $data = [];
        $data['status'] = 3;
        $this->model->where(['id'=>$id])->update($data);

        $this->success('驳回成功');
    }

    /**
     * 详情
     */
    public function detail($ids = null)
    {
        $row = $this->model
                ->alias('a')
                ->field('a.*,c.merchant_number,c.merchant_key,b.channel_type,b.channel_sign,b.channel_safe_url,b.channel_key')
                ->join('channel_list b','a.channel_id = b.id','LEFT')
                ->join('merchant c','a.merchant_id = c.id','LEFT')
                ->where(['a.id' => $ids])
                ->find();

        if (!$row) {
            $this->error(__('No Results were found'));
        }

        // $row = $row->toArray();
        $row['create_time'] = datetime($row['create_time']);
        $row['callback_time'] = $row['callback_time']?datetime($row['callback_time']):'无';

        //查询channel
        // $channel = Db::name('channel_list')->where(array('id'=>$row['channel_id']))->find();

        $status = $row['status'];
        // dump($status);exit;
        if(in_array($row['channel_type'],['payg','Kirin','fastpay','bzpay','dspay'])){
            //查询三方状态
            $status = $this->model->get_order_detail($row);
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
     * 获取所有代收通道
     */
    public function colselect()
    {
        $result = Db::name('channel_list')->where(['type'=>2])->select();
        $data = [];
        foreach ($result as $k => $v) {
            $data[$v['id']] = $v['channel_name'];
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
     * 获取所有商家列表
     */
    public function merchantList()
    {
        $result = Db::name('merchant')
                    // ->where(['status'=>1])
                    ->order('id desc')->select();
        // echo Db::name('merchant')->getLastsql();exit;
        $data = [];
        foreach ($result as $k => $v) {
            $data[$v['id']] = $v['merchant_name'];
        }
        // dump($data);exit;
        return json($data);
    }

     /**
     * 获取所有代付通道
     */
    protected function getExtendData($timewhere,$statuswhere,$where)
    {
        $model = $this->model;
        $success_status = 2;
        //交易金额
        $money = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                // ->where($merchant_where)
                ->where($where)
                ->sum('money');

        //交易笔数
        $total = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                // ->where($merchant_where)
                ->where($where)
                ->count();

        //成功金额
        $price = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                // ->where($merchant_where)
                ->where($where)
                ->where('status',$success_status)
                ->sum('money');

        //交易手续费
        $tax = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                // ->where($merchant_where)
                ->where('status',$success_status)
                ->where($where)
                ->sum('rate_money');

        //成功率
        $count = $model
                ->alias('a')
                ->where($timewhere)
                ->where($statuswhere)
                // ->where($merchant_where)
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
        ];

        return $extend;
    }
}
