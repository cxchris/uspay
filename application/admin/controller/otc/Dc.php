<?php

namespace app\admin\controller\otc;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use think\Env;

/**
 * Dc
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于管理数字货币
 */
class Dc extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;

    protected $noNeedRight = ['*'];
    protected $channel_id = 5; // 5-数字货币

    const usdtbalance  = '/account/usdtbalance';

    public function _initialize()
    {
        // var_dump(PHP_OS);exit;
        parent::_initialize();

        //查询express运行情况
        $pid = model('OtcList')->getappcommand();
        $expressStatus = $pid == 0 ? 0 : 1;
        // dump($expressStatus);exit;

        $this->model = model('dc');

        $this->view->assign("typelist", $this->typeslect(true));
        $item = [
            'name' => 'express',
            'value' => $expressStatus,
        ];
        $this->view->assign("item", $item);
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
            $field = 'a.*';
            if (isset($filter['create_time'])) {
                $timearr = explode(' - ',$filter['create_time']);
                // $model->where('a.create_time','between',[strtotime($timearr[0]),strtotime($timearr[1])]);
                $timewhere = ['a.create_time'=>['between',[strtotime($timearr[0]),strtotime($timearr[1])]]];

                // $filter['a.create_time'] = $filter['create_time'];
                unset($filter['create_time']);
            }

            if (isset($filter['status'])) {

                // $filter['a.status'] = $filter['status'];
                $statuswhere = ['a.status' => $filter['status']];
                unset($filter['status']);
            }

            $typelist = $this->typeslect(true);

            \think\Request::instance()->get(['op' => json_encode($op)]);
            \think\Request::instance()->get(['filter' => json_encode($filter)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $model
                ->alias('a')
                ->where($where)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;


            $items = $list->items();
            foreach ($items as $k => $v) {
                $items[$k]['create_time'] = datevtime($v['create_time']);
                //更新进程运行情况
                // $pid = $model->getcommand($v['id']);
                // if($pid == 0){
                //     $model->where('id',$v['id'])->update(['pid'=>0,'status'=>0]);
                //     $status = 0;
                // }else{
                //     $model->where('id',$v['id'])->update(['pid'=>$pid,'status'=>1]);
                //     $status = 1;
                // }
                //查看密钥
                $items[$k]['privateKey'] = '********';
                $items[$k]['amount'] = '**';
                $items[$k]['type'] = $typelist[$v['type']];
                // $items[$k]['status'] = $status;
            }

            
            // dump($rate);
            // echo $this->model->getLastsql();exit;

            //查询 交易金额/交易笔数 等
            $extend = [];
            if($this->group_id != self::MERCHANT_GROUP){
                // $extend = $this->getExtendData($timewhere,$statuswhere,$where);
            }
            
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
                //先验证后台操作员自己的谷歌验证码是否正确
                if(!$this->checkValid($params['checksum'])){
                    $this->error('谷歌校验码错误',null,[]);
                }
                Db::startTrans();
                try {
                    unset($params['checksum']);

                    //生成地址
                    $address = $this->model->generateUSDTWallet();
                    $params['address'] = $address['usdtAddress'];
                    $params['privateKey'] = $address['usdtPrivateKey'];

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
                    $result = $row->validate('Otc.edit')->save($params);

                    if ($result === false) {
                        exception($row->getError());
                    }

                    //node脚本处理
                    $res = $this->model->node_exce($row,$ids);
                    if($res == 0){
                        $result = $row->save(['status'=>0]);
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

    //调整express开关
    public function express($val){
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $val = $val ? $val : $this->request->post("val");
        // dump($val);exit;
        //获取当前状态
        $pid = model('OtcList')->getappcommand();
        $expressStatus = $pid == 0 ? 0 : 1;
        if($val == $expressStatus){
            if($val == 1){
                $this->error('服务无法重复开启');
            }else{
                $this->error('服务已关闭');
            }
        }

        //先杀进程
        model('OtcList')->stopNodeScript($pid);
        //如果是1，则开启，如果0，则关闭
        if($val == 1){
            model('OtcList')->startnodeapp($pid);
        }

        $this->success('success');
    }

    /**
     * 获取所有类型
     */
    public function typeslect($is_array = false){
        $data = model('DcList')->where('status',1)->select();
        $result = [];
        foreach ($data as $key => $value) {
            $result[$value['id']] = $value['name'];
        }

        
        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }

    //查询
    public function amount($id = null){
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $id = $id ? $id : $this->request->post("id");
        if ($id) {
            $row = $this->model->get(['id' => $id]);

            if (!$row) {
                $this->error(__('No Results were found'));
            }

            //post查询usdt余额
            try {
                $arrData  = array(
                    'address' => $row['address'],
                );

                $sign = Sign::getSign($arrData,Env::get('dc.key', ''));
                $arrData['sign'] = $sign;

                
                $url = Env::get('dc.url', '').self::usdtbalance;

                // dump($url);exit;
                // dump($arrData);
                $res = Http::formpost($url,$arrData);

                if($res){
                    $ret = json_decode($res,true);
                    // dump($ret);exit;
                    if($ret){
                        if(isset($ret['code']) && $ret['code'] == 200){
                            return json($ret);
                        }else{
                            throw new \Exception($ret['msg']);
                        }
                    }else{
                        throw new \Exception('Interface exception');
                    }
                }else{
                    throw new \Exception('service no start');
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        $this->error(__('You have no permission'));
    }
    
}
