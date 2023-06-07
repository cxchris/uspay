<?php

namespace app\admin\controller;

use app\common\controller\Backend;


/**
 * 结算报表
 *
 * @icon   fa fa-list
 * @remark 结算报表
 */
class Report extends Backend
{

    /**
     * @var \app\common\model\Category
     */
    protected $model = null;
    protected $categorylist = [];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('pay_order');
        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }
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

            $timewhere = [];

            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                // $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);
                $timewhere = ['a.create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];

                // $filter['a.create_time'] = $filter['create_time'];
                unset($filter['create_time']);
            }

            if (isset($filter['merchant_number'])) {

                $merchant_where = ['a.merchant_number' => $filter['merchant_number']];
                unset($filter['merchant_number']);
            }


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();


            //新列表：商户，交易金额，代收手续费，到账金额

            $list = $this->model
                ->alias('a')
                ->where(['is_billing'=>1])
                ->where($where)
                ->where($timewhere)
                ->join('yd_merchant c','a.merchant_number = c.merchant_number','LEFT')
                ->field('c.merchant_name,c.merchant_number,sum(money) as sum_money,sum(rate_money) as sum_rate_money,sum(account_money) as sum_account_money ')
                ->order('a.create_time desc')
                ->group('a.merchant_number')
                ->paginate($limit);

            $items = $list->items();
            $result = array("total" => $list->total(), "rows" => $items);
            return json($result);
        }

        return $this->view->fetch();
    }

    //结算详情
    public function detail(){
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

            $merchant_where = [];

            if (isset($filter['merchant_number'])) {

                $merchant_where = ['c.merchant_number' => $filter['merchant_number']];
                unset($filter['merchant_number']);
            }

            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();


            $list = $this->model
                ->alias('a')
                ->where(['is_billing'=>1])
                ->where($where)
                ->where($merchant_where)
                ->join('channel_list b','a.channel_id = b.id','LEFT')
                ->join('yd_merchant c','a.merchant_number = c.merchant_number','LEFT')
                ->field('a.*,b.channel_name,c.merchant_name')
                ->order($sort, $order)
                ->paginate($limit);

            $items = $list->items();
            foreach ($items as $k => $v) {
                $items[$k]['billing_time'] = datevtime($v['billing_time']);
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['callback_time'] = datevtime($v['callback_time']);

                if(!$v['collection_fee_rate']){
                    $collection_fee_rate = explode('|',$v['collection_fee_rate']);
                    $rate = $fee = 0;
                }else{
                    $collection_fee_rate = explode('|',$v['collection_fee_rate']);
                    list($rate, $fee) = $collection_fee_rate;
                }

                $items[$k]['collection_fee_rate'] = $rate.'% + '.$fee;
            }
            $result = array("total" => $list->total(), "rows" => $items);
            return json($result);
        }
        return $this->view->fetch();
    }
}
