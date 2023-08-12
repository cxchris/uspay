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
        $field = [];
        $field['id'] = ['in',$channel['otc_channel']];
        $field['channel_id'] = $channel['channel'];
        $list = Db::name('otc_list')->where($field)->where('status',1)->select();
        // dump($list);exit;
        if(!$list){
            return false;
        }

        //判断如果是数字货币，就随机一个地址返回
        if($list[0]['type'] == 1){
            $card = self::getuseotc($list);
        }else{
            $card = self::getdc($list);
        }

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
        if(!$card){
            $res = [];
        }else{
            //去除后随机取一个值
            $res = $card[array_rand($card)];
        }
        
        return $res;
    }

    //去查询空余的数字货币地址
    public static function getdc($list){
        // 开启事务
        Db::startTrans();

        try {
            // 整体逻辑：选择可以使用的地址，选择后给该地址加锁，如果没有可以使用的地址了，就请求接口生成新的地址，实在没有就报错

            // 1. 查询trc20类型且未锁的地址
            $cond = [
                'type' => 1,
                'is_locked' => 0
            ];
            $res = model('DcType')->orderRaw('RAND()')->lock(true)->where($cond)->find();
            // echo model('DcType')->getLastsql();exit;

            if (!$res) {
                // 2. 请求接口生成新的地址，实在没有就报错

                // 抛出异常，回滚事务
                throw new \Exception('没有可用的数字货币地址');
            }

            // 3. 给该地址加入状态锁，不允许下次请求获得
            $updateData = [
                'is_locked' => 1,
                // 这里可以设置一些额外的字段，例如加锁的时间戳等
                'lock_time' => time()
            ];

            $updateRes = model('DcType')->where('id', $res['id'])->update($updateData);

            if (!$updateRes) {
                // 加锁失败，抛出异常，回滚事务
                throw new \Exception('加锁失败');
            }

            // 提交事务
            Db::commit();

            $data = [];

            //查询dc_type获取channelid
            $channel = model('DcList')->where('id', $res['dcid'])->find();
            $data['id'] = $res['id'];
            $data['channel_id'] = $channel['otcid'];
            $data['account_number'] = $res['address'];
            $data['isfloat'] = 0;
            // echo model('DcList')->getLastsql();exit;

            // 返回获取到的地址信息
            return $data;
        } catch (\Exception $e) {
            // 出现异常，回滚事务
            Db::rollback();

            // 可以在这里记录日志或其他处理
            // ...

            // 返回异常信息或错误码
            return null;
        }
    }

    //后续操作，返回tn和URL
    public static function dispose($res,$orderinfo,$channel){
        $data = [
            'tn' => null,
            'url' => $channel->link.'?orderno='.$orderinfo['eshopno'].'&lang=en',
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
