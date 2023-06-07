<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;

class OtcList extends Model
{
    protected $name = 'otc_list';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $updateTime = '';


    /*
    * 获取统计数据
    */
    public function getpaydata($id = 0,$merchant_number = '',$starttime,$endtime,$datetime){

        // 代收金额,代收手续费
        $where = [
            'status' => 1,
        ];
        if($id != 0){
            $where['merchant_number'] = $merchant_number;
        }

        $field = 'sum(money) AS money,sum(account_money) AS account_money, sum(rate_money) AS rate_money';
        
        $res = $this
        ->where('create_time', 'between time', [$starttime, $endtime])
        ->where($where)
        ->field($field)
        ->select();

        //代收未结算
        $where['is_billing'] = 0;
        $nobilling = $this
        ->where('create_time', 'between time', [$starttime, $endtime])
        ->where($where)
        ->sum('account_money');

        $data = [
            'merchant_id' => $id,
            'datetime' => $datetime,
            'amount' => $res[0]['money']??0,
            'amount_tax' => $res[0]['rate_money']??0,
            'amount_check' => $res[0]['account_money']??0,
            'amount_settlement' => $nobilling??0,
        ];

        return $data;
    }
}
