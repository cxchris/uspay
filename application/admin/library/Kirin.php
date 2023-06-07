<?php
/**
 * 麒麟支付 Kirin Controller
 *
 * 麒麟支付
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

class Kirin {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = '200';
    protected static $_resultStatus = 1;
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
			"appid" => $channel['channel_sign'],
			"pay_type" => "upi",
			"trade_type" => $channel['channel_pay_type'],
			"return_type" => "mobile",
			"amount" => sprintf("%.2f",$orderinfo['money']),
			// "out_uid" => "",
			// "out_payer" => "",
			"out_trade_no" => $orderinfo['eshopno'],
			"callback_url" => $orderinfo['t_notify_url'],
			"success_url" => $orderinfo['callback_url'],
			// "error_url" => "",
			"version" => "v2.0",
			// "sign" => "",
		);

		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		// dump($header);
		$header = [];
		
		// dump($arrData);
		// $arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/pay/unifiedorder?format=json';

		// dump($url);
		$res = Http::post($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['msg']);
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
			'appid' => $channel['channel_sign'],
			'out_trade_no' => $row['eshopno'],
		);


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		
		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/pay/orderquery';

		// dump($url);
		$res = Http::post($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['msg']);
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
		//判断类型
		if($orderinfo['channel_type'] == 'upi'){
			$type = 'UPI';
		}else{
			$type = 'IMPS';
		}

		$arrData = array(
			'appid' => $channel['channel_sign'],
			"money" => sprintf("%.2f",$orderinfo['money']),
			"account" => $orderinfo['accountNo'],
			"type" => $type,
			"ifsc_code" => $orderinfo['bankCode'],
			"name" => $orderinfo['accountName'],
			"out_trade_no" => $orderinfo['orderno'],
			"callback" => $orderinfo['t_notify_url'],
			// "remark" => "",
		);


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		
		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/mch/withdrawin';
	

		// dump($url);
		$res = Http::post($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_resultStatus){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['msg']);
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
		$arrData = array(
			"appid" => $orderinfo['channel_sign'],
			"order_no" => $orderinfo['orderno'],
		);


		$sign = Sign::getSign($arrData,$orderinfo['channel_key']);
		$arrData['sign'] = $sign;
		// dump($arrData);

		$url = $orderinfo['channel_safe_url'].'/mch/orderquery';
		// dump($url);

		$res = Http::post($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_resultStatus){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['msg']);
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
		self::$MerchantKeyId = $channel['channel_sign'];
		$header = array(
		    'Content-Type: application/json',
		    'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
			);
		
		
		$arrData = array(

			"Merchantkeyid" => self::$MerchantKeyId,
			"PayoutAccountKeyId" => self::$PayoutAccountKeyId,
		);

		dump($arrData);

		$arrDatajson = json_encode($arrData);
		$url = self::$payoutURL.'/CheckBalance';
		dump($url);

		$res = Http::payGsendPost($url,$arrDatajson,$header);
		dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],$ret['code'],$ret['msg']);
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
            'tn' => $res['data']['order_no'],
            'url' => $res['url'],
        ];

        return $data;
    }

	/**
	 * Summary of getmodifydata 代付后续操作，返回修改数据
	 * @param mixed $res
	 * @return array
	 */
	public static function getmodifydata($res){
		$data = [
			'tn' => $res['data']['order_no']??null,
			'status' => 1,
		];

        return $data;
    }

	/**
	 * Summary of checkCallbackSign 验证sign
	 * @param mixed $params
	 * @param mixed $order
	 * @return bool
	 */
	public static function checkCallbackSign($params,$order){
		$res = Sign::KilinverifySign($params,$order['channel_key']);
		//$res = true;
		return $res;
	}

	/**
	 * Summary of checkAndObtainARecoveryState 查看狀態
	 * @param mixed $params
	 * @param mixed $order
	 * @return array
	 */
	public static function checkAndObtainARecoveryState($params,$order){
		$data = [];
		if($order['status'] == 2){
			$msg = 'order finish';
			Log::record('callback:'.$msg,'notice');
			exit($msg);
		}

		if(isset($params['order_no'])){
			$data['tn'] = $params['order_no'];
		}

		if(isset($params['fees'])){
			$data['rate_t_money'] = sprintfnum($params['fees']);
		}
		//判断状态
		if($params['code'] == 'CODE_FINISHED'){
			$data['status'] = 2;
		}else{
			$data['status'] = 3;
		}

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