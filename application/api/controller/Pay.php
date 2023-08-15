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
use app\admin\library\Otcpay;

/*use app\admin\library\Paytm;
use app\admin\library\PayGIntegration;
use app\admin\library\Otcpay;
use app\admin\library\Cashfree;
use app\admin\library\Kirin;
use app\admin\library\Fastpay;
use app\admin\library\Bzpay;
use app\admin\library\Dspay;
use app\admin\library\Wepay;
use app\admin\library\Wowpay;
use app\admin\library\WorldPay;
use app\admin\library\GlobalPay;
use app\admin\library\Uzpay;
use app\admin\library\Ndspay;
use app\admin\library\Nndspay;
use app\admin\library\Xdpay;*/

use think\Log;
use app\common\Model\PayOrder;
use app\common\Model\Product;
use think\Env;

/**
 * 支付api
 */
class Pay extends Api
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
                Log::record('代收请求:'.json_encode($params),'notice');
                //数据格式验证
                $result = $this->validate($params,'app\admin\validate\Pay');
                $callbackUrl = $params['returnUrl']??$this->callbackUrl; //跳转给用户的前端页面
                $params['remark'] = $params['remark']??'';
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
                if($row->collection_status != 1){
                    $this->error('Merchant status unuse', [],  self::MERCHANT_UNUSE);
                }

                //判断是否为小数
                if(ceil($params['amount']) != $params['amount']){
                    $this->error('cannot be a decimal', [],  self::NOT_BE_DECIMAL);
                }

                //商户最低限额
                if($params['amount'] < $row->collection_low_money){
                    $this->error('amount not less than the allowed limit', [],  self::AMOUNT_LESS_ALLOWED);
                }

                //商户最高限额
                if($params['amount'] > $row->collection_high_money){
                    $this->error('amount not higher than the allowed limit', [],  self::AMOUNT_HIGHER_ALLOWED);
                }

                //判断获取到的通道ID，默认是1，cashapp
                $channelId = isset($params['channel'])?$params['channel']:1;

                //每日限额，查询今日商户一共代付成功的金额
                $timearr = [
                    strtotime(date('Y-m-d'). '00:00:00'),
                    strtotime(date('Y-m-d'). '23:59:59'),
                ];
                $total = Db::name('pay_order')->where(['merchant_number'=>$params['merchantNo']])->where(['status'=>1])->where('create_time','between',[$timearr[0],$timearr[1]])->sum('money');
                if($params['amount'] + $total > $row->collection_limit){
                    $this->error('amount not higher than the daily limit', [],  self::AMOUNT_HIGHER_DAILY_LIMIT);
                }

                // //签名验证
                $sign = Sign::verifySign($params,$row->merchant_key);
                if(!$sign){
                    $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
                }

                $model = model('PayOrder');

                //商户订单号验证不重复
                $record = $model->where('out_trade_no', $params['merchantSn'])->where('merchant_number', $params['merchantNo'])->find();
                if($record){
                    $this->error('Repeat order', [],  self::REPEAT_ORDER);
                }

                if(!$row->collection_channel_id){
                    $this->error('Channel not exist', [],  self::CHEANNEL_NOT_EXIST);
                }

                //获取可用的卡池
                $library = new \stdClass;
                $library->channel_type = 'otc';
                $reflector = CommonPayment::getpaymentlibrary($library);
                $snnumber = Random::generateRandom(16);
                //打包查询条件
                $channeldata = [
                    'otc_channel' => $row->collection_channel_id,
                    'channel' => $channelId,
                ];
                $res = CommonPayment::pay($reflector,'pay',$params['amount'],$channeldata);
                // dump($res);exit;
                if(!$res || $res['code'] == 400){
                    $this->error('Channel not exist', [],  self::CHEANNEL_NOT_EXIST);
                }

                Log::record('代收下单获取卡池:'.json_encode($res),'notice');


                //查询指定通道，如果未传，则使用给商户分配的通道
                $channel_id = $res['channel_id'];

                $channel = model('\app\admin\model\ChannelList')->where('id', $channel_id)->find();

                if(!$channel){
                    $this->error('Channel not exist', [],  self::CHEANNEL_NOT_EXIST);
                }

                if($channel['status'] != 1){
                    $this->error('Channel closed', [],  self::CHEANNEL_NOT_EXIST);
                }

                //通道最低限额
                if($params['amount'] < $channel->low_money){
                    $this->error('amount not less than the allowed limit', [],  self::AMOUNT_LESS_ALLOWED);
                }

                //通道最高限额
                if($params['amount'] > $channel->high_money){
                    $this->error('amount not higher than the allowed limit', [],  self::AMOUNT_HIGHER_ALLOWED);
                }

                //每日限额，查询今日通道一共代收成功的金额
                $total = Db::name('pay_order')->where(['channel_id'=>$channel_id])->where(['status'=>1])->where('create_time','between',[$timearr[0],$timearr[1]])->sum('money');
                if($params['amount'] + $total > $channel->day_limit_money){
                    $this->error('amount not higher than the daily limit', [],  self::AMOUNT_HIGHER_DAILY_LIMIT);
                }


                //判断支付类型
                $paychannel = $channel->channel_type;
                // if($paychannel != $channel->channel_type){
                //     $this->error('Pay type error', [],  self::PAY_TYPE_ERROR);
                // }

                //查找用户uid
                // $custom = Db::name('customer')->orderRaw('rand()')->find();
                // //查找实际购买的商品
                // $promodel = model('Product');
                // $goods = $promodel->getrandomgood($params['amount']);
                // dump($goods);exit;

                $amount = $params['amount'];
                //获取商户号
                // if($channel->channel_type == 'payg'){
                //     $snnumber = Random::generateRandom();
                // }elseif($channel->channel_type == 'otc'){
                //     $snnumber = Random::generateRandom(16);
                //     $res = Otcpay::pay($snnumber,$amount,$channel->channel_pay_type);
                //     if($res['code'] != '0000'){
                //         $this->error('otc account disable', [],  self::PAY_TYPE_ERROR);
                //     }
                // }else{
                //     $snnumber = Random::getEshopSn();
                // }

                //使用商户费率
                $realRate = $row->collection_fee_rate;
                // dump($realRate);exit;

                //判断
                Db::startTrans();
                try {
                    //1,验证通过，生成订单
                    $cond = [];
                    $cond['channel_id'] = $channel_id;
                    $cond['merchant_number'] = $row->merchant_number;
                    $cond['orderno'] = Random::getOrderSn();
                    $cond['eshopno'] = $snnumber;
                    $cond['out_trade_no'] = $params['merchantSn'];
                    $cond['money'] = $amount;
                    $cond['rate_money'] = $this->getrate($amount,$realRate,'|');
                    $cond['collection_fee_rate'] = $realRate;
                    $cond['account_money'] = $amount - $cond['rate_money'];
                    $cond['billing_around'] = $row->merchant_billing_around;
                    $cond['pay_type'] = $paychannel;
                    $cond['utr'] = Random::alnum(8);
                    $cond['ext_data'] = $res['account_number'];

                    // $cond['customer_uid'] = $custom['id'];
                    // $cond['customer_name'] = $custom['username'];
                    // $cond['customer_mobile'] = $custom['mobile'];
                    // $cond['customer_address'] = $custom['address'];
                    // $cond['customer_email'] = $custom['email'];
                    $cond['t_notify_url'] = $this->t_callbackUrl; //上游通知地址

                    if($channel->channel_type == 'otc'){
                        $cond['otc_id'] = $res['id'];
                        $cond['virtual_money'] = $res['amount'];
                        $cond['rate_t_money'] = $this->getrate($amount,$channel['rate'],'+');
                        //添加卡信息记录
                    }


                    $cond['notify_url'] = $params['notifyUrl'];
                    $cond['callback_url'] = $callbackUrl;
                    $cond['request_ip'] = $this->request->ip();
                    $cond['create_time'] = time();
                    $cond['update_time'] = time();
                    $cond['remark'] = $params['remark'];
                    $result = Db::name('pay_order')->insertGetId($cond);
                    if ($result == false) {
                        exception('订单生成失败',555);
                    }

                    
                    $orderinfo = Db::name('pay_order')->where('id',$result)->find();

                    // dump($channel->channel_type);exit;
                    // if($channel->channel_type != 'otc'){
                    //     // $cond['orderno'] = '72e33b0063bf451294b78d220d561d13';
                    //     //2,根据商户的通道ID，请求上游渠道，获取token
                    //     $res['data']['txnToken'] = '';
                    //     $res = CommonPayment::pay($reflector,'pay',$orderinfo,$channel);
                    //     Log::record('代收下单:'.json_encode($res),'notice');
                    // }
                    

                    if($res['code'] != '0000'){
                        //失败的情况,把订单状态修改为失败
                        Db::name('pay_order')->where(['orderno'=>$cond['orderno']])->update(['status'=>-1]);
                        Db::commit();
                        exception($res['msg'], $res['code']);
                    }else{
                        $setData = CommonPayment::dispose($reflector,$res['data'],$orderinfo,$channel); //返回tn和URL
                        if($setData['tn']){
                            Db::name('pay_order')->where(['id'=>$result])->update(['tn'=>$setData['tn']]);
                        }
                        $payUrl = $setData['url'];
                    }

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage(), [], $e->getCode());
                }

                $data = [
                    'payUrl' => $payUrl,
                    "merchantNo"=> $row->merchant_number,
                    "merchantSn"=> $params['merchantSn'],
                    "sn"=> $cond['orderno'],
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
            $result = $this->validate($params,'app\admin\validate\Pay.orderInfo');
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

            $model = model('PayOrder');

            //查询订单信息
            $record = $model->where('out_trade_no', $params['merchantSn'])->where('merchant_number', $params['merchantNo'])->find();
            if(!$record){
                $this->error('data empty', [], 404);
            }
            $record['merchant_key'] = $row['merchant_key'];
            $data = $model->getCondItem($record,$record['status']);

            Log::record('返回:'.json_encode($data),'notice');
            $this->success('pay success',$data,200);
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
            $header = getallheaders();
            $ip = $this->request->ip();

            // $ip = '54.169.32.94';
            // $params = '{"tradeResult":"1","oriAmount":"200.00","amount":"200.00","mchId":"977977001","orderNo":"188261311","mchOrderNo":"YDHOSH5472287094623310556894","merRetMsg":"test","sign":"3acb0a6a72806eff052886901e61954c","signType":"MD5","orderDate":"2022-08-23 18:50:37"}';
            // $params = json_decode($params,true);


            // $params = '{"resource":{"serviceFee":"8","tradeAmount":"200","tradeNo":"60132239976","tradeStatus":"SUCCESS","outTradeNo":"YDHOSH3995916888539207837462","currencySymbol":"INR","endTime":"1660820485"},"notifyTime":"1660820496","eventType":"RUNTRADER.NOTIFY","id":"d12b5abe-0229-44a0-a2bf-a40bc6c1cca1"}';
            // $params = json_decode($params,true);
            Log::record('代收callback:POST:'.json_encode($params),'notice');
            Log::record('代收callback:POST header:'.json_encode($header),'notice');
            // $header = '{"content-length":"286","connection":"keep-alive","host":"ydapppay.com","pragma":"no-cache","cache-control":"no-cache","elastic-apm-traceparent":"00-59a66a0333a718c7240c6d37c442815a-b56201dfeef72a8c-00","traceparent":"00-59a66a0333a718c7240c6d37c442815a-b56201dfeef72a8c-00","content-type":"application\/json;charset=UTF-8","x-qu-signature":"381DA87957C4E92759FD5685A2B0D3CC816226C74548539B21E30B58F455F39B","accept-language":"zh-CN,zh;q=0.8","x-qu-signature-method":"HmacSHA256","x-qu-nonce":"qyez58snvm3skfsagjzhk8jepwifith7","accept-encoding":"gzip, deflate","x-qu-mid":"300355","user-agent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.142 Safari\/537.36 Hutool","x-qu-timestamp":"1660820496","x-qu-access-key":"D620A4IWDDT3I7OFDXW9QZRE","accept":"text\/html,application\/json,application\/xhtml+xml,application\/xml;q=0.9,*\/*;q=0.8","x-qu-signature-version":"v1.0"}';
            // $header = json_decode($header,true);


            if($params){
                //首先判断订单类型
                if(isset($params['ORDERID'])){ //paytm
                    //1,查询订单
                    $order = Db::name('pay_order')
                                ->alias('a')
                                ->join('channel_list b','a.channel_id = b.id','LEFT')
                                ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
                                ->where(['eshopno'=>$params['ORDERID']])
                                ->field('a.*,b.channel_key,c.merchant_key')
                                ->find();
                    // dump($order);exit;
                    if(!$order){
                        $this->exit_recrod('order not exist');
                    }

                    //检查订单是否完成，如果是成功订单就不往下走
                    if($order['status'] == 1){
                        $this->exit_recrod('order already success');
                    }

                    //2,校验Checksumhash
                    vendor('paytmchecksum.PaytmChecksum');
                    $ga = new \PaytmChecksum();
                    $checksumhash = isset($params['CHECKSUMHASH'])?$params['CHECKSUMHASH']:'';
                    $checkres = $ga::verifySignature($params, $order['channel_key'], $checksumhash);
                    if(!$checkres){
                        $this->exit_recrod('verify failed');
                    }

                    $data = [];
                    $data['tn'] = isset($params['TXNID'])?$params['TXNID']:'';
                    // $data['ext_data'] = $order['ext_data'] =  isset($params['BANKTXNID'])?$params['BANKTXNID']:''; //BANKTXNID
                    $data['callback_time'] = $order['callback_time'] = isset($params['TXNDATE'])?strtotime($params['TXNDATE']):time();
                    if($params['STATUS'] == 'TXN_SUCCESS'){
                        //交易成功
                        $data['status'] = 1;
                    }else if($params['STATUS'] == 'TXN_FAILURE'){
                        //交易失败
                        $data['status'] = 2;
                    }else if($params['STATUS'] == 'PENDING'){
                        //交易进行
                        $data['status'] = 0;
                    }

                    //3,修改订单状态，存入上游订单号
                    Db::name('pay_order')->where(['eshopno'=>$params['ORDERID']])->update($data);

                    $model = model('PayOrder');
                    //4,交易成功，则回调给下游
                    if($order['notify_url']){
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

                    //5,跳转到指定页面
                    header('Location:'.$order['callback_url']);
                    exit;
                }elseif(isset($params['out_trade_no']) && isset($params['callbacks'])){ //麒麟
                    $orderno = $params['out_trade_no'];
                }elseif(isset($params['orderNo']) && isset($params['status'])){ //FastPay
                    $orderno = $params['orderNo'];
                }elseif(isset($params['resource'])){ //BZpay
                    //BZ pay
                    $resource = $params['resource'];
                    $orderno = $resource['outTradeNo']; //商户自定义订单号
                    $tradeNo = $resource['tradeNo']; //订单号
                    $fee = $resource['serviceFee'];
                    $status = $resource['tradeStatus'];
                }elseif(isset($params['out_trade_no']) && isset($params['trade_no'])){ //DS || NDS
                    $orderno = $params['out_trade_no'];
                    $status = $params['trade_status'];
                }elseif(isset($params['orderNo']) && isset($params['tradeResult']) && in_array($ip, CommonPayment::getStaticValue($channelType = 'wepay',$type = 'getAllowIp'))){ //Wepay
                    //wepay
                    $orderno = $params['mchOrderNo'];
                    $status = $params['tradeResult'];
                }elseif(isset($params['orderNo']) && isset($params['tradeResult']) && in_array($ip, CommonPayment::getStaticValue($channelType = 'wowpay',$type = 'getAllowIp'))){ //wowpay
                    //wowpay
                    $orderno = $params['mchOrderNo'];
                    $status = $params['tradeResult'];
                }elseif(isset($params['tradeId'])){ //world pay
                    $orderno = $params['orderId'];
                    $status = $params['orderStatus'];
                }elseif(isset($params['platOrderId'])){ //xdpay
                    $orderno = $params['orderId'];
                    $status = $params['status'];
                    $tradeNo = $params['platOrderId']; //订单号
                }

                $order = Db::name('pay_order')
                            ->alias('a')
                            ->join('channel_list b','a.channel_id = b.id','LEFT')
                            ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
                            ->where(['eshopno'=>$orderno])
                            ->field('a.*,b.channel_key,c.merchant_key,b.channel_type,c.merchant_billing_around')
                            ->find();
                // dump($order);exit;
                if(!$order){
                    $this->exit_recrod('order not exist');
                }

                //检查订单是否完成，如果是成功订单就不往下走
                if($order['status'] == 1){
                    $this->exit_recrod('order already success');
                }

                if(in_array($order['channel_type'],['Kirin','fastpay','worldPay','sgpay'])){
                    //2,校验sign
                    $sign = Sign::verifySign($params,$order['channel_key']);
                }elseif($order['channel_type'] == 'bzpay'){
                    $sign = Sign::bzdecrypt($header,CommonPayment::getStaticValue($channelType = 'bzpay',$type = 'getscrect_key'));
                }elseif($order['channel_type'] == 'dspay' || $order['channel_type'] == 'NDSPAY' || $order['channel_type'] == 'NDSPAY_NEW'){
                    $cond = [
                        "pid" => $params['pid'],
                        "token" => $order['channel_key'],
                        "username" => 'One',
                        "money" => $params['money'],
                        "tid" => $params['out_trade_no'],
                        "sign" => $params['sign'],
                    ];

                    //校验Sign
                    $sign = Sign::verifyDsSign($cond,$order['channel_key']);
                }elseif($order['channel_type'] == 'wepay' || $order['channel_type'] == 'wowpay' || $order['channel_type'] == 'xdpay'){
                    unset($params['signType']);
                    //校验Sign
                    $sign = Sign::verifySign($params,$order['channel_key'],$isdecode = true,$isstrtoupper = false);
                }
                
                if(!$sign){
                    $this->exit_recrod('verify failed');
                }

                $data = [];

                if($order['channel_type'] == 'Kirin'){
                    if($params['callbacks'] == 'CODE_SUCCESS'){
                        //交易成功
                        $data['status'] = 1;
                    }else if($params['callbacks'] == 'CODE_FAILURE'){
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'fastpay'){
                     if($params['status'] == 1){
                        //交易成功
                        $data['status'] = 1;
                    }else if($params['status'] == 2){
                        //交易进行中
                        $data['status'] = 0;
                    }else if($params['status'] == 3){
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'bzpay'){
                    $data['rate_t_money'] = $fee;
                    $data['tn'] = $tradeNo;
                    if($status == 'SUCCESS'){
                        //交易成功
                        $data['status'] = 1;
                    }else if($status == 'PROCESSING'){
                        //交易进行中
                        $data['status'] = 0;
                    }else if($status == 'FAIL'){
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'dspay' || $order['channel_type'] == 'NDSPAY' || $order['channel_type'] == 'NDSPAY_NEW'){
                    if($status == 'SUCCESS'){
                        //交易成功
                        $data['status'] = 1;
                    }else if($status == 'ERROR'){
                        //交易失败
                        $data['status'] = 2;
                    }else{
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'wepay' || $order['channel_type'] == 'wowpay'){
                    if($status == 1){
                        //交易成功
                        $data['status'] = 1;
                    }else{
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'worldPay' || $order['channel_type'] == 'sgpay'){
                    if($status == 'success'){
                        //交易成功
                        $data['status'] = 1;
                    }else{
                        //交易失败
                        $data['status'] = 2;
                    }
                }elseif($order['channel_type'] == 'xdpay'){
                    $data['tn'] = $tradeNo;
                    if($status == 1){
                        //交易成功
                        $data['status'] = 1;
                    }elseif($status == 0){
                        $data['status'] = 0;
                    }else{
                        //交易失败
                        $data['status'] = 2;
                    }
                }

                $data['callback_time'] = $order['callback_time'] = time();

                //3,修改订单状态，存入上游订单号
                Db::name('pay_order')->where(['eshopno'=>$orderno])->update($data);

                $model = model('PayOrder');
                //判断如果是DO商户且交易成功的状态，就结算
                if($data['status'] == 1 && $order['merchant_billing_around'] == 'd0'){
                    $model->check_pay_order($order);
                }

                //4,交易成功，则回调给下游
                if($order['notify_url']){
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

                //5,返回字符串 success 
                $string = CommonPayment::return_string($order);
                $this->exit_recrod($string);

                // if($order['channel_type'] == 'bzpay' || $order['channel_type'] == 'worldPay'){
                //     $this->exit_recrod('ok');
                // }else{
                //     $this->exit_recrod('success');
                // }
            }else{
                $this->error('parameter is empty', [],  self::ONLY_POST);
                // $this->exit_recrod('parameter is empty');
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
        dump(CommonPayment::getStaticValue($channelType = 'wepay', $type = 'getAllowIp'));
        exit;
        $channel = \app\admin\model\ChannelList::where('id', 16)->find();
        // $cond['orderno'] = '72e33b0063bf451294b78d220d561d13';
        $orderinfo = Db::name('pay_order')->where('id',110)->find();
        $orderinfo['eshopno'] = Random::generateRandom();
        //$res = CommonPayment::paymethod($orderinfo,$channel);
        dump($res);exit;
    }

    /**
     * 返回可用的三方通道
     */
    public function channel()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                // Log::record('代收请求:'.json_encode($params),'notice');
                //数据格式验证
                $result = $this->validate($params,'app\admin\validate\Channel');
                if (true !== $result) {
                    // 验证失败 输出错误信息
                    $this->error($result, [],  self::PARMETR_NOT_EMPTY);
                }
                //商户搜索
                $row = model('\app\admin\model\Merchant')->where('merchant_number', $params['merchantNo'])->find();
                if(!$row){
                    $this->error('Merchant not exist', [],  self::MERCHANT_NOT_EXIST);
                }

                //通道类型  1-代收通道，2-代付通道
                $params['type'] = $params['type']??1;

                //IP判断
                if($row['use_ip']){
                    $iparr = explode('|',$row['use_ip']);
                    $reqip = request()->ip();
                    if(!in_array($reqip,$iparr)){
                        $this->error('Not reported IP', [],  self::NOT_REPORTED_IP);
                    }
                }

                // //签名验证
                $sign = Sign::verifySign($params,$row->merchant_key);
                if(!$sign){
                    $this->error('Signature verification failed', [],  self::SIGN_VERFY_FAID);
                }

                //查询可用通道
                $cond = [
                    'type'=> $params['type'],
                    'status'=> 1,
                ];
                $channel = model('\app\admin\model\ChannelList')->where($cond)->order('order_id desc')->select();
                // echo \app\admin\model\ChannelList::Getlastsql();exit;
                $list = [];
                if($channel){
                    foreach ($channel as $k => $v) {
                        $list[$k]['channel_id'] = $v['id'];
                        $list[$k]['channel_name'] = $v['channel_name'];
                    }
                }
                $data = [
                    "list"=> $list,
                ];

                // Log::record('返回:'.json_encode($data),'notice');
                $this->success('success',$data,200);
            }else{
                $this->error('Parameter can not be empty', [],  self::PARMETR_NOT_EMPTY);
            }
        }else{
            $this->error('only post', [],  self::ONLY_POST);
        }
    }
}
