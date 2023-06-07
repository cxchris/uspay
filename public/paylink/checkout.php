<?php

include 'lib/base.php';

class checkout extends base{
    public function __construct() {
        parent::__construct();
    }
    public function main($request){
        //获取mid，orderId，txnToken
        if($request){
            //解密数据
            $data = $this->decrypt($request['params'],$this->key);
            $data = json_decode($data,true);
            if(!$data){
                $this->error_html('data error');
            }else{
                //获取渠道

                $sql = 'SELECT channel_safe_url FROM yd_channel_list where channel_sign = "'.$data['mid']. '" limit 1;';
                $res = $this->mysql->find($sql);
                $data['url'] = $res?$res[0]['channel_safe_url']:$this->url;
                return $data;
            }
        }else{
            $this->error_html('params error');
        }
    }
}

$sys = new checkout();
$response = $sys->main($_REQUEST);


$actionurl = $response['url'].'/theia/api/v1/processTransaction?mid='.$response['mid'].'&orderId='.$response['orderId'];
// var_dump($actionurl);exit;
include_once('checkout.html');
?>