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
class Income extends Backend
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

        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = $this->request->get("filter", '');
            $type = $this->request->get("type", '');
            // dump($filter);exit;
            $op = $this->request->get("op", '', 'trim');

            $filter = (array)json_decode($filter, true);
            $op = (array)json_decode($op, true);

            $model = Db::name('amount_order_log');

            // dump($op);exit;
            //组装搜索
            $timewhere = $statuswhere = [];
            $field = 'a.*';

            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['a.merchant_number'=>$this->merchant['merchant_number']];
            }
            
            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                // $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);
                $timewhere = ['a.create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];

                // $filter['a.create_time'] = $filter['create_time'];
                unset($filter['create_time']);
            }

            if (isset($type)) {

                $statuswhere = ['a.type' => $type];
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
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;
            $items = $list->items();
            foreach ($items as $k => $v) {
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['create_time'] = datevtime($v['create_time']);
            }
            
            // dump($rate);
            // echo $this->model->getLastsql();exit;

            $extend = [];

            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        return $this->view->fetch();
    }
    
}
