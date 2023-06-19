<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;
use think\Log;
use fast\Sign;
use fast\Http;

class Pay extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $errorUrl = 'https://www.google.com';
    protected $expire_time = 15*60;
    protected $_expire_time = 15*60; //前端超时时间
    protected $logo = '/ydpay/public/assets/img/dute_favicon_32x32.ico';
    protected $akey = 'qwerty';
    protected $lang = 'tc-cn';

    public function _initialize(){
        $params = $this->request->request(); //http://localhost:88/index/pay?orderno=p438578475812&lang=en
        $this->isAjax = isset($params['isAjax'])?$params['isAjax']:0;
        $this->isGetInfo = isset($params['isGetInfo'])?$params['isGetInfo']:0;
        $this->lang = isset($params['lang'])?$params['lang']:$this->lang;
        if(!isset($params['orderno'])){
            $this->_error('orderno error');
        }


        // dump($decryptData);exit;
        $this->orderId = $params['orderno'];
        if(!$this->orderId){
            $this->_error('orderId error');
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
        
        // dump($order);exit;
        if(!$order){
            $this->_error('Order does not exist');
        }

        if($order['channel_type'] != 'otc'){
            $this->_error('type exception');
        }

        // dump($order['channel_pay_type']);exit;
        // if($order['channel_pay_type'] != 'upi'){
        //     $this->_error('Not UPI');
        // }

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
                // $this->_error('The order has expired, please place a new order');
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

        if($this->orderinfo['status'] == 0){
            $time = time();
            $res = Db::name('pay_order')->where(['id'=>$this->orderinfo['id']])->update(['status'=>3,'update_time'=>$time]);
            $this->orderinfo['status'] = 3;
            $this->orderinfo['update_time'] = $time;
        }
        // $url = preg_replace("/\/(\w+)\.php$/i", '', $this->request->root());
        // dump($url);exit;
        $order = $this->orderinfo;

        //获取账户信息
        $order['money'] = sprintfnum($order['virtual_money']);
        // dump($order);exit;

        $calc = $this->_expire_time - (time() - $this->orderinfo['update_time']);
        $calc = $calc < 0?0:$calc;
        // dump(__("The current version %s is too low, please use PHP 7.1 or higher", PHP_VERSION));exit;

        //获取时间
        $minutes = intval($calc/60); //怎么说呢  假如(60*10-1)s 正常应该就是的 9.983333  取整就是 9  
        $seconds = $calc % 60;   //余数指定是个整数
        $minutes = (String)(($minutes));
        $seconds = (String)(($seconds));

        $timeArray = [
            'minutes' => $minutes,
            'seconds' => $seconds,
        ];
        // dump($timeArray);exit;


        //查询用的个卡和获取个卡UPI
        $otcid = $this->orderinfo['otc_id'];
        if($otcid == 0){
            $this->error('order error');
        }
        $this->otc_info = Db::name('otc_list')->where(['id'=>$otcid])->find();
        if(!$this->otc_info){
            $this->error('Chennel closed');
        }

        $cashUrl = 'https://cash.app/'.$order['ext_data'].'?qr=1';
        
        // dump($this->otc_info);exit;
        $this->view->assign("order", $order);
        $this->view->assign("calc", $calc);
        $this->view->assign("timeArray", $timeArray);
        $this->view->assign("expire_time", $this->_expire_time);
        $this->view->assign("otc_info", $this->otc_info);
        // $this->view->assign("payload", $data);
        $this->view->assign("errorUrl", $this->errorUrl);
        $this->view->assign("lang", $this->lang);
        $this->view->assign("cashUrl", $cashUrl);

        $this->view->assign('logo',$this->logo);
        return $this->view->fetch();
    }

    /*
    * 写入订单UTR
    */
    public function applyutr(){
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if($params){
                $txtUTR = trim($params['txtUTR']);
                if(!$txtUTR){
                    $this->error('No UTR');
                }

                //写入UTR
                $res = Db::name('pay_order')->where(['id'=>$this->orderinfo['id']])->update(['txtUTR'=>$txtUTR]);

                $this->success('success','',[]);
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

            // $order = $this->orderinfo;
            $order = Db::name('pay_order')->where(['id'=>$this->orderinfo['id']])->find();
            //根据utr查找已记录的utr
            $utr_record = Db::name('utr_list')->where(['utr' => $order['txtUTR'],'status'=>0])->find();
            // dump($utr_record);exit;

            //匹配金额
            if($order['status'] == 3 && $utr_record && $utr_record['money'] == $order['money']){
                Db::name('pay_order')->where(['id'=>$this->orderinfo['id']])->update(['status'=>1]);
                Db::name('utr_list')->where(['utr'=>$order['txtUTR']])->update(['status'=>1]);

                $model = model('PayOrder');
                //4,交易成功，则回调给下游
                if($order['notify_url']){
                    //获取商户密钥回调
                    $merchant = Db::name('merchant')->where(['merchant_number'=>$order['merchant_number']])->find();
                    if(!$merchant){
                        $this->error('商户不存在');
                    }
                    $order['merchant_key'] = $merchant['merchant_key'];
                    $cond = $model->getCondItem($order,$order['status']);

                    try {
                        $res = Http::post($order['notify_url'], $cond, $options = []);
                        Log::record('notify:通知参数'.json_encode($cond),'notice');
                        Log::record('notify:通知回答'.json_encode($res),'notice');
                        if(!$res){
                            $model->update_pay_order($order['id'],2);
                            exception('通知失败');
                        }
                    } catch (\Exception $e) {
                        Log::record('notify:通知失败'.$order['orderno'],'notice');
                        // $this->error($e->getMessage());
                    }

                    if($res){
                        if($res == 'success'){
                            $model->update_pay_order($order['id'],1);
                            Log::record('notify:通知成功'.$order['orderno'],'notice');
                        }else{
                            $model->update_pay_order($order['id'],2);
                            Log::record('notify:通知失败'.$order['orderno'],'notice');
                        }
                    }else{
                        $model->update_pay_order($order['id'],2);
                        Log::record('notify:通知失败'.$order['orderno'],'notice');
                    }
                }
            }

            $data = [
                'status' => $order['status'],
            ];
                
            $this->success('success','',$data);
        }else{
            $this->error('only post');
        }
    }

    private function valid_time($a){
        if($a < 10){
            return "0".$a;
        }else{
            return $a;
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
