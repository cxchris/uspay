<?php
/**
 * PayGIntegration Base Controller
 *
 * Class PayGIntegration
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

class PayGIntegration {
	/** @var string This Request URL */
	protected static  $paymentURL = 'https://paygapi.payg.in/payment/api/order';
	/** @var string This Request URL */ 
	protected static  $payoutURL = 'https://paygapi.payg.in/payment/api/Payout'; 

	/** @var string AuthenticationKey For Payment Provided By Gateway */
	protected static  $AuthenticationKey = 'f8a8c546a4a4449593a99a6b32930c0c'; 
	/** @var string AuthenticationToken For Payment Provided By Gateway */
	protected static  $AuthenticationToken  ='774a4b5e0d004a36880e0e9dafecc305';
	/** @var  string SecureHashKey For Payment Provided By Gateway */
	protected static  $SecureHashKey  = '24fc8ab0e59c4886b1fe084cfca0cae0';
	/** @var  string MerchantKeyId For Payment Provided By Gateway. */
	protected static  $MerchantKeyId = '23794';
	protected static  $PayoutAccountKeyId = 'XSSC000000023794';
 	/** @var  string RedirectUrl For CallBack Url. */
	protected static  $RedirectUrl = 'https://a2zfame.com';
	/** @var  Integer Time Out For Curl Session. */
	protected static $timeout = 30;
    protected static $success_code = '0000'; //返回成功码
    protected static $_success = 'S';
	
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

	public static function pay($postdata,$channel) {
		/**
 			* Set Form data in array to pass in request
 		*/
 		self::$MerchantKeyId = $channel['channel_sign'];
		// dump($postdata);exit;
		$arrData  = array(
			'Merchantkeyid' => self::$MerchantKeyId,
			'UniqueRequestId'=>$postdata['eshopno'],
			'UserDefinedData'=>array('UserDefined1' =>''),
		 	'IntegrationData'=> array('UserName' => 'RAMESHVAR','Source'=>'3213','IntegrationType'=>'11','HashData'=>'','PlatformId'=>'1' ),
		 	'RequestDateTime'=> date('mdY'),
			'RedirectUrl' => $postdata['callback_url'],
			'TransactionData'=> array(
				// 'AcceptedPaymentTypes' =>'' ,
				'PaymentType'=>'Wallet', 
				'WalletType' => 'PhonePe',
				// 'SurchargeType'=>'',
				// 'SurchargeValue'=>'',
				// 'RefTransactionId'=>'',
				// 'IndustrySpecificationCode'=>'',
				// 'PartialPaymentOption'=>''
			 ),
			'OrderAmount' => sprintf("%.2f",$postdata['money']),
			"OrderType" => "",
			"OrderAmountData" => [
				"AmountTypeDesc" => "3",
				"Amount" => "2"
			],
			"CustomerData" => [
				// "CustomerId" => "152433",
				// "CustomerNotes" => "amway product",
				// "FirstName" => "ravi",
				// "LastName" => "sharma",
				// "MobileNo" => "7337327109",
				// "Email" => "test@gmail.com",
				// "EmailReceipt" => "true",
				// "BillingAddress" => "44 bhagvan nagar",
				// "BillingCity" => "orissa",
				// "BillingState" => "orissa",
				// "BillingCountry" => "India",
				// "BillingZipCode" => "30202020",
				// "ShippingFirstName" => "ravi",
				// "ShippingLastName" => "sharma",
				// "ShippingAddress" => "44 bhagvan nagar",
				// "ShippingCity" => "orissa",
				// "ShippingState" => "orissa",
				// "ShippingCountry" => "India",
				// "ShippingZipCode" => "30202020",
				// "ShippingMobileNo" => "08619083450"
			],
			"ProductData" => "{'PaymentReason':'OnlineOrder for OrderNo- 1234', 'ItemId':'T-shirt', 'Size':'medium', 'AppName':'The One'}"

		);

		/**
 			* Set Form data in array to pass in request
 		*/
		foreach ($postdata as $key => $keyval) {
			if($key == 'CustomerData'){
				foreach ($keyval as $cust_key => $cust_keyval) {
					$arrData[$key][$cust_key] = $cust_keyval;
				}
			}
		/**
 			* Set Order Amount Data 
 		*/
			if($key == 'OrderAmountData'){
				foreach ($keyval as $orderamount_key => $orderamount_keyval) {
					$arrData[$key][$orderamount_key] = $orderamount_keyval;
				}
			}
		/**
 			* Set Integration data in array to pass in request
 		*/	
			if($key == 'IntegrationData'){
				foreach ($keyval as $integrationdata_key => $integrationdata_keyval) {
					$arrData[$key][$integrationdata_key] = $integrationdata_keyval;
				}
			}

			
			// $arrData[$key] = $keyval;
		}
		
		$header = array(
		    'Content-Type: application/json',
		    'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
		);

		// dump($header);
		
		//form data json encode 
		// dump($arrData);
		$arrDatajson = json_encode($arrData);
		$url = self::$paymentURL.'/create';

		$res = Http::payGsendPost($url,$arrDatajson,$header);
		

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                    return self::return_json([],$ret['Code'],$ret['Message']);
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

	public  function orderUpdate($postdata,$orderKeyID)
	{
		$arrData  = array(
			'Merchantkeyid' => self::$MerchantKeyId,
			'UniqueRequestId'=>PayGIntegration::generateRandomString(),
			'OrderKeyId'=>$orderKeyID,
			'UserDefinedData'=>array('UserDefined1' =>'' ),
		 	'IntegrationData'=> array('UserName' => 'JoeSmith','Source'=>'','IntegrationType'=>'','HashData'=>'','PlatformId'=>'' ),
		 	'RequestDateTime'=>'09212020',
			'RedirectUrl' => 'https://a2zfame.com',
			'TransactionData'=> array(
				'AcceptedPaymentTypes' =>'' ,
				'PaymentType'=>'',
				'SurchargeType'=>'',
				'SurchargeValue'=>'',
				'RefTransactionId'=>'',
				'IndustrySpecificationCode'=>'',
				'PartialPaymentOption'=>''
			 ),

		);
		/**
 			* Set Form data in array to pass in request
 		*/
		foreach ($postdata as $key => $keyval) {
			if($key == 'CustomerData'){
				foreach ($keyval as $cust_key => $cust_keyval) {
					$arrData[$key][$cust_key] = $cust_keyval;
				}
			}
			$arrData[$key] = $keyval;
		}
		$header = array(
			'Content-Type: application/json',
			'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
		);	
		
		
		
		$arrDatajson = json_encode($arrData);

		$url = self::$paymentURL.'/Update';

		$res = Http::payGsendPost($url,$arrDatajson,$header);
		

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                    return self::return_json([],$ret['Code'],$ret['Message']);
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

	public static function orderDetail($OrderKeyId = null,$PaymentType = '')
	{
		$header = array(
		    'Content-Type: application/json',
		    'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
			);
		
		$arrData = array('OrderKeyId' =>$OrderKeyId,'MerchantKeyId'=>self::$MerchantKeyId,'PaymentType'=>$PaymentType);

		$arrDatajson = json_encode($arrData);
		$url = self::$paymentURL.'/Detail';

        // dump($arrData);
        // dump($url);
		$res = Http::payGsendPost($url,$arrDatajson,$header);
		

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                    return self::return_json([],$ret['Code'],$ret['Message']);
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
                if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                    return self::return_json([],$ret['Code'],$ret['Message']);
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

	/*
	* 高级转账支付
	*/
	public static function df($orderinfo,$channel)
	{
 		self::$MerchantKeyId = $channel['channel_sign'];
		$header = array(
		    'Content-Type: application/json',
		    'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
			);
		
		$arrData = array(

			"MerchantKeyId" => self::$MerchantKeyId,
			"UniqueRequestId" => $orderinfo['orderno'],
			"PayOutType" => "Immediate",
			'TransactionType' => 'Debit',
			"PaymentType" => "IMPS",
			"Amount" => sprintf("%.2f",$orderinfo['money']),
			'PayOutBeneficiaryKeyId' => "",

			"PayoutCustomerkeyId" => "M23794CID87258",
			"BankName" => $orderinfo['bankName'],
			"BranchName" => $orderinfo['branchName'], //KOCHI，KERALA
			"BankCode" => $orderinfo['bankCode'],
			"AccountNumber" => $orderinfo['accountNo'],
			"BankCountry" => "",
			"BeneficiaryName" => $orderinfo['accountName'],
			"ProductData" => "",
			"KycData" => "",
			"BeneficiaryVerification" => 0,
			"ProductData" => '{"PaymentReason":"OnlineOrder for OrderNo- 1234","ItemId":"T-shirt","Size":"medium", "AppName":"XYZApp"}'
		);

		// dump($arrData);

		$arrDatajson = json_encode($arrData);
		$url = self::$payoutURL.'/FundTransfer2';

		$res = Http::payGsendPost($url,$arrDatajson,$header);
		// dump($res);exit;

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
            	if(isset($ret[0])){
            		$ret = $ret[0];
            		if(isset($ret['Code']) ){
	                    return self::return_json([],$ret['Code'],$ret['Message']);
	                }
            	}else{
            		if(isset($ret['PayOutKeyId'])){
            			return self::return_json($ret,self::$success_code,'成功');
            		}else{
                		return self::return_json([],$ret['ResponseCode'],$ret['ResponseText']);
            		}
            	}
                // if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                //     return self::return_json([],$ret['Code'],$ret['Message']);
                // }else{
                //     return self::return_json($ret,self::$success_code,'成功');
                // }
            }else{
                return self::return_json([],'400','Interface exception');
            }
        }else{
            return self::return_json([],'400','Interface exception');
        }		
	}


	/*
	* Payout Transfer Status
	*/
	public static function FundStatus($tn,$transactionId){
		$header = array(
		    'Content-Type: application/json',
		    'Authorization: Basic '. base64_encode(self::$AuthenticationKey.":".self::$AuthenticationToken.":M:".self::$MerchantKeyId)
			);
		
		$arrData = array('TransactionId' =>$transactionId,'MerchantKeyId'=>self::$MerchantKeyId,'tn'=>$tn);

		$arrDatajson = json_encode($arrData);
		$url = self::$payoutURL.'/FundStatus';

        // dump($arrData);
        // dump($url);
		$res = Http::payGsendPost($url,$arrDatajson,$header);
		

		if($res){
            $ret = json_decode($res,true);
            // dump($ret);exit;
            if($ret){
                if(isset($ret['Code']) && !isset($ret['MerchantKeyId'])){
                    return self::return_json([],$ret['Code'],$ret['Message']);
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
			'tn' => $res['OrderKeyId'],
			'url' => $res['PaymentProcessUrl'],
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
			'rate_t_money' => $res['TotalFeeAmount']??null,
			'tn' => $res['PayOutKeyId']??null,
			'transactionId' => $res['TransactionId']??null,
			'status' => $res['Status']??null,
		];

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