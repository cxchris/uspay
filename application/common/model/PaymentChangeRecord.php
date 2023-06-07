<?php

namespace app\common\model;

use think\Model;
use think\Session;

class PaymentChangeRecord extends Model
{
    protected $name = 'payment_change_record';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = false;
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $update_time = 'update_time';

     /**
     * 通用添加方法
     */
    public function addrecord($data){
        $adddata = [
            'merchant_id' => $data['merchant_id'],
            'bef_amount' => $data['bef_amount'],
            'change_amount' => $data['change_amount'],
            'aft_amount' => $data['bef_amount'] + $data['change_amount'],
            'status' => 1,
            'create_time' => time(),
        ];

        if(isset($data['orderno'])){
            $adddata['orderno'] = $data['orderno'];
        }

        if(isset($data['remark'])){
            $adddata['remark'] = $data['remark'];
        }

        if(isset($data['type'])){
            $adddata['type'] = $data['type'];
        }

        if(isset($data['relation_id'])){
            $adddata['relation_id'] = $data['relation_id'];
        }
        
        // dump($adddata);
        $res = $this->create($adddata);
        // echo $this->getLastsql();
        return $res;
    }

    /**
     * 获取所有账变类型
     */
    public function typeslect($is_array = false){
        $result = [
            // 0 => '未知',
            1 => '人工调账',
            2 => '代付预扣',
            3 => '转入代付记录',
            4 => '代付预扣失败回退',
        ];

        if($is_array){
            return $result;
        }else{
            return json($result);
        }

    }

}
