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

class Wepay {
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
    protected static $allowIp = ['54.251.1.55','54.169.32.94'];
	
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
			"mch_id" => $channel['channel_sign'],
			"goods_name" => 'test',
			"mch_order_no" => $orderinfo['eshopno'],
			"mch_return_msg" => 'test',
			"notify_url" => $orderinfo['t_notify_url'],
			"order_date" => date('Y-m-d H:i:s',$orderinfo['create_time']),
			"page_url" => $orderinfo['callback_url'],
			"pay_type" => $channel['channel_pay_type'], //UPI原生一类=102 Paytm原生一类 =101 Paytm*XJD=161 UPI原生专户=172
			// "sign_type" => 'MD5',
			"trade_amount" => sprintf("%.2f",$orderinfo['money']),
			"version" => '1.0',
			// "sign" => "",
		);


		// $arrData = '{"goods_name": "test","mch_id": "123456789","mch_order_no": "2021-04-13 17:32:28","notify_url": "http://www.baidu.com/notify_url.jsp","order_date": "2021-04-13 17:32:25","pay_type": 122,"trade_amount": "100"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '0936D7E86164C2D53C8FF8AD06ED6D09';


		$sign = Sign::getSign($arrData,$channel['channel_key'],$isdecode = true,$isstrtoupper = false);
		$arrData['sign'] = $sign;
		$arrData['sign_type'] = 'MD5';
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		// dump($arrData);
		
		// dump($arrData);
		// $arrDatajson = json_encode($arrData);
        $url = $channel['channel_safe_url'].'/pay/web';

		// dump($url);
		$res = Http::wepayPost($url,$arrData);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['respCode']) && $ret['respCode'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['tradeMsg']);
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
                if(isset($ret['respCode']) && $ret['respCode'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['tradeMsg']);
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
                if(isset($ret['respCode']) && $ret['respCode'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['errorMsg']);
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
                if(isset($ret['respCode']) && $ret['respCode'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['tradeMsg']);
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
                if(isset($ret['respCode']) && $ret['respCode'] == self::$_success){
                    return self::return_json($ret,self::$success_code,'成功');
                }else{
                    return self::return_json([],self::$fail_code,$ret['tradeMsg']);
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
            'tn' => $res['orderNo'],
            'url' => $res['payInfo'],
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
			'tn' => $res['tradeNo']??null,
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
		unset($cond['signType']);
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
        // 上游状态，交易状态 SUCCESS或者ERROR
		// 上游状态，-1-未返回请求失败-,0-待处理,1-处理中,2-成功,3-失败',
		if($params['tradeResult'] == 0){
			//申请成功
			$data['status'] = 0;
		}else if($params['tradeResult'] == 1){
			//转账成功
			$data['status'] = 2;
		}else if($params['tradeResult'] == 2){
			//转账失败
			$data['status'] = 3;
		}else if($params['tradeResult'] == 3){
			//转账拒绝
			$data['status'] = 3;
		}else if($params['tradeResult'] == 4){
			//转账处理中
			$data['status'] = 1;
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