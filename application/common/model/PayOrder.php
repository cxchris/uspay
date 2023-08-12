<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;
use app\admin\library\PayGIntegration;
use app\admin\library\Cashfree;
use app\admin\library\Kirin;
use app\admin\library\Fastpay;
use app\admin\library\Bzpay;
use app\admin\library\Dspay;

class PayOrder extends Model
{
    protected $name = 'pay_order';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $update_time = 'update_time';

    /*
    *修改订单状态
    */
    public function update_pay_order($id,$notify_status = 0){
        $res = self::where(array('id'=>$id))->update(['update_time'=>time(),'notify_status'=>$notify_status,'notify_number'=>['inc', 1]]);
        return $res;
    }

    /*
    * 获取回调给下游的数据
    */
    public function getCondItem($order,$status){
        $data = [];
        $data['merchantNo'] = $order['merchant_number'];
        $data['sn'] = $order['orderno'];
        $data['merchantSn'] = $order['out_trade_no'];
        $data['orderStatus'] = $status; //订单状态：0,待支付。1，支付成功
        // $data['notifyStatus'] = 1; //通知状态：0，未通知。1，已通知
        $data['payTime'] = $order['callback_time'];
        $data['createTime'] = $order['create_time'];
        $data['money'] = sprintf("%.2f",$order['money']);
        $data['tax'] = sprintf("%.2f",$order['rate_money']); //三方手续费
        $data['remark'] = $order['remark'];
        $data['ext_data'] = $order['ext_data'];
        $sign = Sign::getSign($data,$order['merchant_key']);
        $data['sign'] = $sign;
        dump($data);exit;

        return $data;
    }

    /*
    * 回调给下游方法
    */
    public function callbackMerchant($row,$status,$txnId = ''){
        $data = $this->getCondItem($row,$status);
        $url = $row['notify_url'];
        $id = $row['id'];
        // dump($status);exit;
        if($status != $row['status']){
            $cond = [];
            $cond['status'] = $status;
            if($txnId){
                $cond['tn'] = $txnId;
            }
            self::where(array('id'=>$id))->update($cond);

            //记录回调时间，回调次数
            try {
                $res = Http::post($url, $data, $options = []);
                Log::record('notify:通知参数'.json_encode($data),'notice');
                Log::record('notify:通知回答'.json_encode($res),'notice');
                if(!$res){
                    $this->update_pay_order($id,2);
                    exception('通知失败');
                }
            } catch (\Exception $e) {
                // $this->error($e->getMessage());
            }

            if($res){
                if($res == 'success'){
                    $this->update_pay_order($id,1);
                }else{
                    $this->update_pay_order($id,2);
                    // $this->error('通知失败');
                }
            }else{
                $this->update_pay_order($id,2);
                // $this->error('通知失败');
            }
        }
        
        return true;
    }


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

    /*
    * 结算方法
    */
    public function check_pay_order($v){
        Db::startTrans();
        try {
            // 1.修改结算状态，结算时间，和税款
            $cond = [];
            $cond['billing_time'] = time();
            $cond['is_billing'] = 1;
            if(isset($v['rate_t_money'])){
                $cond['rate_t_money'] = $v['rate_t_money']; //上级税款
            }

            $res1 = Db::name('pay_order')->where('id',$v['id'])->update($cond);
            if(!$res1){
                exception('修改结算状态失败');
            }

            // //2.将到账金额加给用商户的代收余额里
            $merchant = Db::name('merchant')->field('merchant_payment_amount')->where('merchant_number',$v['merchant_number'])->find(); //先查找商家前值

            $res2 = Db::name('merchant')
                ->where('merchant_number',$v['merchant_number'])
                ->update([
                    'merchant_payment_amount'=>['inc', $v['account_money']]
                ]);
            if(!$res2){
                exception('添加商户代收余额失败');
            }

            //3.添加账变记录
            $adddata = [
                'merchant_number' => $v['merchant_number'],
                'orderno' => $v['orderno'],
                'type' => 2, //type = 2-代付结算
                'bef_amount' => $merchant['merchant_payment_amount'],
                'change_amount' => $v['account_money'],
                'aft_amount' => $merchant['merchant_payment_amount'] + $v['account_money'],
                'status' => 1,
                'create_time' => time()
            ];
            // dump($adddata);exit;
            $res3 = Db::name('amount_change_record')->insert($adddata);
            if(!$res3){
                exception('添加账变记录失败');
            }

            //4.添加到系统营收中
            $system_aomout = Db::name('config')->field('value')->where('name','system_aomout')->find(); //先查找system_aomout前值

            $res4 = Db::name('config')
                ->where('name','system_aomout')
                ->update([
                    'value'=>['inc', $v['rate_t_money']]
                ]);
            if(!$res4){
                exception('添加系统余额失败');
            }

            //5.添加到系统营收记录
            $adddata = [
                'merchant_number' => $v['merchant_number'],
                'orderno' => $v['eshopno'],
                'type' => 2, //type = 2-代收结算
                'bef_amount' => $system_aomout['value'],
                'change_amount' => $v['rate_t_money'],
                'aft_amount' => $system_aomout['value'] + $v['rate_t_money'],
                'status' => 1,
                'create_time' => time()
            ];
            // dump($adddata);exit;
            $res3 = Db::name('system_amount_change_record')->insert($adddata);
            if(!$res3){
                exception('添加账变记录失败');
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }

        return true;
    }

    /**
     * detail统一查询方法
     */
    public function get_order_detail($row,$channel){
        $status = $row['status'];
        $txnId = '';
        $limit_time = 1*60*30;

        // $row['create_time'] = 1655648210;
        $strtime = is_int($row['create_time'])?$row['create_time']:strtotime($row['create_time']);
        // dump($strtime);exit;
        $calc = time() - $strtime;
        if($channel['channel_type'] == 'Payment'){
            $pay_detail = Paytm::updateTransactionstatus($row,$channel);
            // dump($pay_detail);exit;
            if($pay_detail['code'] == '0000'){
                //成功的情况,修改订单状态
                if(isset($pay_detail['data']['txnType']) && $pay_detail['data']['txnType'] == 'SALE'){
                    $status = 1;
                    $txnId = $pay_detail['data']['txnId'];
                }
            }else{
                if($pay_detail['code'] != '4404'){
                    if($pay_detail['code'] == '400' || $pay_detail['code'] == '402'){
                        $status = $row['status'] == 3?$row['status']:0;

                        //判断是超过3小时支付，超过就直接失败
                        if($calc > $limit_time && $status == 3){
                            $status = 2;
                        }
                    }else{
                        $status = 2;
                    }
                }
            }


        }elseif($channel['channel_type'] == 'payg'){
            // dump($row['tn']);exit;
            if($row['tn']){
                $pay_detail = PayGIntegration::orderDetail($row['tn']);
                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if(isset($pay_detail['data']['OrderKeyId'])){
                        if($pay_detail['data']['OrderStatus'] == 1){
                            $status = 1;
                        }elseif($pay_detail['data']['OrderStatus'] == 0 || $pay_detail['data']['OrderStatus'] == 4){
                            $status = 0;
                            if($calc > $limit_time){
                                $status = 2;
                            }
                        }else{
                            $status = 2;
                        }
                    }
                }
            }
        }elseif($channel['channel_type'] == 'cashfree'){
            // dump($row['tn']);exit;
            if($row){
                $pay_detail = Cashfree::orderDetail($row,$channel);
                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if(isset($pay_detail['data']['order_id'])){
                        if($pay_detail['data']['order_status'] == 'PAID'){
                            $status = 1;
                        }elseif($pay_detail['data']['order_status'] == 'ACTIVE'){
                            $status = $row['status'] == 3?$row['status']:0;
                            //判断是超过3小时支付，超过就直接失败
                            if($calc > $limit_time  && $status == 3){
                                $status = 2;
                            }
                        }else{
                            $status = 2;
                        }
                    }
                }
            }
        }elseif($channel['channel_type'] == 'Kirin'){
            // dump($row['tn']);exit;
            if($row){
                $pay_detail = Kirin::orderDetail($row,$channel); //订单状态 1.等待下发支付 2.未支付 3.订单超时 4.已支付 5.设备池获取订单失败
                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if($pay_detail['data']['data']['status'] == 4){
                        $status = 1;
                    }elseif($pay_detail['data']['data']['status'] == 1 || $pay_detail['data']['data']['status'] == 2){
                        $status = 0;
                    }else{
                        $status = 2;
                    }
                }
            }
        }elseif($channel['channel_type'] == 'fastpay'){
            // dump($row['tn']);exit;
            if($row){
                $pay_detail = Fastpay::orderDetail($row,$channel); //订单状态 1：成功 2：处理中 3：失败

                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if($pay_detail['data']['status'] == 1){
                        $status = 1;
                    }elseif($pay_detail['data']['status'] == 2){
                        $status = 0;
                    }else{
                        $status = 2;
                    }
                }
            }
        }elseif($channel['channel_type'] == 'bzpay'){
            // dump($row['tn']);exit;
            if($row){
                $pay_detail = Bzpay::orderDetail($row,$channel); //订单交易状态 - PROCESSING-进行中 SUCCESS-成功 FAIL-失败

                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if($pay_detail['data']['data']['tradeStatus'] == 'SUCCESS'){
                        $status = 1;
                    }elseif($pay_detail['data']['data']['tradeStatus'] == 'PROCESSING'){
                        $status = 0;
                    }else{
                        $status = 2;
                    }
                }
            }
        }elseif($channel['channel_type'] == 'dspay'){
            // dump($row['tn']);exit;
            if($row){
                $pay_detail = Dspay::orderDetail($row,$channel); //订单交易状态 - 0待支付 1待审核 2支付成功 3支付失败

                // dump($pay_detail);exit;

                if($pay_detail['code'] == '0000'){
                    //成功的情况,修改订单状态
                    if($pay_detail['data']['data']['status'] == 2){
                        $status = 1;
                    }elseif($pay_detail['data']['data']['status'] == 0 || $pay_detail['data']['data']['status'] == 1){
                        $status = 0;
                    }else{
                        $status = 2;
                    }
                }
            }
        }


        $this->callbackMerchant($row,$status,$txnId);
        return $status;
    }


    /**
     * 获取所有账变类型
     */
    public function typelist($is_array = ''){
        $result = [
            "-1" => '下单失败',
            "0" => '进行中',
            "1" => '已支付',
            "2" => '支付失败',
            "3" => '支付中',
            "4" => '支付未确认',
            "5" => '已超时',
        ];

        if($is_array == 'json'){
            return json($result);
        }else{
            return $result;
        }

    }

    /**
     * 获取所有通知类型
     */
    public function notifylist($is_array = ''){
        $result = [
            // 0 => '未知',
            "-1" => '异常',
            0 => '未通知',
            1 => '通知成功',
            2 => '通知失败',
        ];

        if($is_array == 'json'){
            return json($result);
        }else{
            return $result;
        }

    }

}
