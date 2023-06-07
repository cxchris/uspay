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
        $this->model = model('amount_change_record');
        
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
            $field = 'a.*,b.merchant_name';
            if($this->group_id == self::MERCHANT_GROUP){
                //如果是商户，加上订单搜索条件
                $groupwhere = ['a.merchant_number'=>$this->merchant['merchant_number']];
                $field = 'a.*,b.merchant_name';
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

                $filter['a.merchant_number'] = $filter['merchant_number'];
                unset($filter['merchant_number']);
            }


            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $model
                ->alias('a')
                ->join('merchant b','a.merchant_number = b.merchant_number','LEFT')
                ->where($groupwhere)
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();
            $typelist = $this->typeslect(true);

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
            return $this->view->fetch();
        }else{
            //商户组
            return $this->view->fetch('record/collection/merchant');
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
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row = $row->toArray();
        $row['create_time'] = datetime($row['create_time']);

        if($row['status'] == 0){
            $sta = '进行中';
        }else if($row['status'] == 1){
            $sta = '已支付';
        }else if($row['status'] == 2){
            $sta = '支付失败';
        }else if($row['status'] == -1){
            $sta = '请求失败';
        }else{
            $sta = '未知';
        }

        if($row['notify_status'] == 0){
            $notify_sta = '未通知';
        }else if($row['notify_status'] == 1){
            $notify_sta = '通知成功';
        }else if($row['notify_status'] == 2){
            $notify_sta = '通知失败';
        }else{
            $notify_sta = '未知';
        }

        $row['status_type'] = $sta;
        $row['notify_status_type'] = $notify_sta;
        $this->view->assign("row", $row);
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
     * 获取所有账变类型
     */
    public function typeslect($is_array = false){
        $result = [
            1 => '人工调账',
            2 => '代收结算',
            3 => '转入代付记录',
            4 => '商户下发',
            5 => '商户下发拒绝回滚'
        ];
        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }
    
}
