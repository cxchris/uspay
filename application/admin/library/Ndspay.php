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

class Ndspay {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 1;
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
			"pid" => $channel['channel_sign'],
			"token" => $channel['channel_key'],
			"username" => 'One',
			"money" => sprintf("%.2f",$orderinfo['money']),
			"tid" => $orderinfo['eshopno'],
			// "sign" => "",
		);

		// $arrData = '{"amount": "100","ext": "你的传透参数","merchantNo": "1178632860589223937","notifyUrl": "http://localhost/okex-admin/merchant/simulatormerchantorder/orderNotifyV2","orderNo": "1399327655118245890","type": 8,"userName": "paul","version": "2.0.0"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '9656c0292f081a08a16a4b7475a8a5e5';


		$sign = Sign::getDsSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		// $arrData['type'] = 1;
		$arrData['notifyurl'] = $orderinfo['t_notify_url'];
		$arrData['types'] = 1; //0 或者1 0十二码支付 1唤醒方式支付
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		// dump($arrData);
		
		// dump($arrData);
		// $arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/api/v2.pay/createOrder';

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


		$sign = Sign::getDsSign($arrData,$channel['channel_key']);
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
		$arrData = array(
			'pid' => $channel['channel_sign'],
			'username' => 'One',
			"money" => sprintf("%.2f",$orderinfo['money']),
			"tid" => $orderinfo['orderno'],
			"uname" => $orderinfo['accountName'],
			"bankname" => $orderinfo['bankName'],
			"cardbanck" => $orderinfo['accountNo'],
			"ifsc" => $orderinfo['bankCode'],
			"token" => $channel['channel_key'],
		);;


		$sign = Sign::getDsSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;
		$arrData['notifyurl'] = $orderinfo['t_notify_url'];
		unset($arrData['token']);
		// dump($arrData);exit;
		
		
        $url = $channel['channel_safe_url'].'/api/v2.pay/withdraw';
		

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


		$sign = Sign::getDsSign($arrData,$orderinfo['channel_key']);
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


		$sign = Sign::getSign($arrData,$channel['channel_key']);
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

	/**
	 * Summary of getmodifydata 代付后续操作，返回修改数据
	 * @param mixed $res
	 * @return array
	 */
	public static function getmodifydata($res){
		$data = [
			'tn' => $res['data']??null,
			'status' => 1,
		];

        return $data;
    }

	//后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => $res['data']['TradeNo'],
            'url' => $res['data']['payurl'],
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
		//验参所需参数
		$cond = [
			"pid" => $params['pid'],
			"username" => 'One',
			"money" => $params['money'],
			"tid" => $params['out_trade_no'],

			'cardbanck' => $order['accountNo'],
			'bankname' => $order['bankName'],
			'ifsc' => $order['bankCode'],
			'uname' => $order['accountName'],

			"token" => $order['channel_key'],
			"sign" => $params['sign'],
		];
		// dump($cond);exit;
		$res = Sign::verifyDsSign($cond,$order['channel_key']);
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
        // 上游状态，交易状态 SUCCESS或者ERROR
		if($params['trade_status'] == 'SUCCESS'){
			//交易成功
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