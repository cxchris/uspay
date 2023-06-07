<?php
/**
 * fastpay Fastpay Controller
 *
 * fastpay
 * 
 */
namespace app\admin\library;
use app\admin\model\Admin;
use fast\Random;
use fast\Http;
use fast\Sign;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Request;
use think\Log;

class GlobalPay {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 'SUCCESS';
    protected static $_resultStatus = 1;
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $fail_code = '500'; //返回失败码
    protected static $websiteName = 'ydapppay';
    protected static $currency = 'INR';
    protected static $channel_safe_url = 'https://api.cashfree.com/pg';
    protected static $allowIp = ['8.219.155.207'];
	
    public static function getAllowIp(){
    	return self::$allowIp;
    }

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
			"mer_no" => $channel['channel_sign'],
			"mer_order_no" => $orderinfo['eshopno'],
			"order_amount" => sprintf("%.2f",$orderinfo['money']),
			"notifyUrl" => $orderinfo['t_notify_url'],
			"pageUrl" => $orderinfo['callback_url'],
			"pname" => $orderinfo['customer_name'],
			"phone" => substr($orderinfo['customer_mobile'],1),
			"pemail" => $orderinfo['customer_email'],
			"goods" => 'test',
			"ccy_no" => "INR",
			"busi_code" => "100303",
		);

		// $arrData = '{"goods_name": "test","mch_id": "123456789","mch_order_no": "2021-04-13 17:32:28","notify_url": "http://www.baidu.com/notify_url.jsp","order_date": "2021-04-13 17:32:25","pay_type": 122,"trade_amount": "100"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '0936D7E86164C2D53C8FF8AD06ED6D09';


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		// dump($arrData);
		
		dump($arrData);
		$arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/ty/orderPay';

		dump($url);
		$res = Http::post($url,$arrDatajson,[],3);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            dump($ret);exit;
            if($ret){
                if(isset($ret['status']) && $ret['status'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['err_msg']);
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
			'TradeNo' => $row['tn'],
		);


		$sign = Sign::getSign($arrData,$channel['channel_key'],$isdecode = true,$isstrtoupper = false);
		$arrData['sign'] = $sign;
		$arrData['sign_type'] = 'MD5';

		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/api/v2.pay/findOrder';

		// dump($url);
		$res = Http::wepayPost($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['err_msg']);
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
	}

	//创建代付订单
	public static function df($orderinfo,$channel) {
		$arrData = array(
			'mch_id' => $channel['channel_sign'],
			"bank_code" => 'IDPT0001',
			"transfer_amount" => (int)$orderinfo['money'],
			"receive_name" => $orderinfo['accountName'],
			"receive_account" => $orderinfo['accountNo'],
			"mch_transferId" => $orderinfo['orderno'],
			"remark" => $orderinfo['bankCode'], //isfc
			"back_url" => $orderinfo['t_notify_url'],
			"apply_date" => date('Y-m-d H:i:s',$orderinfo['create_time']),
		);


		$sign = Sign::getSign($arrData,$channel['channel_key'],$isdecode = true,$isstrtoupper = false);
		$arrData['sign'] = $sign;
		$arrData['sign_type'] = 'MD5';

		// dump($arrData);
		
		
        $url = $channel['channel_safe_url'].'/pay/transfer';
	

		// dump($url);
		$res = Http::wepayPost($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['err_msg']);
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }
	}

	/*
	* 查询代付订单
	*/
	public static function orderquery($orderinfo)
	{
		$arrData  = array(
			'TradeNo' => $orderinfo['orderno'],
		);


		$sign = Sign::getSign($arrData,$orderinfo['channel_key']);
		$arrData['sign'] = $sign;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);

		// dump($header);
		
		// dump($arrData);
        $url = $orderinfo['channel_safe_url'].'/api/v2.pay/searchWithdraw';

		// dump($url);
		$res = Http::wepayPost($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$fail_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['err_msg']);
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }

	}

	/*
	* 检查账户余额
	*/
	public static function CheckBalance($channel)
	{
		$arrData  = array(
			'merchantNo' => $channel['channel_sign'],
		);


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		$arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);

		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/okex-admin/okex/api/v2/balance';

		// dump($url);
		$res = Http::wepayPost($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['err_msg']);
                }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }

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