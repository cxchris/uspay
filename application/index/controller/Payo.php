<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;
use fast\Sign;
use app\admin\library\Paytm;

class Payo extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $errorUrl = 'https://www.google.com';
    protected $expire_time = 15*60;
    protected $_expire_time = 5*60; //前端超时时间
    protected $logo = '/ydpay/public/assets/img/dute_favicon_32x32.ico';
    protected $akey = 'qwerty';
    // protected $logo = 'https://gopay88.io/favicon-gopay.ico';

    public function _initialize(){
        $params = $this->request->request();
        $this->isAjax = isset($params['isAjax'])?$params['isAjax']:0;
        $this->isGetInfo = isset($params['isGetInfo'])?$params['isGetInfo']:0;
        if(!isset($params['params'])){
            $this->_error('params error');
        }
        $encryptData = $params['params']; //加密值

        if(!$encryptData){
            $this->_error('data error');
        }
        $decryptData = Sign::decrypt($encryptData,$this->akey);
        if(!$decryptData){
            $this->_error('decrypt data error');
        }
        $decryptData = json_decode($decryptData,true);
        // dump($decryptData);exit;
        $this->orderId = $decryptData['orderId'];
        if(!$this->orderId){
            $this->_error('orderId error');
        }

        //paytm token
        $this->txnToken = $decryptData['txnToken'];
        if(!$this->txnToken){
            $this->_error('txnToken error');
        }

        $model = $this->model = model('PayOrder');
        // 查询订单
        $cond = [
            'a.eshopno' => $this->orderId
        ];
        $order = $this->orderinfo = $model
            ->field('a.*,b.channel_pay_type,b.channel_type,b.channel_sign,b.channel_safe_url')
            ->alias('a')
            ->join('channel_list b','a.channel_id = b.id','LEFT')
            ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
            ->where($cond)
            ->find();
        
        if(!$order){
            $this->_error('Order does not exist');
        }

        if($order['channel_type'] != 'Payment'){
            $this->_error('Payment type exception');
        }

        // dump($order['channel_pay_type']);exit;
        if($order['channel_pay_type'] != 'upi'){
            $this->_error('Not UPI');
        }

        //来获取订单信息的,就不用判断超时问题，直接可以获取订单状态
        if($this->isGetInfo != 1){
            if($order['create_time'] == 0){
                $this->_error('Order time exception');
            }

            if($order['status'] == 1 || $order['status'] == 2){
                $this->_error('order processed');
            }

            $calc = time() - $order['create_time'];
            if($order['status'] == 3 && $calc > $this->_expire_time){
                $this->_error('The order has expired, please place a new order');
            }

            //判断超时，超时时间15分钟
            if($calc > $this->expire_time){
                $this->_error('Order timed out');
            }
        }
    }

    public function index()
    {
        $orderId = $this->orderId;
        $order = $this->orderinfo;

        //获取账户信息
        // $card = DB::name('otc_list')->where(['id'=>$order['otc_id']])->find();
        // $order['card'] = $card;
        $order['money'] = sprintfnum($order['money']);
        // $upi = 'upi://pay?cu=INR&pa='.$card['account_number'].'&pn=payment&tn='.$order['eshopno'].'&mc=0000&am='.$order['virtual_money'];

        $calc = $this->_expire_time - (time() - $order['update_time']);
        $calc = $calc < 0?0:$calc;

        $this->view->assign("order", $order);
        $this->view->assign("calc", $calc);
        $this->view->assign("expire_time", $this->_expire_time);

        // $this->view->assign('logo',$this->logo);
        // $this->view->assign('upi',$upi);
        return $this->view->fetch();
    }

    /*
    * 获取深层链接并并前端发起调用
    */
    public function apply(){
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if($params){
                $channel = $params['channel'];
                $osType = $params['osType'];

                if(!$channel){
                    $this->error('No channel');
                }

                if(!$osType){
                    $this->error('No osType');
                }

                if($this->orderinfo['status'] == 3){
                    $this->error('order is being processed.');
                }

                $this->orderinfo['osChannel'] = $channel;
                $this->orderinfo['osType'] = $osType;
                $res = Paytm::processTransaction($this->orderinfo,$this->txnToken);

                if($res['code'] != '0000'){
                    //失败的情况
                    $this->error($res['msg']);
                }else{
                    $link = $res['data']['deepLinkInfo']['deepLink'];
                    //如果是安卓，替换字符串
                    if($osType == 'Android' ){
                        if($channel == 'Phonepe'){
                            $link = str_replace('upi://', 'Phonepe://', $link);
                        }elseif($channel == 'Gpay'){
                            $link = str_replace('upi://', 'gpay://upi/', $link);
                        }elseif($channel == 'Paytm'){
                            $link = str_replace('upi://', 'paytmmp://', $link);
                        }

                    }

                    //修改订单状态status = 3
                    Db::name('pay_order')->where(['id'=>$this->orderinfo['id']])->update(['status'=>3,'update_time'=>time()]);


                    //把深层链接返回到前端拉起App
                    $data = [
                        'deepLinkInfo' => $link,
                    ];
                    $this->success('success','',$data);

                }
            }else{
                $this->error('No params');
            }
        }else{
            $this->error('only post');
        }
    }

    /*
    * 下单成功后，间隔5秒一次请求获取订单状态是否成功
    */
    public function orderinfo(){
        if ($this->request->isPost()) {
            $params = $this->request->post();

            $data = [
                'status' => $this->orderinfo['status'],
            ];
            
                
            $this->success('success','',$data);
        }else{
            $this->error('only post');
        }
    }

    private function _error($msg){
        if($this->isAjax){
            $this->error($msg);
        }else{
            $this->error($msg,$this->errorUrl);
        }
        
    }

}
