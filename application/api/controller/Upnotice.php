<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Session;
use think\Db;
use think\Validate;
use fast\Http;
use fast\Random;
use fast\Sign;
use app\admin\library\Paytm;
use app\admin\library\PayGIntegration;
use think\Log;
use app\common\Model\PayOrder;
use app\common\Model\Product;

/**
 * 个卡安卓回调
 */
class Upnotice extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    // protected $url = 'http://65.1.106.28/api/pay/order';
    protected $testid = 8; //测试通道
    protected $channel = 'alipay'; //通道名-支付宝原生
    protected $channel_key; //密钥
    protected $test_amount = 100.00; //测试金额
    protected $success_code = '200'; //成功码
    protected $link = ''; //平台跳转地址
    protected $akey = 'qwerty';
    protected $t_callbackUrl = 'https://ydapppay.com/ydpay/api/Pay/callback'; //上游通知地址
    protected $callbackUrl = 'https://www.google.com';//跳转给用户的前端页面
    protected $key = 'B3iYKkRHlmUanQGaNMIJziWOkNN9dECQQD';

    public function _initialize()
    {

        parent::_initialize();
    }

    /**
     * 个卡cash回调
     */
    public function callback()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            // $params = '{"BANKTXNID":"","CHECKSUMHASH":"qiq2PYIs3X0RXs55tmyp9Jh\/oYiu2js3M7DNl+UmumH7m1Wlji5K63rOskv1EhkrivWCYAu3aW57CLXGQeGIjNXpnsTv6j5pq77wO\/Opi34=","CURRENCY":"INR","MID":"knHPNb01357087489330","ORDERID":"7a8a0678d55847c9a0307712b40388c2","RESPCODE":"141","RESPMSG":"User has not completed transaction.","STATUS":"TXN_FAILURE","TXNAMOUNT":"10.00","TXNID":"20220329111212800110168028504976045"}';
            // $params = json_decode($params,true);

            // $params = json_decode($params,true);
            Log::record('个卡回调callback:POST:'.json_encode($params),'notice');
            if ($params) {
                $end_time = time();
                // $start_time = time() - 30*60;
                $start_time = time() - 86400*2;

                if(!isset($params['sign'])){
                    $this->error('sign error', [],  self::SIGN_VERFY_FAID);
                }

                //签名验证
                $sign = Sign::verifySign($params,$this->key);
                if(!$sign){
                    $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
                }

                if(!isset($params['amount'])){
                    $this->error('amount error', [],  self::SIGN_VERFY_FAID);
                }

                if(!isset($params['pkg'])){
                    $this->error('NO pkg', [],  self::SIGN_VERFY_FAID);
                }

                if($params['pkg'] != 'Cash App'){
                    $this->error('pkg error', [],  self::SIGN_VERFY_FAID);
                }

                $params['amount'] = (float)trim($params['amount']);
                //传入备注，如果没有就不做处理，算掉单
                if(!isset($params['note'])){
                    Log::record('个卡回调callback:POST: note empty 金额:'.$params['amount'],'notice');
                    $this->error('note empty', [],  self::SIGN_VERFY_FAID);
                }
                $params['note'] = trim($params['note']);

                //获取containsContinue、containsReceived
                if(!isset($params['containsContinue'])){
                    $this->error('containsContinue error', [],  self::SIGN_VERFY_FAID);
                }

                if(!isset($params['containsReceived'])){
                    $this->error('containsReceived error', [],  self::SIGN_VERFY_FAID);
                }

                if($params['containsContinue'] == $params['containsReceived']){
                    $this->error('contains error', [],  self::SIGN_VERFY_FAID);
                }


                $where = [
                    'a.virtual_money'=>$params['amount'],
                    // 'a.status' => 3,
                    'a.create_time' => ['between',[$start_time,$end_time]], //十分钟以内
                    'a.utr' => $params['note']
                ];

                // Db::name('pay_order')->where(['id'=>36218])->update(['create_time'=>time()]);
                //1,查询订单-查询方法，根据当前money 金额，和当前时间来查询
                $order = model('PayOrder')
                            ->alias('a')
                            ->join('channel_list b','a.channel_id = b.id','LEFT')
                            ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
                            ->where($where)
                            ->field('a.*,b.channel_key,c.merchant_key,b.channel_type,c.merchant_billing_around')
                            ->find();
                // Db::name('pay_order')->where(['id'=>36218])->update(['create_time'=>time()]);
                // echo Db::name('pay_order')->getlastsql();exit;
                // dump($order);
                // exit;
                if(!$order){
                    $this->error('order not exist');
                }

                //判断订单是否已经完成
                if($order['status'] == 1){
                    $this->error('order already success');
                }

                //如果已经是确认状态就不用再来确认了
                if($order['status'] == 4 && $params['containsContinue'] == 1){
                    $this->error('order already make sure');
                }

                $data = [];
                $status = 1;

                $data['txtUTR'] = $params['note'];

                //两种情况，一种是不用接受的情况，那么直接修改状态为成功，另一种是是需要接受的情况，那么先接受continue的状态修改为支付未接受，但不进行支付成功的回调，等确认后再来完成回调
                if($params['containsContinue'] == 1){
                    //修改状态为4-支付未确认，等待后续邮件确认
                    $status = 4;
                }
                
                $data['callback_time'] = $end_time;
                $data['status'] = $status;
                

                //3,修改订单状态，存入上游订单号
                Db::name('pay_order')->where(['id'=>$order['id']])->update($data);

                $model = model('PayOrder');
                //判断如果是DO商户且交易成功的状态，就结算
                if($data['status'] == 1 && $order['merchant_billing_around'] == 'd0'){
                    $model->check_pay_order($order);
                }

                // $model = model('PayOrder');
                // //4,交易成功，则回调给下游
                if($order['notify_url'] && $data['status'] == 1){
                    $cond = $model->getCondItem($order,$data['status']);

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

                $this->success('订单处理完成');
            }else{
                $this->error('Parameter can not be empty', [],  self::PARMETR_NOT_EMPTY);
            }
        }else{
            $this->error('only post', [],  self::ONLY_POST);
        }
    }

    /**
     * windows回调
     */
    public function check(){
        if ($this->request->isPost()) {
            $params = $this->request->post();
            // $params = '{"BANKTXNID":"","CHECKSUMHASH":"qiq2PYIs3X0RXs55tmyp9Jh\/oYiu2js3M7DNl+UmumH7m1Wlji5K63rOskv1EhkrivWCYAu3aW57CLXGQeGIjNXpnsTv6j5pq77wO\/Opi34=","CURRENCY":"INR","MID":"knHPNb01357087489330","ORDERID":"7a8a0678d55847c9a0307712b40388c2","RESPCODE":"141","RESPMSG":"User has not completed transaction.","STATUS":"TXN_FAILURE","TXNAMOUNT":"10.00","TXNID":"20220329111212800110168028504976045"}';
            // $params = json_decode($params,true);
// dump($params);exit;
            // $params = json_decode($params,true);
            Log::record('windows银行回调 callback:POST:'.json_encode($params),'notice');
            if ($params) {
                //签名认证
                // //签名验证
                // $sign = Sign::getbkSign($params,$this->akey);
                // dump($sign);exit;

                $sign = Sign::verifybkSign($params,$this->akey);
                if(!$sign){
                    $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
                }
                $data = $params['data'];
                if($data){
                    foreach ($data as $key => $value) {
                        $this->utrlParse($value);
                    }
                }
                $this->success('订单处理完成');
            }else{
                $this->error('Parameter can not be empty', [],  self::PARMETR_NOT_EMPTY);
            }
        }else{
            $this->error('only post', [],  self::ONLY_POST);
        }
    }

    //分析UTR,判断有就不插入了，
    protected function utrlParse($value){
        //
        $utrno = trim($value['ref_id']);
        $money = trim($value['amount']);
        $money = str_replace(",", "", $money);
        $res = Db::name('utr_list')->where(['utr'=>$utrno])->find();
        if(!$res){
            //不存在，插入就行了
            if($utrno != '' && $money != 0){
                $cond = [
                    'utr' => $utrno,
                    'money' => $money,
                    'create_time' => time(),
                ];
                // dump($cond);exit;
                Db::name('utr_list')->insert($cond);
            }
        }
        return true;
    }
}
