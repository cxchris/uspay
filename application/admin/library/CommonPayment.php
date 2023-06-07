<?php
/**
 * CommonPayment
 *
 * @cxq
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
use ReflectionClass;

/*interface PayChannel
{
    public static function pay($orderinfo, $channel);
    public static function df($orderinfo, $channel);
}*/

class CommonPayment {
	//渠道映射列表
	static $CHANNEL_LIST = [
		'Payment' => 'Paytm',
		'otc' => 'Otcpay',
		'cashfree' => 'Cashfree',
		'freepay' => 'Freepaytm',
		'payg' => 'PayGIntegration',
		'Kirin' => 'Kirin',
		'fastpay' => 'Fastpay',
		'bzpay' => 'Bzpay',
		'dspay' => 'Dspay',
		'wepay' => 'Wepay',
		'wowpay' => 'Wowpay',
		'NDSPAY' => 'Ndspay',
		'NDSPAY_NEW' => 'Nndspay',
		'worldPay' => 'WorldPay',
		'GlobalPay' => 'GlobalPay',
		'uzpay' => 'Uzpay',
		'xdpay' => 'Xdpay',
		'sgpay' => 'Sgpay',
		'one' => 'Otcpay',
	];
	
	/**
	 * 获取代收返回
	 * @param mixed $res
	 * @param mixed $type
	 * @return mixed
	 */
	public static function GetPayMap($res,$type){
		return true;
	}

	/**
	 * 获取代付返回
	 * @param mixed $res
	 * @param mixed $type
	 * @return mixed
	 */
	public static function GetApplyMap($data,$channelType) {
		$res = [];
        if($channelType == null){
        	return null;
        }
		$channel = new \stdClass();
		$channel->channel_type = $channelType;
		// dump($channel);exit;
		$reflector = self::getpaymentlibrary($channel);

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod('getmodifydata'); //获取代付修改数据
        $res = $reflectionMethod->invoke(null,$data);
    	return $res;



	    // $functions = [
	    //     'payg' => [
	    //         'rate_t_money' => $res['TotalFeeAmount']??null,
	    //         'tn' => $res['PayOutKeyId']??null,
	    //         'transactionId' => $res['TransactionId']??null,
	    //         'status' => $res['Status']??null,
	    //     ],
	    //     'freepay' => [
	    //         'rate_t_money' => isset($res['fee'])?sprintfnum($res['fee'] / 100):null,
	    //         'tn' => $res['agentpayOrderId']??null,
	    //         'status' => $res['status']??null,
	    //     ],
	    //     'Kirin' => [
	    //         'tn' => $res['data']['order_no']??null,
	    //         'status' => 1,
	    //     ],
	    //     'fastpay' => [
	    //         'tn' => $res['platformOrderNo']??null,
	    //         'status' => 1,
	    //     ],
	    //     'bzpay' => [
	    //         'tn' => $res['data']['tradeNo']??null,
	    //         'status' => 1,
	    //     ],
	    //     'dspay' => [
	    //         'tn' => $res['data']??null,
	    //         'status' => 1,
	    //     ],
	    //     'NDSPAY' => [
	    //         'tn' => $res['data']??null,
	    //         'status' => 1,
	    //     ],
	    //     'NDSPAY_NEW' => [
	    //         'tn' => $res['data']??null,
	    //         'status' => 1,
	    //     ],
	    //     'wepay' => [
	    //         'tn' => $res['tradeNo']??null,
	    //         'status' => 1,
	    //     ],
	    //     'wowpay' => [
	    //         'tn' => $res['tradeNo']??null,
	    //         'status' => 1,
	    //     ],
	    //     'worldPay' => [
	    //         'tn' => $res['data']['tradeId']??null,
	    //         'status' => 1,
	    //     ],
	    //     // ... other functions go here
	    // ];

		// return $functions[$type];
	}

	/*
	**策略模式-根据不同通道类型调用支付方法
	*/
	public static function create($channelType)
    {
        $className = self::$CHANNEL_LIST[$channelType];
		$channel = new ReflectionClass('\app\admin\library\\' . $className);
        return $channel;
    }

    /*
    * 公共方法，引入反射类
    */
    public static function getpaymentlibrary($channel)
    {
    	$channelType = $channel->channel_type;
        // dump($channelType);exit;
        $reflector = self::create($channelType);
        return $reflector;
    }


    /*
    * 公共方法，反射获取渠道支付方法-pay
    */
    public static function pay($reflector = null,$type = 'pay',$orderinfo,$channel)
    {
        $res = [];
        if($reflector == null){
        	return null;
        }

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod($type); //使用代付静态方法
        $res = $reflectionMethod->invoke(null, $orderinfo, $channel);
        // dump($res);
        return $res;
    }

    /*
    * 公共方法，反射获取渠道支付方法-dispose
    */
    public static function dispose($reflector = null,$bcadata = [],$orderinfo,$channel)
    {
        $res = [];
        if($reflector == null){
        	return null;
        }

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod('dispose'); //使用代付静态方法
        $res = $reflectionMethod->invoke(null, $bcadata, $orderinfo, $channel);
        // dump($res);
        return $res;
    }

    /**
	 * Summary of return_string
	 * @param mixed $params
	 * @param mixed $order
	 * @return mixed
	 */
	public static function return_string($order)
	{
		$channelType = $order['channel_type'];
		if($channelType == null){
        	return null;
        }
		$channel = new \stdClass();
		$channel->channel_type = $channelType;
		// dump($channel);exit;
		$reflector = self::getpaymentlibrary($channel);

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod('return_string'); //使用代付静态方法
        $res = $reflectionMethod->invoke(null);
    	return $res;
	}

	/*
    * 公共方法，反射获取静态变量-getAllowIp
    */
	public static function getStaticValue($channelType = 'Wepay',$type = 'getAllowIp'){
		$res = [];
        if($channelType == null){
        	return null;
        }
		$channel = new \stdClass();
		$channel->channel_type = $channelType;
		// dump($channel);exit;
		$reflector = self::getpaymentlibrary($channel);

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod($type); //使用代付静态方法
        $res = $reflectionMethod->invoke(null);
    	return $res;
    }

	/**
	 * Summary of obtainCallbackOrderId 获取回调的订单号
	 * @param mixed $params
	 * @return mixed
	 */
	public static function obtainCallbackOrderId($params)
	{
		if(isset($params['mchOrderNo'])){ //freepay
			$orderno = $params['mchOrderNo'];
		}elseif(isset($params['out_trade_no'])){ //麒麟
			$orderno = $params['out_trade_no'];
		}elseif(isset($params['orderNo'])){ // fastpay
			$orderno = $params['orderNo'];
		}elseif(isset($params['resource'])){ //BZ pay
			$orderno = $params['resource']['outTradeNo'];
		}elseif(isset($params['merTransferId'])){ //Wepay,Wowpay
			$orderno = $params['merTransferId'];
		}elseif(isset($params['orderId'])){ //worldpay,xdpay,sgpay
			$orderno = $params['orderId'];
		}elseif(isset($params['ORDERID'])){ //paytm
			$orderno = $params['ORDERID'];
		}else{
			$orderno = 0;
		}
		return $orderno;
	}
	
	/**
	 * Summary of checkTheSign
	 * @param mixed $params
	 * @param mixed $order
	 * @return mixed
	 */
	public static function checkTheSign($params,$order)
	{
		$channelType = $order['channel_type'];
		if($channelType == null){
        	return null;
        }
		$channel = new \stdClass();
		$channel->channel_type = $channelType;
		// dump($channel);exit;
		$reflector = self::getpaymentlibrary($channel);

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod('checkCallbackSign'); //使用代付静态方法
        $res = $reflectionMethod->invoke(null,$params,$order);
    	return $res;
	}

	/**
	 * Summary of checkAndObtainARecoveryState
	 * @param mixed $params
	 * @param mixed $order
	 * @return mixed
	 */
	public static function checkAndObtainARecoveryState($params,$order){
		$channelType = $order['channel_type'];
		if($channelType == null){
        	return null;
        }
		$channel = new \stdClass();
		$channel->channel_type = $channelType;
		// dump($channel);exit;
		$reflector = self::getpaymentlibrary($channel);

        // $instance = $reflector->newInstance();
        $reflectionMethod = $reflector->getMethod('checkAndObtainARecoveryState'); //使用代付静态方法
        $res = $reflectionMethod->invoke(null,$params,$order);
    	return $res;
	}
	
}