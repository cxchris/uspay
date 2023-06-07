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
use think\Db;

class WorldPay {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = '1000';
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
		return 'ok';
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
			"pay_memberid" => $channel['channel_sign'],
			"pay_orderid" => $orderinfo['eshopno'],
			"pay_type" => $channel['channel_pay_type'],
			"pay_amount" => sprintf("%.2f",$orderinfo['money']),
			"pay_applytime" => $orderinfo['create_time'],
			"pay_notifyurl" => $orderinfo['t_notify_url'],
			"pay_returnurl" => $orderinfo['callback_url'],
			"pay_name" => $orderinfo['customer_name'],
			"pay_mobile" => substr($orderinfo['customer_mobile'],1),
			"pay_email" => $orderinfo['customer_email'],
		);

		// $arrData = '{"goods_name": "test","mch_id": "123456789","mch_order_no": "2021-04-13 17:32:28","notify_url": "http://www.baidu.com/notify_url.jsp","order_date": "2021-04-13 17:32:25","pay_type": 122,"trade_amount": "100"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '0936D7E86164C2D53C8FF8AD06ED6D09';


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['pay_sign'] = $sign;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		// dump($arrData);
		
		// dump($arrData);
		// $arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/pay';

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


		$sign = Sign::getSign($arrData,$channel['channel_key'],$isdecode = true,$isstrtoupper = false);
		$arrData['sign'] = $sign;
		$arrData['sign_type'] = 'MD5';

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
                    return self::return_json([],self::$fail_code,$ret['msg']);
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
		$custom = Db::name('customer')->orderRaw('rand()')->find();
        $orderinfo['customer_email'] = $custom['email'];
        $orderinfo['customer_mobile'] = substr($custom['mobile'],1);

		$arrData = array(
			'pay_memberid' => $channel['channel_sign'],
			"pay_orderid" => $orderinfo['orderno'],
			"model" => 'IMPS',
			"pay_amount" => $orderinfo['money'],
			"pay_applytime" => time(),
			"pay_notifyurl" => $orderinfo['t_notify_url'],
			"pay_name" => $orderinfo['accountName'],
			"pay_mobile" => $orderinfo['customer_mobile'],
			"pay_email" => $orderinfo['customer_email'],
			"account_type" => 'bank_account',
			"account_number" => $orderinfo['accountNo'],
			"ifsc" => $orderinfo['bankCode'], //isfc
			"vpa" => "",
		);


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['pay_sign'] = $sign;

		// dump($arrData);exit;
		
		
		Log::record('WorldPay request:'.json_encode($arrData),'notice');
        $url = $channel['channel_safe_url'].'/payout/create';
	

		// dump($url);
		$res = Http::post($url,$arrData);
		
		Log::record('WorldPay return:'.$res,'notice');
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
                    return self::return_json($ret,self::$fail_code,'成功');
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
		$res = Http::wepayPost($url,$arrData);
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

	//后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => $res['data']['transaction_id'],
            'url' => $res['data']['pay_url'],
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
			'tn' => $res['data']['tradeId']??null,
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
		$res = Sign::verifySign($params,$order['channel_key']);
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
        if($params['orderStatus'] == 'success'){
			//转账成功
			$data['status'] = 2;
		}elseif($params['orderStatus'] == 'pending' || $params['orderStatus'] == 'processing'){
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