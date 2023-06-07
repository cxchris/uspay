<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use fast\Random;
use think\Log;
use app\admin\library\Paytm;

/**
 * 商户
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Issued extends Backend
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


        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }
    }

    /**
     * 查看
     */
    public function index()
    {
        
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

            $model = Db::name('issued_record');

            $timewhere = [];
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
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();exit;
            $items = $list->items();
            $typelist = $this->typeslect(true);

            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);
                $items[$k]['update_time'] = datevtime($v['update_time']);
                $items[$k]['bef_money'] = sprintfnum($v['bef_money']);
                $items[$k]['money'] = sprintfnum($v['money']);
                $items[$k]['aft_money'] = sprintfnum($v['aft_money']);

                // //获取类型type
                $items[$k]['status_name'] = $typelist[$v['status']];
            }

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id == self::MERCHANT_GROUP){
                $extend = $this->getExtendData($timewhere);
            }

            $result = array("total" => $list->total(), "rows" => $items, "extend" => $extend);
            return json($result);
        }

        return json([]);
    }

    /**
     * 操作
     */
    public function operate($id = '')
    {
        $id = $id ? $id : $this->request->post("id");
        $type = $this->request->post("type"); //type = 1，通过，2-拒绝

        $row = Db::name('issued_record')->where(['id' => $id])->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        if(!in_array($type,[1,2])){
            $this->error('操作类型错误');
        }


        $model = Db::name('issued_record');
        Db::startTrans();
        try {
            if($type == 1){
                //通过，修改状态即可
                $res = $model->where(['id' => $id])->update(['status'=>1,'update_time'=>time()]);
                if(!$res){
                    exception('回滚失败');
                }
            }elseif($type == 2){
                //拒绝

                //查询商家信息
                $merchant = Db::name('merchant')->where('id',$row['merchant_id'])->find();

                // 1.回滚金额
                 $res1 = Db::name('merchant')
                    ->where('id',$row['merchant_id'])
                    ->update([
                        'merchant_amount'=>['inc', $row['money']]
                    ]);
                if(!$res1){
                    exception('返回商户余额失败');
                }

                //2.修改状态
                $res2 = $model->where(['id' => $id])->update(['status'=>2,'update_time'=>time()]);
                if(!$res2){
                    exception('修改状态失败');
                }

                //3.添加账变记录
                $adddata = [
                    'merchant_number' => $merchant['merchant_number'],
                    'orderno' => '',
                    'type' => 5, //type = 5-商户下发拒绝回滚
                    'bef_amount' => $merchant['merchant_amount'],
                    'change_amount' => $row['money'],
                    'aft_amount' => $merchant['merchant_amount'] + $row['money'],
                    'status' => 1,
                    'create_time' => time()
                ];
                // dump($adddata);
                $res3 = Db::name('amount_change_record')->insert($adddata);
                if(!$res3){
                    exception('添加账变记录失败');
                }

            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('操作成功');
    }

    /**
     * 获取所有账变类型
     */
    public function typeslect($is_array = false){
        $result = [
            0 => '未处理',
            1 => '成功',
            2 => '拒绝',
        ];
        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }
    
}
