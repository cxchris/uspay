<?php
/**
 * Xdpay Xdpay Controller
 *
 * Xdpay
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

class Xdpay{
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = '200';
    protected static $_resultStatus = 1;
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $fail_code = '500'; //返回失败码
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
			"merchant" => $channel['channel_sign'],
			"payCode" => $channel['channel_pay_type'],
			"amount" => sprintf("%.2f",$orderinfo['money']),
			"orderId" => $orderinfo['eshopno'],
			"notifyUrl" => $orderinfo['t_notify_url'],
			// "sign" => "",
		);

		// $arrData = '{"amount": "100","ext": "你的传透参数","merchantNo": "1178632860589223937","notifyUrl": "http://localhost/okex-admin/merchant/simulatormerchantorder/orderNotifyV2","orderNo": "1399327655118245890","type": 8,"userName": "paul","version": "2.0.0"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '9656c0292f081a08a16a4b7475a8a5e5';


		$sign = Sign::getSign($arrData,$channel['channel_key'],true,false);
		$arrData['sign'] = $sign;
		// $arrData['type'] = 1;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		// dump($arrData);
		$arrData = http_build_query($arrData);
		// dump($arrData);
		// $arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/collect/create';

		// dump($url);
		$res = Http::post($url, $arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['code']) && $ret['code'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['msg']);
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

		
		$sign = Sign::getSign($arrData,$channel['channel_key'],true,false);
		$arrData['sign'] = $sign;

		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/api/v2.pay/findOrder';

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
		// echo 1;exit;
		$arrData = array(
			'merchant' => $channel['channel_sign'],
			'payCode' => '13702',
			"amount" => sprintf("%.2f",$orderinfo['money']),
			"orderId" => $orderinfo['orderno'],
			"notifyUrl" => $orderinfo['t_notify_url'],
			"customName" => $orderinfo['accountName'],
			// "bankname" => $orderinfo['bankName'],
			"bankAccount" => $orderinfo['accountNo'],
			"remark" => $orderinfo['bankCode'],
		);;

		
		$sign = Sign::getSign($arrData,$channel['channel_key'],true,false);
		$arrData['sign'] = $sign;
		// dump($arrData);exit;
		
		$arrData = http_build_query($arrData);
        $url = $channel['channel_safe_url'].'/pay/create';
		

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

	/*
	* 检查账户余额
	*/
	public static function CheckBalance($channel)
	{
		$arrData  = array(
			'merchantNo' => $channel['channel_sign'],
		);

		
		$sign = Sign::getSign($arrData,$channel['channel_key'],true,false);
		$arrData['sign'] = $sign;
		$arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);

		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/okex-admin/okex/api/v2/balance';

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

	//后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => null,
            'url' => $res['data']['url'],
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
			'tn' => $res['data']['platOrderId'] ?? null,
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
		$cond = $params;
		$res = Sign::verifySign($cond,$order['channel_key'],$isdecode = true,$isstrtoupper = false);
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
        if($params['status'] == 1){
			//转账成功
			$data['status'] = 2;
		}elseif($params['status'] == 0){
			//转账处理中
			$data['status'] = 1;
		}else{
			//交易失败
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