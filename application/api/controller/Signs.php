<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Session;
use think\Db;
use think\Validate;
use fast\Http;
use fast\Random;
use fast\Sign;
use think\Log;

/**
 * 获取系统签名字符串
 */
class Signs extends Api
{

   
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    public function get(){
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $merchant = Db::name('merchant')->where('merchant_number',$params['merchantNo'])->find();
                unset($params['sign']);
                // dump($merchant['merchant_key']);exit;
                $sign = Sign::getSign($params,$merchant['merchant_key']);
                exit($sign);
            }
        }
    }

    //cfemail加密
    public function getcfemail(){
        echo $this->encodeEmail('taimoaugmented@g');
    }

    //get处理
    public function geturl(){
        $url = 'https://www.icicibank.com/';
        $res = file_get_contents($url);
        dump($res);exit;
    }

    protected function encodeEmail($email, $key=0) {
        $chars = str_split($email);
        $string = '';
        $key = $key ? $key : rand(10, 99);
        foreach ($chars as $value) {
            $string .= sprintf("%02s", dechex(ord($value)^$key));
        }
        return dechex($key).$string;
    }

    protected function deCFEmail($encode){
        $k = hexdec(substr($encode,0,2));
        for($i=2, $m=''; $i < strlen($encode) - 1; $i += 2){
            $m.=chr(hexdec(substr($encode, $i, 2))^$k);
        }
        return $m;
    }

    
}
