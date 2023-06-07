<?php

namespace app\admin\controller\record;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 代收余额账变记录
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示记录
 */
class Payment extends Backend
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

            $groupwhere = [];
            $field = 'a.*,b.merchant_name,b.merchant_number';
            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['b.merchant_number'=>$this->merchant['merchant_number']];
                $field = 'a.*,b.merchant_name,b.merchant_number';
            }

            // dump($op);exit;
            //组装搜索
            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);

                unset($filter['create_time']);
                unset($op['create_time']);
            }

            if (isset($filter['status'])) {

                $filter['a.status'] = $filter['status'];
                unset($filter['status']);
            }

            if (isset($filter['merchant_number'])) {

                $filter['b.merchant_number'] = $filter['merchant_number'];
                unset($filter['merchant_number']);
            }


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->alias('a')
                ->join('merchant b','a.merchant_id = b.id','LEFT')
                ->where($groupwhere)
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();
            $typelist = $model->typeslect(true);

            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);
                if($v['orderno'] == ''){
                    $items[$k]['orderno'] = '无';
                }

                //获取类型type
                $items[$k]['type'] = $typelist[$v['type']];
            }

            $result = array("total" => $list->total(), "rows" => $items);
            return json($result);
        }

        if($this->group_id != self::MERCHANT_GROUP){
            //管理员
            return $this->view->fetch();
        }else{
            //商户组
            return $this->view->fetch('record/payment/merchant');
        }
    }

    /**
     * 获取所有账变类型
     */
    public function typeslect(){
        return $this->model->typeslect();
    }
    
    
}
