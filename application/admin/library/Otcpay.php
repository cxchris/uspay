<?php

namespace app\admin\library;

use app\admin\model\Admin;
use fast\Random;
use fast\Http;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Db;
use think\Request;
use think\Log;

class Otcpay
{
    protected $_error = '';
    protected $requestUri = '';
    protected $orderinfo;
    protected static $_success = 'S';
    protected static $_resultStatus = 'SUCCESS';
    protected static $detail_success = 'TXN_SUCCESS';
    protected static $success_code = '0000'; //返回成功码
    protected static $websiteName = 'ydapppay';
    protected static $currency = 'INR';
    protected static $amount = 0;


    public function __construct()
    {

    }

    //返回的字符串
    public static function return_string(){
        return 'success';
    }

    /*
    * 发起交易，生成order
    */
    public static function pay($amount,$channel){
        // dump($channel);
        // dump($amount);exit;
        self::$amount = $amount;
        //获取可用的otc_list，个卡
        // $type = $pay_type == 'bank'?2:1;
        $field['id'] = ['in',$channel];
        $list = Db::name('otc_list')->where($field)->where('status',1)->select();
        // dump($list);
        if(!$list){
            return false;
        }
        $card = self::getuseotc($list);


        //生成浮动金额，-0.01 - +0.99
        if($card){
            if($card['isfloat'] == 1){
                $a = rand(1,99)/100;
                $s = rand(0,1);
                $amount = $s == 0?$amount-$a:$amount+$a;
                // dump($amount);exit;
            }
            $card['amount'] = $amount;
            // $card['orderno'] = $orderno;
            $card['code'] = self::$success_code;
        }else{
            $card['code'] = 400;
        }

        $card['data'] = [];
        return $card;
    }

    public static function df($orderinfo,$channel){
        return [
            'code' => '0000',
            'msg' => 'success',
            'data' => []
        ];
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

    //从列表中获取一个用做付款
    public static function getuseotc($list){
        $timearr = [
            strtotime(date('Y-m-d'). '00:00:00'),
            strtotime(date('Y-m-d'). '23:59:59'),
        ];

        $card = [];
        // dump($list);exit;
        if($list){
            foreach ($list as $k => $v) {
                //首先判断每日限额,去除限额的卡
                $cond = [
                    'status'=>1,
                    'otc_id'=>$v['id'],
                    'create_time' => ['between',[$timearr[0],$timearr[1]]]
                ];
                $total = Db::name('pay_order')->where($cond)->sum('money');
                // echo Db::name('pay_order')->getLastsql();exit;
                if(self::$amount + $total <= $v['day_limit']){
                    $card[] = $v;
                }
            }
        }
        //去除后随机取一个值
        $res = $card[array_rand($card)];
        return $res;
    }

    //后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => null,
            'url' => $channel->link.'?orderno='.$orderinfo['eshopno'].'&lang=tc-cn',
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
