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

class Freepaytm
{
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 'S';
    protected static $_resultStatus = 'SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $websiteName = 'ydpay';
    protected static $currency = 'INR';
    protected static $error_code = '400'; //返回成功码


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
    public static function df($orderinfo,$channel){
        /*
        * import checksum generation utility
        * You can get this utility from https://developer.paytm.com/docs/checksum/
        */


        $paytmParams = array();

        $paytmParams = array(
            "mchId" => $channel['channel_sign'],
            "mchOrderNo" => $orderinfo['orderno'],
            "amount" => $orderinfo['money']*100,
            // 'accountAttr' => 0,
            "accountName" => $orderinfo['accountName'],
            "accountNo" => $orderinfo['accountNo'],
            "bankName" => $orderinfo['bankName'],
            "bankCode" => $orderinfo['bankCode'],
            "notifyUrl" => $orderinfo['t_notify_url'],
            "remark" => $orderinfo['remark'],
            "reqTime" => date('YmdHis',$orderinfo['create_time']),
        );


        
        $sign = Sign::getSign($paytmParams,$channel['channel_key'],true);
        $paytmParams['sign'] = $sign;
        // dump($paytmParams);

        /* for Production */
        $url = $channel['channel_safe_url'].'/api/agentpay/apply';

        $res = Http::post($url, $paytmParams, $options = [], 0);
        // dump($res);exit;

        if($res){
            $ret = json_decode($res,true);
            if($ret){
                if($ret['retCode'] != self::$_resultStatus){
                    return self::return_json([],self::$error_code,$ret['retMsg']);
                }else{
                    return self::return_json($ret,self::$success_code,'成功');
                }
            }else{
                return self::return_json([],self::$error_code,'Interface exception');
            }
        }else{
            return self::return_json([],self::$error_code,'Interface exception');
        }
    }

    /**
	 * Summary of getmodifydata 代付后续操作，返回修改数据
	 * @param mixed $res
	 * @return array
	 */
	public static function getmodifydata($res){
		$data = [
			'rate_t_money' => isset($res['fee'])?sprintfnum($res['fee'] / 100):null,
            'tn' => $res['agentpayOrderId']??null,
            'status' => $res['status']??null,
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
		$res = true;
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
        if($order['status'] == 2 || $order['status'] == 3){
            $msg = 'order finish';
			Log::record('callback:'.$msg,'notice');
			exit($msg);
        }

        if(isset($params['agentpayOrderId'])){
            $data['tn'] = $params['agentpayOrderId'];
        }

        if(isset($params['fee'])){
            $data['rate_t_money'] = sprintfnum($params['fee']/100);
        }

        $data['status'] = $params['status'];

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
