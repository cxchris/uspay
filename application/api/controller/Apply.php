<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Session;
use think\Db;
use think\Validate;
use fast\Http;
use fast\Random;
use fast\Sign;
use app\admin\library\CommonPayment;

/*use app\admin\library\Freepaytm;
use app\admin\library\Kirin;
use app\admin\library\Fastpay;
use app\admin\library\Bzpay;
use app\admin\library\Wepay;
use app\admin\library\Dspay;
use app\admin\library\Wowpay;
use app\admin\library\Ndspay;
use app\admin\library\Nndspay;
use app\admin\library\WorldPay;
use app\admin\library\PayGIntegration;*/
use think\Log;
use app\common\Model\PaymentOrder;

/**
 * 申请代付api
 */
class Apply extends Api
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
    protected $t_callbackUrl = 'https://ydapppay.com/ydpay/api/Apply/callback'; //上游通知地址
    protected $callbackUrl = 'https://www.google.com';//跳转给用户的前端页面
    protected $pay_type = ['bank','upi'];
    protected $Support_UPI = ['Kirin']; //支持UPI的渠道

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 创建支付订单获取支付链接
     */
    public function order()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                Log::record('代付请求:'.json_encode($params),'notice');
                //数据格式验证
                $result = $this->validate($params,'app\admin\validate\Apply');
                $params['remark'] = $params['remark']??'';
                $params['extra'] = $params['extra']??'';
                $params['branchName'] = $params['branchName']??'';
                if (true !== $result) {
                    // 验证失败 输出错误信息
                    $this->error($result, [],  self::PARMETR_NOT_EMPTY);
                }
                //商户搜索
                $row = model('\app\admin\model\Merchant')->where('merchant_number', $params['merchantNo'])->find();
                if(!$row){
                    $this->error('Merchant not exist', [],  self::MERCHANT_NOT_EXIST);
                }

                //IP判断
                if($row['use_ip']){
                    $iparr = explode('|',$row['use_ip']);
                    $reqip = request()->ip();
                    if(!in_array($reqip,$iparr)){
                        $this->error('Not reported IP', [],  self::NOT_REPORTED_IP);
                    }
                }

                //判断代收状态是否正常
                if($row->payment_status != 1){
                    $this->error('Merchant status unuse', [],  self::MERCHANT_UNUSE);
                }

                //判断是否为小数
                if(ceil($params['amount']) != $params['amount']){
                    $this->error('cannot be a decimal', [],  self::NOT_BE_DECIMAL);
                }

                //商户最低限额
                if($params['amount'] < $row->payment_low_money){
                    $this->error('amount not less than the allowed limit', [],  self::AMOUNT_LESS_ALLOWED);
                }

                //商户最高限额
                if($params['amount'] > $row->payment_high_money){
                    $this->error('amount not higher than the allowed limit', [],  self::AMOUNT_HIGHER_ALLOWED);
                }

                //必须传入channel了
                if(!$params['channel']){
                    $this->error('No channel', [],  self::AMOUNT_HIGHER_ALLOWED);
                }

                $model = model('PaymentOrder');

                //每日限额，查询今日商户一共代付成功的金额
                $timearr = [
                    strtotime(date('Y-m-d'). '00:00:00'),
                    strtotime(date('Y-m-d'). '23:59:59'),
                ];
                $total = $model->where(['merchant_id'=>$row->id])->where(['status'=>1])->where('create_time','between',[$timearr[0],$timearr[1]])->sum('money');
                if($params['amount'] + $total > $row->payment_limit){
                    $this->error('amount not higher than the daily limit', [],  self::AMOUNT_HIGHER_DAILY_LIMIT);
                }

                // //签名验证
                $sign = Sign::verifySign($params,$row->merchant_key);
                if(!$sign){
                    $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
                }

                //商户订单号验证不重复
                $record = $model->where('out_trade_no', $params['merchantSn'])->where('merchant_id', $row->id)->find();
                if($record){
                    $this->error('Repeat order', [],  self::REPEAT_ORDER);
                }

                //根据传入金额来指定channelid
                // if($params['amount'] <= 1000){
                //     $use_channel_id = $row->payment_channel_id;
                // }else{
                //     $use_channel_id = $row->big_payment_channel_id?$row->big_payment_channel_id:$row->payment_channel_id;
                // }

                //查询指定通道，如果未传，则使用给商户分配的通道
                // $channel_id = isset($params['channel'])?$params['channel']:$use_channel_id;
                $channel_id = $params['channel'];

                $channel = model('\app\admin\model\ChannelList')->where('id', $channel_id)->find();
                if(!$channel){
                    $this->error('Channel not exist', [],  self::CHEANNEL_NOT_EXIST);
                }

                if($channel['status'] != 1){
                    $this->error('Channel closed', [],  self::CHEANNEL_NOT_EXIST);
                }

                //判断pay_type，默认为bank
                $params['pay_type'] = $params['pay_type']??'bank';

                // 判断传的值是否为特定值
                if(!in_array($params['pay_type'],$this->pay_type)){
                    $this->error('error pay_type', [],  self::MERCHANT_UNUSE);
                }

                //判断只有开了upi的渠道才能传这个，否则就报错
                if($params['pay_type'] == 'upi' && !in_array($channel->channel_type,$this->Support_UPI)){
                    $this->error('No this pay type in channel', [],  self::MERCHANT_UNUSE);
                }

                //通道最低限额
                if($params['amount'] < $channel->low_money){
                    $this->error('amount not less than the allowed limit', [],  self::AMOUNT_LESS_ALLOWED);
                }

                //通道最高限额
                if($params['amount'] > $channel->high_money){
                    $this->error('amount not higher than the allowed limit', [],  self::AMOUNT_HIGHER_ALLOWED);
                }

                //每日限额，查询今日通道一共代付成功的金额
                $total = $model->where(['channel_id'=>$channel_id])
                            ->where(['status'=>1])
                            ->where('create_time','between',[$timearr[0],$timearr[1]])
                            ->sum('money');
                if($params['amount'] + $total > $channel->day_limit_money){
                    $this->error('amount not higher than the daily limit', [],  self::AMOUNT_HIGHER_DAILY_LIMIT);
                }


                //判断支付类型
                // if($params['channel'] != $channel->channel_type){
                //     $this->error('Pay type error', [],  self::PAY_TYPE_ERROR);
                // }

                if($channel->channel_type == 'payg'){
                    $snnumber = Random::getsfstr();
                }else{
                    $snnumber = Random::getOrderSn();
                }

                //使用商户费率
                $realRate = $row->payment_fee_rate;

                // dump($realRate);exit;
                //判断商户余额
                $reduce_money = $this->getrate($params['amount'],$realRate,'|') + $params['amount'];
                // dump($this->getrate($params['amount'],$realRate,'|'));exit;

                if($reduce_money > $row['merchant_payment_amount']){
                    $this->error('Merchant balance not enough', [],  self::BALANCE_NOT_ENOUGH);
                }

                //1,验证通过，生成订单
                $cond = [];
                $cond['channel_id'] = $channel_id;
                $cond['merchant_id'] = $row->id;
                $cond['orderno'] = $snnumber;
                // $cond['orderno'] = Random::getEshopSn();
                $cond['out_trade_no'] = $params['merchantSn'];
                $cond['money'] = $params['amount'];
                $cond['rate_money'] = $this->getrate($params['amount'],$realRate,'|');
                // dump($cond['rate_money']);exit;
                $cond['fee_rate'] = $realRate;
                $cond['reduce_money'] = $reduce_money; //扣款金额
                $cond['pay_type'] = $channel->channel_type;
                $cond['channel_type'] = $params['pay_type'];//类型-bank&upi

                $cond['accountName'] = $params['accountName'];
                $cond['accountNo'] = $params['accountNo'];
                $cond['bankName'] = $params['bankName'];
                $cond['bankCode'] = $params['bankCode'];
                $cond['branchName'] = $params['branchName'];


                $cond['notify_url'] = $params['notifyUrl'];
                // $cond['callback_url'] = $params['callbackUrl'];
                $cond['t_notify_url'] = $this->t_callbackUrl; //上游通知地址
                $cond['request_ip'] = $this->request->ip();
                $cond['create_time'] = time();
                $cond['remark'] = $params['remark'];
                $cond['rate_t_money'] = $this->getrate($params['amount'],$channel['rate'],'+');

                $result = $model->insertGetId($cond);
                if ($result == false) {
                    $this->error('订单生成失败',[],555);
                }

                //判断
                Db::startTrans();
                try {
                    //2,预扣商户的代付余额
                    $merchant = Db::name('merchant')->field('merchant_payment_amount')->where('id',$row->id)->find(); //先查找商家前值
                    $res2 = Db::name('merchant')
                        ->where('id',$row->id)
                        ->update([
                            'merchant_payment_amount'=>['dec', $reduce_money]
                        ]);
                    if(!$res2){
                        exception('预扣商户代付余额失败');
                    }

                    //3,添加代付变化记录
                    $adddata = [
                        'orderno' => $snnumber,
                        'merchant_id' => $row->id,
                        'type' => 2, //type = 2-代付预扣
                        'bef_amount' => $merchant['merchant_payment_amount'],
                        'change_amount' => -$reduce_money,
                        'remark' => $params['remark'],
                    ];
                    $res3 = model('payment_change_record')->addrecord($adddata);
                    if(!$res3){
                        exception('添加代付变化记录失败');
                    }

                    //获取支付渠道类
                    $reflector = CommonPayment::getpaymentlibrary($channel);
                    
                    // $cond['orderno'] = '72e33b0063bf451294b78d220d561d13';
                    // $orderinfo = $model->where('id',$result)->find();

                    //4,根据商户的通道ID，请求上游渠道
                    $res = CommonPayment::pay($reflector,'df',$cond,$channel);
                    // dump($res);exit;
                    Log::record('代付返回:'.json_encode($res),'notice');

                    if($res['code'] !== '0000'){
                        //失败的情况,把订单状态修改为失败
                        // $model->where(['orderno'=>$cond['orderno']])->update(['status'=>-1]);
                        exception($res['msg'], $res['code']);
                    }else{
                        //5,修改三方订单号,手续费,status
                        if(isset($res['data'])){
                            //调用公共类获取修改信息
                            $done = CommonPayment::GetApplyMap($res['data'],$channel->channel_type);
                            // dump($done);exit;
                            if($done){
                                $res3 = $model->where(['orderno'=>$cond['orderno']])->update($done);
                            }
                        }
                    }

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    //失败的情况,把订单状态修改为失败
                    $fail_status = '-1';
                    $model->where(['orderno'=>$cond['orderno']])->update(['status'=>$fail_status]);

                    //通知商户
                    $order = $model->notifyShop($cond['orderno'],$fail_status);
                    $this->error($e->getMessage(), [], $e->getCode());
                }

                $data = [
                    "merchantNo" => $row->merchant_number,
                    "merchantSn" => $params['merchantSn'],
                    "sn" => $cond['orderno'],
                    'extra' => $params['extra'],
                    'fee' => $cond['rate_money'],
                    'status' => $done['status']??0
                ];

                Log::record('返回:'.json_encode($data),'notice');
                $this->success('success',$data,200);
            }else{
                $this->error('Parameter can not be empty', [],  self::PARMETR_NOT_EMPTY);
            }
        }else{
            $this->error('only post', [],  self::ONLY_POST);
        }
    }


    /**
     * 支付订单查询
     */
    public function orderInfo()
    {
        $params = $this->request->request();
        if ($params) {
            unset($params['s']);
            //数据格式验证
            $result = $this->validate($params,'app\admin\validate\Apply.orderInfo');
            if (true !== $result) {
                // 验证失败 输出错误信息
                $this->error($result, [],  self::PARMETR_NOT_EMPTY);
            }
            //商户搜索
            $row = Db::name('merchant')->where('merchant_number', $params['merchantNo'])->find();
            if(!$row){
                $this->error('Merchant not exist', [],  self::MERCHANT_NOT_EXIST);
            }

            //IP判断
            if($row['use_ip']){
                $iparr = explode('|',$row['use_ip']);
                $reqip = request()->ip();
                if(!in_array($reqip,$iparr)){
                    $this->error('Not reported IP', [],  self::NOT_REPORTED_IP);
                }
            }

            //签名验证
            $sign = Sign::verifySign($params,$row['merchant_key']);
            if(!$sign){
                $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
            }

            $model = model('PaymentOrder');

            //查询订单信息
            $record = $model->where('out_trade_no', $params['merchantSn'])->where('merchant_id', $row['id'])->find();
            if(!$record){
                $this->error('data empty', [], 404);
            }
            $record['merchant_key'] = $row['merchant_key'];
            $record['merchant_number'] = $row['merchant_number'];
            $data = $model->getCondItem($record,$record['status']);

            Log::record('返回:'.json_encode($data),'notice');
            $this->success('success',$data,200);
        }else{
            $this->error('Parameter can not be empty', [],  self::PARMETR_NOT_EMPTY);
        }
    }

    /**
     * 接受上游回调
     */
    public function callback()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            // $header = getallheaders();
            // $ip = $this->request->ip();

            // $params = '{"code":"CODE_FINISHED","appid":"1044406","order_no":"DF2718300274300030976","pay_trade_no":"P1672621948CLZLX","out_trade_no":"1d8011bd398d4526b4acbd22b62d1f1a","amount":"2250.00","fees":"67.50","deal_time":"1672622777","remark":"","utr":"300206817585","reversal":"1","hold_on":"1","err_msg":"","sign":"451E3B89800223E301A634883E573D03","images":[]}';
            // $params = json_decode($params,true);

            Log::record('代付callback:POST:'.json_encode($params),'notice');
            if($params){
                //获取代付回调的orderNo
                $orderno = CommonPayment::obtainCallbackOrderId($params);
                // dump($orderno);
                // exit;
                if(!$orderno){
                    $this->exit_recrod('faild');
                }
                //1,查询订单
                $model = model('PaymentOrder');
                $order = $model->alias('a')
                            ->join('channel_list b','a.channel_id = b.id','LEFT')
                            ->join('merchant c','a.merchant_id = c.id','LEFT')
                            ->where(['orderno'=>$orderno])
                            ->field('a.*,b.channel_key,c.merchant_number,c.merchant_key,b.channel_type')
                            ->find();

                // dump($order);
                // exit;

                if(!$order){
                    $this->exit_recrod('order not exist');
                }
                
                //2,校验Sign
                $checkres = CommonPayment::checkTheSign($params,$order);
                // dump($checkres);
                // exit;
                if(!$checkres){
                    $this->exit_recrod('verify failed');
                }
                // dump($checkres);exit;
                $data = [];

                //2.5,校验并获取回调状态 checkAndObtainARecoveryState
                $data = CommonPayment::checkAndObtainARecoveryState($params,$order);
                // dump($data);
                // exit;
                $data['callback_time'] = $order['callback_time'] = time();
                
                //3,修改订单状态，存入上游订单号
                $model->where(['orderno'=>$orderno])->update($data);

                //3.5,如果是失败状态，则需要回滚用户代付余额
                if($data['status'] == 3 && $order['status'] != 3){
                    $rollback = $model->rollback_order($order);
                    if(!$rollback){
                        $this->exit_recrod('faild');
                    }
                }

                //4,交易成功，则回调给下游
                model('PaymentOrder')->notifyShop($orderno, $data['status']);

                //5,返回字符串 success 
                $string = CommonPayment::return_string($order);
                $this->exit_recrod($string);
                // if($order['channel_type'] == 'bzpay' || $order['channel_type'] == 'worldPay'){
                //     $this->exit_recrod('ok');
                // }else{
                //     //5,返回字符串 success 
                //     $this->exit_recrod('success');
                // }

            }else{
                $this->exit_recrod('parameter is empty');
            }
        }else{
            $this->error('only post', [],  self::ONLY_POST);
        }
    }

    /**
     * 测试
     */
    public function test()
    {

        $channel = \app\admin\model\ChannelList::where('id', 17)->find();
        // $res = PayGIntegration::CheckBalance($channel);
        // exit;

        // $orderinfo = Db::name('payment_order')->where('orderno','GAYM872453')->find();
        // $res = $this->paymethod($orderinfo,$channel);
        // dump($res);exit;
    }
}
