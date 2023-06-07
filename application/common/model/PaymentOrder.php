<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\PayGIntegration;
use app\admin\library\Kirin;
use app\admin\library\Fastpay;
use app\admin\library\Bzpay;
use app\admin\library\Dspay;

class PaymentOrder extends Model
{
    protected $name = 'payment_order';
    
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
    *回滚商户代付余额
    */
    public function rollback_order($order){
        Db::startTrans();
        try {
            // 1.回滚商户代付余额
            $merchant = Db::name('merchant')->field('merchant_payment_amount')->where('id',$order['merchant_id'])->find(); //先查找商家前值

            $res1 = Db::name('merchant')
                    ->where('id',$order['merchant_id'])
                    ->update([
                        'merchant_payment_amount'=>['inc', $order['reduce_money']]
                    ]);
            if(!$res1){
                exception('回滚商户代付余额失败');
            }

            //2,添加代付变化记录
            $adddata = [
                'orderno' => $order['orderno'],
                'merchant_id' => $order['merchant_id'],
                'type' => 4, //type = 4-代付预扣失败回退
                'bef_amount' => $merchant['merchant_payment_amount'],
                'change_amount' => $order['reduce_money'],
                'remark' => $order['remark'],
                'relation_id' => $order['id'],
            ];
            $res2 = model('payment_change_record')->addrecord($adddata);
            // echo model('payment_change_record')->getLastsql();exit;
            if(!$res2){
                exception('添加代付变化记录失败');
            }
            
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }

        return true;
    }

    /*
    * 公共回调方法，回调给商户
    */
    public function notifyShop($orderno,$status){
        $order = $this->alias('a')
                    ->field('a.*,c.merchant_key,c.merchant_number')
                    ->join('merchant c','a.merchant_id = c.id','LEFT')
                    ->where(['orderno'=>$orderno])
                    ->find();

        if($order['notify_url']){
            $cond = $this->getCondItem($order,$status);

            try {
                $res = Http::post($order['notify_url'], $cond, $options = []);
                Log::record('apply notify:通知参数'.json_encode($cond),'notice');
                Log::record('apply notify:返回参数'.json_encode($res),'notice');
                if(!$res){
                    $this->update_pay_order($order['id'],2);
                    exception('通知失败');
                }
            } catch (\Exception $e) {
                Log::record('notify:通知失败'.$order['orderno'],'notice');
                // $this->error($e->getMessage());
            }

            if($res){
                if($res == 'success'){
                    $this->update_pay_order($order['id'],1);
                    Log::record('notify:通知成功'.$order['orderno'],'notice');
                }else{
                    $this->update_pay_order($order['id'],2);
                    Log::record('notify:通知失败'.$order['orderno'],'notice');
                }
            }else{
                $this->update_pay_order($order['id'],2);
                Log::record('notify:通知失败'.$order['orderno'],'notice');
            }
        }
        return true;
    }

    /*
    * 获取回调给下游的数据
    */
    public function getCondItem($order,$status){
        $data = [];
        $data['merchantNo'] = $order['merchant_number'];
        $data['sn'] = $order['orderno'];
        $data['merchantSn'] = $order['out_trade_no'];
        $data['orderStatus'] = $status; //订单状态：-1-未返回请求失败-,0-待处理,1-处理中,2-成功,3-失败
        // $data['notifyStatus'] = 1; //通知状态：0，未通知。1，已通知
        $data['payTime'] = $order['callback_time'];
        $data['createTime'] = $order['create_time'];
        $data['money'] = sprintf("%.2f",$order['money']);
        $data['tax'] = sprintf("%.2f",$order['rate_money']); //三方手续费
        $data['remark'] = $order['remark'];
        $sign = Sign::getSign($data,$order['merchant_key']);
        $data['sign'] = $sign;

        return $data;
    }

    /**
     * 获取所有账变类型
     */
    public function typelist($is_array = ''){
        $result = [
            "-1" => '下单失败',
            "0" => '待处理',
            "1" => '处理中',
            "2" => '成功',
            "3" => '失败',
        ];

        if($is_array == 'json'){
            return json($result);
        }else{
            return $result;
        }

    }

    /**
     * detail统一查询方法，0-待处理,1-处理中,2-成功,3-失败
     */
    public function get_order_detail($row){
        $status = $row['status'];

        if(in_array($status,[0,1,3,-1])){
            $txnId = '';
            if($row['channel_type'] == 'payg'){
                // dump($row['tn']);exit;
                if($row['tn'] && $row['transactionId']){
                    $pay_detail = PayGIntegration::FundStatus($row['tn'],$row['transactionId']);
                    // dump($pay_detail);exit;

                    if($pay_detail['code'] == '0000'){
                        //成功的情况,修改订单状态
                        if(isset($pay_detail['data']['PayOutKeyId'])){
                            if($pay_detail['data']['Status'] == 3){
                                $status = 2;
                            }elseif($pay_detail['data']['Status'] == 0){
                                $status = 0;
                            }elseif($pay_detail['data']['Status'] == 1){
                                $status = 1;
                            }else{
                                $status = 3;
                            }

                            $this->callbackMerchant($row,$status,$txnId);
                        }
                    }
                }
            }elseif($row['channel_type'] == 'Kirin'){
                if($row['orderno']){
                    $pay_detail = Kirin::orderquery($row);
                    // dump($pay_detail);exit;

                    if($pay_detail['code'] === '0000'){
                        //成功的情况,修改订单状态
                        ///0.等待审核 2.等待打款 3.上游已打款 4.上游确认到账 6.驳回
                        // -1-未返回请求失败-,0-待处理,1-处理中,2-成功,3-失败'
                        $data = $pay_detail['data']['data'];
                        // dump($data);exit;
                        if(isset($data['order_no'])){
                            if($data['status'] == 4){
                                $status = 2;
                            }elseif($data['status'] == 0 || $data['status'] == 2){
                                $status = 0;
                            }elseif($data['status'] == 3){
                                $status = 1;
                            }else{
                                $status = 3;
                            }

                            $this->callbackMerchant($row,$status,$txnId);
                        }
                    }
                }
            }elseif($row['channel_type'] == 'fastpay'){
                // dump($row['tn']);exit;
                if($row){
                    $pay_detail = Fastpay::orderquery($row); //订单状态 1：成功 2：处理中 3：失败
                    // dump($pay_detail);exit;
                    // 上游状态，-1-未返回请求失败-,0-待处理,1-处理中,2-成功,3-失败
                    if($pay_detail['code'] === '0000'){
                        //成功的情况,修改订单状态
                        if($pay_detail['data']['status'] == 1){
                            //交易成功
                            $status = 2;
                        }else if($pay_detail['data']['status'] == 2){
                            //交易进行中
                            $status = 1;
                        }else if($pay_detail['data']['status'] == 3){
                            //交易失败
                            $status = 3;
                        }else{
                            $status = 3;
                        }

                        $this->callbackMerchant($row,$status,$txnId);
                    }
                }
            }elseif($row['channel_type'] == 'bzpay'){
                // dump($row['tn']);exit;
                if($row){
                    $pay_detail = Bzpay::orderquery($row); //订单状态 1：成功 2：处理中 3：失败
                    // dump($pay_detail);exit;
                    // 交易状态 PROCESSING-进行中 SUCCESS-成功 FAIL-失败
                    if($pay_detail['code'] === '0000'){
                        //成功的情况,修改订单状态
                        if($pay_detail['data']['data']['payStatus'] == 'SUCCESS'){
                            //交易成功
                            $status = 2;
                        }else if($pay_detail['data']['data']['payStatus'] == 'PROCESSING'){
                            //交易进行中
                            $status = 1;
                        }else if($pay_detail['data']['data']['payStatus'] == 'FAIL'){
                            //交易失败
                            $status = 3;
                        }else{
                            $status = 3;
                        }

                        $this->callbackMerchant($row,$status,$txnId);
                    }
                }
            }elseif($row['channel_type'] == 'dspay'){
                // dump($row['tn']);exit;
                if($row){
                    $pay_detail = Dspay::orderquery($row); //0待支付 1待审核 2支付成功 3支付失败
                    // dump($pay_detail);exit;
                    // 交易状态 PROCESSING-进行中 SUCCESS-成功 FAIL-失败
                    if($pay_detail['code'] === '0000'){
                        //成功的情况,修改订单状态
                        if($pay_detail['data']['data']['status'] == 2){
                            //交易成功
                            $status = 2;
                        }else if($pay_detail['data']['data']['status'] == 0){
                            //交易进行中
                            $status = 1;
                        }else if($pay_detail['data']['data']['status'] == 1){
                            //交易进行中
                            $status = 1;
                        }else{
                            //交易失败
                            $status = 3;
                        }

                        $this->callbackMerchant($row,$status,$txnId);
                    }
                }
            }


        }
        
        return $status;
    }

    /*
    * 回调给下游方法，失败需要回退金额
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

            // 如果是失败状态，则需要回滚用户代付余额
            if($status == 3){
                $rollback = $this->rollback_order($row);
                if(!$rollback){
                    // $this->exit_recrod('faild');
                }
            }

            if($url){
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
            
        }
        
        return true;
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
