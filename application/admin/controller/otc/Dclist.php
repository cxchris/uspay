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
class Dclist extends Backend
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
        $this->model = model('DcList');

        $this->view->assign("typelist", $this->typeslect(true));
        $item = [
            'name' => 'express',
            'value' => 1,
            'switch' => 0
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
                ->where($statuswhere)
                ->field($field)
                ->order($sort, $order)
                ->paginate($limit);
            // echo $this->model->getLastsql();echo '<br>';echo '<br>';exit;


            $items = $list->items();
            foreach ($items as $k => $v) {
                
            }

            
            // dump($rate);
            // echo $this->model->getLastsql();exit;

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
                //先验证后台操作员自己的谷歌验证码是否正确
                if(!$this->checkValid($params['checksum'])){
                    $this->error('谷歌校验码错误',null,[]);
                }
                Db::startTrans();
                try {
                    unset($params['checksum']);

                    $result = $this->model->allowField(true)->save($params);
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

    //调整开关
    public function express(){
        
    }

    /**
     * 获取所有类型
     */
    public function typeslect($is_array = false){
        $result = [
            "1" => 'trc20',
            "2" => 'erc20'
        ];
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
