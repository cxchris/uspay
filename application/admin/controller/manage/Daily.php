<?php

namespace app\admin\controller\manage;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 报表
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户报表
 */
class Daily extends Backend
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

        // if (!in_array($this->group_id , [self::MERCHANT_GROUP])) {
        //     $this->error('商户才可以查看');
        // }
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

            $model = Db::name('merchant_daily');

            $groupwhere = [];
            $field = 'a.*,b.merchant_name,b.merchant_number';
            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['a.merchant_id'=>$this->merchant['id']];
                $field = 'a.*,b.merchant_name,b.merchant_number';
            }else{
                $groupwhere = ['a.merchant_id'=>0];
            }

            // dump($op);exit;
            //组装搜索
            if (isset($filter['starttime'])) {

                $model->where('a.datetime','>=',$filter['starttime']);

                unset($filter['starttime']);
                unset($op['starttime']);
            }

            if (isset($filter['endtime'])) {

                $model->where('a.datetime','<=',$filter['endtime']);

                unset($filter['endtime']);
                unset($op['endtime']);
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
                ->order('datetime desc')
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();

            foreach ($items as $k => $v) {
                $items[$k]['amount'] = sprintfnum($v['amount']);
                $items[$k]['amount_settlement'] = sprintfnum($v['amount_settlement']);
                $items[$k]['amount_tax'] = sprintfnum($v['amount_tax']);
                $items[$k]['payment'] = sprintfnum($v['payment']);
                $items[$k]['payment_tax'] = sprintfnum($v['payment_tax']);
            }

            $result = array("total" => $list->total(), "rows" => $items);
            return json($result);
        }

        return $this->view->fetch();
    }
}
