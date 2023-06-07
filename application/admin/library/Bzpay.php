<?php
/**
 * Bzpay Bzpay Controller
 *
 * Bzpay
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
use app\admin\library\CommonPayment;

class Bzpay {
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = '00000';
    protected static $_resultStatus = 1;
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $screct_key = '9BOLDK1SF2M4DP5IZH4DYVHDW3D5JZIV';
    protected static $success_code = '0000'; //返回成功码
    protected static $websiteName = 'ydapppay';
    protected static $currency = 'INR';
    protected static $channel_safe_url = 'https://api.cashfree.com/pg';
	
	public static function getscrect_key(){
		return self::$screct_key;
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
			// "merchantNo" => $channel['channel_sign'],
			"outTradeNo" => $orderinfo['eshopno'],
			"totalAmount" => sprintf("%.2f",$orderinfo['money']),
			"channelCode" => '601',
			"notifyUrl" => $orderinfo['t_notify_url'],
			"buyerId" => $orderinfo['customer_uid'],
			"payName" => $orderinfo['customer_name'],
			// "sign" => "",
		);

		// $arrData = '{"amount": "100","ext": "你的传透参数","merchantNo": "1178632860589223937","notifyUrl": "http://localhost/okex-admin/merchant/simulatormerchantorder/orderNotifyV2","orderNo": "1399327655118245890","type": 8,"userName": "paul","version": "2.0.0"}';
		// $arrData = json_decode($arrData,true);
		// $channel['channel_key'] = '9656c0292f081a08a16a4b7475a8a5e5';


		// $sign = Sign::getSign($arrData,$channel['channel_key']);
		// $arrData['sign'] = $sign;
		// dump($arrData);

		$arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/trade/v1/unifiedorder/legal';

		// dump($url);
		$arr_header = self::techeader($channel);

		// dump($arr_header);
		$res = Http::post($url,$arrData,[],3,$arr_header);
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
			// 'merchantNo' => $channel['channel_sign'],
			'outTradeNo' => $row['eshopno'],
		);


		// $sign = Sign::getSign($arrData,$channel['channel_key']);
		// $arrData['sign'] = $sign;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);

		// dump($header);
		
		// dump($arrData);
        $url = $channel['channel_safe_url'].'/trade/v1/unifiedorder/query';

		// dump($url);
		$arr_header = self::techeader($channel);

		// dump($arr_header);
		$res = Http::get($url,$arrData,[],3,$arr_header);
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
			"currency" => 'INR',
			"tradeAmount" => sprintf("%.2f",$orderinfo['money']),
			"outTradeNo" => $orderinfo['orderno'],
			"bankCardNo" => $orderinfo['accountNo'],
			"bankName" => $orderinfo['bankName'],
			"bankAccountName" => $orderinfo['accountName'],
			// "bankBranchName" => '',
			"bankNum" => $orderinfo['bankCode'],
			"bankType" => 0,
			"notifyUrl" => $orderinfo['t_notify_url'],
		);


		$sign = Sign::getSign($arrData,$channel['channel_key']);
		$arrData['sign'] = $sign;

		// dump($arrData);
		$arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);
		
		
        $url = $channel['channel_safe_url'].'/trade/v1/agentpay/legal';
	

		// dump($url);
		$arr_header = self::techeader($channel);

		// dump($arr_header);
		$res = Http::post($url,$arrData,[],3,$arr_header);
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
			'orderNo' => $orderinfo['orderno'],
		);


		// $sign = Sign::getSign($arrData,$orderinfo['channel_key']);
		// $arrData['sign'] = $sign;
		// $arrData = json_encode($arrData, JSON_UNESCAPED_SLASHES);

		// dump($header);
		
		// dump($arrData);
        $url = $orderinfo['channel_safe_url'].'/trade/v1/agentpay/legal/query';

		// dump($url);
		$arr_header = self::techeader($orderinfo);

		// dump($arr_header);
		$res = Http::get($url,$arrData,[],3,$arr_header);
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
		$arr_header = self::techeader($orderinfo);

		// dump($arr_header);
		$res = Http::post($url,$arrData,[],3,$arr_header);
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
	* 获取header
	*/
	public static function techeader($channel){
		// dump($url);
		$arr_header = [];
		$arr_header[] = "Content-Type:application/json";
		$arr_header[] = "X-Qu-Access-Key:".$channel['channel_key'];
		$arr_header[] = "X-Qu-Mid:".$channel['channel_sign'];
		$arr_header[] = "X-Qu-Nonce:".Random::getOrderSn(); //UUID
		$arr_header[] = "X-Qu-Signature-Method:".'HmacSHA256';
		$arr_header[] = "X-Qu-Timestamp:".time();
		$arr_header[] = "X-Qu-Signature-Version:v1.0";


		// dump($arr_header);exit;
		$sign = Sign::bzencrypt($arr_header,self::$screct_key);
		$arr_header[] = "X-Qu-Signature:".$sign;
		return $arr_header;
	}

	//后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => null,
            'url' => $res['data']['payUrl'],
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
			'tn' => $res['data']['tradeNo']??null,
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
		$header = getallheaders();
		// dump($header);
		// exit;
		Log::record('代付callback:POST header:'.json_encode($header),'notice');
		$res = Sign::bzdecrypt($header,CommonPayment::getStaticValue($channelType = 'bzpay',$type = 'getscrect_key'));
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

        // 上游状态，-1-未返回请求失败-,0-待处理,1-处理中,2-成功,3-失败
		// 交易状态 PROCESSING-进行中 SUCCESS-成功 FAIL-失败

		$resource = $params['resource'];
		if($resource['payStatus'] == 'SUCCESS'){
			//交易成功
			$data['status'] = 2;
		}else if($resource['payStatus'] == 'PROCESSING'){
			//交易进行中
			$data['status'] = 1;
		}else if($resource['payStatus'] == 'FAIL'){
			//交易失败
			$data['status'] = 3;
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