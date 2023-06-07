<?php

namespace app\admin\library;

use app\admin\model\Admin;
use fast\Random;
use fast\Http;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Request;
use think\Log;
use fast\Sign;

class Paytm
{
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 'S';
    protected static $_resultStatus = 'SUCCESS';
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $websiteName = 'ydapppay';
    protected static $currency = 'INR';
    protected $akey = 'qwerty';


    public function __construct()
    {

    }

    //返回的字符串
    public static function return_string(){
        return 'success';
    }
    /*
    * paytm发起交易
    */
    public static function pay($orderinfo,$channel){
        /*
        * import checksum generation utility
        * You can get this utility from https://developer.paytm.com/docs/checksum/
        */

        $paytmParams = array();

        $paytmParams["body"] = array(
            "requestType"   => $channel['channel_type'],
            "mid"           => $channel['channel_sign'],
            "websiteName"   => self::$websiteName,
            "orderId"       => $orderinfo['eshopno'],
            "callbackUrl"   => $orderinfo['t_notify_url'],
            // 'callbackUrl' => 'https://vegitown-api.vegitown.me/paytm/deposit/callback',
            "txnAmount"     => array(
                "value"     => sprintf("%.2f",$orderinfo['money']),
                "currency"  => self::$currency,
            ),
            "userInfo"      => array(
                // "custId"    => 'CUST_001',
                "custId"    => $orderinfo['customer_uid'],
                "mobile"    => substr($orderinfo['customer_mobile'],3),
                "email"     => $orderinfo['customer_email'],
            ),
        );

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();

        /*
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */
        // dump($channel['channel_key']);
        $checksum = $ga::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $channel['channel_key']);

        $paytmParams["head"] = array(
            "signature"    => $checksum,
        );

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        // $post_data = $paytmParams;

        /* for Staging */
        // $url = "https://securegw.paytm.in/theia/api/v1/initiateTransaction?mid=YOUR_MID_HERE&orderId=ORDERID_98765";

        /* for Production */
        $url = $channel['channel_safe_url'].'/theia/api/v1/initiateTransaction?mid='.$channel['channel_sign'].'&orderId='.$orderinfo['eshopno'];
        // dump($url);
        // dump(json_decode($post_data,true));

        Log::record('paytm-safe_url:'.$url,'notice');
        Log::record('paytm-body:'.$post_data,'notice');

        $res = Http::post($url, $post_data, $options = [], 1);
        // dump($res);exit;
        if($res){
            $ret = json_decode($res,true);
            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$_success){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMsg']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
    }

    /*
    * paytm Process Transaction API
    */
    public static function processTransaction($orderinfo,$token){
        $paytmParams = array();
        // dump($token);exit;

        $paytmParams["body"] = array(
            "requestType" => "NATIVE",
            "mid"         => $orderinfo['channel_sign'],
            "orderId"     => $orderinfo['eshopno'],
            "paymentMode" => "UPI_INTENT",
            "osType" => $orderinfo['osType'],
            "pspApp" => $orderinfo['osChannel'],
        );

        $paytmParams["head"] = array(
            "txnToken"    => $token
        );

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Production */
        $url = $orderinfo['channel_safe_url']."/theia/api/v1/processTransaction?mid=".$orderinfo['channel_sign']."&orderId=".$orderinfo['eshopno'];
        // dump($url);
        // dump(json_encode($paytmParams));
        $res = Http::post($url, $post_data, $options = [], 1);
        // dump($res);exit;
        if($res){
            $ret = json_decode($res,true);
            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$_success){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMsg']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','interface exception');
            }
        }else{
            return self::return_json([],'400','interface exception');
        }
    }

    /*
    * 获取UPI应用程序
    */
    public static function fetchUPI($orderinfo,$channel){
        $paytmParams = array();

        $paytmParams["body"] = array(
            "mid" => $channel['channel_sign']
        );

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();

        /*
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */
        $checksum = $ga::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $channel['channel_key']);

        $paytmParams["head"] = array(
            "tokenType" => "CHECKSUM",
            "token" => $checksum
        );

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);


        /* for Production */
        $url = "https://securegw.paytm.in/theia/api/v1/fetchUPIOptions?mid=".$channel['channel_sign']."&orderId=".$orderinfo['eshopno'];

        $res = Http::post($url, $post_data, $options = [], 1);
        if($res){
            $ret = json_decode($res,true);
            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$_success){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMsg']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
    }

    /*
    * paytm createQrcode
    */
    public static function createQrcode($orderinfo,$channel){
        $paytmParams = array();

        $paytmParams["body"] = array(
            "mid"           => $channel['channel_sign'],
            "orderId"       => $orderinfo['eshopno'],
            "amount"        => sprintf("%.2f",$orderinfo['money']),
            "businessType"  => "UPI_QR_CODE",
            "posId"         => "S12_123"
        );

        /*
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();

        $checksum = $ga::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $channel['channel_key']);

        $paytmParams["head"] = array(
            "clientId"          => 'C11',
            "version"           => 'v1',
            "signature"         => $checksum,
        );

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Staging */
        // $url = "https://securegw-stage.paytm.in/paymentservices/qr/create";

        /* for Production */
        $url = $channel['channel_safe_url']."/paymentservices/qr/create";
    
        $res = Http::post($url, $post_data, $options = [], 1);
        // dump($res);exit;
        if($res){
            $ret = json_decode($res,true);
            dump($ret);exit;
            dump(base64_decode($ret['body']['image']));exit;
            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$_resultStatus){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMsg']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }

    }

    /*
    * paytm paylink创建链接
    */
    public static function paylink($orderinfo,$channel){
        $paytmParams = array();

        $paytmParams["body"] = array(
            "mid"             => $channel['channel_sign'],
            "linkType"        => "GENERIC",
            "linkDescription" => "Test Payment",
            "linkName"        => "Test",
        );

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();

        /*
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */
        $checksum = $ga::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $channel['channel_key']);

        $paytmParams["head"] = array(
            "tokenType"       => "AES",
            "signature"       => $checksum
        );

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Staging */
        $url = $channel['channel_safe_url']."/link/create";

        $res = Http::post($url, $post_data, $options = [], 1);
        if($res){
            $ret = json_decode($res,true);

            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$_resultStatus){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMessage']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
    }

    /*
    * paytm 结算
    */
    public static function settlement($StartTime,$EndTime,$channel,$page = 1,$size = 20){
        $paytmParams = array();

        // $channel['channel_sign'] = 'MlwrXl42921921914328';
        // $channel['channel_key'] = 'hmfC5Ub9exClS07c';
        // $channel['channel_safe_url'] = 'https://securegw.paytm.in';



        // dump($EndTime);exit;
        $paytmParams["MID"]                   = $channel['channel_sign'];
        $paytmParams["utrProcessedStartTime"] = $StartTime; 
        $paytmParams["utrProcessedEndTime"] = $EndTime; 
        $paytmParams["pageNum"]               = $page;
        $paytmParams["pageSize"]              = $size;

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();

        /*
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */
        $checksum = $ga::generateSignature($paytmParams, $channel['channel_key']);

        $paytmParams['checksumHash'] = $checksum;

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Production */
        // $url = "https://securegw.paytm.in/merchant-settlement-service/settlement/list";

        $url = $channel['channel_safe_url']."/merchant-settlement-service/settlement/list";

        $res = Http::post($url, $post_data, $options = [], 1);

        if($res){
            $ret = json_decode($res,true);
            // Log::record('paytm-settlement:'.json_encode($ret),'notice');

            if($ret){
                if($ret['status'] != self::$_resultStatus){
                    return self::return_json([],$ret['status'],$ret['errorMessage']);
                }else{
                    return self::return_json($ret['settlementListResponse'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'4404','Interface exception');
            }
        }else{
            return self::return_json([],'4404','Interface exception');
        }
    }

    /*
    * paytm 更新交易状态 API
    */
    public static function updateTransactionstatus($orderinfo,$channel){
        /* initialize an array */
        $paytmParams = array();

        // $orderinfo['eshopno'] = 'YDHOSH5141057388012735973540';
        /* body parameters */
        $paytmParams["body"] = array(
            "mid" => $channel['channel_sign'],
            "orderId" => $orderinfo['eshopno'],
        );

        /**
        * Generate checksum by parameters we have in body
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */

        vendor('paytmchecksum.PaytmChecksum');
        $ga = new \PaytmChecksum();
        $checksum = $ga::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $channel['channel_key']);
        /* head parameters */
        $paytmParams["head"] = array(

            /* put generated checksum value here */
            "signature" => $checksum
        );

        /* prepare JSON string for request */
        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

        /* for Production */
        $url = $channel['channel_safe_url'].'/v3/order/status';
        // $url = "https://securegw-stage.paytm.in/v3/order/status";

        $res = Http::post($url, $post_data, $options = [], 1);
        // dump($res);exit;
        if($res){
            $ret = json_decode($res,true);
            // dump($res);exit;
            if($ret){
                if($ret['body']['resultInfo']['resultStatus'] != self::$detail_success){
                    return self::return_json([],$ret['body']['resultInfo']['resultCode'],$ret['body']['resultInfo']['resultMsg']);
                }else{
                    return self::return_json($ret['body'],self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }

    }

    //后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => null,
        ];

        //paylink
        if($channel->channel_pay_type == 'paylink'){
            $payUrl = $res['longUrl'];
        }elseif($channel->channel_pay_type == 'upi'){
            $token = $res['txnToken'];
            $token_params = [
                'orderId' => $orderinfo['eshopno'],
                'txnToken' => $token,
            ];
            $token_params = json_encode($token_params);
            // var_dump($token_params);exit;
            $payUrl = $channel->link.'?params='.urlencode(Sign::encrypt($token_params,$this->akey));

        }else{
            //成功的情况,拼接支付链接
            $token = $res['txnToken'];
            if($token){
                $token_params = [
                    'mid' => $channel->channel_sign,
                    'orderId' => $orderinfo['eshopno'],
                    'txnToken' => $token,
                ];
                $token_params = json_encode($token_params);
                Log::record('未加密数据:'.json_encode($token_params),'notice');
                Log::record('加密数据:'.Sign::encrypt($token_params,$this->akey),'notice');

                // var_dump($token_params);exit;
                $payUrl = $channel->link.'?params='.urlencode(Sign::encrypt($token_params,$this->akey));
            }else{
                exception('Token Unknow', 3030);
            }
        }

        $data['url'] = $payUrl;
        return $data;
    }

    /*
    * 返回数据格式
    */
    public static function return_json($data = [], $code = '0000', $msg = ''){
        $data = [
            'data' => $data,
            'code' => $code,
            'msg' => $msg,
        ];
        return $data;
    }
}
