<?php

namespace app\admin\controller\system;

use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\Validate;
use think\Env;

/**
 * 商户管理
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于商户管理
 */
class Merchant extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;
    protected $noNeedRight = ['*'];

    protected $collection_type = 1; //代收-1，代付-2
    protected $payment_type = 2; //代收-1，代付-2
    protected $merchant_group_id = 2; //商户用户组
    protected $password = '123456'; //默认密码
    protected $operator_type = [
        0 => '请选择',
        1 => '增加代收余额',
        2 => '减少代收余额',
        3 => '增加代付余额',
        4 => '减少代付余额',
    ];

    public function _initialize()
    {
        parent::_initialize();
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
        $this->model = model('Merchant');
        if (!in_array($this->group_id , [self::SUPER_ADMIN_GROUP,self::ADMIN_GROUP])) {
            $this->error('管理员才可以访问');
        }

        $this->assignconfig("admin", ['id' => $this->auth->id]);

        //获取所有银行卡支付渠道
        // $collectionList = \think\Db::name("channel_list")->field('*', true)->where(array('status'=>1,'type'=>$this->collection_type))->order('id ASC')->select();
        $collectionList = model('otc_list')->where(['status'=>1])->select();
        $paymentList = \think\Db::name("channel_list")->field('*', true)->where(array('status'=>1,'type'=>$this->payment_type))->order('id ASC')->select();
        
        //获取name
        $collectionName = [];
        $paymentListName = [0 => __('None')];

        foreach ($collectionList as $k => $v) {
            $type = $v['type'] == 1 ? '转数快' : '银行卡';
            $collectionName[$v['id']] = $type.'-'.$v['account_number'].'-'.$v['ifsc'];
        }
        foreach ($paymentList as $k => $v) {
            $paymentListName[$v['id']] = $v['channel_name'];
        }
        // dump($collectionName);exit;
        $this->view->assign("collectionName", $collectionName);
        $this->view->assign("paymentListName", $paymentListName);
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

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'a.id';

            $list = $this->model
                ->alias('a')
                ->where($where)
                ->join('channel_list b','a.collection_channel_id = b.id','LEFT')
                ->join('channel_list c','a.payment_channel_id = c.id','LEFT')
                ->field('a.*,b.channel_name as collection_channel_name,c.channel_name as payment_channel_name')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $k => &$v) {
                //格式化百分之几+每笔
                $collection = explode('|',$v['collection_fee_rate']);
                $v['collection_fee_rate'] = $collection[0].'+'.$collection[1];
                $payment = explode('|',$v['payment_fee_rate']);
                $v['payment_fee_rate'] = $payment[0].'+'.$payment[1];
            }

            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 后台操作余额记录
     */
    public function order()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = 'a.id';

            $ordermodel = model('Amount_order_log');
            $list = $ordermodel
                ->alias('a')
                ->where($where)
                ->join('admin b','a.operate_admin_id = b.id','LEFT')
                ->field('a.*,b.nickname as admin_name')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $k => &$v) {
                if($v['type'] == 1){
                    $v['type_name'] = '代收';
                }else{
                    $v['type_name'] = '代付';
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
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
                //先验证后台操作员自己的谷歌验证码是否正确
                if(!$this->checkValid($params['checksum'])){
                    $this->error('谷歌校验码错误',null,[]);
                }
                Db::startTrans();
                try {
                    //验证器
                    $validate = new \think\Validate;
                    $validate->rule('collection_fee_rate_per', 'require|number|between:1,100')
                            ->rule('payment_fee_rate_per', 'require|number|between:1,100');
                    $validdata = [
                        'collection_fee_rate_per'  => $params['collection_fee_rate_per'],
                        'payment_fee_rate_per' => $params['payment_fee_rate_per']
                    ];

                    if (!$validate->check($validdata)) {
                        exception($validate->getError());
                    }

                    unset($params['checksum']);
                    //生成用户商户号，也是用户后台登录账号
                    $merchant_number = Random::getMerchantnum();
                    $params['merchant_number'] = $merchant_number;
                    $params['merchant_key'] = Random::getOrderSn();

                    
                    //1.添加admin表
                    $adminData = [];
                    $adminData['username'] = $merchant_number;
                    $adminData['nickname'] = $params['merchant_name'];
                    $adminData['salt'] = Random::alnum();
                    $adminData['password'] = md5(md5($this->password) . $adminData['salt']);
                    $adminData['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
                    $adminmodel = model('Admin');
                    $result = $adminmodel->save($adminData);
                    if ($result === false) {
                        exception($adminmodel->getError());
                    }
                    $params['admin_id'] = $adminmodel->id;
                    //2.添加权限表
                    $dataset = ['uid' => $adminmodel->id, 'group_id' => self::MERCHANT_GROUP];
                    model('AuthGroupAccess')->save($dataset);

                    //3.添加merchant表
                    $data = $this->install_data($params);
                    $result = $this->model->validate('Merchant.add')->save($data);
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

                    $data = $this->install_data($params);

                    $result = $row->validate('Merchant.edit')->save($data);
                    // echo $this->model->getLastsql();exit;
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

        //数据格式化
        $collection_fee_rate = explode('|', $row->collection_fee_rate);
        //手续费转化为前端数据
        $row->collection_fee_rate_per = $collection_fee_rate[0];
        $row->collection_fee_rate_sigle = $collection_fee_rate[1];

        $payment_fee_rate = explode('|', $row->payment_fee_rate);
        //手续费转化为前端数据
        $row->payment_fee_rate_per = $payment_fee_rate[0];
        $row->payment_fee_rate_sigle = $payment_fee_rate[1];

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 余额管理
     */
    public function amount_edit($ids = null)
    {
        $row = $this->model->alias('a')->field('a.*,b.id as merchant_id')->join('merchant b','a.merchant_number = b.merchant_number','LEFT')->where(['a.id' => $ids])->find();

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
                    if($params['type'] == 0){
                        exception('请勾选交易类型');
                    }
                    //判断merchant_change_amount限制
                    $validate = new \think\Validate;
                    $validate->rule('merchant_change_amount', 'number|between:1,10000000');

                    if (!$validate->check(['merchant_change_amount'=>$params['merchant_change_amount']])) {
                        exception($validate->getError());
                    }

                    //1，根据类型添加余额
                    try {
                        $data = $this->operate_type($row,$params);
                    } catch (\Exception $e) {
                        exception($e->getMessage());
                    }
                    $where = [
                        $data['field'] => [$data['operate'],$data['operate_money']],
                        'update_time' => $data['update_time']
                    ];
                    $result = $row->save($where,['id'=>$params['id']]);
                    // echo $this->model->getLastsql();exit;
                    if ($result === false) {
                        exception($row->getError());
                    }
                    //2，添加记录 yd_amount_order_log
                    $cond = [];
                    $cond['merchant_number'] = $row->merchant_number;
                    $cond['bef_money'] = $data['bef_money'];
                    $cond['money'] = $data['money'];

                    $newrow = $this->model->get(['id' => $ids]);
                    if($data['field'] == 'merchant_payment_amount'){
                        $cond['aft_money'] = $newrow->merchant_payment_amount;
                    }else{
                        $cond['aft_money'] = $newrow->merchant_amount;
                    }
                    
                    $cond['note'] = $params['note'];
                    $cond['status'] = 1;
                    $cond['type'] = $data['type'];
                    $cond['operate_admin_id'] = $this->auth->id;
                    $cond['update_time'] = time();
                    $cond['create_time'] = time();
                    $result = model('Amount_order_log')->save($cond);
                    if ($result === false) {
                        exception($row->getError());
                    }

                    //3.添加账变记录
                    if($data['field'] == 'merchant_amount'){
                        $adddata = [
                            'merchant_number' => $row->merchant_number,
                            'type' => 1, //type = 1-后台操作
                            'bef_amount' => $data['bef_money'],
                            'change_amount' => $data['money'],
                            'aft_amount' => $cond['aft_money'],
                            'status' => 1,
                            'create_time' => time(),
                            'remark' => $params['note']
                        ];
                        // dump($adddata);
                        $res3 = Db::name('amount_change_record')->insert($adddata);
                        if (!$res3) {
                            exception('账变记录添加失败');
                        }
                    }else{
                        $adddata = [
                            'merchant_id' => $row->merchant_id,
                            'type' => 1, //type = 1-后台操作
                            'bef_amount' => $data['bef_money'],
                            'change_amount' => $data['money'],
                            'remark' => $params['note']
                        ];
                        // dump($adddata);
                        $res3 = model('payment_change_record')->addrecord($adddata);
                        // echo model('payment_change_record')->getLastsql();exit;
                        if (!$res3) {
                            exception('账变记录添加失败');
                        }
                    }
                    

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $data['aft_money'] = $cond['aft_money'];
                $this->success('操作成功',null,$data);
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $type = $this->operator_type;


        $this->view->assign("type", $type);
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
        $checksum = $this->request->post("checksum");

        //先验证后台操作员自己的谷歌验证码是否正确
        // if(!$this->checkValid($checksum)){
        //     $this->error('谷歌校验码错误',null,[]);
        // }
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
                        //删除商户表
                        $this->model->destroy($deleteIds);
                        //删除后台用户表

                        //删除权限表

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
     * 商户重置密码
     */
    public function reset($ids = null)
    {
        $params = $_REQUEST;
        if(!$params || !$params['id'] || !$params['Checksum']){
            $this->error('参数不能为空',null,[]);
        }

        $model = model('Admin');
        $row = $model->get(['id' => $params['id']]);
        if(!$row){
            $this->error('用户不存在',null,[]);
        }

        //先验证后台操作员自己的谷歌验证码是否正确
        if(!$this->checkValid($params['checksum'])){
            $this->error('谷歌校验码错误',null,[]);
        }


        //重置登录密码为123456
        $adminData = [];
        $adminData['id'] = $params['id'];
        $adminData['salt'] = Random::alnum();
        $adminData['password'] = md5(md5($this->password) . $adminData['salt']);
        $result = $row->save($adminData);
        if ($result === false) {
            $this->error('重置失败',null,[]);
        }
        
        $this->success('重置成功',null,[]);
    }

    //组装入库数组
    public function install_data($data)
    {
        // 拼接代付手续费
        $collection_fee_rate = $data['collection_fee_rate_per'].'|'.$data['collection_fee_rate_sigle'];
        $payment_fee_rate = $data['payment_fee_rate_per'].'|'.$data['payment_fee_rate_sigle'];

        $data['collection_fee_rate'] = $collection_fee_rate;
        $data['payment_fee_rate'] = $payment_fee_rate;

        //代收卡池里存字符串、兼容多个
        $collection_channel_arr = $data['collection_channel_id'];
        $collection_channel_id = implode(',',$collection_channel_arr);

        $data['collection_channel_id'] = $collection_channel_id;



        unset($data['collection_fee_rate_per']);
        unset($data['collection_fee_rate_sigle']);
        unset($data['payment_fee_rate_per']);
        unset($data['payment_fee_rate_sigle']);
        return $data;
    }

    //操作类型
    /*$operator_type = [
        0 => '请选择',
        1 => '增加代收余额',
        2 => '减少代收余额',
        3 => '增加代付余额',
        4 => '减少代付余额',
    ];*/
    public function operate_type($row,$params)
    {
        $data = [];
        switch ($params['type']) {
            case 0:
                break;
            case 1:
                $data['field'] = 'merchant_amount';
                $data['operate'] = 'inc';
                $data['bef_money'] = $row->merchant_amount;
                $data['money'] = $params['merchant_change_amount'];
                $data['type'] = 1;
                break;
            case 2:
                $data['field'] = 'merchant_amount';
                $data['operate'] = 'dec';
                $data['bef_money'] = $row->merchant_amount;
                $data['money'] = -$params['merchant_change_amount'];
                $data['type'] = 1;
                break;
            case 3:
                $data['field'] = 'merchant_payment_amount';
                $data['operate'] = 'inc';
                $data['bef_money'] = $row->merchant_payment_amount;
                $data['money'] = $params['merchant_change_amount'];
                $data['type'] = 2;
                break;
            case 4:
                $data['field'] = 'merchant_payment_amount';
                $data['operate'] = 'dec';
                $data['bef_money'] = $row->merchant_payment_amount;
                $data['money'] = -$params['merchant_change_amount'];
                $data['type'] = 2;
                break;
        }

        //判断merchant_change_amount相减后值不能小于0
        if($data['bef_money'] + $data['money'] < 0){
            exception('余额不能小于0，请检查金额');
            return;
        }

        $data['operate_money'] = $params['merchant_change_amount'];
        $data['update_time'] = time();
        return $data;
    }

}
