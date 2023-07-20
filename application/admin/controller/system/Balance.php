<?php

namespace app\admin\controller\system;

use app\common\controller\Backend;
use think\Db;
use think\Validate;

/**
 * 系统余额
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于系统余额的管理
 */
class Balance extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;

    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
        $this->model = model('SystemAmountChangeRecord');

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

            $typelist = $this->typeslect(true);

            $timewhere = $merchant_where = [];
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

            $list = $this->model
                ->alias('a')
                ->where($merchant_where)
                ->where($timewhere)
                ->where($where)
                ->join('merchant b','a.merchant_number = b.merchant_number','LEFT')
                ->field('a.*,b.merchant_name')
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;
            if($list){
                foreach ($list as $k => $v) {
                    $list[$k]['create_time'] = datevtime($v['create_time']);
                    //获取类型type
                    $list[$k]['type'] = $typelist[$v['type']];
                }
            }

            //获取当前可用收益余额
            $extend = Db::name('config')->field('value')->where('name','system_aomout')->find();
            if($extend){
                $extend['value'] = sprintfnum($extend['value']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items(), "extend" => $extend);
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
                //先验证后台操作员自己的谷歌验证码是否正确
                if(!$this->checkValid($params['checksum'])){
                    $this->error('谷歌校验码错误',null,[]);
                }
                Db::startTrans();
                try {
                    unset($params['checksum']);
                    $result = $this->model->save($params);
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
                //先验证后台操作员自己的谷歌验证码是否正确
                if(!$this->checkValid($params['checksum'])){
                    $this->error('谷歌校验码错误',null,[]);
                }
                Db::startTrans();
                try {
                    unset($params['checksum']);
                    $result = $row->save($params);
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

    public function check(){
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $params = $this->request->post();
            // if(!$params['check_channel_id']){
            //     $this->error('请选择通道后再进行结算操作');
            // }
            if(!isset($params['type'])){
                $this->error('请选择操作类型再进行操作');
            }
            if(!isset($params['amount'])){
                $this->error('请输入操作金额');
            }

            if($params['amount'] == 0){
                $this->error('操作金额不能为0');
            }

            $amount = $params['amount'];

            //根据不同的类型来限制,如果是人工调账，那么正数负数都接受，如果是下U结算那只接受正数
            if($params['type'] == 1){
                if($amount < 0){
                    $this->error('下U结算那只接受正数');
                }
                $amount = -$amount;
            }
            //

            $system_aomout = Db::name('config')->field('value')->where('name','system_aomout')->find(); //先查找system_aomout前值

            $res1 = Db::name('config')
                ->where('name','system_aomout')
                ->update([
                    'value'=>['inc', $amount]
                ]);

            if(!$res1){
                $this->error('操作系统余额失败');
            }

            //5.添加到系统营收记录
            $adddata = [
                'merchant_number' => '-',
                'orderno' => '-',
                'type' => $params['type'],
                'bef_amount' => $system_aomout['value'],
                'change_amount' => $amount,
                'aft_amount' => $system_aomout['value'] + $amount,
                'status' => 1,
                'create_time' => time()
            ];
            // dump($adddata);exit;
            $res2 = Db::name('system_amount_change_record')->insert($adddata);
            if(!$res2){
                $this->error('添加账变记录失败');
            }

            $this->success('结算成功');
        }else{
            $this->error('Fail Request');
        }
    }

    /**
     * 获取所有账变类型
     */
    public function typeslect($is_array = false){
        $result = [
            1 => '下U结算',
            2 => '代收结算',
            3 => '人工调账',
        ];
        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }
}
