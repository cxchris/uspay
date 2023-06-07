<?php
/**
 * Cashfree Base Controller
 *
 * Class Cashfree
 * 
 */
namespace app\admin\library;
use app\admin\model\Admin;
use fast\Random;
use fast\Http;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Request;
use think\Log;

class Cashfree {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 'S';
    protected static $_resultStatus = 'SUCCESS';
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $websiteName = 'ydapppay';
    protected static $currency = 'INR';
    protected static $channel_safe_url = 'https://api.cashfree.com/pg';
	
	//返回的字符串
	public static function return_string(){
		return 'success';
	}

	/**
	 * Sets Create new customer order for request
	 *
	 * @param PayGIntegration – <MerchantAuthenticationKey>:<MerchantAuthenticationToken>:M:<MerchantKeyId> in base64_encode.
	 * Required Fields 
	 *	Merchantkeyid integer (10) Id which is obtained on MerchantRegistration Mandatory
	 *	UniqueRequestId string(10) Unique Id generated for that particular Request Mandatory
	 *	OrderAmount decimal(18,2) Transaction Amount Mandatory
	 * @return Response Json Object With OrderKeyId and Payment Url
	 */

	public static function pay($orderinfo,$channel) {
		$arrData  = array(

			'order_id' => $orderinfo['eshopno'],
			'order_amount' => sprintf("%.2f",$orderinfo['money']),
			'order_currency' => self::$currency,
			'order_note' => 'Additional order info',
			'order_meta' => [
				'notify_url' => $orderinfo['t_notify_url']
			],
			'customer_details' => [
				"customer_id" => (string)$orderinfo['customer_uid'],
				"customer_email" => $orderinfo['customer_email'],
				"customer_phone" => substr($orderinfo['customer_mobile'],3),
			]
		);
		
		$header = array(
		    'Content-Type: application/json',
		    'x-api-version: 2022-01-01',
		    'x-client-id: '.$channel['channel_sign'],
		    'x-client-secret: '.$channel['channel_key'],
		);

		// dump($header);
		
		//form data json encode 
		// dump($arrData);
		$arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/orders';

		// dump($url);
		$res = Http::payGsendPost($url,$arrDatajson,$header);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && !isset($ret['order_token'])){
                    return self::return_json([],'777',$ret['message']);
                }else{
                    return self::return_json($ret,self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
		
	}

	//拉起深层链接
	public static function payload($order_token) {
		$arrData  = array(
			'order_token' => $order_token,
			'payment_method' => [
				'upi' => [
					'channel' => 'link'
				]
			],
		);
		
		$header = array(
		    'Content-Type: application/json',
		    'x-api-version: 2022-01-01',
		    // 'x-client-id: '.$channel['channel_sign'],
		    // 'x-client-secret: '.$channel['channel_key'],
		);

		// dump($header);
		
		//form data json encode 
		// dump($arrData);
		$arrDatajson = json_encode($arrData);
        $url = self::$channel_safe_url.'/orders/pay';

		// dump($url);
		$res = Http::payGsendPost($url,$arrDatajson,$header);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
        	Log::record('cashfree拉起深层链接:'.json_encode($res),'notice');
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code'])){
                    return self::return_json([],'777',$ret['message']);
                }else{
                    return self::return_json($ret,self::$success_code,'成功');
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
		
	}

	//获取订单详情
	public static function orderDetail($row,$channel) {
		
		$arrData  = array(

		);
		
		$header = array(
		    'Content-Type: application/json',
		    'x-api-version: 2022-01-01',
		    'x-client-id: '.$channel['channel_sign'],
		    'x-client-secret: '.$channel['channel_key'],
		);

		// dump($header);
		
		//form data json encode 
		// dump($arrData);
		$arrDatajson = json_encode($arrData);
		// https://sandbox.cashfree.com/pg/orders/order_271vWwzSQOHe01ZVXpEcguVxQSRqr
        $url = $channel['channel_safe_url'].'/orders/'.$row['eshopno'];
        // $url = 'https://api.cashfree.com/api/v2/orders/'.$row['eshopno'].'/status';

		// dump($url);
		$res = Http::payGsendPost($url,$arrDatajson,$header,'GET');
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && !isset($ret['order_id'])){
                    return self::return_json([],'777',$ret['message']);
                }else{
                    return self::return_json($ret,self::$success_code,'成功');
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

        $token = $res['order_token'];

        $token_params = [
            'orderId' => $orderinfo['eshopno'],
            'txnToken' => $token,
        ];
        $token_params = json_encode($token_params);

        // var_dump($token_params);exit;
        $payUrl = $channel->link.'?params='.urlencode(Sign::encrypt($token_params,$this->akey));

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