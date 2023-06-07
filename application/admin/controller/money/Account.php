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
class Account extends Backend
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
        $this->model = model('merchant_bank');

        // if (!in_array($this->group_id , [self::MERCHANT_GROUP])) {
        //     $this->error('商户才可以查看');
        // }
    }

    /**
     * 查看
     */
    public function index()
    {

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

            $model = Db::name('merchant_bank');

            $timewhere = $merchantwhere = [];
            if(isset($this->merchant['id'])){
                $merchantwhere = [
                    'merchant_id' => $this->merchant['id']
                ];
            }

            if (isset($filter['merchant_number'])) {

                $merchantwhere = ['b.merchant_number' => $filter['merchant_number']];
                unset($filter['merchant_number']);
            }
            
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
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();

            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);

            }

            //查询 交易金额/交易笔数 等
            $extend = [];

            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        return $this->view->fetch();
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
                    if(!isset($params['is_default'])){
                        $params['is_default'] = 0;
                    }else{
                        //修改其他is_default为0
                        $res1 = Db::name('merchant_bank')->where(['merchant_id'=>$this->merchant['id']])->update(['is_default'=>0]);
                        if ($res1 === false) {
                            exception($row->getError());
                        }
                    }

                    $params['merchant_id'] = $this->merchant['id'];
                    $params['create_time'] = time();
                    $params['status'] = 1;
                    $result = $this->model->validate('MerchantBank.add')->save($params);
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
                    if(!isset($params['is_default'])){
                        $params['is_default'] = 0;
                    }else{
                        //修改其他is_default为0
                        $res1 = Db::name('merchant_bank')->where(['merchant_id'=>$row->merchant_id])->update(['is_default'=>0]);
                        if ($res1 === false) {
                            exception($row->getError());
                        }
                    }
                    $result = $row->validate('MerchantBank.edit')->save($params);
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
    
}
